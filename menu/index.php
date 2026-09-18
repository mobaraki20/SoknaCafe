<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/maintenance.php';
if (maintenance_is_active()) {
    http_response_code(503);
    header('Retry-After: 120');
    header('Cache-Control: no-store, max-age=0');
    ?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>نگهداری سامانه</title><?= favicon_head_tags() ?><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f7f3ec;color:#2c2723;font-family:Tahoma,Arial,sans-serif}.box{max-width:520px;margin:20px;padding:28px;border:1px solid #ded5cc;border-radius:20px;background:#fff;text-align:center;box-shadow:0 12px 35px #00000010}.box span{font-size:40px}.box p{line-height:2;color:#6d625b}.rescue{display:inline-block;margin-top:8px;padding:9px 14px;border:1px solid #d4cbc1;border-radius:12px;color:#365b4c;text-decoration:none;font-size:13px}</style></head><body><main class="box"><span><?= ui_icon('coffee') ?></span><h1>چند لحظه در حال نگهداری هستیم</h1><p><?= e((string)(maintenance_state()['message'] ?? 'اطلاعات سامانه با دقت در حال بررسی و بازیابی است.')) ?></p><p>این صفحه را کمی بعد دوباره باز کنید.</p><a class="rescue" href="<?= e(asset('admin/update/')) ?>">مرکز به‌روزرسانی و بازیابی</a></main></body></html><?php
    exit;
}
$tableToken=trim((string)($_GET['table']??''));$table=null;$session=null;
if($tableToken!==''){$stmt=db()->prepare('SELECT id,name,code,access_token,zone_label FROM cafe_tables WHERE access_token=? AND active=1 LIMIT 1');$stmt->execute([$tableToken]);$table=$stmt->fetch()?:null;if(!$table){http_response_code(404);header('Cache-Control: no-store, max-age=0');?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><title>QR نامعتبر | Sokna</title><?= favicon_head_tags() ?><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f7f3ec;color:#262722;font-family:Tahoma,Arial,sans-serif}.box{width:min(520px,calc(100% - 32px));padding:26px;border:1px solid #e5dfd6;border-radius:22px;background:#fff;text-align:center;box-shadow:0 16px 44px #0001}.box p{color:#6f746f;line-height:2}</style></head><body><main class="box"><h1>این QR معتبر نیست</h1><p>این QR ممکن است قدیمی یا مربوط به میزی غیرفعال باشد. QR موجود روی میز را دوباره اسکن کنید یا از همکاران کافه کمک بگیرید.</p></main></body></html><?php exit;}}
if($table&&table_sessions_enabled())$session=active_table_session((int)$table['id']);
$isPublic=$table===null;
$requestedMenuKey=trim((string)($_GET['menu']??''));
$catalog=menu_catalog_snapshot(db(),$isPublic?'guest_public':'guest_table',$requestedMenuKey,false);
$availableMenus=$catalog['menus'];$selectedMenu=$catalog['selected_menu'];$flatItems=$catalog['items'];
$orderAcceptance=order_acceptance_states();
$tagsByItem=item_tags_for(array_column($flatItems,'id'));
$menuByCategory=[];
foreach($catalog['categories'] as $category){$category['items']=[];$menuByCategory[(int)$category['id']]=$category;}
foreach($flatItems as &$item){
    $item['tags']=$tagsByItem[(int)$item['id']]??[];
    $description=trim((string)($item['description']??''));
    $item['display_description']=text_lower($description,'UTF-8')===text_lower(trim((string)$item['name']),'UTF-8')?'':$description;
    $categoryId=(int)$item['category_id'];
    if(isset($menuByCategory[$categoryId]))$menuByCategory[$categoryId]['items'][]=$item;
}
unset($item);
$menu=array_values($menuByCategory);
$clientItems=array_map(static fn(array $item):array=>[
    'id'=>(int)$item['id'],
    'name'=>(string)$item['name'],
    'description'=>(string)($item['display_description']??$item['description']??''),
    'category'=>(string)($item['category_name']??''),
    'image'=>$item['image_path'] ? asset((string)$item['image_path']) : '',
    'search_text'=>(string)$item['name'].' '.(string)($item['category_name']??'').' '.(string)($item['display_description']??'').' '.implode(' ',array_column($item['tags']??[],'title')),
    'price'=>(int)$item['price'],
    'available'=>(int)$item['available'],
    'takeaway_allowed'=>(int)($item['takeaway_allowed']??1),
    'order_available'=>$isPublic?0:(((int)$item['available']===1 && order_acceptance_blocked_scope_for_station((string)$item['preparation_station'])===null)?1:0),
    'blocked_scope'=>$isPublic?null:order_acceptance_blocked_scope_for_station((string)$item['preparation_station']),
    'unavailable_message'=>$isPublic?'':((($blocked=order_acceptance_blocked_scope_for_station((string)$item['preparation_station']))!==null)?order_acceptance_message($blocked):''),
    'preparation_station'=>$isPublic?'':normalize_preparation_station((string)$item['preparation_station']),
    'preparation_area'=>$isPublic?'':preparation_area_for_station((string)$item['preparation_station']),
    'station_busy'=>$isPublic?0:(station_is_busy((string)$item['preparation_station'])?1:0),
    'suggested_item_id'=>$item['suggested_item_id']!==null?(int)$item['suggested_item_id']:null,
    'tags'=>array_map(static fn(array $tag):array=>['title'=>(string)$tag['title'],'color_key'=>(string)$tag['color_key'],'icon'=>(string)($tag['icon']??'')],$item['tags']??[]),
],$flatItems);
$marketingEnabled=sokna_module_enabled('marketing');
$events=[];if($marketingEnabled&&setting_bool('events_enabled',true)){ $eventRows=db()->query("SELECT * FROM events WHERE active=1 ORDER BY featured DESC,starts_at,sort_order,id LIMIT 100")->fetchAll(); $events=array_slice(array_values(array_filter($eventRows,static fn(array $event):bool=>in_array(event_lifecycle_status($event),['upcoming','live'],true))),0,30); }
$campaign=$marketingEnabled?active_campaign():null;$social=setting_bool('social_footer_enabled',true)?social_links():[];$accommodationUrl=setting_bool('accommodation_enabled',true)?safe_external_url(setting('accommodation_site_url')):'';
$activeWaiterCall=null;if($table){$callStmt=db()->prepare("SELECT public_code,status FROM waiter_calls WHERE table_id=? AND status IN('new','accepted') ORDER BY id DESC LIMIT 1");$callStmt->execute([$table['id']]);$activeWaiterCall=$callStmt->fetch()?:null;}
$orderingEnabled=(bool)$orderAcceptance['cafe'];$sessionsEnabled=table_sessions_enabled();$canOrder=$table&&$orderingEnabled;$publicWaiterEnabled=$table===null&&waiter_call_allowed(true);$publicWaiterTables=$publicWaiterEnabled?db()->query("SELECT id,name,code,table_number,sort_order FROM cafe_tables WHERE active=1 ORDER BY table_number,sort_order,id LIMIT 100")->fetchAll():[];$waiterEnabled=($table&&(waiter_call_allowed(false)||$activeWaiterCall))||($publicWaiterEnabled&&!empty($publicWaiterTables));$eventsEnabled=$marketingEnabled&&setting_bool('events_enabled',true)&&!empty($events);
$logoPath=setting('logo_path');$primary=valid_hex_color(setting('primary_color','#365b4c'),'#365b4c');$accent=valid_hex_color(setting('accent_color','#b85c38'),'#b85c38');$background=valid_hex_color(setting('background_color','#f7f3ec'),'#f7f3ec');$onPrimary=contrast_text_color($primary);$onAccent=contrast_text_color($accent);$theme=menu_theme();$font=menu_font();$density=menu_density();$layout=menu_layout();$description=setting('seo_description','منوی کافه و رویدادهای سکنا');
$tableBadge=$table?trim((string)preg_replace('/^میز\s*/u','',(string)$table['name'])):'—';$showTableUi=$table!==null;$showOrderUi=$canOrder;
$searchPresets=[];foreach($menu as $category){if(count($searchPresets)>=4)break;$searchPresets[]=(string)$category['name'];}foreach($flatItems as $item){if(count($searchPresets)>=6)break;if((int)$item['featured']===1&&!in_array((string)$item['name'],$searchPresets,true))$searchPresets[]=(string)$item['name'];}
$featuredItems=array_values(array_filter($flatItems,static fn(array $item):bool=>(int)$item['featured']===1&&(int)$item['available']===1));
if(!$featuredItems)$featuredItems=array_values(array_filter($flatItems,static fn(array $item):bool=>(int)$item['available']===1));
$featuredItems=array_slice($featuredItems,0,5);

define('SOKNA_GUEST_VIEW_READY', true);
require dirname(__DIR__) . '/includes/guest_menu_view.php';
