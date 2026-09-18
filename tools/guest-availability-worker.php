#!/usr/bin/env php
<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only\n"); exit(64); }
require dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/guest_publish.php';
$cfg = sokna_relay_config();
if (empty($cfg['enabled'])) { fwrite(STDOUT, "Relay disabled.\n"); exit(0); }
try {
    $bind = sokna_relay_http('POST','/api/v1/local/bind.php',['display_name'=>setting('cafe_name','SOKNA Cafe')]);
    if (empty($bind['ok'])) throw new RuntimeException('Relay bind failed: ' . (string)($bind['error'] ?? 'unknown'));
    $payload = guest_availability_payload(db());
    $result = sokna_relay_http('POST', '/api/v1/local/guest/availability.php', $payload);
    if (empty($result['ok'])) throw new RuntimeException('Availability sync failed: ' . (string)($result['error'] ?? 'unknown'));
    fwrite(STDOUT, json_encode(['ok'=>true,'version'=>$payload['version']], JSON_UNESCAPED_SLASHES) . PHP_EOL);
} catch (Throwable $e) {
    if (function_exists('sokna_log_event')) sokna_log_event('warning','guest.availability_sync_failed',['message'=>$e->getMessage()]);
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(2);
}
