<?php
declare(strict_types=1);

/**
 * Cross-cutting observability owner for Local runtime and HTTP requests.
 * This file deliberately has no database dependency so it can be used by
 * diagnostics/recovery paths before MariaDB is available.
 */

function sokna_data_root(): string
{
    static $root = null;
    if (is_string($root)) return $root;

    $configured = trim((string)(getenv('SOKNA_DATA_DIR') ?: ''));
    if ($configured === '' && isset($GLOBALS['config']) && is_array($GLOBALS['config'])) {
        $configured = trim((string)($GLOBALS['config']['app']['data_dir'] ?? ''));
    }
    if ($configured === '' && PHP_OS_FAMILY === 'Windows') {
        $programData = trim((string)(getenv('PROGRAMDATA') ?: ''));
        if ($programData !== '') $configured = rtrim($programData, "\\/") . DIRECTORY_SEPARATOR . 'SOKNA';
    }
    if ($configured === '') $configured = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage';

    $root = rtrim($configured, "\\/");
    return $root;
}

function sokna_runtime_storage_dir(): string
{
    return sokna_data_root() . DIRECTORY_SEPARATOR . 'runtime';
}

function sokna_log_dir(): string
{
    return sokna_data_root() . DIRECTORY_SEPARATOR . 'logs';
}

function sokna_ensure_private_dir(string $path): bool
{
    if (is_dir($path)) return is_writable($path);
    return @mkdir($path, 0700, true) || is_dir($path);
}

function sokna_normalize_correlation_id(?string $value): string
{
    $value = trim((string)$value);
    if ($value !== '' && preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{7,95}$/D', $value) === 1) return $value;
    return bin2hex(random_bytes(16));
}

function sokna_correlation_id(?string $candidate = null): string
{
    static $id = null;
    if (is_string($id)) return $id;
    if ($candidate === null && PHP_SAPI !== 'cli') $candidate = (string)($_SERVER['HTTP_X_SOKNA_CORRELATION_ID'] ?? '');
    $id = sokna_normalize_correlation_id($candidate);
    return $id;
}

function sokna_observability_boot_http(): void
{
    if (PHP_SAPI === 'cli' || headers_sent()) return;
    header('X-Sokna-Correlation-ID: ' . sokna_correlation_id());
}

function sokna_sensitive_context_key(string $key): bool
{
    $key = strtolower(trim($key));
    if ($key === '') return false;
    $exact = [
        'password','pass','passwd','authorization','cookie','set-cookie','app_key','private_key',
        'secret','client_secret','token','access_token','refresh_token','bearer','token_hash','secret_key',
        'db_pass','database_password','recovery_passphrase','passphrase',
    ];
    if (in_array($key, $exact, true)) return true;
    return preg_match('/(?:^|_)(?:password|passwd|secret|private_key|access_token|refresh_token|passphrase)$/', $key) === 1;
}

function sokna_redact_context(mixed $value, string $key = '', int $depth = 0): mixed
{
    if ($depth > 8) return '[DEPTH_LIMIT]';
    if ($key !== '' && sokna_sensitive_context_key($key)) return '[REDACTED]';
    if (is_array($value)) {
        $safe = [];
        foreach ($value as $k => $v) $safe[$k] = sokna_redact_context($v, (string)$k, $depth + 1);
        return $safe;
    }
    if (is_object($value)) return '[OBJECT ' . get_class($value) . ']';
    if (is_resource($value)) return '[RESOURCE]';
    if (is_string($value) && strlen($value) > 4000) return substr($value, 0, 4000) . '…';
    return $value;
}

function sokna_log_event(string $level, string $event, array $context = []): void
{
    $level = strtolower(trim($level));
    if (!in_array($level, ['debug','info','warning','error','critical'], true)) $level = 'info';
    $event = preg_replace('/[^A-Za-z0-9_.:-]+/', '_', trim($event)) ?: 'event';
    $entry = [
        'ts' => gmdate('Y-m-d\\TH:i:s\\Z'),
        'level' => $level,
        'event' => $event,
        'correlation_id' => sokna_correlation_id(),
        'pid' => getmypid(),
        'context' => sokna_redact_context($context),
    ];
    try {
        $json = json_encode($entry, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR) . PHP_EOL;
        $dir = sokna_log_dir();
        if (sokna_ensure_private_dir($dir) && @file_put_contents($dir . DIRECTORY_SEPARATOR . 'sokna-' . gmdate('Y-m-d') . '.jsonl', $json, FILE_APPEND|LOCK_EX) !== false) return;
    } catch (Throwable) {
        // fall through to PHP error log without leaking the original context
    }
    error_log('[SOKNA][' . $level . '][' . $event . '] correlation_id=' . sokna_correlation_id());
}

function sokna_atomic_json_write(string $path, array $payload): void
{
    $dir = dirname($path);
    if (!sokna_ensure_private_dir($dir)) throw new RuntimeException('Runtime storage is not writable.');
    $tmp = $path . '.tmp-' . bin2hex(random_bytes(4));
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
    if (@file_put_contents($tmp, $json, LOCK_EX) === false || !@rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException('Runtime state could not be written atomically.');
    }
}

function sokna_read_json_file(string $path): array
{
    if (!is_file($path)) return [];
    $raw = @file_get_contents($path);
    if (!is_string($raw) || $raw === '') return [];
    try {
        $decoded = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : [];
    } catch (Throwable) {
        return [];
    }
}
