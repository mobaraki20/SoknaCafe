<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_login(['admin']);
require dirname(__DIR__) . '/includes/panel_layout.php';
$inventoryEnabled = sokna_module_enabled('inventory');
if ($inventoryEnabled) inventory_seed_if_empty(db());

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$copyFrom = $id === 0 ? (int)($_GET['copy_from'] ?? 0) : 0;
$item = null;
$copySourceName = '';
$selectedTagIds = [];
$inventoryRecipe = null;
$recipeRows = [];
$menuOptions = db()->query('SELECT id,menu_key,name,status FROM menus ORDER BY sort_order,id')->fetchAll();
$selectedMenuIds = [];

if ($id) {
    $stmt = db()->prepare('SELECT * FROM items WHERE id=?');
    $stmt->execute([$id]);
    $item = $stmt->fetch() ?: null;
    if (!$item) render_recovery_error_page(404, 'آیتم منو پیدا نشد', 'ممکن است آیتم حذف شده باشد یا پیوند قدیمی باشد.', 'items.php', 'بازگشت به آیتم‌ها');
    $q = db()->prepare('SELECT tag_id FROM item_tags WHERE item_id=?');
    $q->execute([$id]);
    $selectedTagIds = array_map('intval', array_column($q->fetchAll(), 'tag_id'));
    $selectedMenuIds = menu_catalog_item_menu_ids(db(), $id);
    if ($inventoryEnabled) {
        $inventoryRecipe = inventory_active_recipe(db(), $id);
        foreach ((array)($inventoryRecipe['components'] ?? []) as $component) {
            $recipeRows[] = ['inventory_item_id'=>(int)$component['inventory_item_id'],'quantity_base'=>(int)$component['quantity_base']];
        }
    }
} elseif ($copyFrom) {
    $stmt = db()->prepare('SELECT * FROM items WHERE id=?');
    $stmt->execute([$copyFrom]);
    $source = $stmt->fetch() ?: null;
    if (!$source) render_recovery_error_page(404, 'آیتم مبنا پیدا نشد', 'آیتمی که برای ساخت کپی انتخاب شده بود دیگر در دسترس نیست.', 'items.php', 'بازگشت به آیتم‌ها');
    $copySourceName = (string)$source['name'];
    $item = $source;
    $item['id'] = 0;
    $item['name'] = 'کپی از ' . $copySourceName;
    $item['item_code'] = '';
    $item['active'] = 0;
    $item['available'] = 1;
    $q = db()->prepare('SELECT tag_id FROM item_tags WHERE item_id=?');
    $q->execute([$copyFrom]);
    $selectedTagIds = array_map('intval', array_column($q->fetchAll(), 'tag_id'));
    $selectedMenuIds = menu_catalog_item_menu_ids(db(), $copyFrom);
    if ($inventoryEnabled) {
        $inventoryRecipe = inventory_active_recipe(db(), $copyFrom);
        foreach ((array)($inventoryRecipe['components'] ?? []) as $component) {
            $recipeRows[] = ['inventory_item_id'=>(int)$component['inventory_item_id'],'quantity_base'=>(int)$component['quantity_base']];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    $pdo = db();
    $oldImage = $item['image_path'] ?? null;
    $uploadedImage = null;
    try {
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $name = text_substr(trim((string)($_POST['name'] ?? '')), 0, 160);
        $itemCode = text_substr(trim((string)($_POST['item_code'] ?? '')), 0, 80);
        $description = text_substr(trim((string)($_POST['description'] ?? '')), 0, 4000);
        $price = (int)str_replace([',','٬',' '], '', en_digits((string)($_POST['price'] ?? '0')));
        $preparationStation = normalize_preparation_station((string)($_POST['preparation_station'] ?? 'other'));
        $suggested = (int)($_POST['suggested_item_id'] ?? 0);
        if ($suggested === $id) $suggested = 0;
        if ($categoryId < 1 || $name === '' || $price < 0) throw new RuntimeException('نام، دسته‌بندی و قیمت معتبر لازمه.');
        if ((string)($_POST['menu_membership_present'] ?? '') !== '1') throw new RuntimeException('اطلاعات عضویت منو کامل دریافت نشد؛ صفحه را تازه کن و دوباره ذخیره کن.');
        $requestedMenuIds = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['menu_ids'] ?? [])), static fn(int $menuId): bool => $menuId > 0)));
        $allowedMenuIds = menu_catalog_allowed_menu_ids_for_category($pdo,$categoryId);
        if (array_diff($requestedMenuIds,$allowedMenuIds)) throw new RuntimeException('یکی از منوهای انتخاب‌شده برای دسته‌بندی فعلی مجاز نیست.');
        if ($itemCode !== '' && !preg_match('/^[A-Za-z0-9._-]+$/', $itemCode)) throw new RuntimeException('کد آیتم فقط حروف انگلیسی، عدد، خط تیره و نقطه داشته باشه.');
        $originalCategoryId = (int)($item['category_id'] ?? 0);
        if ($id > 0 && $originalCategoryId === $categoryId) {
            $sort = (int)($item['sort_order'] ?? 0);
        } else {
            $sortStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0)+10 FROM items WHERE category_id=?');
            $sortStmt->execute([$categoryId]);
            $sort = (int)$sortStmt->fetchColumn();
        }

        $scheduleStart = parse_optional_jalali_day_boundary((string)($_POST['schedule_start_date_j'] ?? ''), false, 'شروع نمایش آیتم');
        $scheduleEnd = parse_optional_jalali_day_boundary((string)($_POST['schedule_end_date_j'] ?? ''), true, 'پایان نمایش آیتم');
        if ($scheduleStart !== null && $scheduleEnd !== null && strtotime($scheduleEnd) < strtotime($scheduleStart)) throw new RuntimeException('پایان نمایش نباید قبل از شروع باشه.');
        $days = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['schedule_days'] ?? [])), static fn($d) => $d >= 1 && $d <= 7)));
        sort($days);
        $dailyStart = trim((string)($_POST['daily_start'] ?? ''));
        $dailyEnd = trim((string)($_POST['daily_end'] ?? ''));
        if (($dailyStart === '') !== ($dailyEnd === '')) throw new RuntimeException('برای ساعت روزانه، شروع و پایان رو با هم وارد کن.');
        if ($dailyStart !== '' && (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $dailyStart) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $dailyEnd))) throw new RuntimeException('ساعت روزانه معتبر نیست.');

        $marketing = (int)($_POST['marketing_tag_id'] ?? 0);
        $attributes = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['attribute_tag_ids'] ?? [])))));
        if (count($attributes) > 2) throw new RuntimeException('برای هر آیتم حداکثر دو ویژگی محصول انتخاب کن.');
        $tagIds = array_values(array_filter(array_merge($marketing ? [$marketing] : [], $attributes)));
        if ($tagIds) {
            $marks = implode(',', array_fill(0, count($tagIds), '?'));
            $q = $pdo->prepare("SELECT id,tag_type FROM tags WHERE id IN ($marks) AND active=1");
            $q->execute($tagIds);
            $valid = $q->fetchAll();
            if (count($valid) !== count($tagIds)) throw new RuntimeException('یکی از برچسب‌ها معتبر نیست.');
            $marketingCount = count(array_filter($valid, static fn($t) => $t['tag_type'] === 'marketing'));
            $attributeCount = count(array_filter($valid, static fn($t) => $t['tag_type'] === 'attribute'));
            if ($marketingCount > 1 || $attributeCount > 2) throw new RuntimeException('محدودیت برچسب‌های آیتم رعایت نشده.');
        }

        $imageMode = trim((string)($_POST['image_mode'] ?? 'keep'));
        if ($imageMode === 'upload') assert_square_uploaded_image($_FILES['image'] ?? []);
        if ($imageMode === 'library') assert_square_library_image($_POST['image_library'] ?? null);
        $imageChoice = resolve_image_input($_FILES['image'] ?? [], $oldImage, $_POST);
        $image = $imageChoice['path'];
        $uploadedImage = $imageChoice['uploaded'];
        $imageChanged = $imageChoice['changed'];
        $available = isset($_POST['available']) ? 1 : 0;
        $active = isset($_POST['active']) ? 1 : 0;
        $featured = isset($_POST['featured']) ? 1 : 0;
        $staffOnly = isset($_POST['staff_only']) ? 1 : 0;
        $takeawayAllowed = isset($_POST['takeaway_allowed']) ? 1 : 0;
        $recipeRows = [];
        $normalizedRecipeRows = [];
        if ($inventoryEnabled) {
            if ((string)($_POST['recipe_state_present'] ?? '') !== '1') throw new RuntimeException('اطلاعات مواد مصرفی کامل دریافت نشد؛ صفحه را تازه کن و دوباره ذخیره کن.');
            $recipeItemIds = array_values((array)($_POST['recipe_inventory_item_id'] ?? []));
            $recipeQuantities = array_values((array)($_POST['recipe_quantity_base'] ?? []));
            if (count($recipeItemIds) !== count($recipeQuantities)) throw new RuntimeException('اطلاعات مواد مصرفی ناقص است.');
            if (count($recipeItemIds) > 50) throw new RuntimeException('تعداد مواد مصرفی این آیتم غیرعادی است.');
            foreach ($recipeItemIds as $recipeIndex=>$inventoryItemRaw) {
                $inventoryItemId = (int)$inventoryItemRaw;
                $quantityRaw = trim(en_digits((string)($recipeQuantities[$recipeIndex] ?? '')));
                $quantityRaw = str_replace(['٬',',',' '],'',$quantityRaw);
                if ($inventoryItemId < 1 || !preg_match('/^[0-9]+$/',$quantityRaw) || (int)$quantityRaw < 1) {
                    throw new RuntimeException('برای هر ماده انبار، مقدار مصرف معتبر و بیشتر از صفر وارد کن.');
                }
                $recipeRows[] = ['inventory_item_id'=>$inventoryItemId,'quantity_base'=>(int)$quantityRaw];
            }
            $normalizedRecipeRows = inventory_normalize_recipe_components($recipeRows);
        }

        $pdo->beginTransaction();
        if ($inventoryEnabled && $active === 1) inventory_validate_recipe_components_locked($pdo,$normalizedRecipeRows);
        $params = [
            $itemCode !== '' ? $itemCode : null, $categoryId, $name, $description, $price, $image,
            $available, $active, $featured, $staffOnly, $takeawayAllowed, $preparationStation,
            $scheduleStart,
            $scheduleEnd,
            $days ? implode(',', $days) : null, $dailyStart ?: null, $dailyEnd ?: null,
            $suggested ?: null, $sort,
        ];
        if ($id) {
            $params[] = $id;
            $pdo->prepare('UPDATE items SET item_code=?,category_id=?,name=?,description=?,price=?,image_path=?,available=?,active=?,featured=?,staff_only=?,takeaway_allowed=?,preparation_station=?,schedule_start=?,schedule_end=?,schedule_days=?,daily_start=?,daily_end=?,suggested_item_id=?,sort_order=? WHERE id=?')->execute($params);
            $itemId = $id;
        } else {
            $pdo->prepare('INSERT INTO items(item_code,category_id,name,description,price,image_path,available,active,featured,staff_only,takeaway_allowed,preparation_station,schedule_start,schedule_end,schedule_days,daily_start,daily_end,suggested_item_id,sort_order) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute($params);
            $itemId = (int)$pdo->lastInsertId();
            if ($itemCode === '') $pdo->prepare('UPDATE items SET item_code=? WHERE id=?')->execute(['ITEM-' . $itemId, $itemId]);
        }
        menu_catalog_set_item_memberships($pdo,$itemId,$categoryId,$requestedMenuIds);
        $pdo->prepare('DELETE FROM item_tags WHERE item_id=?')->execute([$itemId]);
        $tagStmt = $pdo->prepare('INSERT INTO item_tags(item_id,tag_id) VALUES(?,?)');
        foreach ($tagIds as $tagId) $tagStmt->execute([$itemId, $tagId]);
        if ($inventoryEnabled) inventory_save_recipe_locked($pdo, $itemId, $recipeRows, (int)(current_user()['id'] ?? 0));
        $afterImportant=['name'=>$name,'price'=>$price,'available'=>$available,'active'=>$active,'featured'=>$featured,'staff_only'=>$staffOnly,'takeaway_allowed'=>$takeawayAllowed,'preparation_station'=>$preparationStation];
        $actorUserId=(int)(current_user()['id']??0);
        if($id>0){
            $importantChanges=menu_item_important_changes((array)$item,$afterImportant);
            if($importantChanges)audit_log_write('menu.item_important_updated','menu_item',$itemId,['name'=>$name,'changes'=>$importantChanges],$actorUserId);
        }else{
            audit_log_write('menu.item_created','menu_item',$itemId,['name'=>$name,'state'=>menu_item_audit_snapshot($afterImportant)],$actorUserId);
        }
        $pdo->commit();
        if (($imageChanged ?? false) && $oldImage) delete_upload_path($oldImage);
        flash('success', $id ? 'تغییرات آیتم ذخیره شد.' : 'آیتم تازه به‌صورت ' . ($active ? 'فعال' : 'پیش‌نویس') . ' ساخته شد.');
        redirect('items.php');
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($uploadedImage) delete_upload_path($uploadedImage);
        flash('error', 'کد آیتم تکراریه یا یکی از ارتباط‌ها معتبر نیست.');
        $item = array_merge($item ?? [], $_POST);
        $selectedMenuIds = array_values(array_unique(array_map('intval',(array)($_POST['menu_ids']??[]))));
        $selectedTagIds = array_values(array_filter(array_merge([(int)($_POST['marketing_tag_id'] ?? 0)], array_map('intval', (array)($_POST['attribute_tag_ids'] ?? [])))));
    } catch (RuntimeException|InvalidArgumentException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($uploadedImage) delete_upload_path($uploadedImage);
        flash('error', $e->getMessage());
        $item = array_merge($item ?? [], $_POST);
        $selectedMenuIds = array_values(array_unique(array_map('intval',(array)($_POST['menu_ids']??[]))));
        $selectedTagIds = array_values(array_filter(array_merge([(int)($_POST['marketing_tag_id'] ?? 0)], array_map('intval', (array)($_POST['attribute_tag_ids'] ?? [])))));
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($uploadedImage) delete_upload_path($uploadedImage);
        error_log('menu item save: '.$e->getMessage());
        flash('error', 'ذخیره آیتم انجام نشد. دوباره تلاش کن.');
        $item = array_merge($item ?? [], $_POST);
        $selectedMenuIds = array_values(array_unique(array_map('intval',(array)($_POST['menu_ids']??[]))));
        $selectedTagIds = array_values(array_filter(array_merge([(int)($_POST['marketing_tag_id'] ?? 0)], array_map('intval', (array)($_POST['attribute_tag_ids'] ?? [])))));
    }
}

$inventoryItems = $inventoryEnabled ? db()->query("SELECT i.id,i.name,i.base_unit,i.active,b.average_unit_cost,COALESCE(b.cost_status,'unknown') cost_status FROM inventory_items i LEFT JOIN inventory_balances b ON b.inventory_item_id=i.id ORDER BY i.active DESC,i.name,i.id")->fetchAll() : [];
$categories = db()->query('SELECT id,name FROM categories ORDER BY sort_order,id')->fetchAll();
$tags = db()->query('SELECT * FROM tags WHERE active=1 ORDER BY tag_type,sort_order,id')->fetchAll();
$allItems = db()->query('SELECT id,name FROM items WHERE active=1 ORDER BY name')->fetchAll();
$inventoryItemMap = [];
foreach ($inventoryItems as $inventoryRow) $inventoryItemMap[(int)$inventoryRow['id']] = $inventoryRow;
$recipeSalePrice = max(0,(int)($item['price'] ?? 0));
$recipeCostPreview = $inventoryEnabled ? inventory_recipe_cost_preview(db(),$recipeRows,$recipeSalePrice) : ['known_subtotal'=>0,'unknown_count'=>0,'estimated_count'=>0,'ratio'=>null];
$recipeKnownSubtotal = (int)$recipeCostPreview['known_subtotal'];
$recipeUnknownCount = (int)$recipeCostPreview['unknown_count'];
$recipeEstimatedCount = (int)$recipeCostPreview['estimated_count'];
$recipeCostRatio = $recipeCostPreview['ratio'];
$recipeComponentCount = count($recipeRows);
$recipeDisclosureSummary = $recipeComponentCount < 1 ? 'تعریف نشده' : (fa_digits($recipeComponentCount) . ' ماده · ' . ($recipeUnknownCount > 0 ? 'هزینه کامل قابل محاسبه نیست' : 'هزینه تقریبی ' . toman($recipeKnownSubtotal)));
$daysLabels = [7=>'شنبه',1=>'یکشنبه',2=>'دوشنبه',3=>'سه‌شنبه',4=>'چهارشنبه',5=>'پنجشنبه',6=>'جمعه'];
$savedDays = array_map('intval', explode(',', (string)($item['schedule_days'] ?? '')));
$currentCategoryId=(int)($item['category_id']??0);
$currentAllowedMenuIds=$currentCategoryId>0?menu_catalog_allowed_menu_ids_for_category(db(),$currentCategoryId):[];
$categoryMenuMap=[];foreach($categories as $categoryRow)$categoryMenuMap[(int)$categoryRow['id']]=menu_catalog_allowed_menu_ids_for_category(db(),(int)$categoryRow['id']);
$title = $id ? 'ویرایش آیتم' : ($copyFrom ? 'ساخت آیتم مشابه' : 'افزودن آیتم');
panel_header($title, 'items');
panel_subnav(['items'=>['items.php','مدیریت منو'],'order'=>['items.php?view=arrange','چیدمان'],'tags'=>['tags.php','برچسب‌ها'],'transfer'=>['menu_transfer.php','ورود و خروجی']], 'items', 'مدیریت آیتم‌های منو');
?>
<?php if ($copyFrom): ?><div class="alert alert-info duplicate-context"><?= ui_icon('copy') ?><div><strong>ساخت آیتم مشابه «<?= e($copySourceName) ?>»</strong><p>اطلاعات قبلی کپی شده‌اند؛ کد جدید خودکار ساخته می‌شود و آیتم تا زمان فعال‌کردن در منوی مهمان دیده نمی‌شود.<?= $inventoryEnabled ? ' مواد مصرفی را قبل از انتشار دوباره بررسی کن.' : '' ?></p></div></div><?php endif; ?>
<section class="card"><div class="card-body"><form method="post" enctype="multipart/form-data" class="form-grid" id="menuItemForm"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><?php if($inventoryEnabled): ?><input type="hidden" name="recipe_state_present" value="1"><?php endif; ?>
<div class="form-group"><label for="itemName">نام آیتم</label><input class="form-control" id="itemName" name="name" value="<?= e($item['name'] ?? '') ?>" required></div>
<div class="form-group"><label for="itemCategory">دسته‌بندی</label><select class="form-control" id="itemCategory" name="category_id" data-choice-mode="adaptive" data-choice-search="true" required><option value="">انتخاب</option><?php foreach($categories as $cat): ?><option value="<?= (int)$cat['id'] ?>" <?= (int)($item['category_id'] ?? 0) === (int)$cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option><?php endforeach; ?></select></div>
<fieldset class="form-group full item-menu-memberships"><legend>نمایش در منوها</legend><input type="hidden" name="menu_membership_present" value="1"><div class="check-grid" id="itemMenuMemberships"><?php foreach($menuOptions as $menu): $menuId=(int)$menu['id']; $allowed=in_array($menuId,$currentAllowedMenuIds,true); ?><label class="<?= $allowed?'':'is-disabled' ?>"><input type="checkbox" name="menu_ids[]" value="<?= $menuId ?>" <?= in_array($menuId,$selectedMenuIds,true)?'checked':'' ?> <?= $allowed?'':'disabled' ?>> <span><?= e((string)$menu['name']) ?></span><small class="muted"><?= e((string)$menu['status']) ?></small></label><?php endforeach; ?></div><small class="muted">دسته‌بندی تعیین می‌کند کدام منوها مجازند؛ خود آیتم می‌تواند در یک یا چند منوی مجاز نمایش داده شود.</small></fieldset>
<div class="form-group"><label for="itemPrice">قیمت، تومان</label><input class="form-control" id="itemPrice" type="text" inputmode="numeric" enterkeyhint="done" name="price" value="<?= e(money_input_display_value((string)($item['price'] ?? 0))) ?>" data-money-input required></div>
<div class="form-group"><label for="itemPreparationStation">محل آماده‌سازی</label><select class="form-control" id="itemPreparationStation" name="preparation_station" data-choice-mode="compact"><?php foreach(preparation_stations() as $stationKey=>$stationLabel): ?><option value="<?= e($stationKey) ?>" <?= normalize_preparation_station((string)($item['preparation_station'] ?? 'other')) === $stationKey ? 'selected' : '' ?>><?= e($stationLabel) ?></option><?php endforeach; ?></select><small class="muted">برای گروه‌بندی سفارش و اعلام شلوغی استفاده می‌شود.</small></div>
<div class="form-group full"><label for="itemDescription">توضیحات</label><textarea class="form-control" id="itemDescription" name="description"><?= e($item['description'] ?? '') ?></textarea></div>
<?= image_picker_html($item['image_path'] ?? null, 'تصویر آیتم', 'تصویر باید مربع ۱:۱ باشد؛ اندازه پیشنهادی ۱۲۰۰×۱۲۰۰ پیکسل است. تصاویر عمودی یا افقی ذخیره نمی‌شوند.', 'image/jpeg,image/png,image/webp', true, 'square') ?>
<div class="form-group full check-row"><label><input type="checkbox" name="available" <?= !isset($item['available']) || $item['available'] ? 'checked' : '' ?>> قابل سفارش</label><label><input type="checkbox" name="active" <?= !isset($item['active']) || $item['active'] ? 'checked' : '' ?>> منتشرشده <small class="muted">در منوهای انتخاب‌شده قابل نمایش است</small></label><label><input type="checkbox" name="featured" <?= !empty($item['featured']) ? 'checked' : '' ?>> پیشنهاد ویژه</label><label><input type="checkbox" name="staff_only" <?= !empty($item['staff_only']) ? 'checked' : '' ?>> فقط کارکنان <small class="muted">در منوی مهمان نمایش داده نمی‌شود</small></label><label><input type="checkbox" name="takeaway_allowed" <?= !isset($item['takeaway_allowed']) || (int)$item['takeaway_allowed']===1 ? 'checked' : '' ?>> امکان بیرون‌بر <small class="muted">در صورت خاموش بودن فقط داخل کافه سرو می‌شود</small></label></div>

<?php if($inventoryEnabled): ?>
<details class="form-disclosure full recipe-disclosure" id="recipeDisclosure" <?= ($copyFrom || $_SERVER['REQUEST_METHOD']==='POST')?'open':'' ?>><summary><?= ui_icon('archive') ?><span><strong>مصرف از انبار</strong><small id="recipeDisclosureSummary"><?= e($recipeDisclosureSummary) ?></small></span></summary><div class="disclosure-body recipe-disclosure-body">
<div class="form-section-header"><div class="panel-copy-stack"><small class="muted">مواد مصرفی هر عدد از این آیتم؛ هنگام تأیید سفارش از موجودی کسر می‌شوند.</small></div><button class="btn btn-sm btn-light" type="button" id="addRecipeComponent"><?= ui_icon('plus') ?> افزودن ماده</button></div>
<div class="recipe-cost-preview" id="recipeCostPreview" aria-live="polite"><?php if(!$recipeRows): ?><span class="muted">بدون مصرف از انبار</span><?php elseif($recipeUnknownCount>0): ?><strong>هزینه کامل قابل محاسبه نیست</strong><span><?= fa_digits($recipeUnknownCount) ?> ماده بدون هزینه ثبت‌شده است<?php if($recipeKnownSubtotal>0): ?> · بخش قابل محاسبه: <?= e(toman($recipeKnownSubtotal)) ?><?php endif; ?></span><?php else: ?><strong>هزینه تقریبی مواد: <?= e(toman($recipeKnownSubtotal)) ?></strong><span>براساس میانگین هزینه فعلی انبار<?php if($recipeCostRatio!==null): ?> · <bdi class="recipe-cost-ratio" dir="ltr"><?= e(fa_digits(number_format($recipeCostRatio,1,'.',''))) ?>٪</bdi> قیمت فروش<?php endif; ?><?= $recipeEstimatedCount>0?' · برخی هزینه‌ها برآوردی‌اند':'' ?></span><?php endif; ?></div>
<div class="recipe-component-list" id="recipeComponentRows">
<?php if(!$recipeRows): ?><div class="recipe-empty-note" data-recipe-empty>برای این آیتم هنوز ماده مصرفی تعریف نشده است.</div><?php endif; ?>
<?php foreach($recipeRows as $recipeIndex=>$recipeRow): $selectedInventoryId=(int)$recipeRow['inventory_item_id']; $selectedInventory=$inventoryItemMap[$selectedInventoryId]??null; ?>
<div class="recipe-component-row" data-recipe-row><div class="form-group"><label>ماده انبار</label><select class="form-control recipe-item" name="recipe_inventory_item_id[]" data-choice-mode="browse" data-choice-search="true" data-choice-search-focus="true" required><option value="" disabled hidden data-choice-placeholder="true">انتخاب ماده</option><?php foreach($inventoryItems as $inventoryOption): $optionId=(int)$inventoryOption['id']; if((int)$inventoryOption['active']!==1 && $optionId!==$selectedInventoryId)continue; ?><option value="<?= $optionId ?>" <?= $optionId===$selectedInventoryId?'selected':'' ?> <?= (int)$inventoryOption['active']!==1?'data-inactive-recipe-item="1"':'' ?>><?= e((string)$inventoryOption['name']) ?><?= (int)$inventoryOption['active']!==1?' · غیرفعال':'' ?></option><?php endforeach; ?></select></div><div class="form-group"><label>مقدار مصرف برای ۱ عدد <span class="recipe-unit-label"><?= $selectedInventory?'· '.e(inventory_base_unit_labels()[(string)$selectedInventory['base_unit']]??(string)$selectedInventory['base_unit']):'' ?></span></label><input class="form-control recipe-qty" type="text" inputmode="numeric" enterkeyhint="done" autocomplete="off" name="recipe_quantity_base[]" value="<?= e(fa_digits((string)$recipeRow['quantity_base'])) ?>" required></div><button class="btn btn-sm btn-light recipe-remove" type="button" aria-label="حذف ماده از فهرست مصرف">حذف</button></div>
<?php endforeach; ?>
</div>
<template id="recipeComponentTemplate"><div class="recipe-component-row" data-recipe-row><div class="form-group"><label>ماده انبار</label><select class="form-control recipe-item" name="recipe_inventory_item_id[]" data-choice-mode="browse" data-choice-search="true" data-choice-search-focus="true" required><option value="" disabled selected hidden data-choice-placeholder="true">انتخاب ماده</option><?php foreach($inventoryItems as $inventoryOption): if((int)$inventoryOption['active']!==1)continue; ?><option value="<?= (int)$inventoryOption['id'] ?>"><?= e((string)$inventoryOption['name']) ?></option><?php endforeach; ?></select></div><div class="form-group"><label>مقدار مصرف برای ۱ عدد <span class="recipe-unit-label"></span></label><input class="form-control recipe-qty" type="text" inputmode="numeric" enterkeyhint="done" autocomplete="off" name="recipe_quantity_base[]" value="" required></div><button class="btn btn-sm btn-light recipe-remove" type="button" aria-label="حذف ماده از فهرست مصرف">حذف</button></div></template>
</div></details>
<?php endif; ?>

<details class="form-disclosure full" <?= (!empty($item['item_code']) || !empty($item['schedule_start']) || !empty($selectedTagIds)) && !$copyFrom ? 'open' : '' ?>><summary><?= ui_icon('settings') ?><span><strong>تنظیمات تکمیلی</strong><small>کد فنی، پیشنهاد مکمل، برچسب‌ها و زمان‌بندی</small></span></summary><div class="form-grid disclosure-body">
<div class="form-group"><label>کد فنی آیتم</label><input class="form-control ltr-input" dir="ltr" name="item_code" value="<?= e($item['item_code'] ?? '') ?>" placeholder="خالی بماند تا خودکار ساخته شود"><small class="muted">در منوی مهمان نمایش داده نمی‌شود.</small></div>
<div class="form-group"><label>پیشنهاد مکمل</label><select class="form-control" name="suggested_item_id" data-choice-mode="browse"><option value="0">بدون پیشنهاد</option><?php foreach($allItems as $candidate): if((int)$candidate['id'] === $id) continue; ?><option value="<?= (int)$candidate['id'] ?>" <?= (int)($item['suggested_item_id'] ?? 0) === (int)$candidate['id'] ? 'selected' : '' ?>><?= e($candidate['name']) ?></option><?php endforeach; ?></select></div>
<div class="form-group"><label>برچسب بازاریابی</label><select class="form-control" name="marketing_tag_id" data-choice-mode="browse"><option value="0">بدون برچسب</option><?php foreach($tags as $tag): if($tag['tag_type'] !== 'marketing') continue; ?><option value="<?= (int)$tag['id'] ?>" <?= in_array((int)$tag['id'], $selectedTagIds, true) ? 'selected' : '' ?>><?= e($tag['title']) ?></option><?php endforeach; ?></select></div>
<div class="form-group full"><label>ویژگی‌های محصول، حداکثر دو مورد</label><div class="tag-check-grid"><?php foreach($tags as $tag): if($tag['tag_type'] !== 'attribute') continue; ?><label><span><?= e($tag['title']) ?></span><input type="checkbox" name="attribute_tag_ids[]" value="<?= (int)$tag['id'] ?>" <?= in_array((int)$tag['id'], $selectedTagIds, true) ? 'checked' : '' ?>></label><?php endforeach; ?></div></div>
<div class="form-group full schedule-panel"><strong>زمان‌بندی نمایش، اختیاری</strong><small class="muted">بازه تاریخ مشخص می‌کند این قانون در چه روزهایی معتبر است؛ ساعت روزانه زمان نمایش داخل هر روز را تعیین می‌کند.</small><div class="form-grid schedule-grid"><div class="form-group"><label>شروع بازه</label><div class="jalali-date-control"><input class="form-control" id="itemScheduleStartDateJ" name="schedule_start_date_j" data-jalali-date inputmode="none" value="<?= e((string)($_POST['schedule_start_date_j'] ?? (!empty($item['schedule_start']) ? jalali_date_input((string)$item['schedule_start']) : ''))) ?>" placeholder="۱۴۰۵/۰۵/۲۰" aria-label="تاریخ شروع نمایش آیتم"><button class="jalali-date-button" type="button" data-open-jalali="itemScheduleStartDateJ" aria-label="انتخاب تاریخ شروع از تقویم"><?= ui_icon('calendar') ?></button></div></div><div class="form-group"><label>پایان بازه</label><div class="jalali-date-control"><input class="form-control" id="itemScheduleEndDateJ" name="schedule_end_date_j" data-jalali-date inputmode="none" value="<?= e((string)($_POST['schedule_end_date_j'] ?? (!empty($item['schedule_end']) ? jalali_date_input((string)$item['schedule_end']) : ''))) ?>" placeholder="۱۴۰۵/۰۵/۲۷" aria-label="تاریخ پایان نمایش آیتم"><button class="jalali-date-button" type="button" data-open-jalali="itemScheduleEndDateJ" aria-label="انتخاب تاریخ پایان از تقویم"><?= ui_icon('calendar') ?></button></div></div><div class="form-group"><label>ساعت شروع روزانه</label><input class="form-control ltr-input" dir="ltr" type="time" name="daily_start" data-minute-step="15" step="900" value="<?= e(substr((string)($item['daily_start'] ?? ''),0,5)) ?>" aria-label="ساعت شروع روزانه نمایش آیتم"></div><div class="form-group"><label>ساعت پایان روزانه</label><input class="form-control ltr-input" dir="ltr" type="time" name="daily_end" data-minute-step="15" step="900" value="<?= e(substr((string)($item['daily_end'] ?? ''),0,5)) ?>" aria-label="ساعت پایان روزانه نمایش آیتم"></div><div class="form-group full"><label>روزهای نمایش</label><div class="day-check-grid"><?php foreach($daysLabels as $day=>$label): ?><label><span><?= e($label) ?></span><input type="checkbox" name="schedule_days[]" value="<?= $day ?>" <?= in_array($day,$savedDays,true) ? 'checked' : '' ?>></label><?php endforeach; ?></div></div></div></div>
</div></details>
<div class="form-group full actions"><button class="btn btn-primary">ذخیره آیتم</button><a class="btn btn-light" href="items.php">بازگشت</a></div>
</form></div></section>
<script>
window.SOKNA_CATEGORY_MENU_IDS=<?= json_script($categoryMenuMap) ?>;
(()=>{const category=document.getElementById('itemCategory'),box=document.getElementById('itemMenuMemberships');if(!category||!box)return;let previous=String(category.value||'');const inputs=()=>[...box.querySelectorAll('input[name="menu_ids[]"]')];const sync=()=>{const current=String(category.value||''),allowed=new Set((window.SOKNA_CATEGORY_MENU_IDS?.[current]||[]).map(Number)),wasBlank=previous==='';let selectedAllowed=0;inputs().forEach(input=>{const ok=allowed.has(Number(input.value));input.disabled=!ok;input.closest('label')?.classList.toggle('is-disabled',!ok);if(!ok)input.checked=false;if(ok&&input.checked)selectedAllowed++;});if(wasBlank&&selectedAllowed===0)inputs().forEach(input=>{if(!input.disabled)input.checked=true;});previous=current;};category.addEventListener('change',sync);sync();})();
</script>
<?php if($inventoryEnabled): ?>
<script>
window.SOKNA_INVENTORY_ITEMS=<?= json_script(array_map(static fn($row)=>[
    'id'=>(int)$row['id'],'name'=>(string)$row['name'],'base_unit'=>(string)$row['base_unit'],
    'unit_label'=>inventory_base_unit_labels()[(string)$row['base_unit']]??(string)$row['base_unit'],
    'active'=>(int)$row['active']===1,
    'average_unit_cost'=>$row['average_unit_cost']===null?null:(float)$row['average_unit_cost'],
    'cost_status'=>(string)$row['cost_status'],
],$inventoryItems)) ?>;
(()=>{
 const box=document.getElementById('recipeComponentRows'),add=document.getElementById('addRecipeComponent'),template=document.getElementById('recipeComponentTemplate'),preview=document.getElementById('recipeCostPreview'),summary=document.getElementById('recipeDisclosureSummary'),disclosure=document.getElementById('recipeDisclosure'),priceInput=document.getElementById('itemPrice');
 if(!box||!add||!template)return;
 const items=Array.isArray(window.SOKNA_INVENTORY_ITEMS)?window.SOKNA_INVENTORY_ITEMS:[],itemById=id=>items.find(item=>Number(item.id)===Number(id));
 const rows=()=>[...box.querySelectorAll('[data-recipe-row]')];
 const numberValue=value=>{const normalized=window.CafeUI?.digits?.normalizeNumberText?.(value)??String(value??'');const clean=String(normalized).replace(/[٬,\s]/g,'').replace('٫','.');const number=Number(clean);return Number.isFinite(number)?number:0;};
 const syncDuplicateOptions=()=>{const selections=rows().map(row=>Number(row.querySelector('.recipe-item')?.value||0)).filter(Boolean);rows().forEach(row=>{const select=row.querySelector('.recipe-item'),own=Number(select?.value||0);select?.querySelectorAll('option[value]:not([value=""])').forEach(option=>{const value=Number(option.value||0),item=itemById(value),usedElsewhere=value!==own&&selections.includes(value);option.disabled=Boolean(usedElsewhere||(!item?.active&&value!==own));});});};
 const syncCost=()=>{if(!preview)return;let known=0,unknown=0,estimated=0,componentCount=0;rows().forEach(row=>{const id=Number(row.querySelector('.recipe-item')?.value||0),qty=numberValue(row.querySelector('.recipe-qty')?.value||'');if(!id||qty<=0)return;componentCount++;const item=itemById(id);if(!item||item.average_unit_cost===null){unknown++;return;}known+=qty*Number(item.average_unit_cost);if(item.cost_status!=='known')estimated++;});if(componentCount===0){preview.innerHTML='<span class="muted">بدون مصرف از انبار</span>';if(summary)summary.textContent='تعریف نشده';return;}const countLabel=componentCount.toLocaleString('fa-IR')+' ماده',money=Math.round(known).toLocaleString('fa-IR')+' تومان',price=numberValue(priceInput?.value||'');if(unknown>0){preview.innerHTML=`<strong>هزینه کامل قابل محاسبه نیست</strong><span>${unknown.toLocaleString('fa-IR')} ماده بدون هزینه ثبت‌شده است${known>0?' · بخش قابل محاسبه: '+money:''}</span>`;if(summary)summary.textContent=countLabel+' · هزینه کامل قابل محاسبه نیست';return;}const ratio=price>0?(known/price*100):null;preview.innerHTML=`<strong>هزینه تقریبی مواد: ${money}</strong><span>براساس میانگین هزینه فعلی انبار${ratio!==null?' · <bdi class="recipe-cost-ratio" dir="ltr">'+ratio.toLocaleString('fa-IR',{maximumFractionDigits:1})+'٪</bdi> قیمت فروش':''}${estimated>0?' · برخی هزینه‌ها برآوردی‌اند':''}</span>`;if(summary)summary.textContent=countLabel+' · هزینه تقریبی '+money;};
 const syncRow=(row,updateCost=true)=>{const select=row.querySelector('.recipe-item'),unit=row.querySelector('.recipe-unit-label'),item=itemById(Number(select?.value||0));if(unit)unit.textContent=item?'· '+item.unit_label:'';syncDuplicateOptions();if(updateCost)syncCost();};
 const bind=(row,initial=false)=>{if(row.dataset.recipeBound==='1')return;row.dataset.recipeBound='1';row.querySelector('.recipe-item')?.addEventListener('change',()=>{syncRow(row);if(window.CafeUI?.keyboard?.isTouchContext?.())return;requestAnimationFrame(()=>{const qty=row.querySelector('.recipe-qty');qty?.focus();qty?.select?.();});});row.querySelector('.recipe-qty')?.addEventListener('input',syncCost);row.querySelector('.recipe-remove')?.addEventListener('click',()=>{row.remove();if(!rows().length){const empty=document.createElement('div');empty.className='recipe-empty-note';empty.dataset.recipeEmpty='';empty.textContent='برای این آیتم هنوز ماده مصرفی تعریف نشده است.';box.appendChild(empty);}syncDuplicateOptions();syncCost();});syncRow(row,!initial);};
 rows().forEach(row=>bind(row,true));priceInput?.addEventListener('input',syncCost);
 add.addEventListener('click',()=>{if(disclosure)disclosure.open=true;box.querySelector('[data-recipe-empty]')?.remove();const row=template.content.firstElementChild.cloneNode(true);box.appendChild(row);bind(row);syncDuplicateOptions();syncCost();requestAnimationFrame(()=>row.querySelector('.recipe-item')?.click());});
 syncDuplicateOptions();
})();
</script>
<?php endif; ?>
<?php panel_footer(); ?>
