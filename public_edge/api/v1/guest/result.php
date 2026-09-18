<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/bootstrap.php';

$installationId=trim((string)($_GET['installation_id']??''));
$requestId=trim((string)($_GET['request_id']??''));
$clientToken=trim((string)($_GET['client_token']??''));
if($installationId===''||$requestId===''||strlen($clientToken)<16) public_json(['ok'=>false,'error'=>'invalid_result_lookup'],400);

$pdo=public_db();
$pdo->prepare("UPDATE realtime_requests SET state='expired',lease_token_hash=NULL,lease_expires_at=NULL WHERE installation_id=? AND request_id=? AND state='queued' AND expires_at<=UTC_TIMESTAMP()")->execute([$installationId,$requestId]);
$stmt=$pdo->prepare('SELECT state,envelope_json,result_json,error_code,updated_at FROM realtime_requests WHERE installation_id=? AND request_id=? LIMIT 1');
$stmt->execute([$installationId,$requestId]);
$row=$stmt->fetch();
if(!$row) public_json(['ok'=>false,'error'=>'not_found'],404);
$envelope=json_decode((string)$row['envelope_json'],true);
$payload=is_array($envelope['payload']??null)?$envelope['payload']:[];
if(!isset($payload['client_token'])||!hash_equals((string)$payload['client_token'],$clientToken)) public_json(['ok'=>false,'error'=>'not_found'],404);
$result=json_decode((string)($row['result_json']??''),true);
public_json([
    'ok'=>true,
    'state'=>(string)$row['state'],
    'terminal'=>sokna_relay_is_terminal((string)$row['state']),
    'result'=>is_array($result)?$result:[],
    'error_code'=>(string)($row['error_code']??''),
    'updated_at'=>(string)$row['updated_at'],
]);
