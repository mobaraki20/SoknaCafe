#!/usr/bin/env php
<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only\n"); exit(64); }
require dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/relay_client.php';
require_once dirname(__DIR__) . '/includes/relay_dispatch.php';

$cfg = sokna_relay_config();
if (empty($cfg['enabled'])) { fwrite(STDOUT, "Relay disabled.\n"); exit(0); }

try {
    $bind = sokna_relay_http('POST', '/api/v1/local/bind.php', ['display_name'=>(string)(config()['app']['name'] ?? 'SOKNA Cafe')]);
    if (empty($bind['ok'])) throw new RuntimeException('Relay bind failed: ' . (string)($bind['error'] ?? 'unknown'));
    $health = function_exists('sokna_runtime_health_snapshot') ? sokna_runtime_health_snapshot() : [];
    sokna_relay_http('POST', '/api/v1/local/heartbeat.php', [
        'local_version'=>trim((string)@file_get_contents(dirname(__DIR__) . '/VERSION.txt')),
        'runtime_status'=>(string)($health['status'] ?? 'worker'),
        'telemetry'=>['healthy'=>(bool)($health['healthy'] ?? true),'stale'=>(bool)($health['stale'] ?? false)],
    ]);
    $claim = sokna_relay_http('POST', '/api/v1/local/claim.php', ['lease_seconds'=>20]);
    if (empty($claim['ok'])) {
        if (($claim['error'] ?? '') === 'empty_queue') { fwrite(STDOUT, "Relay queue empty.\n"); exit(0); }
        throw new RuntimeException('Relay claim failed: ' . (string)($claim['error'] ?? 'unknown'));
    }
    $request = is_array($claim['request'] ?? null) ? $claim['request'] : [];
    $leaseToken = (string)($claim['lease_token'] ?? '');
    if (!$request || $leaseToken === '') throw new RuntimeException('Relay claim payload is incomplete.');
    $result = sokna_relay_process_claim(db(), ['envelope'=>$request]);
    $ack = sokna_relay_http('POST', '/api/v1/local/ack.php', [
        'request_id'=>(string)($request['request_id'] ?? ''),
        'lease_token'=>$leaseToken,
        'state'=>(string)$result['state'],
        'error_code'=>(string)($result['error_code'] ?? ''),
        'result'=>is_array($result['result'] ?? null) ? $result['result'] : [],
    ]);
    if (empty($ack['ok'])) throw new RuntimeException('Relay ACK failed: ' . (string)($ack['error'] ?? 'unknown'));
    fwrite(STDOUT, json_encode(['processed'=>(string)($request['request_id'] ?? ''),'state'=>$result['state']], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . PHP_EOL);
} catch (Throwable $e) {
    if (function_exists('sokna_log_event')) sokna_log_event('warning','relay.worker_failed',['message'=>$e->getMessage()]);
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(2);
}
