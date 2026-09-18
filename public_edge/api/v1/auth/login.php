<?php
declare(strict_types=1); require dirname(__DIR__,3).'/bootstrap.php';
$body=public_json_body(); $installationId=trim((string)($body['installation_id']??'')); $username=trim((string)($body['username']??'')); $password=(string)($body['password']??'');
if($installationId===''||$username===''||$password==='') public_json(['ok'=>false,'error'=>'invalid_credentials'],400);
$pdo=public_db(); $stmt=$pdo->prepare('SELECT p.projection_id,p.display_name,p.role,p.password_hash,p.capabilities_json,p.preparation_areas_json FROM auth_projections p JOIN installations i ON i.installation_id=p.installation_id WHERE p.installation_id=? AND p.username=? AND p.active=1 AND i.active=1 AND i.remote_enabled=1 LIMIT 1'); $stmt->execute([$installationId,$username]); $row=$stmt->fetch();
if(!$row||!password_verify($password,(string)$row['password_hash'])) public_json(['ok'=>false,'error'=>'invalid_credentials'],401);
$token=bin2hex(random_bytes(32)); $ttl=max(900,(int)($publicConfig['app']['session_ttl_seconds']??28800));
$ins=$pdo->prepare('INSERT INTO public_sessions(installation_id,projection_id,token_hash,expires_at) VALUES(?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND))'); $ins->execute([$installationId,(string)$row['projection_id'],hash('sha256',$token),$ttl]);
public_json(['ok'=>true,'token'=>$token,'expires_in'=>$ttl,'projection_id'=>(string)$row['projection_id'],'display_name'=>(string)($row['display_name']??''),'role'=>(string)($row['role']??''),'capabilities'=>json_decode((string)$row['capabilities_json'],true)?:[],'preparation_areas'=>json_decode((string)($row['preparation_areas_json']??'[]'),true)?:[]]);
