<?php
declare(strict_types=1);
require dirname(__DIR__,4).'/bootstrap.php';
$installationId=public_verify_local_signature();$body=public_json_body();
$requestId=trim((string)($body['request_id']??''));$target=(string)($body['state']??'');
if($requestId===''||!in_array($target,['committed','rejected'],true))public_json(['ok'=>false,'error'=>'invalid_reconcile'],400);
$result=is_array($body['result']??null)?$body['result']:[];
$error=substr(trim((string)($body['error_code']??'')),0,80);
$pdo=public_db();$pdo->beginTransaction();
try{
    $q=$pdo->prepare('SELECT state FROM deferred_work WHERE installation_id=? AND request_id=? FOR UPDATE');
    $q->execute([$installationId,$requestId]);$current=(string)($q->fetchColumn()?:'');
    if($current===''){ $pdo->rollBack();public_json(['ok'=>false,'error'=>'not_found'],404); }
    if($current===$target){$pdo->commit();public_json(['ok'=>true,'state'=>$target,'deduplicated'=>true]);}
    if($current!=='needs_review'){$pdo->rollBack();public_json(['ok'=>false,'error'=>'state_conflict','state'=>$current],409);}
    $json=json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $pdo->prepare('UPDATE deferred_work SET state=?,result_json=?,error_code=?,lease_token=NULL,lease_expires_at=NULL WHERE installation_id=? AND request_id=?')
        ->execute([$target,$json,$error!==''?$error:null,$installationId,$requestId]);
    $pdo->commit();
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
public_json(['ok'=>true,'state'=>$target,'deduplicated'=>false]);
