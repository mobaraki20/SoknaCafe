<?php
declare(strict_types=1);
require dirname(__DIR__,4).'/bootstrap.php';
$installationId=public_verify_local_signature();$body=public_json_body();
$leaseSeconds=max(10,min(120,(int)($body['lease_seconds']??30)));
$pdo=public_db();$pdo->beginTransaction();
try{
    $install=$pdo->prepare('SELECT active,remote_enabled FROM installations WHERE installation_id=? FOR UPDATE');
    $install->execute([$installationId]);$state=$install->fetch();
    if(!$state||!(int)$state['active']){ $pdo->rollBack();public_json(['ok'=>false,'error'=>'installation_inactive'],409); }
    if(!(int)$state['remote_enabled']){ $pdo->rollBack();public_json(['ok'=>false,'error'=>'remote_disabled'],409); }
    $pdo->prepare("UPDATE deferred_work SET lease_token=NULL,lease_expires_at=NULL WHERE installation_id=? AND state='pending_sync' AND lease_expires_at IS NOT NULL AND lease_expires_at<UTC_TIMESTAMP()")->execute([$installationId]);
    $q=$pdo->prepare("SELECT * FROM deferred_work WHERE installation_id=? AND state='pending_sync' AND lease_token IS NULL ORDER BY occurred_at,created_at,request_id LIMIT 1 FOR UPDATE");
    $q->execute([$installationId]);$row=$q->fetch();
    if(!$row){$pdo->commit();public_json(['ok'=>false,'error'=>'empty_queue'],404);}
    $lease=bin2hex(random_bytes(32));
    $pdo->prepare("UPDATE deferred_work SET lease_token=?,lease_expires_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND),claimed_at=UTC_TIMESTAMP(),attempt_count=attempt_count+1 WHERE installation_id=? AND request_id=? AND state='pending_sync'")
        ->execute([$lease,$leaseSeconds,$installationId,(string)$row['request_id']]);
    $pdo->commit();
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
$envelope=json_decode((string)$row['envelope_json'],true);
public_json(['ok'=>true,'lease_token'=>$lease,'lease_seconds'=>$leaseSeconds,'request'=>is_array($envelope)?$envelope:[]]);
