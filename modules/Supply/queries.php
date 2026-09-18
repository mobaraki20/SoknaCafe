<?php
declare(strict_types=1);

/** Read models for the purchasing UI. They never mutate inventory or supply state. */
function supply_purchase_group_key(array $row): string
{
    return supply_group_key_for_need($row);
}

function supply_purchase_groups(PDO $pdo): array
{
    $rows=$pdo->query("SELECT n.*,i.name inventory_name,i.default_department,i.active inventory_active,COALESCE(b.quantity_base,0) quantity_base,u.display_name requester_name,pu.display_name preparing_user_name
        FROM inventory_supply_needs n
        LEFT JOIN inventory_items i ON i.id=n.inventory_item_id
        LEFT JOIN inventory_balances b ON b.inventory_item_id=n.inventory_item_id
        LEFT JOIN users u ON u.id=n.created_by_user_id
        LEFT JOIN users pu ON pu.id=n.preparing_by_user_id
        WHERE n.status='open'
        ORDER BY FIELD(n.source,'staff','manager','low_stock'),COALESCE(n.preparing_at,n.updated_at),n.id")->fetchAll();

    $groups=[];
    foreach($rows as $row){
        $key=supply_purchase_group_key($row);
        if(!isset($groups[$key]))$groups[$key]=[
            'group_key'=>$key,'item_id'=>(int)($row['inventory_item_id']??0),'name'=>(string)$row['item_name_snapshot'],'base_unit'=>(string)$row['base_unit'],
            'quantity_base'=>(int)($row['quantity_base']??0),'unknown'=>(int)($row['inventory_item_id']??0)<1,
            'uncommitted_quantity_base'=>0,'preparing_quantity_base'=>0,'need_ids'=>[],'departments'=>[],'uncommitted_departments'=>[],'preparing_departments'=>[],'requesters'=>[],'notes'=>[],
            'preparing_users'=>[],'preparing_at'=>null,'last_outcome'=>null,
        ];
        $g=&$groups[$key];
        $uncommitted=supply_need_uncommitted($row);
        $preparing=(int)($row['preparing_quantity_base']??0);
        $g['uncommitted_quantity_base']+=$uncommitted;
        $g['preparing_quantity_base']+=$preparing;
        $g['need_ids'][]=(int)$row['id'];
        $dept=(string)$row['department'];
        $g['departments'][$dept]=($g['departments'][$dept]??0)+supply_need_remaining($row);
        $g['uncommitted_departments'][$dept]=($g['uncommitted_departments'][$dept]??0)+$uncommitted;
        $g['preparing_departments'][$dept]=($g['preparing_departments'][$dept]??0)+$preparing;
        if(trim((string)($row['requester_name']??''))!=='')$g['requesters'][(string)$row['requester_name']]=true;
        if(trim((string)($row['note']??''))!=='')$g['notes'][]=(string)$row['note'];
        if(trim((string)($row['preparing_user_name']??''))!=='')$g['preparing_users'][(string)$row['preparing_user_name']]=true;
        $at=(string)($row['preparing_at']??'');if($at!==''&&($g['preparing_at']===null||$at<$g['preparing_at']))$g['preparing_at']=$at;
        if((string)($row['last_outcome']??'')==='unavailable')$g['last_outcome']='unavailable';
        unset($g);
    }
    return array_values($groups);
}

function supply_group_department_summary(array $group, string $mode = 'all'): string
{
    $source=$mode==='uncommitted'?($group['uncommitted_departments']??[]):($mode==='preparing'?($group['preparing_departments']??[]):($group['departments']??[]));
    $parts=[];
    foreach((array)$source as $department=>$quantity){
        if($quantity<1)continue;
        $parts[]=(inventory_department_labels()[$department]??$department).' '.inventory_format_quantity((int)$quantity,(string)$group['base_unit']);
    }
    return implode(' · ',$parts);
}


/** Buyer-facing attention count without exposing Supply table SQL to the panel shell. */
function supply_purchase_attention_count(PDO $pdo): int
{
    $rows = $pdo->query("SELECT inventory_item_id,item_name_snapshot,base_unit FROM inventory_supply_needs WHERE status='open' AND requested_quantity_base>fulfilled_quantity_base")->fetchAll();
    $groups = [];
    foreach ($rows as $row) $groups[supply_group_key_for_need($row)] = true;
    return count($groups);
}
