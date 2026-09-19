<?php
declare(strict_types=1);

/** Load the versioned default Sokna menu/catalog data. */
function default_menu_seed(): array
{
    static $seed = null;
    if (is_array($seed)) return $seed;
    $path = dirname(__DIR__) . '/database/default_menu.json';
    $raw = is_file($path) ? file_get_contents($path) : false;
    if ($raw === false) throw new RuntimeException('فایل داده اولیه منوی سکنا پیدا نشد.');
    $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    foreach (['menus','categories','items'] as $required) {
        if (!is_array($decoded[$required] ?? null)) throw new RuntimeException('ساختار داده اولیه منوی سکنا معتبر نیست.');
    }
    $seed = $decoded;
    return $seed;
}

function default_menu_station(string $station): string
{
    return in_array($station, ['kitchen','hot_bar','cold_bar','none','other'], true) ? $station : 'other';
}

function default_menu_status(string $status): string
{
    return in_array($status, ['active','draft','inactive'], true) ? $status : 'draft';
}

function default_category_audience(string $audience): string
{
    return in_array($audience, ['guest_staff','staff_only'], true) ? $audience : 'guest_staff';
}

/**
 * Install/synchronize the versioned default catalog.
 * Menus/categories use stable machine keys; display names are editable metadata.
 * Item identity remains item_code. Live availability is never re-opened by a seed sync.
 */
function sync_default_menu_seed(PDO $pdo, bool $forceImages = true, bool $retireLegacy = true): array
{
    $seed = default_menu_seed();
    $stats = [
        'menus_created'=>0,'menus_updated'=>0,
        'categories_created'=>0,'categories_updated'=>0,
        'items_created'=>0,'items_updated'=>0,'items_retired'=>0,'categories_retired'=>0,
        'menu_categories_synced'=>0,'menu_items_synced'=>0,
    ];
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        $menuMap = [];
        $selectMenu = $pdo->prepare('SELECT id FROM menus WHERE menu_key=? LIMIT 1');
        $insertMenu = $pdo->prepare('INSERT INTO menus(menu_key,name,status,sort_order,schedule_days,daily_start,daily_end) VALUES(?,?,?,?,?,?,?)');
        $updateMenu = $pdo->prepare('UPDATE menus SET name=?,status=?,sort_order=?,schedule_days=?,daily_start=?,daily_end=? WHERE id=?');
        foreach ($seed['menus'] as $menu) {
            $key = trim((string)($menu['menu_key'] ?? ''));
            $name = trim((string)($menu['name'] ?? ''));
            if ($key === '' || $name === '') continue;
            $values = [
                $name,
                default_menu_status((string)($menu['status'] ?? 'draft')),
                (int)($menu['sort_order'] ?? 0),
                $menu['schedule_days'] ?? null,
                $menu['daily_start'] ?? null,
                $menu['daily_end'] ?? null,
            ];
            $selectMenu->execute([$key]);
            $row = $selectMenu->fetch();
            if ($row) {
                $id = (int)$row['id'];
                $updateMenu->execute([...$values, $id]);
                $stats['menus_updated']++;
            } else {
                $insertMenu->execute([$key, ...$values]);
                $id = (int)$pdo->lastInsertId();
                $stats['menus_created']++;
            }
            $menuMap[$key] = $id;
        }

        $categoryMap = [];
        $selectCategory = $pdo->prepare('SELECT id,image_path FROM categories WHERE category_key=? LIMIT 1');
        $insertCategory = $pdo->prepare('INSERT INTO categories(category_key,name,audience,image_path,icon_key,sort_order,active) VALUES(?,?,?,?,?,?,1)');
        $updateCategory = $pdo->prepare('UPDATE categories SET name=?,audience=?,image_path=?,icon_key=?,sort_order=? WHERE id=?');
        foreach ($seed['categories'] as $category) {
            $key = trim((string)($category['category_key'] ?? ''));
            $name = trim((string)($category['name'] ?? ''));
            if ($key === '' || $name === '') continue;
            $selectCategory->execute([$key]);
            $row = $selectCategory->fetch();
            if ($row) {
                $currentImage = (string)($row['image_path'] ?? '');
                $replaceBundledImage = $currentImage !== '' && str_starts_with($currentImage, 'assets/menu/default/');
                $imagePath = ($forceImages || $currentImage === '' || $replaceBundledImage) ? ($category['image_path'] ?? null) : $currentImage;
                $id = (int)$row['id'];
                $updateCategory->execute([
                    $name,
                    default_category_audience((string)($category['audience'] ?? 'guest_staff')),
                    $imagePath,
                    $category['icon_key'] ?? null,
                    (int)($category['sort_order'] ?? 0),
                    $id,
                ]);
                $stats['categories_updated']++;
            } else {
                $insertCategory->execute([
                    $key,$name,default_category_audience((string)($category['audience'] ?? 'guest_staff')),
                    $category['image_path'] ?? null,$category['icon_key'] ?? null,(int)($category['sort_order'] ?? 0),
                ]);
                $id = (int)$pdo->lastInsertId();
                $stats['categories_created']++;
            }
            $categoryMap[$key] = $id;
        }

        $upsertMenuCategory = $pdo->prepare('INSERT INTO menu_categories(menu_id,category_id,sort_order) VALUES(?,?,?) ON DUPLICATE KEY UPDATE sort_order=VALUES(sort_order)');
        foreach ($seed['categories'] as $category) {
            $categoryKey = (string)($category['category_key'] ?? '');
            $categoryId = $categoryMap[$categoryKey] ?? 0;
            if ($categoryId < 1) continue;
            foreach (array_values(array_unique(array_map('strval', $category['menus'] ?? []))) as $menuKey) {
                $menuId = $menuMap[$menuKey] ?? 0;
                if ($menuId < 1) continue;
                $upsertMenuCategory->execute([$menuId,$categoryId,(int)($category['sort_order'] ?? 0)]);
                $stats['menu_categories_synced']++;
            }
        }

        $selectItem = $pdo->prepare('SELECT id,image_path,available FROM items WHERE item_code=? LIMIT 1');
        $insertItem = $pdo->prepare('INSERT INTO items(item_code,category_id,name,description,price,image_path,available,active,featured,staff_only,sellable_kind,preparation_station,sort_order) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $updateItem = $pdo->prepare('UPDATE items SET category_id=?,name=?,description=?,price=?,image_path=?,active=?,featured=?,staff_only=?,sellable_kind=?,preparation_station=?,sort_order=? WHERE id=?');
        $itemMap = [];
        foreach ($seed['items'] as $item) {
            $code = trim((string)($item['item_code'] ?? ''));
            $categoryId = $categoryMap[(string)($item['category_key'] ?? '')] ?? 0;
            if ($code === '' || $categoryId < 1) continue;
            $selectItem->execute([$code]);
            $row = $selectItem->fetch();
            if ($row) {
                $currentImage = (string)($row['image_path'] ?? '');
                $replaceBundledImage = $currentImage !== '' && str_starts_with($currentImage, 'assets/menu/default/');
                $imagePath = ($forceImages || $currentImage === '' || $replaceBundledImage) ? ($item['image_path'] ?? null) : $currentImage;
                $id = (int)$row['id'];
                $updateItem->execute([
                    $categoryId,(string)$item['name'],(string)($item['description'] ?? ''),(int)$item['price'],$imagePath,
                    (int)($item['active'] ?? 1),(int)($item['featured'] ?? 0),(int)($item['staff_only'] ?? 0),normalize_sellable_kind($item['sellable_kind'] ?? null),
                    default_menu_station((string)($item['preparation_station'] ?? 'other')),(int)($item['sort_order'] ?? 0),$id,
                ]);
                $stats['items_updated']++;
            } else {
                $insertItem->execute([
                    $code,$categoryId,(string)$item['name'],(string)($item['description'] ?? ''),(int)$item['price'],$item['image_path'] ?? null,
                    (int)($item['available'] ?? 1),(int)($item['active'] ?? 1),(int)($item['featured'] ?? 0),(int)($item['staff_only'] ?? 0),normalize_sellable_kind($item['sellable_kind'] ?? null),
                    default_menu_station((string)($item['preparation_station'] ?? 'other')),(int)($item['sort_order'] ?? 0),
                ]);
                $id = (int)$pdo->lastInsertId();
                $stats['items_created']++;
            }
            $itemMap[$code] = $id;
        }

        $upsertMenuItem = $pdo->prepare('INSERT IGNORE INTO menu_items(menu_id,item_id) VALUES(?,?)');
        foreach ($seed['items'] as $item) {
            $itemId = $itemMap[(string)($item['item_code'] ?? '')] ?? 0;
            if ($itemId < 1) continue;
            foreach (array_values(array_unique(array_map('strval', $item['menus'] ?? []))) as $menuKey) {
                $menuId = $menuMap[$menuKey] ?? 0;
                if ($menuId < 1) continue;
                $upsertMenuItem->execute([$menuId,$itemId]);
                $stats['menu_items_synced']++;
            }
        }

        if ($retireLegacy) {
            $retiredCodes = array_values(array_filter(array_map('strval', $seed['retired_item_codes'] ?? [])));
            if ($retiredCodes) {
                $placeholders = implode(',', array_fill(0, count($retiredCodes), '?'));
                $stmt = $pdo->prepare("UPDATE items SET active=0,available=0 WHERE item_code IN ($placeholders) AND active=1");
                $stmt->execute($retiredCodes);
                $stats['items_retired'] = $stmt->rowCount();
            }
            // Legacy names are only retired when they are not one of the keyed categories installed above.
            $retiredNames = array_values(array_filter(array_map('strval', $seed['retired_category_names'] ?? [])));
            if ($retiredNames) {
                $placeholders = implode(',', array_fill(0, count($retiredNames), '?'));
                $stmt = $pdo->prepare("UPDATE categories SET active=0 WHERE name IN ($placeholders) AND category_key LIKE 'legacy-%' AND active=1");
                $stmt->execute($retiredNames);
                $stats['categories_retired'] = $stmt->rowCount();
            }
        }

        if ($ownsTransaction) $pdo->commit();
        return $stats;
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
