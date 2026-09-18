<?php
declare(strict_types=1);

function sokna_relay_projection_capabilities(PDO $pdo, array $user): array
{
    if ((string)($user['role'] ?? '') === 'admin') return ['*'];
    $stmt=$pdo->prepare('SELECT capability FROM user_capabilities WHERE user_id=? AND enabled=1 ORDER BY capability');
    $stmt->execute([(int)$user['id']]);
    $local=array_map('strval',$stmt->fetchAll(PDO::FETCH_COLUMN));
    $public=[];
    if(in_array('orders_floor',$local,true)){
        array_push($public,'orders.mutate','orders.table_draft','orders.read','operations.read');
    }
    if(in_array('cashier_accounts',$local,true)){
        array_push($public,'finance.settle','finance.read','orders.read','operations.read');
    }
    if(in_array('preparation',$local,true)){
        array_push($public,'preparation.mutate','preparation.read');
    }
    if(in_array('shift_supervision',$local,true)){
        array_push($public,'operations.read','preparation.monitor');
    }
    if(array_intersect(['inventory_view','inventory_operations','inventory_finalize','inventory_manage'],$local)){
        $public[]='inventory.read';
    }
    if(in_array('inventory_cost_view',$local,true)){
        array_push($public,'inventory.read','inventory.cost.read');
    }
    return array_values(array_unique($public));
}
function sokna_relay_projection_areas(PDO $pdo, int $userId): array
{
    $stmt=$pdo->prepare("SELECT area_key FROM user_preparation_areas WHERE user_id=? AND area_key IN ('kitchen','bar') ORDER BY area_key");
    $stmt->execute([$userId]);
    return array_values(array_map('strval',$stmt->fetchAll(PDO::FETCH_COLUMN)));
}

function sokna_relay_auth_projections(PDO $pdo): array
{
    $rows=$pdo->query('SELECT id,username,password_hash,display_name,role,active,updated_at FROM users WHERE active=1 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $out=[];
    foreach($rows as $row){
        $id=(int)$row['id'];
        $out[]=[
            'projection_id'=>'user:'.$id,
            'username'=>(string)$row['username'],
            'display_name'=>(string)($row['display_name'] ?? $row['username']),
            'role'=>(string)$row['role'],
            'password_hash'=>(string)$row['password_hash'],
            'capabilities'=>sokna_relay_projection_capabilities($pdo,$row),
            'preparation_areas'=>sokna_relay_projection_areas($pdo,$id),
            'projection_version'=>max(1,strtotime((string)$row['updated_at'])?:1),
            'active'=>true,
        ];
    }
    return $out;
}
