<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
sokna_module_require('inventory');
require_login();
if (!user_can_inventory_manage()) deny_access_and_return();
require dirname(__DIR__) . '/includes/panel_layout.php';
$pdo=db();inventory_seed_if_empty($pdo);$userId=(int)(current_user()['id']??0);
$reviewWhere="i.active=1 AND (i.review_status='needs_review' OR EXISTS(SELECT 1 FROM inventory_purchase_units pu WHERE pu.inventory_item_id=i.id AND pu.active=1 AND pu.review_status='needs_review'))";
$total=(int)$pdo->query("SELECT COUNT(*) FROM inventory_items i WHERE $reviewWhere")->fetchColumn();
$id=(int)($_GET['id']??$_POST['id']??0);
if($id<1){$id=(int)($pdo->query("SELECT i.id FROM inventory_items i WHERE $reviewWhere ORDER BY i.id LIMIT 1")->fetchColumn()?:0);}
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf($_POST['csrf_token']??null);$action=(string)($_POST['action']??'later');
 if($action==='confirm'&&$id>0){
  try{
   $pdo->beginTransaction();
   $stmt=$pdo->prepare("SELECT * FROM inventory_items WHERE id=? AND active=1 FOR UPDATE");$stmt->execute([$id]);$item=$stmt->fetch();if(!$item)throw new RuntimeException('کالا پیدا نشد.');
   $unitStmt=$pdo->prepare("SELECT * FROM inventory_purchase_units WHERE inventory_item_id=? AND active=1 FOR UPDATE");$unitStmt->execute([$id]);$units=$unitStmt->fetchAll();
   foreach($units as $unit){if((string)$unit['conversion_mode']==='fixed'&&(int)($unit['base_quantity']??0)<1)throw new RuntimeException('تبدیل یکی از واحدهای خرید هنوز مشخص نشده است؛ ابتدا جزئیات کالا را ویرایش کن.');}
   $pdo->prepare("UPDATE inventory_items SET review_status='ready',review_note=NULL WHERE id=?")->execute([$id]);
   $pdo->prepare("UPDATE inventory_purchase_units SET review_status='ready' WHERE inventory_item_id=? AND active=1")->execute([$id]);
   audit_log_write('inventory.item_review_confirmed','inventory_item',$id,['name'=>$item['name']],$userId);
   $pdo->commit();flash('success','کالا تأیید شد.');redirect('inventory_review.php');
  }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('inventory review: '.$e->getMessage());flash('error',safe_business_error_message($e,'تأیید کالا انجام نشد.'));redirect('inventory_review.php?id='.$id);}
 }
 if($action==='later')redirect('inventory_review.php?after='.$id);
}
$after=(int)($_GET['after']??0);if($after>0){$stmt=$pdo->prepare("SELECT i.id FROM inventory_items i WHERE $reviewWhere AND i.id>? ORDER BY i.id LIMIT 1");$stmt->execute([$after]);$next=(int)($stmt->fetchColumn()?:0);if(!$next){$next=(int)($pdo->query("SELECT i.id FROM inventory_items i WHERE $reviewWhere ORDER BY i.id LIMIT 1")->fetchColumn()?:0);}if($next&&$next!==$id){redirect('inventory_review.php?id='.$next);}}
$item=$id?inventory_item($pdo,$id):null;$units=$item?inventory_purchase_units($pdo,$id,false):[];
$done=max(0,(int)$pdo->query("SELECT COUNT(*) FROM inventory_items i WHERE i.active=1 AND i.review_status='ready' AND NOT EXISTS(SELECT 1 FROM inventory_purchase_units pu WHERE pu.inventory_item_id=i.id AND pu.active=1 AND pu.review_status='needs_review')")->fetchColumn());
panel_header('تأیید کالاها','inventory');
?>
<?php if(!$item||$total===0): ?><section class="card inventory-review-card"><div class="card-body"><h2>تأیید کالاها تمام شد</h2><p class="muted">فعلاً کالای فعالی که نیاز به تأیید داشته باشد باقی نمانده است.</p><div class="actions"><a class="btn btn-primary" href="inventory_items.php">مدیریت کالاها</a><a class="btn btn-light" href="inventory.php">انبار</a></div></div></section>
<?php else: ?>
<section class="card inventory-review-card"><div class="card-body"><div class="inventory-item-header"><div class="panel-copy-stack"><small class="muted">نیاز به تأیید · <?= fa_digits($total) ?> مورد باقی‌مانده</small><h2><?= e((string)$item['name']) ?></h2></div><a class="btn btn-light" href="inventory_item_form.php?id=<?= $id ?>&return=review">ویرایش جزئیات</a></div><div class="inventory-review-progress"><span style="width:<?= max(3,min(100,(int)round($done/max(1,$done+$total)*100))) ?>%"></span></div>
<div class="inventory-summary"><div class="summary-cell"><small>دسته</small><strong><?= e(inventory_category_labels()[(string)$item['category']]??'') ?></strong></div><div class="summary-cell"><small>بخش پیش‌فرض</small><strong><?= e(inventory_department_labels()[(string)$item['default_department']]??'') ?></strong></div><div class="summary-cell"><small>واحد موجودی</small><strong><?= e(inventory_base_unit_labels()[(string)$item['base_unit']]??'') ?></strong></div></div>
<?php if(!empty($item['review_note'])): ?><div class="inventory-attention"><strong>یادداشت بررسی</strong><p><?= e((string)$item['review_note']) ?></p></div><?php endif; ?>
<h3>واحدهای خرید</h3><div class="inventory-purchase-unit-list"><?php if(!$units): ?><div class="inventory-empty">واحد خرید اختصاصی ندارد؛ ورود مستقیم با واحد موجودی ممکن است.</div><?php endif; ?><?php foreach($units as $unit): $unitIncomplete=(string)$unit['conversion_mode']==='fixed'&&(int)($unit['base_quantity']??0)<1; ?><div class="inventory-purchase-unit"><div><strong><?= e((string)$unit['name']) ?></strong><small><?= (string)$unit['conversion_mode']==='actual_quantity'?'مقدار واقعی هنگام ورود ثبت می‌شود':(!$unitIncomplete?'تبدیل: '.e(inventory_format_quantity((int)$unit['base_quantity'],(string)$item['base_unit'])):'تبدیل هنوز مشخص نیست') ?></small></div><?php if((int)$unit['active']!==1): ?><span class="inventory-status inactive">غیرفعال</span><?php elseif($unitIncomplete): ?><span class="inventory-status needs-review">نیازمند تکمیل</span><?php elseif((string)$unit['review_status']==='needs_review'): ?><span class="inventory-status needs-review">نیازمند تأیید</span><?php endif; ?></div><?php endforeach; ?></div>
<form method="post" class="form-grid" style="margin-top:16px"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><div class="form-group full actions inventory-sticky-actions"><button class="btn btn-primary" name="action" value="confirm">تأیید و مورد بعدی</button><button class="btn btn-light" name="action" value="later">بعداً بررسی می‌کنم</button></div></form>
</div></section>
<?php endif; ?>
<?php panel_footer(); ?>
