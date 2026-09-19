<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_any_capability(['orders_floor','preparation','shift_supervision']);

$user=current_user();
$userId=(int)$user['id'];
$preparationMode=(string)($_GET['mode']??'')==='preparation';
$ordersAllowed=!$preparationMode && user_has_capability('orders_floor',$user);
$preparationAccess=preparation_access_context($user);
$preparationAllowed=(bool)$preparationAccess['can_view'];
$assignedAreas=$preparationAccess['assigned_areas'];
$canClaimPreparation=(bool)$preparationAccess['can_mutate'];
$monitorPreparation=(bool)$preparationAccess['monitor_only'] || (bool)$preparationAccess['has_shift_supervision'] || (bool)$preparationAccess['is_admin'];
$areas=$preparationAccess['visible_areas'];
$actionableAreas=$preparationAccess['actionable_areas'];
$pdo=db();
$businessDateSql=$pdo->quote(business_current_date());
$tasks=[];$callIds=[];$attentionKeys=[];$todayOrders=[];
$clientRevision=trim((string)($_GET['revision']??''));
$lightRevision='';
try{
    $revision=$pdo->query("SELECT
      (SELECT COALESCE(MAX(updated_at),'') FROM orders WHERE status IN('pending_approval','new','accounted','completed') AND business_date=$businessDateSql) orders_updated,
      (SELECT COUNT(*) FROM orders WHERE status IN('pending_approval','new','accounted','completed') AND business_date=$businessDateSql) orders_count,
      (SELECT COALESCE(MAX(updated_at),'') FROM waiter_calls WHERE status IN('new','accepted')) calls_updated,
      (SELECT COUNT(*) FROM waiter_calls WHERE status IN('new','accepted')) calls_count,
      (SELECT COALESCE(MAX(updated_at),'') FROM order_preparation_claims) claims_updated,
      (SELECT COALESCE(MAX(updated_at),'') FROM preparation_adjustments WHERE status NOT IN('applied','cancelled')) adjustments_updated,
      (SELECT COUNT(*) FROM preparation_adjustments WHERE status NOT IN('applied','cancelled')) adjustments_count")->fetch()?:[];
    $lightRevision=substr(hash('sha256',json_encode([$revision,$areas,$ordersAllowed,$canClaimPreparation],JSON_UNESCAPED_UNICODE)),0,24);
}catch(Throwable $revisionError){error_log('preparation revision: '.$revisionError->getMessage());}
if($lightRevision!==''&&$clientRevision!==''&&hash_equals($lightRevision,$clientRevision)){
    json_response(['success'=>true,'unchanged'=>true,'revision'=>$lightRevision,'permissions'=>['orders_floor'=>$ordersAllowed,'preparation'=>$preparationAllowed,'preparation_areas'=>$areas,'visible_preparation_areas'=>$areas,'actionable_preparation_areas'=>$actionableAreas,'can_claim_preparation'=>$canClaimPreparation,'monitor_preparation'=>$monitorPreparation],'server_time'=>date(DATE_ATOM)]);
}

if($ordersAllowed){
    $calls=$pdo->query("SELECT w.id,w.status,w.created_at,w.accepted_by_user_id,t.id table_id,t.name table_name,t.zone_label,u.display_name accepted_by FROM waiter_calls w JOIN cafe_tables t ON t.id=w.table_id LEFT JOIN users u ON u.id=w.accepted_by_user_id WHERE t.active=1 AND w.status IN('new','accepted') ORDER BY w.created_at,w.id")->fetchAll();
    foreach($calls as $row){
        $minutes=max(0,(int)floor((time()-strtotime((string)$row['created_at']))/60));$id=(int)$row['id'];$callIds[]=(string)$id;
        $tasks[]=['key'=>'call-'.$id,'kind'=>'call','id'=>$id,'table_id'=>(int)$row['table_id'],'table_name'=>(string)$row['table_name'],'zone_label'=>(string)($row['zone_label']??''),'title'=>'فراخوان مهمان','subtitle'=>$row['status']==='accepted'?'در حال رسیدگی':'هنوز کسی نپذیرفته','status'=>(string)$row['status'],'accepted_by'=>(string)($row['accepted_by']??''),'mine'=>(int)($row['accepted_by_user_id']??0)===$userId,'created_at'=>(string)$row['created_at'],'updated_at'=>'','time_ago'=>time_ago((string)$row['created_at']),'waiting_minutes'=>$minutes,'priority'=>300000+$minutes];
    }

    $pending=$pdo->query("SELECT o.id,o.table_id,o.status,o.total_amount,o.customer_note,o.created_at,o.updated_at,t.name table_name,t.zone_label FROM orders o JOIN cafe_tables t ON t.id=o.table_id WHERE t.active=1 AND o.status IN('pending_approval','new') ORDER BY o.created_at,o.id")->fetchAll();
    $ids=array_map('intval',array_column($pending,'id'));$itemsBy=[];
    if($ids){$ph=implode(',',array_fill(0,count($ids),'?'));$st=$pdo->prepare("SELECT id,order_id,item_name,quantity,item_note,fulfillment_mode,preparation_station,line_total FROM order_items WHERE order_id IN($ph) AND quantity>0 ORDER BY order_id,id");$st->execute($ids);foreach($st->fetchAll() as $line){if(!preparation_station_requires_work((string)$line['preparation_station']))continue;$line['preparation_station_label']=preparation_operational_areas()[preparation_area_for_station((string)$line['preparation_station'])]??'بار';$itemsBy[(int)$line['order_id']][]=$line;}}
    foreach($pending as $order){$id=(int)$order['id'];$items=$itemsBy[$id]??[];$qty=array_sum(array_map(static fn(array $x):int=>(int)$x['quantity'],$items));$minutes=max(0,(int)floor((time()-strtotime((string)$order['created_at']))/60));$key='order-'.$id.'-approval';$attentionKeys[]=$key;$tasks[]=['key'=>$key,'kind'=>'order','queue_stage'=>'approval','id'=>$id,'table_id'=>(int)$order['table_id'],'table_name'=>(string)$order['table_name'],'zone_label'=>(string)($order['zone_label']??''),'title'=>'سفارش جدید مهمان','subtitle'=>$qty.' عدد','status'=>(string)$order['status'],'pending'=>true,'total'=>(int)$order['total_amount'],'note'=>(string)($order['customer_note']??''),'items'=>$items,'created_at'=>(string)$order['created_at'],'updated_at'=>(string)$order['updated_at'],'time_ago'=>time_ago((string)$order['created_at']),'waiting_minutes'=>$minutes,'priority'=>200000+$minutes];}
}


if($areas){
    $placeholders=implode(',',array_fill(0,count($areas),'?'));
    $adjustmentStmt=$pdo->prepare("SELECT pa.*,t.name table_name,t.zone_label,o.created_at order_created_at FROM preparation_adjustments pa JOIN cafe_tables t ON t.id=pa.table_id JOIN orders o ON o.id=pa.order_id WHERE pa.area_key IN ($placeholders) AND pa.status NOT IN('applied','cancelled') ORDER BY pa.created_at,pa.id");
    $adjustmentStmt->execute($areas);
    $adjustments=$adjustmentStmt->fetchAll();
    foreach($adjustments as $adjustment){
        $id=(int)$adjustment['id'];
        $key='adjustment-'.$id;$attentionKeys[]=$key;
        $minutes=max(0,(int)floor((time()-strtotime((string)$adjustment['created_at']))/60));
        $tasks[]=[
            'key'=>$key,'kind'=>'adjustment','queue_stage'=>'adjustment','adjustment_id'=>$id,'id'=>(int)$adjustment['order_id'],
            'table_id'=>(int)$adjustment['table_id'],'table_name'=>(string)$adjustment['table_name'],'zone_label'=>(string)($adjustment['zone_label']??''),
            'area'=>(string)$adjustment['area_key'],'area_label'=>preparation_operational_areas()[(string)$adjustment['area_key']]??'آماده‌سازی',
            'title'=>(int)$adjustment['new_quantity']===0?'لغو آیتم':'اصلاح سفارش',
            'subtitle'=>(string)$adjustment['item_name'].' · '.(int)$adjustment['previous_quantity'].' ← '.(int)$adjustment['new_quantity'],
            'item_name'=>(string)$adjustment['item_name'],'previous_quantity'=>(int)$adjustment['previous_quantity'],'new_quantity'=>(int)$adjustment['new_quantity'],
            'reason'=>(string)$adjustment['reason'],'status'=>(string)$adjustment['status'],'created_at'=>(string)$adjustment['created_at'],'updated_at'=>(string)$adjustment['updated_at'],
            'time_ago'=>time_ago((string)$adjustment['created_at']),'waiting_minutes'=>$minutes,'priority'=>500000+$minutes,
        ];
    }
}

if($preparationAllowed && $areas){
    $orders=$pdo->query("SELECT o.id,o.table_id,o.status,o.total_amount,o.customer_note,o.created_at,o.updated_at,t.name table_name,t.zone_label FROM orders o JOIN cafe_tables t ON t.id=o.table_id WHERE o.status IN('accounted','completed') AND o.business_date=$businessDateSql ORDER BY o.created_at,o.id")->fetchAll();
    $ids=array_map('intval',array_column($orders,'id'));$itemsByArea=[];$claims=[];
    if($ids){
        $ph=implode(',',array_fill(0,count($ids),'?'));
        $st=$pdo->prepare("SELECT id,order_id,item_name,quantity,item_note,fulfillment_mode,preparation_station,line_total FROM order_items WHERE order_id IN($ph) AND quantity>0 ORDER BY order_id,id");$st->execute($ids);
        foreach($st->fetchAll() as $line){if(!preparation_station_requires_work((string)$line['preparation_station']))continue;$area=preparation_area_for_station((string)$line['preparation_station']);if(!in_array($area,$areas,true))continue;$line['preparation_station_label']=preparation_operational_areas()[$area];$itemsByArea[(int)$line['order_id']][$area][]=$line;}
        $cs=$pdo->prepare("SELECT c.*,u.display_name claimed_by FROM order_preparation_claims c LEFT JOIN users u ON u.id=c.claimed_by_user_id WHERE c.order_id IN($ph)");$cs->execute($ids);foreach($cs->fetchAll() as $claim)$claims[(int)$claim['order_id']][(string)$claim['area_key']]=$claim;
    }
    foreach($orders as $order){
        $id=(int)$order['id'];
        foreach($itemsByArea[$id]??[] as $area=>$items){
            $signature=preparation_items_signature($items);$claim=$claims[$id][$area]??null;$claimed=$claim&&hash_equals((string)$claim['item_signature'],$signature);$qty=array_sum(array_map(static fn(array $x):int=>(int)$x['quantity'],$items));$minutes=max(0,(int)floor((time()-strtotime((string)$order['created_at']))/60));$key="order-$id-$area";
            $record=['key'=>$key,'kind'=>'order','queue_stage'=>'preparation','area'=>$area,'area_label'=>preparation_operational_areas()[$area],'id'=>$id,'table_id'=>(int)$order['table_id'],'table_name'=>(string)$order['table_name'],'zone_label'=>(string)($order['zone_label']??''),'title'=>preparation_operational_areas()[$area].' · سفارش تأییدشده','subtitle'=>$qty.' عدد','status'=>(string)$order['status'],'pending'=>false,'claimed'=>$claimed,'claimed_by'=>$claimed?(string)($claim['claimed_by']??''):'','claimed_at'=>$claimed?(string)($claim['claimed_at']??''):null,'total'=>(int)$order['total_amount'],'note'=>(string)($order['customer_note']??''),'items'=>$items,'created_at'=>(string)$order['created_at'],'updated_at'=>$claimed?(string)$claim['updated_at']:(string)$order['updated_at'],'time_ago'=>time_ago((string)$order['created_at']),'waiting_minutes'=>$minutes,'priority'=>100000+$minutes];
            $todayOrders[]=$record;
            if(!$claimed){$attentionKeys[]=$key;$tasks[]=$record;}
        }
    }
}

usort($tasks,static fn(array $a,array $b):int=>((int)$b['priority']<=>(int)$a['priority'])?:strcmp((string)$a['created_at'],(string)$b['created_at']));
usort($todayOrders,static fn(array $a,array $b):int=>strcmp((string)$b['created_at'],(string)$a['created_at']));
$snapshot=hash('sha256',json_encode([$callIds,$attentionKeys,array_map(static fn(array $t):array=>[$t['key'],$t['status'],$t['updated_at']??'',$t['claimed_by']??''],$tasks),array_map(static fn(array $t):array=>[$t['key'],$t['claimed']??false,$t['claimed_by']??'',$t['updated_at']??''],$todayOrders)],JSON_UNESCAPED_UNICODE));
$client=trim((string)($_GET['snapshot']??''));$unchanged=$client!==''&&hash_equals($snapshot,$client);
json_response(['success'=>true,'unchanged'=>$unchanged,'snapshot'=>$snapshot,'revision'=>$lightRevision,'tasks'=>$unchanged?[]:$tasks,'today_orders'=>$unchanged?[]:$todayOrders,'call_ids'=>$callIds,'attention_keys'=>$attentionKeys,'permissions'=>['orders_floor'=>$ordersAllowed,'preparation'=>$preparationAllowed,'preparation_areas'=>$areas,'visible_preparation_areas'=>$areas,'actionable_preparation_areas'=>$actionableAreas,'can_claim_preparation'=>$canClaimPreparation,'monitor_preparation'=>$monitorPreparation],'server_time'=>date(DATE_ATOM)]);
