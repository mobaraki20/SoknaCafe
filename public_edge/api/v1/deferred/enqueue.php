<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/bootstrap.php';

$session=public_session();
$body=public_json_body();
$body['actor_projection_id']=(string)$session['projection_id'];
$validation=sokna_deferred_validate_envelope($body);
if(!$validation['ok'])public_json(['ok'=>false,'error'=>'invalid_envelope','fields'=>$validation['errors']],400);
$kind=(string)$body['kind'];
$required=public_deferred_capability_for_kind($kind);
if(!public_session_has_capability($session,$required))public_json(['ok'=>false,'error'=>'forbidden'],403);

$requestId=(string)$body['request_id'];
$hash=sokna_relay_request_hash($body);
$json=sokna_relay_canonical_json($body);
$occurred=date('Y-m-d H:i:s',(int)$validation['occurred_ts']);
$pdo=public_db();$pdo->beginTransaction();
try{
    $q=$pdo->prepare('SELECT request_hash,state,result_json,error_code FROM deferred_work WHERE installation_id=? AND request_id=? FOR UPDATE');
    $q->execute([(string)$session['installation_id'],$requestId]);$existing=$q->fetch();
    if($existing){
        if(!hash_equals((string)$existing['request_hash'],$hash)){
            $pdo->rollBack();public_json(['ok'=>false,'error'=>'request_id_conflict'],409);
        }
        $pdo->commit();
        public_json([
            'ok'=>true,'state'=>(string)$existing['state'],'deduplicated'=>true,
            'result'=>json_decode((string)($existing['result_json']??''),true),
            'error_code'=>(string)($existing['error_code']??''),
        ]);
    }
    $ins=$pdo->prepare("INSERT INTO deferred_work(installation_id,request_id,request_hash,kind,actor_projection_id,envelope_json,state,occurred_at) VALUES(?,?,?,?,?,?,'pending_sync',?)");
    $ins->execute([(string)$session['installation_id'],$requestId,$hash,$kind,(string)$session['projection_id'],$json,$occurred]);
    $pdo->commit();
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
public_json(['ok'=>true,'state'=>'pending_sync','deduplicated'=>false],202);
