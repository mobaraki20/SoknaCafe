<?php
declare(strict_types=1);

function sokna_relay_projection_capabilities(PDO $pdo, array $user): array
{
    if ((string)($user['role'] ?? '') === 'admin') return ['*'];
    $stmt=$pdo->prepare('SELECT capability FROM user_capabilities WHERE user_id=? AND enabled=1 ORDER BY capability');
    $stmt->execute([(int)$user['id']]);
    $local=array_map('strval',$stmt->fetchAll(PDO::FETCH_COLUMN));
    $public=[];
    if(in_array('orders_floor',$local,true)){$public[]='orders.mutate';$public[]='orders.table_draft';}
    if(in_array('cashier_accounts',$local,true))$public[]='finance.settle';
    if(in_array('preparation',$local,true))$public[]='preparation.mutate';
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
    $rows=$pdo->query('SELECT id,username,password_hash,role,active,updated_at FROM users WHERE active=1 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $out=[];
    foreach($rows as $row){
        $id=(int)$row['id'];
        $out[]=[
            'projection_id'=>'user:'.$id,
            'username'=>(string)$row['username'],
            'password_hash'=>(string)$row['password_hash'],
            'capabilities'=>sokna_relay_projection_capabilities($pdo,$row),
            'preparation_areas'=>sokna_relay_projection_areas($pdo,$id),
            'projection_version'=>max(1,strtotime((string)$row['updated_at'])?:1),
            'active'=>true,
        ];
    }
    return $out;
}
