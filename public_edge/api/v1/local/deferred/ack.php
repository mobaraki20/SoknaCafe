<?php
declare(strict_types=1);
require dirname(__DIR__,4).'/bootstrap.php';
$installationId=public_verify_local_signature();$body=public_json_body();
$requestId=trim((string)($body['request_id']??''));$lease=trim((string)($body['lease_token']??''));$target=(string)($body['state']??'');
if($requestId===''||$lease===''||!in_array($target,SOKNA_DEFERRED_TERMINAL_STATES,true))public_json(['ok'=>false,'error'=>'invalid_ack'],400);
$result=is_array($body['result']??null)?$body['result']:[];
$error=substr(trim((string)($body['error_code']??'')),0,80);
$pdo=public_db();$pdo->beginTransaction();
try{
    $q=$pdo->prepare('SELECT state,lease_token FROM deferred_work WHERE installation_id=? AND request_id=? FOR UPDATE');
    $q->execute([$installationId,$requestId]);$row=$q->fetch();
    if(!$row){$pdo->rollBack();public_json(['ok'=>false,'error'=>'not_found'],404);}
    $current=(string)$row['state'];
    if(sokna_deferred_is_terminal($current)){
        if($current!==$target){$pdo->rollBack();public_json(['ok'=>false,'error'=>'terminal_state_conflict','state'=>$current],409);}
        $pdo->commit();public_json(['ok'=>true,'state'=>$current,'deduplicated'=>true]);
    }
    if($current!=='pending_sync'||!hash_equals((string)($row['lease_token']??''),$lease)){
        $pdo->rollBack();public_json(['ok'=>false,'error'=>'lease_conflict','state'=>$current],409);
    }
    $json=json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $pdo->prepare('UPDATE deferred_work SET state=?,result_json=?,error_code=?,lease_token=NULL,lease_expires_at=NULL WHERE installation_id=? AND request_id=?')
        ->execute([$target,$json,$error!==''?$error:null,$installationId,$requestId]);
    $pdo->commit();
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
public_json(['ok'=>true,'state'=>$target,'deduplicated'=>false]);
