#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(64);
}

require dirname(__DIR__) . '/includes/observability.php';
require dirname(__DIR__) . '/includes/runtime.php';

function print_runtime_emit(string $status, array $extra = []): void
{
    fwrite(STDOUT, json_encode(['status'=>$status] + $extra, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . PHP_EOL);
}

if (PHP_OS_FAMILY !== 'Windows') {
    print_runtime_emit('not_windows');
    exit(0);
}

$systemRoot = rtrim((string)(getenv('SystemRoot') ?: 'C:\\Windows'), "\\/");
$sc = $systemRoot . DIRECTORY_SEPARATOR . 'System32' . DIRECTORY_SEPARATOR . 'sc.exe';
if (!is_file($sc)) {
    fwrite(STDERR, "Windows Service Control Manager client is unavailable.\n");
    exit(2);
}

$service = sokna_runtime_print_worker_service_name();
$queryService = static function() use ($sc, $service): array {
    return sokna_runtime_run_process([$sc, 'query', $service], 8);
};
$serviceState = static function(array $result): string {
    $text = strtoupper((string)($result['stdout'] ?? '') . "\n" . (string)($result['stderr'] ?? ''));
    if (preg_match('/STATE\\s*:\\s*\\d+\\s+([A-Z_]+)/', $text, $m)) return (string)$m[1];
    return '';
};

$query = $queryService();
if ((int)$query['exit_code'] !== 0) {
    $text = (string)$query['stdout'] . "\n" . (string)$query['stderr'];
    if (preg_match('/\\b1060\\b/', $text) || stripos($text, 'does not exist') !== false) {
        print_runtime_emit('component_missing', ['service'=>$service]);
        exit(2);
    }
    fwrite(STDERR, "Unable to query the internal SOKNA Print Worker service.\n");
    exit(2);
}

$state = $serviceState($query);
if ($state === 'RUNNING') {
    print_runtime_emit('running', ['service'=>$service]);
    exit(0);
}

if ($state === 'STOPPED') {
    $start = sokna_runtime_run_process([$sc, 'start', $service], 8);
    if ((int)$start['exit_code'] !== 0) {
        fwrite(STDERR, "Internal SOKNA Print Worker service could not be started.\n");
        exit(2);
    }
}

if (in_array($state, ['STOPPED','START_PENDING'], true)) {
    for ($i = 0; $i < 20; $i++) {
        usleep(250000);
        $probe = $queryService();
        if ((int)$probe['exit_code'] !== 0) break;
        $current = $serviceState($probe);
        if ($current === 'RUNNING') {
            print_runtime_emit('running', ['service'=>$service,'recovered'=>$state === 'STOPPED']);
            exit(0);
        }
        if (!in_array($current, ['START_PENDING','STOPPED'], true)) break;
    }
}

fwrite(STDERR, "Internal SOKNA Print Worker service is not running.\n");
exit(2);
