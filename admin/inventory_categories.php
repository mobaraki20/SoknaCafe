<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
sokna_module_require('inventory');
require_login();
if (!user_can_inventory_manage()) deny_access_and_return();
require dirname(__DIR__) . '/includes/panel_layout.php';

$pdo=db();
$userId=(int)(current_user()['id']??0);
$editingKey=text_substr(trim((string)($_GET['edit']??'')),0,64);
$new=($_GET['new']??'')==='1';

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf($_POST['csrf_token']??null);
    $action=(string)($_POST['action']??'save');
    try{
        if($action==='save'){
            $formKey=text_substr(trim((string)($_POST['category_key']??'')),0,64);
            $name=text_substr(trim((string)($_POST['name']??'')),0,100);
            if($name==='')throw new RuntimeException('نام دسته را وارد کن.');
            $pdo->beginTransaction();
            if($formKey!==''){
                $lock=$pdo->prepare('SELECT * FROM inventory_categories WHERE category_key=? FOR UPDATE');
                $lock->execute([$formKey]);$before=$lock->fetch();
                if(!$before)throw new RuntimeException('دسته پیدا نشد.');
                $pdo->prepare('UPDATE inventory_categories SET name=? WHERE category_key=?')->execute([$name,$formKey]);
                audit_log_write('inventory.category_updated','inventory_category',$formKey,['name_before'=>$before['name'],'name_after'=>$name],$userId);
            }else{
                $key='custom_'.strtolower(substr(bin2hex(random_bytes(8)),0,12));
                $sort=(int)($pdo->query('SELECT COALESCE(MAX(sort_order),0)+10 FROM inventory_categories')->fetchColumn()?:10);
                $pdo->prepare('INSERT INTO inventory_categories(category_key,name,active,system_category,sort_order) VALUES(?,?,1,0,?)')->execute([$key,$name,$sort]);
                audit_log_write('inventory.category_created','inventory_category',$key,['name'=>$name],$userId);
            }
            $pdo->commit();
            flash('success',$formKey!==''?'نام دسته ذخیره شد.':'دسته جدید ساخته شد.');
            redirect('inventory_categories.php');
        }
        if($action==='set_active'){
            $actionKey=text_substr(trim((string)($_POST['category_key']??'')),0,64);
            if($actionKey==='')throw new RuntimeException('دسته معتبر نیست.');
            $desired=((string)($_POST['desired_active']??''))==='1'?1:0;
            $pdo->beginTransaction();
            $lock=$pdo->prepare('SELECT * FROM inventory_categories WHERE category_key=? FOR UPDATE');
            $lock->execute([$actionKey]);$row=$lock->fetch();
            if(!$row)throw new RuntimeException('دسته پیدا نشد.');
            if($desired!==(int)$row['active']){
                $pdo->prepare('UPDATE inventory_categories SET active=? WHERE category_key=?')->execute([$desired,$actionKey]);
                audit_log_write('inventory.category_active_changed','inventory_category',$actionKey,['name'=>$row['name'],'active_before'=>(int)$row['active'],'active_after'=>$desired],$userId);
            }
            $pdo->commit();
            flash('success',$desired?'دسته فعال است.':'دسته غیرفعال شد؛ کالاهای قبلی و تاریخچه تغییری نکردند.');
            redirect('inventory_categories.php');
        }
        throw new RuntimeException('عملیات معتبر نیست.');
    }catch(PDOException $e){
        if($pdo->inTransaction())$pdo->rollBack();
        error_log('inventory category: '.$e->getMessage());
        flash('error',($e->getCode()==='23000')?'نام دسته تکراری است.':'ذخیره دسته انجام نشد. دوباره تلاش کن.');
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        flash('error',$e instanceof RuntimeException?$e->getMessage():'انجام عملیات دسته ممکن نشد.');
    }
}

$editing=null;
if($editingKey!==''){
    $st=$pdo->prepare('SELECT * FROM inventory_categories WHERE category_key=?');
    $st->execute([$editingKey]);$editing=$st->fetch()?:null;
    if(!$editing){flash('error','دسته پیدا نشد.');redirect('inventory_categories.php');}
}
$rows=$pdo->query("SELECT c.*,(SELECT COUNT(*) FROM inventory_items i WHERE i.category=c.category_key) item_count FROM inventory_categories c ORDER BY c.active DESC,c.sort_order,c.name")->fetchAll();
panel_header('دسته‌های انبار','inventory');
?>
<div class="panel-balanced-actions"><a class="btn btn-primary" href="?new=1"><?= ui_icon('plus') ?> دسته جدید</a><a class="btn btn-light" href="inventory_items.php">بازگشت به کالاها</a></div><p class="panel-section-meta">دسته فقط برای گروه‌بندی کالاهاست؛ موجودی و تاریخچه با تغییر نام دسته عوض نمی‌شود.</p>
<div class="panel-surface-stack">
<?php if($new||$editing): ?>
<section class="card inventory-category-editor"><div class="card-head"><div><h2><?= $editing?'ویرایش دسته':'دسته جدید' ?></h2><small>فقط یک نام روشن و کوتاه لازم است.</small></div></div><div class="card-body"><form method="post" class="form-grid"><?= csrf_field() ?><?php if($editing): ?><input type="hidden" name="category_key" value="<?= e((string)$editing['category_key']) ?>"><?php endif; ?><div class="form-group full"><label for="inventoryCategoryName">نام دسته</label><input class="form-control" id="inventoryCategoryName" name="name" maxlength="100" autocomplete="off" value="<?= e((string)($editing['name']??'')) ?>" required></div><div class="form-group full actions"><button class="btn btn-primary" name="action" value="save">ذخیره</button><a class="btn btn-light" href="inventory_categories.php">انصراف</a></div></form></div></section>
<?php endif; ?>
<section class="card panel-list-card inventory-category-list"><?php if(!$rows): ?><div class="inventory-empty">دسته‌ای ثبت نشده است.</div><?php endif; ?><?php foreach($rows as $row): ?><div class="panel-list-row panel-list-row-3"><div class="panel-list-primary"><strong><?= e((string)$row['name']) ?></strong><small><?= fa_digits((int)$row['item_count']) ?> کالا<?= (int)$row['system_category']===1?' · دسته اولیه سامانه':'' ?></small></div><div class="panel-list-secondary"><span class="panel-status-badge <?= (int)$row['active']===1?'is-ok':'' ?>"><?= (int)$row['active']===1?'فعال':'غیرفعال' ?></span></div><div class="panel-row-actions"><div class="row-action-menu" data-action-menu data-action-menu-label="<?= e((string)$row['name']) ?>"><button type="button" class="btn btn-sm btn-light" data-action-menu-trigger aria-expanded="false" aria-haspopup="menu"><?= ui_icon('more') ?> مدیریت</button><div class="row-action-popover" data-action-menu-popover role="menu"><a role="menuitem" href="?edit=<?= e(rawurlencode((string)$row['category_key'])) ?>"><?= ui_icon('edit') ?> تغییر نام</a><form method="post" data-confirm="<?= (int)$row['active']===1?'این دسته از انتخاب‌های جدید پنهان می‌شود؛ کالاهای فعلی تغییر نمی‌کنند.':'این دسته دوباره در انتخاب‌های جدید نمایش داده می‌شود.' ?>" data-confirm-title="<?= (int)$row['active']===1?'غیرفعال‌کردن دسته؟':'فعال‌کردن دسته؟' ?>" data-confirm-ok="<?= (int)$row['active']===1?'غیرفعال‌کردن':'فعال‌کردن' ?>"><?= csrf_field() ?><input type="hidden" name="category_key" value="<?= e((string)$row['category_key']) ?>"><input type="hidden" name="desired_active" value="<?= (int)$row['active']===1?'0':'1' ?>"><button role="menuitem" name="action" value="set_active"><?= ui_icon('refresh') ?> <?= (int)$row['active']===1?'غیرفعال‌کردن':'فعال‌کردن' ?></button></form></div></div></div></div><?php endforeach; ?></section>
</div>
<?php panel_footer(); ?>
