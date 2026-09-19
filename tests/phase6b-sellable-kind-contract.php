<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$schema=(string)file_get_contents($root.'/database/schema.sql');
$migration=(string)file_get_contents($root.'/docs/architecture-migration-r2/PHASE6B_LOCAL_MIGRATION.sql');
$sellable=(string)file_get_contents($root.'/includes/sellable.php');
$form=(string)file_get_contents($root.'/admin/item_form.php');
$admin=(string)file_get_contents($root.'/admin/items.php');
$catalog=(string)file_get_contents($root.'/includes/menu_catalog.php');
$functions=(string)file_get_contents($root.'/includes/functions.php');
$guest=(string)file_get_contents($root.'/includes/guest_order_service.php');
$guestManage=(string)file_get_contents($root.'/includes/guest_order_manage_service.php');
$staff=(string)file_get_contents($root.'/staff/api_quick_order.php')."\n".(string)file_get_contents($root.'/includes/staff_order_service.php');
$bill=(string)file_get_contents($root.'/operator/api_bill.php');
$audit=(string)file_get_contents($root.'/includes/function_domains/audit.php');
$seed=json_decode((string)file_get_contents($root.'/database/default_menu.json'),true,512,JSON_THROW_ON_ERROR);

function p6b(bool $ok,string $message):void{
    if(!$ok){fwrite(STDERR,"FAIL: $message\n");exit(1);}
    echo "PASS: $message\n";
}

p6b((bool)preg_match("/sellable_kind\s+VARCHAR\(20\)\s+NOT NULL DEFAULT 'menu_item'/",$schema),'items has explicit sellable kind defaulting to menu_item');
p6b((bool)preg_match('/sellable_kind_snapshot\s+VARCHAR\(20\)\s+NULL/',$schema),'order lines have nullable sellable-kind snapshot');
p6b(str_contains($migration,"SET sellable_kind='service_item'")&&str_contains($migration,"item_code IN ('SERVICE-TAKEAWAY','SERVICE-CAKE')"),'migration marks only documented service item codes explicitly');
p6b(!preg_match('/category|preparation_station/i',preg_replace('/--.*$/m','',$migration)??''),'migration never infers service kind from category or preparation station');
p6b(!preg_match('/UPDATE\s+order_items\s+SET\s+sellable_kind_snapshot/i',$migration),'migration does not rewrite historical order-line kind snapshots');

p6b(str_contains($sellable,"SOKNA_SELLABLE_MENU_ITEM='menu_item'")&&str_contains($sellable,"SOKNA_SELLABLE_SERVICE_ITEM='service_item'"),'canonical sellable kinds are explicit');
p6b(str_contains($sellable,'function require_sellable_kind'),'write-time sellable kind validation is strict');
p6b(str_contains($form,'نوع مورد')&&str_contains($form,'name="sellable_kind"'),'item form exposes explicit item/service selector');
p6b(str_contains($form,'require_sellable_kind('),'item form rejects invalid sellable kind instead of silently guessing');
p6b(str_contains($admin,"'sellable_kind' => normalize_sellable_kind")&&str_contains($admin,"'sellable_kind_label' => sellable_kind_label"),'admin item payload exposes explicit kind and label');
p6b(str_contains($catalog,'i.sellable_kind'),'shared menu catalog projects explicit kind');
p6b(str_contains($functions,'i.sellable_kind'),'canonical locked order catalog reads explicit kind');

foreach([$guest,$guestManage,$staff,$bill] as $source){
    p6b(str_contains($source,'sellable_kind_snapshot'),'every canonical order-write family persists sellable-kind snapshot');
}
p6b(str_contains($audit,"'sellable_kind'=>normalize_sellable_kind"),'sellable kind changes are part of menu-item audit snapshot');

$byCode=[];
foreach(($seed['items']??[]) as $row)$byCode[(string)($row['item_code']??'')]=$row;
foreach(['SERVICE-TAKEAWAY','SERVICE-CAKE'] as $code){
    $row=$byCode[$code]??null;
    p6b(is_array($row)&&($row['sellable_kind']??null)==='service_item',$code.' is explicitly a service item in seed');
    p6b((int)($row['staff_only']??0)===1&&($row['preparation_station']??'')==='none',$code.' service properties remain explicit, separate settings');
}
foreach(($seed['items']??[]) as $row){
    if(!str_starts_with((string)($row['item_code']??''),'SERVICE-')){
        p6b(($row['sellable_kind']??'menu_item')!=='service_item','normal seed item is not inferred as service from category/station');
    }
}

$orderRuntime=$guest."\n".$guestManage."\n".$staff."\n".$bill;
p6b(!str_contains($orderRuntime,'SERVICE-TAKEAWAY'),'order runtime never hardcodes automatic takeaway service insertion');
p6b(!preg_match('/takeaway[\s\S]{0,300}service_item/i',$orderRuntime),'takeaway mode does not imply service-item classification');

echo "Phase 6B explicit sellable kind contract PASS\n";
