<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Tehran');
if (session_status() !== PHP_SESSION_ACTIVE) {
    $httpsRequest = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((int)($_SERVER['SERVER_PORT'] ?? 80) === 443);
    session_start(['cookie_httponly' => true, 'cookie_secure' => $httpsRequest, 'cookie_samesite' => 'Lax', 'use_strict_mode' => true, 'use_only_cookies' => true]);
}
if (empty($_SESSION['install_csrf'])) $_SESSION['install_csrf'] = bin2hex(random_bytes(32));

$root = __DIR__;
$lockFile = $root . '/install.lock';
$configFile = $root . '/config.php';
$errors = [];
$success = false;
require_once __DIR__ . '/includes/default_menu_seed.php';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Unicode-safe length check available before config.php/bootstrap exists. */
function install_text_length(string $value): int
{
    $count = preg_match_all('/./us', $value, $matches);
    return $count === false ? strlen($value) : $count;
}

function detect_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((int)($_SERVER['SERVER_PORT'] ?? 80) === 443);
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return $scheme . '://' . $host . ($dir === '' ? '' : $dir);
}

/** @return array{raw:string,engine:string,version:string,supported:bool} */
function install_database_engine_info(PDO $pdo): array
{
    $raw = trim((string)$pdo->query('SELECT VERSION()')->fetchColumn());
    $engine = stripos($raw, 'mariadb') !== false ? 'MariaDB' : 'MySQL';
    preg_match('/(\d+)\.(\d+)\.(\d+)/', $raw, $match);
    $version = isset($match[0]) ? $match[0] : '0.0.0';
    $minimum = $engine === 'MariaDB' ? '10.2.0' : '5.7.8';
    return ['raw'=>$raw,'engine'=>$engine,'version'=>$version,'supported'=>version_compare($version,$minimum,'>=')];
}

/** @return list<string> */
function install_schema_table_names(string $schema): array
{
    preg_match_all('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?([A-Za-z0-9_]+)`?/i', $schema, $matches);
    return array_values(array_unique(array_map('strval', $matches[1] ?? [])));
}

/** @param list<string> $tables */
function install_drop_created_tables(PDO $pdo, array $tables): void
{
    if (!$tables) return;
    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach (array_reverse($tables) as $table) {
            if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) continue;
            $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
        }
    } catch (Throwable $cleanupError) {
        error_log('Cafe install cleanup: ' . $cleanupError->getMessage());
    } finally {
        try { $pdo->exec('SET FOREIGN_KEY_CHECKS=1'); } catch (Throwable) {}
    }
}

function install_assert_empty_database(PDO $pdo): void
{
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    if ($tables) {
        $sample = implode('، ', array_slice(array_map('strval', $tables), 0, 5));
        throw new RuntimeException('این دیتابیس خالی نیست' . ($sample !== '' ? ' (' . $sample . ')' : '') . '. برای نصب تازه، یک دیتابیس خالی بساز یا جدول‌های نصب ناموفق قبلی را حذف کن.');
    }
}

function install_probe_privileges(PDO $pdo): void
{
    $name = 'sokna_install_probe_' . bin2hex(random_bytes(4));
    $pdo->exec('CREATE TEMPORARY TABLE `' . $name . '` (id INT NOT NULL PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec('ALTER TABLE `' . $name . '` ADD COLUMN label VARCHAR(20) NULL');
    $pdo->exec('DROP TEMPORARY TABLE `' . $name . '`');
}

if (is_file($lockFile) && is_file($configFile)) {
    $installed = true;
} else {
    $installed = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$installed) {
    if (!hash_equals((string)($_SESSION['install_csrf'] ?? ''), (string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'درخواست نصب معتبر نیست. صفحه را تازه‌سازی کنید.';
    }
    $dbHost = trim($_POST['db_host'] ?? 'localhost');
    $dbPort = trim($_POST['db_port'] ?? '3306');
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = (string)($_POST['db_pass'] ?? '');
    $appUrl = rtrim(trim($_POST['app_url'] ?? ''), '/');
    $cafeName = trim($_POST['cafe_name'] ?? 'کافه من');
    $adminUser = trim($_POST['admin_user'] ?? 'admin');
    $adminPass = (string)($_POST['admin_pass'] ?? '');
    $tableCount = max(1, min(200, (int)($_POST['table_count'] ?? 30)));

    if ($dbName === '' || $dbUser === '') $errors[] = 'اطلاعات پایگاه داده کامل نیست.';
    if (str_contains($dbHost, ';') || str_contains($dbName, ';')) $errors[] = 'میزبان یا نام پایگاه داده معتبر نیست.';
    if (!ctype_digit($dbPort) || (int)$dbPort < 1 || (int)$dbPort > 65535) $errors[] = 'پورت پایگاه داده معتبر نیست.';
    if (!preg_match('/^[A-Za-z0-9._-]{3,80}$/', $adminUser)) $errors[] = 'نام کاربری باید ۳ تا ۸۰ کاراکتر و شامل حروف انگلیسی، عدد، نقطه، خط تیره یا زیرخط باشد.';
    if (!is_writable($root)) $errors[] = 'پوشه نصب برای ساخت فایل‌های تنظیمات قابل نوشتن نیست.';
    if (!filter_var($appUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $appUrl)) $errors[] = 'آدرس کامل نصب معتبر نیست.';
    if (install_text_length($adminPass) < 8) $errors[] = 'رمز مدیر باید حداقل ۸ کاراکتر باشد.';
    foreach (['pdo_mysql'=>'PDO MySQL','fileinfo'=>'Fileinfo','openssl'=>'OpenSSL','sodium'=>'Sodium'] as $extension=>$label) {
        if (!extension_loaded($extension)) $errors[] = 'افزونه PHP «' . $label . '» روی هاست فعال نیست.';
    }

    if (!$errors) {
        $tempConfigFile = ''; $createdConfig = false; $createdLock = false;
        $databaseWasEmpty = false; $schemaStarted = false; $schemaTableNames = [];
        try {
            $config = [
                'db' => ['host'=>$dbHost,'port'=>$dbPort,'name'=>$dbName,'user'=>$dbUser,'pass'=>$dbPass,'charset'=>'utf8mb4'],
                'app' => ['url'=>$appUrl,'key'=>bin2hex(random_bytes(32)),'timezone'=>'Asia/Tehran','debug'=>false,'trust_proxy_headers'=>false],
            ];
            $configContent = "<?php\nreturn " . var_export($config, true) . ";\n";
            $tempConfigFile = $configFile . '.tmp-' . bin2hex(random_bytes(6));
            if (file_put_contents($tempConfigFile, $configContent, LOCK_EX) === false) throw new RuntimeException('امکان ساخت فایل تنظیمات موقت وجود ندارد.');
            @chmod($tempConfigFile, 0600);

            $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            $engineInfo = install_database_engine_info($pdo);
            if (!$engineInfo['supported']) {
                throw new RuntimeException('نسخه پایگاه داده پشتیبانی نمی‌شود: ' . $engineInfo['raw'] . '. حداقل MySQL 5.7.8 یا MariaDB 10.2 لازم است.');
            }
            install_assert_empty_database($pdo);
            $databaseWasEmpty = true;
            install_probe_privileges($pdo);

            $uploadDir = $root . '/uploads';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) throw new RuntimeException('پوشه تصاویر ساخته نشد.');
            $uploadGuard = "Options -Indexes\n<FilesMatch \"\\.(?:php|phtml|phar|cgi|pl|py|sh|exe|bat|cmd)$\">\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n</FilesMatch>\n";
            if (!is_file($uploadDir . '/.htaccess')) @file_put_contents($uploadDir . '/.htaccess', $uploadGuard, LOCK_EX);
            if (!is_file($uploadDir . '/index.html')) @file_put_contents($uploadDir . '/index.html', '', LOCK_EX);

            $schema = file_get_contents($root . '/database/schema.sql');
            if ($schema === false || trim($schema) === '') throw new RuntimeException('فایل ساخت دیتابیس قابل خواندن نیست.');
            $schemaTableNames = install_schema_table_names($schema);
            if (count($schemaTableNames) < 20) throw new RuntimeException('فایل ساخت دیتابیس ناقص است.');
            $statements = array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $schema)));
            $schemaStarted = true;
            foreach ($statements as $statement) {
                if ($statement !== '') $pdo->exec($statement);
            }

            $pdo->beginTransaction();

            $settings = [
                'cafe_name' => $cafeName,
                'primary_color' => '#365b4c',
                'accent_color' => '#b85c38',
                'background_color' => '#f7f3ec',
                'logo_path' => '',
                'favicon_32_path' => '',
                'favicon_180_path' => '',
                'favicon_192_path' => '',
                'favicon_512_path' => '',
                'favicon_updated_at' => '',
                'waiter_call_enabled' => '1',
                'public_waiter_call_enabled' => '0',
                'events_enabled' => '1',
                'show_visit_duration' => '1',
                'business_day_cutoff' => '04:00',
                'business_shifts_json' => json_encode([
                    ['key'=>'shift_1','label'=>'صبح','start'=>'08:00','end'=>'16:00','active'=>true],
                    ['key'=>'shift_2','label'=>'عصر','start'=>'16:00','end'=>'01:00','active'=>true],
                ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                'new_device_alert_minutes' => '20',
                'pwa_enabled' => '1',
                'menu_theme' => 'courtyard',
                'app_canonical_url' => $appUrl,
                'menu_density' => 'balanced',
                'menu_layout' => 'editorial',
                'public_about_enabled' => '1',
                'about_title' => 'درباره سکنا',
                'about_intro' => 'سکنا جایی برای قهوه، گفت‌وگو و تجربه‌های نزدیکه.',
                'seo_description' => 'منوی کافه، رویدادها و راه‌های ارتباط با سکنا.',
                'public_address' => '',
                'public_phone' => '',
                'instagram_cafe_url' => '',
                'instagram_house_url' => '',
                'whatsapp_enabled' => '0',
                'whatsapp_number' => '',
                'whatsapp_message' => 'سلام، از طریق سایت سکنا پیام می‌دم.',
                'accommodation_enabled' => '1',
                'accommodation_site_url' => 'https://www.soknahouse.ir/',
                'accommodation_rooms_url' => '',
                'accommodation_tours_url' => '',
                'accommodation_card_title' => 'خانه سکنا رو هم می‌شناسی؟',
                'accommodation_card_text' => 'اقامت، گشت‌وگذار و تجربه غرب هرمزگان',
                'social_footer_enabled' => '1',
                'post_order_instagram_enabled' => '1',
                'campaigns_enabled' => '1',
                'analytics_enabled' => '1',
                'station_busy.kitchen' => '0',
                'station_busy.bar' => '0',
                'orders_accepting.cafe' => '1',
                'orders_accepting.kitchen' => '1',
                'orders_accepting.bar' => '1',
                'orders_message.cafe' => 'سفارش‌گیری کافه موقتاً متوقف است. لطفاً کمی بعد دوباره بررسی کنید.',
                'orders_message.kitchen' => 'سفارش‌گیری آشپزخانه موقتاً متوقف است. نوشیدنی‌ها و اقلام بار همچنان قابل سفارش‌اند.',
                'orders_message.bar' => 'سفارش‌گیری بار موقتاً متوقف است. غذاهای آشپزخانه همچنان قابل سفارش‌اند.',
                'app_timezone' => 'Asia/Tehran',
                'checkout_print_default' => '1',
                'module.inventory.enabled' => '1',
                'module.supply.enabled' => '1',
                'module.marketing.enabled' => '1',
                'module.reporting.enabled' => '1',
                'module.personnel.enabled' => '1',
                'inventory_initialized' => '0',
                'inventory_reconciliation_required' => '0',
            ];
            $settingStmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
            foreach ($settings as $key => $value) $settingStmt->execute([$key, $value]);

            $userStmt = $pdo->prepare('INSERT INTO users (username, password_hash, display_name, role, active) VALUES (?, ?, ?, ?, 1) ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), display_name = VALUES(display_name), role = VALUES(role), active = 1');
            $userStmt->execute([$adminUser, password_hash($adminPass, PASSWORD_DEFAULT), 'مدیر کافه', 'admin']);

            sync_default_menu_seed($pdo, true);

            $tagStmt = $pdo->prepare('INSERT IGNORE INTO tags(title,slug,tag_type,color_key,icon,sort_order) VALUES(?,?,?,?,?,?)');
            foreach ([
                ['جدید','new','marketing','accent','✦',10],
                ['پرفروش','popular','marketing','primary','★',20],
                ['فصلی','seasonal','marketing','sand','☼',30],
                ['گیاهی','vegan','attribute','green','●',10],
                ['بدون قند','sugar-free','attribute','blue','○',20],
            ] as $tag) $tagStmt->execute($tag);

            $tableStmt = $pdo->prepare('INSERT IGNORE INTO cafe_tables (name, table_number, code, access_token, active, sort_order) VALUES (?, ?, ?, ?, 1, ?)');
            for ($i = 1; $i <= $tableCount; $i++) {
                $faNumber = strtr((string)$i, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']);
                $tableStmt->execute(['میز ' . $faNumber, $i, (string)$i, rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '='), $i]);
            }

            $migrationStmt = $pdo->prepare('INSERT IGNORE INTO schema_migrations(version) VALUES(?)');
            $migrationStmt->execute(['1.30.1-rc2-baseline']);

            if (!@rename($tempConfigFile, $configFile)) throw new RuntimeException('انتقال فایل config.php ناموفق بود. سطح دسترسی پوشه را بررسی کنید.');
            $createdConfig = true; $tempConfigFile = '';
            if (file_put_contents($lockFile, date(DATE_ATOM), LOCK_EX) === false) throw new RuntimeException('ساخت install.lock ناموفق بود.');
            $createdLock = true;
            $pdo->commit();
            $success = true;
            $installed = true;
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
            if ($databaseWasEmpty && $schemaStarted && isset($pdo) && $pdo instanceof PDO) install_drop_created_tables($pdo, $schemaTableNames);
            if ($createdLock && is_file($lockFile)) @unlink($lockFile);
            if ($createdConfig && is_file($configFile)) @unlink($configFile);
            if ($tempConfigFile !== '' && is_file($tempConfigFile)) @unlink($tempConfigFile);
            error_log('Cafe install error: ' . $e->getMessage());
            $errors[] = 'نصب انجام نشد: ' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>نصب سامانه سفارش کافه</title>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-180.png">
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f7f3ec;color:#202522;font-family:system-ui,-apple-system,"Segoe UI",Tahoma,Arial,sans-serif;line-height:1.8}.wrap{max-width:820px;margin:32px auto;padding:16px}.card{background:#fff;border-radius:24px;padding:28px;box-shadow:0 16px 45px rgba(58,39,28,.12)}h1{margin:0 0 8px}p{color:#6d625b}.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.full{grid-column:1/-1}label{display:block;font-weight:700;margin-bottom:6px}input{width:100%;padding:12px 14px;border:1px solid #d9d0ca;border-radius:12px;font:inherit}button,.btn{display:inline-block;border:0;background:#365b4c;color:#fff;padding:13px 22px;border-radius:12px;font:inherit;font-weight:700;cursor:pointer;text-decoration:none}.alert{padding:12px 16px;border-radius:12px;margin:12px 0}.error{background:#fff0f0;color:#9c2525}.success{background:#eaf8ef;color:#166534}@media(max-width:650px){.grid{grid-template-columns:1fr}.full{grid-column:auto}.card{padding:20px}}
    </style>
</head>
<body><div class="wrap"><div class="card">
    <h1>نصب سامانه سفارش کافه</h1>
    <p>در cPanel یک دیتابیس خالی MySQL و یک کاربر با دسترسی کامل به همان دیتابیس بسازید. نصب فقط حساب مدیر را ایجاد می‌کند؛ حساب شخصی اعضای تیم بعد از ورود، از بخش «اعضای تیم» ساخته می‌شود.</p>
    <?php foreach ($errors as $error): ?><div class="alert error"><?= h($error) ?></div><?php endforeach; ?>
    <?php if ($installed): ?>
        <div class="alert success"><?= $success ? 'همه‌چیز آماده‌ست؛ حالا وارد سامانه شو.' : 'سامانه قبلاً نصب شده است.' ?></div>
        <a class="btn" href="login.php">ورود به سامانه</a>
    <?php else: ?>
    <form method="post" class="grid" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['install_csrf']) ?>">
        <div><label>میزبان دیتابیس</label><input name="db_host" value="<?= h($_POST['db_host'] ?? 'localhost') ?>" required></div>
        <div><label>پورت دیتابیس</label><input name="db_port" value="<?= h($_POST['db_port'] ?? '3306') ?>" required></div>
        <div><label>نام دیتابیس</label><input name="db_name" value="<?= h($_POST['db_name'] ?? '') ?>" required></div>
        <div><label>نام کاربری دیتابیس</label><input name="db_user" value="<?= h($_POST['db_user'] ?? '') ?>" required></div>
        <div class="full"><label>رمز دیتابیس</label><input type="password" name="db_pass"></div>
        <div class="full"><label>آدرس کامل نصب</label><input type="url" name="app_url" value="<?= h($_POST['app_url'] ?? detect_url()) ?>" placeholder="https://example.com/cafe" required></div>
        <div><label>نام کافه</label><input name="cafe_name" value="<?= h($_POST['cafe_name'] ?? 'کافه من') ?>" required></div>
        <div><label>تعداد میز اولیه</label><input type="number" min="1" max="200" name="table_count" value="<?= h($_POST['table_count'] ?? '30') ?>"></div>
        <div></div>
        <div><label>نام کاربری مدیر</label><input name="admin_user" value="<?= h($_POST['admin_user'] ?? 'admin') ?>" required></div>
        <div><label>رمز مدیر</label><input type="password" name="admin_pass" minlength="8" required></div>
        <div class="full"><button type="submit">نصب و راه‌اندازی</button></div>
    </form>
    <?php endif; ?>
</div></div></body></html>
