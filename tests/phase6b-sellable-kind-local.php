<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
require_once dirname(__DIR__).'/includes/guest_order_service.php';

function p6bl(bool $ok,string $message,mixed $context=null):void{
    if(!$ok){
        fwrite(STDERR,"FAIL: $message\n");
        if($context!==null)fwrite(STDERR,json_encode($context,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n");
        exit(1);
    }
    echo "PASS: $message\n";
}

$pdo=db();
$suffix=substr(bin2hex(random_bytes(6)),0,10);
$menuKey='p6b-'.$suffix;
$categoryKey='p6b-cat-'.$suffix;
$tableToken='p6b-table-'.$suffix.'-1234567890';

$pdo->prepare("INSERT INTO menus(menu_key,name,status,sort_order) VALUES(?,?,'active',900)")->execute([$menuKey,'Phase6B Menu']);
$menuId=(int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO categories(category_key,name,audience,sort_order,active) VALUES(?,?,'guest_staff',900,1)")->execute([$categoryKey,'Phase6B Normal Category']);
$categoryId=(int)$pdo->lastInsertId();
$pdo->prepare('INSERT INTO menu_categories(menu_id,category_id,sort_order) VALUES(?,?,10)')->execute([$menuId,$categoryId]);

$insert=$pdo->prepare("INSERT INTO items(item_code,category_id,name,price,available,active,staff_only,sellable_kind,takeaway_allowed,preparation_station,sort_order) VALUES(?,?,?,?,1,1,0,?,1,?,?)");
$insert->execute(['P6B-MENU-'.$suffix,$categoryId,'No Prep Menu Item',11000,SOKNA_SELLABLE_MENU_ITEM,'none',10]);
$menuItemId=(int)$pdo->lastInsertId();
$insert->execute(['P6B-SVC-'.$suffix,$categoryId,'Explicit Service In Normal Category',22000,SOKNA_SELLABLE_SERVICE_ITEM,'kitchen',20]);
$serviceItemId=(int)$pdo->lastInsertId();
$mi=$pdo->prepare('INSERT INTO menu_items(menu_id,item_id) VALUES(?,?)');
$mi->execute([$menuId,$menuItemId]);$mi->execute([$menuId,$serviceItemId]);

$locked=order_catalog_items_locked($pdo,[$menuItemId,$serviceItemId]);
p6bl(($locked[$menuItemId]['sellable_kind']??null)===SOKNA_SELLABLE_MENU_ITEM,'station=none does not turn a menu item into a service',$locked[$menuItemId]??null);
p6bl(($locked[$serviceItemId]['sellable_kind']??null)===SOKNA_SELLABLE_SERVICE_ITEM,'normal category/kitchen station does not erase explicit service kind',$locked[$serviceItemId]??null);
p6bl(sellable_item_is_service($locked[$menuItemId])===false,'menu item classification uses explicit kind');
p6bl(sellable_item_is_service($locked[$serviceItemId])===true,'service classification uses explicit kind');

$pdo->prepare("INSERT INTO cafe_tables(name,table_number,code,access_token,active,sort_order) VALUES(?,?,?,?,1,900)")
    ->execute(['Phase6B Table',29000+random_int(1,500),'P6B-'.$suffix,$tableToken]);
$tableId=(int)$pdo->lastInsertId();

$result=guest_order_commit($pdo,[
    'table_token'=>$tableToken,
    'session_token'=>'',
    'device_token'=>'p6b-device-'.$suffix.'-123456',
    'client_token'=>'p6b-client-'.$suffix.'-123456',
    'customer_note'=>'',
    'items'=>[[
        'id'=>$serviceItemId,
        'quantity'=>1,
        'unit_price'=>22000,
        'note'=>'',
        'fulfillment_mode'=>'dine_in',
    ]],
]);
p6bl(!empty($result['success'])&&!empty($result['order_code']),'canonical guest order owner committed explicit service item',$result);
$orderLookup=$pdo->prepare('SELECT id FROM orders WHERE client_token=? LIMIT 1');
$orderLookup->execute(['p6b-client-'.$suffix.'-123456']);
$orderId=(int)($orderLookup->fetchColumn()?:0);
p6bl($orderId>0,'committed order exists in Local authority');

$stmt=$pdo->prepare('SELECT sellable_kind_snapshot,preparation_station,item_name FROM order_items WHERE order_id=? LIMIT 1');
$stmt->execute([$orderId]);$line=$stmt->fetch()?:[];
p6bl(($line['sellable_kind_snapshot']??null)===SOKNA_SELLABLE_SERVICE_ITEM,'committed order line snapshots explicit service kind',$line);
p6bl(($line['preparation_station']??null)==='kitchen','sellable kind does not silently rewrite preparation station',$line);

p6bl(require_sellable_kind('menu_item')==='menu_item'&&require_sellable_kind('service_item')==='service_item','strict validator accepts both canonical kinds');
$invalidRejected=false;
try{require_sellable_kind('internal-services');}catch(InvalidArgumentException){$invalidRejected=true;}
p6bl($invalidRejected,'strict validator rejects category-like inferred value');

echo "Phase 6B sellable kind Local/MariaDB integration PASS\n";
