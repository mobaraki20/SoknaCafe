<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_login(['admin']);
require dirname(__DIR__) . '/includes/panel_layout.php';

$pdo=db();
$categoryIcons = [''=>'انتخاب خودکار'];
foreach (category_icon_library() as $group) $categoryIcons += $group;
$menus=$pdo->query('SELECT id,menu_key,name,status FROM menus ORDER BY sort_order,id')->fetchAll();
$menuIds=array_map('intval',array_column($menus,'id'));
$id=(int)($_GET['id']??$_POST['id']??0);
$category=null;$selectedMenuIds=[];
if($id){
    $stmt=$pdo->prepare('SELECT * FROM categories WHERE id=?');$stmt->execute([$id]);$category=$stmt->fetch()?:null;
    if(!$category)render_recovery_error_page(404,'دسته‌بندی پیدا نشد','ممکن است دسته‌بندی حذف شده باشد یا پیوند قدیمی باشد.','categories.php','بازگشت به دسته‌بندی‌ها');
    $ms=$pdo->prepare('SELECT menu_id FROM menu_categories WHERE category_id=? ORDER BY sort_order,menu_id');$ms->execute([$id]);$selectedMenuIds=array_map('intval',$ms->fetchAll(PDO::FETCH_COLUMN));
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf($_POST['csrf_token']??null);
    $oldImage=$category['image_path']??null;$uploadedImage=null;
    try{
        $name=text_substr(trim((string)($_POST['name']??'')),0,120);
        $active=isset($_POST['active'])?1:0;
        $audience=(string)($_POST['audience']??'guest_staff');
        $requestedMenus=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['menu_ids']??[])),static fn(int $v):bool=>$v>0)));
        if(array_diff($requestedMenus,$menuIds))throw new RuntimeException('یکی از منوهای انتخاب‌شده معتبر نیست.');
        if(!menu_catalog_valid_audience($audience))throw new RuntimeException('نوع نمایش دسته‌بندی معتبر نیست.');
        $icon=(string)($_POST['icon_key']??'');if(!array_key_exists($icon,$categoryIcons))$icon='';
        if($name==='')throw new RuntimeException('نام دسته‌بندی الزامی است.');
        $dupe=$pdo->prepare('SELECT id FROM categories WHERE name=? AND id<>? LIMIT 1');$dupe->execute([$name,$id]);if($dupe->fetchColumn())throw new RuntimeException('دسته‌بندی دیگری با همین نام وجود دارد.');
        $imageChoice=resolve_image_input($_FILES['image']??[],$oldImage,$_POST);$image=$imageChoice['path'];$uploadedImage=$imageChoice['uploaded'];$imageChanged=$imageChoice['changed'];
        $pdo->beginTransaction();
        if($id){
            $lock=$pdo->prepare('SELECT id FROM categories WHERE id=? FOR UPDATE');$lock->execute([$id]);if(!$lock->fetchColumn())throw new RuntimeException('دسته‌بندی پیدا نشد.');
            $pdo->prepare('UPDATE categories SET name=?,audience=?,image_path=?,icon_key=?,active=? WHERE id=?')->execute([$name,$audience,$image,$icon?:null,$active,$id]);
            $categoryId=$id;$auditAction='menu.category_updated';
        }else{
            $nextSort=(int)$pdo->query('SELECT COALESCE(MAX(sort_order),0)+10 FROM categories')->fetchColumn();$categoryKey=menu_catalog_new_category_key($pdo);
            $pdo->prepare('INSERT INTO categories(category_key,name,audience,image_path,icon_key,sort_order,active) VALUES(?,?,?,?,?,?,?)')->execute([$categoryKey,$name,$audience,$image,$icon?:null,$nextSort,$active]);
            $categoryId=(int)$pdo->lastInsertId();$auditAction='menu.category_created';
        }
        if($requestedMenus){
            $marks=implode(',',array_fill(0,count($requestedMenus),'?'));
            $delete=$pdo->prepare("DELETE FROM menu_categories WHERE category_id=? AND menu_id NOT IN ($marks)");$delete->execute([$categoryId,...$requestedMenus]);
        }else{$pdo->prepare('DELETE FROM menu_categories WHERE category_id=?')->execute([$categoryId]);}
        $nextOrder=$pdo->prepare('SELECT COALESCE(MAX(sort_order),0)+10 FROM menu_categories WHERE menu_id=?');
        $insertMembership=$pdo->prepare('INSERT IGNORE INTO menu_categories(menu_id,category_id,sort_order) VALUES(?,?,?)');
        foreach($requestedMenus as $menuId){$nextOrder->execute([$menuId]);$insertMembership->execute([$menuId,$categoryId,(int)$nextOrder->fetchColumn()]);}
        // A category is the permission boundary for item placement. Remove stale item memberships
        // immediately when the category leaves a menu; guest/POS must never disagree with Admin.
        if($requestedMenus){
            $marks=implode(',',array_fill(0,count($requestedMenus),'?'));
            $prune=$pdo->prepare("DELETE FROM menu_items WHERE item_id IN (SELECT id FROM items WHERE category_id=?) AND menu_id NOT IN ($marks)");
            $prune->execute([$categoryId,...$requestedMenus]);
        }else{
            $prune=$pdo->prepare('DELETE FROM menu_items WHERE item_id IN (SELECT id FROM items WHERE category_id=?)');$prune->execute([$categoryId]);
        }
        audit_log_write_strict($pdo,$auditAction,'category',$categoryId,['name'=>$name,'active'=>$active,'audience'=>$audience,'menu_ids'=>$requestedMenus],(int)(current_user()['id']??0));
        $pdo->commit();
        if(($imageChanged??false)&&$oldImage)delete_upload_path((string)$oldImage);
        flash('success',$id?'دسته‌بندی ویرایش شد.':'دسته‌بندی اضافه شد.');redirect('categories.php');
    }catch(PDOException $e){
        if($pdo->inTransaction())$pdo->rollBack();if($uploadedImage)delete_upload_path($uploadedImage);error_log('category save: '.$e->getMessage());flash('error','ذخیره دسته‌بندی انجام نشد. دوباره تلاش کنید.');
        $category=array_merge($category??[],$_POST);$selectedMenuIds=array_values(array_unique(array_map('intval',(array)($_POST['menu_ids']??[]))));
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();if($uploadedImage)delete_upload_path($uploadedImage);flash('error',$e instanceof RuntimeException?$e->getMessage():'ذخیره دسته‌بندی انجام نشد.');
        $category=array_merge($category??[],$_POST);$selectedMenuIds=array_values(array_unique(array_map('intval',(array)($_POST['menu_ids']??[]))));
    }
}

panel_header($id?'ویرایش دسته‌بندی':'افزودن دسته‌بندی','categories');
?>
<div class="toolbar"><a class="btn btn-light" href="categories.php">بازگشت به فهرست</a><span class="muted"><?= $id?'تغییرات روی همین دسته اعمال می‌شود.':'دسته جدید پس از ذخیره در مدیریت منو قابل استفاده است.' ?></span></div>
<section class="card category-form-card"><div class="card-head"><div><h2><?= $id?'ویرایش '.e((string)$category['name']):'دسته‌بندی جدید' ?></h2><small>دسته برای نمایش منو است؛ محل آماده‌سازی هر آیتم مستقل باقی می‌ماند.</small></div></div><div class="card-body"><form method="post" enctype="multipart/form-data" class="form-grid"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
<div class="form-group full"><label for="categoryName">نام دسته‌بندی</label><input class="form-control" id="categoryName" name="name" maxlength="120" value="<?= e($category['name']??'') ?>" required></div>
<div class="form-group"><label for="categoryAudience">نمایش برای</label><select class="form-control" id="categoryAudience" name="audience" data-choice-mode="compact"><option value="guest_staff" <?= ($category['audience']??'guest_staff')==='guest_staff'?'selected':'' ?>>مهمان و کارکنان</option><option value="staff_only" <?= ($category['audience']??'')==='staff_only'?'selected':'' ?>>فقط کارکنان</option></select><small class="muted">دسته داخلی در منوی مهمان نمایش داده نمی‌شود، حتی اگر آیتم آن عمومی باشد.</small></div>
<div class="form-group full"><span>عضویت در منوها</span><div class="check-grid"><?php foreach($menus as $menu): ?><label><input type="checkbox" name="menu_ids[]" value="<?= (int)$menu['id'] ?>" <?= in_array((int)$menu['id'],$selectedMenuIds,true)?'checked':'' ?>> <?= e((string)$menu['name']) ?> <small class="muted"><?= e((string)$menu['status']) ?></small></label><?php endforeach; ?></div><small class="muted">یک دسته می‌تواند در چند منو باشد؛ ترتیب هر منو مستقل نگهداری می‌شود.</small></div>
<div class="form-group full"><label>نمای دسته در منوی مهمان</label><div class="category-icon-picker" role="radiogroup" aria-label="انتخاب آیکن دسته‌بندی"><div class="category-icon-grid"><div class="category-icon-option is-auto"><input id="categoryIconAuto" type="radio" name="icon_key" value="" <?= empty($category['icon_key'])?'checked':'' ?>><label for="categoryIconAuto"><?= ui_icon('sparkles') ?><span>انتخاب خودکار براساس نام دسته</span></label></div></div><?php foreach(category_icon_library() as $groupLabel=>$icons): ?><details><summary><?= e($groupLabel) ?><span><?= fa_digits(count($icons)) ?> آیکن</span></summary><div class="category-icon-grid"><?php foreach($icons as $value=>$label): $inputId='categoryIcon'.preg_replace('/[^A-Za-z0-9_-]/','',$value); ?><div class="category-icon-option"><input id="<?= e($inputId) ?>" type="radio" name="icon_key" value="<?= e($value) ?>" <?= ($category['icon_key']??'')===$value?'checked':'' ?>><label for="<?= e($inputId) ?>"><?= ui_icon($value) ?><span><?= e($label) ?></span></label></div><?php endforeach; ?></div></details><?php endforeach; ?></div><small class="muted">عکس دسته در صورت انتخاب، جای آیکن را می‌گیرد.</small></div>
<?= image_picker_html($category['image_path']??null,'عکس اختیاری دسته','تصویر مربع و ساده بهترین نتیجه را دارد. با حذف تصویر، آیکن انتخاب‌شده نمایش داده می‌شود.','image/jpeg,image/png,image/webp') ?>
<div class="form-group full"><label><input type="checkbox" name="active" <?= !isset($category['active'])||$category['active']?'checked':'' ?>> فعال باشد</label></div><div class="form-group full actions"><button class="btn btn-primary">ذخیره دسته‌بندی</button><a class="btn btn-light" href="categories.php">انصراف</a></div></form></div></section>
<?php panel_footer(); ?>
