<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/bootstrap.php';
$session=public_session();
$requestId=trim((string)($_GET['request_id']??''));
if($requestId==='')public_json(['ok'=>false,'error'=>'request_id_required'],400);
$stmt=public_db()->prepare('SELECT request_id,kind,actor_projection_id,state,occurred_at,result_json,error_code,created_at,updated_at FROM deferred_work WHERE installation_id=? AND request_id=? LIMIT 1');
$stmt->execute([(string)$session['installation_id'],$requestId]);$row=$stmt->fetch();
if(!$row)public_json(['ok'=>false,'error'=>'not_found'],404);
if(!public_deferred_actor_can_access($session,(string)$row['actor_projection_id']))public_json(['ok'=>false,'error'=>'forbidden'],403);
$row['result']=json_decode((string)($row['result_json']??''),true);unset($row['result_json']);
public_json(['ok'=>true]+$row);
