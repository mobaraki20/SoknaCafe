<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
sokna_module_require('supply');
require_login();
if (!user_can_manage_purchases()) deny_access_and_return();
require dirname(__DIR__) . '/includes/panel_layout.php';
$pdo=db();$user=current_user();$userId=(int)($user['id']??0);
inventory_seed_if_empty($pdo);inventory_process_pending_order_events(10);
$groups=array_values(array_filter(supply_purchase_groups($pdo),static fn(array $g): bool => (int)$g['preparing_quantity_base']>0 && !(bool)$g['unknown']));
$unitsByItem=[];foreach($groups as $g){$iid=(int)$g['item_id'];$unitsByItem[$iid]=inventory_operational_purchase_units($pdo,$iid);}
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf($_POST['csrf_token']??null);
    try{
        $raw=(array)($_POST['lines']??[]);$lines=[];
        foreach($raw as $line){if(!is_array($line)||empty($line['include']))continue;$line['supplier']=$_POST['supplier']??'';$line['occurred_date_j']=$_POST['occurred_date_j']??'';$line['occurred_time']=$_POST['occurred_time']??'';$line['note']=$_POST['note']??'';$lines[]=$line;}
        $pdo->beginTransaction();$result=supply_receive_batch_locked($pdo,$lines,(string)($_POST['batch_token']??''),$userId);$pdo->commit();
        flash('success',fa_digits(count($result['results'])).' قلم در یک ثبت گروهی تحویل و وارد انبار شد.');redirect('purchases.php');
    }catch(RuntimeException|InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();flash('error',$e->getMessage());}
    catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('purchase batch receive: '.$e->getMessage());flash('error','ثبت گروهی خرید ذخیره نشد. دوباره تلاش کن.');}
}
$batchToken=bin2hex(random_bytes(16));
panel_header('ثبت گروهی تحویل خرید','purchases');
?>
<div class="panel-surface-stack purchase-page">
<header class="inventory-toolbar"><div class="panel-copy-stack"><strong>ثبت گروهی تحویل خرید</strong><small class="muted">همه ردیف‌های انتخاب‌شده ابتدا بررسی می‌شوند؛ اگر یکی نامعتبر باشد هیچ ورود انباری ثبت نمی‌شود.</small></div><div class="inventory-toolbar-actions"><a class="btn btn-light" href="purchases.php">بازگشت به خرید</a></div></header>
<?php if(count($groups)<2): ?>
<section class="card"><div class="card-body"><div class="inventory-empty">برای ثبت گروهی حداقل دو قلم شناخته‌شده باید در وضعیت «در حال خرید» باشند.</div></div></section>
<?php else: ?>
<form method="post" data-confirm="همه اقلام انتخاب‌شده در یک تراکنش ثبت و وارد انبار می‌شوند." data-confirm-title="ثبت گروهی تحویل؟" data-confirm-ok="ثبت تحویل‌ها"><?= csrf_field() ?><input type="hidden" name="batch_token" value="<?= e($batchToken) ?>">
<section class="card"><div class="card-head"><div><h2>اقلام تحویل‌شده</h2><small>اگر بسته یا واحد خرید متغیر است، مقدار واقعی کل را هم وارد کن.</small></div></div><div class="panel-list-card">
<?php foreach($groups as $i=>$g): $iid=(int)$g['item_id'];$units=$unitsByItem[$iid]??[];$base=(string)$g['base_unit']; ?>
<article class="panel-list-row"><div class="panel-list-primary"><label class="check-line"><input type="checkbox" name="lines[<?= $i ?>][include]" value="1" checked><span><strong><?= e((string)$g['name']) ?></strong><small>در حال خرید: <?= e(inventory_format_quantity((int)$g['preparing_quantity_base'],$base)) ?></small></span></label><input type="hidden" name="lines[<?= $i ?>][group_key]" value="<?= e((string)$g['group_key']) ?>"><input type="hidden" name="lines[<?= $i ?>][expected_preparing_quantity_base]" value="<?= (int)$g['preparing_quantity_base'] ?>"></div>
<div class="panel-list-secondary"><div class="form-grid">
<div class="form-group"><label>شکل خرید</label><select class="form-control" name="lines[<?= $i ?>][purchase_unit_id]" data-batch-unit-select><option value="0" data-mode="base">مستقیم · <?= e(inventory_major_unit_label($base)) ?></option><?php foreach($units as $u): ?><option value="<?= (int)$u['id'] ?>" data-mode="<?= e((string)$u['conversion_mode']) ?>"><?= e((string)$u['name']) ?></option><?php endforeach; ?></select></div>
<div class="form-group"><label>تعداد / مقدار تحویل</label><input class="form-control" name="lines[<?= $i ?>][unit_count]" inputmode="decimal" autocomplete="off" required value="<?= e(inventory_base_to_major_value((int)$g['preparing_quantity_base'],$base)) ?>"></div>
<div class="form-group"><label>مقدار واقعی کل، فقط بسته متغیر</label><input class="form-control" name="lines[<?= $i ?>][actual_major_quantity]" inputmode="decimal" autocomplete="off" placeholder="در صورت نیاز"></div>
<div class="form-group"><label>مبلغ کل این قلم، تومان — اختیاری</label><input class="form-control" name="lines[<?= $i ?>][total_cost]" inputmode="numeric" autocomplete="off" data-money-input placeholder="اختیاری"></div>
</div></div></article>
<?php endforeach; ?>
</div></section>
<section class="card"><div class="card-head"><div><h2>اطلاعات مشترک تحویل</h2><small>در صورت نیاز برای همه ردیف‌های این ثبت اعمال می‌شود.</small></div></div><div class="card-body"><div class="form-grid"><div class="form-group"><label>تأمین‌کننده — اختیاری</label><input class="form-control" name="supplier" maxlength="160" autocomplete="off"></div><div class="form-group"><label>تاریخ تحویل — اختیاری</label><div class="jalali-date-control"><input class="form-control" id="batchReceiveDate" name="occurred_date_j" data-jalali-date inputmode="none" autocomplete="off" placeholder="انتخاب تاریخ"><button class="jalali-date-button" type="button" data-open-jalali="batchReceiveDate" aria-label="انتخاب تاریخ تحویل"><?= ui_icon('calendar') ?></button></div></div><div class="form-group"><label>زمان دقیق — اختیاری</label><input class="form-control" name="occurred_time" inputmode="numeric" autocomplete="off" placeholder="مثلاً ۱۸:۳۰"></div><div class="form-group full"><label>یادداشت مشترک — اختیاری</label><textarea class="form-control" name="note" rows="2" maxlength="500"></textarea></div><div class="form-group full actions"><button class="btn btn-primary" type="submit">ثبت گروهی و ورود به انبار</button><a class="btn btn-light" href="purchases.php">انصراف</a></div></div></div></section>
</form>
<?php endif; ?>
</div>
<?php panel_footer(); ?>
