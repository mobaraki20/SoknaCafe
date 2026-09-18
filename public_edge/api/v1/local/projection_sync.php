<?php
declare(strict_types=1); require dirname(__DIR__,3).'/bootstrap.php';
$installationId=public_verify_local_signature(); $body=public_json_body(); $rows=is_array($body['projections']??null)?$body['projections']:[];
$pdo=public_db(); $pdo->beginTransaction(); try{
  $seen=[]; foreach($rows as $row){if(!is_array($row))continue;$id=trim((string)($row['projection_id']??''));$username=trim((string)($row['username']??''));$hash=(string)($row['password_hash']??'');if($id===''||$username===''||$hash==='')continue;$seen[]=$id;$caps=json_encode(is_array($row['capabilities']??null)?$row['capabilities']:[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$areas=json_encode(is_array($row['preparation_areas']??null)?$row['preparation_areas']:[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$stmt=$pdo->prepare('INSERT INTO auth_projections(installation_id,projection_id,username,password_hash,capabilities_json,preparation_areas_json,projection_version,active) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE username=VALUES(username),password_hash=VALUES(password_hash),capabilities_json=VALUES(capabilities_json),preparation_areas_json=VALUES(preparation_areas_json),projection_version=VALUES(projection_version),active=VALUES(active)');$stmt->execute([$installationId,$id,$username,$hash,$caps,$areas,max(1,(int)($row['projection_version']??1)),!empty($row['active'])?1:0]);}
  $pdo->commit();
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
public_json(['ok'=>true,'synced'=>count($seen)]);
