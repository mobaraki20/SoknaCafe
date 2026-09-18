#!/usr/bin/env php
<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
require dirname(__DIR__) . '/bootstrap.php';

if (!sokna_module_runtime_ready('inventory')) {
    fwrite(STDOUT, json_encode(['processed'=>0,'failed'=>0,'inventory_ready'=>false], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(0);
}

$once = in_array('--once', $argv, true);
$retryFailed = in_array('--retry-failed', $argv, true);
$limit = 50;
foreach (array_slice($argv,1) as $arg) {
    if (preg_match('/^\d+$/', (string)$arg)) { $limit = max(1,min(100,(int)$arg)); break; }
}

$pdo = db();
$lockName = 'sokna_inventory_worker_' . substr(hash('sha256', (string)(config()['db']['name'] ?? 'sokna')), 0, 20);
$lock = $pdo->prepare('SELECT GET_LOCK(?,0)');
$lock->execute([$lockName]);
if ((int)$lock->fetchColumn() !== 1) {
    fwrite(STDOUT, "Another inventory worker is already active.\n");
    exit(0);
}
register_shutdown_function(static function() use ($pdo,$lockName): void {
    try { $stmt=$pdo->prepare('SELECT RELEASE_LOCK(?)'); $stmt->execute([$lockName]); } catch (Throwable) {}
});

if ($retryFailed) inventory_retry_failed_order_events($pdo);

do {
    try {
        $result = inventory_process_pending_order_events($limit);
        $backlog = inventory_order_event_backlog($pdo);
        if ($once) {
            fwrite(STDOUT,json_encode($result + ['pending'=>$backlog['pending'],'stuck_failed'=>$backlog['failed']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL);
            exit((int)$result['failed']>0 || (int)$backlog['failed']>0 ? 2 : 0);
        }
        if ((int)$result['processed'] === 0) usleep(750000);
        else usleep(100000);
    } catch (Throwable $e) {
        error_log('inventory worker loop: ' . $e->getMessage());
        if ($once) { fwrite(STDERR,$e->getMessage().PHP_EOL); exit(2); }
        sleep(3);
    }
} while (true);
