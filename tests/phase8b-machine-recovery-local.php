<?php
declare(strict_types=1);

$repo = dirname(__DIR__);
$host = getenv('SOKNA_PUBLIC_TEST_DB_HOST') ?: '127.0.0.1';
$port = getenv('SOKNA_PUBLIC_TEST_DB_PORT') ?: '3306';
$user = getenv('SOKNA_PUBLIC_TEST_DB_USER') ?: 'root';
$pass = getenv('SOKNA_PUBLIC_TEST_DB_PASS') ?: 'root';

$sourceDb = 'sokna_p8b_src_' . bin2hex(random_bytes(3));
$targetDb = 'sokna_p8b_dst_' . bin2hex(random_bytes(3));
$admin = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    $user,
    $pass,
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
);
foreach ([$sourceDb,$targetDb] as $name) {
    $admin->exec('CREATE DATABASE ' . $name . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
}

$tmp = sys_get_temp_dir() . '/sokna-p8b-' . bin2hex(random_bytes(4));
@mkdir($tmp . '/storage/backups', 0750, true);
@mkdir($tmp . '/storage/tmp', 0750, true);
@mkdir($tmp . '/uploads', 0755, true);
@mkdir($tmp . '/includes', 0755, true);
@mkdir($tmp . '/api', 0755, true);
@mkdir($tmp . '/data', 0750, true);

copy($repo . '/VERSION.txt', $tmp . '/VERSION.txt');
copy($repo . '/login.php', $tmp . '/login.php');
copy($repo . '/includes/auth.php', $tmp . '/includes/auth.php');
copy($repo . '/includes/guest_order_service.php', $tmp . '/includes/guest_order_service.php');
copy($repo . '/api/create_order.php', $tmp . '/api/create_order.php');

define('SOKNA_MAINTENANCE_ROOT', $tmp);
putenv('SOKNA_DATA_DIR=' . $tmp . '/data');

$GLOBALS['config'] = [
    'db'=>[
        'host'=>$host,
        'port'=>$port,
        'name'=>$targetDb,
        'user'=>$user,
        'pass'=>$pass,
        'charset'=>'utf8mb4',
    ],
    'app'=>[
        'url'=>'https://sokna.local',
        'key'=>str_repeat('a',64),
        'timezone'=>'Asia/Tehran',
        'debug'=>false,
        'trust_proxy_headers'=>false,
        'data_dir'=>$tmp . '/data',
    ],
    'relay'=>[
        'enabled'=>false,
        'public_base_url'=>'',
        'installation_id'=>'',
        'shared_secret'=>'',
    ],
];
file_put_contents($tmp . '/config.php', "<?php\nreturn " . var_export($GLOBALS['config'], true) . ";\n");

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = $GLOBALS['config']['db'];
        $pdo = new PDO(
            "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4",
            $c['user'],
            $c['pass'],
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}
function setting(string $key, string $default=''): string
{
    try {
        $stmt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key=? LIMIT 1');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : (string)$value;
    } catch (Throwable) {
        return $default;
    }
}
function clear_setting_cache(): void {}
function sokna_center_secret(): string { return ''; }
function accommodation_api_key(): string { return ''; }
function current_user(): ?array { return null; }
function audit_log_write(string $action, string $entityType, string|int|null $entityId, array $details=[], ?int $actorUserId=null): void {}
function fa_digits(string $value): string { return $value; }

require $repo . '/includes/setup_install.php';
require $repo . '/includes/observability.php';
require $repo . '/includes/installation_identity.php';
require $repo . '/includes/maintenance.php';

function t8bl(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException($message);
}

try {
    $source = new PDO(
        "mysql:host={$host};port={$port};dbname={$sourceDb};charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    );

    // Exercise the full production schema but keep fixture rows intentionally tiny.
    // The recovery contract is about whole-schema dump/restore, identity and health;
    // default catalog seeding is already covered by the setup-owner runtime tests.
    sokna_setup_apply_schema($source, $repo);
    $source->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')
        ->execute(['cafe_name','Recovered Cafe']);
    $source->prepare('INSERT IGNORE INTO schema_migrations(version) VALUES(?)')
        ->execute(['1.30.1-rc2-baseline']);

    $databaseFile = $tmp . '/storage/tmp/source.sql';
    $dump = maintenance_database_dump_file($source, $databaseFile);

    $tarPath = $tmp . '/storage/tmp/recovery.tar';
    @unlink($tarPath);
    $archive = new PharData($tarPath);
    $entries = [];
    maintenance_add_file($archive, $databaseFile, 'database/database.sql', $entries);

    $oldAppKey = str_repeat('z',64);
    maintenance_add_string($archive, $oldAppKey, 'system/app.key', $entries);

    $index = json_encode($entries, JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $archive->addFromString('files.json', $index);
    $manifest = [
        'format'=>'sokna-backup-v3',
        'type'=>'backup',
        'version'=>maintenance_version(),
        'created_at'=>date(DATE_ATOM),
        'timezone'=>'Asia/Tehran',
        'actor_user_id'=>null,
        'database_sha256'=>$dump['sha256'],
        'files_index_sha256'=>hash('sha256', $index),
        'entry_count'=>count($entries),
        'uploads_present'=>false,
        'schema_fingerprint'=>'intentionally-different-for-machine-recovery',
        'portable_app_identity'=>true,
        'app_identity_fingerprint'=>substr(hash('sha256',$oldAppKey),0,16),
        'recovery_metadata'=>[
            'format'=>'sokna-recovery-metadata-v1',
            'installation_identity'=>['installation_id'=>'inst_oldmachine0000000000000000000000'],
            'private_identity_cloned'=>false,
        ],
    ];
    $archive->addFromString('manifest.json', json_encode($manifest, JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    unset($archive);

    $freshBefore = sokna_installation_identity_ensure();
    t8bl(($freshBefore['installation_id'] ?? '') !== '', 'fresh installation identity missing');

    $result = maintenance_restore_archive_to_empty_target($tarPath, db());

    t8bl(($result['success'] ?? false) === true, 'machine recovery failed');
    t8bl(setting('cafe_name') === 'Recovered Cafe', 'business data was not restored');

    $configAfter = require $tmp . '/config.php';
    t8bl(($configAfter['app']['key'] ?? '') === $oldAppKey, 'portable app key was not restored');
    t8bl(count(db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN)) > 50, 'target schema was not restored from backup');

    $freshAfter = sokna_installation_identity_ensure();
    t8bl(
        ($freshAfter['installation_id'] ?? '') === ($freshBefore['installation_id'] ?? ''),
        'fresh machine identity changed during restore'
    );
    t8bl(
        ($freshAfter['installation_id'] ?? '') !== 'inst_oldmachine0000000000000000000000',
        'old installation identity was cloned'
    );

    echo "Phase 8B machine recovery MariaDB PASS.\n";
} finally {
    foreach ([$sourceDb,$targetDb] as $name) {
        try { $admin->exec('DROP DATABASE IF EXISTS ' . $name); } catch (Throwable) {}
    }
    if (is_dir($tmp)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($tmp);
    }
}
