<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/bootstrap.php';

$session=public_session();
$key=trim((string)($_GET['model']??''));
$required=public_remote_model_capability($key);
if($required===null)public_json(['ok'=>false,'error'=>'unknown_model'],404);
if(!public_session_has_capability($session,$required))public_json(['ok'=>false,'error'=>'forbidden'],403);

$stmt=public_db()->prepare('SELECT source_version,payload_json,generated_at,last_sync_at FROM remote_read_models WHERE installation_id=? AND model_key=? LIMIT 1');
$stmt->execute([(string)$session['installation_id'],$key]);$row=$stmt->fetch();
if(!$row)public_json(['ok'=>false,'error'=>'model_unavailable'],404);
$payload=json_decode((string)$row['payload_json'],true);$payload=is_array($payload)?$payload:[];
if($key==='preparation')$payload=public_remote_filter_preparation($payload,$session);

$lastSync=strtotime((string)$row['last_sync_at'])?:0;
$heartbeat=public_remote_connectivity((string)$session['installation_id']);
$stale=$lastSync<time()-90||!$heartbeat['local_fresh'];
public_json([
    'ok'=>true,'model_key'=>$key,'source_version'=>(string)$row['source_version'],
    'generated_at'=>(string)$row['generated_at'],'last_sync_at'=>(string)$row['last_sync_at'],
    'stale'=>$stale,'connectivity'=>$heartbeat,'payload'=>$payload,
]);
