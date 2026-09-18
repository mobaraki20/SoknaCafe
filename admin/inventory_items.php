<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
sokna_module_require('inventory');
require_login();
if (!user_can_inventory_manage()) deny_access_and_return();
require dirname(__DIR__) . '/includes/panel_layout.php';
$pdo=db();inventory_seed_if_empty($pdo);$userId=(int)current_user()['id'];

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!csrf_valid($_POST['csrf_token']??null)){flash('error','نشست صفحه منقضی شده است.');redirect('inventory_items.php');}
    $id=(int)($_POST['id']??0);$action=(string)($_POST['action']??'');
    try{
        $stmt=$pdo->prepare('SELECT * FROM inventory_items WHERE id=? FOR UPDATE');$pdo->beginTransaction();$stmt->execute([$id]);$item=$stmt->fetch();if(!$item)throw new RuntimeException('کالا پیدا نشد.');
        if($action==='set_active'){
            $next=((string)($_POST['desired_active']??''))==='1'?1:0;
            if($next!==(int)$item['active']){
                if($next===0){
                    $recipeUsage=inventory_active_recipe_usage($pdo,$id,4);
                    if($recipeUsage){
                        $names=array_map(static fn($row)=>(string)$row['name'],$recipeUsage);
                        throw new RuntimeException('این کالا در مواد مصرفی آیتم فعال استفاده می‌شود: '.implode('، ',$names).'. ابتدا مواد مصرفی آن آیتم را اصلاح کنید.');
                    }
                }
                $pdo->prepare('UPDATE inventory_items SET active=? WHERE id=?')->execute([$next,$id]);
                audit_log_write('inventory.item_active_changed','inventory_item',$id,['active_before'=>(int)$item['active'],'active_after'=>$next],$userId);
            }
            $pdo->commit();flash('success',$next?'کالا فعال است.':'کالا غیرفعال شد؛ تاریخچه آن حفظ می‌شود.');
        }else{throw new RuntimeException('عملیات شناخته نشد.');}
    }catch(PDOException $e){if($pdo->inTransaction())$pdo->rollBack();error_log('inventory items action: '.$e->getMessage());flash('error','ذخیره تغییر وضعیت کالا انجام نشد. دوباره تلاش کن.');}
    catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();flash('error',$e instanceof RuntimeException?$e->getMessage():'انجام عملیات کالا ممکن نشد.');}
    redirect('inventory_items.php');
}
$q=trim((string)($_GET['q']??''));$status=(string)($_GET['status']??'all');
$where=['1=1'];$params=[];
if($q!==''){
    $where[]="REPLACE(REPLACE(REPLACE(i.name,'ي','ی'),'ى','ی'),'ك','ک') LIKE ?";
    $params[]='%'.normalize_persian_search($q).'%';
}
if($status==='review')$where[]="i.review_status='needs_review'";elseif($status==='active')$where[]='i.active=1';elseif($status==='inactive')$where[]='i.active=0';
$stmt=$pdo->prepare("SELECT i.*,COALESCE(b.quantity_base,0) quantity_base,(SELECT COUNT(*) FROM inventory_purchase_units pu WHERE pu.inventory_item_id=i.id AND pu.active=1) purchase_unit_count,(SELECT COUNT(*) FROM inventory_purchase_units pu WHERE pu.inventory_item_id=i.id AND pu.active=1 AND pu.review_status='needs_review') purchase_unit_review_count FROM inventory_items i LEFT JOIN inventory_balances b ON b.inventory_item_id=i.id WHERE ".implode(' AND ',$where)." ORDER BY i.active DESC,FIELD(i.review_status,'needs_review','ready'),i.category,i.name,i.id");$stmt->execute($params);$items=$stmt->fetchAll();
$reviewCount=(int)$pdo->query("SELECT COUNT(*) FROM inventory_items i WHERE i.active=1 AND (i.review_status='needs_review' OR EXISTS(SELECT 1 FROM inventory_purchase_units pu WHERE pu.inventory_item_id=i.id AND pu.active=1 AND pu.review_status='needs_review'))")->fetchColumn();
panel_header('مدیریت کالاهای انبار','inventory');
?>
<div class="panel-balanced-actions is-fill-last inventory-management-actions">
<a class="btn btn-primary" href="inventory_item_form.php"><?= ui_icon('plus') ?> کالای جدید</a>
<?php if($reviewCount): ?><a class="btn btn-light" href="inventory_review.php">بازبینی · <?= fa_digits($reviewCount) ?></a><?php endif; ?>
<a class="btn btn-light" href="inventory_categories.php">دسته‌ها</a>
<a class="btn btn-light" href="inventory.php">بازگشت به انبار</a>
</div>
<p class="panel-section-meta"><?= fa_digits(count($items)) ?> کالا · مشخصات پایه و واحدهای خرید</p>
<form method="get" class="inventory-filters inventory-management-filters"><div class="form-group inventory-search"><label>جست‌وجو</label><input class="form-control" type="search" inputmode="search" enterkeyhint="search" autocomplete="off" name="q" value="<?= e($q) ?>" placeholder="نام کالا"></div><div class="form-group"><label>وضعیت</label><select class="form-control" name="status" data-choice-mode="compact"><option value="all" <?= $status==='all'?'selected':'' ?>>همه</option><option value="review" <?= $status==='review'?'selected':'' ?>>نیاز به تأیید</option><option value="active" <?= $status==='active'?'selected':'' ?>>فعال</option><option value="inactive" <?= $status==='inactive'?'selected':'' ?>>غیرفعال</option></select></div><button class="btn btn-light">اعمال</button></form>
<section class="card panel-list-card inventory-management-list">
<?php if(!$items): ?><div class="inventory-empty">کالایی پیدا نشد.</div><?php endif; ?>
<?php foreach($items as $item):
$needsReview=(string)$item['review_status']==='needs_review'||(int)($item['purchase_unit_review_count']??0)>0;
$statusLabel=!(int)$item['active']?'غیرفعال':($needsReview?'نیاز به تأیید':'آماده');
$statusClass=!(int)$item['active']?'':($needsReview?'is-warning':'is-ok'); ?>
<div class="panel-list-row inventory-management-row">
  <div class="panel-list-primary"><a href="inventory_item_form.php?id=<?= (int)$item['id'] ?>"><?= e((string)$item['name']) ?></a><small><?= e(inventory_category_labels()[(string)$item['category']]??(string)$item['category']) ?> · <?= e(inventory_department_labels()[(string)$item['default_department']]??'مشترک') ?> · <?= fa_digits((int)$item['purchase_unit_count']) ?> واحد خرید <span class="inventory-management-mobile-meta">· واحد موجودی: <?= e(inventory_base_unit_labels()[(string)$item['base_unit']]??(string)$item['base_unit']) ?></span></small></div>
  <div class="panel-list-value"><span>واحد موجودی</span><strong><?= e(inventory_base_unit_labels()[(string)$item['base_unit']]??(string)$item['base_unit']) ?></strong></div>
  <div class="panel-list-value"><span>موجودی</span><strong><?= e(inventory_format_quantity((int)$item['quantity_base'],(string)$item['base_unit'])) ?></strong></div>
  <div class="inventory-management-status"><span class="panel-status-badge <?= e($statusClass) ?>"><?= e($statusLabel) ?></span></div>
  <div class="panel-row-actions"><div class="row-action-menu" data-action-menu data-action-menu-label="<?= e((string)$item['name']) ?>"><button type="button" class="btn btn-sm btn-light" data-action-menu-trigger aria-expanded="false" aria-haspopup="menu"><?= ui_icon('more') ?> مدیریت</button><div class="row-action-popover" data-action-menu-popover role="menu"><a role="menuitem" href="inventory_item_form.php?id=<?= (int)$item['id'] ?>"><?= ui_icon('edit') ?> ویرایش</a><a role="menuitem" href="inventory_item_form.php?copy=<?= (int)$item['id'] ?>"><?= ui_icon('copy') ?> کپی به‌عنوان کالای جدید</a><form method="post" data-confirm="<?= (int)$item['active']===1?'کالا از انتخاب‌های روزمره کنار گذاشته می‌شود؛ سابقه و موجودی آن حذف نمی‌شود.':'کالا دوباره در عملیات روزمره قابل استفاده می‌شود.' ?>" data-confirm-title="<?= (int)$item['active']===1?'غیرفعال‌کردن کالا؟':'فعال‌کردن کالا؟' ?>" data-confirm-ok="<?= (int)$item['active']===1?'غیرفعال‌کردن':'فعال‌کردن' ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><input type="hidden" name="desired_active" value="<?= (int)$item['active']===1?'0':'1' ?>"><button role="menuitem" name="action" value="set_active"><?= ui_icon('refresh') ?> <?= (int)$item['active']===1?'غیرفعال‌کردن':'فعال‌کردن' ?></button></form></div></div></div>
</div>
<?php endforeach; ?>
</section>
<?php panel_footer(); ?>
