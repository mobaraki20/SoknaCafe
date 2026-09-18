<?php
declare(strict_types=1);

/** Machine keys are stable identities; display names may change freely. */
function menu_catalog_new_category_key(PDO $pdo): string
{
    $check = $pdo->prepare('SELECT 1 FROM categories WHERE category_key=? LIMIT 1');
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $key = 'custom-' . bin2hex(random_bytes(10));
        $check->execute([$key]);
        if (!$check->fetchColumn()) return $key;
    }
    throw new RuntimeException('شناسه داخلی دسته‌بندی ساخته نشد. دوباره تلاش کنید.');
}

function menu_catalog_new_menu_key(PDO $pdo): string
{
    $check = $pdo->prepare('SELECT 1 FROM menus WHERE menu_key=? LIMIT 1');
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $key = 'custom-' . bin2hex(random_bytes(10));
        $check->execute([$key]);
        if (!$check->fetchColumn()) return $key;
    }
    throw new RuntimeException('شناسه داخلی منو ساخته نشد. دوباره تلاش کنید.');
}

function menu_catalog_valid_menu_status(string $status): bool
{
    return in_array($status, ['active','draft','inactive'], true);
}

function menu_catalog_valid_audience(string $audience): bool
{
    return in_array($audience, ['guest_staff','staff_only'], true);
}

/** SQL fragment for a menu's recurring service window. Empty times mean all-day. */
function menu_catalog_schedule_sql(string $alias = 'm'): string
{
    $a = preg_replace('/[^A-Za-z0-9_]/', '', $alias) ?: 'm';
    return "($a.daily_start IS NULL OR $a.daily_end IS NULL OR
        ($a.daily_start<=$a.daily_end
            AND ($a.schedule_days IS NULL OR $a.schedule_days='' OR FIND_IN_SET(DAYOFWEEK(NOW()),$a.schedule_days))
            AND TIME(NOW()) BETWEEN $a.daily_start AND $a.daily_end)
        OR
        ($a.daily_start>$a.daily_end AND (
            (TIME(NOW())>=$a.daily_start AND ($a.schedule_days IS NULL OR $a.schedule_days='' OR FIND_IN_SET(DAYOFWEEK(NOW()),$a.schedule_days)))
            OR
            (TIME(NOW())<=$a.daily_end AND ($a.schedule_days IS NULL OR $a.schedule_days='' OR FIND_IN_SET(IF(DAYOFWEEK(NOW())=1,7,DAYOFWEEK(NOW())-1),$a.schedule_days)))
        )))";
}

/** Menus that are published and in their service window right now. */
function menu_catalog_visible_menus(PDO $pdo): array
{
    $schedule = menu_catalog_schedule_sql('m');
    $rows = $pdo->query("SELECT id,menu_key,name,status,sort_order,schedule_days,daily_start,daily_end
        FROM menus m WHERE m.status='active' AND ($schedule) ORDER BY m.sort_order,m.id")->fetchAll();
    foreach ($rows as &$row) {
        $row['id'] = (int)$row['id'];
        $row['sort_order'] = (int)$row['sort_order'];
    }
    unset($row);
    return $rows;
}

function menu_catalog_resolve_menu(array $menus, ?string $requestedKey = null): ?array
{
    $requestedKey = trim((string)$requestedKey);
    if ($requestedKey !== '') {
        foreach ($menus as $menu) if (hash_equals((string)$menu['menu_key'], $requestedKey)) return $menu;
    }
    return $menus[0] ?? null;
}

/**
 * One read model for guest public/table and staff ordering.
 * Visibility differences are context rules here, not duplicated page queries.
 */
function menu_catalog_snapshot(PDO $pdo, string $context, ?string $requestedMenuKey = null, bool $orderableOnly = false): array
{
    if (!in_array($context, ['guest_public','guest_table','staff_order'], true)) {
        throw new InvalidArgumentException('زمینه نمایش منو معتبر نیست.');
    }
    $menus = menu_catalog_visible_menus($pdo);
    $selected = menu_catalog_resolve_menu($menus, $requestedMenuKey);
    if (!$selected) return ['menus'=>[],'selected_menu'=>null,'categories'=>[],'items'=>[]];

    $itemSchedule = item_schedule_sql('i');
    $guest = str_starts_with($context, 'guest_');
    $visibility = $guest ? " AND c.audience='guest_staff' AND COALESCE(i.staff_only,0)=0" : '';
    $available = $orderableOnly ? ' AND i.available=1' : '';
    $stmt = $pdo->prepare("SELECT
        i.id,i.item_code,i.category_id,i.name,i.description,i.price,i.image_path,i.available,i.active,i.featured,i.staff_only,
        i.takeaway_allowed,i.preparation_station,i.suggested_item_id,i.sort_order item_sort,
        c.category_key,c.name category_name,c.audience category_audience,c.image_path category_image,c.icon_key category_icon,
        mc.sort_order category_sort,m.menu_key,m.name menu_name
        FROM menu_items mi
        JOIN menus m ON m.id=mi.menu_id
        JOIN items i ON i.id=mi.item_id
        JOIN categories c ON c.id=i.category_id
        JOIN menu_categories mc ON mc.menu_id=m.id AND mc.category_id=c.id
        WHERE m.id=? AND c.active=1 AND i.active=1 AND ($itemSchedule)$visibility$available
        ORDER BY mc.sort_order,c.id,i.featured DESC,i.sort_order,i.id");
    $stmt->execute([(int)$selected['id']]);
    $rows = $stmt->fetchAll();

    $categories = [];
    foreach ($rows as $row) {
        $categoryId = (int)$row['category_id'];
        $categories[$categoryId] ??= [
            'id'=>$categoryId,
            'category_key'=>(string)$row['category_key'],
            'name'=>(string)$row['category_name'],
            'audience'=>(string)$row['category_audience'],
            'image_path'=>$row['category_image'] ?? null,
            'icon_key'=>$row['category_icon'] ?? null,
            'sort_order'=>(int)$row['category_sort'],
        ];
    }
    return ['menus'=>$menus,'selected_menu'=>$selected,'categories'=>array_values($categories),'items'=>$rows];
}

/** EXISTS expression used by transactional order validation. */
function menu_catalog_orderable_membership_sql(string $itemAlias = 'i'): string
{
    $i = preg_replace('/[^A-Za-z0-9_]/', '', $itemAlias) ?: 'i';
    $menuSchedule = menu_catalog_schedule_sql('mcat');
    return "EXISTS(SELECT 1 FROM menu_items mi_cat
        JOIN menus mcat ON mcat.id=mi_cat.menu_id
        JOIN menu_categories mc_cat ON mc_cat.menu_id=mcat.id AND mc_cat.category_id=$i.category_id
        WHERE mi_cat.item_id=$i.id AND mcat.status='active' AND ($menuSchedule))";
}

/** Menu memberships allowed by a category. The category defines the boundary; the item chooses a subset. */
function menu_catalog_allowed_menu_ids_for_category(PDO $pdo, int $categoryId): array
{
    if ($categoryId < 1) return [];
    $stmt=$pdo->prepare('SELECT menu_id FROM menu_categories WHERE category_id=? ORDER BY menu_id');
    $stmt->execute([$categoryId]);
    return array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));
}

function menu_catalog_item_menu_ids(PDO $pdo, int $itemId): array
{
    if ($itemId < 1) return [];
    $stmt=$pdo->prepare('SELECT menu_id FROM menu_items WHERE item_id=? ORDER BY menu_id');
    $stmt->execute([$itemId]);
    return array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));
}

/** Persist the explicit item choice after proving every selected menu is allowed by its category. */
function menu_catalog_set_item_memberships(PDO $pdo, int $itemId, int $categoryId, array $requestedMenuIds): void
{
    if ($itemId < 1 || $categoryId < 1) throw new InvalidArgumentException('شناسه آیتم یا دسته معتبر نیست.');
    $requested=array_values(array_unique(array_filter(array_map('intval',$requestedMenuIds),static fn(int $id):bool=>$id>0)));
    sort($requested);
    $allowed=menu_catalog_allowed_menu_ids_for_category($pdo,$categoryId);
    sort($allowed);
    if (array_diff($requested,$allowed)) throw new InvalidArgumentException('یکی از منوهای انتخاب‌شده برای این دسته‌بندی مجاز نیست.');
    $pdo->prepare('DELETE FROM menu_items WHERE item_id=?')->execute([$itemId]);
    if($requested){
        $ins=$pdo->prepare('INSERT INTO menu_items(menu_id,item_id) VALUES(?,?)');
        foreach($requested as $menuId)$ins->execute([$menuId,$itemId]);
    }
}

/**
 * Reconcile memberships after a category-only bulk move. Compatible explicit choices survive;
 * an orphaned item inherits the new category menus so a bulk move cannot silently disappear it.
 */
function menu_catalog_sync_item_memberships_to_category(PDO $pdo, int $itemId, int $categoryId): void
{
    $allowed=menu_catalog_allowed_menu_ids_for_category($pdo,$categoryId);
    $current=menu_catalog_item_menu_ids($pdo,$itemId);
    $keep=array_values(array_intersect($current,$allowed));
    menu_catalog_set_item_memberships($pdo,$itemId,$categoryId,$keep ?: $allowed);
}
