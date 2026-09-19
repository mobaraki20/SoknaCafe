<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
sokna_module_require('inventory');
require_any_capability(['inventory_operations','inventory_finalize','inventory_manage']);
require dirname(__DIR__) . '/includes/panel_layout.php';

$pdo = db();
$user = current_user();
$userId = (int)($user['id'] ?? 0);
$canCount = user_can_inventory_operate($user);
$canFinalize = user_can_inventory_finalize($user);
$canCost = user_can_inventory_cost($user);
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id < 1) render_recovery_error_page(404, 'شمارش پیدا نشد', 'شناسه شمارش معتبر نیست یا این شمارش دیگر در دسترس نیست.', 'inventory.php?tab=counts', 'بازگشت به شمارش‌ها');

$stmt = $pdo->prepare('SELECT s.*,cu.display_name created_by,fu.display_name finalized_by FROM inventory_count_sessions s LEFT JOIN users cu ON cu.id=s.created_by_user_id LEFT JOIN users fu ON fu.id=s.finalized_by_user_id WHERE s.id=?');
$stmt->execute([$id]);
$session = $stmt->fetch();
if (!$session) render_recovery_error_page(404, 'شمارش پیدا نشد', 'ممکن است شمارش حذف شده باشد یا پیوند قدیمی باشد.', 'inventory.php?tab=counts', 'بازگشت به شمارش‌ها');
$isOpening = (string)$session['session_type'] === 'opening';
if ($isOpening && !user_can_inventory_manage($user)) deny_access_and_return($user);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    $action = (string)($_POST['action'] ?? 'save');
    try {
        if ($action === 'cancel') {
            if (!$canCount) deny_access_and_return($user);
            $pdo->beginTransaction();
            inventory_count_cancel_locked($pdo,$id,$userId);
            $pdo->commit();
            flash('success','شمارش لغو شد؛ هیچ تغییری روی موجودی اعمال نشد.');
            redirect('inventory.php?tab=counts');
        }

        if ($action === 'save' || $action === 'review') {
            if (!$canCount) deny_access_and_return($user);
            $actuals = (array)($_POST['actual_major'] ?? []);
            $costs = (array)($_POST['actual_total_cost'] ?? []);
            $notes = (array)($_POST['note'] ?? []);

            $pdo->beginTransaction();
            $sessionLock=$pdo->prepare('SELECT status,session_type FROM inventory_count_sessions WHERE id=? FOR UPDATE');
            $sessionLock->execute([$id]);$lockedSession=$sessionLock->fetch();
            if(!$lockedSession||(string)$lockedSession['status']!=='draft')throw new RuntimeException('این شمارش دیگر قابل تغییر نیست.');
            $linesStmt=$pdo->prepare('SELECT l.id,l.updated_at FROM inventory_count_lines l WHERE l.session_id=? ORDER BY l.id FOR UPDATE');
            $linesStmt->execute([$id]);$valid=[];
            foreach($linesStmt->fetchAll() as $line)$valid[(int)$line['id']]=$line;
            foreach($valid as $lineId=>$line){
                if(!array_key_exists((string)$lineId,$actuals)&&!array_key_exists($lineId,$actuals))continue;
                $raw=trim((string)($actuals[$lineId]??''));
                if($isOpening&&$action==='review'&&$raw==='')$raw='0';
                inventory_count_update_line_locked(
                    $pdo,$id,$lineId,$raw,
                    $isOpening?(string)($costs[$lineId]??''):null,
                    (string)($notes[$lineId]??''),$userId,null
                );
            }
            $pdo->commit();
            if ($action === 'review') redirect('inventory_count.php?id='.$id.'&review=1');
            flash('success','پیشرفت شمارش ذخیره شد.');
            redirect('inventory.php?tab=counts');
        }

        if ($action === 'finalize') {
            if (!$canFinalize) deny_access_and_return($user);
            $pdo->beginTransaction();
            $result = inventory_count_finalize_locked($pdo,$id,$userId);
            $pdo->commit();
            flash('success','شمارش نهایی شد و '.fa_digits((int)$result['differences']).' مغایرت روی موجودی اعمال شد.');
            redirect('inventory_count.php?id='.$id);
        }
        throw new RuntimeException('عملیات معتبر نیست.');
    } catch (RuntimeException|InvalidArgumentException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('error',$e->getMessage());
        redirect('inventory_count.php?id='.$id.($action === 'finalize' ? '&review=1' : ''));
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('inventory count '.$id.': '.$e->getMessage());
        flash('error','انجام عملیات شمارش ممکن نشد. دوباره تلاش کن.');
        redirect('inventory_count.php?id='.$id.($action === 'finalize' ? '&review=1' : ''));
    }
}

$stmt = $pdo->prepare('SELECT l.*,i.name,i.category,i.base_unit FROM inventory_count_lines l JOIN inventory_items i ON i.id=l.inventory_item_id WHERE l.session_id=? ORDER BY i.category,i.name,i.id');
$stmt->execute([$id]);
$lines = $stmt->fetchAll();
$categoryLabels = inventory_category_labels(false,$pdo);
$scopeType = (string)($session['scope_type'] ?? 'full');
$scopeCategoryKey = (string)($session['scope_category_key'] ?? '');
$scopeLabel = $scopeType === 'category' ? ('دسته: '.($categoryLabels[$scopeCategoryKey] ?? 'دسته انتخاب‌شده')) : 'کل انبار';
$counted = 0; $differences = 0; $knownValue = 0;
foreach ($lines as $line) {
    if ($line['actual_quantity'] === null) continue;
    $counted++;
    $actual = (int)$line['actual_quantity'];
    $diff = $actual - (int)$line['system_quantity_snapshot'];
    if ($diff !== 0) $differences++;
    if ($line['unit_cost_snapshot'] !== null) $knownValue += (int)round(abs($diff) * (float)$line['unit_cost_snapshot']);
}
$progressPercent = count($lines)>0 ? (int)round(($counted / count($lines)) * 100) : 0;
$review = isset($_GET['review']) || (string)$session['status'] === 'finalized' || ((string)$session['status'] === 'draft' && !$canCount);
$problemLines = []; $matchingLines = [];
foreach ($lines as $line) {
    $actual = $line['actual_quantity'] === null ? null : (int)$line['actual_quantity'];
    $system = (int)$line['system_quantity_snapshot'];
    $isProblem = $actual === null;
    if ($actual !== null && $actual !== $system) $isProblem = true;
    if ($isProblem) $problemLines[] = $line; else $matchingLines[] = $line;
}

panel_header($isOpening ? 'موجودی اولیه' : 'شمارش موجودی','inventory');
panel_subnav(['stock'=>['inventory.php','موجودی'],'movements'=>['inventory.php?tab=movements','گردش'],'counts'=>['inventory.php?tab=counts','شمارش‌ها']],'counts','بخش‌های انبار');
?>
<div class="inventory-count-progress">
  <div class="inventory-count-progress-main"><div class="panel-copy-stack"><strong><?= e((string)$session['title']) ?></strong><small class="muted"><?= !$isOpening?e($scopeLabel).' · ':'' ?><span data-inventory-live-progress aria-live="polite"><?= fa_digits($counted) ?> از <?= fa_digits(count($lines)) ?> قلم</span></small></div><?php if((string)$session['status']==='draft'): ?><div class="inventory-count-meter" aria-label="پیشرفت شمارش"><span data-inventory-live-meter style="width:<?= max(0,min(100,$progressPercent)) ?>%"></span></div><?php endif; ?></div>
  <div class="inventory-count-head-actions"><?php if((string)$session['status']==='draft' && $review && $canCount): ?><a class="btn btn-sm btn-light" href="inventory_count.php?id=<?= $id ?>">ادامه شمارش</a><?php endif; ?><?php if((string)$session['status']==='draft' && $canCount): ?><div class="row-action-menu" data-action-menu data-action-menu-label="گزینه‌های شمارش"><button type="button" class="btn btn-sm btn-light" data-action-menu-trigger aria-expanded="false" aria-haspopup="menu" aria-label="گزینه‌های بیشتر"><?= ui_icon('more') ?></button><div class="row-action-popover" data-action-menu-popover role="menu"><form method="post" data-confirm="مقادیر ثبت‌شده این شمارش کنار گذاشته می‌شوند و موجودی تغییر نمی‌کند." data-confirm-title="لغو شمارش؟" data-confirm-ok="لغو شمارش" data-confirm-danger="1"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><button role="menuitem" class="danger-action" name="action" value="cancel"><?= ui_icon('trash') ?> لغو شمارش</button></form></div></div><?php endif; ?></div>
</div>

<?php if((string)$session['status']==='cancelled'): ?>
<div class="panel-helper-note"><span><?= ui_icon('info') ?></span><div><strong>این شمارش لغو شده است.</strong> هیچ تغییری روی موجودی ثبت نشده است.</div></div>
<?php elseif(!$review && (string)$session['status']==='draft' && $canCount): ?>
<div class="panel-helper-note inventory-count-helper"><span><?= ui_icon('info') ?></span><div><strong>شمارش کور فعال است.</strong> موجودی ثبت‌شده سیستم نمایش داده نمی‌شود؛ مقدار واقعی را وارد کنید و برای کالای بدون موجودی عدد صفر بزنید.</div></div>
<div class="inventory-count-tools" data-inventory-count-tools aria-label="فیلتر شمارش">
  <div class="inventory-count-filters"><button type="button" class="btn btn-sm btn-light is-active" data-inventory-count-filter="all" aria-pressed="true">همه اقلام</button><button type="button" class="btn btn-sm btn-light" data-inventory-count-filter="empty" aria-pressed="false">شمارش‌نشده‌ها <span data-inventory-empty-count><?= fa_digits(max(0,count($lines)-$counted)) ?></span></button></div>
  <button type="button" class="btn btn-sm btn-light" data-inventory-count-next>رفتن به مورد بعدی</button>
</div>
<form method="post" id="inventoryCountForm" data-inventory-count-form data-inventory-count-session="<?= $id ?>" data-inventory-autofocus="<?= isset($_GET['started'])?'1':'0' ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><div class="inventory-count-list">
<?php $lastCategory=null; foreach($lines as $index=>$line): $categoryLabel=$categoryLabels[(string)$line['category']]??'بدون دسته‌بندی'; if($categoryLabel!==$lastCategory): if($lastCategory!==null): ?></div></section><?php endif; $lastCategory=$categoryLabel; ?><section class="inventory-count-group" data-inventory-count-group><h3><?= e($categoryLabel) ?></h3><div class="inventory-count-group-list"><?php endif; ?><div class="inventory-count-line" data-inventory-count-line><div class="inventory-count-copy"><strong><?= e((string)$line['name']) ?></strong></div><div class="inventory-count-entry"><input class="form-control" inputmode="<?= (string)$line['base_unit']==='count'?'numeric':'decimal' ?>" enterkeyhint="<?= $index===count($lines)-1?'done':'next' ?>" autocomplete="off" name="actual_major[<?= (int)$line['id'] ?>]" value="<?= $line['actual_quantity']===null?'':e(numeric_input_display_value(inventory_base_to_major_value((int)$line['actual_quantity'],(string)$line['base_unit']))) ?>" placeholder="مقدار" aria-label="مقدار واقعی <?= e((string)$line['name']) ?>" data-inventory-count-quantity><span class="inventory-count-unit"><?= e(inventory_major_unit_label((string)$line['base_unit'])) ?></span><?php if($isOpening): ?><details class="inventory-count-cost" <?= $line['actual_total_cost']!==null?'open':'' ?>><summary>ارزش تقریبی اولیه، اختیاری</summary><input class="form-control inventory-opening-cost" inputmode="numeric" enterkeyhint="done" autocomplete="off" name="actual_total_cost[<?= (int)$line['id'] ?>]" value="<?= $line['actual_total_cost']===null?'':e(money_input_display_value((string)$line['actual_total_cost'])) ?>" data-money-input placeholder="تومان" aria-label="ارزش تقریبی اولیه <?= e((string)$line['name']) ?>"></details><?php endif; ?></div></div><?php endforeach; ?><?php if($lastCategory!==null): ?></div></section><?php endif; ?>
</div><div class="inventory-sticky-actions inventory-count-actions"><button class="btn btn-primary" name="action" value="review">مرور شمارش</button><button class="btn btn-light" name="action" value="save">ذخیره و خروج</button></div></form>
<?php else: ?>
<div class="inventory-toolbar"><div class="panel-copy-stack"><strong><?= $isOpening?'مرور موجودی اولیه':'مرور مغایرت‌ها' ?></strong><small class="muted"><?= $isOpening?fa_digits(count($problemLines)).' قلم هنوز نیاز به مرور دارد':fa_digits($differences).' قلم با موجودی دفتری تفاوت دارد' ?><?php if(!$isOpening&&count($matchingLines)): ?> · <?= fa_digits(count($matchingLines)) ?> قلم بدون مغایرت<?php endif; ?></small></div><?php if($canCost&&$knownValue>0&&!$isOpening): ?><div><small class="muted">ارزش تقریبی مغایرت‌های قیمت‌دار</small><strong><?= e(toman($knownValue)) ?></strong></div><?php endif; ?></div>
<div class="inventory-variance-list"><?php if(!$problemLines): ?><div class="inventory-empty"><?= $isOpening?'همه مقادیر برای ثبت آماده‌اند.':'مغایرتی پیدا نشد؛ شمارش با موجودی سیستم برابر است.' ?></div><?php endif; ?><?php foreach($problemLines as $line): ?>
<?php if($line['actual_quantity']===null): ?><div class="inventory-variance"><div class="inventory-variance-main"><strong><?= e((string)$line['name']) ?></strong><small><?= $isOpening?'هنوز مرور نشده است.':'هنوز شمارش نشده است.' ?></small></div><div class="inventory-variance-diff"><small>وضعیت</small><strong>ثبت نشده</strong></div></div><?php continue; endif; ?>
<?php $actual=(int)$line['actual_quantity'];$system=(int)$line['system_quantity_snapshot'];$diff=$actual-$system; ?><div class="inventory-variance"><div class="inventory-variance-main"><strong><?= e((string)$line['name']) ?></strong><?php if($isOpening): ?><small>موجودی اولیه: <?= e(inventory_format_quantity($actual,(string)$line['base_unit'])) ?><?php if($canCost&&$line['actual_total_cost']!==null): ?> · <?= e(toman((int)$line['actual_total_cost'])) ?><?php endif; ?></small><?php else: ?><small>سیستم <?= e(inventory_format_quantity($system,(string)$line['base_unit'])) ?> · شمارش <?= e(inventory_format_quantity($actual,(string)$line['base_unit'])) ?></small><?php endif; ?></div><div class="inventory-variance-diff <?= $diff<0?'negative':($diff>0?'positive':'') ?>"><small><?= $isOpening?'ثبت می‌شود':'اختلاف' ?></small><strong><?= e(inventory_format_quantity($isOpening?$actual:$diff,(string)$line['base_unit'])) ?></strong></div></div><?php endforeach; ?></div>
<?php if($matchingLines): ?><details class="inventory-matching-details"><summary><?= fa_digits(count($matchingLines)) ?> قلم بدون مغایرت</summary><div class="inventory-matching-list"><?php foreach($matchingLines as $line): ?><span><?= e((string)$line['name']) ?></span><?php endforeach; ?></div></details><?php endif; ?>
<?php if((string)$session['status']==='draft'): ?><form method="post" class="form-grid inventory-review-card" data-confirm="<?= e($isOpening?'موجودی شمارش‌شده به‌عنوان موجودی شروع ثبت می‌شود و انبار آماده استفاده خواهد شد.':'اختلاف شمارش با موجودی ثبت‌شده روی مقدار نهایی انبار اعمال می‌شود.') ?>" data-confirm-title="نهایی‌کردن شمارش؟" data-confirm-ok="نهایی‌کردن شمارش"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><div class="form-group full actions inventory-sticky-actions"><?php if($canFinalize): ?><button class="btn btn-primary" name="action" value="finalize">نهایی‌کردن شمارش</button><?php endif; ?><?php if($canCount): ?><a class="btn btn-light" href="inventory_count.php?id=<?= $id ?>">بازگشت به شمارش</a><?php else: ?><a class="btn btn-light" href="inventory.php?tab=counts">بازگشت</a><?php endif; ?></div><?php if(!$canFinalize): ?><small class="muted">می‌توانی شمارش را ثبت و مرور کنی؛ نهایی‌سازی نیاز به دسترسی جداگانه دارد.</small><?php elseif(!$canCount): ?><small class="muted">این حساب فقط می‌تواند نتیجه ثبت‌شده را مرور و نهایی کند؛ مقدار شمارش از این صفحه قابل تغییر نیست.</small><?php endif; ?></form><?php else: ?><div class="panel-helper-note"><span><?= ui_icon('info') ?></span><div><strong>این شمارش نهایی شده است.</strong> اگر بعداً اشتباهی پیدا شد، شمارش یا اصلاح جدید ثبت می‌شود.</div></div><?php endif; ?>
<?php endif; ?>
<?php panel_footer(); ?>
