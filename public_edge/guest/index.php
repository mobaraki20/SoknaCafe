<?php
declare(strict_types=1);

require dirname(__DIR__).'/bootstrap.php';
require __DIR__.'/compat.php';

$installationId=trim((string)($_GET['installation_id']??public_guest_installation_id()));
if($installationId===''){http_response_code(404);exit('Not found');}
public_guest_bind_installation($installationId);

$bundle=public_guest_bundle($installationId);
$snapshot=is_array($bundle['snapshot']??null)?$bundle['snapshot']:[];
if(!$snapshot){
    http_response_code(503);
    ?><!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>منوی سکنا</title><style>body{font-family:Tahoma,Arial,sans-serif;background:#f7f3ec;display:grid;place-items:center;min-height:100vh;margin:0;color:#26312b}.box{background:#fff;padding:28px;border-radius:20px;max-width:480px;text-align:center;border:1px solid #e7ded3}</style><body><div class="box"><h1>منوی عمومی هنوز منتشر نشده</h1><p>لطفاً کمی بعد دوباره امتحان کنید.</p></div></body></html><?php
    exit;
}

$tableToken=trim((string)($_GET['table']??''));
$table=$tableToken!==''?public_guest_table_from_token($snapshot,$tableToken):null;
if($tableToken!==''&&!$table){
    http_response_code(404);header('Cache-Control: no-store, max-age=0');
    ?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><title>QR نامعتبر | Sokna</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f7f3ec;color:#262722;font-family:Tahoma,Arial,sans-serif}.box{width:min(520px,calc(100% - 32px));padding:26px;border:1px solid #e5dfd6;border-radius:22px;background:#fff;text-align:center;box-shadow:0 16px 44px #0001}.box p{color:#6f746f;line-height:2}</style></head><body><main class="box"><h1>این QR معتبر نیست</h1><p>این QR ممکن است قدیمی یا مربوط به میزی غیرفعال باشد. QR موجود روی میز را دوباره اسکن کنید یا از همکاران کافه کمک بگیرید.</p></main></body></html><?php
    exit;
}

$manifest=is_array($bundle['media_manifest']??null)?$bundle['media_manifest']:[];
$availability=is_array($bundle['availability']??null)?$bundle['availability']:[];
$features=is_array($snapshot['features']??null)?$snapshot['features']:[];
$themeData=is_array($snapshot['theme']??null)?$snapshot['theme']:[];
$actionState=public_guest_action_state($bundle);

if(!$actionState['enabled']){
    $availability['order_acceptance']=['cafe'=>false,'kitchen'=>false,'bar'=>false];
    $availability['order_acceptance_messages']=[
        'cafe'=>'ارتباط زنده با کافه موقتاً در دسترس نیست؛ منو همچنان قابل مشاهده است.',
        'kitchen'=>'ارتباط زنده با کافه موقتاً در دسترس نیست.',
        'bar'=>'ارتباط زنده با کافه موقتاً در دسترس نیست.',
    ];
}

$GLOBALS['soknaPublicGuestContext']=[
    'installation_id'=>$installationId,'snapshot'=>$snapshot,'media_manifest'=>$manifest,
    'availability'=>$availability,'bundle'=>$bundle,'action_state'=>$actionState,
];

$isPublic=$table===null;
$session=null;
$requestedMenuKey=trim((string)($_GET['menu']??''));
$rawMenus=is_array($snapshot['menus']??null)?$snapshot['menus']:[];
$catalogs=is_array($snapshot['catalogs']??null)?$snapshot['catalogs']:[];
$availableMenus=[];
foreach($rawMenus as $index=>$row){
    if(!is_array($row))continue;
    $availableMenus[]=[
        'id'=>$index+1,'menu_key'=>(string)($row['menu_key']??''),
        'name'=>(string)($row['name']??''),'sort_order'=>(int)($row['sort_order']??0),
    ];
}
$selectedMenu=$availableMenus[0]??null;
if($requestedMenuKey!==''){
    foreach($availableMenus as $row)if((string)$row['menu_key']===$requestedMenuKey){$selectedMenu=$row;break;}
}
$selectedKey=(string)($selectedMenu['menu_key']??'');
$catalog=is_array($catalogs[$selectedKey]??null)?$catalogs[$selectedKey]:['categories'=>[],'items'=>[]];

$availabilityItems=is_array($availability['items']??null)?$availability['items']:[];
$categoryNames=[];
$menuByCategory=[];
foreach(($catalog['categories']??[]) as $category){
    if(!is_array($category))continue;
    $category['id']=(int)($category['id']??0);
    $category['sort_order']=(int)($category['sort_order']??0);
    $category['items']=[];
    $categoryNames[$category['id']]=(string)($category['name']??'');
    $menuByCategory[$category['id']]=$category;
}
$flatItems=[];
foreach(($catalog['items']??[]) as $item){
    if(!is_array($item))continue;
    $id=(int)($item['id']??0);$categoryId=(int)($item['category_id']??0);
    $live=is_array($availabilityItems[(string)$id]??null)?$availabilityItems[(string)$id]:[];
    $item['id']=$id;$item['category_id']=$categoryId;
    $item['category_name']=(string)($item['category_name']??($categoryNames[$categoryId]??''));
    $item['available']=(array_key_exists('available',$live)?(bool)$live['available']:(bool)($item['available']??false))?1:0;
    $item['featured']=!empty($item['featured'])?1:0;
    $item['takeaway_allowed']=!array_key_exists('takeaway_allowed',$item)||!empty($item['takeaway_allowed'])?1:0;
    $item['preparation_station']=(string)($item['preparation_station']??'cold_bar');
    $item['tags']=is_array($item['tags']??null)?$item['tags']:[];
    $description=trim((string)($item['description']??''));
    $sameName=function_exists('mb_strtolower')
        ? mb_strtolower($description,'UTF-8')===mb_strtolower(trim((string)($item['name']??'')),'UTF-8')
        : $description===trim((string)($item['name']??''));
    $item['display_description']=$sameName?'':$description;
    $flatItems[]=$item;
    if(isset($menuByCategory[$categoryId]))$menuByCategory[$categoryId]['items'][]=$item;
}
$menu=array_values($menuByCategory);

$orderAcceptance=order_acceptance_states();
$clientItems=array_map(static function(array $item)use($isPublic):array{
    $blocked=$isPublic?null:order_acceptance_blocked_scope_for_station((string)$item['preparation_station']);
    return [
        'id'=>(int)$item['id'],'name'=>(string)$item['name'],
        'description'=>(string)($item['display_description']??$item['description']??''),
        'category'=>(string)($item['category_name']??''),
        'image'=>$item['image_path']?asset((string)$item['image_path']):'',
        'search_text'=>(string)$item['name'].' '.(string)($item['category_name']??'').' '.(string)($item['display_description']??'').' '.implode(' ',array_column($item['tags']??[],'title')),
        'price'=>(int)$item['price'],'available'=>(int)$item['available'],
        'takeaway_allowed'=>(int)($item['takeaway_allowed']??1),
        'order_available'=>$isPublic?0:(((int)$item['available']===1&&$blocked===null)?1:0),
        'blocked_scope'=>$blocked,'unavailable_message'=>$blocked!==null?order_acceptance_message($blocked):'',
        'preparation_station'=>$isPublic?'':normalize_preparation_station((string)$item['preparation_station']),
        'preparation_area'=>$isPublic?'':preparation_area_for_station((string)$item['preparation_station']),
        'station_busy'=>$isPublic?0:(station_is_busy((string)$item['preparation_station'])?1:0),
        'suggested_item_id'=>$item['suggested_item_id']!==null?(int)$item['suggested_item_id']:null,
        'tags'=>array_map(static fn(array $tag):array=>[
            'title'=>(string)($tag['title']??''),'color_key'=>(string)($tag['color_key']??''),'icon'=>(string)($tag['icon']??''),
        ],$item['tags']??[]),
    ];
},$flatItems);

$marketing=is_array($snapshot['marketing']??null)?$snapshot['marketing']:[];
$marketingEnabled=sokna_module_enabled('marketing');
$events=$marketingEnabled&&setting_bool('events_enabled',true)&&is_array($marketing['events']??null)?$marketing['events']:[];
$campaign=$marketingEnabled&&setting_bool('campaigns_enabled',true)&&is_array($marketing['campaign']??null)?$marketing['campaign']:null;
$social=setting_bool('social_footer_enabled',true)&&is_array($themeData['social_links']??null)?$themeData['social_links']:[];
$accommodationUrl=setting_bool('accommodation_enabled',true)?safe_external_url(setting('accommodation_site_url')):'';

$orderingEnabled=(bool)$orderAcceptance['cafe']&&$actionState['enabled'];
$sessionsEnabled=!empty($features['table_sessions_enabled']);
$canOrder=(bool)$table&&$orderingEnabled;
$publicWaiterEnabled=$table===null&&$actionState['enabled']&&!empty($availability['waiter_enabled_public']);
$publicWaiterTables=[];
if($publicWaiterEnabled){
    foreach(($snapshot['tables']??[]) as $row){
        if(!is_array($row))continue;
        $publicWaiterTables[]=[
            'id'=>(int)($row['id']??0),'name'=>(string)($row['name']??''),
            'code'=>(string)($row['code']??''),'table_number'=>(int)($row['table_number']??0),
            'sort_order'=>(int)($row['sort_order']??0),
        ];
    }
}
$waiterEnabled=$table
    ?($actionState['enabled']&&!empty($availability['waiter_enabled_table']))
    :($publicWaiterEnabled&&!empty($publicWaiterTables));
$eventsEnabled=$marketingEnabled&&setting_bool('events_enabled',true)&&!empty($events);

if($table){
    $table=[
        'id'=>(int)($table['id']??0),'name'=>(string)($table['name']??''),
        'code'=>(string)($table['code']??''),'access_token'=>(string)($table['token']??''),
        'zone_label'=>(string)($table['zone_label']??''),
    ];
}
$logoPath=setting('logo_path');
$primary=valid_hex_color(setting('primary_color','#365b4c'),'#365b4c');
$accent=valid_hex_color(setting('accent_color','#b85c38'),'#b85c38');
$background=valid_hex_color(setting('background_color','#f7f3ec'),'#f7f3ec');
$onPrimary=contrast_text_color($primary);$onAccent=contrast_text_color($accent);
$theme=menu_theme();$font=menu_font();$density=menu_density();$layout=menu_layout();
$description=setting('seo_description','منوی کافه و رویدادهای سکنا');
$tableBadge=$table?trim((string)preg_replace('/^میز\s*/u','',(string)$table['name'])):'—';
$showTableUi=$table!==null;$showOrderUi=$canOrder;

$searchPresets=[];
foreach($menu as $category){if(count($searchPresets)>=4)break;$searchPresets[]=(string)$category['name'];}
foreach($flatItems as $item){if(count($searchPresets)>=6)break;if((int)$item['featured']===1&&!in_array((string)$item['name'],$searchPresets,true))$searchPresets[]=(string)$item['name'];}
$featuredItems=array_values(array_filter($flatItems,static fn(array $item):bool=>(int)$item['featured']===1&&(int)$item['available']===1));
if(!$featuredItems)$featuredItems=array_values(array_filter($flatItems,static fn(array $item):bool=>(int)$item['available']===1));
$featuredItems=array_slice($featuredItems,0,5);

define('SOKNA_GUEST_VIEW_READY',true);
require dirname(__DIR__,2).'/includes/guest_menu_view.php';
