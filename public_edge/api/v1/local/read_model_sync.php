<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/bootstrap.php';

$installationId=public_verify_local_signature();
$body=public_json_body();
$models=is_array($body['models']??null)?$body['models']:[];
$allowed=['operations','preparation','inventory','inventory_cost','reports'];
$pdo=public_db();$synced=0;$unchanged=0;
$pdo->beginTransaction();
try{
    foreach($models as $model){
        if(!is_array($model))continue;
        $format=(string)($model['format']??'');$key=trim((string)($model['model_key']??''));
        $version=strtolower(trim((string)($model['source_version']??'')));$generated=(string)($model['generated_at']??'');
        $payload=is_array($model['payload']??null)?$model['payload']:null;
        if($format!=='sokna-remote-read-v1'||!in_array($key,$allowed,true)||!preg_match('/^[a-f0-9]{64}$/',$version)||$payload===null||strtotime($generated)===false)continue;
        $expected=hash('sha256',sokna_relay_canonical_json($payload));
        if(!hash_equals($expected,$version))continue;
        $check=$pdo->prepare('SELECT source_version FROM remote_read_models WHERE installation_id=? AND model_key=? FOR UPDATE');
        $check->execute([$installationId,$key]);$old=$check->fetchColumn();
        if($old!==false&&hash_equals((string)$old,$version)){
            $pdo->prepare('UPDATE remote_read_models SET last_sync_at=UTC_TIMESTAMP() WHERE installation_id=? AND model_key=?')->execute([$installationId,$key]);
            $unchanged++;continue;
        }
        $json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $stmt=$pdo->prepare('INSERT INTO remote_read_models(installation_id,model_key,source_version,payload_json,generated_at,last_sync_at) VALUES(?,?,?,?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE source_version=VALUES(source_version),payload_json=VALUES(payload_json),generated_at=VALUES(generated_at),last_sync_at=UTC_TIMESTAMP()');
        $stmt->execute([$installationId,$key,$version,$json,date('Y-m-d H:i:s',strtotime($generated))]);$synced++;
    }
    $pdo->commit();
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
public_json(['ok'=>true,'synced'=>$synced,'unchanged'=>$unchanged]);
