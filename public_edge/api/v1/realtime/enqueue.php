<?php
declare(strict_types=1); require dirname(__DIR__,3).'/bootstrap.php';
$session=public_session(); $body=public_json_body(); $body['actor_projection_id']=(string)$session['projection_id'];
$valid=sokna_relay_validate_realtime_envelope($body); if(!$valid['ok']) public_json(['ok'=>false,'error'=>'invalid_envelope','fields'=>$valid['errors']],400);
if(in_array((string)$body['kind'],['guest_order.submit','waiter_call.create'],true) && !(int)$session['order_intake_enabled']) public_json(['ok'=>false,'error'=>'order_intake_disabled'],409);
$cap=public_capability_for_kind((string)$body['kind']); if(!in_array($cap,$session['capabilities'],true) && !in_array('*',$session['capabilities'],true)) public_json(['ok'=>false,'error'=>'forbidden'],403);
$pdo=public_db(); $installationId=(string)$session['installation_id']; $hash=sokna_relay_request_hash($body); $json=sokna_relay_canonical_json($body); $expires=date('Y-m-d H:i:s',(int)$valid['expires_ts']);
$pdo->beginTransaction(); try{
  $q=$pdo->prepare('SELECT request_hash,state,result_json,error_code FROM realtime_requests WHERE installation_id=? AND request_id=? FOR UPDATE'); $q->execute([$installationId,(string)$body['request_id']]); $existing=$q->fetch();
  if($existing){ if(!hash_equals((string)$existing['request_hash'],$hash)){ $pdo->rollBack(); public_json(['ok'=>false,'error'=>'request_id_conflict'],409);} $pdo->commit(); public_json(['ok'=>true,'state'=>(string)$existing['state'],'deduplicated'=>true,'result'=>json_decode((string)($existing['result_json']??''),true),'error_code'=>(string)($existing['error_code']??'')]); }
  $ins=$pdo->prepare('INSERT INTO realtime_requests(installation_id,request_id,request_hash,kind,actor_projection_id,envelope_json,state,expires_at) VALUES(?,?,?,?,?,?,?,?)'); $ins->execute([$installationId,(string)$body['request_id'],$hash,(string)$body['kind'],(string)$session['projection_id'],$json,'queued',$expires]); $pdo->commit();
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
public_json(['ok'=>true,'state'=>'queued','deduplicated'=>false],202);
