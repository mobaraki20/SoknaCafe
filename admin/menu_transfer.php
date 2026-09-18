<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_login(['admin']);
require dirname(__DIR__) . '/includes/panel_layout.php';

function menu_transfer_csv_download(string $filename, array $rows): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    foreach ($rows as $row) fputcsv($out, array_map('csv_safe_cell', $row));
    fclose($out);
    exit;
}

function menu_transfer_pipe_list(string $raw): array
{
    $values = array_map(static fn(string $v): string => strtolower(trim($v)), explode('|', $raw));
    return array_values(array_unique(array_filter($values, static fn(string $v): bool => $v !== '')));
}

function menu_transfer_machine_key(string $value): bool
{
    return $value !== '' && (bool)preg_match('/^[A-Za-z0-9._-]{1,80}$/', $value);
}

$headers = [
    'item_code','category_key','category_name','category_audience','category_active','category_menu_keys','menu_keys',
    'name','description','price','available','active','featured','staff_only','takeaway_allowed','preparation_station','sort_order',
    'image_path','marketing_tag','attribute_tags','schedule_start','schedule_end','schedule_days','daily_start','daily_end','suggested_item_code',
];

if (($_GET['action'] ?? '') === 'export') {
    $rows = [$headers];
    $sql = "SELECT i.*,c.category_key,c.name category_name,c.audience category_audience,c.active category_active,
        s.item_code suggested_code,
        (SELECT GROUP_CONCAT(m.menu_key ORDER BY m.sort_order,m.id SEPARATOR '|') FROM menu_items mi JOIN menus m ON m.id=mi.menu_id WHERE mi.item_id=i.id) menu_keys,
        (SELECT GROUP_CONCAT(m.menu_key ORDER BY m.sort_order,m.id SEPARATOR '|') FROM menu_categories mc JOIN menus m ON m.id=mc.menu_id WHERE mc.category_id=c.id) category_menu_keys,
        (SELECT GROUP_CONCAT(t.slug ORDER BY t.sort_order SEPARATOR '|') FROM item_tags it JOIN tags t ON t.id=it.tag_id WHERE it.item_id=i.id AND t.tag_type='marketing') marketing_tag,
        (SELECT GROUP_CONCAT(t.slug ORDER BY t.sort_order SEPARATOR '|') FROM item_tags it JOIN tags t ON t.id=it.tag_id WHERE it.item_id=i.id AND t.tag_type='attribute') attribute_tags
        FROM items i
        JOIN categories c ON c.id=i.category_id
        LEFT JOIN items s ON s.id=i.suggested_item_id
        ORDER BY c.category_key,i.sort_order,i.id";
    foreach (db()->query($sql)->fetchAll() as $r) {
        $rows[] = [
            $r['item_code'],$r['category_key'],$r['category_name'],$r['category_audience'],$r['category_active'],$r['category_menu_keys'],$r['menu_keys'],
            $r['name'],$r['description'],$r['price'],$r['available'],$r['active'],$r['featured'],$r['staff_only'],$r['takeaway_allowed'],$r['preparation_station'],$r['sort_order'],
            $r['image_path'],$r['marketing_tag'],$r['attribute_tags'],$r['schedule_start'],$r['schedule_end'],$r['schedule_days'],$r['daily_start'],$r['daily_end'],$r['suggested_code'],
        ];
    }
    menu_transfer_csv_download('sokna-menu-items-' . date('Y-m-d') . '.csv', $rows);
}

if (($_GET['action'] ?? '') === 'template') {
    menu_transfer_csv_download('sokna-menu-items-template.csv', [
        $headers,
        ['COFFEE-LATTE','coffee','قهوه','guest_staff','1','main|breakfast|lunch','main|breakfast','لاته','اسپرسو با شیر گرم','180000','1','1','0','0','1','hot_bar','10','','popular','sugar-free','','','7,1,2,3,4,5','08:00','23:30','CAKE-CHEESE'],
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    $action = (string)($_POST['action'] ?? 'preview');
    try {
        if ($action === 'clear') {
            unset($_SESSION['menu_import_preview']);
            flash('success', 'پیش‌نمایش پاک شد.');
            redirect('menu_transfer.php');
        }

        if ($action === 'preview') {
            $file = $_FILES['menu_file'] ?? [];
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('فایل CSV را انتخاب کنید.');
            if (($file['size'] ?? 0) > 2 * 1024 * 1024) throw new RuntimeException('حجم فایل بیشتر از ۲ مگابایت است.');
            $h = fopen((string)$file['tmp_name'], 'rb');
            if (!$h) throw new RuntimeException('فایل باز نشد.');
            $first = fgets($h);
            if ($first === false) throw new RuntimeException('فایل خالی است.');
            $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
            $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
            rewind($h);
            $header = fgetcsv($h, 0, $delimiter);
            if (!$header) throw new RuntimeException('عنوان ستون‌ها خوانده نشد.');
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', trim((string)$header[0]));
            $header = array_map(static fn($v): string => trim((string)$v), $header);
            foreach (['item_code','category_key','category_name','category_audience','category_menu_keys','menu_keys','name','price'] as $required) {
                if (!in_array($required, $header, true)) throw new RuntimeException('این فایل از قالب فعلی ورود منو نیست. فایل نمونه تازه را دانلود کنید.');
            }

            $pdo = db();
            $knownTags = [];
            foreach ($pdo->query('SELECT slug,tag_type FROM tags')->fetchAll() as $t) $knownTags[strtolower((string)$t['slug'])] = (string)$t['tag_type'];
            $knownMenus = [];
            foreach ($pdo->query('SELECT menu_key,name FROM menus')->fetchAll() as $m) $knownMenus[strtolower((string)$m['menu_key'])] = (string)$m['name'];

            $rows = [];$seenItems = [];$categoryDefinitions = [];$line = 1;$general = [];
            while (($values = fgetcsv($h, 0, $delimiter)) !== false) {
                $line++;
                if ($line > 1001) {$general[] = 'فایل بیشتر از ۱۰۰۰ ردیف دارد.';break;}
                if (!array_filter($values, static fn($v): bool => trim((string)$v) !== '')) continue;
                $values = array_pad($values, count($header), '');
                $raw = array_combine($header, array_slice($values, 0, count($header)));
                if (!is_array($raw)) continue;
                foreach ($raw as $k => $v) {
                    $v = (string)$v;
                    if (preg_match("/^'[=+\\-@\\t\\r]/u", $v)) $v = text_substr($v, 1);
                    $raw[$k] = $v;
                }

                $code = text_substr(trim((string)($raw['item_code'] ?? '')), 0, 80);
                $categoryKey = strtolower(text_substr(trim((string)($raw['category_key'] ?? '')), 0, 80));
                $categoryName = text_substr(trim((string)($raw['category_name'] ?? '')), 0, 120);
                $categoryAudience = trim((string)($raw['category_audience'] ?? 'guest_staff'));
                $categoryActive = bool_from_mixed($raw['category_active'] ?? '1', true) ? 1 : 0;
                $categoryMenuKeys = menu_transfer_pipe_list((string)($raw['category_menu_keys'] ?? ''));
                $menuKeys = menu_transfer_pipe_list((string)($raw['menu_keys'] ?? ''));
                $name = text_substr(trim((string)($raw['name'] ?? '')), 0, 160);
                $priceText = preg_replace('/[\s,٬]/u', '', en_digits((string)($raw['price'] ?? '')));
                $price = preg_match('/^\d+$/', (string)$priceText) ? (int)$priceText : -1;
                $errors = [];

                $itemIdentity = strtolower($code);
                if (!menu_transfer_machine_key($code)) $errors[] = 'کد آیتم نامعتبر';
                if (isset($seenItems[$itemIdentity])) $errors[] = 'کد آیتم تکراری';
                $seenItems[$itemIdentity] = true;
                if (!menu_transfer_machine_key($categoryKey)) $errors[] = 'شناسه داخلی دسته نامعتبر';
                if ($categoryName === '') $errors[] = 'نام دسته خالی';
                if (!menu_catalog_valid_audience($categoryAudience)) $errors[] = 'نوع نمایش دسته نامعتبر';
                if ($name === '') $errors[] = 'نام آیتم خالی';
                if ($price < 0) $errors[] = 'قیمت نامعتبر';
                foreach (array_unique(array_merge($categoryMenuKeys, $menuKeys)) as $menuKey) {
                    if (!menu_transfer_machine_key($menuKey) || !isset($knownMenus[$menuKey])) $errors[] = 'منوی ناشناخته: ' . $menuKey;
                }
                if (array_diff($menuKeys, $categoryMenuKeys)) $errors[] = 'عضویت آیتم باید زیر یکی از منوهای دسته باشد';

                $categoryDefinition = [$categoryName,$categoryAudience,$categoryActive,implode('|',$categoryMenuKeys)];
                if (isset($categoryDefinitions[$categoryKey]) && $categoryDefinitions[$categoryKey] !== $categoryDefinition) {
                    $errors[] = 'تعریف این شناسه دسته در ردیف‌های فایل یکسان نیست';
                } else {
                    $categoryDefinitions[$categoryKey] = $categoryDefinition;
                }

                $image = text_substr(trim((string)($raw['image_path'] ?? '')), 0, 255);
                if ($image !== '' && !preg_match('#^(?:uploads|assets)/[A-Za-z0-9_./-]+$#', $image)) $errors[] = 'مسیر تصویر ناامن';
                elseif ($image !== '' && !is_file(dirname(__DIR__) . '/' . $image)) $errors[] = 'فایل تصویر پیدا نشد';

                $marketing = strtolower(trim((string)($raw['marketing_tag'] ?? '')));
                $attrs = menu_transfer_pipe_list((string)($raw['attribute_tags'] ?? ''));
                if (str_contains($marketing, '|')) $errors[] = 'فقط یک برچسب بازاریابی مجاز است';
                if ($marketing !== '' && ($knownTags[$marketing] ?? '') !== 'marketing') $errors[] = 'برچسب بازاریابی ناشناخته';
                if (count($attrs) > 2) $errors[] = 'بیش از دو ویژگی';
                foreach ($attrs as $slug) if (($knownTags[$slug] ?? '') !== 'attribute') $errors[] = 'ویژگی ناشناخته: ' . $slug;

                $start = trim((string)($raw['schedule_start'] ?? ''));
                $end = trim((string)($raw['schedule_end'] ?? ''));
                if (($start !== '' && !strtotime($start)) || ($end !== '' && !strtotime($end))) $errors[] = 'زمان نامعتبر';
                elseif ($start !== '' && $end !== '' && strtotime($end) <= strtotime($start)) $errors[] = 'پایان نمایش قبل از شروع است';
                $days = trim((string)($raw['schedule_days'] ?? ''));
                if ($days !== '' && !preg_match('/^[1-7](,[1-7])*$/', $days)) $errors[] = 'روزها نامعتبر';
                $dailyStart = trim((string)($raw['daily_start'] ?? ''));
                $dailyEnd = trim((string)($raw['daily_end'] ?? ''));
                if (($dailyStart === '') !== ($dailyEnd === '')) $errors[] = 'ساعت شروع و پایان ناقص';
                if ($dailyStart !== '' && (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $dailyStart) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $dailyEnd))) $errors[] = 'ساعت روزانه نامعتبر';

                $stationRaw = trim((string)($raw['preparation_station'] ?? 'other'));
                if ($stationRaw !== '' && !array_key_exists($stationRaw, preparation_stations())) $errors[] = 'محل آماده‌سازی نامعتبر';
                $station = normalize_preparation_station($stationRaw);

                $rows[] = [
                    'line'=>$line,'item_code'=>$code,'category_key'=>$categoryKey,'category_name'=>$categoryName,'category_audience'=>$categoryAudience,
                    'category_active'=>$categoryActive,'category_menu_keys'=>$categoryMenuKeys,'menu_keys'=>$menuKeys,'name'=>$name,
                    'description'=>text_substr(trim((string)($raw['description'] ?? '')),0,5000),'price'=>$price,
                    'available'=>bool_from_mixed($raw['available'] ?? '1',true)?1:0,'active'=>bool_from_mixed($raw['active'] ?? '1',true)?1:0,
                    'featured'=>bool_from_mixed($raw['featured'] ?? '0',false)?1:0,'staff_only'=>bool_from_mixed($raw['staff_only'] ?? '0',false)?1:0,
                    'takeaway_allowed'=>bool_from_mixed($raw['takeaway_allowed'] ?? '1',true)?1:0,'preparation_station'=>$station,
                    'sort_order'=>(int)en_digits((string)($raw['sort_order'] ?? 0)),'image_path'=>$image,'marketing_tag'=>$marketing,'attribute_tags'=>$attrs,
                    'schedule_start'=>$start,'schedule_end'=>$end,'schedule_days'=>$days,'daily_start'=>$dailyStart,'daily_end'=>$dailyEnd,
                    'suggested_item_code'=>trim((string)($raw['suggested_item_code'] ?? '')),'errors'=>array_values(array_unique($errors)),
                ];
            }
            fclose($h);
            if (!$rows) throw new RuntimeException('ردیفی برای ورود پیدا نشد.');

            $availableCodes = [];
            foreach ($pdo->query("SELECT LOWER(item_code) code FROM items WHERE item_code IS NOT NULL AND item_code<>''")->fetchAll() as $existing) $availableCodes[(string)$existing['code']] = true;
            foreach ($rows as $r) $availableCodes[strtolower($r['item_code'])] = true;
            foreach ($rows as &$r) {
                $suggested = trim((string)$r['suggested_item_code']);
                if ($suggested !== '') {
                    if (!menu_transfer_machine_key($suggested)) $r['errors'][] = 'کد پیشنهاد مکمل نامعتبر';
                    elseif (strtolower($suggested) === strtolower($r['item_code'])) $r['errors'][] = 'آیتم نمی‌تواند مکمل خودش باشد';
                    elseif (!isset($availableCodes[strtolower($suggested)])) $r['errors'][] = 'پیشنهاد مکمل پیدا نشد';
                }
                $r['errors'] = array_values(array_unique($r['errors']));
            }
            unset($r);

            $_SESSION['menu_import_preview'] = [
                'token'=>random_token(18),'rows'=>$rows,'general_errors'=>$general,
                'file_sha256'=>hash_file('sha256',(string)$file['tmp_name']) ?: null,'created_at'=>time(),
            ];
            redirect('menu_transfer.php');
        }

        if ($action === 'confirm') {
            $preview = $_SESSION['menu_import_preview'] ?? null;
            if (!$preview || time() - (int)($preview['created_at'] ?? 0) > 1800 || !hash_equals((string)$preview['token'], (string)($_POST['preview_token'] ?? ''))) throw new RuntimeException('پیش‌نمایش منقضی شده؛ فایل را دوباره انتخاب کنید.');
            if (array_filter($preview['rows'], static fn($r): bool => (bool)$r['errors']) || $preview['general_errors']) throw new RuntimeException('اول خطاها را برطرف کنید.');

            $pdo = db();$pdo->beginTransaction();
            $menuIdByKey = [];
            foreach ($pdo->query('SELECT id,menu_key FROM menus ORDER BY id FOR UPDATE')->fetchAll() as $menu) $menuIdByKey[strtolower((string)$menu['menu_key'])] = (int)$menu['id'];

            $categoryFind = $pdo->prepare('SELECT id,image_path FROM categories WHERE category_key=? LIMIT 1 FOR UPDATE');
            $categoryInsert = $pdo->prepare('INSERT INTO categories(category_key,name,audience,sort_order,active) VALUES(?,?,?,?,?)');
            $categoryUpdate = $pdo->prepare('UPDATE categories SET name=?,audience=?,active=? WHERE id=?');
            $categoryNextSort = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0)+10 FROM categories');
            $menuCategoryInsert = $pdo->prepare('INSERT IGNORE INTO menu_categories(menu_id,category_id,sort_order) VALUES(?,?,?)');
            $menuCategoryNext = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0)+10 FROM menu_categories WHERE menu_id=?');

            $itemFind = $pdo->prepare('SELECT id,image_path FROM items WHERE item_code=? LIMIT 1 FOR UPDATE');
            $itemInsert = $pdo->prepare('INSERT INTO items(item_code,category_id,name,description,price,image_path,available,active,featured,staff_only,takeaway_allowed,preparation_station,schedule_start,schedule_end,schedule_days,daily_start,daily_end,sort_order) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $itemUpdate = $pdo->prepare('UPDATE items SET category_id=?,name=?,description=?,price=?,image_path=?,available=?,active=?,featured=?,staff_only=?,takeaway_allowed=?,preparation_station=?,schedule_start=?,schedule_end=?,schedule_days=?,daily_start=?,daily_end=?,sort_order=? WHERE id=?');
            $itemMenuDelete = $pdo->prepare('DELETE FROM menu_items WHERE item_id=?');
            $itemMenuInsert = $pdo->prepare('INSERT INTO menu_items(menu_id,item_id) VALUES(?,?)');

            $created = 0;$updated = 0;$ids = [];$categoryIds = [];
            foreach ($preview['rows'] as $r) {
                $categoryKey = (string)$r['category_key'];
                if (!isset($categoryIds[$categoryKey])) {
                    $categoryFind->execute([$categoryKey]);$categoryRow = $categoryFind->fetch();
                    if ($categoryRow) {
                        $categoryId = (int)$categoryRow['id'];
                        $categoryUpdate->execute([$r['category_name'],$r['category_audience'],$r['category_active'],$categoryId]);
                    } else {
                        $categoryNextSort->execute();$sort = (int)$categoryNextSort->fetchColumn();
                        $categoryInsert->execute([$categoryKey,$r['category_name'],$r['category_audience'],$sort,$r['category_active']]);
                        $categoryId = (int)$pdo->lastInsertId();
                    }
                    foreach ($r['category_menu_keys'] as $menuKey) {
                        $menuId = $menuIdByKey[$menuKey] ?? 0;
                        if (!$menuId) throw new RuntimeException('منوی مقصد هنگام اعمال فایل پیدا نشد.');
                        $menuCategoryNext->execute([$menuId]);
                        $menuCategoryInsert->execute([$menuId,$categoryId,(int)$menuCategoryNext->fetchColumn()]);
                    }
                    $categoryIds[$categoryKey] = $categoryId;
                }
                $categoryId = $categoryIds[$categoryKey];

                $itemFind->execute([$r['item_code']]);$old = $itemFind->fetch();
                $start = $r['schedule_start'] !== '' ? date('Y-m-d H:i:s', strtotime($r['schedule_start'])) : null;
                $end = $r['schedule_end'] !== '' ? date('Y-m-d H:i:s', strtotime($r['schedule_end'])) : null;
                if ($old) {
                    $image = $r['image_path'] !== '' ? $r['image_path'] : $old['image_path'];
                    $itemUpdate->execute([$categoryId,$r['name'],$r['description'],$r['price'],$image,$r['available'],$r['active'],$r['featured'],$r['staff_only'],$r['takeaway_allowed'],$r['preparation_station'],$start,$end,$r['schedule_days'] ?: null,$r['daily_start'] ?: null,$r['daily_end'] ?: null,$r['sort_order'],$old['id']]);
                    $id = (int)$old['id'];$updated++;
                } else {
                    $itemInsert->execute([$r['item_code'],$categoryId,$r['name'],$r['description'],$r['price'],$r['image_path'] ?: null,$r['available'],$r['active'],$r['featured'],$r['staff_only'],$r['takeaway_allowed'],$r['preparation_station'],$start,$end,$r['schedule_days'] ?: null,$r['daily_start'] ?: null,$r['daily_end'] ?: null,$r['sort_order']]);
                    $id = (int)$pdo->lastInsertId();$created++;
                }
                $ids[strtolower($r['item_code'])] = $id;
                $itemMenuDelete->execute([$id]);
                foreach ($r['menu_keys'] as $menuKey) {
                    $menuId = $menuIdByKey[$menuKey] ?? 0;
                    if (!$menuId) throw new RuntimeException('منوی آیتم هنگام اعمال فایل پیدا نشد.');
                    $itemMenuInsert->execute([$menuId,$id]);
                }
            }

            $tagFind = $pdo->prepare('SELECT id FROM tags WHERE slug=?');
            $tagInsert = $pdo->prepare('INSERT INTO item_tags(item_id,tag_id) VALUES(?,?)');
            $clearTags = $pdo->prepare('DELETE FROM item_tags WHERE item_id=?');
            $suggest = $pdo->prepare('UPDATE items SET suggested_item_id=? WHERE id=?');
            foreach ($preview['rows'] as $r) {
                $id = $ids[strtolower($r['item_code'])] ?? 0;
                if (!$id) throw new RuntimeException('شناسه آیتم در مرحله دوم پیدا نشد.');
                $clearTags->execute([$id]);
                foreach (array_filter(array_merge([$r['marketing_tag']], $r['attribute_tags'])) as $slug) {
                    $tagFind->execute([$slug]);$tagId = (int)$tagFind->fetchColumn();if ($tagId) $tagInsert->execute([$id,$tagId]);
                }
                $target = 0;
                if ($r['suggested_item_code'] !== '') {
                    $target = $ids[strtolower($r['suggested_item_code'])] ?? 0;
                    if (!$target) {$itemFind->execute([$r['suggested_item_code']]);$found=$itemFind->fetch();$target=(int)($found['id']??0);}
                }
                $suggest->execute([$target ?: null,$id]);
            }

            audit_log_write_strict($pdo,'menu.csv_import_applied','menu_item',null,[
                'created'=>$created,'updated'=>$updated,'row_count'=>count($preview['rows']),'file_sha256'=>$preview['file_sha256']??null,'format'=>'stable-category-menu-keys-v1',
            ],(int)(current_user()['id']??0));
            $pdo->commit();
            unset($_SESSION['menu_import_preview']);
            flash('success', fa_digits($created) . ' آیتم تازه و ' . fa_digits($updated) . ' آیتم به‌روز شد.');
            redirect('items.php');
        }

        throw new RuntimeException('عملیات ورود و خروجی معتبر نیست.');
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        flash('error', safe_business_error_message($e, 'فایل وارد نشد؛ ساختار فایل را بررسی کنید.'));
        redirect('menu_transfer.php');
    }
}

$preview = $_SESSION['menu_import_preview'] ?? null;
$rows = $preview['rows'] ?? [];
$errorCount = count(array_filter($rows, static fn($r): bool => (bool)$r['errors'])) + count($preview['general_errors'] ?? []);
panel_header('ورود و خروجی منو','items');
panel_subnav(['items'=>['items.php','مدیریت منو'],'order'=>['items.php?view=arrange','چیدمان'],'tags'=>['tags.php','برچسب‌ها'],'transfer'=>['menu_transfer.php','ورود و خروجی']], 'transfer', 'مدیریت آیتم‌های منو');
?>
<div class="page-grid">
  <section class="card"><div class="card-head"><h2>خروجی آیتم‌ها و جایگاه منو</h2></div><div class="card-body"><p class="muted">شناسه پایدار دسته و عضویت آیتم/دسته در منوها همراه CSV ذخیره می‌شود. تصاویر فایل جدا هستند و پشتیبان کامل سامانه جای این ابزار را نمی‌گیرد.</p><div class="actions"><a class="btn btn-primary" href="?action=export">دانلود CSV فعلی</a><a class="btn btn-light" href="?action=template">فایل نمونه</a></div></div></section>
  <section class="card"><div class="card-head"><h2>ورود فایل</h2></div><div class="card-body"><form method="post" enctype="multipart/form-data" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="preview"><div class="form-group full"><label>CSV UTF-8</label><input class="form-control" type="file" name="menu_file" accept=".csv,text/csv" required><small class="muted">پیش‌نمایش اجباری است. این ورود merge است و آیتم‌های غایب را حذف نمی‌کند.</small></div><div class="form-group full"><button class="btn btn-primary">دیدن پیش‌نمایش</button></div></form></div></section>
</div>
<?php if ($preview): ?>
<section class="card table-card" style="margin-top:18px"><div class="card-head"><div><h2>پیش‌نمایش</h2><small><?= fa_digits(count($rows)) ?> ردیف · <?= $errorCount ? fa_digits($errorCount).' خطا' : 'آماده ورود' ?></small></div><form method="post"><?= csrf_field() ?><button class="btn btn-sm btn-light" name="action" value="clear">پاک‌کردن</button></form></div>
<div class="data-table-wrap"><table class="data-table"><thead><tr><th>ردیف</th><th>کد</th><th>آیتم و دسته</th><th>منوها</th><th>محل آماده‌سازی</th><th>وضعیت</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= fa_digits($r['line']) ?></td><td><code><?= e($r['item_code']) ?></code></td><td><?= e($r['name']) ?><br><small><code dir="ltr"><?= e($r['category_key']) ?></code> · <?= e($r['category_name']) ?></small></td><td><small><?= e(implode('، ',$r['menu_keys'])) ?: 'بدون منو' ?></small></td><td><?= e(station_label($r['preparation_station'])) ?></td><td><?= $r['errors'] ? '<span class="text-danger">'.e(implode('، ',$r['errors'])).'</span>' : '<span class="text-success">آماده</span>' ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php if (!$errorCount): ?><div class="card-body"><form method="post"><?= csrf_field() ?><input type="hidden" name="preview_token" value="<?= e($preview['token']) ?>"><button class="btn btn-success" name="action" value="confirm">تأیید و اعمال</button></form></div><?php endif; ?>
</section>
<?php endif; ?>
<?php panel_footer(); ?>
