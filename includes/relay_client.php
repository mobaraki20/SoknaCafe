<?php
declare(strict_types=1);
require_once __DIR__ . '/relay_protocol.php';

function sokna_relay_config(): array
{
    $cfg = function_exists('config') ? config() : [];
    $relay = is_array($cfg['relay'] ?? null) ? $cfg['relay'] : [];
    return $relay + ['enabled'=>false,'public_base_url'=>'','installation_id'=>'','shared_secret'=>'','timeout_seconds'=>8];
}

function sokna_relay_http(string $method, string $path, array $payload = []): array
{
    $cfg = sokna_relay_config();
    if (empty($cfg['enabled'])) return ['ok'=>false,'disabled'=>true];
    $base = rtrim((string)$cfg['public_base_url'], '/');
    $installationId = trim((string)$cfg['installation_id']);
    $secret = (string)$cfg['shared_secret'];
    if ($base === '' || $installationId === '' || $secret === '') throw new RuntimeException('Relay configuration is incomplete.');
    $body = $payload ? sokna_relay_canonical_json($payload) : '{}';
    $timestamp = (string)time();
    $nonce = bin2hex(random_bytes(16));
    $signature = sokna_relay_sign($secret, $method, $path, $timestamp, $nonce, $body);
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-Sokna-Installation: ' . $installationId,
        'X-Sokna-Timestamp: ' . $timestamp,
        'X-Sokna-Nonce: ' . $nonce,
        'X-Sokna-Signature: ' . $signature,
        'X-Correlation-Id: ' . (function_exists('sokna_correlation_id') ? sokna_correlation_id() : bin2hex(random_bytes(8))),
    ];
    $context = stream_context_create(['http'=>[
        'method'=>strtoupper($method),
        'header'=>implode("\r\n", $headers),
        'content'=>$body,
        'timeout'=>max(2,(int)$cfg['timeout_seconds']),
        'ignore_errors'=>true,
    ]]);
    $raw = @file_get_contents($base . '/' . ltrim($path, '/'), false, $context);
    $status = 0;
    foreach (($http_response_header ?? []) as $line) if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m)) $status = (int)$m[1];
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($decoded)) $decoded = ['ok'=>false,'error'=>'invalid_public_response'];
    $decoded['_http_status'] = $status;
    return $decoded;
}
