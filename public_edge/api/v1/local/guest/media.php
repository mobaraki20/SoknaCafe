<?php
declare(strict_types=1);
require dirname(__DIR__,4) . '/bootstrap.php';
$installationId = public_verify_local_signature();
$body = public_json_body();
$sha = strtolower(trim((string)($body['sha256'] ?? '')));
$mime = trim((string)($body['mime'] ?? ''));
$extension = strtolower(trim((string)($body['extension'] ?? '')));
$declaredSize = (int)($body['size'] ?? 0);
$encoded = (string)($body['content_base64'] ?? '');
$allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif','image/svg+xml'=>'svg'];
if (!preg_match('/^[a-f0-9]{64}$/',$sha) || !isset($allowed[$mime]) || $allowed[$mime] !== $extension || $declaredSize < 1 || $declaredSize > 8*1024*1024) {
    public_json(['ok'=>false,'error'=>'invalid_media_metadata'],400);
}
$bytes = base64_decode($encoded,true);
if (!is_string($bytes) || strlen($bytes) !== $declaredSize || !hash_equals($sha,hash('sha256',$bytes))) {
    public_json(['ok'=>false,'error'=>'media_integrity_failed'],422);
}
$base = (string)($publicConfig['app']['storage_dir'] ?? (dirname(__DIR__,4) . '/storage'));
$dir = rtrim($base,DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'guest-media' . DIRECTORY_SEPARATOR . preg_replace('/[^A-Za-z0-9._-]/','_',$installationId);
if (!is_dir($dir) && !mkdir($dir,0750,true) && !is_dir($dir)) public_json(['ok'=>false,'error'=>'media_storage_unavailable'],503);
$path = $dir . DIRECTORY_SEPARATOR . $sha . '.' . $extension;
if (is_file($path)) {
    $existing = hash_file('sha256',$path);
    if (is_string($existing) && hash_equals($sha,$existing)) public_json(['ok'=>true,'sha256'=>$sha,'deduplicated'=>true]);
    public_json(['ok'=>false,'error'=>'media_hash_collision'],409);
}
$tmp = $path . '.tmp.' . bin2hex(random_bytes(6));
if (file_put_contents($tmp,$bytes,LOCK_EX) !== strlen($bytes) || !rename($tmp,$path)) {
    @unlink($tmp); public_json(['ok'=>false,'error'=>'media_write_failed'],503);
}
public_json(['ok'=>true,'sha256'=>$sha,'deduplicated'=>false],201);
