<?php
declare(strict_types=1);

const SOKNA_RELAY_PROTOCOL_VERSION = 'sokna-relay-v1';
const SOKNA_RELAY_TERMINAL_STATES = ['committed','rejected','expired','cancelled','unknown_review'];
const SOKNA_RELAY_REALTIME_KINDS = [
    'guest_order.submit',
    'guest_order.list',
    'guest_order.status',
    'waiter_call.create',
    'waiter_call.status',
    'waiter_call.cancel',
    'order.edit',
    'order.cancel',
    'settlement.commit',
    'preparation.mutate',
    'table_draft.create',
    'table_draft.edit',
    'table_draft.finalize',
    'table_draft.cancel',
];

function sokna_relay_is_list(array $value): bool
{
    if (function_exists('array_is_list')) return array_is_list($value);
    $i = 0;
    foreach ($value as $key => $_) { if ($key !== $i++) return false; }
    return true;
}

function sokna_relay_normalize(mixed $value): mixed
{
    if (!is_array($value)) return $value;
    if (sokna_relay_is_list($value)) return array_map('sokna_relay_normalize', $value);
    ksort($value, SORT_STRING);
    foreach ($value as $key => $child) $value[$key] = sokna_relay_normalize($child);
    return $value;
}

function sokna_relay_canonical_json(array $value): string
{
    $json = json_encode(sokna_relay_normalize($value), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION);
    if (!is_string($json)) throw new RuntimeException('Relay canonical JSON encoding failed.');
    return $json;
}

function sokna_relay_request_hash(array $envelope): string
{
    return hash('sha256', sokna_relay_canonical_json($envelope));
}

function sokna_relay_signature_base(string $method, string $path, string $timestamp, string $nonce, string $body): string
{
    return implode("\n", [
        SOKNA_RELAY_PROTOCOL_VERSION,
        strtoupper(trim($method)),
        '/' . ltrim($path, '/'),
        trim($timestamp),
        trim($nonce),
        hash('sha256', $body),
    ]);
}

function sokna_relay_sign(string $secret, string $method, string $path, string $timestamp, string $nonce, string $body): string
{
    if ($secret === '') throw new InvalidArgumentException('Relay shared secret is empty.');
    return hash_hmac('sha256', sokna_relay_signature_base($method, $path, $timestamp, $nonce, $body), $secret);
}

function sokna_relay_verify_signature(string $secret, string $method, string $path, string $timestamp, string $nonce, string $body, string $signature, int $clockSkewSeconds = 300): bool
{
    if ($secret === '' || $signature === '' || $nonce === '') return false;
    $ts = ctype_digit($timestamp) ? (int)$timestamp : (strtotime($timestamp) ?: 0);
    if ($ts <= 0 || abs(time() - $ts) > max(30, $clockSkewSeconds)) return false;
    return hash_equals(sokna_relay_sign($secret, $method, $path, $timestamp, $nonce, $body), strtolower(trim($signature)));
}

function sokna_relay_parse_time(string $value): int
{
    $ts = strtotime($value);
    return $ts === false ? 0 : $ts;
}

function sokna_relay_validate_realtime_envelope(array $envelope, bool $allowSynthetic = false): array
{
    $errors = [];
    $requestId = trim((string)($envelope['request_id'] ?? ''));
    if ($requestId === '' || strlen($requestId) > 96 || preg_match('/^[A-Za-z0-9._:-]+$/', $requestId) !== 1) $errors[] = 'request_id';
    $kind = trim((string)($envelope['kind'] ?? ''));
    $allowedKinds = SOKNA_RELAY_REALTIME_KINDS;
    if ($allowSynthetic) $allowedKinds[] = 'system.synthetic_commit';
    if (!in_array($kind, $allowedKinds, true)) $errors[] = 'kind';
    $createdAt = trim((string)($envelope['created_at'] ?? ''));
    $expiresAt = trim((string)($envelope['expires_at'] ?? ''));
    $createdTs = sokna_relay_parse_time($createdAt);
    $expiresTs = sokna_relay_parse_time($expiresAt);
    if ($createdTs <= 0) $errors[] = 'created_at';
    if ($expiresTs <= 0 || ($createdTs > 0 && $expiresTs <= $createdTs)) $errors[] = 'expires_at';
    if (!isset($envelope['payload']) || !is_array($envelope['payload'])) $errors[] = 'payload';
    if (isset($envelope['expected_version']) && !is_int($envelope['expected_version']) && !is_string($envelope['expected_version'])) $errors[] = 'expected_version';
    if (isset($envelope['expected_state']) && !is_string($envelope['expected_state'])) $errors[] = 'expected_state';
    $actor = trim((string)($envelope['actor_projection_id'] ?? $envelope['session_id'] ?? ''));
    if ($actor === '') $errors[] = 'actor_projection_id';
    return ['ok'=>$errors === [], 'errors'=>$errors, 'expires_ts'=>$expiresTs];
}

function sokna_relay_is_terminal(string $state): bool
{
    return in_array($state, SOKNA_RELAY_TERMINAL_STATES, true);
}
