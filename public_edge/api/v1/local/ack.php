<?php
declare(strict_types=1); require dirname(__DIR__,3).'/bootstrap.php';
$installationId=public_verify_local_signature(); $body=public_json_body(); $requestId=trim((string)($body['request_id']??'')); $lease=(string)($body['lease_token']??''); $state=(string)($body['state']??'');
if($requestId===''||$lease===''||!sokna_relay_is_terminal($state)) public_json(['ok'=>false,'error'=>'invalid_ack'],400);
$pdo=public_db(); $pdo->beginTransaction(); try{
  $stmt=$pdo->prepare('SELECT state,lease_token_hash,result_json,error_code FROM realtime_requests WHERE installation_id=? AND request_id=? FOR UPDATE'); $stmt->execute([$installationId,$requestId]); $row=$stmt->fetch(); if(!$row){$pdo->rollBack();public_json(['ok'=>false,'error'=>'not_found'],404);} if(sokna_relay_is_terminal((string)$row['state'])){$pdo->commit();public_json(['ok'=>true,'state'=>(string)$row['state'],'deduplicated'=>true]);}
  if((string)$row['state']!=='claimed'||!hash_equals((string)$row['lease_token_hash'],hash('sha256',$lease))){$pdo->rollBack();public_json(['ok'=>false,'error'=>'lease_conflict'],409);} $result=json_encode(is_array($body['result']??null)?$body['result']:[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  $up=$pdo->prepare('UPDATE realtime_requests SET state=?,result_json=?,error_code=?,lease_token_hash=NULL,lease_expires_at=NULL WHERE installation_id=? AND request_id=?'); $up->execute([$state,$result,(string)($body['error_code']??''),$installationId,$requestId]); $pdo->commit();
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
public_json(['ok'=>true,'state'=>$state,'deduplicated'=>false]);
