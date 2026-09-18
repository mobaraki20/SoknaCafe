<?php
declare(strict_types=1); require dirname(__DIR__,3).'/bootstrap.php';
$installationId=public_verify_local_signature(); $body=public_json_body(); $leaseSeconds=min(60,max(5,(int)($body['lease_seconds']??20))); $pdo=public_db();
$enabled=$pdo->prepare('SELECT active,remote_enabled FROM installations WHERE installation_id=? LIMIT 1');$enabled->execute([$installationId]);$flags=$enabled->fetch();
if(!$flags||!(int)$flags['active']||!(int)$flags['remote_enabled']) public_json(['ok'=>false,'error'=>'remote_disabled'],409);
$pdo->beginTransaction(); try{
  $pdo->prepare("UPDATE realtime_requests SET state='expired',lease_token_hash=NULL,lease_expires_at=NULL WHERE installation_id=? AND state='queued' AND expires_at<=UTC_TIMESTAMP()")->execute([$installationId]);
  $stmt=$pdo->prepare("SELECT id,envelope_json FROM realtime_requests WHERE installation_id=? AND ((state='queued' AND expires_at>UTC_TIMESTAMP()) OR (state='claimed' AND lease_expires_at<UTC_TIMESTAMP())) ORDER BY id ASC LIMIT 1 FOR UPDATE"); $stmt->execute([$installationId]); $row=$stmt->fetch();
  if(!$row){$pdo->commit();public_json(['ok'=>false,'error'=>'empty_queue'],404);} $lease=bin2hex(random_bytes(24));
  $up=$pdo->prepare("UPDATE realtime_requests SET state='claimed',lease_token_hash=?,lease_expires_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND),claimed_at=UTC_TIMESTAMP() WHERE id=?"); $up->execute([hash('sha256',$lease),$leaseSeconds,(int)$row['id']]); $pdo->commit();
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
public_json(['ok'=>true,'request'=>json_decode((string)$row['envelope_json'],true),'lease_token'=>$lease,'lease_seconds'=>$leaseSeconds]);
