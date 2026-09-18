<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/bootstrap.php';
$session=public_session();
$pdo=public_db();
$stmt=$pdo->prepare('SELECT model_key,source_version,generated_at,last_sync_at FROM remote_read_models WHERE installation_id=? ORDER BY model_key');
$stmt->execute([(string)$session['installation_id']]);$models=[];
foreach($stmt->fetchAll() as $row){
    $key=(string)$row['model_key'];$required=public_remote_model_capability($key);
    if($required===null||!public_session_has_capability($session,$required))continue;
    $models[]=[
        'model_key'=>$key,'source_version'=>(string)$row['source_version'],
        'generated_at'=>(string)$row['generated_at'],'last_sync_at'=>(string)$row['last_sync_at'],
        'stale'=>(strtotime((string)$row['last_sync_at'])?:0)<time()-90,
    ];
}
public_json([
    'ok'=>true,
    'user'=>[
        'projection_id'=>(string)$session['projection_id'],'display_name'=>(string)($session['display_name']??''),
        'role'=>(string)($session['role']??''),'capabilities'=>$session['capabilities'],
        'preparation_areas'=>$session['preparation_areas'],
    ],
    'connectivity'=>public_remote_connectivity((string)$session['installation_id']),
    'models'=>$models,
]);
