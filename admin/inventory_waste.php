<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
sokna_module_require('inventory');
sokna_module_require_runtime_ready('inventory');
require_login();
if (!user_can_inventory_operate()) deny_access_and_return();
require dirname(__DIR__) . '/includes/panel_layout.php';
$pdo=db(); inventory_seed_if_empty($pdo);
if(!inventory_initialized()){
    flash('warning','ابتدا موجودی اولیه را نهایی کن؛ بعد از آن عملیات روزانه انبار فعال می‌شود.');
    redirect('inventory.php');
} $user=current_user();$userId=(int)($user['id']??0);$canViewStock=user_has_inventory_access($user);
$itemId=(int)($_GET['item_id']??$_POST['item_id']??0); $item=$itemId?inventory_item($pdo,$itemId):null; if($item&&!(int)$item['active'])$item=null; $units=$item?inventory_operational_purchase_units($pdo,$itemId):[];
$defaultUnitId=count($units)===1?(int)$units[0]['id']:0;$selectedUnitId=(int)($_POST['purchase_unit_id']??$defaultUnitId);$selectedUnitMode='base';foreach($units as $candidateUnit){if((int)$candidateUnit['id']===$selectedUnitId){$selectedUnitMode=(string)$candidateUnit['conversion_mode'];break;}}
$requestToken=trim((string)($_POST['request_token']??''));if($_SERVER['REQUEST_METHOD']!=='POST'&&!preg_match('/^[a-f0-9]{32}$/',$requestToken))$requestToken=bin2hex(random_bytes(16));
$reasons=['spoilage'=>'خرابی','expired'=>'تاریخ‌گذشته','prep_error'=>'اشتباه آماده‌سازی','spill'=>'ریخت‌وپاش','other'=>'سایر'];
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf($_POST['csrf_token']??null);
 try{
  if(!$item)throw new RuntimeException('کالای انبار را انتخاب کن.');
  if(!preg_match('/^[a-f0-9]{32}$/',$requestToken))throw new RuntimeException('فرم منقضی شده است؛ صفحه را تازه کن.');
  $resolved=inventory_resolve_operation_quantity($pdo,$itemId,(int)($_POST['purchase_unit_id']??0),$_POST['unit_count']??'',$_POST['actual_major_quantity']??'');
  $reason=(string)($_POST['reason']??''); if(!isset($reasons[$reason]))throw new RuntimeException('دلیل ضایعات را انتخاب کن.');
  $department=inventory_normalize_department((string)($_POST['department']??$item['default_department']))??'shared';
  $note=text_substr(trim((string)($_POST['note']??'')),0,500);
  $occurredAt=inventory_optional_occurred_at((string)($_POST['occurred_date_j']??''),(string)($_POST['occurred_time']??''),'ضایعات');
  $pdo->beginTransaction();
  inventory_record_movement_locked($pdo,array_merge($resolved,[
   'item_id'=>$itemId,'movement_type'=>'waste','quantity_base'=>-(int)$resolved['quantity_base'],'department'=>$department,
   'source_type'=>'inventory_waste','idempotency_key'=>'inventory:waste:'.$itemId.':'.$requestToken,'metadata'=>['reason'=>$reason,'reason_label'=>$reasons[$reason],'occurred_precision'=>inventory_occurrence_precision((string)($_POST['occurred_date_j']??''),(string)($_POST['occurred_time']??''))],'note'=>$note?:null,'actor_user_id'=>$userId,'occurred_at'=>$occurredAt,
  ]));
  $pdo->commit(); flash('success','ضایعات «'.(string)$item['name'].'» ثبت شد.'); redirect('inventory.php?tab=movements');
 }catch(RuntimeException|InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();flash('error',$e->getMessage());}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('inventory waste: '.$e->getMessage());flash('error','ضایعات ثبت نشد. دوباره تلاش کن؛ اگر مشکل ادامه داشت مدیر سامانه را مطلع کن.');}
}
$activeItems=$pdo->query("SELECT id,name FROM inventory_items WHERE active=1 ORDER BY name,id")->fetchAll();
panel_header('ثبت ضایعات','inventory');
panel_subnav(['stock'=>['inventory.php','موجودی'],'movements'=>['inventory.php?tab=movements','گردش'],'counts'=>['inventory.php?tab=counts','شمارش‌ها']],'movements','بخش‌های انبار');
?>
<section class="card"><div class="card-body">
<?php if(!$item): ?><form method="get" class="form-grid inventory-review-card" data-inventory-item-picker><div class="form-group full"><label>کالا</label><select class="form-control" name="item_id" data-choice-mode="browse" data-choice-search="true" data-choice-search-focus="true" data-inventory-item-select required><option value="" disabled hidden data-choice-placeholder="true">جست‌وجو و انتخاب کالا</option><?php foreach($activeItems as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e((string)$c['name']) ?></option><?php endforeach; ?></select><small class="muted">با انتخاب کالا، مستقیم وارد فرم ثبت ضایعات می‌شوی.</small></div><noscript><div class="form-group full actions"><button class="btn btn-primary">ادامه</button></div></noscript><div class="form-group full actions"><a class="btn btn-light" href="inventory.php">انصراف</a></div></form>
<?php else: ?><div class="inventory-item-header"><div class="panel-copy-stack"><h2><?= e((string)$item['name']) ?></h2><?php if($canViewStock): ?><p class="muted">موجودی فعلی: <?= e(inventory_format_major_quantity((int)($item['quantity_base']??0),(string)$item['base_unit'])) ?></p><?php endif; ?></div><a class="btn btn-light btn-sm" href="inventory_waste.php">تغییر</a></div>
<form method="post" class="form-grid" data-inventory-flow data-inventory-operation-form data-inventory-base-unit="<?= e((string)$item['base_unit']) ?>" data-inventory-current-base="<?= $canViewStock?(int)($item['quantity_base']??0):0 ?>" data-inventory-direction="-1"><?= csrf_field() ?><input type="hidden" name="item_id" value="<?= $itemId ?>"><input type="hidden" name="request_token" value="<?= e($requestToken) ?>">
<div class="form-group"><label>به چه شکلی مقدار را ثبت می‌کنی؟</label><select class="form-control" id="purchaseUnit" name="purchase_unit_id" data-choice-mode="adaptive" data-inventory-purchase-unit data-inventory-next-target="[data-inventory-unit-count]" <?= count($units)>1?'data-inventory-autofocus':'' ?>><option value="0" data-mode="base" data-unit-label="<?= e(inventory_major_unit_label((string)$item['base_unit'])) ?>" data-major-label="<?= e(inventory_major_unit_label((string)$item['base_unit'])) ?>" <?= $selectedUnitId===0?'selected':'' ?>>مستقیم · <?= e(inventory_major_unit_label((string)$item['base_unit'])) ?></option><?php foreach($units as $unit): ?><option value="<?= (int)$unit['id'] ?>" data-mode="<?= e((string)$unit['conversion_mode']) ?>" data-base-quantity="<?= $unit['base_quantity']===null?'':(int)$unit['base_quantity'] ?>" data-unit-label="<?= e((string)$unit['name']) ?>" data-major-label="<?= e(inventory_major_unit_label((string)$item['base_unit'])) ?>" <?= $selectedUnitId===(int)$unit['id']?'selected':'' ?>><?= e((string)$unit['name']) ?></option><?php endforeach; ?></select></div>
<div class="form-group"><label data-inventory-unit-count-label>مقدار</label><input class="form-control" inputmode="decimal" enterkeyhint="next" autocomplete="off" name="unit_count" value="<?= e(numeric_input_display_value((string)($_POST['unit_count']??''))) ?>" required data-inventory-unit-count data-inventory-step <?= count($units)<=1?'data-inventory-autofocus':'' ?>></div>
<div class="form-group" data-inventory-actual-group <?= $selectedUnitMode==='actual_quantity'?'':'hidden' ?>><label>مقدار واقعی کل (<?= e(inventory_major_unit_label((string)$item['base_unit'])) ?>)</label><input class="form-control" inputmode="decimal" enterkeyhint="next" autocomplete="off" name="actual_major_quantity" value="<?= e(numeric_input_display_value((string)($_POST['actual_major_quantity']??''))) ?>" data-inventory-actual-input data-inventory-step></div>
<div class="inventory-quantity-preview full" data-inventory-quantity-preview hidden></div><?php if($canViewStock): ?><small class="inventory-stock-preview full" data-inventory-stock-preview hidden></small><?php endif; ?>
<div class="form-group"><label>بخش مربوط</label><select class="form-control" name="department" data-choice-mode="compact"><?php foreach(inventory_department_labels() as $key=>$label): ?><option value="<?= e($key) ?>" <?= (string)($_POST['department']??$item['default_department'])===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select><small class="muted">موجودی بین همه بخش‌ها مشترک است؛ این انتخاب فقط برای گزارش مصرف و ضایعات استفاده می‌شود.</small></div>
<div class="form-group"><label>دلیل ضایعات</label><select class="form-control" name="reason" data-choice-mode="compact" data-inventory-step data-inventory-next-target="[data-inventory-submit]" required><option value="" disabled hidden data-choice-placeholder="true">انتخاب دلیل</option><?php foreach($reasons as $key=>$label): ?><option value="<?= e($key) ?>" <?= (string)($_POST['reason']??'')===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
<details class="form-disclosure full inventory-optional-details" <?= (!empty($_POST['occurred_date_j'])||!empty($_POST['occurred_time'])||!empty($_POST['note']))?'open':'' ?>><summary><?= ui_icon('plus') ?><span><strong>افزودن اطلاعات بیشتر</strong><small>تاریخ واقعی یا یادداشت؛ فقط در صورت نیاز</small></span></summary><div class="disclosure-body">
<div class="inventory-optional-field-actions"><button class="btn btn-sm btn-light" type="button" data-inventory-add-field="waste-date" <?= !empty($_POST['occurred_date_j'])?'hidden':'' ?>>+ تاریخ واقعی وقوع</button><button class="btn btn-sm btn-light" type="button" data-inventory-add-field="waste-note" <?= !empty($_POST['note'])?'hidden':'' ?>>+ یادداشت</button></div>
<div class="form-group inventory-optional-field" data-inventory-optional-field="waste-date" <?= !empty($_POST['occurred_date_j'])?'':'hidden' ?>><div class="inventory-optional-field-head"><label>تاریخ واقعی وقوع</label><button type="button" class="panel-text-action" data-inventory-remove-field="waste-date">حذف</button></div><div class="jalali-date-control"><input class="form-control" id="inventoryWasteDate" name="occurred_date_j" data-jalali-date inputmode="none" value="<?= e((string)($_POST['occurred_date_j']??'')) ?>" placeholder="انتخاب تاریخ"><button class="jalali-date-button" type="button" data-open-jalali="inventoryWasteDate" aria-label="انتخاب تاریخ وقوع ضایعات"><?= ui_icon('calendar') ?></button></div><button class="panel-text-action inventory-exact-time-trigger" type="button" data-inventory-add-field="waste-time" <?= !empty($_POST['occurred_time'])?'hidden':'' ?>>+ افزودن ساعت دقیق، در صورت نیاز</button></div>
<div class="form-group inventory-optional-field" data-inventory-optional-field="waste-time" <?= !empty($_POST['occurred_time'])?'':'hidden' ?>><div class="inventory-optional-field-head"><label>ساعت دقیق وقوع</label><button type="button" class="panel-text-action" data-inventory-remove-field="waste-time">حذف</button></div><input class="form-control ltr-input" dir="ltr" type="time" name="occurred_time" data-minute-step="5" step="300" value="<?= e((string)($_POST['occurred_time']??'')) ?>"><small class="muted">بدون ساعت هم می‌توان تاریخ را ثبت کرد.</small></div>
<div class="form-group inventory-optional-field" data-inventory-optional-field="waste-note" <?= !empty($_POST['note'])?'':'hidden' ?>><div class="inventory-optional-field-head"><label>یادداشت</label><button type="button" class="panel-text-action" data-inventory-remove-field="waste-note">حذف</button></div><textarea class="form-control" name="note" rows="3"><?= e((string)($_POST['note']??'')) ?></textarea></div>
</div></details>
<div class="form-group full actions"><button class="btn btn-primary" data-inventory-submit>ثبت ضایعات</button><a class="btn btn-light" href="inventory.php">انصراف</a></div></form>
<?php endif; ?></div></section>
<?php panel_footer(); ?>
