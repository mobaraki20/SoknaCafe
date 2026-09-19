<?php
declare(strict_types=1);

require_once __DIR__ . '/default_menu_seed.php';

function sokna_setup_text_length(string $value): int
{
    $count = preg_match_all('/./us', $value, $matches);
    return $count === false ? strlen($value) : $count;
}

function sokna_setup_database_engine_info(PDO $pdo): array
{
    $raw = trim((string)$pdo->query('SELECT VERSION()')->fetchColumn());
    $engine = stripos($raw, 'mariadb') !== false ? 'MariaDB' : 'MySQL';
    preg_match('/(\d+)\.(\d+)\.(\d+)/', $raw, $match);
    $version = isset($match[0]) ? $match[0] : '0.0.0';
    $minimum = $engine === 'MariaDB' ? '10.2.0' : '5.7.8';
    return ['raw'=>$raw,'engine'=>$engine,'version'=>$version,'supported'=>version_compare($version,$minimum,'>=')];
}

function sokna_setup_schema_table_names(string $schema): array
{
    preg_match_all('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?([A-Za-z0-9_]+)`?/i', $schema, $matches);
    return array_values(array_unique(array_map('strval', $matches[1] ?? [])));
}

function sokna_setup_drop_created_tables(PDO $pdo, array $tables): void
{
    if (!$tables) return;
    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach (array_reverse($tables) as $table) {
            if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) continue;
            $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
        }
    } catch (Throwable $cleanupError) {
        error_log('SOKNA setup cleanup: ' . $cleanupError->getMessage());
    } finally {
        try { $pdo->exec('SET FOREIGN_KEY_CHECKS=1'); } catch (Throwable) {}
    }
}

function sokna_setup_assert_empty_database(PDO $pdo): void
{
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    if ($tables) {
        $sample = implode('، ', array_slice(array_map('strval', $tables), 0, 5));
        throw new RuntimeException('این دیتابیس خالی نیست' . ($sample !== '' ? ' (' . $sample . ')' : '') . '. برای نصب یا بازیابی ماشین، یک دیتابیس خالی لازم است.');
    }
}

function sokna_setup_probe_privileges(PDO $pdo): void
{
    $name = 'sokna_install_probe_' . bin2hex(random_bytes(4));
    $pdo->exec('CREATE TEMPORARY TABLE `' . $name . '` (id INT NOT NULL PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec('ALTER TABLE `' . $name . '` ADD COLUMN label VARCHAR(20) NULL');
    $pdo->exec('DROP TEMPORARY TABLE `' . $name . '`');
}

function sokna_setup_validate_input(array $input, string $mode = 'new'): array
{
    $errors = [];
    $db = is_array($input['db'] ?? null) ? $input['db'] : [];
    $host = trim((string)($db['host'] ?? 'localhost'));
    $port = trim((string)($db['port'] ?? '3306'));
    $name = trim((string)($db['name'] ?? ''));
    $user = trim((string)($db['user'] ?? ''));
    $appUrl = rtrim(trim((string)($input['app_url'] ?? '')), '/');

    if ($name === '' || $user === '') $errors[] = 'اطلاعات پایگاه داده کامل نیست.';
    if (str_contains($host, ';') || str_contains($name, ';')) $errors[] = 'میزبان یا نام پایگاه داده معتبر نیست.';
    if (!ctype_digit($port) || (int)$port < 1 || (int)$port > 65535) $errors[] = 'پورت پایگاه داده معتبر نیست.';
    if (!filter_var($appUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $appUrl)) $errors[] = 'آدرس کامل نصب معتبر نیست.';

    if ($mode === 'new') {
        $adminUser = trim((string)($input['admin_user'] ?? ''));
        $adminPass = (string)($input['admin_password'] ?? '');
        if (!preg_match('/^[A-Za-z0-9._-]{3,80}$/', $adminUser)) $errors[] = 'نام کاربری مدیر معتبر نیست.';
        if (sokna_setup_text_length($adminPass) < 8) $errors[] = 'رمز مدیر باید حداقل ۸ کاراکتر باشد.';
    } elseif ($mode !== 'recover') {
        $errors[] = 'حالت Setup معتبر نیست.';
    }

    $relay = is_array($input['relay'] ?? null) ? $input['relay'] : [];
    if (!empty($relay['enabled'])) {
        $base = rtrim(trim((string)($relay['public_base_url'] ?? '')), '/');
        $installationId = trim((string)($relay['installation_id'] ?? ''));
        $secret = (string)($relay['shared_secret'] ?? '');
        if (!filter_var($base, FILTER_VALIDATE_URL) || !preg_match('#^https://#i', $base)) $errors[] = 'آدرس Public Pairing باید HTTPS معتبر باشد.';
        if ($installationId === '' || strlen($installationId) > 128 || preg_match('/[\r\n]/', $installationId)) $errors[] = 'شناسه Public Pairing معتبر نیست.';
        if (strlen($secret) < 32 || preg_match('/[\r\n]/', $secret)) $errors[] = 'Secret مربوط به Public Pairing معتبر نیست.';
    }

    foreach (['pdo_mysql'=>'PDO MySQL','fileinfo'=>'Fileinfo','openssl'=>'OpenSSL','sodium'=>'Sodium'] as $extension=>$label) {
        if (!extension_loaded($extension)) $errors[] = 'افزونه PHP «' . $label . '» نصب نیست.';
    }
    return $errors;
}

function sokna_setup_require_valid(array $input, string $mode): void
{
    $errors = sokna_setup_validate_input($input, $mode);
    if ($errors) throw new RuntimeException(implode(' ', $errors));
}

function sokna_setup_config(array $input): array
{
    $db = is_array($input['db'] ?? null) ? $input['db'] : [];
    $relayInput = is_array($input['relay'] ?? null) ? $input['relay'] : [];
    return [
        'db'=>[
            'host'=>trim((string)($db['host'] ?? 'localhost')),
            'port'=>trim((string)($db['port'] ?? '3306')),
            'name'=>trim((string)($db['name'] ?? '')),
            'user'=>trim((string)($db['user'] ?? '')),
            'pass'=>(string)($db['pass'] ?? ''),
            'charset'=>'utf8mb4',
        ],
        'relay'=>[
            'enabled'=>(bool)($relayInput['enabled'] ?? false),
            'public_base_url'=>rtrim(trim((string)($relayInput['public_base_url'] ?? '')), '/'),
            'installation_id'=>trim((string)($relayInput['installation_id'] ?? '')),
            'shared_secret'=>(string)($relayInput['shared_secret'] ?? ''),
            'timeout_seconds'=>max(2, min(30, (int)($relayInput['timeout_seconds'] ?? 8))),
        ],
        'app'=>[
            'url'=>rtrim(trim((string)($input['app_url'] ?? '')), '/'),
            'key'=>bin2hex(random_bytes(32)),
            'timezone'=>trim((string)($input['timezone'] ?? 'Asia/Tehran')) ?: 'Asia/Tehran',
            'debug'=>false,
            'trust_proxy_headers'=>false,
            'data_dir'=>trim((string)($input['data_dir'] ?? '')),
            'local_hostname'=>strtolower(trim((string)($input['local_hostname'] ?? 'sokna.local'))) ?: 'sokna.local',
            'print_agent_service_name'=>'Sokna Print Agent 6',
        ],
    ];
}

function sokna_setup_connect(array $config): PDO
{
    $db = $config['db'];
    $dsn = 'mysql:host=' . $db['host'] . ';port=' . $db['port'] . ';dbname=' . $db['name'] . ';charset=utf8mb4';
    $pdo = new PDO($dsn, (string)$db['user'], (string)$db['pass'], [
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false,
    ]);
    $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
    $info = sokna_setup_database_engine_info($pdo);
    if (!$info['supported']) throw new RuntimeException('نسخه پایگاه داده پشتیبانی نمی‌شود: ' . $info['raw']);
    return $pdo;
}

function sokna_setup_prepare_uploads(string $root): void
{
    $uploadDir = rtrim($root, "\\/") . '/uploads';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) throw new RuntimeException('پوشه تصاویر ساخته نشد.');
    $guard = "Options -Indexes\n<FilesMatch \"\\.(?:php|phtml|phar|cgi|pl|py|sh|exe|bat|cmd)$\">\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n</FilesMatch>\n";
    if (!is_file($uploadDir . '/.htaccess')) @file_put_contents($uploadDir . '/.htaccess', $guard, LOCK_EX);
    if (!is_file($uploadDir . '/index.html')) @file_put_contents($uploadDir . '/index.html', '', LOCK_EX);
}

function sokna_setup_apply_schema(PDO $pdo, string $root): array
{
    $schema = file_get_contents(rtrim($root, "\\/") . '/database/schema.sql');
    if ($schema === false || trim($schema) === '') throw new RuntimeException('فایل ساخت دیتابیس قابل خواندن نیست.');
    $tables = sokna_setup_schema_table_names($schema);
    if (count($tables) < 20) throw new RuntimeException('فایل ساخت دیتابیس ناقص است.');
    $statements = array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $schema)));
    foreach ($statements as $statement) if ($statement !== '') $pdo->exec($statement);
    return $tables;
}

function sokna_setup_default_settings(string $cafeName, string $appUrl): array
{
    return [
        'cafe_name'=>$cafeName,'primary_color'=>'#365b4c','accent_color'=>'#b85c38','background_color'=>'#f7f3ec',
        'logo_path'=>'','favicon_32_path'=>'','favicon_180_path'=>'','favicon_192_path'=>'','favicon_512_path'=>'','favicon_updated_at'=>'',
        'waiter_call_enabled'=>'1','public_waiter_call_enabled'=>'0','events_enabled'=>'1','show_visit_duration'=>'1','business_day_cutoff'=>'04:00',
        'business_shifts_json'=>json_encode([
            ['key'=>'shift_1','label'=>'صبح','start'=>'08:00','end'=>'16:00','active'=>true],
            ['key'=>'shift_2','label'=>'عصر','start'=>'16:00','end'=>'01:00','active'=>true],
        ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        'new_device_alert_minutes'=>'20','pwa_enabled'=>'1','menu_theme'=>'courtyard','app_canonical_url'=>$appUrl,
        'menu_density'=>'balanced','menu_layout'=>'editorial','public_about_enabled'=>'1','about_title'=>'درباره سکنا',
        'about_intro'=>'سکنا جایی برای قهوه، گفت‌وگو و تجربه‌های نزدیکه.','seo_description'=>'منوی کافه، رویدادها و راه‌های ارتباط با سکنا.',
        'public_address'=>'','public_phone'=>'','instagram_cafe_url'=>'','instagram_house_url'=>'',
        'whatsapp_enabled'=>'0','whatsapp_number'=>'','whatsapp_message'=>'سلام، از طریق سایت سکنا پیام می‌دم.',
        'accommodation_enabled'=>'1','accommodation_site_url'=>'https://www.soknahouse.ir/','accommodation_rooms_url'=>'','accommodation_tours_url'=>'',
        'accommodation_card_title'=>'خانه سکنا رو هم می‌شناسی؟','accommodation_card_text'=>'اقامت، گشت‌وگذار و تجربه غرب هرمزگان',
        'social_footer_enabled'=>'1','post_order_instagram_enabled'=>'1','campaigns_enabled'=>'1','analytics_enabled'=>'1',
        'station_busy.kitchen'=>'0','station_busy.bar'=>'0',
        'orders_accepting.cafe'=>'1','orders_accepting.kitchen'=>'1','orders_accepting.bar'=>'1',
        'orders_message.cafe'=>'سفارش‌گیری کافه موقتاً متوقف است. لطفاً کمی بعد دوباره بررسی کنید.',
        'orders_message.kitchen'=>'سفارش‌گیری آشپزخانه موقتاً متوقف است. نوشیدنی‌ها و اقلام بار همچنان قابل سفارش‌اند.',
        'orders_message.bar'=>'سفارش‌گیری بار موقتاً متوقف است. غذاهای آشپزخانه همچنان قابل سفارش‌اند.',
        'app_timezone'=>'Asia/Tehran','checkout_print_default'=>'1',
        'module.inventory.enabled'=>'1','module.supply.enabled'=>'1','module.marketing.enabled'=>'1','module.reporting.enabled'=>'1','module.personnel.enabled'=>'1',
        'inventory_initialized'=>'0','inventory_reconciliation_required'=>'0',
    ];
}

function sokna_setup_seed_new(PDO $pdo, array $input): void
{
    $cafeName = trim((string)($input['cafe_name'] ?? 'کافه من')) ?: 'کافه من';
    $appUrl = rtrim(trim((string)($input['app_url'] ?? '')), '/');
    $settingStmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    foreach (sokna_setup_default_settings($cafeName, $appUrl) as $key=>$value) $settingStmt->execute([$key,$value]);

    $userStmt = $pdo->prepare('INSERT INTO users (username,password_hash,display_name,role,active) VALUES (?,?,?,?,1) ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash),display_name=VALUES(display_name),role=VALUES(role),active=1');
    $userStmt->execute([trim((string)$input['admin_user']),password_hash((string)$input['admin_password'],PASSWORD_DEFAULT),'مدیر کافه','admin']);
    sync_default_menu_seed($pdo, true);

    $tagStmt=$pdo->prepare('INSERT IGNORE INTO tags(title,slug,tag_type,color_key,icon,sort_order) VALUES(?,?,?,?,?,?)');
    foreach ([['جدید','new','marketing','accent','✦',10],['پرفروش','popular','marketing','primary','★',20],['فصلی','seasonal','marketing','sand','☼',30],['گیاهی','vegan','attribute','green','●',10],['بدون قند','sugar-free','attribute','blue','○',20]] as $tag) $tagStmt->execute($tag);

    $tableCount=max(1,min(200,(int)($input['table_count']??30)));
    $tableStmt=$pdo->prepare('INSERT IGNORE INTO cafe_tables (name,table_number,code,access_token,active,sort_order) VALUES (?,?,?,?,1,?)');
    for($i=1;$i<=$tableCount;$i++){
        $fa=strtr((string)$i,['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']);
        $tableStmt->execute(['میز '.$fa,$i,(string)$i,rtrim(strtr(base64_encode(random_bytes(18)),'+/','-_'),'='),$i]);
    }
    $pdo->prepare('INSERT IGNORE INTO schema_migrations(version) VALUES(?)')->execute(['1.30.1-rc2-baseline']);
}

function sokna_setup_write_config_atomic(string $root, array $config): string
{
    $path=rtrim($root,"\\/").'/config.php';
    if (is_file($path)) throw new RuntimeException('config.php از قبل وجود دارد.');
    $tmp=$path.'.tmp-'.bin2hex(random_bytes(6));
    $content="<?php\nreturn ".var_export($config,true).";\n";
    if(file_put_contents($tmp,$content,LOCK_EX)===false)throw new RuntimeException('امکان ساخت فایل تنظیمات موقت وجود ندارد.');
    @chmod($tmp,0600);
    if(!rename($tmp,$path)){@unlink($tmp);throw new RuntimeException('فعال‌سازی config.php ناموفق بود.');}
    @chmod($path,0600);
    return $path;
}

function sokna_setup_write_lock(string $root): void
{
    $path=rtrim($root,"\\/").'/install.lock';
    if(file_put_contents($path,date(DATE_ATOM),LOCK_EX)===false)throw new RuntimeException('ساخت install.lock ناموفق بود.');
    @chmod($path,0640);
}

function sokna_setup_fresh_install(array $input, ?string $root = null): array
{
    $root=rtrim($root??dirname(__DIR__),"\\/");
    sokna_setup_require_valid($input,'new');
    if(!is_writable($root))throw new RuntimeException('پوشه نصب قابل نوشتن نیست.');
    if(is_file($root.'/config.php')||is_file($root.'/install.lock'))throw new RuntimeException('سامانه قبلاً نصب شده یا Setup ناقص دارد.');
    $config=sokna_setup_config($input);
    $pdo=sokna_setup_connect($config);
    sokna_setup_assert_empty_database($pdo);
    sokna_setup_probe_privileges($pdo);
    sokna_setup_prepare_uploads($root);
    $tables=[];$configPath='';
    try{
        $tables=sokna_setup_apply_schema($pdo,$root);
        $pdo->beginTransaction();
        sokna_setup_seed_new($pdo,$input);
        $configPath=sokna_setup_write_config_atomic($root,$config);
        sokna_setup_write_lock($root);
        $pdo->commit();
        return ['mode'=>'new','config'=>$config,'table_count'=>count($tables)];
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        sokna_setup_drop_created_tables($pdo,$tables);
        if($configPath!==''&&is_file($configPath))@unlink($configPath);
        @unlink($root.'/install.lock');
        throw $e;
    }
}

function sokna_setup_prepare_recovery_target(array $input, ?string $root = null): array
{
    $root=rtrim($root??dirname(__DIR__),"\\/");
    sokna_setup_require_valid($input,'recover');
    if(!is_writable($root))throw new RuntimeException('پوشه نصب قابل نوشتن نیست.');
    if(is_file($root.'/config.php')||is_file($root.'/install.lock'))throw new RuntimeException('Recover فقط روی مقصد نصب‌نشده مجاز است.');
    $config=sokna_setup_config($input);
    $pdo=sokna_setup_connect($config);
    sokna_setup_assert_empty_database($pdo);
    sokna_setup_probe_privileges($pdo);
    sokna_setup_prepare_uploads($root);
    sokna_setup_write_config_atomic($root,$config);
    return ['mode'=>'recover','config'=>$config];
}
