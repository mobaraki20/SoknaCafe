<?php
declare(strict_types=1); require dirname(__DIR__,3).'/bootstrap.php';
$session=public_session(); $requestId=trim((string)($_GET['request_id']??'')); if($requestId==='') public_json(['ok'=>false,'error'=>'request_id_required'],400);
$stmt=public_db()->prepare('SELECT actor_projection_id,state,result_json,error_code,updated_at FROM realtime_requests WHERE installation_id=? AND request_id=? LIMIT 1'); $stmt->execute([(string)$session['installation_id'],$requestId]); $row=$stmt->fetch(); if(!$row) public_json(['ok'=>false,'error'=>'not_found'],404);
if(!in_array('*',$session['capabilities']??[],true) && !hash_equals((string)$session['projection_id'],(string)$row['actor_projection_id'])) public_json(['ok'=>false,'error'=>'forbidden'],403);
public_json(['ok'=>true,'state'=>(string)$row['state'],'terminal'=>sokna_relay_is_terminal((string)$row['state']),'result'=>json_decode((string)($row['result_json']??''),true),'error_code'=>(string)($row['error_code']??''),'updated_at'=>(string)$row['updated_at']]);
