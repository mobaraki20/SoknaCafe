<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
require dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/maintenance.php';

$staleHours = MAINTENANCE_AUTO_BACKUP_HOURS;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--if-stale-hours=')) $staleHours = max(1, min(720, (int)substr($arg, 17)));
}

maintenance_ensure_storage();
$latest = null;
foreach (maintenance_list_backups(false) as $backup) {
    if (($backup['manifest']['type'] ?? '') === 'backup' && ($backup['valid'] ?? null) === true) {
        $latest = $backup;
        break;
    }
}

if ($latest) {
    $ts = strtotime((string)($latest['manifest']['created_at'] ?? $latest['modified_at'] ?? '')) ?: 0;
    if ($ts > time() - $staleHours * 3600) {
        maintenance_record_backup_worker_run('fresh', 'Backup is fresh; nothing to do.');
        echo "Backup is fresh; nothing to do.\n";
        exit(0);
    }
}

try {
    $job = maintenance_job_start('backup', ['source' => 'cli']);
    $result = maintenance_create_backup(null);
    maintenance_job_finish($job, true, 'CLI backup completed.');
    maintenance_record_backup_worker_run('completed', (string)($result['name'] ?? 'backup'));
    echo ($result['name'] ?? 'backup') . "\n";
    exit(0);
} catch (Throwable $e) {
    if (isset($job)) maintenance_job_finish($job, false, $e->getMessage());
    maintenance_record_backup_worker_run('failed', $e->getMessage());
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
