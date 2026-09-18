<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$configFile = $root . '/config.php';
$lockFile = $root . '/install.lock';
$schemaFile = $root . '/database/schema.sql';

function fail_check(string $message): never
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

if (!is_file($configFile)) fail_check('config.php پیدا نشد؛ نصب هنوز کامل نیست.');
if (!is_file($lockFile)) fail_check('install.lock پیدا نشد؛ نصب کامل ثبت نشده است.');
if (!extension_loaded('pdo_mysql')) fail_check('افزونه pdo_mysql فعال نیست.');

$config = require $configFile;
$db = $config['db'] ?? [];
foreach (['host', 'port', 'name', 'user', 'pass'] as $key) {
    if (!array_key_exists($key, $db)) fail_check("تنظیم دیتابیس {$key} ناقص است.");
}

$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['name']);
$pdo = new PDO($dsn, (string)$db['user'], (string)$db['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');

$schema = file_get_contents($schemaFile);
if ($schema === false) fail_check('database/schema.sql قابل خواندن نیست.');
preg_match_all('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?([A-Za-z0-9_]+)`?/i', $schema, $matches);
$expected = array_values(array_unique(array_map('strval', $matches[1] ?? [])));
if (count($expected) < 20) fail_check('فهرست جدول‌های Schema ناقص است.');

$stmt = $pdo->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_TYPE=\'BASE TABLE\'');
$stmt->execute([(string)$db['name']]);
$actual = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
$missing = array_values(array_diff($expected, $actual));
if ($missing) fail_check('جدول‌های ناقص: ' . implode(', ', $missing));

$marker = $pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE version=?');
$marker->execute(['1.30.1-rc2-baseline']);
if ((int)$marker->fetchColumn() !== 1) fail_check('Marker پایه نسخه 1.30.1-rc2 وجود ندارد.');

foreach (['billing_locked_at', 'billing_locked_by_user_id'] as $columnName) {
    $columnCheck = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='table_sessions' AND COLUMN_NAME=?");
    $columnCheck->execute([(string)$db['name'], $columnName]);
    if ((int)$columnCheck->fetchColumn() !== 1) fail_check("ستون table_sessions.{$columnName} وجود ندارد.");
}
foreach (['print_agents','print_destinations','print_jobs'] as $printTable) {
    if (!in_array($printTable, $actual, true)) fail_check("جدول {$printTable} وجود ندارد.");
}

$inventoryTables = [
    'inventory_items','inventory_purchase_units','inventory_balances','inventory_movements',
    'inventory_recipe_versions','inventory_recipe_components','inventory_count_sessions',
    'inventory_count_lines','inventory_order_events',
];
foreach ($inventoryTables as $inventoryTable) {
    if (!in_array($inventoryTable, $actual, true)) fail_check("جدول {$inventoryTable} وجود ندارد.");
}
$inventorySetting = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key=? LIMIT 1');
$inventorySetting->execute(['inventory_initialized']);
if ((string)$inventorySetting->fetchColumn() !== '0') fail_check('نصب تازه باید inventory_initialized=0 داشته باشد.');
foreach ([['inventory_movements','uq_inventory_movement_idempotency'],['inventory_order_events','uq_inventory_order_event_idempotency']] as [$tableName,$indexName]) {
    $indexCheck = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND INDEX_NAME=? AND NON_UNIQUE=0');
    $indexCheck->execute([(string)$db['name'], $tableName, $indexName]);
    if ((int)$indexCheck->fetchColumn() < 1) fail_check("Unique Index {$indexName} وجود ندارد.");
}

$tableColumns = ['table_number', 'previous_access_token', 'qr_rotated_at', 'qr_rotated_by_user_id'];
foreach ($tableColumns as $columnName) {
    $columnCheck = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='cafe_tables' AND COLUMN_NAME=?");
    $columnCheck->execute([(string)$db['name'], $columnName]);
    if ((int)$columnCheck->fetchColumn() !== 1) fail_check("ستون cafe_tables.{$columnName} وجود ندارد.");
}
foreach (['uq_tables_number', 'uq_tables_previous_token', 'idx_tables_number_active'] as $indexName) {
    $indexCheck = $pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME='cafe_tables' AND INDEX_NAME=?");
    $indexCheck->execute([(string)$db['name'], $indexName]);
    if ((int)$indexCheck->fetchColumn() < 1) fail_check("Index {$indexName} وجود ندارد.");
}

$tableNumberNullability = $pdo->prepare("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='cafe_tables' AND COLUMN_NAME='table_number' LIMIT 1");
$tableNumberNullability->execute([(string)$db['name']]);
if ((string)$tableNumberNullability->fetchColumn() !== 'NO') fail_check('cafe_tables.table_number باید NOT NULL باشد.');

foreach (['table_sessions','orders','waiter_calls','settlement_records'] as $snapshotTable) {
    foreach (['business_date','business_shift_key','business_shift_label','business_cutoff_snapshot'] as $snapshotColumn) {
        $columnCheck = $pdo->prepare('SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');
        $columnCheck->execute([(string)$db['name'], $snapshotTable, $snapshotColumn]);
        if ((string)$columnCheck->fetchColumn() !== 'NO') fail_check("{$snapshotTable}.{$snapshotColumn} باید NOT NULL باشد.");
    }
}

$feeColumn = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=\'events\' AND COLUMN_NAME=\'fee_amount\'');
$feeColumn->execute([(string)$db['name']]);
if ((int)$feeColumn->fetchColumn() !== 1) fail_check('ستون هزینه رویداد وجود ندارد.');

$guardColumns = [
    ['table_sessions', 'live_table_guard', 'uq_table_sessions_one_live_table'],
    ['waiter_calls', 'active_table_guard', 'uq_waiter_calls_one_active_table'],
];
foreach ($guardColumns as [$table, $column, $index]) {
    $columnStmt = $pdo->prepare('SELECT EXTRA, IS_NULLABLE, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?');
    $columnStmt->execute([(string)$db['name'], $table, $column]);
    $info = $columnStmt->fetch();
    if (!$info) fail_check("ستون {$table}.{$column} وجود ندارد.");
    if (stripos((string)$info['EXTRA'], 'GENERATED') !== false) fail_check("ستون {$table}.{$column} هنوز Generated است.");
    if ((string)$info['IS_NULLABLE'] !== 'YES') fail_check("ستون {$table}.{$column} باید Nullable باشد.");

    $indexStmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND INDEX_NAME=? AND NON_UNIQUE=0');
    $indexStmt->execute([(string)$db['name'], $table, $index]);
    if ((int)$indexStmt->fetchColumn() < 1) fail_check("Unique Index {$index} وجود ندارد.");
}

foreach (['favicon_32_path', 'favicon_180_path', 'favicon_192_path', 'favicon_512_path'] as $key) {
    $setting = $pdo->prepare('SELECT COUNT(*) FROM settings WHERE setting_key=?');
    $setting->execute([$key]);
    if ((int)$setting->fetchColumn() !== 1) fail_check("تنظیم {$key} وجود ندارد.");
}

$userCount = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE active=1')->fetchColumn();
$adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE active=1 AND role='admin'")->fetchColumn();
$memberCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role<>'admin'")->fetchColumn();
$tableCount = (int)$pdo->query('SELECT COUNT(*) FROM cafe_tables WHERE active=1')->fetchColumn();
if ($userCount !== 1 || $adminCount !== 1 || $memberCount !== 0) fail_check('نصب تازه باید فقط یک حساب مدیر بسازد.');
if ($tableCount < 1) fail_check('هیچ میز فعالی ساخته نشده است.');

$version = trim((string)$pdo->query('SELECT VERSION()')->fetchColumn());
printf("PASS: Sokna 1.32.4 fresh install database is structurally valid.\n");
printf("Database engine: %s\n", $version);
printf("Expected tables: %d | Present tables: %d | Active users: %d | Active cafe tables: %d\n", count($expected), count($actual), $userCount, $tableCount);
