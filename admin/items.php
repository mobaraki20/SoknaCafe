<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_login(['admin']);
require dirname(__DIR__) . '/includes/panel_layout.php';

function admin_item_schedule_summary(array $item): string
{
    $hasSchedule = !empty($item['schedule_start']) || !empty($item['schedule_end']) || !empty($item['schedule_days']) || !empty($item['daily_start']);
    if (!$hasSchedule) return 'همیشه در دسترس منو';
    $parts = [];
    $dayLabels = [1=>'یکشنبه',2=>'دوشنبه',3=>'سه‌شنبه',4=>'چهارشنبه',5=>'پنجشنبه',6=>'جمعه',7=>'شنبه'];
    $days = array_values(array_filter(array_map('intval', explode(',', (string)($item['schedule_days'] ?? '')))));
    if ($days) $parts[] = implode('، ', array_map(static fn(int $day): string => $dayLabels[$day] ?? '', $days));
    if (!empty($item['daily_start']) && !empty($item['daily_end'])) {
        $parts[] = fa_digits(substr((string)$item['daily_start'], 0, 5)) . ' تا ' . fa_digits(substr((string)$item['daily_end'], 0, 5));
    }
    if (!empty($item['schedule_start']) || !empty($item['schedule_end'])) {
        $range = [];
        if (!empty($item['schedule_start'])) $range[] = 'از ' . format_jalali_date((string)$item['schedule_start'], false);
        if (!empty($item['schedule_end'])) $range[] = 'تا ' . format_jalali_date((string)$item['schedule_end'], false);
        $parts[] = implode(' ', $range);
    }
    return implode(' · ', array_filter($parts)) ?: 'زمان‌بندی‌شده';
}

function admin_item_payload(array $item): array
{
    return [
        'id' => (int)$item['id'],
        'name' => (string)$item['name'],
        'price' => (int)$item['price'],
        'category_id' => (int)$item['category_id'],
        'category_name' => (string)($item['category_name'] ?? ''),
        'preparation_station' => normalize_preparation_station((string)($item['preparation_station'] ?? 'other')),
        'station_label' => station_label((string)($item['preparation_station'] ?? 'other')),
        'available' => (int)$item['available'] === 1,
        'active' => (int)$item['active'] === 1,
        'featured' => (int)$item['featured'] === 1,
        'staff_only' => (int)($item['staff_only'] ?? 0) === 1,
        'takeaway_allowed' => (int)($item['takeaway_allowed'] ?? 1) === 1,
        'schedule_summary' => admin_item_schedule_summary($item),
        'schedule_now' => (int)($item['schedule_now'] ?? 1) === 1,
        'has_schedule' => !empty($item['schedule_start']) || !empty($item['schedule_end']) || !empty($item['schedule_days']) || !empty($item['daily_start']),
        'menu_ids' => array_values(array_filter(array_map('intval', explode(',', (string)($item['menu_ids_csv'] ?? ''))), static fn(int $id): bool => $id > 0)),
        'menu_names' => array_values(array_filter(explode('||', (string)($item['menu_names_csv'] ?? '')), static fn(string $name): bool => $name !== '')),
        'allowed_menu_ids' => array_values(array_filter(array_map('intval', explode(',', (string)($item['allowed_menu_ids_csv'] ?? ''))), static fn(int $id): bool => $id > 0)),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = str_contains(strtolower((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json') ? request_json() : $_POST;
    $isJson = request_expects_json();
    if (!csrf_valid($input['csrf_token'] ?? null)) {
        if ($isJson) json_response(['success'=>false,'message'=>'صفحه منقضی شده است؛ آن را تازه‌سازی کنید.'], 419);
        verify_csrf($input['csrf_token'] ?? null);
    }
    $action = (string)($input['action'] ?? '');
    $pdo = db();
    try {
        if ($action === 'reorder_menu_categories') {
            $menuId=(int)($input['menu_id']??0);
            $raw=json_decode((string)($input['ordered_ids']??'[]'),true);
            $ids=is_array($raw)?array_values(array_unique(array_filter(array_map('intval',$raw),static fn(int $id):bool=>$id>0))):[];
            if($menuId<1||!$ids)throw new RuntimeException('منو و ترتیب دسته‌بندی‌ها معتبر نیست.');
            $pdo->beginTransaction();
            $lock=$pdo->prepare('SELECT category_id FROM menu_categories WHERE menu_id=? ORDER BY sort_order,category_id FOR UPDATE');
            $lock->execute([$menuId]);$existing=array_map('intval',$lock->fetchAll(PDO::FETCH_COLUMN));
            if(count($ids)!==count($existing)||array_diff($ids,$existing)||array_diff($existing,$ids))throw new RuntimeException('فهرست دسته‌بندی‌های این منو تغییر کرده است؛ صفحه را تازه‌سازی کنید.');
            $update=$pdo->prepare('UPDATE menu_categories SET sort_order=? WHERE menu_id=? AND category_id=?');
            foreach($ids as $index=>$categoryId)$update->execute([($index+1)*10,$menuId,$categoryId]);
            if($existing!==$ids)audit_log_write_strict($pdo,'menu.category_order_changed','menu',$menuId,['before'=>$existing,'after'=>$ids],(int)(current_user()['id']??0));
            $pdo->commit();$message='ترتیب دسته‌بندی‌های این منو ذخیره شد.';
        } elseif ($action === 'reorder_category_items') {
            $categoryId=(int)($input['category_id']??0);
            $raw=json_decode((string)($input['ordered_ids']??'[]'),true);
            $ids=is_array($raw)?array_values(array_unique(array_filter(array_map('intval',$raw),static fn(int $id):bool=>$id>0))):[];
            if($categoryId<1||!$ids)throw new RuntimeException('دسته‌بندی و ترتیب آیتم‌ها معتبر نیست.');
            $pdo->beginTransaction();
            $lock=$pdo->prepare('SELECT id FROM items WHERE category_id=? ORDER BY sort_order,id FOR UPDATE');
            $lock->execute([$categoryId]);$existing=array_map('intval',$lock->fetchAll(PDO::FETCH_COLUMN));
            if(count($ids)!==count($existing)||array_diff($ids,$existing)||array_diff($existing,$ids))throw new RuntimeException('فهرست آیتم‌های این دسته تغییر کرده است؛ صفحه را تازه‌سازی کنید.');
            $update=$pdo->prepare('UPDATE items SET sort_order=? WHERE category_id=? AND id=?');
            foreach($ids as $index=>$itemId)$update->execute([($index+1)*10,$categoryId,$itemId]);
            if($existing!==$ids)audit_log_write_strict($pdo,'menu.item_order_changed','category',$categoryId,['before'=>$existing,'after'=>$ids],(int)(current_user()['id']??0));
            $pdo->commit();$message='ترتیب آیتم‌های این دسته ذخیره شد.';
        } elseif (in_array($action, ['availability','active'], true)) {
            $id = (int)($input['id'] ?? 0);
            $desiredRaw = (string)($input['desired_state'] ?? '');
            if ($id < 1 || !in_array($desiredRaw,['0','1'],true)) throw new RuntimeException('وضعیت درخواستی معتبر نیست.');
            $desired=(int)$desiredRaw;
            $column = $action === 'availability' ? 'available' : 'active';
            $pdo->beginTransaction();
            $lock=$pdo->prepare("SELECT id,name,price,available,active,featured FROM items WHERE id=? FOR UPDATE");
            $lock->execute([$id]);$before=$lock->fetch();
            if(!$before)throw new RuntimeException('آیتم پیدا نشد.');
            if((int)$before[$column]!==$desired){
                $pdo->prepare("UPDATE items SET {$column}=? WHERE id=?")->execute([$desired,$id]);
                $after=$before;$after[$column]=$desired;
                audit_log_write($action==='availability'?'menu.item_orderability_changed':'menu.item_publication_changed','menu_item',$id,[
                    'name'=>(string)$before['name'],'changes'=>menu_item_important_changes($before,$after)
                ],(int)(current_user()['id']??0));
            }
            $pdo->commit();
            $message = $action === 'availability' ? ($desired?'آیتم قابل سفارش شد.':'سفارش این آیتم متوقف شد.') : ($desired?'آیتم در منوی مهمان منتشر شد.':'آیتم به پیش‌نویس رفت.');
        } elseif ($action === 'quick_update') {
            $id = (int)($input['id'] ?? 0);
            $name = text_substr(trim((string)($input['name'] ?? '')), 0, 160);
            $price = parse_toman_amount_text((string)($input['price'] ?? ''));
            $categoryId = (int)($input['category_id'] ?? 0);
            $station = normalize_preparation_station((string)($input['preparation_station'] ?? 'other'));
            $available = !empty($input['available']) ? 1 : 0;
            $active = !empty($input['active']) ? 1 : 0;
            $featured = !empty($input['featured']) ? 1 : 0;
            $staffOnly = !empty($input['staff_only']) ? 1 : 0;
            $takeawayAllowed = !empty($input['takeaway_allowed']) ? 1 : 0;
            $requestedMenuIds = array_values(array_unique(array_filter(array_map('intval', (array)($input['menu_ids'] ?? [])), static fn(int $menuId): bool => $menuId > 0)));
            if ($id < 1 || $name === '' || $price === null || $categoryId < 1) throw new RuntimeException('نام، قیمت و دسته‌بندی معتبر لازم است.');
            $categoryCheck = $pdo->prepare('SELECT 1 FROM categories WHERE id=? LIMIT 1');
            $categoryCheck->execute([$categoryId]);
            if (!$categoryCheck->fetchColumn()) throw new RuntimeException('دسته‌بندی انتخاب‌شده معتبر نیست.');
            $pdo->beginTransaction();
            $lock=$pdo->prepare('SELECT id,name,price,available,active,featured,staff_only,takeaway_allowed,preparation_station FROM items WHERE id=? FOR UPDATE');$lock->execute([$id]);$before=$lock->fetch();
            if(!$before)throw new RuntimeException('آیتم پیدا نشد.');
            $pdo->prepare('UPDATE items SET name=?,price=?,category_id=?,preparation_station=?,available=?,active=?,featured=?,staff_only=?,takeaway_allowed=? WHERE id=?')
                ->execute([$name,$price,$categoryId,$station,$available,$active,$featured,$staffOnly,$takeawayAllowed,$id]);
            menu_catalog_set_item_memberships($pdo,$id,$categoryId,$requestedMenuIds);
            $after=['name'=>$name,'price'=>$price,'available'=>$available,'active'=>$active,'featured'=>$featured,'staff_only'=>$staffOnly,'takeaway_allowed'=>$takeawayAllowed,'preparation_station'=>$station];
            $importantChanges=menu_item_important_changes($before,$after);
            if($importantChanges)audit_log_write('menu.item_important_updated','menu_item',$id,['name'=>$name,'changes'=>$importantChanges],(int)(current_user()['id']??0));
            $pdo->commit();
            $message = 'تغییرات سریع آیتم ذخیره شد.';
        } elseif ($action === 'bulk_update') {
            $ids = array_values(array_unique(array_filter(array_map('intval', (array)($input['ids'] ?? [])), static fn(int $id): bool => $id > 0)));
            if (!$ids) throw new RuntimeException('حداقل یک آیتم انتخاب کنید.');
            if (count($ids) > 200) throw new RuntimeException('تعداد آیتم‌های انتخاب‌شده بیش از حد مجاز است.');
            $operation = (string)($input['operation'] ?? '');
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $pdo->beginTransaction();
            $lock=$pdo->prepare("SELECT id,category_id FROM items WHERE id IN ($marks) ORDER BY id FOR UPDATE");$lock->execute($ids);$locked=$lock->fetchAll();
            if(count($locked)!==count($ids))throw new RuntimeException('یکی از آیتم‌های انتخاب‌شده پیدا نشد.');
            $affected=0;$target=$input['target']??null;
            if (in_array($operation,['available_on','available_off','active_on','active_off'],true)) {
                $set=['available_on'=>'available=1','available_off'=>'available=0','active_on'=>'active=1','active_off'=>'active=0'][$operation];
                $stmt=$pdo->prepare("UPDATE items SET $set WHERE id IN ($marks)");$stmt->execute($ids);$affected=$stmt->rowCount();
            } elseif ($operation === 'category') {
                $categoryId=(int)$target;$check=$pdo->prepare('SELECT 1 FROM categories WHERE id=? LIMIT 1');$check->execute([$categoryId]);if(!$check->fetchColumn())throw new RuntimeException('دسته‌بندی مقصد معتبر نیست.');
                $stmt=$pdo->prepare("UPDATE items SET category_id=? WHERE id IN ($marks)");$stmt->execute([$categoryId,...$ids]);$affected=$stmt->rowCount();
                foreach($ids as $itemId)menu_catalog_sync_item_memberships_to_category($pdo,$itemId,$categoryId);
            } elseif ($operation === 'station') {
                $station=normalize_preparation_station((string)$target);$stmt=$pdo->prepare("UPDATE items SET preparation_station=? WHERE id IN ($marks)");$stmt->execute([$station,...$ids]);$affected=$stmt->rowCount();$target=$station;
            } elseif (in_array($operation,['menu_add','menu_remove'],true)) {
                $menuId=(int)$target;$m=$pdo->prepare('SELECT id FROM menus WHERE id=? LIMIT 1');$m->execute([$menuId]);if(!$m->fetchColumn())throw new RuntimeException('منوی مقصد معتبر نیست.');
                if($operation==='menu_add'){
                    $catMembership=$pdo->prepare('INSERT IGNORE INTO menu_categories(menu_id,category_id,sort_order) SELECT ?,c.id,COALESCE((SELECT MAX(x.sort_order)+10 FROM menu_categories x WHERE x.menu_id=?),10) FROM categories c JOIN items i ON i.category_id=c.id WHERE i.id=?');
                    $ins=$pdo->prepare('INSERT IGNORE INTO menu_items(menu_id,item_id) VALUES(?,?)');
                    foreach($locked as $row){$catMembership->execute([$menuId,$menuId,(int)$row['id']]);$ins->execute([$menuId,(int)$row['id']]);$affected+=$ins->rowCount();}
                }else{
                    $del=$pdo->prepare("DELETE FROM menu_items WHERE menu_id=? AND item_id IN ($marks)");$del->execute([$menuId,...$ids]);$affected=$del->rowCount();
                }
                $target=$menuId;
            } else throw new RuntimeException('عملیات گروهی معتبر نیست.');
            if($affected>0)audit_log_write_strict($pdo,'menu.items_bulk_updated','menu_item',null,[
                'operation'=>$operation,'affected_count'=>$affected,'item_ids'=>array_slice($ids,0,50),'target'=>$target
            ],(int)(current_user()['id']??0));
            $pdo->commit();
            $message = fa_digits($affected) . ' آیتم به‌روزرسانی شد.';
        } else {
            throw new RuntimeException('عملیات نامعتبر است.');
        }

        if ($isJson) {
            $id = (int)($input['id'] ?? 0);
            $payload = null;
            if ($id > 0) {
                $stmt = $pdo->prepare("SELECT i.*,c.name category_name,c.audience category_audience,(" . item_schedule_sql('i') . ") schedule_now,
                    (SELECT GROUP_CONCAT(mi.menu_id ORDER BY m.sort_order,m.id SEPARATOR ',') FROM menu_items mi JOIN menus m ON m.id=mi.menu_id WHERE mi.item_id=i.id) menu_ids_csv,
                    (SELECT GROUP_CONCAT(m.name ORDER BY m.sort_order,m.id SEPARATOR '||') FROM menu_items mi JOIN menus m ON m.id=mi.menu_id WHERE mi.item_id=i.id) menu_names_csv,
                    (SELECT GROUP_CONCAT(mc.menu_id ORDER BY m2.sort_order,m2.id SEPARATOR ',') FROM menu_categories mc JOIN menus m2 ON m2.id=mc.menu_id WHERE mc.category_id=i.category_id) allowed_menu_ids_csv
                    FROM items i JOIN categories c ON c.id=i.category_id WHERE i.id=?");
                $stmt->execute([$id]);
                $row = $stmt->fetch();
                if ($row) $payload = admin_item_payload($row);
            }
            json_response(['success'=>true,'message'=>$message,'item'=>$payload]);
        }
        flash('success', $message);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($isJson) json_response(['success'=>false,'message'=>safe_business_error_message($e, 'این تغییر انجام نشد.')], 422);
        flash('error', safe_business_error_message($e, 'این تغییر انجام نشد.'));
    }
    $return = safe_local_redirect_target($input['return_to'] ?? '', 'items.php');
    redirect($return ?: 'items.php');
}

$pdo=db();
$menus=$pdo->query("SELECT m.*,(SELECT COUNT(*) FROM menu_items mi WHERE mi.menu_id=m.id) item_count FROM menus m ORDER BY m.sort_order,m.id")->fetchAll();
$menuByKey=[];foreach($menus as $m)$menuByKey[(string)$m['menu_key']]=$m;
$menuKey=trim((string)($_GET['menu']??'all'));if($menuKey!=='all'&&!isset($menuByKey[$menuKey]))$menuKey='all';
$view=(string)($_GET['view']??'list');if(!in_array($view,['list','arrange'],true))$view='list';
if($view==='arrange'&&$menuKey==='all'&&$menus)$menuKey=(string)$menus[0]['menu_key'];
$categoryId = (int)($_GET['category'] ?? 0);
$search = trim((string)($_GET['search'] ?? ''));
$status = in_array((string)($_GET['status'] ?? 'all'), ['all','available','unavailable','published','draft','scheduled','featured','missing_image'], true) ? (string)$_GET['status'] : 'all';
$audience=in_array((string)($_GET['audience']??'all'),['all','guest','staff'],true)?(string)$_GET['audience']:'all';
$sort=in_array((string)($_GET['sort']??'menu'),['menu','active_first','inactive_first','unavailable_first','name'],true)?(string)$_GET['sort']:'menu';
$station = (string)($_GET['station'] ?? 'all');
if ($station !== 'all' && !array_key_exists($station, preparation_stations())) $station = 'all';
$where = [];$params = [];
if($menuKey!=='all'){$where[]='EXISTS(SELECT 1 FROM menu_items mi JOIN menus m ON m.id=mi.menu_id WHERE mi.item_id=i.id AND m.menu_key=?)';$params[]=$menuKey;}
if ($categoryId) { $where[] = 'i.category_id=?'; $params[] = $categoryId; }
if ($search !== '') {
    $tokens=persian_search_tokens($search);
    $normName="REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(i.name,'ي','ی'),'ى','ی'),'ك','ک'),'‌',' '),'-',' '),'–',' '),'—',' ')";
    $normCategory="REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(c.name,'ي','ی'),'ى','ی'),'ك','ک'),'‌',' '),'-',' '),'–',' '),'—',' ')";
    $normTag="REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(t.title,'ي','ی'),'ى','ی'),'ك','ک'),'‌',' '),'-',' '),'–',' '),'—',' ')";
    foreach($tokens as $token){
        $where[]="($normName LIKE ? OR i.item_code LIKE ? OR $normCategory LIKE ? OR EXISTS(SELECT 1 FROM item_tags its JOIN tags t ON t.id=its.tag_id WHERE its.item_id=i.id AND $normTag LIKE ?))";
        $term='%'.$token.'%';$params[]=$term;$params[]='%'.en_digits($token).'%';$params[]=$term;$params[]=$term;
    }
}
if ($station !== 'all') { $where[] = 'i.preparation_station=?'; $params[] = $station; }
if($audience==='guest')$where[]="c.audience='guest_staff' AND COALESCE(i.staff_only,0)=0";
elseif($audience==='staff')$where[]="(c.audience='staff_only' OR COALESCE(i.staff_only,0)=1)";
if ($status === 'available') $where[] = 'i.active=1 AND i.available=1';
elseif ($status === 'unavailable') $where[] = 'i.available=0';
elseif ($status === 'published') $where[] = 'i.active=1';
elseif ($status === 'draft') $where[] = 'i.active=0';
elseif ($status === 'scheduled') $where[] = '(i.schedule_start IS NOT NULL OR i.schedule_end IS NOT NULL OR i.schedule_days IS NOT NULL OR i.daily_start IS NOT NULL)';
elseif ($status === 'featured') $where[] = 'i.featured=1';
elseif ($status === 'missing_image') $where[] = '(i.image_path IS NULL OR i.image_path="")';
$baseOrder='c.sort_order,i.sort_order,i.id';
if($menuKey!=='all')$baseOrder="COALESCE((SELECT mc.sort_order FROM menu_categories mc JOIN menus om ON om.id=mc.menu_id WHERE mc.category_id=c.id AND om.menu_key=".$pdo->quote($menuKey)." LIMIT 1),c.sort_order),i.sort_order,i.id";
$orderSql=match($sort){'active_first'=>'i.active DESC,i.available DESC,'.$baseOrder,'inactive_first'=>'i.active ASC,'.$baseOrder,'unavailable_first'=>'i.available ASC,i.active DESC,'.$baseOrder,'name'=>'i.name COLLATE utf8mb4_unicode_ci,i.id',default=>$baseOrder};

$perPage = 50;$page = max(1, (int)($_GET['page'] ?? 1));
$countSql = 'SELECT COUNT(*) FROM items i JOIN categories c ON c.id=i.category_id' . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
$countStmt = $pdo->prepare($countSql);$countStmt->execute($params);$filteredTotal = (int)$countStmt->fetchColumn();
$totalPages=max(1,(int)ceil($filteredTotal/$perPage));$page=min($page,$totalPages);$offset=($page-1)*$perPage;
$sql="SELECT i.*,c.name category_name,c.audience category_audience,(".item_schedule_sql('i').") schedule_now,
(SELECT GROUP_CONCAT(t.title ORDER BY t.tag_type,t.sort_order SEPARATOR '، ') FROM item_tags it JOIN tags t ON t.id=it.tag_id WHERE it.item_id=i.id) tag_titles,
(SELECT GROUP_CONCAT(mi.menu_id ORDER BY m.sort_order,m.id SEPARATOR ',') FROM menu_items mi JOIN menus m ON m.id=mi.menu_id WHERE mi.item_id=i.id) menu_ids_csv,
(SELECT GROUP_CONCAT(m.name ORDER BY m.sort_order,m.id SEPARATOR '||') FROM menu_items mi JOIN menus m ON m.id=mi.menu_id WHERE mi.item_id=i.id) menu_names_csv,
(SELECT GROUP_CONCAT(mc.menu_id ORDER BY m2.sort_order,m2.id SEPARATOR ',') FROM menu_categories mc JOIN menus m2 ON m2.id=mc.menu_id WHERE mc.category_id=i.category_id) allowed_menu_ids_csv
FROM items i JOIN categories c ON c.id=i.category_id".($where?' WHERE '.implode(' AND ',$where):'')." ORDER BY $orderSql LIMIT $perPage OFFSET $offset";
$stmt=$pdo->prepare($sql);$stmt->execute($params);$items=$stmt->fetchAll();
$categories=$pdo->query('SELECT id,name,audience FROM categories WHERE active=1 ORDER BY sort_order,id')->fetchAll();
$arrangeCategories=[];$arrangeItems=[];$arrangeCategoryId=(int)($_GET['arrange_category']??0);$arrangeMenu=null;
if($view==='arrange'&&$menuKey!=='all'&&isset($menuByKey[$menuKey])){
    $arrangeMenu=$menuByKey[$menuKey];$arrangeMenuId=(int)$arrangeMenu['id'];
    $stmt=$pdo->prepare("SELECT c.id,c.name,c.audience,c.image_path,c.icon_key,mc.sort_order,(SELECT COUNT(*) FROM items i JOIN menu_items mi ON mi.item_id=i.id AND mi.menu_id=mc.menu_id WHERE i.category_id=c.id) menu_item_count FROM menu_categories mc JOIN categories c ON c.id=mc.category_id WHERE mc.menu_id=? ORDER BY mc.sort_order,c.id");
    $stmt->execute([$arrangeMenuId]);$arrangeCategories=$stmt->fetchAll();
    $allowedCategoryIds=array_map('intval',array_column($arrangeCategories,'id'));
    if(!$arrangeCategoryId||!in_array($arrangeCategoryId,$allowedCategoryIds,true))$arrangeCategoryId=(int)($allowedCategoryIds[0]??0);
    if($arrangeCategoryId>0){
        $stmt=$pdo->prepare('SELECT i.id,i.name,i.image_path,i.available,i.active,i.sort_order,EXISTS(SELECT 1 FROM menu_items mi WHERE mi.menu_id=? AND mi.item_id=i.id) in_menu FROM items i WHERE i.category_id=? ORDER BY i.sort_order,i.id');
        $stmt->execute([$arrangeMenuId,$arrangeCategoryId]);$arrangeItems=$stmt->fetchAll();
    }
}
$stats=$pdo->query("SELECT COUNT(*) total,SUM(available=0) unavailable_count,SUM(active=0) draft_count,SUM(active=1 AND available=1) orderable_count,SUM(staff_only=1) staff_only_count FROM items")->fetch()?:[];
$itemPayloads=[];foreach($items as $item)$itemPayloads[(int)$item['id']]=admin_item_payload($item);
$filtersActive=$menuKey!=='all'||$categoryId>0||$search!==''||$status!=='all'||$audience!=='all'||$station!=='all'||$sort!=='menu';
$itemsPageUrl=static function(int $targetPage):string{$query=$_GET;$query['page']=max(1,$targetPage);return 'items.php?'.http_build_query($query);};
$currentQuery=$_SERVER['REQUEST_URI']??'items.php';
panel_header('مدیریت منو', 'items');
panel_subnav(['items'=>['items.php','مدیریت منو'],'categories'=>['categories.php','دسته‌بندی‌ها'],'order'=>['items.php?view=arrange','چیدمان'],'tags'=>['tags.php','برچسب‌ها'],'transfer'=>['menu_transfer.php','ورود و خروجی']], $view==='arrange'?'order':'items', 'منو، دسته‌بندی و آیتم‌ها');
$menuStatusLabels=['active'=>'فعال','draft'=>'پیش‌نویس','inactive'=>'غیرفعال'];
$menuScheduleSummary=static function(array $menu):string{
    $start=substr((string)($menu['daily_start']??''),0,5);$end=substr((string)($menu['daily_end']??''),0,5);
    if($start!==''&&$end!=='')return fa_digits($start).' تا '.fa_digits($end);
    return 'تمام روز';
};
?>
<div class="panel-page-flow menu-manager-page-flow">
<section class="menu-manager-strip" aria-label="منوهای فروش">
  <div class="menu-manager-strip-head"><div><span class="dashboard-kicker">منوهای فروش</span><h2>کافه، صبحانه و ناهار</h2><p>فعال‌بودن منو و ساعت سرو مستقل از دسته‌بندی و ایستگاه آماده‌سازی است.</p></div><a class="btn btn-light btn-sm" href="menu_form.php"><?= ui_icon('plus') ?> افزودن منو</a></div>
  <div class="menu-manager-cards">
    <a class="menu-manager-card <?= $menuKey==='all'?'is-selected':'' ?>" href="items.php"><strong>همه آیتم‌ها</strong><small><?= fa_digits((int)($stats['total']??0)) ?> آیتم</small></a>
    <?php foreach($menus as $menu): $key=(string)$menu['menu_key'];$status=(string)$menu['status']; ?>
      <article class="menu-manager-card <?= $menuKey===$key?'is-selected':'' ?>">
        <a class="menu-manager-card-main" href="items.php?menu=<?= e(rawurlencode($key)) ?>"><span><strong><?= e((string)$menu['name']) ?></strong><span class="panel-status-badge <?= $status==='active'?'is-ok':'' ?>"><?= e($menuStatusLabels[$status]??'نامشخص') ?></span></span><small><?= fa_digits((int)$menu['item_count']) ?> آیتم · <?= e($menuScheduleSummary($menu)) ?></small></a>
        <a class="menu-manager-card-edit" href="menu_form.php?id=<?= (int)$menu['id'] ?>" aria-label="ویرایش <?= e((string)$menu['name']) ?>"><?= ui_icon('edit') ?></a>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<?php if($view==='arrange'): ?>
<section class="card reorder-toolbar">
  <div><span class="dashboard-kicker">چیدمان منو</span><h2>ترتیب نمایش <?= e((string)($arrangeMenu['name']??'')) ?></h2><p class="muted">ترتیب دسته‌ها برای هر منو مستقل است. ترتیب آیتم‌های داخل هر دسته بین منوها مشترک می‌ماند تا یک آیتم فقط یک جایگاه نگهداری داشته باشد.</p></div>
  <div class="reorder-toolbar-actions"><a class="btn btn-light" href="items.php?menu=<?= e(rawurlencode($menuKey)) ?>">بازگشت به مدیریت منو</a></div>
</section>
<div class="menu-arrange-grid">
  <section class="card">
    <div class="card-head"><div><h3>دسته‌بندی‌ها</h3><small>ترتیب دسته‌های همین منو</small></div></div>
    <?php if($arrangeCategories): ?>
    <form method="post" data-reorder-form><?= csrf_field() ?><input type="hidden" name="action" value="reorder_menu_categories"><input type="hidden" name="menu_id" value="<?= (int)$arrangeMenu['id'] ?>"><input type="hidden" name="return_to" value="<?= e($_SERVER['REQUEST_URI']??'items.php?view=arrange') ?>"><input type="hidden" name="ordered_ids" data-reorder-output>
      <div class="reorder-list" data-reorder-list>
      <?php foreach($arrangeCategories as $cat): ?>
        <article class="reorder-row <?= (int)$cat['id']===$arrangeCategoryId?'is-selected':'' ?>" draggable="true" data-reorder-id="<?= (int)$cat['id'] ?>">
          <button class="reorder-handle" data-reorder-handle type="button" aria-label="جابه‌جایی <?= e((string)$cat['name']) ?>"><?= ui_icon('menu') ?></button>
          <?php if($cat['image_path']): ?><img class="reorder-thumb" src="<?= e(asset((string)$cat['image_path'])) ?>" alt=""><?php else: ?><span class="reorder-thumb"><?= ui_icon(category_visual_icon($cat['icon_key']??null,(string)$cat['name'])) ?></span><?php endif; ?>
          <a class="reorder-copy" href="items.php?view=arrange&amp;menu=<?= e(rawurlencode($menuKey)) ?>&amp;arrange_category=<?= (int)$cat['id'] ?>"><strong><?= e((string)$cat['name']) ?></strong><small><?= fa_digits((int)$cat['menu_item_count']) ?> آیتم در این منو<?= ($cat['audience']??'')==='staff_only'?' · فقط کارکنان':'' ?></small></a>
          <div class="reorder-actions"><button class="btn btn-sm btn-light" type="button" data-reorder-up aria-label="انتقال به بالا">↑</button><button class="btn btn-sm btn-light" type="button" data-reorder-down aria-label="انتقال به پایین">↓</button></div>
        </article>
      <?php endforeach; ?>
      </div><div class="reorder-save"><button class="btn btn-primary" type="submit">ذخیره ترتیب دسته‌ها</button></div>
    </form>
    <?php else: ?><div class="empty-state">برای این منو هنوز دسته‌ای تعریف نشده است.</div><?php endif; ?>
  </section>
  <section class="card">
    <div class="card-head"><div><h3>آیتم‌های دسته</h3><small><?= $arrangeCategoryId?e((string)(array_values(array_filter($arrangeCategories,static fn(array $c):bool=>(int)$c['id']===$arrangeCategoryId))[0]['name']??'')):'ابتدا یک دسته انتخاب کنید' ?></small></div></div>
    <?php if($arrangeItems): ?>
    <form method="post" data-reorder-form><?= csrf_field() ?><input type="hidden" name="action" value="reorder_category_items"><input type="hidden" name="category_id" value="<?= $arrangeCategoryId ?>"><input type="hidden" name="return_to" value="<?= e($_SERVER['REQUEST_URI']??'items.php?view=arrange') ?>"><input type="hidden" name="ordered_ids" data-reorder-output>
      <div class="reorder-list" data-reorder-list>
      <?php foreach($arrangeItems as $item): ?>
        <article class="reorder-row" draggable="true" data-reorder-id="<?= (int)$item['id'] ?>">
          <button class="reorder-handle" data-reorder-handle type="button" aria-label="جابه‌جایی <?= e((string)$item['name']) ?>"><?= ui_icon('menu') ?></button>
          <?php if($item['image_path']): ?><img class="reorder-thumb" src="<?= e(asset((string)$item['image_path'])) ?>" alt=""><?php else: ?><span class="reorder-thumb"><?= ui_icon('coffee') ?></span><?php endif; ?>
          <div class="reorder-copy"><strong><?= e((string)$item['name']) ?></strong><small><?= (int)$item['in_menu']===1?'در این منو':'خارج از این منو' ?><?= (int)$item['active']===1?'':' · پیش‌نویس' ?><?= (int)$item['available']===1?'':' · غیرقابل سفارش' ?></small></div>
          <div class="reorder-actions"><button class="btn btn-sm btn-light" type="button" data-reorder-up aria-label="انتقال به بالا">↑</button><button class="btn btn-sm btn-light" type="button" data-reorder-down aria-label="انتقال به پایین">↓</button></div>
        </article>
      <?php endforeach; ?>
      </div><div class="reorder-save"><button class="btn btn-primary" type="submit">ذخیره ترتیب آیتم‌ها</button></div>
    </form>
    <?php elseif($arrangeCategoryId): ?><div class="empty-state">در این دسته آیتمی وجود ندارد.</div><?php else: ?><div class="empty-state">برای شروع یک دسته را انتخاب کنید.</div><?php endif; ?>
  </section>
</div>
</div>
<?php panel_footer('<script defer src="'.e(asset('assets/js/reorder-list.js')).'"></script>'); return; endif; ?>

<section class="items-overview" aria-label="خلاصه مدیریت منو">
  <div class="items-overview-copy"><span class="dashboard-kicker">مدیریت روزمره منو</span><h2>آیتم موردنظر را پیدا کنید و همان‌جا تغییر دهید</h2><p>جست‌وجو، فیلتر و عملیات گروهی روی همان منبعی اعمال می‌شود که منوی مهمان و سفارش کارکنان مصرف می‌کنند.</p></div>
  <div class="items-overview-stats">
    <span><small>کل آیتم‌ها</small><strong><?= fa_digits((int)($stats['total'] ?? 0)) ?></strong></span>
    <span><small>قابل سفارش</small><strong><?= fa_digits((int)($stats['orderable_count'] ?? 0)) ?></strong></span>
    <span class="is-warning"><small>غیرقابل سفارش</small><strong><?= fa_digits((int)($stats['unavailable_count'] ?? 0)) ?></strong></span>
    <span><small>پیش‌نویس</small><strong><?= fa_digits((int)($stats['draft_count'] ?? 0)) ?></strong></span>
    <span><small>فقط کارکنان</small><strong><?= fa_digits((int)($stats['staff_only_count'] ?? 0)) ?></strong></span>
  </div>
</section>

<div class="items-primary-actions">
  <a class="btn btn-primary" href="item_form.php"><?= ui_icon('plus') ?> افزودن آیتم</a>
  <a class="btn btn-light" href="category_form.php"><?= ui_icon('plus') ?> افزودن دسته‌بندی</a>
  <a class="btn btn-light" target="_blank" href="<?= e(asset('menu/')) ?>"><?= ui_icon('eye') ?> مشاهده منوی مهمان</a>
</div>

<form method="get" class="item-filter-bar" id="itemFilterForm">
  <div class="item-filter-primary">
    <label class="item-search-field"><span>جست‌وجو</span><input class="form-control" type="search" inputmode="search" enterkeyhint="search" name="search" value="<?= e($search) ?>" placeholder="نام، کد، دسته یا برچسب" autocomplete="off"></label>
    <label><span>منو</span><select class="form-control" name="menu" data-choice-mode="compact"><option value="all">همه منوها</option><?php foreach($menus as $menu): ?><option value="<?= e((string)$menu['menu_key']) ?>" <?= $menuKey===(string)$menu['menu_key']?'selected':'' ?>><?= e((string)$menu['name']) ?></option><?php endforeach; ?></select></label>
    <label><span>وضعیت</span><select class="form-control" name="status" data-choice-mode="compact"><option value="all">همه وضعیت‌ها</option><option value="available" <?= $status==='available'?'selected':'' ?>>فعال و قابل سفارش</option><option value="unavailable" <?= $status==='unavailable'?'selected':'' ?>>غیرقابل سفارش</option><option value="published" <?= $status==='published'?'selected':'' ?>>منتشرشده</option><option value="draft" <?= $status==='draft'?'selected':'' ?>>پیش‌نویس</option><option value="scheduled" <?= $status==='scheduled'?'selected':'' ?>>زمان‌بندی‌شده</option><option value="featured" <?= $status==='featured'?'selected':'' ?>>پیشنهاد کافه</option><option value="missing_image" <?= $status==='missing_image'?'selected':'' ?>>بدون تصویر</option></select></label>
    <button class="btn btn-primary item-search-submit" type="submit"><?= ui_icon('search') ?> جست‌وجو</button>
  </div>
  <details class="item-filter-more" <?= ($categoryId>0||$audience!=='all'||$station!=='all'||$sort!=='menu')?'open':'' ?>><summary><?= ui_icon('adjust') ?><span>فیلترهای بیشتر</span><?php if($categoryId>0||$audience!=='all'||$station!=='all'||$sort!=='menu'): ?><b>فعال</b><?php endif; ?></summary><div class="item-filter-more-grid">
    <label><span>دسته‌بندی</span><select class="form-control" name="category" data-choice-mode="adaptive" data-choice-search="true"><option value="0">همه دسته‌ها</option><?php foreach($categories as $cat): ?><option value="<?= (int)$cat['id'] ?>" <?= $categoryId === (int)$cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?><?= ($cat['audience']??'')==='staff_only'?' · داخلی':'' ?></option><?php endforeach; ?></select></label>
    <label><span>مخاطب</span><select class="form-control" name="audience" data-choice-mode="compact"><option value="all">همه</option><option value="guest" <?= $audience==='guest'?'selected':'' ?>>مهمان و کارکنان</option><option value="staff" <?= $audience==='staff'?'selected':'' ?>>فقط کارکنان</option></select></label>
    <label><span>محل آماده‌سازی</span><select class="form-control" name="station" data-choice-mode="compact"><option value="all">همه بخش‌ها</option><?php foreach(preparation_stations() as $key=>$label): ?><option value="<?= e($key) ?>" <?= $station===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
    <label><span>مرتب‌سازی</span><select class="form-control" name="sort" data-choice-mode="compact"><option value="menu" <?= $sort==='menu'?'selected':'' ?>>ترتیب منو</option><option value="active_first" <?= $sort==='active_first'?'selected':'' ?>>فعال‌ها اول</option><option value="inactive_first" <?= $sort==='inactive_first'?'selected':'' ?>>غیرفعال‌ها اول</option><option value="unavailable_first" <?= $sort==='unavailable_first'?'selected':'' ?>>ناموجودها اول</option><option value="name" <?= $sort==='name'?'selected':'' ?>>نام آیتم</option></select></label>
  </div></details>
  <?php if($filtersActive): ?><div class="item-filter-reset"><span><?= fa_digits($filteredTotal) ?> نتیجه</span><a href="items.php">پاک‌کردن همه فیلترها</a></div><?php endif; ?>
</form>

<div class="item-selection-bar hidden" id="itemSelectionBar" role="region" aria-label="عملیات گروهی">
  <strong><span id="selectedItemCount">۰</span> آیتم انتخاب شده</strong>
  <select class="form-control" id="bulkItemOperation" data-choice-mode="compact" aria-label="عملیات گروهی">
    <option value="">انتخاب عملیات…</option><option value="available_on">قابل سفارش کن</option><option value="available_off">توقف سفارش</option><option value="active_on">منتشر کن</option><option value="active_off">به پیش‌نویس ببر</option><option value="category">انتقال به دسته‌بندی</option><option value="station">تغییر محل آماده‌سازی</option><option value="menu_add">افزودن به منو</option><option value="menu_remove">حذف از منو</option>
  </select>
  <select class="form-control hidden" id="bulkCategoryTarget" data-choice-mode="adaptive" data-choice-search="true" aria-label="دسته‌بندی مقصد"><option value="">انتخاب دسته‌بندی…</option><?php foreach($categories as $cat): ?><option value="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></option><?php endforeach; ?></select>
  <select class="form-control hidden" id="bulkStationTarget" data-choice-mode="compact" aria-label="محل آماده‌سازی مقصد"><option value="">انتخاب بخش…</option><?php foreach(preparation_stations() as $key=>$label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?></select>
  <select class="form-control hidden" id="bulkMenuTarget" data-choice-mode="compact" aria-label="منوی مقصد"><option value="">انتخاب منو…</option><?php foreach($menus as $menu): ?><option value="<?= (int)$menu['id'] ?>"><?= e((string)$menu['name']) ?></option><?php endforeach; ?></select>
  <button class="btn btn-primary" id="applyBulkItemAction" type="button">اعمال</button>
  <button class="btn btn-light" id="clearItemSelection" type="button">لغو انتخاب</button>
</div>

<section class="card items-management-card">
  <div class="items-list-head"><span><input type="checkbox" id="selectAllItems" aria-label="انتخاب همه آیتم‌های نمای فعلی"></span><span>آیتم</span><span>دسته و آماده‌سازی</span><span>قیمت و زمان‌بندی</span><span>وضعیت</span><span>عملیات</span></div>
  <div class="items-management-list" id="itemsManagementList">
    <?php foreach($items as $item): $payload=$itemPayloads[(int)$item['id']]; ?>
    <article class="item-management-row" data-item-row="<?= (int)$item['id'] ?>">
      <label class="item-row-select"><input type="checkbox" value="<?= (int)$item['id'] ?>" data-item-select aria-label="انتخاب <?= e($item['name']) ?>"></label>
      <div class="item-admin-identity">
        <div class="item-admin-thumb"><?php if($item['image_path']): ?><img src="<?= e(asset($item['image_path'])) ?>" alt=""><?php else: ?><?= ui_icon('coffee') ?><?php endif; ?></div>
        <div><strong data-item-name><?= e($item['name']) ?></strong><small><?= e((string)($item['item_code'] ?: 'بدون کد')) ?></small><div class="item-admin-badges"><?php if($item['featured']): ?><span class="badge badge-new" data-featured-badge>پیشنهاد کافه</span><?php else: ?><span class="badge badge-new hidden" data-featured-badge>پیشنهاد کافه</span><?php endif; ?><?php if($payload['has_schedule']): ?><span class="badge">زمان‌بندی‌شده</span><?php endif; ?><?php if(($item['category_audience']??'')==='staff_only'||(int)($item['staff_only']??0)===1): ?><span class="badge">فقط کارکنان</span><?php endif; ?><?php foreach($payload['menu_names'] as $menuName): ?><span class="badge badge-menu" data-item-menu-badge><?= e($menuName) ?></span><?php endforeach; ?><?php if(!$payload['menu_names']): ?><span class="badge badge-muted" data-item-menu-empty>بدون منو</span><?php endif; ?></div></div>
      </div>
      <div class="item-admin-context"><strong data-item-category><?= e($item['category_name']) ?></strong><small data-item-station><?= e($payload['station_label']) ?></small><?php if($item['tag_titles']): ?><small class="muted"><?= e($item['tag_titles']) ?></small><?php endif; ?></div>
      <div class="item-admin-commercial"><strong data-item-price><?= e(toman((int)$item['price'])) ?></strong><small><?= e($payload['schedule_summary']) ?></small><?php if($payload['has_schedule']): ?><span class="schedule-state <?= $payload['schedule_now']?'is-live':'is-paused' ?>"><?= $payload['schedule_now']?'اکنون فعال':'اکنون خارج از زمان نمایش' ?></span><?php endif; ?></div>
      <div class="item-admin-state">
        <form method="post" class="item-state-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><input type="hidden" name="return_to" value="<?= e($currentQuery) ?>"><input type="hidden" name="desired_state" value="<?= $item['available']?'0':'1' ?>"><button class="state-toggle <?= $item['available']?'is-on':'is-off' ?>" name="action" value="availability" data-item-action="availability" data-item-id="<?= (int)$item['id'] ?>" aria-pressed="<?= $item['available']?'true':'false' ?>"><span></span><b><?= $item['available']?'قابل سفارش':'غیرقابل سفارش' ?></b></button></form>
        <form method="post" class="item-state-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><input type="hidden" name="return_to" value="<?= e($currentQuery) ?>"><input type="hidden" name="desired_state" value="<?= $item['active']?'0':'1' ?>"><button class="state-toggle <?= $item['active']?'is-on':'is-off' ?>" name="action" value="active" data-item-action="active" data-item-id="<?= (int)$item['id'] ?>" aria-pressed="<?= $item['active']?'true':'false' ?>"><span></span><b><?= $item['active']?'منتشرشده':'پیش‌نویس' ?></b></button></form>
      </div>
      <div class="item-admin-actions"><button class="btn btn-sm btn-light" type="button" data-quick-edit-item="<?= (int)$item['id'] ?>"><?= ui_icon('edit') ?> ویرایش سریع</button><div class="row-action-menu" data-action-menu><button type="button" class="btn btn-sm btn-light" data-action-menu-trigger aria-label="عملیات بیشتر <?= e($item['name']) ?>" aria-haspopup="menu" aria-expanded="false"><?= ui_icon('more') ?></button><div class="row-action-popover" data-action-menu-popover role="menu"><a role="menuitem" href="item_form.php?id=<?= (int)$item['id'] ?>"><?= ui_icon('settings') ?> ویرایش کامل</a><a role="menuitem" href="item_form.php?copy_from=<?= (int)$item['id'] ?>"><?= ui_icon('copy') ?> ساخت آیتم مشابه</a></div></div></div>
    </article>
    <?php endforeach; ?>
    <?php if(!$items): ?><div class="empty-state"><strong>آیتمی با این فیلتر پیدا نشد.</strong><p>فیلترها را پاک کنید یا یک آیتم تازه بسازید.</p><a class="btn btn-primary" href="item_form.php"><?= ui_icon('plus') ?> افزودن آیتم</a></div><?php endif; ?>
  </div>
</section>
<?php if($totalPages>1): ?><nav class="panel-pagination items-pagination" aria-label="صفحه‌بندی آیتم‌های منو"><a class="btn btn-light btn-sm" href="<?= e($itemsPageUrl($page-1)) ?>" <?= $page<=1?'aria-disabled="true" tabindex="-1"':'' ?>>قبلی</a><span>صفحه <?= fa_digits($page) ?> از <?= fa_digits($totalPages) ?> · نمایش <?= fa_digits(min($filteredTotal,$offset+1)) ?> تا <?= fa_digits(min($filteredTotal,$offset+count($items))) ?> از <?= fa_digits($filteredTotal) ?></span><a class="btn btn-light btn-sm" href="<?= e($itemsPageUrl($page+1)) ?>" <?= $page>=$totalPages?'aria-disabled="true" tabindex="-1"':'' ?>>بعدی</a></nav><?php endif; ?>
</div>

<div class="item-editor-backdrop hidden" id="itemEditorBackdrop"></div>
<aside class="item-editor-drawer hidden" id="itemEditorDrawer" role="dialog" aria-modal="false" aria-labelledby="itemEditorTitle">
  <form id="itemQuickEditForm">
    <header data-item-editor-swipe-handle><div><span>ویرایش سریع</span><h2 id="itemEditorTitle">آیتم منو</h2></div><button class="icon-btn" type="button" data-close-item-editor aria-label="بستن"><?= ui_icon('close') ?></button></header>
    <div class="item-editor-body">
      <input type="hidden" name="id" id="quickItemId">
      <label><span>نام آیتم</span><input class="form-control" name="name" id="quickItemName" required></label>
      <label><span>قیمت، تومان</span><input class="form-control" inputmode="numeric" enterkeyhint="done" name="price" id="quickItemPrice" data-money-input required></label>
      <label><span>دسته‌بندی</span><select class="form-control" name="category_id" id="quickItemCategory" data-choice-mode="embedded"><?php foreach($categories as $cat): ?><option value="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></option><?php endforeach; ?></select></label>
      <fieldset class="quick-item-menus"><legend>نمایش در منوها</legend><div><?php foreach($menus as $menu): ?><label><span><?= e((string)$menu['name']) ?></span><input type="checkbox" value="<?= (int)$menu['id'] ?>" data-quick-item-menu></label><?php endforeach; ?></div><small>دسته‌بندی مشخص می‌کند کدام منوها مجازند؛ عضویت خود آیتم را اینجا انتخاب کنید.</small></fieldset>
      <label><span>محل آماده‌سازی</span><select class="form-control" name="preparation_station" id="quickItemStation" data-choice-mode="embedded"><?php foreach(preparation_stations() as $key=>$label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
      <div class="quick-item-flags"><label><span>قابل سفارش</span><input type="checkbox" name="available" id="quickItemAvailable"></label><label><span>منتشرشده</span><input type="checkbox" name="active" id="quickItemActive"></label><label><span>پیشنهاد کافه</span><input type="checkbox" name="featured" id="quickItemFeatured"></label><label><span>فقط کارکنان</span><input type="checkbox" name="staff_only" id="quickItemStaffOnly"></label><label><span>امکان بیرون‌بر</span><input type="checkbox" name="takeaway_allowed" id="quickItemTakeaway" checked></label></div>
      <div class="quick-item-schedule" id="quickItemScheduleWrap" hidden><span>زمان‌بندی</span><strong id="quickItemSchedule"></strong><small>تغییر از ویرایش کامل</small></div>
    </div>
    <footer><button class="btn btn-primary" type="submit">ذخیره تغییرات</button><a class="btn btn-light" id="quickItemFullEdit" href="#">ویرایش کامل</a></footer>
  </form>
</aside>

<script>
window.SOKNA_ITEMS=<?= json_script($itemPayloads) ?>;
window.SOKNA_ITEMS_ENDPOINT=<?= json_script(asset('admin/items.php')) ?>;
window.SOKNA_ITEMS_CSRF=<?= json_script(csrf_token()) ?>;
window.SOKNA_ITEMS_FILTERED=<?= $filtersActive?'true':'false' ?>;
window.SOKNA_CATEGORY_MENU_IDS=<?= json_script(array_reduce($categories,static function(array $carry,array $cat) use($pdo):array{$carry[(int)$cat['id']]=menu_catalog_allowed_menu_ids_for_category($pdo,(int)$cat['id']);return $carry;},[])) ?>;
</script>
<?php panel_footer('<script defer src="'.e(asset('assets/js/items-management.js')).'"></script>'); ?>
