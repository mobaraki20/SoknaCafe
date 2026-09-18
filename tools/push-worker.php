#!/usr/bin/env php
<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/push.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This worker is CLI-only.\n");
    exit(64);
}

$once = in_array('--once', $argv, true);
$idleMicros = 750000;
$lockName = 'sokna_push_worker_' . substr(hash('sha256', (string)(config()['db']['name'] ?? 'sokna')), 0, 20);
$pdo = db();
$lock = $pdo->prepare('SELECT GET_LOCK(?,0)');
$lock->execute([$lockName]);
if ((int)$lock->fetchColumn() !== 1) {
    fwrite(STDOUT, "Another push worker is already active.\n");
    exit(0);
}
register_shutdown_function(static function() use ($pdo, $lockName): void {
    try { $stmt=$pdo->prepare('SELECT RELEASE_LOCK(?)'); $stmt->execute([$lockName]); } catch (Throwable) {}
});

do {
    try {
        try {
            $heartbeat = $pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
            $heartbeat->execute(['push.worker_last_seen_at', date('Y-m-d H:i:s')]);
        } catch (Throwable $heartbeatError) {
            error_log('push worker heartbeat: ' . $heartbeatError->getMessage());
        }
        $result = push_process_queue(10);
        if ($once) {
            fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . PHP_EOL);
            break;
        }
        if ((int)($result['processed'] ?? 0) === 0) usleep($idleMicros);
        else usleep(100000);
    } catch (Throwable $e) {
        error_log('push worker loop: ' . $e->getMessage());
        if ($once) { fwrite(STDERR, $e->getMessage() . PHP_EOL); exit(2); }
        sleep(3);
    }
} while (true);
