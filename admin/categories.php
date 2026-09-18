<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_login(['admin']);
require dirname(__DIR__) . '/includes/panel_layout.php';

$pdo=db();
$userId=(int)(current_user()['id']??0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    try {
        if ($id < 1) throw new RuntimeException('دسته‌بندی معتبر انتخاب نشده است.');
        if ($action === 'set_active') {
            $desired=((string)($_POST['desired_active']??''))==='1'?1:0;
            $pdo->beginTransaction();
            $st=$pdo->prepare('SELECT id,name,active FROM categories WHERE id=? FOR UPDATE');$st->execute([$id]);$before=$st->fetch();
            if(!$before)throw new RuntimeException('دسته‌بندی پیدا نشد.');
            if($desired!==(int)$before['active']){
                $pdo->prepare('UPDATE categories SET active=? WHERE id=?')->execute([$desired,$id]);
                audit_log_write('menu.category_active_changed','category',$id,['name'=>$before['name'],'active_before'=>(int)$before['active'],'active_after'=>$desired],$userId);
            }
            $pdo->commit();
            flash('success',$desired?'دسته‌بندی فعال است.':'دسته‌بندی غیرفعال شد.');
        } elseif ($action === 'delete') {
            $pdo->beginTransaction();
            $q=$pdo->prepare('SELECT name,image_path FROM categories WHERE id=? FOR UPDATE');$q->execute([$id]);$before=$q->fetch();
            if(!$before)throw new RuntimeException('دسته‌بندی پیدا نشد.');
            $pdo->prepare('DELETE FROM categories WHERE id=?')->execute([$id]);
            audit_log_write('menu.category_deleted','category',$id,['name'=>$before['name']],$userId);
            $pdo->commit();
            if (!empty($before['image_path'])) delete_upload_path((string)$before['image_path']);
            flash('success', 'دسته‌بندی حذف شد.');
        } else {
            throw new RuntimeException('عملیات دسته‌بندی معتبر نیست.');
        }
    } catch (PDOException $e) {
        if($pdo->inTransaction())$pdo->rollBack();
        error_log('menu category action: '.$e->getMessage());
        flash('error', $e->getCode()==='23000' ? 'این دسته‌بندی دارای آیتم است و قابل حذف نیست. ابتدا آیتم‌های آن را جابه‌جا کنید.' : 'انجام عملیات دسته‌بندی ممکن نشد. دوباره تلاش کن.');
    } catch (Throwable $e) {
        if($pdo->inTransaction())$pdo->rollBack();
        flash('error', $e instanceof RuntimeException ? $e->getMessage() : 'انجام عملیات دسته‌بندی ممکن نشد.');
    }
    redirect('categories.php');
}

$categories = $pdo->query("SELECT c.*,COUNT(DISTINCT i.id) item_count,GROUP_CONCAT(DISTINCT m.name ORDER BY m.sort_order SEPARATOR '، ') menu_names FROM categories c LEFT JOIN items i ON i.category_id=c.id LEFT JOIN menu_categories mc ON mc.category_id=c.id LEFT JOIN menus m ON m.id=mc.menu_id GROUP BY c.id ORDER BY c.sort_order,c.id")->fetchAll();
panel_header('دسته‌بندی‌های منو', 'categories');
?>
<div class="panel-page-actions"><a class="btn btn-primary" href="category_form.php"><?= ui_icon('plus') ?> افزودن دسته‌بندی</a><a class="btn btn-light" href="items.php"><?= ui_icon('menu') ?> مدیریت منو</a><a class="btn btn-light" href="items.php?view=arrange"><?= ui_icon('refresh') ?> چیدمان منو</a><span class="muted"><?= fa_digits(count($categories)) ?> دسته‌بندی</span></div>
<section class="card panel-list-card category-management-list">
<div class="card-head"><div><h2>فهرست دسته‌بندی‌ها</h2><small>نام، تعداد آیتم و وضعیت؛ ترتیب نمایش هر دسته برای هر منو از بخش «چیدمان» در مدیریت منو تنظیم می‌شود.</small></div></div>
<?php foreach ($categories as $cat): $menuId='categoryActions'.(int)$cat['id']; ?>
<div class="panel-list-row panel-list-row-3 category-management-row">
  <div class="panel-list-primary category-management-primary"><span class="admin-category-orb"><?php if($cat['image_path']): ?><img src="<?= e(asset($cat['image_path'])) ?>" alt=""><?php else: ?><?= ui_icon(category_visual_icon($cat['icon_key'] ?? null,(string)$cat['name'])) ?><?php endif; ?></span><span><strong><?= e((string)$cat['name']) ?></strong><small><?= fa_digits((int)$cat['item_count']) ?> آیتم · <?= ($cat['audience']??'guest_staff')==='staff_only'?'فقط کارکنان':'مهمان و کارکنان' ?></small><?php if(!empty($cat['menu_names'])): ?><small><?= e((string)$cat['menu_names']) ?></small><?php endif; ?></span></div>
  <div class="panel-list-secondary"><span class="panel-status-badge <?= (int)$cat['active']===1?'is-ok':'' ?>"><?= (int)$cat['active']===1?'فعال':'غیرفعال' ?></span></div>
  <div class="panel-row-actions"><div class="row-action-menu" data-action-menu data-action-menu-label="<?= e((string)$cat['name']) ?>"><button type="button" class="btn btn-sm btn-light" data-action-menu-trigger aria-label="عملیات <?= e((string)$cat['name']) ?>" aria-haspopup="menu" aria-expanded="false" aria-controls="<?= e($menuId) ?>"><?= ui_icon('more') ?> مدیریت</button><div class="row-action-popover" data-action-menu-popover id="<?= e($menuId) ?>" role="menu"><a role="menuitem" href="category_form.php?id=<?= (int)$cat['id'] ?>"><?= ui_icon('edit') ?> ویرایش</a><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$cat['id'] ?>"><input type="hidden" name="desired_active" value="<?= (int)$cat['active']===1?'0':'1' ?>"><button role="menuitem" name="action" value="set_active"><?= ui_icon('refresh') ?> <?= (int)$cat['active']===1?'غیرفعال‌کردن':'فعال‌کردن' ?></button></form><form method="post" data-confirm="این دسته‌بندی حذف می‌شود. اگر هنوز در استفاده باشد، سامانه اجازه حذف نمی‌دهد." data-confirm-title="حذف دسته‌بندی؟" data-confirm-ok="حذف دسته" data-confirm-danger="1"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$cat['id'] ?>"><button role="menuitem" class="danger-action" name="action" value="delete"><?= ui_icon('trash') ?> حذف</button></form></div></div></div>
</div>
<?php endforeach; ?>
<?php if (!$categories): ?><div class="empty-state category-empty-state"><strong>هنوز دسته‌بندی ساخته نشده است.</strong><span>برای شروع، اولین دسته‌بندی منو را اضافه کنید.</span><a class="btn btn-primary" href="category_form.php"><?= ui_icon('plus') ?> افزودن دسته‌بندی</a></div><?php endif; ?>
</section>
<?php panel_footer(); ?>
