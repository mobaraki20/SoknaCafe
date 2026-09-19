<?php
declare(strict_types=1);

require_once __DIR__ . '/observability.php';

const SOKNA_RUNTIME_STATE_FORMAT = 'sokna-local-runtime-v1';

function sokna_runtime_state_path(): string
{
    return sokna_runtime_storage_dir() . DIRECTORY_SEPARATOR . 'state.json';
}

function sokna_runtime_lock_path(): string
{
    return sokna_runtime_storage_dir() . DIRECTORY_SEPARATOR . 'runtime.lock';
}

function sokna_runtime_local_hostname(): string
{
    $configured = '';
    if (isset($GLOBALS['config']) && is_array($GLOBALS['config'])) $configured = trim((string)($GLOBALS['config']['app']['local_hostname'] ?? ''));
    if ($configured === '') $configured = trim((string)(getenv('SOKNA_LOCAL_HOSTNAME') ?: ''));
    return $configured !== '' ? strtolower($configured) : 'sokna.local';
}

function sokna_runtime_print_agent_service_name(): string
{
    $configured = '';
    if (isset($GLOBALS['config']) && is_array($GLOBALS['config'])) {
        $configured = trim((string)($GLOBALS['config']['app']['print_agent_service_name'] ?? ''));
    }
    if ($configured === '') $configured = trim((string)(getenv('SOKNA_PRINT_AGENT_SERVICE') ?: ''));
    return $configured !== '' ? $configured : 'Sokna Print Agent 6';
}

function sokna_runtime_worker_registry(): array
{
    $root = dirname(__DIR__);
    return [
        'push' => [
            'interval_seconds' => 1,
            'command' => [PHP_BINARY, $root . '/tools/push-worker.php', '--once'],
            'required' => true,
        ],
        'printing' => [
            'interval_seconds' => 5,
            'command' => [PHP_BINARY, $root . '/tools/print-runtime-worker.php', '--once'],
            'required' => false,
        ],
        'inventory' => [
            'interval_seconds' => 2,
            'command' => [PHP_BINARY, $root . '/tools/inventory-worker.php', '--once'],
            'required' => false,
        ],
        'backup' => [
            'interval_seconds' => 300,
            'command' => [PHP_BINARY, $root . '/tools/backup-worker.php'],
            'required' => true,
        ],
        'relay' => [
            'interval_seconds' => 1,
            'command' => [PHP_BINARY, $root . '/tools/relay-worker.php', '--once'],
            'required' => false,
        ],
        'relay_projection' => [
            'interval_seconds' => 60,
            'command' => [PHP_BINARY, $root . '/tools/relay-projection-worker.php', '--once'],
            'required' => false,
        ],
        'remote_read_models' => [
            'interval_seconds' => 30,
            'command' => [PHP_BINARY, $root . '/tools/remote-read-worker.php', '--once'],
            'required' => false,
        ],
        'deferred_sync' => [
            'interval_seconds' => 2,
            'command' => [PHP_BINARY, $root . '/tools/deferred-worker.php', '--once'],
            'required' => false,
        ],
        'center_projection' => [
            'interval_seconds' => 60,
            'command' => [PHP_BINARY, $root . '/tools/center-projection-worker.php', '--once'],
            'required' => false,
        ],
        'guest_availability' => [
            'interval_seconds' => 2,
            'command' => [PHP_BINARY, $root . '/tools/guest-availability-worker.php', '--once'],
            'required' => false,
        ],
    ];
}

function sokna_runtime_base_state(): array
{
    $versionPath = dirname(__DIR__) . '/VERSION.txt';
    return [
        'format' => SOKNA_RUNTIME_STATE_FORMAT,
        'version' => trim((string)@file_get_contents($versionPath)),
        'pid' => getmypid(),
        'hostname' => gethostname() ?: '',
        'local_hostname' => sokna_runtime_local_hostname(),
        'started_at' => gmdate('Y-m-d\\TH:i:s\\Z'),
        'last_seen_at' => gmdate('Y-m-d\\TH:i:s\\Z'),
        'status' => 'starting',
        'workers' => [],
    ];
}

function sokna_runtime_write_state(array $state): void
{
    $state['format'] = SOKNA_RUNTIME_STATE_FORMAT;
    $state['last_seen_at'] = gmdate('Y-m-d\\TH:i:s\\Z');
    sokna_atomic_json_write(sokna_runtime_state_path(), $state);
}

function sokna_runtime_read_state(): array
{
    return sokna_read_json_file(sokna_runtime_state_path());
}

function sokna_runtime_maintenance_state(): array
{
    $candidates = [
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'maintenance.json',
        sokna_data_root() . DIRECTORY_SEPARATOR . 'maintenance.json',
    ];
    foreach (array_unique($candidates) as $path) {
        if (!is_file($path)) continue;
        $state = sokna_read_json_file($path);
        if ($state) return $state + ['active' => true];
        return ['active' => true, 'mode' => 'unknown'];
    }
    return ['active' => false];
}

function sokna_runtime_health_snapshot(int $staleSeconds = 20): array
{
    $state = sokna_runtime_read_state();
    if (!$state) return ['available'=>false,'healthy'=>false,'status'=>'not_started','stale'=>true];
    $lastSeen = strtotime((string)($state['last_seen_at'] ?? '')) ?: 0;
    $stale = $lastSeen < time() - max(5, $staleSeconds);
    $status = (string)($state['status'] ?? 'unknown');
    return [
        'available' => true,
        'healthy' => !$stale && in_array($status, ['running','maintenance_paused'], true),
        'status' => $status,
        'stale' => $stale,
        'last_seen_at' => (string)($state['last_seen_at'] ?? ''),
        'version' => (string)($state['version'] ?? ''),
        'workers' => is_array($state['workers'] ?? null) ? $state['workers'] : [],
    ];
}

function sokna_runtime_self_check(): array
{
    $checks = [];
    $checks['php_cli'] = PHP_SAPI === 'cli';
    $checks['php_binary'] = is_file(PHP_BINARY);
    $checks['runtime_storage'] = sokna_ensure_private_dir(sokna_runtime_storage_dir());
    $checks['log_storage'] = sokna_ensure_private_dir(sokna_log_dir());
    $checks['local_hostname'] = sokna_runtime_local_hostname() === 'sokna.local' || preg_match('/^[a-z0-9.-]+$/', sokna_runtime_local_hostname()) === 1;
    foreach (sokna_runtime_worker_registry() as $key => $worker) {
        $script = (string)($worker['command'][1] ?? '');
        $checks['worker_' . $key] = $script !== '' && is_file($script);
    }
    return [
        'ok' => !in_array(false, $checks, true),
        'checks' => $checks,
        'data_root' => sokna_data_root(),
        'local_hostname' => sokna_runtime_local_hostname(),
    ];
}

function sokna_runtime_run_process(array $command, int $timeoutSeconds = 45): array
{
    if (!$command) throw new InvalidArgumentException('Worker command is empty.');
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $started = microtime(true);
    $process = @proc_open($command, $descriptors, $pipes, dirname(__DIR__), null, ['bypass_shell' => true]);
    if (!is_resource($process)) throw new RuntimeException('Worker process could not be started.');
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $stdout = '';
    $stderr = '';
    $timedOut = false;
    $observedExit = null;
    while (true) {
        $stdout .= (string)stream_get_contents($pipes[1]);
        $stderr .= (string)stream_get_contents($pipes[2]);
        $status = proc_get_status($process);
        if (!$status['running']) { $observedExit = (int)$status['exitcode']; break; }
        if ((microtime(true) - $started) >= $timeoutSeconds) {
            $timedOut = true;
            proc_terminate($process);
            usleep(200000);
            $status = proc_get_status($process);
            if ($status['running']) proc_terminate($process, 9);
            break;
        }
        usleep(50000);
    }
    $stdout .= (string)stream_get_contents($pipes[1]);
    $stderr .= (string)stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $closeExit = proc_close($process);
    $exitCode = $timedOut ? 124 : (($observedExit !== null && $observedExit >= 0) ? $observedExit : $closeExit);
    return [
        'exit_code' => $exitCode,
        'timed_out' => $timedOut,
        'duration_ms' => (int)round((microtime(true)-$started)*1000),
        'stdout' => substr(trim($stdout), 0, 2000),
        'stderr' => substr(trim($stderr), 0, 2000),
    ];
}

function sokna_runtime_lock()
{
    if (!sokna_ensure_private_dir(sokna_runtime_storage_dir())) throw new RuntimeException('Runtime storage is not writable.');
    $handle = fopen(sokna_runtime_lock_path(), 'c+');
    if ($handle === false) throw new RuntimeException('Runtime lock could not be opened.');
    if (!flock($handle, LOCK_EX|LOCK_NB)) { fclose($handle); return null; }
    ftruncate($handle, 0);
    fwrite($handle, (string)getmypid());
    fflush($handle);
    return $handle;
}
