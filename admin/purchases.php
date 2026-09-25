<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
sokna_module_require('supply');
require_login();
if (!user_can_manage_purchases()) deny_access_and_return();
require dirname(__DIR__) . '/includes/panel_layout.php';

$pdo=db();
inventory_seed_if_empty($pdo);
inventory_process_pending_order_events(10);
$user=current_user();$userId=(int)($user['id']??0);$canAdjustPurchaseHistory=user_can_inventory_finalize($user);

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf($_POST['csrf_token']??null);
    $action=(string)($_POST['action']??'');
    try{
        $pdo->beginTransaction();
        if($action==='prepare'){
            $groupKey=(string)($_POST['group_key']??'');
            $expected=(int)($_POST['expected_quantity_base']??-1);
            if($expected<0) throw new RuntimeException('اطلاعات لیست خرید ناقص است؛ صفحه را تازه کن.');
            $result=supply_mark_group_preparing_locked($pdo,$groupKey,$userId,$expected);
            $pdo->commit();
            flash('success','خرید این قلم شروع شد.');
        }elseif($action==='prepare_all'){
            $keys=array_values((array)($_POST['group_key']??[]));$expectedValues=array_values((array)($_POST['expected_quantity_base']??[]));
            if(!$keys || count($keys)!==count($expectedValues) || count($keys)>100) throw new RuntimeException('لیست خرید تغییر کرده است؛ صفحه را تازه کن.');
            $count=0;
            foreach($keys as $i=>$groupKey){
                $expected=(int)($expectedValues[$i]??-1);if($expected<1)continue;
                supply_mark_group_preparing_locked($pdo,(string)$groupKey,$userId,$expected);$count++;
            }
            if($count<1)throw new RuntimeException('قلم تازه‌ای برای شروع خرید وجود ندارد.');
            $pdo->commit();flash('success',fa_digits($count).' قلم به «در حال خرید» منتقل شد.');
        }elseif($action==='return_preparing' || $action==='unavailable'){
            $groupKey=(string)($_POST['group_key']??'');
            $expected=(int)($_POST['expected_quantity_base']??-1);if($expected<1)throw new RuntimeException('اطلاعات قلم ناقص است؛ صفحه را تازه کن.');
            supply_return_group_from_preparing_locked($pdo,$groupKey,$userId,$action==='unavailable'?'unavailable':'returned',$expected);
            $pdo->commit();
            flash('success',$action==='unavailable'?'خرید نشد و قلم به فهرست خرید برگشت.':'قلم به فهرست خرید برگشت.');
        }elseif($action==='cancel_open'){
            $groupKey=(string)($_POST['group_key']??'');
            $expected=(int)($_POST['expected_quantity_base']??-1);if($expected<1)throw new RuntimeException('اطلاعات قلم ناقص است؛ صفحه را تازه کن.');
            supply_cancel_group_uncommitted_locked($pdo,$groupKey,$userId,$expected);
            $pdo->commit();flash('success','نیازهای جدید این قلم لغو شدند.');
        }elseif($action==='receive'){
            $groupKey=(string)($_POST['group_key']??'');
            $result=supply_receive_preparing_locked($pdo,$groupKey,$_POST,$userId);
            $pdo->commit();
            $received=inventory_format_quantity((int)$result['received_quantity_base'],(string)$result['base_unit']);
            $preparedRemaining=(int)$result['preparing_remaining_base'];
            $uncommitted=(int)$result['uncommitted_remaining_base'];
            if($preparedRemaining>0){
                flash('success',$received.' وارد انبار شد؛ '.inventory_format_quantity($preparedRemaining,(string)$result['base_unit']).' هنوز در حال خرید باقی ماند.');
            }elseif($uncommitted>0){
                flash('success',$received.' وارد انبار شد؛ '.inventory_format_quantity($uncommitted,(string)$result['base_unit']).' نیاز اضافه هنوز در انتظار خرید است.');
            }else{
                flash('success',$received.' تحویل شد و ورود استاندارد انبار ثبت شد.');
            }
        }elseif($action==='low_stock_add'){
            $itemId=(int)($_POST['inventory_item_id']??0);if($itemId<1)throw new RuntimeException('کالا معتبر نیست.');
            $item=inventory_item($pdo,$itemId,true);if(!$item||(int)$item['active']!==1)throw new RuntimeException('کالا پیدا نشد.');
            supply_request_upsert_locked($pdo,[
                'inventory_item_id'=>$itemId,'quantity_major'=>$_POST['quantity_major']??'',
                'department'=>(string)($item['default_department']??'shared'),'source'=>'low_stock',
            ],$userId);
            $pdo->commit();flash('success','به فهرست خرید اضافه شد.');
        }else throw new RuntimeException('عملیات خرید معتبر نیست.');
    }catch(RuntimeException|InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();flash('error',$e->getMessage());}
    catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('purchases modular supply: '.$e->getMessage());flash('error','عملیات خرید ذخیره نشد. دوباره تلاش کن.');}
    redirect('purchases.php');
}

$groups=supply_purchase_groups($pdo);
$openGroups=array_values(array_filter($groups,static fn(array $g): bool => (int)$g['uncommitted_quantity_base']>0));
$preparingGroups=array_values(array_filter($groups,static fn(array $g): bool => (int)$g['preparing_quantity_base']>0));

$unitsByItem=[];
foreach($groups as $g){$iid=(int)$g['item_id'];if($iid>0&&!isset($unitsByItem[$iid]))$unitsByItem[$iid]=inventory_operational_purchase_units($pdo,$iid);}
$activeItems=$pdo->query("SELECT i.id,i.name,i.base_unit,i.default_department FROM inventory_items i WHERE i.active=1 ORDER BY i.name,i.id")->fetchAll();
foreach($activeItems as $it){$iid=(int)$it['id'];if(!isset($unitsByItem[$iid]))$unitsByItem[$iid]=inventory_operational_purchase_units($pdo,$iid);}

$low=$pdo->query("SELECT i.*,COALESCE(b.quantity_base,0) quantity_base,n.id open_need_id
    FROM inventory_items i LEFT JOIN inventory_balances b ON b.inventory_item_id=i.id
    LEFT JOIN inventory_supply_needs n ON n.inventory_item_id=i.id AND n.status='open'
    WHERE i.active=1 AND i.warning_threshold>0 AND COALESCE(b.quantity_base,0)>=0 AND COALESCE(b.quantity_base,0)<=i.warning_threshold AND n.id IS NULL
    ORDER BY (CASE WHEN i.warning_threshold>0 THEN COALESCE(b.quantity_base,0)/i.warning_threshold ELSE 1 END),i.name,i.id")->fetchAll();

$history=$pdo->query("SELECT r.*,i.name item_name,i.base_unit,u.display_name actor_name FROM inventory_supply_receipts r JOIN inventory_items i ON i.id=r.inventory_item_id LEFT JOIN users u ON u.id=r.actor_user_id ORDER BY r.id DESC LIMIT 15")->fetchAll();
$purchasePayload=[];
foreach($preparingGroups as $g){
    $iid=(int)$g['item_id'];
    $purchasePayload[]=[
        'group_key'=>(string)$g['group_key'],'item_id'=>$iid,'name'=>(string)$g['name'],'base_unit'=>(string)$g['base_unit'],'unit_label'=>inventory_major_unit_label((string)$g['base_unit']),
        'preparing_label'=>inventory_format_quantity((int)$g['preparing_quantity_base'],(string)$g['base_unit']),'preparing_quantity_base'=>(int)$g['preparing_quantity_base'],
        'preparing_major'=>inventory_base_to_major_value((int)$g['preparing_quantity_base'],(string)$g['base_unit']),'unknown'=>(bool)$g['unknown'],
        'units'=>array_map(static fn($u)=>['id'=>(int)$u['id'],'name'=>(string)$u['name'],'mode'=>(string)$u['conversion_mode'],'base_quantity'=>$u['base_quantity']===null?null:(int)$u['base_quantity']],$iid>0?($unitsByItem[$iid]??[]):[]),
    ];
}
$inventoryChoices=array_map(static fn($x)=>['id'=>(int)$x['id'],'name'=>(string)$x['name'],'base_unit'=>(string)$x['base_unit'],'unit_label'=>inventory_major_unit_label((string)$x['base_unit']),'units'=>array_map(static fn($u)=>['id'=>(int)$u['id'],'name'=>(string)$u['name'],'mode'=>(string)$u['conversion_mode'],'base_quantity'=>$u['base_quantity']===null?null:(int)$u['base_quantity']],$unitsByItem[(int)$x['id']]??[])],$activeItems);

panel_header('خرید','purchases');
?>
<div class="panel-surface-stack purchase-page">
<header class="purchase-page-head inventory-toolbar"><div class="panel-copy-stack"><strong>خرید و تحویل</strong><small class="muted">اقلام موردنیاز را برای خرید انتخاب کن و بعد از تحویل، مقدار واقعی را ثبت کن.</small></div><div class="inventory-toolbar-actions"><a class="btn btn-light" href="<?= e(asset('operator/supply-needs.php')) ?>"><?= ui_icon('plus') ?> درخواست خرید</a><?php $batchEligibleCount=count(array_filter($preparingGroups,static fn(array $g): bool => !(bool)$g['unknown'])); if($batchEligibleCount>1): ?><a class="btn btn-light" href="purchases_batch.php"><?= ui_icon('check') ?> ثبت گروهی تحویل</a><?php endif; ?><?php if($preparingGroups): ?><button class="btn btn-light purchase-share-btn" id="sharePurchaseList" type="button" aria-label="اشتراک فهرست در حال خرید"><?= ui_icon('share') ?><span>اشتراک</span></button><?php endif; ?></div></header>

<?php if($preparingGroups): ?><section class="card purchase-preparing-card" id="preparingPurchases"><div class="card-head"><div><h2>در حال خرید · <?= fa_digits(count($preparingGroups)) ?> قلم</h2><small>این اقلام برای خرید برداشته شده‌اند؛ موجودی پس از ثبت تحویل تغییر می‌کند.</small></div></div><div class="purchase-need-list">
<?php foreach($preparingGroups as $g):
    $prepared=(int)$g['preparing_quantity_base'];$isUnknown=(bool)$g['unknown'];$key=(string)$g['group_key'];
    $ageWarning=$g['preparing_at'] && (time()-strtotime((string)$g['preparing_at']))>=86400;
    $prepUsers=array_keys((array)$g['preparing_users']);$who=$prepUsers?implode('، ',$prepUsers):'مسئول خرید';
?>
<article class="purchase-need-row is-preparing" data-purchase-share="<?= e((string)$g['name'].' — '.inventory_format_quantity($prepared,(string)$g['base_unit'])) ?>">
  <div class="purchase-need-main"><div class="purchase-need-title"><strong><?= e((string)$g['name']) ?></strong><?php if($isUnknown): ?><span class="status-chip status-chip-warning">خارج از فهرست</span><?php endif; ?><?php if($ageWarning): ?><span class="status-chip status-chip-warning">بیش از ۲۴ ساعت</span><?php endif; ?></div>
  <small><b><?= e(inventory_format_quantity($prepared,(string)$g['base_unit'])) ?></b><?php $dept=supply_group_department_summary($g,'preparing'); if($dept!==''): ?> · <?= e($dept) ?><?php endif; ?></small>
  <small class="muted"><?= e($who) ?><?php if($g['preparing_at']): ?> · <?= e(format_jalali_human_datetime((string)$g['preparing_at'])) ?><?php endif; ?></small>
  <?php if($g['notes']): ?><p class="purchase-need-note"><?= e(implode(' · ',array_unique((array)$g['notes']))) ?></p><?php endif; ?></div>
  <div class="purchase-need-actions"><button class="btn btn-primary btn-sm" type="button" data-open-purchase-receive="<?= e($key) ?>"><?= $isUnknown?'ثبت تحویل و اتصال به انبار':'ثبت تحویل' ?></button><button class="btn btn-light btn-sm" type="button" data-open-purchase-more="prep-<?= e($key) ?>" aria-expanded="false">بیشتر</button></div>
  <div class="purchase-need-more hidden" data-purchase-more="prep-<?= e($key) ?>"><form method="post" data-confirm="تهیه‌نشدن این نوبت ثبت می‌شود و قلم دوباره در فهرست خرید قرار می‌گیرد." data-confirm-title="خرید انجام نشد؟" data-confirm-ok="ثبت تهیه‌نشدن"><?= csrf_field() ?><input type="hidden" name="action" value="unavailable"><input type="hidden" name="group_key" value="<?= e($key) ?>"><input type="hidden" name="expected_quantity_base" value="<?= $prepared ?>"><button class="panel-text-action" type="submit"><strong>خرید انجام نشد</strong><small>تهیه‌نشدن ثبت می‌شود و قلم به فهرست خرید برمی‌گردد.</small></button></form><form method="post" data-confirm="شروع خرید لغو می‌شود و قلم بدون ثبت تهیه‌نشدن به فهرست خرید برمی‌گردد." data-confirm-title="لغو شروع خرید؟" data-confirm-ok="لغو شروع خرید"><?= csrf_field() ?><input type="hidden" name="action" value="return_preparing"><input type="hidden" name="group_key" value="<?= e($key) ?>"><input type="hidden" name="expected_quantity_base" value="<?= $prepared ?>"><button class="panel-text-action" type="submit"><strong>لغو شروع خرید</strong><small>فقط وضعیت «در حال خرید» برداشته می‌شود.</small></button></form></div>
</article>
<?php endforeach; ?></div></section><?php endif; ?>

<?php if($openGroups): ?><section class="card purchase-waiting-card"><div class="card-head"><div><h2>در انتظار خرید · <?= fa_digits(count($openGroups)) ?> قلم</h2></div><?php if(count($openGroups)>1): ?><form method="post" data-confirm="همه اقلام فعلی وارد فهرست در حال خرید می‌شوند." data-confirm-title="شروع خرید همه اقلام؟" data-confirm-ok="شروع خرید"><?= csrf_field() ?><input type="hidden" name="action" value="prepare_all"><?php foreach($openGroups as $snapshotGroup): ?><input type="hidden" name="group_key[]" value="<?= e((string)$snapshotGroup['group_key']) ?>"><input type="hidden" name="expected_quantity_base[]" value="<?= (int)$snapshotGroup['uncommitted_quantity_base'] ?>"><?php endforeach; ?><button class="btn btn-primary btn-sm" type="submit">شروع خرید همه</button></form><?php endif; ?></div>
<div class="purchase-need-list">
<?php foreach($openGroups as $g): $uncommitted=(int)$g['uncommitted_quantity_base'];$isUnknown=(bool)$g['unknown'];$key=(string)$g['group_key'];$negativeStock=!$isUnknown&&(int)$g['quantity_base']<0; ?>
<article class="purchase-need-row">
  <div class="purchase-need-main"><div class="purchase-need-title"><strong><?= e((string)$g['name']) ?></strong><?php if($isUnknown): ?><span class="status-chip status-chip-warning">خارج از فهرست</span><?php endif; ?><?php if($negativeStock): ?><span class="status-chip status-chip-warning">موجودی منفی</span><?php endif; ?><?php if((int)$g['preparing_quantity_base']>0): ?><span class="status-chip">نیاز اضافه</span><?php endif; ?><?php if((string)$g['last_outcome']==='unavailable'): ?><span class="status-chip">دفعه قبل تهیه نشد</span><?php endif; ?></div>
  <small>نیاز<?= (int)$g['preparing_quantity_base']>0?' اضافه':'' ?>: <b><?= e(inventory_format_quantity($uncommitted,(string)$g['base_unit'])) ?></b><?php $dept=supply_group_department_summary($g,'uncommitted'); if($dept!==''): ?> · <?= e($dept) ?><?php endif; ?></small>
  <?php if(!$isUnknown): ?><small class="muted"><?php if($negativeStock): ?>موجودی انبار نیاز به بررسی دارد<?php else: ?>موجودی انبار: <?= e(inventory_format_major_quantity((int)$g['quantity_base'],(string)$g['base_unit'])) ?><?php endif; ?><?php if((int)$g['preparing_quantity_base']>0): ?> · <?= e(inventory_format_quantity((int)$g['preparing_quantity_base'],(string)$g['base_unit'])) ?> از قبل در حال خرید<?php endif; ?></small><?php endif; ?>
  <?php if($g['notes']): ?><p class="purchase-need-note"><?= e(implode(' · ',array_unique((array)$g['notes']))) ?></p><?php endif; ?></div>
  <div class="purchase-need-actions"><form method="post" class="purchase-inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="prepare"><input type="hidden" name="group_key" value="<?= e($key) ?>"><input type="hidden" name="expected_quantity_base" value="<?= $uncommitted ?>"><button class="btn btn-primary btn-sm" type="submit">شروع خرید</button></form><button class="btn btn-light btn-sm" type="button" data-open-purchase-more="open-<?= e($key) ?>" aria-expanded="false">بیشتر</button></div>
  <div class="purchase-need-more hidden" data-purchase-more="open-<?= e($key) ?>"><form method="post" data-confirm="این درخواست از فهرست خرید حذف می‌شود؛ مقدارهایی که از قبل در حال خرید هستند تغییری نمی‌کنند." data-confirm-title="حذف از فهرست خرید" data-confirm-ok="حذف از فهرست" data-confirm-danger="1"><?= csrf_field() ?><input type="hidden" name="action" value="cancel_open"><input type="hidden" name="group_key" value="<?= e($key) ?>"><input type="hidden" name="expected_quantity_base" value="<?= $uncommitted ?>"><button class="panel-text-action danger" type="submit">حذف از فهرست خرید</button></form></div>
</article>
<?php endforeach; ?></div></section><?php endif; ?>

<?php if(!$openGroups && !$preparingGroups): ?><section class="card"><div class="card-body"><div class="inventory-empty">فعلاً چیزی در انتظار خرید نیست.</div></div></section><?php endif; ?>

<?php if($low): ?><section class="card purchase-low-stock"><div class="card-head"><div><h2>موجودی کم · <?= fa_digits(count($low)) ?></h2><small>فقط یک هشدار است؛ سیستم مقدار خرید را حدس نمی‌زند. موجودی منفی از این پیشنهادها جداست و باید در انبار بررسی شود.</small></div></div><div class="purchase-low-list">
<?php foreach($low as $it): ?><form method="post" class="purchase-low-row"><?= csrf_field() ?><input type="hidden" name="action" value="low_stock_add"><input type="hidden" name="inventory_item_id" value="<?= (int)$it['id'] ?>"><div><strong><?= e((string)$it['name']) ?></strong><small>موجودی <?= e(inventory_format_major_quantity((int)$it['quantity_base'],(string)$it['base_unit'])) ?> · حد هشدار <?= e(inventory_format_major_quantity((int)$it['warning_threshold'],(string)$it['base_unit'])) ?></small></div><label><span>اگر لازم است، مقدار خرید</span><span class="purchase-inline-qty"><input class="form-control" name="quantity_major" inputmode="decimal" enterkeyhint="done" autocomplete="off" placeholder="مقدار" required><em><?= e(inventory_major_unit_label((string)$it['base_unit'])) ?></em></span></label><button class="btn btn-light btn-sm">+ افزودن</button></form><?php endforeach; ?>
</div></section><?php endif; ?>

<?php if($history): ?><section class="card purchase-history-card"><div class="card-head"><div><h2>آخرین تحویل‌ها</h2><small>خریدهایی که تحویل و وارد انبار شده‌اند.<?php if($canAdjustPurchaseHistory): ?> برای مشاهده یا اصلاح، روی ردیف بزن.<?php endif; ?></small></div></div><div class="purchase-history-list"><?php foreach($history as $h): $historyHref=$canAdjustPurchaseHistory?'inventory_adjustment.php?id='.(int)$h['movement_id'].'&from=purchases':''; ?><article class="purchase-history-row<?= $historyHref!==''?' is-navigable':'' ?>"<?= $historyHref!==''?' data-row-href="'.e($historyHref).'" role="link" tabindex="0" aria-label="اصلاح تحویل '.e((string)$h['item_name']).'"':'' ?>><div class="purchase-history-main"><strong><?= e((string)$h['item_name']) ?></strong><small><?= e((string)($h['actor_name']??'سامانه')) ?> · <?= e(format_jalali_human_datetime((string)$h['received_at'])) ?></small></div><span class="purchase-history-qty"><?= e(inventory_format_major_quantity((int)$h['received_quantity_base'],(string)$h['base_unit'])) ?></span><?php if($historyHref!==''): ?><span class="purchase-history-chevron" aria-hidden="true"><?= ui_icon('chevron-left') ?></span><?php endif; ?></article><?php endforeach; ?></div></section><?php endif; ?>
</div>

<div class="panel-confirm-layer panel-form-dialog-layer hidden purchase-receive-layer" id="purchaseReceiveLayer" role="dialog" aria-modal="true" aria-labelledby="purchaseReceiveTitle" aria-hidden="true" data-backdrop-close="0" data-escape-close="0">
<div class="panel-confirm-backdrop" data-dialog-backdrop aria-hidden="true"></div>
<div class="panel-form-dialog-card purchase-receive-card">
  <header class="panel-form-dialog-head"><div><h2 id="purchaseReceiveTitle">ثبت تحویل</h2><p id="purchaseReceiveSummary"></p></div><button class="icon-btn" type="button" data-dialog-close aria-label="انصراف"><?= ui_icon('close') ?></button></header>
  <form method="post" id="purchaseReceiveForm" class="panel-form-dialog-form purchase-receive-form"><?= csrf_field() ?><input type="hidden" name="action" value="receive"><input type="hidden" name="group_key" id="purchaseGroupKey"><input type="hidden" name="expected_preparing_quantity_base" id="purchaseExpectedPreparing"><input type="hidden" name="request_token" id="purchaseRequestToken">
    <div class="panel-form-dialog-body">
      <div class="form-group purchase-target-group hidden" id="purchaseTargetGroup"><label>اتصال به کالای موجود — اختیاری</label><select class="form-control" name="target_item_id" id="purchaseTargetItem"><option value="0">ایجاد کالای جدید با همین نام</option></select><small class="muted">اگر همین کالا از قبل با نام دیگری در انبار هست، به آن وصلش کن.</small></div>
      <div class="form-group"><label for="purchaseUnit">به چه شکلی خرید شده؟</label><select class="form-control" name="purchase_unit_id" id="purchaseUnit"><option value="0">مستقیم</option></select></div>
      <div class="form-group"><label id="purchaseUnitCountLabel" for="purchaseUnitCount">مقدار واقعی تحویل‌شده</label><div class="purchase-receive-qty"><input class="form-control" name="unit_count" id="purchaseUnitCount" inputmode="decimal" enterkeyhint="next" autocomplete="off" required><em id="purchaseUnitLabel"></em></div><button class="panel-text-action purchase-fill-prepared" id="purchaseFillPrepared" type="button">همان مقدار در حال خرید</button></div>
      <div class="form-group hidden" id="purchaseActualGroup"><label>مقدار واقعی کل</label><div class="purchase-receive-qty"><input class="form-control" name="actual_major_quantity" id="purchaseActual" inputmode="decimal" enterkeyhint="next" autocomplete="off"><em id="purchaseActualLabel"></em></div><small class="muted">برای بسته‌های وزن/حجم متغیر.</small></div>
      <div class="form-group"><label>مبلغ کل خرید — اختیاری</label><input class="form-control" name="total_cost" inputmode="numeric" enterkeyhint="done" autocomplete="off" data-money-input placeholder="اگر نمی‌دانی خالی بگذار"></div>
      <details class="form-disclosure full"><summary><?= ui_icon('plus') ?><span><strong>اطلاعات بیشتر</strong><small>تأمین‌کننده، زمان واقعی تحویل و یادداشت؛ فقط در صورت نیاز</small></span></summary><div class="disclosure-body">
        <div class="form-group"><label>تأمین‌کننده</label><input class="form-control" name="supplier" autocomplete="off"></div>
        <div class="form-grid purchase-receive-date-grid"><div class="form-group"><label for="purchaseReceiveDate">تاریخ واقعی تحویل به کافه</label><div class="jalali-date-control"><input class="form-control" id="purchaseReceiveDate" name="occurred_date_j" data-jalali-date inputmode="none" autocomplete="off" placeholder="انتخاب تاریخ"><button class="jalali-date-button" type="button" data-open-jalali="purchaseReceiveDate" aria-label="انتخاب تاریخ"><?= ui_icon('calendar') ?></button></div></div><div class="form-group"><label>زمان دقیق — اختیاری</label><input class="form-control" name="occurred_time" inputmode="numeric" enterkeyhint="next" autocomplete="off" placeholder="مثلاً ۱۸:۳۰"></div></div>
        <div class="form-group"><label>یادداشت — اختیاری</label><textarea class="form-control" name="note" rows="2" maxlength="500" placeholder="مثلاً اختلاف وزن، توضیح فاکتور یا شرایط تحویل"></textarea></div>
      </div></details>
    </div>
    <div class="panel-form-dialog-actions"><button class="btn btn-primary" id="purchaseReceiveSubmit">ثبت تحویل و ورود انبار</button><button class="btn btn-light" type="button" data-dialog-close>انصراف</button></div>
  </form>
</div></div>
<script>window.SOKNA_PURCHASE_NEEDS=<?= json_script($purchasePayload) ?>;window.SOKNA_PURCHASE_ITEMS=<?= json_script($inventoryChoices) ?>;</script>
<?php panel_footer('<script defer src="'.e(asset('assets/js/supply-purchases.js')).'"></script>'); ?>
