<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
sokna_module_require('inventory');
require_login();
if (!user_can_inventory_operate()) deny_access_and_return();
require dirname(__DIR__) . '/includes/panel_layout.php';

$pdo = db();
inventory_seed_if_empty($pdo);
$reconciliationRequired = inventory_reconciliation_required();
if (!inventory_initialized()) {
    flash('warning','ابتدا موجودی اولیه را نهایی کن؛ بعد از آن عملیات روزانه انبار فعال می‌شود.');
    redirect('inventory.php');
}
$userId = (int)(current_user()['id'] ?? 0);

$openCount = inventory_open_count_session($pdo);
$categories = $pdo->query("SELECT c.category_key,c.name,COUNT(i.id) item_count
    FROM inventory_categories c
    LEFT JOIN inventory_items i ON i.category=c.category_key AND i.active=1
    WHERE c.active=1
    GROUP BY c.category_key,c.name,c.sort_order
    HAVING COUNT(i.id)>0
    ORDER BY c.sort_order,c.name,c.category_key")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    try {
        if ($openCount) throw new RuntimeException(inventory_open_count_message());
        $title = text_substr(trim((string)($_POST['title'] ?? '')),0,160);
        if ($reconciliationRequired && $title === '') $title = 'بازشماری پس از فعال‌سازی انبار';
        $scopeType = $reconciliationRequired ? 'full' : (string)($_POST['scope_type'] ?? 'full');
        if (!in_array($scopeType,['full','category'],true)) $scopeType = 'full';
        $scopeCategoryKey = $scopeType === 'category' ? text_substr(trim((string)($_POST['scope_category_key'] ?? '')),0,64) : null;
        $id = inventory_count_start($pdo,$title,'periodic',$userId,$scopeType,$scopeCategoryKey);
        redirect('inventory_count.php?id='.$id.'&started=1');
    } catch (RuntimeException $e) {
        flash('error',$e->getMessage());
    } catch (Throwable $e) {
        error_log('inventory count start: '.$e->getMessage());
        flash('error','شروع شمارش انجام نشد. دوباره تلاش کن.');
    }
}

$scopeTypeValue = $reconciliationRequired ? 'full' : (in_array((string)($_POST['scope_type'] ?? 'full'),['full','category'],true) ? (string)($_POST['scope_type'] ?? 'full') : 'full');
panel_header('شروع شمارش','inventory');
panel_subnav(['stock'=>['inventory.php','موجودی'],'movements'=>['inventory.php?tab=movements','گردش'],'counts'=>['inventory.php?tab=counts','شمارش‌ها']],'counts','بخش‌های انبار');
?>
<?php if($openCount): ?>
<section class="card inventory-review-card"><div class="card-body"><div class="inventory-attention"><strong>یک شمارش در حال انجام است</strong><p><?= e((string)$openCount['title']) ?> هنوز نهایی یا لغو نشده است.</p></div><div class="inventory-legacy-count-list"><a class="panel-list-row" href="inventory_count.php?id=<?= (int)$openCount['id'] ?>"><span class="panel-list-primary"><strong><?= e((string)$openCount['title']) ?></strong><small><?= fa_digits((int)$openCount['counted_lines']) ?> از <?= fa_digits((int)$openCount['total_lines']) ?> قلم ثبت شده</small></span><span class="btn btn-sm btn-light">بازکردن</span></a></div><div class="actions"><a class="btn btn-light" href="inventory.php?tab=counts">بازگشت</a></div></div></section>
<?php else: ?>
<section class="card inventory-review-card"><div class="card-body"><h2><?= $reconciliationRequired?'بازشماری کامل انبار':'شمارش دوره‌ای' ?></h2><p class="muted"><?= $reconciliationRequired?'برای شروع دوباره مصرف خودکار، موجودی واقعی همه اقلام را ثبت کن. تا پایان این شمارش، سفارش‌ها بدون اثر روی انبار ادامه می‌دهند.':'عدد دفتری هنگام شمارش پنهان می‌ماند. می‌توانی کل انبار یا فقط یک دسته را بشماری؛ اقلام همان لحظه شروع ثابت می‌شوند.' ?></p><form method="post" class="form-grid"><?= csrf_field() ?>
<?php if($reconciliationRequired): ?><input type="hidden" name="scope_type" value="full"><div class="panel-helper-note full"><strong>محدوده: کل انبار</strong><span>بازفعال‌سازی با شمارش یک دسته کامل نمی‌شود.</span></div><?php else: ?>
<div class="form-group"><label for="inventoryCountScope">محدوده شمارش</label><select class="form-control" id="inventoryCountScope" name="scope_type" data-choice-mode="compact"><option value="full" <?= $scopeTypeValue==='full'?'selected':'' ?>>کل انبار</option><option value="category" <?= $scopeTypeValue==='category'?'selected':'' ?>>یک دسته</option></select><small class="muted">برای مقیاس فعلی سکنا فقط یک شمارش می‌تواند هم‌زمان باز باشد.</small></div>
<div class="form-group" data-panel-condition-source="inventoryCountScope" data-panel-condition-value="category"><label for="inventoryCountCategory">دسته</label><select class="form-control" id="inventoryCountCategory" name="scope_category_key" data-choice-mode="adaptive" data-choice-search="true"><option value="" disabled <?= trim((string)($_POST['scope_category_key']??''))===''?'selected':'' ?>>انتخاب دسته</option><?php foreach($categories as $category): ?><option value="<?= e((string)$category['category_key']) ?>" <?= (string)($_POST['scope_category_key']??'')===(string)$category['category_key']?'selected':'' ?>><?= e((string)$category['name']) ?> · <?= fa_digits((int)$category['item_count']) ?> کالا</option><?php endforeach; ?></select><small class="muted">تغییر دسته یا فعال‌بودن کالاها بعد از شروع، اعضای همین شمارش را عوض نمی‌کند.</small></div>
<?php endif; ?>
<details class="form-disclosure full"><summary><?= ui_icon('edit') ?><span><strong>نام‌گذاری شمارش</strong><small>اختیاری؛ اگر خالی بماند «شمارش دوره‌ای» ثبت می‌شود.</small></span></summary><div class="disclosure-body"><div class="form-group full"><label>عنوان شمارش</label><input class="form-control" name="title" autocomplete="off" enterkeyhint="done" value="<?= e((string)($_POST['title']??'')) ?>" placeholder="مثلاً شمارش پایان مرداد"></div></div></details>
<div class="form-group full actions"><button class="btn btn-primary">شروع شمارش</button><a class="btn btn-light" href="inventory.php?tab=counts">انصراف</a></div></form></div></section>
<?php endif; ?>
<?php panel_footer(); ?>
