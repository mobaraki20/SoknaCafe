#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
require dirname(__DIR__) . '/includes/observability.php';
require dirname(__DIR__) . '/includes/runtime.php';

$options = [
    'once' => in_array('--once', $argv, true),
    'self_check' => in_array('--self-check', $argv, true),
    'health_json' => in_array('--health-json', $argv, true),
    'max_seconds' => 0,
];
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with((string)$arg, '--max-seconds=')) $options['max_seconds'] = max(0, (int)substr((string)$arg, 14));
}

if ($options['self_check']) {
    $check = sokna_runtime_self_check();
    echo json_encode($check, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT) . PHP_EOL;
    exit($check['ok'] ? 0 : 2);
}
if ($options['health_json']) {
    $state = sokna_runtime_read_state();
    echo json_encode($state ?: ['format'=>SOKNA_RUNTIME_STATE_FORMAT,'status'=>'not_started'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}

$lock = sokna_runtime_lock();
if ($lock === null) {
    fwrite(STDERR, "SOKNA Local Runtime is already active.\n");
    exit(0);
}
register_shutdown_function(static function() use ($lock): void {
    if (is_resource($lock)) { @flock($lock, LOCK_UN); @fclose($lock); }
});

$state = sokna_runtime_base_state();
$state['status'] = 'running';
$registry = sokna_runtime_worker_registry();
$nextRun = [];
foreach ($registry as $key => $_) $nextRun[$key] = 0.0;
$startedAt = microtime(true);
$lastHeartbeat = 0.0;
$stop = false;
if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGTERM, static function() use (&$stop): void { $stop = true; });
    pcntl_signal(SIGINT, static function() use (&$stop): void { $stop = true; });
}

sokna_log_event('info', 'runtime.started', ['pid'=>getmypid(),'workers'=>array_keys($registry)]);
try {
    do {
        $now = microtime(true);
        $maintenance = sokna_runtime_maintenance_state();
        if (!empty($maintenance['active'])) {
            $state['status'] = 'maintenance_paused';
            $state['maintenance_mode'] = (string)($maintenance['mode'] ?? 'maintenance');
            if (($now - $lastHeartbeat) >= 2 || $options['once']) {
                sokna_runtime_write_state($state);
                $lastHeartbeat = $now;
            }
            if ($options['once']) break;
            if ($options['max_seconds'] > 0 && (microtime(true)-$startedAt) >= $options['max_seconds']) break;
            usleep(500000);
            continue;
        }
        unset($state['maintenance_mode']);
        foreach ($registry as $key => $worker) {
            if ($now < ($nextRun[$key] ?? 0)) continue;
            $result = sokna_runtime_run_process((array)$worker['command']);
            $state['workers'][$key] = [
                'last_run_at' => gmdate('Y-m-d\\TH:i:s\\Z'),
                'exit_code' => $result['exit_code'],
                'timed_out' => $result['timed_out'],
                'duration_ms' => $result['duration_ms'],
                'healthy' => $result['exit_code'] === 0,
                'message' => $result['exit_code'] === 0 ? $result['stdout'] : ($result['stderr'] ?: $result['stdout']),
            ];
            if ($result['exit_code'] !== 0) {
                sokna_log_event('warning', 'runtime.worker_failed', ['worker'=>$key,'exit_code'=>$result['exit_code'],'timed_out'=>$result['timed_out'],'message'=>$state['workers'][$key]['message']]);
            }
            $nextRun[$key] = microtime(true) + max(1, (int)$worker['interval_seconds']);
        }

        $requiredHealthy = true;
        foreach ($registry as $key => $worker) {
            if (!empty($worker['required']) && isset($state['workers'][$key]) && empty($state['workers'][$key]['healthy'])) $requiredHealthy = false;
        }
        $state['status'] = $requiredHealthy ? 'running' : 'degraded';
        if (($now - $lastHeartbeat) >= 5 || $options['once']) {
            sokna_runtime_write_state($state);
            $lastHeartbeat = $now;
        }
        if ($options['once']) break;
        if ($options['max_seconds'] > 0 && (microtime(true)-$startedAt) >= $options['max_seconds']) break;
        usleep(250000);
    } while (!$stop);
    $state['status'] = 'stopped';
    $state['stopped_at'] = gmdate('Y-m-d\\TH:i:s\\Z');
    sokna_runtime_write_state($state);
    sokna_log_event('info', 'runtime.stopped', ['pid'=>getmypid()]);
    exit(0);
} catch (Throwable $e) {
    $state['status'] = 'failed';
    $state['last_error'] = get_class($e) . ': ' . $e->getMessage();
    try { sokna_runtime_write_state($state); } catch (Throwable) {}
    sokna_log_event('critical', 'runtime.failed', ['error_class'=>get_class($e),'message'=>$e->getMessage()]);
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(2);
}
