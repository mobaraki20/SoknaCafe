<?php
declare(strict_types=1);

require_once __DIR__.'/relay_protocol.php';

const SOKNA_REMOTE_READ_FORMAT='sokna-remote-read-v1';

function sokna_remote_read_wrap(string $key,array $payload):array
{
    $sourceVersion=hash('sha256',sokna_relay_canonical_json($payload));
    return [
        'format'=>SOKNA_REMOTE_READ_FORMAT,
        'model_key'=>$key,
        'source_version'=>$sourceVersion,
        'generated_at'=>gmdate('c'),
        'payload'=>$payload,
    ];
}

function sokna_remote_operations_model(PDO $pdo):array
{
    $orders=$pdo->query("SELECT o.id,o.status,o.total_amount,o.created_at,o.updated_at,t.id table_id,t.name table_name,t.zone_label
        FROM orders o JOIN cafe_tables t ON t.id=o.table_id
        WHERE t.active=1 AND o.status IN('pending_approval','new','accounted')
        ORDER BY o.created_at ASC,o.id ASC LIMIT 120")->fetchAll(PDO::FETCH_ASSOC);
    $calls=$pdo->query("SELECT w.id,w.status,w.created_at,w.updated_at,t.id table_id,t.name table_name,t.zone_label
        FROM waiter_calls w JOIN cafe_tables t ON t.id=w.table_id
        WHERE t.active=1 AND w.status IN('new','accepted')
        ORDER BY w.created_at ASC,w.id ASC LIMIT 120")->fetchAll(PDO::FETCH_ASSOC);
    $tables=$pdo->query("SELECT t.id,t.name,t.table_number,t.code,t.zone_label,s.status session_status,s.started_at,s.guest_count
        FROM cafe_tables t
        LEFT JOIN table_sessions s ON s.table_id=t.id AND s.status IN('active','pending')
        WHERE t.active=1 ORDER BY t.sort_order,t.table_number,t.id")->fetchAll(PDO::FETCH_ASSOC);
    foreach($orders as &$row){
        $row['id']=(int)$row['id'];$row['table_id']=(int)$row['table_id'];$row['total_amount']=(int)$row['total_amount'];
        $row['status_label']=order_status_label((string)$row['status']);
        $row['order_number']=order_display_number($row);
    }unset($row);
    foreach($calls as &$row){$row['id']=(int)$row['id'];$row['table_id']=(int)$row['table_id'];}unset($row);
    foreach($tables as &$row){$row['id']=(int)$row['id'];$row['table_number']=(int)$row['table_number'];$row['guest_count']=$row['guest_count']===null?null:(int)$row['guest_count'];}unset($row);
    return [
        'business_date'=>business_current_date(),
        'order_acceptance'=>order_acceptance_states(),
        'station_states'=>station_busy_states(),
        'waiter_enabled'=>setting_bool('waiter_call_enabled',true),
        'orders'=>$orders,'waiter_calls'=>$calls,'tables'=>$tables,
    ];
}

function sokna_remote_preparation_model(PDO $pdo):array
{
    $businessDate=$pdo->quote(business_current_date());
    $orders=$pdo->query("SELECT o.id,o.status,o.created_at,o.updated_at,t.id table_id,t.name table_name,t.zone_label
        FROM orders o JOIN cafe_tables t ON t.id=o.table_id
        WHERE o.status IN('accounted','completed') AND o.business_date=$businessDate
        ORDER BY o.created_at,o.id LIMIT 160")->fetchAll(PDO::FETCH_ASSOC);
    $orderIds=array_map('intval',array_column($orders,'id'));
    $itemsByOrder=[];$claims=[];
    if($orderIds){
        $ph=implode(',',array_fill(0,count($orderIds),'?'));
        $st=$pdo->prepare("SELECT id,order_id,item_name,quantity,item_note,fulfillment_mode,preparation_station
            FROM order_items WHERE order_id IN ($ph) AND quantity>0 ORDER BY order_id,id");
        $st->execute($orderIds);
        foreach($st->fetchAll(PDO::FETCH_ASSOC) as $line){
            if(!preparation_station_requires_work((string)$line['preparation_station']))continue;
            $area=preparation_area_for_station((string)$line['preparation_station']);
            $line['id']=(int)$line['id'];$line['order_id']=(int)$line['order_id'];$line['quantity']=(int)$line['quantity'];$line['area']=$area;
            $itemsByOrder[(int)$line['order_id']][$area][]=$line;
        }
        $cs=$pdo->prepare("SELECT order_id,area_key,item_signature,claimed_by_user_id,claimed_at,updated_at FROM order_preparation_claims WHERE order_id IN ($ph)");
        $cs->execute($orderIds);
        foreach($cs->fetchAll(PDO::FETCH_ASSOC) as $claim)$claims[(int)$claim['order_id']][(string)$claim['area_key']]=$claim;
    }
    $tasks=[];
    foreach($orders as $order){
        $oid=(int)$order['id'];
        foreach($itemsByOrder[$oid]??[] as $area=>$items){
            $claim=$claims[$oid][$area]??null;
            $claimed=$claim&&hash_equals((string)$claim['item_signature'],preparation_items_signature($items));
            $tasks[]=[
                'key'=>"order-$oid-$area",'kind'=>'order','area'=>$area,
                'area_label'=>preparation_operational_areas()[$area]??$area,
                'order_id'=>$oid,'table_id'=>(int)$order['table_id'],'table_name'=>(string)$order['table_name'],
                'zone_label'=>(string)($order['zone_label']??''),'status'=>(string)$order['status'],
                'claimed'=>$claimed,'claimed_at'=>$claimed?(string)($claim['claimed_at']??''):null,
                'items'=>$items,'created_at'=>(string)$order['created_at'],'updated_at'=>(string)$order['updated_at'],
            ];
        }
    }
    $adjustments=$pdo->query("SELECT pa.id,pa.order_id,pa.table_id,pa.area_key,pa.item_name,pa.previous_quantity,pa.new_quantity,pa.reason,pa.status,pa.created_at,pa.updated_at,t.name table_name,t.zone_label
        FROM preparation_adjustments pa JOIN cafe_tables t ON t.id=pa.table_id
        WHERE pa.status NOT IN('applied','cancelled') ORDER BY pa.created_at,pa.id LIMIT 160")->fetchAll(PDO::FETCH_ASSOC);
    foreach($adjustments as &$row){
        foreach(['id','order_id','table_id','previous_quantity','new_quantity'] as $k)$row[$k]=(int)$row[$k];
    }unset($row);
    return ['areas'=>preparation_operational_areas(),'tasks'=>$tasks,'adjustments'=>$adjustments];
}

function sokna_remote_inventory_models(PDO $pdo):array
{
    if(!sokna_module_enabled('inventory'))return [
        'inventory'=>['enabled'=>false,'items'=>[],'low_stock_count'=>0],
        'inventory_cost'=>['enabled'=>false,'items'=>[],'total_stock_value'=>0],
    ];
    $rows=$pdo->query("SELECT i.id,i.item_code,i.name,i.category,i.base_unit,i.default_department,i.warning_threshold,i.review_status,
        COALESCE(b.quantity_base,0) quantity_base,b.average_unit_cost,b.cost_status,b.updated_at
        FROM inventory_items i LEFT JOIN inventory_balances b ON b.inventory_item_id=i.id
        WHERE i.active=1 ORDER BY i.name,i.id LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
    $basic=[];$cost=[];$low=0;$totalValue=0;
    foreach($rows as $row){
        $qty=(int)$row['quantity_base'];$threshold=(int)$row['warning_threshold'];$isLow=$threshold>0&&$qty<=$threshold;if($isLow)$low++;
        $basic[]=[
            'id'=>(int)$row['id'],'item_code'=>(string)$row['item_code'],'name'=>(string)$row['name'],
            'category'=>(string)$row['category'],'base_unit'=>(string)$row['base_unit'],'department'=>(string)$row['default_department'],
            'quantity_base'=>$qty,'warning_threshold'=>$threshold,'low_stock'=>$isLow,'review_status'=>(string)$row['review_status'],
            'updated_at'=>(string)($row['updated_at']??''),
        ];
        $avg=$row['average_unit_cost']===null?null:(float)$row['average_unit_cost'];
        $value=$avg===null?null:(int)round(max(0,$qty)*$avg);
        if($value!==null)$totalValue+=$value;
        $cost[]=[
            'id'=>(int)$row['id'],'name'=>(string)$row['name'],'quantity_base'=>$qty,'base_unit'=>(string)$row['base_unit'],
            'average_unit_cost'=>$avg,'cost_status'=>(string)($row['cost_status']??'unknown'),'stock_value'=>$value,
        ];
    }
    return [
        'inventory'=>['enabled'=>true,'items'=>$basic,'low_stock_count'=>$low],
        'inventory_cost'=>['enabled'=>true,'items'=>$cost,'total_stock_value'=>$totalValue],
    ];
}

function sokna_remote_reports_model(PDO $pdo):array
{
    if(!sokna_module_enabled('reporting'))return ['enabled'=>false];
    $today=business_current_date();
    $from30=(new DateTimeImmutable($today))->modify('-29 days')->format('Y-m-d');
    $valid="sr.status='completed' AND NOT EXISTS(SELECT 1 FROM settlement_records rv WHERE rv.reverses_settlement_id=sr.id AND rv.status='reversal')";
    $sum=function(string $from,string $to)use($pdo,$valid):array{
        $st=$pdo->prepare("SELECT COUNT(*) receipts,COALESCE(SUM(sr.total),0) revenue,COALESCE(SUM(sr.discount),0) discount
            FROM settlement_records sr WHERE $valid AND sr.business_date BETWEEN ? AND ?");
        $st->execute([$from,$to]);$r=$st->fetch(PDO::FETCH_ASSOC)?:[];
        return ['receipts'=>(int)($r['receipts']??0),'revenue'=>(int)($r['revenue']??0),'discount'=>(int)($r['discount']??0)];
    };
    $todaySummary=$sum($today,$today);$summary30=$sum($from30,$today);
    $orders=$pdo->prepare("SELECT COUNT(*) c,COALESCE(SUM(total_amount),0) total FROM orders WHERE status<>'cancelled' AND business_date BETWEEN ? AND ?");
    $orders->execute([$from30,$today]);$or=$orders->fetch(PDO::FETCH_ASSOC)?:[];
    $top=$pdo->prepare("SELECT sl.item_name_snapshot name,SUM(sl.quantity) quantity,SUM(sl.net_amount) net
        FROM settlement_record_lines sl JOIN settlement_records sr ON sr.id=sl.settlement_id
        WHERE $valid AND sr.business_date BETWEEN ? AND ? GROUP BY sl.item_name_snapshot ORDER BY net DESC LIMIT 10");
    $top->execute([$from30,$today]);$topRows=$top->fetchAll(PDO::FETCH_ASSOC);
    foreach($topRows as &$r){$r['quantity']=(int)$r['quantity'];$r['net']=(int)$r['net'];}unset($r);
    return [
        'enabled'=>true,'today'=>$today,'range_30'=>['from'=>$from30,'to'=>$today],
        'today_summary'=>$todaySummary,'summary_30'=>$summary30,
        'orders_30'=>['count'=>(int)($or['c']??0),'gross'=>(int)($or['total']??0)],
        'top_items_30'=>$topRows,
    ];
}

function sokna_remote_read_models(PDO $pdo):array
{
    $inventory=sokna_remote_inventory_models($pdo);
    return [
        sokna_remote_read_wrap('operations',sokna_remote_operations_model($pdo)),
        sokna_remote_read_wrap('preparation',sokna_remote_preparation_model($pdo)),
        sokna_remote_read_wrap('inventory',$inventory['inventory']),
        sokna_remote_read_wrap('inventory_cost',$inventory['inventory_cost']),
        sokna_remote_read_wrap('reports',sokna_remote_reports_model($pdo)),
    ];
}
