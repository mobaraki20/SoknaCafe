<?php
declare(strict_types=1);
require dirname(__DIR__,4) . '/bootstrap.php';
$installationId = public_verify_local_signature();
$body = public_json_body();
$generatedAt = trim((string)($body['generated_at'] ?? ''));
$version = strtolower(trim((string)($body['version'] ?? '')));
$items = $body['items'] ?? null; $acceptance = $body['order_acceptance'] ?? null;
if (!is_array($items) || !is_array($acceptance) || !preg_match('/^[a-f0-9]{64}$/',$version) || strtotime($generatedAt) === false) {
    public_json(['ok'=>false,'error'=>'invalid_availability_payload'],400);
}
$canonical = $body; unset($canonical['version']);
if (!hash_equals($version,hash('sha256',sokna_relay_canonical_json($canonical)))) public_json(['ok'=>false,'error'=>'availability_integrity_failed'],422);
$json = json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
$stmt = public_db()->prepare('INSERT INTO guest_availability_state(installation_id,version,payload_json,generated_at,last_sync_at) VALUES(?,?,?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE version=VALUES(version),payload_json=VALUES(payload_json),generated_at=VALUES(generated_at),last_sync_at=UTC_TIMESTAMP()');
$stmt->execute([$installationId,$version,$json,date('Y-m-d H:i:s',strtotime($generatedAt))]);
public_json(['ok'=>true,'version'=>$version]);
