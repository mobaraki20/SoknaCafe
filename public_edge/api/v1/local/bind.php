<?php
declare(strict_types=1); require dirname(__DIR__,3).'/bootstrap.php';
$installationId=public_verify_local_signature(); $body=public_json_body(); $name=trim((string)($body['display_name']??''));
$stmt=public_db()->prepare('INSERT INTO installations(installation_id,display_name,active,remote_enabled,order_intake_enabled) VALUES(?,?,1,1,1) ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),active=1'); $stmt->execute([$installationId,$name]);
public_json(['ok'=>true,'installation_id'=>$installationId]);
