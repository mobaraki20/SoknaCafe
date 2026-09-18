<?php
declare(strict_types=1); require dirname(__DIR__,3).'/bootstrap.php';
$installationId=public_verify_local_signature(); $body=public_json_body(); $telemetry=json_encode(is_array($body['telemetry']??null)?$body['telemetry']:[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$stmt=public_db()->prepare('INSERT INTO installation_heartbeats(installation_id,local_version,runtime_status,telemetry_json,last_seen_at) VALUES(?,?,?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE local_version=VALUES(local_version),runtime_status=VALUES(runtime_status),telemetry_json=VALUES(telemetry_json),last_seen_at=UTC_TIMESTAMP()'); $stmt->execute([$installationId,(string)($body['local_version']??''),(string)($body['runtime_status']??''),$telemetry]); public_json(['ok'=>true]);
