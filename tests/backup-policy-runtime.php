<?php
declare(strict_types=1);

date_default_timezone_set('UTC');
$root = sys_get_temp_dir() . '/sokna-backup-policy-' . bin2hex(random_bytes(4));
@mkdir($root, 0777, true);
file_put_contents($root . '/VERSION.txt', "1.30.5\n");
define('SOKNA_MAINTENANCE_ROOT', $root);

$GLOBALS['fake_settings'] = [];
$GLOBALS['fake_audit'] = [];
function setting(string $key, string $default = ''): string { return array_key_exists($key, $GLOBALS['fake_settings']) ? (string)$GLOBALS['fake_settings'][$key] : $default; }
function clear_setting_cache(): void {}
class FakeStmt { public function execute(array $values): bool { $GLOBALS['fake_settings'][(string)$values[0]] = (string)$values[1]; return true; } }
class FakeDb { public function prepare(string $sql): FakeStmt { return new FakeStmt(); } }
function db(): FakeDb { return new FakeDb(); }
function audit_log_write(string $action, string $entityType, string|int|null $entityId, array $details = [], ?int $actorUserId = null): void { $GLOBALS['fake_audit'][] = compact('action','entityType','entityId','details','actorUserId'); }
require dirname(__DIR__) . '/includes/maintenance.php';

$now = strtotime('2026-08-09T12:00:00Z');
$GLOBALS['fake_settings']['backup_last_offserver_export_at'] = '2026-08-03T12:00:01Z';
$status = maintenance_offserver_export_status($now);
if ($status['due']) throw new RuntimeException('Reminder fired before seven complete days.');
$GLOBALS['fake_settings']['backup_last_offserver_export_at'] = '2026-08-02T12:00:00Z';
$status = maintenance_offserver_export_status($now);
if (!$status['due'] || $status['days_since'] !== 7) throw new RuntimeException('Seven-day reminder boundary failed.');

$backup = [
  'name' => 'sokna-backup-20260809-120000-deadbeef.tar.gz',
  'size' => 1234,
  'valid' => true,
  'manifest' => ['type'=>'backup','version'=>'1.30.5','created_at'=>'2026-08-09T11:00:00Z'],
];
maintenance_record_offserver_export($backup, 9);
if (($GLOBALS['fake_settings']['backup_last_offserver_export_name'] ?? '') !== $backup['name']) throw new RuntimeException('Export name was not recorded.');
if (empty($GLOBALS['fake_settings']['backup_last_offserver_export_at'])) throw new RuntimeException('Export timestamp was not recorded.');
if (($GLOBALS['fake_audit'][0]['action'] ?? '') !== 'backup.offserver_exported') throw new RuntimeException('Export audit was not written.');

maintenance_record_backup_worker_run('completed', 'ok');
$worker = maintenance_backup_worker_status(time());
if (!$worker['active'] || !$worker['seen'] || $worker['status'] !== 'completed') throw new RuntimeException('Automatic backup worker heartbeat status failed.');

function rrmdir(string $dir): void { if (!is_dir($dir)) return; foreach (scandir($dir) ?: [] as $n) { if ($n==='.'||$n==='..') continue; $p=$dir.'/'.$n; is_dir($p)?rrmdir($p):@unlink($p); } @rmdir($dir); }
rrmdir($root);
echo "Backup policy runtime passed: seven-day reminder boundary, successful export tracking/audit, and automatic-worker heartbeat.\n";
