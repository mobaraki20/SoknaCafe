<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
sokna_module_require('expenses');
require_login(['admin']);
require dirname(__DIR__) . '/includes/panel_layout.php';
$pdo=db();$userId=(int)current_user()['id'];
$mode=(string)($_GET['mode']??'create');$targetId=(int)($_GET['id']??0);$target=null;
if(in_array($mode,['correct','reverse'],true)&&$targetId>0){
    $st=$pdo->prepare("SELECT e.*,c.name category_name,fp.status period_status FROM expenses e JOIN expense_categories c ON c.category_key=e.category_key JOIN financial_periods fp ON fp.id=e.financial_period_id WHERE e.id=? AND e.status='committed' LIMIT 1");$st->execute([$targetId]);$target=$st->fetch(PDO::FETCH_ASSOC)?:null;
    if(!$target){flash('error','هزینه برای اصلاح پیدا نشد.');redirect('expenses.php');}
}
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf($_POST['csrf_token']??null);$action=(string)($_POST['action']??'');
    try{
        $requestToken=trim((string)($_POST['request_token']??''));if(!preg_match('/^[a-f0-9]{32}$/',$requestToken))throw new RuntimeException('فرم منقضی شده؛ صفحه را تازه کن.');
        $pdo->beginTransaction();
        if($action==='create'||$action==='correct'){
            $amount=parse_toman_amount_text((string)($_POST['amount']??''));if(($amount??0)<1)throw new RuntimeException('مبلغ هزینه را به تومان وارد کن.');
            $occurredAt=parse_optional_jalali_datetime((string)($_POST['occurred_date_j']??''),(string)($_POST['occurred_time']??''),'زمان هزینه')??date('Y-m-d H:i:s');
            $category=(string)($_POST['category_key']??'');$description=(string)($_POST['description']??'');
            if($action==='create') expense_create_locked($pdo,$category,(int)$amount,$occurredAt,$description,$userId,'local:expense:'.$requestToken);
            else{
                $expenseId=(int)($_POST['expense_id']??0);$reason=(string)($_POST['correction_reason']??'');
                expense_correct_locked($pdo,$expenseId,$category,(int)$amount,$occurredAt,$description,$reason,$userId,'local:expense-correct:'.$requestToken);
            }
            $pdo->commit();flash('success',$action==='create'?'هزینه ثبت شد.':'اصلاح هزینه به‌صورت سند برگشت و سند جایگزین ثبت شد.');redirect('expenses.php');
        }
        if($action==='reverse'){
            expense_reverse_locked($pdo,(int)($_POST['expense_id']??0),(string)($_POST['reason']??''),$userId,'local:expense-reverse:'.$requestToken);
            $pdo->commit();flash('success','برگشت هزینه بدون حذف سند اصلی ثبت شد.');redirect('expenses.php');
        }
        throw new RuntimeException('عملیات هزینه معتبر نیست.');
    }catch(RuntimeException|InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();flash('error',$e->getMessage());}
    catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('expenses admin: '.$e->getMessage());flash('error','عملیات هزینه ذخیره نشد. دوباره تلاش کن.');}
}
$categories=expense_categories($pdo,true);$rows=expense_recent_rows($pdo,120);$requestToken=bin2hex(random_bytes(16));
$todaySummary=['committed_count'=>0,'reversal_count'=>0,'net_amount'=>0];
try{$pdo->beginTransaction();$period=financial_period_for_date_locked($pdo,business_current_date(),$userId);$pdo->commit();$todaySummary=expense_period_summary($pdo,(int)$period['id']);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('expense current period: '.$e->getMessage());$period=null;}
$formTarget=$target&&$mode==='correct'?$target:null;$reverseTarget=$target&&$mode==='reverse'?$target:null;
$defaultDate=$formTarget?jalali_date_input((string)$formTarget['occurred_at']):jalali_date_input(business_current_date());
$defaultTime=$formTarget?date('H:i',strtotime((string)$formTarget['occurred_at'])):date('H:i');
panel_header('هزینه‌های کافه','expenses');
?>
<div class="financial-workspace panel-page-flow" data-visual-quality-page="expenses">
<section class="card financial-record-hero"><div class="card-head"><div class="panel-copy-stack"><small class="muted">هزینه‌های عمومی کافه</small><h2><?= e(toman($todaySummary['net_amount'])) ?></h2><small>خالص هزینه در <?= e((string)($period['title']??'دوره جاری')) ?> · خرید مواد و کالا از مسیر انبار/خرید ثبت می‌شود و اینجا دوباره شمرده نمی‌شود.</small></div></div></section>

<?php if($reverseTarget): ?>
<section class="card financial-record-section"><div class="card-head"><div><h2>برگشت هزینه</h2><small><?= e((string)$reverseTarget['category_name']) ?> · <?= e(toman((int)$reverseTarget['amount'])) ?> · <?= e(format_jalali_human_datetime((string)$reverseTarget['occurred_at'])) ?></small></div></div><div class="card-body"><form method="post" class="form-grid" data-confirm="سند اصلی حذف نمی‌شود؛ یک سند برگشت جداگانه ثبت خواهد شد." data-confirm-title="ثبت برگشت هزینه؟" data-confirm-ok="ثبت برگشت"><?= csrf_field() ?><input type="hidden" name="action" value="reverse"><input type="hidden" name="expense_id" value="<?= (int)$reverseTarget['id'] ?>"><input type="hidden" name="request_token" value="<?= e($requestToken) ?>"><div class="form-group full"><label>دلیل برگشت</label><textarea class="form-control" name="reason" maxlength="500" rows="3" required></textarea></div><div class="form-group full actions"><button class="btn btn-danger">ثبت برگشت</button><a class="btn btn-light" href="expenses.php">انصراف</a></div></form></div></section>
<?php else: ?>
<section class="card financial-record-section"><div class="card-head"><div><h2><?= $formTarget?'اصلاح هزینه':'ثبت هزینه' ?></h2><small><?= $formTarget?'سند قبلی حذف یا ویرایش نمی‌شود؛ برگشت و جایگزین به‌صورت اتمیک ثبت می‌شوند.':'فقط هزینه‌های عمومی؛ خرید موجودی از صفحه خرید ثبت می‌شود.' ?></small></div><?php if($formTarget): ?><span class="panel-status-badge is-warning">اصلاح سند <?= fa_digits((int)$formTarget['id']) ?></span><?php endif; ?></div><div class="card-body"><form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $formTarget?'correct':'create' ?>"><input type="hidden" name="request_token" value="<?= e($requestToken) ?>"><?php if($formTarget): ?><input type="hidden" name="expense_id" value="<?= (int)$formTarget['id'] ?>"><?php endif; ?>
<div class="form-group"><label>دسته هزینه</label><select class="form-control" name="category_key" required data-choice-mode="compact"><?php foreach($categories as $cat): ?><option value="<?= e((string)$cat['category_key']) ?>" <?= ($formTarget['category_key']??'')===$cat['category_key']?'selected':'' ?>><?= e((string)$cat['name']) ?></option><?php endforeach; ?></select></div>
<div class="form-group"><label>مبلغ، تومان</label><input class="form-control" name="amount" inputmode="numeric" enterkeyhint="next" autocomplete="off" data-money-input required value="<?= $formTarget?e(money_input_display_value((int)$formTarget['amount'])):'' ?>"></div>
<div class="form-group"><label>تاریخ وقوع</label><div class="jalali-date-control"><input class="form-control" id="expenseOccurredDate" name="occurred_date_j" data-jalali-date inputmode="none" autocomplete="off" value="<?= e($defaultDate) ?>" required><button class="jalali-date-button" type="button" data-open-jalali="expenseOccurredDate" aria-label="انتخاب تاریخ هزینه"><?= ui_icon('calendar') ?></button></div></div>
<div class="form-group"><label>زمان</label><input class="form-control" type="time" name="occurred_time" value="<?= e($defaultTime) ?>" step="60" required></div>
<div class="form-group full"><label>شرح — اختیاری</label><textarea class="form-control" name="description" maxlength="500" rows="2"><?= $formTarget?e((string)($formTarget['description']??'')):'' ?></textarea></div>
<?php if($formTarget): ?><div class="form-group full"><label>دلیل اصلاح</label><textarea class="form-control" name="correction_reason" maxlength="500" rows="2" required></textarea></div><?php endif; ?>
<div class="form-group full actions"><button class="btn btn-primary"><?= $formTarget?'ثبت اصلاح':'ثبت هزینه' ?></button><?php if($formTarget): ?><a class="btn btn-light" href="expenses.php">انصراف</a><?php endif; ?></div></form></div></section>
<?php endif; ?>

<section class="card financial-record-section"><div class="card-head"><div><h2>آخرین اسناد هزینه</h2><small>سندهای اصلی، برگشت‌ها و اصلاحات به‌ترتیب زمانی؛ هیچ سند متعهدشده‌ای حذف نمی‌شود.</small></div></div><div class="financial-list">
<?php if(!$rows): ?><div class="empty-state">هنوز هزینه‌ای ثبت نشده است.</div><?php endif; ?>
<?php foreach($rows as $row): $isReversal=(string)$row['status']==='reversal';$isReversed=!empty($row['reversal_id']);$canChange=!$isReversal&&!$isReversed&&(string)$row['period_status']==='open'; ?>
<article class="financial-row"><div class="financial-row-main"><strong><?= e((string)$row['category_name']) ?><?php if($isReversal): ?> · برگشت<?php endif; ?></strong><span><?= e(format_jalali_human_datetime((string)$row['occurred_at'])) ?> · <?= e((string)($row['actor_name']?:'سامانه')) ?></span><small><?= e((string)($row['description']?:($isReversal?'برگشت سند هزینه':'بدون شرح'))) ?> · <?= e((string)$row['period_title']) ?></small><?php if($isReversed): ?><span class="panel-status-badge is-muted">برگشت‌خورده</span><?php endif; ?></div><div class="financial-row-amount"><strong><?= $isReversal?'− ':'' ?><?= e(toman((int)$row['amount'])) ?></strong><small><?= $isReversal?'کاهنده هزینه':'هزینه' ?></small></div><?php if($canChange): ?><div class="row-actions"><a class="btn btn-sm btn-light" href="expenses.php?mode=correct&id=<?= (int)$row['id'] ?>">اصلاح</a><a class="btn btn-sm btn-danger" href="expenses.php?mode=reverse&id=<?= (int)$row['id'] ?>">برگشت</a></div><?php endif; ?></article>
<?php endforeach; ?>
</div></section>
</div>
<?php panel_footer(); ?>
