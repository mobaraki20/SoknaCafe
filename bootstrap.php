<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/request_path.php';
require_once __DIR__ . '/includes/observability.php';
sokna_observability_boot_http();

if (session_status() !== PHP_SESSION_ACTIVE) {
    $httpsRequest = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((int)($_SERVER['SERVER_PORT'] ?? 80) === 443);
    // Café authentication owns its own cookie and server-side session directory.
    // This prevents another PHP application on the same domain (for example Center)
    // from replacing PHPSESSID and prevents host-wide cleaners from treating Sokna
    // sessions as unrelated short-lived PHP sessions.
    $shiftSessionLifetime = 12 * 3600;
    $mountPath = app_request_mount_path();
    // Scope the cookie to the Sokna installation root for every route depth.
    $sessionCookiePath = $mountPath === '' ? '/' : $mountPath . '/';
    $runtimeStorage = __DIR__ . '/storage';
    $sessionStorage = $runtimeStorage . '/sessions';
    if (!is_dir($sessionStorage)) @mkdir($sessionStorage, 0700, true);
    // storage contains sessions, updater state and backups. Protect it from direct HTTP access
    // from the first request, rather than waiting until Maintenance/Updater is opened once.
    if (is_dir($runtimeStorage)) {
        $deny = "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n";
        if (!is_file($runtimeStorage . '/.htaccess')) @file_put_contents($runtimeStorage . '/.htaccess', $deny, LOCK_EX);
        if (!is_file($runtimeStorage . '/index.html')) @file_put_contents($runtimeStorage . '/index.html', '', LOCK_EX);
    }
    if (is_dir($sessionStorage) && is_writable($sessionStorage)) session_save_path($sessionStorage);
    session_name('SOKNA_CAFE_SID');
    ini_set('session.gc_maxlifetime', (string)$shiftSessionLifetime);
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => $httpsRequest,
        'cookie_samesite' => 'Lax',
        'cookie_path' => $sessionCookiePath,
        'cookie_lifetime' => $shiftSessionLifetime,
        'gc_maxlifetime' => $shiftSessionLifetime,
        'use_strict_mode' => true,
        'use_only_cookies' => true,
    ]);
}

$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    $isInstall = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'install.php';
    if (!$isInstall) {
        $mountPath = app_request_mount_path();
        header('Location: ' . $mountPath . '/install.php');
        exit;
    }
    return;
}

$config = require $configFile;

date_default_timezone_set($config['app']['timezone'] ?? 'Asia/Tehran');

if (($config['app']['debug'] ?? false) === true) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
}

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/sellable.php';
require_once __DIR__ . '/includes/preparation_permissions.php';
require_once __DIR__ . '/includes/modules.php';
require_once __DIR__ . '/includes/tax.php';
require_once __DIR__ . '/includes/menu_catalog.php';
require_once __DIR__ . '/includes/sokna_center.php';
require_once __DIR__ . '/includes/accommodation.php';
require_once __DIR__ . '/includes/printing.php';
require_once __DIR__ . '/includes/business_time.php';
require_once __DIR__ . '/includes/settlement.php';
require_once __DIR__ . '/includes/subscribers.php';
require_once __DIR__ . '/includes/inventory.php';
require_once __DIR__ . '/modules/Supply/domain.php';
require_once __DIR__ . '/modules/Supply/queries.php';
require_once __DIR__ . '/includes/expenses.php';
require_once __DIR__ . '/includes/deferred.php';
require_once __DIR__ . '/includes/font_runtime.php';
if (PHP_SAPI !== 'cli') font_runtime_ensure_vazirmatn(false);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/maintenance.php';

if (maintenance_is_active()) {
    $state = maintenance_state();
    $mode = (string)($state['mode'] ?? 'maintenance');
    $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $cutover = in_array($mode, ['restore','recovery_required'], true);
    $writeRequest = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    if (($cutover || $writeRequest) && $script !== 'maintenance.php') {
        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        if (str_contains($accept, 'application/json') || str_contains((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) maintenance_guard_json();
        http_response_code(503);
        header('Cache-Control: no-store, max-age=0');
        exit((string)($state['message'] ?? 'سامانه برای عملیات نگهداری موقتاً در دسترس نیست.'));
    }
}

function db(): PDO
{
    static $pdo = null;
    global $config;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $db = $config['db'];
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $db['host'],
        $db['port'] ?? '3306',
        $db['name'],
        $db['charset'] ?? 'utf8mb4'
    );

    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('SET time_zone = ' . $pdo->quote(date('P')));

    return $pdo;
}
