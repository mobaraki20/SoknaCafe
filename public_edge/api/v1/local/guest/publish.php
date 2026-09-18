<?php
declare(strict_types=1);
require dirname(__DIR__,4) . '/bootstrap.php';
$installationId = public_verify_local_signature();
$body = public_json_body();
$revisionId = trim((string)($body['revision_id'] ?? ''));
$contentHash = strtolower(trim((string)($body['content_hash'] ?? '')));
$generatedAt = trim((string)($body['generated_at'] ?? ''));
$snapshot = is_array($body['snapshot'] ?? null) ? $body['snapshot'] : [];
$manifest = is_array($body['media_manifest'] ?? null) ? $body['media_manifest'] : [];
if (!preg_match('/^guest-[a-f0-9]{32}$/',$revisionId) || !preg_match('/^[a-f0-9]{64}$/',$contentHash) || ($snapshot['format'] ?? '') !== 'sokna-guest-snapshot-v1') {
    public_json(['ok'=>false,'error'=>'invalid_guest_revision'],400);
}
$recomputed = sokna_relay_request_hash(['format'=>'sokna-guest-snapshot-v1','snapshot'=>$snapshot,'media_manifest'=>$manifest]);
if (!hash_equals($contentHash,$recomputed) || !hash_equals($revisionId,'guest-'.substr($recomputed,0,32))) {
    public_json(['ok'=>false,'error'=>'guest_revision_integrity_failed'],422);
}
$storage = (string)($publicConfig['app']['storage_dir'] ?? (dirname(__DIR__,4) . '/storage'));
$mediaDir = rtrim($storage,DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'guest-media' . DIRECTORY_SEPARATOR . preg_replace('/[^A-Za-z0-9._-]/','_',$installationId);
foreach ($manifest as $source => $meta) {
    if (!is_array($meta)) public_json(['ok'=>false,'error'=>'invalid_media_manifest'],400);
    $sha = strtolower((string)($meta['sha256'] ?? '')); $ext = strtolower((string)($meta['extension'] ?? ''));
    if (!preg_match('/^[a-f0-9]{64}$/',$sha) || !preg_match('/^(jpg|png|webp|gif|svg)$/',$ext)) public_json(['ok'=>false,'error'=>'invalid_media_manifest'],400);
    $path = $mediaDir . DIRECTORY_SEPARATOR . $sha . '.' . $ext;
    if (!is_file($path) || !hash_equals($sha,(string)hash_file('sha256',$path))) {
        public_json(['ok'=>false,'error'=>'missing_media','source'=>(string)$source,'sha256'=>$sha],409);
    }
}
$pdo = public_db(); $pdo->beginTransaction();
try {
    $existing = $pdo->prepare('SELECT content_hash FROM guest_publish_revisions WHERE installation_id=? AND revision_id=? FOR UPDATE');
    $existing->execute([$installationId,$revisionId]); $old = $existing->fetchColumn();
    if ($old !== false && !hash_equals((string)$old,$contentHash)) { $pdo->rollBack(); public_json(['ok'=>false,'error'=>'revision_id_conflict'],409); }
    if ($old === false) {
        $json = static fn(array $value): string => json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $ins = $pdo->prepare('INSERT INTO guest_publish_revisions(installation_id,revision_id,content_hash,snapshot_json,media_manifest_json,generated_at) VALUES(?,?,?,?,?,?)');
        $ins->execute([$installationId,$revisionId,$contentHash,$json($snapshot),$json($manifest),date('Y-m-d H:i:s',strtotime($generatedAt) ?: time())]);
    }
    $pointer = $pdo->prepare('INSERT INTO guest_active_revisions(installation_id,revision_id,activated_at) VALUES(?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE revision_id=VALUES(revision_id),activated_at=UTC_TIMESTAMP()');
    $pointer->execute([$installationId,$revisionId]); $pdo->commit();
} catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
public_json(['ok'=>true,'revision_id'=>$revisionId,'active'=>true,'deduplicated'=>$old !== false]);
