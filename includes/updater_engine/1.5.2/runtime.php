<?php
declare(strict_types=1);

/**
 * Sokna Updater Engine 1.5.2
 *
 * Versioned in-app update engine.
 * New engine versions are installed beside the active engine and activated
 * only after application Health Check succeeds. No cPanel step is required.
 *
 * Runtime flow: validate -> stage -> restore point -> maintenance -> apply ->
 * health check -> complete, with automatic rollback on any live failure.
 */

const SOKNA_UPDATER_RUNTIME_VERSION = '1.5.2';
const SOKNA_UPDATER_JOB_FORMAT = 'sokna-updater-job-v1';
const SOKNA_UPDATER_MANIFEST_FORMAT = 'sokna-release-v2';
const SOKNA_UPDATER_ENGINE = 'sokna-updater-engine-1.5.2';

function updater_root(): string { return defined('SOKNA_UPDATER_ROOT') ? rtrim((string)constant('SOKNA_UPDATER_ROOT'), DIRECTORY_SEPARATOR . '/') : dirname(__DIR__); }
function updater_storage_dir(): string { return updater_root() . '/storage/updater'; }
function updater_archive_entry_is_safe(string $path): bool
{
    $path = str_replace('\\', '/', $path);
    return $path !== '' && $path[0] !== '/' && !preg_match('#(^|/)\.\.(/|$)#', $path) && !str_contains($path, "\0");
}
function updater_clear_directory(string $directory): void
{
    if (!is_dir($directory)) return;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) {
        if ($file->isLink() || $file->isFile()) {
            if (!@unlink($file->getPathname()) && file_exists($file->getPathname())) throw new RuntimeException('پاکسازی فایل موقت انجام نشد.');
        } elseif ($file->isDir() && !@rmdir($file->getPathname()) && is_dir($file->getPathname())) {
            throw new RuntimeException('پاکسازی پوشه موقت انجام نشد.');
        }
    }
    @rmdir($directory);
}
function updater_ensure_storage(): void
{
    $storage = updater_root() . '/storage';
    if (!is_dir($storage) && !mkdir($storage, 0750, true) && !is_dir($storage)) throw new RuntimeException('پوشه storage ساخته نشد.');
    $dir = updater_storage_dir();
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) throw new RuntimeException('پوشه امن Updater ساخته نشد.');
    updater_guard_directory($storage);
    updater_guard_directory($dir);
}
function updater_disk_check(int $estimatedBytes = 0): void
{
    updater_ensure_storage();
    $free = @disk_free_space(updater_storage_dir());
    $required = max(20 * 1024 * 1024, max(0, $estimatedBytes) * 2);
    if ($free !== false && $free < $required) throw new RuntimeException('فضای آزاد سرور برای Restore Point و نصب امن کافی نیست.');
}
function updater_pending_dir(): string
{
    $dir = updater_storage_dir() . '/work';
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) throw new RuntimeException('پوشه امن به‌روزرسانی ساخته نشد.');
    updater_guard_directory(updater_storage_dir());
    updater_guard_directory($dir);
    return $dir;
}
function updater_restore_dir(): string
{
    $dir = updater_storage_dir() . '/restore';
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) throw new RuntimeException('پوشه Restore Point ساخته نشد.');
    updater_guard_directory($dir);
    return $dir;
}
function updater_history_file(): string { return updater_storage_dir() . '/history.jsonl'; }
function updater_guard_directory(string $dir): void
{
    if (!is_dir($dir)) return;
    $deny = "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n";
    if (!is_file($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', $deny, LOCK_EX);
    if (!is_file($dir . '/index.html')) @file_put_contents($dir . '/index.html', '', LOCK_EX);
}
function updater_limit_text(string $value, int $length): string
{
    return function_exists('mb_substr') ? (string)call_user_func('mb_substr', $value, 0, $length, 'UTF-8') : substr($value, 0, $length);
}
function updater_safe_id(string $id): bool { return (bool)preg_match('/^[a-f0-9]{24}$/', $id); }
function updater_runtime_paths(): array
{
    $version = defined('SOKNA_UPDATER_ENGINE_VERSION') ? (string)SOKNA_UPDATER_ENGINE_VERSION : SOKNA_UPDATER_RUNTIME_VERSION;
    return [
        'admin/update/index.php',
        'includes/updater_engine/' . $version . '/runtime.php',
        'includes/updater_engine/' . $version . '/console.php',
    ];
}
function updater_immutable_paths(): array { return updater_runtime_paths(); }
function updater_validate_recovery_runtime(): void
{
    foreach (updater_runtime_paths() as $relative) {
        $path = updater_root() . '/' . $relative;
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('موتور مستقل به‌روزرسانی کامل نیست: ' . $relative);
        }
        $lint = updater_php_lint($path);
        if (!$lint['ok']) {
            throw new RuntimeException('موتور مستقل به‌روزرسانی Syntax معتبر ندارد: ' . $relative . ' — ' . $lint['message']);
        }
    }
    $runtimeSource = (string)file_get_contents(SOKNA_UPDATER_ENGINE_DIR . '/runtime.php');
    foreach (['function updater_start(', 'function sru_step(', 'function sru_fail_and_rollback(', 'function sru_start_rollback('] as $contract) {
        if (!str_contains($runtimeSource, $contract)) {
            throw new RuntimeException('قرارداد موتور مستقل به‌روزرسانی ناقص است.');
        }
    }
    $consoleSource = (string)file_get_contents(SOKNA_UPDATER_ENGINE_DIR . '/console.php');
    if (!str_contains($consoleSource, 'updater_retire_legacy_runtime')) {
        throw new RuntimeException('مرکز مستقل به موتور استاندارد متصل نیست.');
    }
}

function updater_engine_pointer_path(): string { return updater_root() . '/storage/updater-engine.json'; }
function updater_engine_pointer(): array
{
    $path = updater_engine_pointer_path();
    if (!is_file($path)) return [];
    try {
        $decoded = json_decode((string)file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : [];
    } catch (Throwable) {
        return [];
    }
}
function updater_activate_engine(string $version): void
{
    if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) throw new RuntimeException('نسخه موتور مقصد معتبر نیست.');
    $dir = updater_root() . '/includes/updater_engine/' . $version;
    foreach (['runtime.php','console.php'] as $file) {
        $path = $dir . '/' . $file;
        if (!is_file($path) || !updater_php_lint($path)['ok']) throw new RuntimeException('موتور مقصد کامل یا سالم نیست: ' . $version);
    }
    $current = defined('SOKNA_UPDATER_ENGINE_VERSION') ? (string)SOKNA_UPDATER_ENGINE_VERSION : SOKNA_UPDATER_RUNTIME_VERSION;
    $pointer = ['format'=>'sokna-updater-engine-pointer-v1','current'=>$version,'previous'=>$version === $current ? (string)(updater_engine_pointer()['previous'] ?? '') : $current,'activated_at'=>date(DATE_ATOM)];
    $storage = updater_root() . '/storage';
    if (!is_dir($storage) && !mkdir($storage,0750,true) && !is_dir($storage)) throw new RuntimeException('پوشه storage برای فعال‌سازی موتور ساخته نشد.');
    $temp = updater_engine_pointer_path() . '.tmp-' . bin2hex(random_bytes(4));
    $json = json_encode($pointer, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
    if (file_put_contents($temp,$json,LOCK_EX)===false || !rename($temp,updater_engine_pointer_path())) { @unlink($temp); throw new RuntimeException('فعال‌سازی اتمی موتور جدید انجام نشد.'); }
}
function updater_retire_legacy_runtime(): void
{
    if (!defined('SOKNA_UPDATER_ENGINE_VERSION')) return;
    $pointer = updater_engine_pointer();
    if (($pointer['current'] ?? '') === '') {
        try { updater_activate_engine((string)SOKNA_UPDATER_ENGINE_VERSION); } catch (Throwable) { return; }
    }
    foreach (['admin/update.php','includes/updater_runtime.php'] as $relative) {
        $path = updater_root() . '/' . $relative;
        if (is_file($path)) @unlink($path);
    }
}

function updater_core_paths(): array
{
    return ['VERSION.txt','bootstrap.php','index.php','login.php','includes/functions.php','includes/auth.php','includes/maintenance.php','includes/panel_layout.php'];
}
function updater_protected_path(string $path): bool
{
    $path = trim(str_replace('\\', '/', $path), '/');
    foreach (array_merge(['config.php','install.lock','.git','storage','uploads'], updater_runtime_paths()) as $protected) {
        if ($path === $protected || str_starts_with($path, $protected . '/')) return true;
    }
    return false;
}
function updater_normalize_path(string $path): string
{
    $path = trim(str_replace('\\', '/', $path), '/');
    if ($path === '' || str_contains($path, "\0") || str_starts_with($path, '/') || preg_match('#(^|/)\.\.(/|$)#', $path) || updater_protected_path($path)) {
        throw new RuntimeException('مسیر غیرمجاز در بسته انتشار: ' . $path);
    }
    if (strlen($path) > 240) throw new RuntimeException('طول یکی از مسیرهای بسته بیش از حد مجاز است.');
    return $path;
}
function updater_target_path(string $relative): string
{
    $relative = updater_normalize_path($relative);
    $root = realpath(updater_root());
    if (!$root) throw new RuntimeException('مسیر اصلی پروژه پیدا نشد.');
    $cursor = $root;
    $parts = explode('/', $relative); array_pop($parts);
    foreach ($parts as $part) { $cursor .= DIRECTORY_SEPARATOR . $part; if (is_link($cursor)) throw new RuntimeException('مسیر مقصد شامل پیوند نمادین غیرمجاز است: ' . $relative); }
    return $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
}
function updater_validate_manifest(array $manifest): array
{
    if (($manifest['format'] ?? '') !== SOKNA_UPDATER_MANIFEST_FORMAT) throw new RuntimeException('قالب بسته انتشار شناخته‌شده نیست.');
    if (($manifest['package_type'] ?? '') !== 'update') throw new RuntimeException('این صفحه فقط بسته استاندارد به‌روزرسانی را می‌پذیرد.');
    $runtime = trim((string)($manifest['min_updater'] ?? ''));
    if ($runtime === '' || version_compare(SOKNA_UPDATER_RUNTIME_VERSION, $runtime, '<')) throw new RuntimeException('این بسته به Updater نسخه ' . ($runtime ?: 'جدیدتر') . ' نیاز دارد.');
    $version = trim((string)($manifest['version'] ?? ''));
    if (!preg_match('/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$/', $version)) throw new RuntimeException('شماره نسخه مقصد معتبر نیست.');
    $current = trim((string)@file_get_contents(updater_root() . '/VERSION.txt')) ?: 'unknown';
    if (version_compare($version, $current, '<=')) throw new RuntimeException('نسخه مقصد باید از نسخه نصب‌شده جدیدتر باشد.');
    $from = $manifest['from_versions'] ?? [];
    if (!is_array($from) || !in_array($current, array_map('strval', $from), true)) throw new RuntimeException('این بسته برای ارتقا از نسخه فعلی ' . $current . ' ساخته نشده است.');
    $minPhp = trim((string)($manifest['min_php'] ?? '8.1.0'));
    if (version_compare(PHP_VERSION, $minPhp, '<')) throw new RuntimeException('این بسته به PHP ' . $minPhp . ' یا جدیدتر نیاز دارد.');
    $extensions = $manifest['required_extensions'] ?? [];
    if (!is_array($extensions)) throw new RuntimeException('فهرست افزونه‌های PHP معتبر نیست.');
    $skipExtensionCheck = PHP_SAPI === 'cli' && defined('SOKNA_UPDATER_TEST_SKIP_EXTENSIONS') && constant('SOKNA_UPDATER_TEST_SKIP_EXTENSIONS') === true;
    foreach ($extensions as $extension) {
        $extension = trim((string)$extension);
        if (!$skipExtensionCheck && $extension !== '' && !extension_loaded($extension)) throw new RuntimeException('افزونه PHP موردنیاز نصب نیست: ' . $extension);
    }
    $files = $manifest['files'] ?? [];
    if (!is_array($files) || !$files || count($files) > 2000) throw new RuntimeException('فهرست فایل‌های بسته خالی یا بیش از حد بزرگ است.');
    $normalized=[];
    foreach ($files as $file) {
        if (!is_array($file)) throw new RuntimeException('ساختار یکی از فایل‌های Manifest معتبر نیست.');
        $path=updater_normalize_path((string)($file['path']??''));
        if (isset($normalized[$path])) throw new RuntimeException('مسیر تکراری در Manifest: ' . $path);
        $sha=strtolower(trim((string)($file['sha256']??''))); if(!preg_match('/^[a-f0-9]{64}$/',$sha)) throw new RuntimeException('Checksum فایل معتبر نیست: '.$path);
        $size=(int)($file['size']??-1); if($size<0||$size>50*1024*1024) throw new RuntimeException('اندازه فایل مجاز نیست: '.$path);
        $normalized[$path]=['path'=>$path,'sha256'=>$sha,'size'=>$size];
    }
    $engineVersion = trim((string)($manifest['updater_engine'] ?? ''));
    if ($engineVersion !== '') {
        if (!preg_match('/^\d+\.\d+\.\d+$/', $engineVersion)) throw new RuntimeException('نسخه موتور Updater معتبر نیست.');
        foreach (['runtime.php','console.php'] as $engineFile) {
            $enginePath = 'includes/updater_engine/' . $engineVersion . '/' . $engineFile;
            if (!isset($normalized[$enginePath]) && $engineVersion !== SOKNA_UPDATER_RUNTIME_VERSION) {
                throw new RuntimeException('بسته موتور جدید را کامل حمل نمی‌کند: ' . $enginePath);
            }
        }
    }
    if (!isset($normalized['VERSION.txt'])) throw new RuntimeException('بسته باید VERSION.txt را شامل شود.');
    $delete=[];
    foreach (($manifest['delete']??[]) as $path) { $path=updater_normalize_path((string)$path); if(isset($normalized[$path])) throw new RuntimeException('یک مسیر هم‌زمان برای نصب و حذف تعیین شده است: '.$path); $delete[$path]=$path; }
    $migration=$manifest['migration']??null;
    if ($migration!==null) {
        if(!is_array($migration)) throw new RuntimeException('تعریف Migration معتبر نیست.');
        $migrationPath=trim(str_replace('\\','/',(string)($migration['path']??'')),'/');
        if(!preg_match('#^migrations/[A-Za-z0-9._-]+\.sql$#',$migrationPath)) throw new RuntimeException('مسیر Migration معتبر نیست.');
        $migrationSha=strtolower(trim((string)($migration['sha256']??''))); if(!preg_match('/^[a-f0-9]{64}$/',$migrationSha)) throw new RuntimeException('Checksum Migration معتبر نیست.');
        $migration=['path'=>$migrationPath,'sha256'=>$migrationSha];
    }
    $manifest['version']=$version; $manifest['current_version']=$current; $manifest['files']=array_values($normalized); $manifest['delete']=array_values($delete); $manifest['migration']=$migration;
    $manifest['release_notes']=updater_limit_text(trim((string)($manifest['release_notes']??'')),5000);
    return $manifest;
}


function updater_archive_file_path(string $packagePath, string $entry): string
{
    return 'phar://' . $packagePath . '/' . ltrim($entry, '/');
}

function updater_archive_entries(PharData $archive, string $packagePath): array
{
    $entries = [];
    $prefix = 'phar://' . str_replace('\\', '/', $packagePath) . '/';
    foreach (new RecursiveIteratorIterator($archive) as $file) {
        if ($file->isDir()) continue;
        if ($file->isLink()) throw new RuntimeException('بسته شامل پیوند نمادین غیرمجاز است.');
        $entry = str_replace('\\', '/', $file->getPathname());
        if (str_starts_with($entry, $prefix)) $entry = substr($entry, strlen($prefix));
        if (!updater_archive_entry_is_safe($entry)) throw new RuntimeException('بسته شامل مسیر ناامن است.');
        $entries[$entry] = ['size' => (int)$file->getSize()];
    }
    return $entries;
}

function updater_php_lint(string $path): array
{
    if (!str_ends_with(strtolower($path), '.php')) return ['checked' => false, 'ok' => true, 'message' => ''];
    if (!is_file($path) || !is_readable($path)) {
        return ['checked' => true, 'ok' => false, 'message' => 'فایل PHP برای بررسی قابل خواندن نیست.'];
    }

    $source = file_get_contents($path);
    if ($source === false) {
        return ['checked' => true, 'ok' => false, 'message' => 'خواندن فایل PHP برای بررسی Syntax انجام نشد.'];
    }

    try {
        token_get_all($source, TOKEN_PARSE);
        return ['checked' => true, 'ok' => true, 'message' => 'No syntax errors detected'];
    } catch (ParseError $e) {
        $message = trim($e->getMessage());
        $line = $e->getLine();
        if ($line > 0 && !str_contains($message, 'line ')) $message .= ' در خط ' . $line;
        return ['checked' => true, 'ok' => false, 'message' => $message];
    } catch (Throwable $e) {
        return ['checked' => false, 'ok' => true, 'message' => 'بررسی Syntax داخلی PHP در این سرور در دسترس نبود؛ اعتبارسنجی Checksum ادامه یافت.'];
    }
}

function updater_validate_package(string $packagePath, bool $stage = false, ?string $stageDir = null): array
{
    if (!is_file($packagePath) || strtolower(pathinfo($packagePath, PATHINFO_EXTENSION)) !== 'zip') {
        throw new RuntimeException('فقط بسته ZIP استاندارد پذیرفته می‌شود.');
    }
    if ((filesize($packagePath) ?: 0) > 30 * 1024 * 1024) throw new RuntimeException('حجم بسته بیشتر از حد مجاز است.');
    try {
        $archive = new PharData($packagePath);
    } catch (Throwable $e) {
        throw new RuntimeException('بسته ZIP قابل خواندن نیست.', 0, $e);
    }
    $entries = updater_archive_entries($archive, $packagePath);
    if (!isset($entries['release-manifest.json'])) throw new RuntimeException('فایل release-manifest.json در بسته وجود ندارد.');
    $manifestRaw = (string)$archive['release-manifest.json']->getContent();
    if (strlen($manifestRaw) > 1024 * 1024) throw new RuntimeException('Manifest بیش از حد بزرگ است.');
    try {
        $decodedManifest = json_decode($manifestRaw, true, 128, JSON_THROW_ON_ERROR);
        if (!is_array($decodedManifest)) throw new RuntimeException('Manifest باید یک شیء JSON باشد.');
        $manifest = updater_validate_manifest($decodedManifest);
    } catch (JsonException $e) {
        throw new RuntimeException('Manifest JSON معتبر نیست.', 0, $e);
    }
    $allowedEntries = ['release-manifest.json' => true];
    $warnings = [];
    $total = 0;
    if ($stage) {
        if (!$stageDir) throw new RuntimeException('مسیر مرحله‌بندی مشخص نیست.');
        if (is_dir($stageDir)) updater_clear_directory($stageDir);
        if (!is_dir($stageDir) && !mkdir($stageDir, 0750, true) && !is_dir($stageDir)) throw new RuntimeException('پوشه مرحله‌بندی ساخته نشد.');
    }
    foreach ($manifest['files'] as $file) {
        $entry = 'files/' . $file['path'];
        $allowedEntries[$entry] = true;
        if (!isset($entries[$entry])) throw new RuntimeException('فایل اعلام‌شده در بسته وجود ندارد: ' . $file['path']);
        if ($entries[$entry]['size'] !== $file['size']) throw new RuntimeException('اندازه فایل با Manifest هماهنگ نیست: ' . $file['path']);
        $actual = hash_file('sha256', updater_archive_file_path($packagePath, $entry));
        if (!$actual || !hash_equals($file['sha256'], $actual)) throw new RuntimeException('Checksum فایل تأیید نشد: ' . $file['path']);
        $total += $file['size'];
        if ($total > 100 * 1024 * 1024) throw new RuntimeException('حجم بازشده بسته بیش از حد مجاز است.');
        if ($stage) {
            $target = rtrim($stageDir, '/') . '/files/' . $file['path'];
            $parent = dirname($target);
            if (!is_dir($parent) && !mkdir($parent, 0750, true) && !is_dir($parent)) throw new RuntimeException('ساخت پوشه مرحله‌بندی انجام نشد.');
            if (!copy(updater_archive_file_path($packagePath, $entry), $target)) throw new RuntimeException('مرحله‌بندی فایل انجام نشد: ' . $file['path']);
            $lint = updater_php_lint($target);
            if (!$lint['ok']) throw new RuntimeException('خطای Syntax در ' . $file['path'] . ': ' . $lint['message']);
            if (!$lint['checked'] && $lint['message'] !== '') $warnings[] = $lint['message'];
        }
    }
    if (trim((string)file_get_contents(updater_archive_file_path($packagePath, 'files/VERSION.txt'))) !== $manifest['version']) {
        throw new RuntimeException('محتوای VERSION.txt با نسخه Manifest یکسان نیست.');
    }
    if ($manifest['migration']) {
        $entry = $manifest['migration']['path'];
        $allowedEntries[$entry] = true;
        if (!isset($entries[$entry])) throw new RuntimeException('فایل Migration در بسته وجود ندارد.');
        $actual = hash_file('sha256', updater_archive_file_path($packagePath, $entry));
        if (!$actual || !hash_equals($manifest['migration']['sha256'], $actual)) throw new RuntimeException('Checksum Migration تأیید نشد.');
        if ($stage) {
            $target = rtrim($stageDir, '/') . '/' . $entry;
            if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0750, true) && !is_dir(dirname($target))) throw new RuntimeException('پوشه Migration ساخته نشد.');
            if (!copy(updater_archive_file_path($packagePath, $entry), $target)) throw new RuntimeException('مرحله‌بندی Migration انجام نشد.');
        }
    }
    foreach (array_keys($entries) as $entry) {
        if (!isset($allowedEntries[$entry])) throw new RuntimeException('فایل اعلام‌نشده در بسته وجود دارد: ' . $entry);
    }
    updater_disk_check($total);
    foreach ($manifest['files'] as $file) updater_target_path($file['path']);
    foreach ($manifest['delete'] as $path) updater_target_path($path);
    return ['manifest' => $manifest, 'warnings' => array_values(array_unique($warnings)), 'total_unpacked_bytes' => $total, 'package_sha256' => hash_file('sha256', $packagePath)];
}

function updater_prepare_package(string $uploadedPath, string $originalName, int $actorUserId): array
{
    updater_ensure_storage();
    $id = bin2hex(random_bytes(12));
    $packagePath = updater_pending_dir() . '/package-' . $id . '.zip';
    $stageDir = updater_pending_dir() . '/stage-' . $id;
    if (!rename($uploadedPath, $packagePath)) {
        if (!copy($uploadedPath, $packagePath)) throw new RuntimeException('ذخیره بسته در مسیر محافظت‌شده انجام نشد.');
        @unlink($uploadedPath);
    }
    try {
        $validation = updater_validate_package($packagePath, true, $stageDir);
        $pending = [
            'format' => 'sokna-updater-pending-v1',
            'id' => $id,
            'original_name' => updater_limit_text($originalName, 180),
            'package_path' => $packagePath,
            'stage_dir' => $stageDir,
            'actor_user_id' => $actorUserId,
            'created_at' => date(DATE_ATOM),
            'validation' => $validation,
        ];
        file_put_contents(updater_pending_dir() . '/pending-' . $id . '.json', json_encode($pending, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX);
        return $pending;
    } catch (Throwable $e) {
        @unlink($packagePath);
        updater_remove_tree($stageDir);
        throw $e;
    }
}

function updater_pending(string $id, ?int $actorUserId = null): array
{
    if (!updater_safe_id($id)) throw new RuntimeException('شناسه بسته معتبر نیست.');
    $path = updater_pending_dir() . '/pending-' . $id . '.json';
    if (!is_file($path)) throw new RuntimeException('بسته آماده نصب پیدا نشد یا منقضی شده است.');
    $pending = json_decode((string)file_get_contents($path), true, 128, JSON_THROW_ON_ERROR);
    if (($pending['format'] ?? '') !== 'sokna-updater-pending-v1' || ($pending['id'] ?? '') !== $id) throw new RuntimeException('اطلاعات بسته آماده نصب معتبر نیست.');
    if ($actorUserId !== null && (int)($pending['actor_user_id'] ?? 0) !== $actorUserId) throw new RuntimeException('این بسته توسط حساب دیگری آماده شده است.');
    if (strtotime((string)($pending['created_at'] ?? '')) < time() - 2 * 3600) {
        updater_discard_pending($id);
        throw new RuntimeException('زمان اعتبار بسته آماده نصب پایان یافته است.');
    }
    return $pending;
}

function updater_remove_tree(string $directory): void
{
    if (!is_dir($directory)) return;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $file) {
        $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
    }
    @rmdir($directory);
}

function updater_discard_pending(string $id): void
{
    // Engine 1.5 has one pending cleanup owner. Keep the public compatibility name only.
    sru_discard_pending($id);
}

function updater_record_history(array $entry): void
{
    // Engine 1.5 has one history writer. Keep the public compatibility name only.
    sru_history($entry);
}

function updater_history(int $limit = 20): array
{
    $file = updater_history_file();
    if (!is_file($file)) return [];
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $rows = [];
    foreach (array_reverse($lines) as $line) {
        $row = json_decode($line, true);
        if (is_array($row)) $rows[] = $row;
        if (count($rows) >= $limit) break;
    }
    return $rows;
}

function updater_job_path(string $id): string
{
    if (!updater_safe_id($id)) throw new RuntimeException('شناسه عملیات به‌روزرسانی معتبر نیست.');
    return updater_pending_dir() . '/job-' . $id . '.json';
}

function updater_job_write(array $job): void
{
    $path = updater_job_path((string)($job['id'] ?? ''));
    $job['updated_at'] = date(DATE_ATOM);
    $json = json_encode($job, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $temp = $path . '.tmp-' . bin2hex(random_bytes(4));
    if (file_put_contents($temp, $json, LOCK_EX) === false || !rename($temp, $path)) {
        @unlink($temp);
        throw new RuntimeException('ثبت پایدار عملیات به‌روزرسانی انجام نشد.');
    }
    @chmod($path, 0640);
}

function updater_job_read(string $id, ?int $actorUserId = null): array
{
    $path = updater_job_path($id);
    if (!is_file($path)) throw new RuntimeException('عملیات به‌روزرسانی پیدا نشد.');
    $job = json_decode((string)file_get_contents($path), true);
    if (!is_array($job) || ($job['format'] ?? '') !== SOKNA_UPDATER_JOB_FORMAT) throw new RuntimeException('فایل وضعیت عملیات معتبر نیست.');
    if ($actorUserId !== null && (int)($job['actor_user_id'] ?? 0) !== $actorUserId) throw new RuntimeException('این عملیات متعلق به مدیر دیگری است.');
    return $job;
}

function updater_job_public(array $job): array
{
    $manifest = is_array($job['manifest'] ?? null) ? $job['manifest'] : [];
    return [
        'id' => (string)($job['id'] ?? ''),
        'mode' => (string)($job['mode'] ?? 'update'),
        'status' => (string)($job['status'] ?? 'unknown'),
        'stage' => (string)($job['stage'] ?? 'unknown'),
        'message' => (string)($job['message'] ?? ''),
        'progress' => max(0, min(100, (int)($job['progress'] ?? 0))),
        'from_version' => (string)($manifest['current_version'] ?? $job['from_version'] ?? ''),
        'to_version' => (string)($manifest['version'] ?? $job['to_version'] ?? ''),
        'error' => (string)($job['error'] ?? ''),
        'rollback_available' => (bool)($job['rollback_available'] ?? false),
        'restore_id' => (string)($job['restore_id'] ?? ''),
        'created_at' => (string)($job['created_at'] ?? ''),
        'updated_at' => (string)($job['updated_at'] ?? ''),
    ];
}

function updater_latest_job(int $actorUserId): ?array
{
    $files = glob(updater_pending_dir() . '/job-*.json') ?: [];
    usort($files, static fn(string $a, string $b): int => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));
    foreach ($files as $path) {
        $id = preg_replace('/^job-|\.json$/', '', basename($path));
        if (!is_string($id) || !updater_safe_id($id)) continue;
        try {
            $job = updater_job_read($id, $actorUserId);
            if (in_array((string)($job['status'] ?? ''), ['running', 'recovery_required', 'failed', 'completed'], true)) return $job;
        } catch (Throwable) {
            continue;
        }
    }
    return null;
}

function updater_rescue_material(): array
{
    $token = bin2hex(random_bytes(32));
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $raw = '';
    for ($i = 0; $i < 12; $i++) $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    $code = implode('-', str_split($raw, 4));
    return [
        'token' => $token,
        'token_hash' => hash('sha256', $token),
        'code' => $code,
        'code_hash' => hash('sha256', $raw),
        'expires_at' => date(DATE_ATOM, time() + 24 * 3600),
    ];
}

function updater_set_rescue_cookie(string $jobId, string $token, string $expiresAt): void
{
    if ($jobId === '' || $token === '') return;
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/admin/update/'));
    $root = rtrim(dirname(dirname($script)), '/');
    $path = ($root === '' || $root === '.') ? '/' : $root . '/';
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((int)($_SERVER['SERVER_PORT'] ?? 80) === 443);
    setcookie('sokna_updater_recovery', $jobId . '.' . $token, [
        'expires' => strtotime($expiresAt) ?: time() + 24 * 3600,
        'path' => $path,
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

function updater_start(string $pendingId, int $actorUserId): array
{
    updater_validate_recovery_runtime();
    $pending = updater_pending($pendingId, $actorUserId);
    $validation = updater_validate_package((string)$pending['package_path'], false);
    if (!hash_equals((string)$pending['validation']['package_sha256'], (string)$validation['package_sha256'])) {
        throw new RuntimeException('بسته پس از بررسی اولیه تغییر کرده است.');
    }
    $active = updater_latest_job($actorUserId);
    if ($active && in_array((string)($active['status'] ?? ''), ['running','recovery_required'], true)) return $active;

    $manifest = $validation['manifest'];
    $immutable = array_flip(updater_immutable_paths());
    foreach ($manifest['delete'] as $path) {
        if (isset($immutable[(string)$path])) throw new RuntimeException('موتور مستقل بازیابی قابل حذف نیست: ' . $path);
    }

    $core = array_flip(updater_core_paths());
    $regular = [];
    $deferred = [];
    $snapshot = [];
    foreach ($manifest['files'] as $file) {
        $path = (string)$file['path'];
        if (isset($immutable[$path])) {
            $installed = updater_root() . '/' . $path;
            $actual = is_file($installed) ? hash_file('sha256', $installed) : false;
            if (!$actual || !hash_equals((string)$file['sha256'], $actual)) {
                throw new RuntimeException('بسته عادی اجازه تغییر موتور مستقل بازیابی را ندارد: ' . $path);
            }
            continue; // Identical stable runtime may exist in full packages, but is never replaced live.
        }
        $snapshot[$path] = $path;
        if (isset($core[$path])) $deferred[] = $file; else $regular[] = $file;
    }
    foreach ($manifest['delete'] as $path) $snapshot[(string)$path] = (string)$path;

    $id = bin2hex(random_bytes(12));
    $restoreId = bin2hex(random_bytes(12));
    $restorePath = updater_restore_dir() . '/restore-' . $restoreId;
    if (!is_dir(dirname($restorePath)) && !mkdir(dirname($restorePath), 0750, true) && !is_dir(dirname($restorePath))) {
        throw new RuntimeException('پوشه Restore Point ساخته نشد.');
    }
    if (!mkdir($restorePath, 0750, true) && !is_dir($restorePath)) throw new RuntimeException('Restore Point ساخته نشد.');
    $deny = "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n";
    if (!is_file(dirname($restorePath) . '/.htaccess')) @file_put_contents(dirname($restorePath) . '/.htaccess', $deny, LOCK_EX);
    foreach (array_keys($snapshot) as $relative) {
        $target = updater_target_path($relative);
        $parent = dirname($target);
        while (!is_dir($parent) && dirname($parent) !== $parent) $parent = dirname($parent);
        if (is_file($target) && !is_writable($target)) throw new RuntimeException('فایل مقصد قابل جایگزینی نیست: ' . $relative);
        if (!is_dir($parent) || !is_writable($parent)) throw new RuntimeException('پوشه مقصد قابل نوشتن نیست: ' . $relative);
    }

    $required = (int)$validation['total_unpacked_bytes'];
    foreach (array_keys($snapshot) as $relative) {
        try {
            $target = updater_target_path($relative);
            if (is_file($target)) $required += filesize($target) ?: 0;
        } catch (Throwable) {}
    }
    updater_disk_check($required + 10 * 1024 * 1024);
    $rescue = updater_rescue_material();

    $job = [
        'format' => SOKNA_UPDATER_JOB_FORMAT,
        'id' => $id,
        'mode' => 'update',
        'pending_id' => $pendingId,
        'actor_user_id' => $actorUserId,
        'status' => 'running',
        'stage' => 'snapshot_files',
        'message' => 'Restore Point فایل‌های درگیر ساخته می‌شود؛ سامانه هنوز فعال است.',
        'progress' => 2,
        'manifest' => $manifest,
        'package_sha256' => (string)$validation['package_sha256'],
        'stage_dir' => (string)$pending['stage_dir'],
        'regular_files' => $regular,
        'deferred_files' => $deferred,
        'snapshot_paths' => array_values($snapshot),
        'snapshot_index' => 0,
        'regular_index' => 0,
        'deferred_index' => 0,
        'delete_index' => 0,
        'restore_id' => $restoreId,
        'restore_path' => $restorePath,
        'restore_map' => [],
        'database_ready' => false,
        'database_sha256' => '',
        'migration_applied' => false,
        'live_changes_started' => false,
        'rollback_available' => false,
        'rescue_token_hash' => (string)$rescue['token_hash'],
        'rescue_code_hash' => (string)$rescue['code_hash'],
        'rescue_expires_at' => (string)$rescue['expires_at'],
        'rescue_runtime' => SOKNA_UPDATER_ENGINE,
        'error' => '',
        'created_at' => date(DATE_ATOM),
        'updated_at' => date(DATE_ATOM),
    ];
    updater_job_write($job);
    $job['_rescue_token'] = (string)$rescue['token'];
    $job['_rescue_code'] = (string)$rescue['code'];
    return $job;
}

function updater_restore_points(int $limit = 5): array
{
    $base = updater_restore_dir();
    if (!is_dir($base)) return [];
    $dirs = glob($base . '/restore-*', GLOB_ONLYDIR) ?: [];
    usort($dirs, static fn(string $a, string $b): int => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));
    $rows = [];
    foreach ($dirs as $dir) {
        $path = $dir . '/restore-point.json';
        if (!is_file($path)) continue;
        $manifest = json_decode((string)file_get_contents($path), true);
        if (!is_array($manifest) || ($manifest['format'] ?? '') !== 'sokna-restore-point-v1' || empty($manifest['completed_at'])) continue;
        $rows[] = [
            'restore_id' => (string)($manifest['restore_id'] ?? ''),
            'from_version' => (string)($manifest['from_version'] ?? ''),
            'to_version' => (string)($manifest['to_version'] ?? ''),
            'created_at' => (string)($manifest['created_at'] ?? ''),
            'completed_at' => (string)($manifest['completed_at'] ?? ''),
            'database_ready' => (bool)($manifest['database_ready'] ?? false),
            'migration_applied' => (bool)($manifest['migration_applied'] ?? false),
            'file_count' => is_array($manifest['restore_map'] ?? null) ? count($manifest['restore_map']) : 0,
        ];
        if (count($rows) >= $limit) break;
    }
    return $rows;
}

function sru_root(): string { return defined('SOKNA_RECOVERY_ROOT') ? rtrim((string)constant('SOKNA_RECOVERY_ROOT'), DIRECTORY_SEPARATOR . '/') : updater_root(); }

function sru_storage(): string { return updater_root() . '/storage'; }

function sru_updates_dir(): string { return updater_pending_dir(); }

function sru_restore_dir(): string { return updater_restore_dir(); }

function sru_safe_id(string $id): bool
{
    return (bool)preg_match('/^[a-f0-9]{24}$/', $id);
}

function sru_job_path(string $id): string
{
    if (!sru_safe_id($id)) throw new RuntimeException('شناسه عملیات معتبر نیست.');
    return sru_updates_dir() . '/job-' . $id . '.json';
}

function sru_job_read(string $id, ?int $actorId = null): array
{
    $path = sru_job_path($id);
    if (!is_file($path)) throw new RuntimeException('عملیات به‌روزرسانی پیدا نشد.');
    $job = json_decode((string)file_get_contents($path), true);
    if (!is_array($job) || ($job['format'] ?? '') !== SOKNA_UPDATER_JOB_FORMAT) {
        throw new RuntimeException('فایل وضعیت عملیات معتبر نیست.');
    }
    if ($actorId !== null && (int)($job['actor_user_id'] ?? 0) !== $actorId) {
        throw new RuntimeException('این عملیات متعلق به مدیر دیگری است.');
    }
    return $job;
}

function sru_job_write(array $job): void
{
    $path = sru_job_path((string)($job['id'] ?? ''));
    $job['updated_at'] = date(DATE_ATOM);
    $json = json_encode($job, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $temp = $path . '.tmp-' . bin2hex(random_bytes(4));
    if (file_put_contents($temp, $json, LOCK_EX) === false || !rename($temp, $path)) {
        @unlink($temp);
        throw new RuntimeException('ثبت پایدار وضعیت عملیات انجام نشد.');
    }
    @chmod($path, 0640);
}

function sru_job_public(array $job): array
{
    $manifest = is_array($job['manifest'] ?? null) ? $job['manifest'] : [];
    return [
        'id' => (string)($job['id'] ?? ''),
        'mode' => (string)($job['mode'] ?? 'update'),
        'status' => (string)($job['status'] ?? 'unknown'),
        'stage' => (string)($job['stage'] ?? 'unknown'),
        'message' => (string)($job['message'] ?? ''),
        'progress' => max(0, min(100, (int)($job['progress'] ?? 0))),
        'from_version' => (string)($manifest['current_version'] ?? $job['from_version'] ?? ''),
        'to_version' => (string)($manifest['version'] ?? $job['to_version'] ?? ''),
        'error' => (string)($job['error'] ?? ''),
        'rollback_available' => (bool)($job['rollback_available'] ?? false),
        'restore_id' => (string)($job['restore_id'] ?? ''),
        'created_at' => (string)($job['created_at'] ?? ''),
        'updated_at' => (string)($job['updated_at'] ?? ''),
    ];
}

function sru_lock(string $id, callable $callback): mixed
{
    updater_ensure_storage();
    $global = fopen(updater_root() . '/storage/operations.lock', 'c+');
    if (!$global || !flock($global, LOCK_EX | LOCK_NB)) {
        if (is_resource($global)) fclose($global);
        throw new RuntimeException('یک عملیات پشتیبان‌گیری، بازیابی، به‌روزرسانی یا بازگشت دیگر در حال اجراست.');
    }
    $handle = fopen(sru_updates_dir() . '/job-' . $id . '.lock', 'c+');
    if (!$handle || !flock($handle, LOCK_EX | LOCK_NB)) {
        if (is_resource($handle)) fclose($handle);
        flock($global, LOCK_UN); fclose($global);
        throw new RuntimeException('مرحله قبلی هنوز در حال اجراست؛ چند ثانیه دیگر دوباره تلاش کنید.');
    }
    try {
        return $callback();
    } finally {
        flock($handle, LOCK_UN); fclose($handle);
        flock($global, LOCK_UN); fclose($global);
    }
}

function sru_normalize_path(string $path): string
{
    $path = trim(str_replace('\\', '/', $path), '/');
    if ($path === '' || str_contains($path, "\0") || preg_match('#(^|/)\.\.(/|$)#', $path) || str_starts_with($path, '/')) {
        throw new RuntimeException('مسیر فایل در عملیات امن نیست.');
    }
    foreach (['config.php', 'install.lock', '.git', 'storage', 'uploads'] as $protected) {
        if ($path === $protected || str_starts_with($path, $protected . '/')) {
            throw new RuntimeException('مسیر محافظت‌شده قابل تغییر نیست: ' . $path);
        }
    }
    return $path;
}

function sru_target(string $relative): string
{
    $relative = sru_normalize_path($relative);
    $root = realpath(sru_root());
    if (!$root) throw new RuntimeException('ریشه پروژه پیدا نشد.');
    $cursor = $root;
    $parts = explode('/', $relative);
    array_pop($parts);
    foreach ($parts as $part) {
        $cursor .= DIRECTORY_SEPARATOR . $part;
        if (is_link($cursor)) throw new RuntimeException('مسیر مقصد شامل پیوند نمادین است: ' . $relative);
    }
    return $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
}

function sru_atomic_copy(string $source, string $target): void
{
    if (!is_file($source) || !is_readable($source)) throw new RuntimeException('فایل مرحله‌بندی‌شده قابل خواندن نیست: ' . basename($source));
    $parent = dirname($target);
    if (!is_dir($parent) && !mkdir($parent, 0755, true) && !is_dir($parent)) throw new RuntimeException('پوشه مقصد ساخته نشد.');
    if (!is_writable($parent)) throw new RuntimeException('پوشه مقصد قابل نوشتن نیست: ' . $parent);
    $temp = $target . '.sokna-update-' . bin2hex(random_bytes(4));
    if (!copy($source, $temp)) throw new RuntimeException('کپی موقت فایل انجام نشد: ' . basename($target));
    $mode = is_file($target) ? (fileperms($target) & 0777) : 0644;
    @chmod($temp, $mode ?: 0644);
    if (!@rename($temp, $target)) {
        @unlink($temp);
        throw new RuntimeException('فعال‌سازی اتمیک فایل انجام نشد: ' . basename($target));
    }
    if (function_exists('opcache_invalidate')) @opcache_invalidate($target, true);
}

function sru_remove_tree(string $dir): void
{
    if ($dir === '' || !is_dir($dir)) return;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $file) {
        $path = $file->getPathname();
        if ($file->isDir() && !$file->isLink()) @rmdir($path); else @unlink($path);
    }
    @rmdir($dir);
}

function sru_set_maintenance(string $message, int $actorId): void
{
    $path = sru_storage() . '/maintenance.json';
    $state = [
        'active' => true,
        'mode' => 'update',
        'message' => $message,
        'started_at' => date(DATE_ATOM),
        'actor_user_id' => $actorId,
        'engine' => SOKNA_UPDATER_ENGINE,
    ];
    if (file_put_contents($path, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX) === false) {
        throw new RuntimeException('حالت نگهداری فعال نشد.');
    }
}

function sru_clear_maintenance(): void
{
    @unlink(sru_storage() . '/maintenance.json');
}

function sru_config(): array
{
    $path = sru_root() . '/config.php';
    if (!is_file($path)) throw new RuntimeException('فایل تنظیمات سامانه پیدا نشد.');
    $config = require $path;
    if (!is_array($config) || !is_array($config['db'] ?? null)) throw new RuntimeException('تنظیمات دیتابیس معتبر نیست.');
    return $config;
}

function sru_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $config = sru_config();
    $db = $config['db'];
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $db['host'], $db['port'] ?? '3306', $db['name'], $db['charset'] ?? 'utf8mb4');
    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function sru_sql_value(PDO $pdo, mixed $value): string
{
    if ($value === null) return 'NULL';
    if (is_bool($value)) return $value ? '1' : '0';
    return $pdo->quote((string)$value);
}

function sru_db_dump_file(PDO $pdo, string $path): array
{
    $stream = fopen($path, 'wb');
    if (!$stream) throw new RuntimeException('فایل Restore Point دیتابیس ساخته نشد.');
    $hash = hash_init('sha256');
    $bytes = 0;
    $write = static function (string $chunk) use ($stream, $hash, &$bytes): void {
        $length = strlen($chunk);
        $offset = 0;
        while ($offset < $length) {
            $written = fwrite($stream, substr($chunk, $offset));
            if ($written === false || $written === 0) throw new RuntimeException('نوشتن Restore Point دیتابیس کامل نشد.');
            $offset += $written;
        }
        hash_update($hash, $chunk);
        $bytes += $length;
    };

    $owns = !$pdo->inTransaction();
    if ($owns) {
        $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
    }
    try {
        $write("SET FOREIGN_KEY_CHECKS=0;\n-- SOKNA-STMT --\n");
        $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
        foreach ($tables as $row) {
            $table = (string)($row[0] ?? '');
            if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) continue;
            $qt = '`' . $table . '`';
            $create = $pdo->query('SHOW CREATE TABLE ' . $qt)->fetch(PDO::FETCH_NUM);
            $createSql = (string)($create[1] ?? '');
            if ($createSql === '') throw new RuntimeException('ساختار جدول ' . $table . ' خوانده نشد.');
            $write('DROP TABLE IF EXISTS ' . $qt . ";\n-- SOKNA-STMT --\n" . $createSql . ";\n-- SOKNA-STMT --\n");
            $columns = [];
            foreach ($pdo->query('SHOW FULL COLUMNS FROM ' . $qt)->fetchAll(PDO::FETCH_ASSOC) as $column) {
                if (str_contains(strtoupper((string)($column['Extra'] ?? '')), 'GENERATED')) continue;
                $name = (string)($column['Field'] ?? '');
                if ($name !== '') $columns[] = $name;
            }
            if (!$columns) continue;
            $columnSql = implode(',', array_map(static fn(string $name): string => '`' . str_replace('`', '``', $name) . '`', $columns));
            $options = [];
            if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) $options[(int)constant('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')] = false;
            $stmt = $pdo->prepare('SELECT ' . $columnSql . ' FROM ' . $qt, $options);
            $stmt->execute();
            $batch = [];
            while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $batch[] = '(' . implode(',', array_map(static fn(mixed $value): string => sru_sql_value($pdo, $value), array_values($data))) . ')';
                if (count($batch) >= 100) {
                    $write('INSERT INTO ' . $qt . ' (' . $columnSql . ') VALUES ' . implode(',', $batch) . ";\n-- SOKNA-STMT --\n");
                    $batch = [];
                }
            }
            $stmt->closeCursor();
            if ($batch) $write('INSERT INTO ' . $qt . ' (' . $columnSql . ') VALUES ' . implode(',', $batch) . ";\n-- SOKNA-STMT --\n");
        }
        $write("SET FOREIGN_KEY_CHECKS=1;\n-- SOKNA-STMT --\n");
        if ($owns) $pdo->commit();
        fflush($stream);
        return ['sha256' => hash_final($hash), 'bytes' => $bytes];
    } catch (Throwable $e) {
        if ($owns && $pdo->inTransaction()) $pdo->rollBack();
        @unlink($path);
        throw $e;
    } finally {
        fclose($stream);
    }
}

function sru_sql_statements(string $sql): array
{
    $parts = preg_split('/\R-- SOKNA-STMT --\R/', trim($sql));
    return array_values(array_filter(array_map('trim', $parts ?: []), static fn(string $part): bool => $part !== ''));
}

function sru_db_restore(PDO $pdo, string $path): void
{
    if (!is_file($path)) throw new RuntimeException('Restore Point دیتابیس پیدا نشد.');
    $sql = (string)file_get_contents($path);
    $statements = sru_sql_statements($sql);
    if (!$statements) throw new RuntimeException('Restore Point دیتابیس خالی است.');
    foreach ($statements as $statement) $pdo->exec($statement);
}

function sru_restore_path(array $job): string
{
    $path = (string)($job['restore_path'] ?? '');
    $base = realpath(sru_restore_dir());
    $parent = $path !== '' ? realpath(dirname($path)) : false;
    if (!$base || !$parent || $base !== $parent) throw new RuntimeException('مسیر Restore Point معتبر نیست.');
    return $path;
}

function sru_snapshot_file(array &$job, string $relative): void
{
    $relative = sru_normalize_path($relative);
    if (isset($job['restore_map'][$relative])) return;
    $target = sru_target($relative);
    $exists = is_file($target);
    $record = ['existed' => $exists, 'sha256' => null, 'size' => 0];
    if ($exists) {
        $restore = sru_restore_path($job) . '/files/' . $relative;
        if (!is_dir(dirname($restore)) && !mkdir(dirname($restore), 0750, true) && !is_dir(dirname($restore))) {
            throw new RuntimeException('پوشه Restore Point فایل ساخته نشد.');
        }
        if (!copy($target, $restore)) throw new RuntimeException('ذخیره نسخه قبلی فایل انجام نشد: ' . $relative);
        $record['sha256'] = hash_file('sha256', $restore) ?: null;
        $record['size'] = filesize($restore) ?: 0;
    }
    $job['restore_map'][$relative] = $record;
    sru_job_write($job);
}

function sru_valid_restore_source(string $path): string
{
    $base = realpath(sru_restore_dir());
    $parent = $path !== '' ? realpath(dirname($path)) : false;
    $resolved = $path !== '' ? realpath($path) : false;
    if (!$base || !$parent || $base !== $parent || !$resolved || $resolved !== $path) {
        throw new RuntimeException('مسیر Restore Point معتبر نیست.');
    }
    return $path;
}

function sru_restore_one_from(string $restorePath, string $relative, array $record): void
{
    $restorePath = sru_valid_restore_source($restorePath);
    $relative = sru_normalize_path($relative);
    $target = sru_target($relative);
    if (($record['existed'] ?? false) === true) {
        $source = $restorePath . '/files/' . $relative;
        $hash = is_file($source) ? hash_file('sha256', $source) : false;
        if (!$hash || !hash_equals((string)($record['sha256'] ?? ''), $hash)) throw new RuntimeException('نسخه قبلی فایل معتبر نیست: ' . $relative);
        sru_atomic_copy($source, $target);
    } elseif (is_file($target) || is_link($target)) {
        if (!@unlink($target)) throw new RuntimeException('حذف فایل تازه ایجادشده انجام نشد: ' . $relative);
    }
}

function sru_restore_one(array $job, string $relative, array $record): void
{
    sru_restore_one_from(sru_restore_path($job), $relative, $record);
}

function sru_restore_batch(array &$job, int $batchSize = 25): bool
{
    $map = array_reverse((array)($job['restore_map'] ?? []), true);
    $paths = array_keys($map);
    $index = max(0, (int)($job['recovery_index'] ?? 0));
    $end = min(count($paths), $index + max(1, $batchSize));
    for (; $index < $end; $index++) {
        $relative = (string)$paths[$index];
        sru_restore_one($job, $relative, (array)$map[$relative]);
        $job['recovery_index'] = $index + 1;
        sru_job_write($job);
    }
    return $index >= count($paths);
}

function sru_restore_external_batch(array &$job, string $sourcePath, array $restoreMap, int $batchSize = 25): bool
{
    $sourcePath = sru_valid_restore_source($sourcePath);
    $map = array_reverse($restoreMap, true);
    $paths = array_keys($map);
    $index = max(0, (int)($job['rollback_apply_index'] ?? 0));
    $end = min(count($paths), $index + max(1, $batchSize));
    for (; $index < $end; $index++) {
        $relative = (string)$paths[$index];
        sru_restore_one_from($sourcePath, $relative, (array)$map[$relative]);
        $job['rollback_apply_index'] = $index + 1;
        sru_job_write($job);
    }
    return $index >= count($paths);
}

function sru_restore_files(array $job): array
{
    $errors = [];
    foreach (array_reverse((array)($job['restore_map'] ?? []), true) as $relative => $record) {
        try { sru_restore_one($job, (string)$relative, (array)$record); }
        catch (Throwable $e) { $errors[] = $relative . ': ' . $e->getMessage(); }
    }
    return $errors;
}

function sru_health(array $job, string $expectedVersion): array
{
    $checks = [];
    $checks['version'] = trim((string)@file_get_contents(sru_root() . '/VERSION.txt')) === $expectedVersion;
    foreach (array_merge(['bootstrap.php','login.php','includes/auth.php','api/create_order.php'], updater_runtime_paths()) as $file) {
        $checks['file_' . str_replace(['/', '.'], '_', $file)] = is_file(sru_root() . '/' . $file) && is_readable(sru_root() . '/' . $file);
    }
    foreach ((array)($job['manifest']['files'] ?? []) as $file) {
        if (!is_array($file)) continue;
        $relative = (string)($file['path'] ?? '');
        $target = sru_target($relative);
        $actual = is_file($target) ? hash_file('sha256', $target) : false;
        $checks['hash_' . substr(hash('sha256', $relative), 0, 12)] = is_string($actual) && hash_equals((string)($file['sha256'] ?? ''), $actual);
    }
    foreach ((array)($job['manifest']['delete'] ?? []) as $relative) {
        $relative = (string)$relative;
        $checks['deleted_' . substr(hash('sha256', $relative), 0, 12)] = !is_file(sru_target($relative));
    }
    if (PHP_SAPI === 'cli' && defined('SOKNA_RECOVERY_TEST_SKIP_DB') && constant('SOKNA_RECOVERY_TEST_SKIP_DB') === true) {
        $checks['database_test_seam'] = true;
    } else {
        try {
            $pdo = sru_db();
            $tables = array_map(static fn(array $row): string => (string)array_values($row)[0], $pdo->query('SHOW TABLES')->fetchAll());
            foreach (['settings', 'users', 'items', 'orders', 'order_items', 'cafe_tables'] as $table) {
                $checks['table_' . $table] = in_array($table, $tables, true);
            }
            $checks['db_query'] = (int)$pdo->query('SELECT 1')->fetchColumn() === 1;
            $schemaHealthPath = sru_root() . '/includes/schema_health.php';
            if (is_file($schemaHealthPath) && is_readable($schemaHealthPath)) {
                require_once $schemaHealthPath;
                if (!function_exists('sokna_schema_health_checks')) {
                    $checks['schema_health_contract'] = false;
                } else {
                    $schemaChecks = sokna_schema_health_checks($pdo);
                    if (!is_array($schemaChecks) || $schemaChecks === []) {
                        $checks['schema_health_contract'] = false;
                    } else {
                        foreach ($schemaChecks as $name => $result) {
                            if (!is_string($name) || !preg_match('/^[a-z0-9_]{1,80}$/', $name)) continue;
                            $checks['schema_' . $name] = $result === true;
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            $checks['database'] = false;
            $checks['database_error'] = $e->getMessage();
        }
    }
    return ['ok' => !in_array(false, $checks, true), 'checks' => $checks, 'checked_at' => date(DATE_ATOM)];
}

function sru_restored_health(array $job): array
{
    $checks = [];
    $expectedVersion = (string)($job['manifest']['current_version'] ?? $job['from_version'] ?? '');
    $checks['version'] = $expectedVersion !== '' && trim((string)@file_get_contents(sru_root() . '/VERSION.txt')) === $expectedVersion;
    foreach ((array)($job['restore_map'] ?? []) as $relative => $record) {
        try {
            $target = sru_target((string)$relative);
            if (($record['existed'] ?? false) === true) {
                $actual = is_file($target) ? hash_file('sha256', $target) : false;
                $checks['file_' . substr(hash('sha256', (string)$relative), 0, 12)] = is_string($actual) && hash_equals((string)($record['sha256'] ?? ''), $actual);
            } else {
                $checks['removed_' . substr(hash('sha256', (string)$relative), 0, 12)] = !is_file($target) && !is_link($target);
            }
        } catch (Throwable) {
            $checks['file_' . substr(hash('sha256', (string)$relative), 0, 12)] = false;
        }
    }
    if (PHP_SAPI === 'cli' && defined('SOKNA_RECOVERY_TEST_SKIP_DB') && constant('SOKNA_RECOVERY_TEST_SKIP_DB') === true) {
        $checks['database_test_seam'] = true;
    } else {
        try {
            $pdo = sru_db();
            $tables = array_map(static fn(array $row): string => (string)array_values($row)[0], $pdo->query('SHOW TABLES')->fetchAll());
            foreach (['settings', 'users', 'items', 'orders', 'order_items', 'cafe_tables'] as $table) {
                $checks['table_' . $table] = in_array($table, $tables, true);
            }
            $checks['db_query'] = (int)$pdo->query('SELECT 1')->fetchColumn() === 1;
            $schemaHealthPath = sru_root() . '/includes/schema_health.php';
            if (is_file($schemaHealthPath) && is_readable($schemaHealthPath)) {
                require_once $schemaHealthPath;
                if (!function_exists('sokna_schema_health_checks')) {
                    $checks['schema_health_contract'] = false;
                } else {
                    $schemaChecks = sokna_schema_health_checks($pdo);
                    if (!is_array($schemaChecks) || $schemaChecks === []) {
                        $checks['schema_health_contract'] = false;
                    } else {
                        foreach ($schemaChecks as $name => $result) {
                            if (!is_string($name) || !preg_match('/^[a-z0-9_]{1,80}$/', $name)) continue;
                            $checks['schema_' . $name] = $result === true;
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            $checks['database'] = false;
            $checks['database_error'] = $e->getMessage();
        }
    }
    return ['ok' => !in_array(false, $checks, true), 'checks' => $checks, 'checked_at' => date(DATE_ATOM)];
}

function sru_discard_pending(string $id): void
{
    if (!sru_safe_id($id)) return;
    $base = sru_updates_dir();
    $pendingPath = $base . '/pending-' . $id . '.json';
    $pending = is_file($pendingPath) ? json_decode((string)file_get_contents($pendingPath), true) : null;
    if (is_array($pending)) {
        $package = (string)($pending['package_path'] ?? '');
        $stage = (string)($pending['stage_dir'] ?? '');
        if ($package !== '' && dirname($package) === $base) @unlink($package);
        if ($stage !== '' && dirname($stage) === $base) sru_remove_tree($stage);
    }
    @unlink($pendingPath);
}

function sru_prune_restore_points(int $keep = 3, string $preserveId = ''): void
{
    $keep = max(2, min(10, $keep));
    $dirs = glob(sru_restore_dir() . '/restore-*', GLOB_ONLYDIR) ?: [];
    usort($dirs, static fn(string $a, string $b): int => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));
    $kept = 0;
    foreach ($dirs as $dir) {
        $id = preg_replace('/^restore-/', '', basename($dir));
        if ($id === $preserveId || $kept < $keep) {
            $kept++;
            continue;
        }
        sru_remove_tree($dir);
    }
}

function updater_actor_snapshot(int $actorUserId): array
{
    if ($actorUserId <= 0) return [];
    try {
        $stmt = sru_db()->prepare("SELECT id,username,display_name FROM users WHERE id=? LIMIT 1");
        $stmt->execute([$actorUserId]);
        $row = $stmt->fetch();
        if (!is_array($row)) return ['actor_user_id'=>$actorUserId];
        return [
            'actor_user_id' => (int)$row['id'],
            'actor_username' => updater_limit_text((string)($row['username'] ?? ''), 120),
            'actor_display_name' => updater_limit_text((string)($row['display_name'] ?? ''), 160),
        ];
    } catch (Throwable) {
        return ['actor_user_id'=>$actorUserId];
    }
}

function updater_history_write_ready(): bool
{
    updater_ensure_storage();
    $file = updater_history_file();
    return is_file($file) ? is_writable($file) : is_writable(dirname($file));
}

function sru_history(array $entry): bool
{
    updater_ensure_storage();
    $entry['recorded_at'] = date(DATE_ATOM);
    $actorId = (int)($entry['actor_user_id'] ?? ($_SESSION['user']['id'] ?? 0));
    if ($actorId > 0) $entry = array_merge($entry, updater_actor_snapshot($actorId));
    if (empty($entry['event_code'])) {
        if (!empty($entry['action'])) $entry['event_code'] = (string)$entry['action'];
        elseif (!empty($entry['cancelled_before_apply'])) $entry['event_code'] = 'update_cancelled_before_apply';
        elseif (!empty($entry['recovery_required'])) $entry['event_code'] = 'update_recovery_required';
        elseif (!empty($entry['rolled_back']) && empty($entry['success'])) $entry['event_code'] = 'update_automatic_rollback';
        elseif (!empty($entry['success'])) $entry['event_code'] = 'operation_completed';
        else $entry['event_code'] = 'operation_failed';
    }
    try {
        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        $ok = file_put_contents(updater_history_file(), $line, FILE_APPEND | LOCK_EX);
        if ($ok === false) throw new RuntimeException('history_write_failed');
        return true;
    } catch (Throwable $e) {
        error_log('[SOKNA_UPDATER_AUDIT] event=' . preg_replace('/[^a-z0-9_.-]+/i','_', (string)($entry['event_code'] ?? 'unknown')) . ' result=write_failed');
        return false;
    }
}

function sru_restore_manifest_write(array $job): void
{
    $restore = sru_restore_path($job);
    $manifest = [
        'format' => 'sokna-restore-point-v1',
        'restore_id' => (string)$job['restore_id'],
        'from_version' => (string)($job['manifest']['current_version'] ?? $job['from_version'] ?? ''),
        'to_version' => (string)($job['manifest']['version'] ?? $job['to_version'] ?? ''),
        'created_at' => (string)$job['created_at'],
        'completed_at' => $job['status'] === 'completed' ? date(DATE_ATOM) : null,
        'database_sha256' => (string)($job['database_sha256'] ?? ''),
        'database_ready' => (bool)($job['database_ready'] ?? false),
        'restore_map' => (array)($job['restore_map'] ?? []),
        'migration_applied' => (bool)($job['migration_applied'] ?? false),
    ];
    $json = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    if (file_put_contents($restore . '/restore-point.json', $json, LOCK_EX) === false) throw new RuntimeException('ثبت Restore Point انجام نشد.');
}

function sru_fail_and_rollback(array $job, Throwable $error): array
{
    $job['original_error'] = $error->getMessage();
    if (($job['live_changes_started'] ?? false) !== true) {
        sru_clear_maintenance();
        $job['status'] = 'failed';
        $job['stage'] = 'failed_before_apply';
        $job['progress'] = 100;
        $job['rollback_available'] = false;
        $job['error'] = 'به‌روزرسانی پیش از تغییر فایل‌های زنده متوقف شد: ' . $error->getMessage();
        $job['message'] = $job['error'];
        sru_job_write($job);
        sru_history(['event_code'=>'update_failed_before_apply','success'=>false,'rolled_back'=>false,'actor_user_id'=>(int)($job['actor_user_id']??0),'from_version'=>(string)($job['manifest']['current_version']??''),'to_version'=>(string)($job['manifest']['version']??''),'message'=>$error->getMessage(),'engine'=>SOKNA_UPDATER_ENGINE]);
        return $job;
    }

    try { sru_set_maintenance('به‌روزرسانی متوقف شد و نسخه قبل در حال بازیابی است.', (int)$job['actor_user_id']); } catch (Throwable) {}
    $job['status'] = 'recovery_required';
    $job['stage'] = 'recovery_files';
    $job['progress'] = 94;
    $job['recovery_index'] = 0;
    $job['recovery_database_done'] = false;
    $job['rollback_available'] = true;
    $job['error'] = 'به‌روزرسانی ناموفق بود؛ بازیابی مرحله‌ای نسخه قبل شروع شد: ' . $error->getMessage();
    $job['message'] = 'فایل‌های نسخه قبل در چند مرحله بازگردانده می‌شوند.';
    sru_job_write($job);
    sru_history(['event_code'=>'update_recovery_required','success'=>false,'recovery_required'=>true,'actor_user_id'=>(int)($job['actor_user_id']??0),'from_version'=>(string)($job['manifest']['current_version']??''),'to_version'=>(string)($job['manifest']['version']??''),'message'=>$error->getMessage(),'engine'=>SOKNA_UPDATER_ENGINE]);
    return $job;
}

function sru_retry_recovery(array $job): array
{
    try {
        $stage = (string)($job['stage'] ?? 'recovery_files');
        if ($stage === 'recovery_required') { $stage='recovery_files'; $job['stage']=$stage; $job['recovery_index']=0; }
        if ($stage === 'recovery_files') {
            $done = sru_restore_batch($job, 25);
            $total = count((array)($job['restore_map'] ?? []));
            $index = (int)($job['recovery_index'] ?? 0);
            $job['progress'] = 94 + (int)floor(($index / max(1,$total)) * 3);
            $job['message'] = 'بازیابی فایل‌ها: ' . $index . ' از ' . $total;
            if ($done) { $job['stage']='recovery_database'; $job['message']='Restore Point دیتابیس بررسی می‌شود.'; }
            sru_job_write($job);
            return $job;
        }
        if ($stage === 'recovery_database') {
            if (($job['database_ready'] ?? false) && !($job['recovery_database_done'] ?? false)) {
                $dbPath = sru_restore_path($job) . '/database.sql';
                $actual = is_file($dbPath) ? hash_file('sha256', $dbPath) : false;
                if (!$actual || !hash_equals((string)($job['database_sha256'] ?? ''), $actual)) throw new RuntimeException('Checksum دیتابیس Restore Point معتبر نیست.');
                sru_db_restore(sru_db(), $dbPath);
            }
            $job['recovery_database_done']=true;
            $job['stage']='recovery_verify';
            $job['progress']=98;
            $job['message']='سلامت نسخه بازگردانده‌شده بررسی می‌شود.';
            sru_job_write($job);
            return $job;
        }
        if ($stage === 'recovery_verify') {
            $health=sru_restored_health($job);
            $job['rollback_health']=$health;
            if(!$health['ok']) throw new RuntimeException('Health Check نسخه بازگردانده‌شده کامل نبود.');
            sru_clear_maintenance();
            $job['status']='failed'; $job['stage']='rolled_back'; $job['progress']=100; $job['rollback_available']=false;
            $job['error']='به‌روزرسانی ناموفق بود، اما سامانه کامل به نسخه قبل بازگشت. علت اولیه: '.(string)($job['original_error']??'نامشخص');
            $job['message']=$job['error'];
            sru_job_write($job);
            sru_history(['event_code'=>'update_automatic_rollback','success'=>false,'rolled_back'=>true,'actor_user_id'=>(int)($job['actor_user_id']??0),'from_version'=>(string)($job['manifest']['current_version']??''),'to_version'=>(string)($job['manifest']['version']??''),'message'=>'Automatic rollback completed','engine'=>SOKNA_UPDATER_ENGINE]);
            return $job;
        }
        throw new RuntimeException('مرحله بازیابی شناخته‌شده نیست.');
    } catch (Throwable $e) {
        $job['status']='recovery_required';
        $job['progress']=99;
        $job['rollback_available']=true;
        $job['error']='بازیابی هنوز کامل نشده است: '.$e->getMessage();
        $job['message']='سامانه برای حفاظت از داده‌ها در حالت نگهداری باقی مانده است. دوباره ادامه بازیابی را بزنید.';
        sru_job_write($job);
        return $job;
    }
}

function sru_step_update(array $job): array
{
    $manifest = (array)($job['manifest'] ?? []);
    $allPaths = (array)($job['snapshot_paths'] ?? []);
    $regular = (array)($job['regular_files'] ?? []);
    $deferred = (array)($job['deferred_files'] ?? []);
    $delete = (array)($manifest['delete'] ?? []);

    switch ((string)$job['stage']) {
        case 'snapshot_files':
            $index = (int)($job['snapshot_index'] ?? 0);
            $end = min(count($allPaths), $index + 25);
            for (; $index < $end; $index++) sru_snapshot_file($job, (string)$allPaths[$index]);
            $job['snapshot_index'] = $index;
            if ($index >= count($allPaths)) {
                $job['stage'] = 'snapshot_database';
                $job['message'] = 'عملیات نوشتنی متوقف و Restore Point دیتابیس ساخته می‌شود.';
                $job['progress'] = 20;
            } else {
                $job['message'] = 'ساخت Restore Point فایل‌ها: ' . $index . ' از ' . count($allPaths);
                $job['progress'] = 3 + (int)floor(($index / max(1, count($allPaths))) * 16);
            }
            sru_job_write($job);
            return $job;

        case 'snapshot_database':
            sru_set_maintenance('سامانه در حال به‌روزرسانی امن است؛ چند دقیقه دیگر دوباره تلاش کنید.', (int)$job['actor_user_id']);
            if (is_array($manifest['migration'] ?? null)) {
                $dbPath = sru_restore_path($job) . '/database.sql';
                $dump = sru_db_dump_file(sru_db(), $dbPath);
                $job['database_sha256'] = (string)$dump['sha256'];
                $job['database_ready'] = true;
                $job['message'] = 'Restore Point دیتابیس ساخته شد؛ فایل‌های نسخه جدید نصب می‌شوند.';
            } else {
                $job['database_sha256'] = '';
                $job['database_ready'] = false;
                $job['message'] = 'این نسخه تغییر دیتابیس ندارد؛ فایل‌های نسخه جدید نصب می‌شوند.';
            }
            sru_restore_manifest_write($job);
            $job['stage'] = 'apply_regular';
            $job['progress'] = 28;
            sru_job_write($job);
            return $job;

        case 'apply_regular':
            $index = (int)($job['regular_index'] ?? 0);
            $end = min(count($regular), $index + 20);
            for (; $index < $end; $index++) {
                $file = $regular[$index];
                $relative = (string)$file['path'];
                $source = (string)$job['stage_dir'] . '/files/' . $relative;
                $actual = is_file($source) ? hash_file('sha256', $source) : false;
                if (!$actual || !hash_equals((string)$file['sha256'], $actual)) throw new RuntimeException('فایل مرحله‌بندی‌شده تغییر کرده است: ' . $relative);
                $job['live_changes_started'] = true;
                sru_job_write($job);
                sru_atomic_copy($source, sru_target($relative));
                $job['regular_index'] = $index + 1;
                sru_job_write($job);
            }
            if ($index >= count($regular)) {
                $job['stage'] = 'delete';
                $job['message'] = 'حذف‌های کنترل‌شده بررسی می‌شوند.';
                $job['progress'] = 68;
            } else {
                $job['progress'] = 28 + (int)floor(($index / max(1, count($regular))) * 39);
                $job['message'] = 'نصب فایل‌ها: ' . $index . ' از ' . count($regular);
            }
            sru_job_write($job);
            return $job;

        case 'delete':
            $index = (int)($job['delete_index'] ?? 0);
            $end = min(count($delete), $index + 20);
            for (; $index < $end; $index++) {
                $target = sru_target((string)$delete[$index]);
                if (is_file($target) && !@unlink($target)) throw new RuntimeException('حذف کنترل‌شده انجام نشد: ' . $delete[$index]);
                $job['delete_index'] = $index + 1;
                sru_job_write($job);
            }
            if ($index >= count($delete)) {
                $job['stage'] = 'migration';
                $job['message'] = 'تغییرات پایگاه داده بررسی می‌شوند.';
                $job['progress'] = 73;
            } else {
                $job['message'] = 'حذف‌های کنترل‌شده: ' . $index . ' از ' . count($delete);
                $job['progress'] = 68 + (int)floor(($index / max(1, count($delete))) * 4);
            }
            sru_job_write($job);
            return $job;

        case 'migration':
            $migration = $manifest['migration'] ?? null;
            if (is_array($migration)) {
                $path = (string)$job['stage_dir'] . '/' . (string)$migration['path'];
                $actual = is_file($path) ? hash_file('sha256', $path) : false;
                if (!$actual || !hash_equals((string)$migration['sha256'], $actual)) throw new RuntimeException('Migration مرحله‌بندی‌شده معتبر نیست.');
                $sql = (string)file_get_contents($path);
                foreach (preg_split('/\R-- CAFE-STMT --\R/', trim($sql)) ?: [] as $statement) {
                    $statement = trim($statement);
                    if ($statement !== '') sru_db()->exec($statement);
                }
                $job['migration_applied'] = true;
                sru_job_write($job);
            }
            $job['stage'] = 'apply_deferred';
            $job['message'] = 'هسته سامانه در آخرین مرحله جایگزین می‌شود.';
            $job['progress'] = 80;
            sru_job_write($job);
            return $job;

        case 'apply_deferred':
            $index = (int)($job['deferred_index'] ?? 0);
            $end = min(count($deferred), $index + 12);
            for (; $index < $end; $index++) {
                $file = $deferred[$index];
                $relative = (string)$file['path'];
                $source = (string)$job['stage_dir'] . '/files/' . $relative;
                $actual = is_file($source) ? hash_file('sha256', $source) : false;
                if (!$actual || !hash_equals((string)$file['sha256'], $actual)) throw new RuntimeException('فایل هسته مرحله‌بندی‌شده معتبر نیست: ' . $relative);
                $job['live_changes_started'] = true;
                sru_job_write($job);
                sru_atomic_copy($source, sru_target($relative));
                $job['deferred_index'] = $index + 1;
                sru_job_write($job);
            }
            if ($index >= count($deferred)) {
                $job['stage'] = 'verify';
                $job['message'] = 'نسخه نصب‌شده مستقل از برنامه اصلی بررسی می‌شود.';
                $job['progress'] = 92;
            }
            sru_job_write($job);
            return $job;

        case 'verify':
            clearstatcache(true);
            if (function_exists('opcache_reset')) @opcache_reset();
            $health = sru_health($job, (string)$manifest['version']);
            if (!$health['ok']) throw new RuntimeException('Health Check نسخه جدید موفق نبود.');
            $job['health'] = $health;
            $targetEngine = trim((string)($manifest['updater_engine'] ?? ''));
            if ($targetEngine !== '') updater_activate_engine($targetEngine);
            $job['status'] = 'completed';
            $job['stage'] = 'completed';
            $job['message'] = 'نسخه جدید نصب و Health Check شد. امکان بازگشت به نسخه قبل حفظ شده است.';
            $job['progress'] = 100;
            $job['rollback_available'] = true;
            sru_restore_manifest_write($job);
            sru_discard_pending((string)($job['pending_id'] ?? ''));
            sru_prune_restore_points(3, (string)$job['restore_id']);
            sru_clear_maintenance();
            sru_job_write($job);
            sru_history([
                'event_code' => 'update_completed',
                'success' => true,
                'actor_user_id' => (int)($job['actor_user_id'] ?? 0),
                'from_version' => (string)$manifest['current_version'],
                'to_version' => (string)$manifest['version'],
                'restore_id' => (string)$job['restore_id'],
                'migration_required' => is_array($manifest['migration'] ?? null),
                'migration_applied' => (bool)($job['migration_applied'] ?? false),
                'message' => 'Safe update completed',
                'engine' => SOKNA_UPDATER_ENGINE,
            ]);
            return $job;
    }

    throw new RuntimeException('مرحله عملیات شناخته‌شده نیست.');
}

function sru_step_rollback(array $job): array
{
    $source = (string)($job['source_restore_path'] ?? '');
    $base = realpath(sru_restore_dir());
    $parent = $source !== '' ? realpath(dirname($source)) : false;
    if (!$base || !$parent || $base !== $parent || !is_file($source . '/restore-point.json')) throw new RuntimeException('Restore Point انتخاب‌شده معتبر نیست.');
    $manifest = json_decode((string)file_get_contents($source . '/restore-point.json'), true);
    if (!is_array($manifest) || ($manifest['format'] ?? '') !== 'sokna-restore-point-v1') throw new RuntimeException('Manifest بازگشت معتبر نیست.');

    switch ((string)$job['stage']) {
        case 'rollback_snapshot_files':
            $paths = array_keys((array)$manifest['restore_map']);
            $index = (int)($job['snapshot_index'] ?? 0);
            $end = min(count($paths), $index + 25);
            for (; $index < $end; $index++) sru_snapshot_file($job, (string)$paths[$index]);
            $job['snapshot_index'] = $index;
            if ($index >= count($paths)) {
                $job['stage'] = 'rollback_snapshot_database';
                $job['message'] = 'نسخه فعلی قبل از بازگشت ذخیره می‌شود.';
                $job['progress'] = 25;
            } else {
                $job['progress'] = 4 + (int)floor(($index / max(1, count($paths))) * 20);
            }
            sru_job_write($job);
            return $job;

        case 'rollback_snapshot_database':
            sru_set_maintenance('سامانه در حال بازگشت امن به نسخه قبل است.', (int)$job['actor_user_id']);
            if (($manifest['migration_applied'] ?? false) === true) {
                $dump = sru_db_dump_file(sru_db(), sru_restore_path($job) . '/database.sql');
                $job['database_sha256'] = (string)$dump['sha256'];
                $job['database_ready'] = true;
                $job['message'] = 'فایل‌ها و دیتابیس نسخه قبل بازگردانده می‌شوند.';
            } else {
                $job['database_sha256'] = '';
                $job['database_ready'] = false;
                $job['message'] = 'این نسخه Migration نداشته است؛ فقط فایل‌ها بازگردانده می‌شوند و داده‌های جاری حفظ می‌شوند.';
            }
            $job['stage'] = 'rollback_apply';
            $job['progress'] = 40;
            sru_job_write($job);
            return $job;

        case 'rollback_apply':
            $job['live_changes_started'] = true;
            sru_job_write($job);
            $map = (array)($manifest['restore_map'] ?? []);
            $done = sru_restore_external_batch($job, $source, $map, 25);
            $total = count($map);
            $index = (int)($job['rollback_apply_index'] ?? 0);
            if (!$done) {
                $job['message'] = 'بازگردانی فایل‌ها: ' . $index . ' از ' . $total;
                $job['progress'] = 40 + (int)floor(($index / max(1, $total)) * 35);
                sru_job_write($job);
                return $job;
            }
            $job['stage'] = 'rollback_database';
            $job['message'] = 'بازگردانی پایگاه داده بررسی می‌شود.';
            $job['progress'] = 78;
            sru_job_write($job);
            return $job;

        case 'rollback_database':
            if (($manifest['migration_applied'] ?? false) === true && !($job['rollback_database_done'] ?? false)) {
                $dbPath = $source . '/database.sql';
                $actual = is_file($dbPath) ? hash_file('sha256', $dbPath) : false;
                if (!$actual || !hash_equals((string)$manifest['database_sha256'], $actual)) throw new RuntimeException('دیتابیس Restore Point معتبر نیست.');
                sru_db_restore(sru_db(), $dbPath);
                $job['rollback_database_done'] = true;
                sru_job_write($job);
            }
            $job['stage'] = 'rollback_verify';
            $job['message'] = 'سلامت نسخه بازگردانده‌شده بررسی می‌شود.';
            $job['progress'] = 88;
            sru_job_write($job);
            return $job;

        case 'rollback_verify':
            clearstatcache(true);
            if (function_exists('opcache_reset')) @opcache_reset();
            $expected = (string)($manifest['from_version'] ?? '');
            $checks = [
                'version' => trim((string)@file_get_contents(sru_root() . '/VERSION.txt')) === $expected,
                'bootstrap' => is_file(sru_root() . '/bootstrap.php'),
                'login' => is_file(sru_root() . '/login.php'),
            ];
            if (PHP_SAPI === 'cli' && defined('SOKNA_RECOVERY_TEST_SKIP_DB') && constant('SOKNA_RECOVERY_TEST_SKIP_DB') === true) {
                $checks['database_test_seam'] = true;
            } else {
                try { $checks['database'] = (int)sru_db()->query('SELECT 1')->fetchColumn() === 1; }
                catch (Throwable) { $checks['database'] = false; }
            }
            if (in_array(false, $checks, true)) throw new RuntimeException('Health Check پس از بازگشت کامل نبود.');
            $job['status'] = 'completed';
            $job['stage'] = 'completed';
            $job['progress'] = 100;
            $job['message'] = 'سامانه با موفقیت به نسخه ' . $expected . ' بازگشت.';
            $job['rollback_available'] = true; // The safety snapshot can roll forward if needed.
            sru_restore_manifest_write($job);
            sru_prune_restore_points(3, (string)$job['restore_id']);
            sru_clear_maintenance();
            sru_job_write($job);
            sru_history([
                'event_code' => 'rollback_completed',
                'success' => true,
                'actor_user_id' => (int)($job['actor_user_id'] ?? 0),
                'from_version' => (string)($manifest['to_version'] ?? ''),
                'to_version' => $expected,
                'restore_id' => (string)$job['restore_id'],
                'message' => 'Manual rollback completed',
                'engine' => SOKNA_UPDATER_ENGINE,
            ]);
            return $job;
    }
    throw new RuntimeException('مرحله بازگشت شناخته‌شده نیست.');
}

function sru_step(string $id, int $actorId): array
{
    return sru_lock($id, function () use ($id, $actorId): array {
        $job = sru_job_read($id, $actorId);
        if (($job['status'] ?? '') === 'recovery_required') {
            @set_time_limit(120);
            return sru_retry_recovery($job);
        }
        if (($job['status'] ?? '') !== 'running') return $job;
        @set_time_limit(120);
        try {
            return (($job['mode'] ?? 'update') === 'rollback') ? sru_step_rollback($job) : sru_step_update($job);
        } catch (Throwable $e) {
            try { $job = sru_job_read($id, $actorId); } catch (Throwable) {}
            return sru_fail_and_rollback($job, $e);
        }
    });
}

function sru_abort(string $id, int $actorId): array
{
    return sru_lock($id, function () use ($id, $actorId): array {
        $job = sru_job_read($id, $actorId);
        if (($job['status'] ?? '') !== 'running') return $job;
        return sru_fail_and_rollback($job, new RuntimeException('عملیات با درخواست مدیر متوقف شد.'));
    });
}

function sru_latest_restore_points(int $limit = 5): array
{
    $dirs = glob(sru_restore_dir() . '/restore-*', GLOB_ONLYDIR) ?: [];
    usort($dirs, static fn(string $a, string $b): int => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));
    $rows = [];
    foreach ($dirs as $dir) {
        $path = $dir . '/restore-point.json';
        if (!is_file($path)) continue;
        $manifest = json_decode((string)file_get_contents($path), true);
        if (!is_array($manifest) || ($manifest['format'] ?? '') !== 'sokna-restore-point-v1' || empty($manifest['completed_at'])) continue;
        $rows[] = [
            'restore_id' => (string)($manifest['restore_id'] ?? basename($dir)),
            'from_version' => (string)($manifest['from_version'] ?? ''),
            'to_version' => (string)($manifest['to_version'] ?? ''),
            'created_at' => (string)($manifest['created_at'] ?? ''),
            'completed_at' => (string)($manifest['completed_at'] ?? ''),
            'path' => $dir,
        ];
        if (count($rows) >= $limit) break;
    }
    return $rows;
}

function sru_start_rollback(string $restoreId, int $actorId): array
{
    if (!preg_match('/^[a-f0-9]{24}$/', $restoreId)) throw new RuntimeException('شناسه Restore Point معتبر نیست.');
    $source = sru_restore_dir() . '/restore-' . $restoreId;
    if (!is_file($source . '/restore-point.json')) throw new RuntimeException('Restore Point پیدا نشد.');
    $manifest = json_decode((string)file_get_contents($source . '/restore-point.json'), true);
    if (!is_array($manifest)) throw new RuntimeException('Restore Point معتبر نیست.');
    $id = bin2hex(random_bytes(12));
    $safetyId = bin2hex(random_bytes(12));
    $safetyPath = sru_restore_dir() . '/restore-' . $safetyId;
    if (!mkdir($safetyPath, 0750, true) && !is_dir($safetyPath)) throw new RuntimeException('Restore Point ایمنی ساخته نشد.');
    $job = [
        'format' => SOKNA_UPDATER_JOB_FORMAT,
        'id' => $id,
        'mode' => 'rollback',
        'actor_user_id' => $actorId,
        'status' => 'running',
        'stage' => 'rollback_snapshot_files',
        'message' => 'نسخه فعلی پیش از بازگشت ایمن ذخیره می‌شود.',
        'progress' => 2,
        'from_version' => (string)($manifest['to_version'] ?? ''),
        'to_version' => (string)($manifest['from_version'] ?? ''),
        'source_restore_path' => $source,
        'restore_id' => $safetyId,
        'restore_path' => $safetyPath,
        'restore_map' => [],
        'snapshot_index' => 0,
        'rollback_apply_index' => 0,
        'rollback_database_done' => false,
        'database_ready' => false,
        'database_sha256' => '',
        'migration_applied' => (bool)($manifest['migration_applied'] ?? false),
        'live_changes_started' => false,
        'rollback_available' => false,
        'error' => '',
        'created_at' => date(DATE_ATOM),
        'updated_at' => date(DATE_ATOM),
    ];
    sru_job_write($job);
    return $job;
}

function sur_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((int)($_SERVER['SERVER_PORT'] ?? 80) === 443);
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => $https,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
        'use_only_cookies' => true,
    ]);
}

function sur_csrf_token(): string
{
    sur_session_start();
    $token = (string)($_SESSION['sokna_rescue_csrf'] ?? '');
    if ($token === '') {
        $token = bin2hex(random_bytes(24));
        $_SESSION['sokna_rescue_csrf'] = $token;
    }
    return $token;
}

function sur_csrf_valid(mixed $token): bool
{
    sur_session_start();
    if (!is_string($token) || $token === '') return false;
    $candidates = [
        (string)($_SESSION['sokna_rescue_csrf'] ?? ''),
        (string)($_SESSION['csrf_token'] ?? ''),
    ];
    foreach ($candidates as $candidate) {
        if ($candidate !== '' && hash_equals($candidate, $token)) return true;
    }
    return false;
}

function sur_maintenance_state(): array
{
    $path = sru_storage() . '/maintenance.json';
    if (!is_file($path)) return [];
    $state = json_decode((string)@file_get_contents($path), true);
    return is_array($state) ? $state : [];
}

function sur_latest_job(): ?array
{
    $files = glob(sru_updates_dir() . '/job-*.json') ?: [];
    usort($files, static fn(string $a, string $b): int => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));
    $fallback = null;
    foreach ($files as $path) {
        $id = preg_replace('/^job-|\.json$/', '', basename($path));
        if (!is_string($id) || !sru_safe_id($id)) continue;
        try { $job = sru_job_read($id); } catch (Throwable) { continue; }
        if ($fallback === null) $fallback = $job;
        if (in_array((string)($job['status'] ?? ''), ['running', 'recovery_required'], true)) return $job;
    }
    return $fallback;
}

function sur_cookie_name(): string
{
    return 'sokna_updater_recovery';
}

function sur_cookie_path(): string
{
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/admin/update/'));
    $root = rtrim(dirname(dirname($script)), '/');
    return ($root === '' || $root === '.') ? '/' : $root . '/';
}

function sur_set_cookie(string $jobId, string $token, int $expiresAt): void
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((int)($_SERVER['SERVER_PORT'] ?? 80) === 443);
    setcookie(sur_cookie_name(), $jobId . '.' . $token, [
        'expires' => $expiresAt,
        'path' => sur_cookie_path(),
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

function sur_clear_cookie(): void
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((int)($_SERVER['SERVER_PORT'] ?? 80) === 443);
    setcookie(sur_cookie_name(), '', [
        'expires' => time() - 3600,
        'path' => sur_cookie_path(),
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

function sur_normalize_code(string $code): string
{
    return strtoupper((string)preg_replace('/[^A-Z0-9]/i', '', trim($code)));
}

function sur_access_not_expired(array $job): bool
{
    $expires = strtotime((string)($job['rescue_expires_at'] ?? '')) ?: 0;
    return $expires > time();
}

function sur_cookie_authorizes(array $job): bool
{
    $cookie = trim((string)($_COOKIE[sur_cookie_name()] ?? ''));
    if ($cookie === '' || !str_contains($cookie, '.')) return false;
    [$jobId, $token] = explode('.', $cookie, 2);
    if (!hash_equals((string)($job['id'] ?? ''), $jobId) || $token === '' || !sur_access_not_expired($job)) return false;
    $expected = (string)($job['rescue_token_hash'] ?? '');
    return $expected !== '' && hash_equals($expected, hash('sha256', $token));
}

function sur_session_authorizes(array $job): bool
{
    sur_session_start();
    $user = $_SESSION['user'] ?? null;
    $last = (int)($_SESSION['user_last_activity'] ?? 0);
    if (is_array($user) && ($user['role'] ?? '') === 'admin' && $last > 0 && time() - $last <= 12 * 3600) {
        $_SESSION['user_last_activity'] = time();
        return true;
    }
    $auth = $_SESSION['sokna_rescue_auth'] ?? null;
    return is_array($auth)
        && hash_equals((string)($job['id'] ?? ''), (string)($auth['job_id'] ?? ''))
        && (int)($auth['expires_at'] ?? 0) > time();
}

function sur_authorize_session(array $job, string $method): void
{
    sur_session_start();
    $_SESSION['sokna_rescue_auth'] = [
        'job_id' => (string)$job['id'],
        'expires_at' => min(time() + 12 * 3600, strtotime((string)($job['rescue_expires_at'] ?? '')) ?: time() + 3600),
        'method' => $method,
    ];
    sur_csrf_token();
}

function sur_is_authorized(array $job): bool
{
    if (sur_session_authorizes($job)) return true;
    if (sur_cookie_authorizes($job)) {
        sur_authorize_session($job, 'cookie');
        return true;
    }
    return false;
}

function sur_rate_path(): string
{
    return sru_storage() . '/update-rescue-rate.json';
}

function sur_rate_key(): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return hash('sha256', $ip);
}

function sur_rate_state(): array
{
    $path = sur_rate_path();
    $state = is_file($path) ? json_decode((string)@file_get_contents($path), true) : [];
    return is_array($state) ? $state : [];
}

function sur_rate_allowed(): bool
{
    $state = sur_rate_state();
    $now = time();
    $attempts = array_values(array_filter((array)($state[sur_rate_key()] ?? []), static fn($ts): bool => is_int($ts) && $ts > $now - 600));
    return count($attempts) < 8;
}

function sur_rate_fail(): void
{
    $state = sur_rate_state();
    $key = sur_rate_key();
    $now = time();
    $attempts = array_values(array_filter((array)($state[$key] ?? []), static fn($ts): bool => is_int($ts) && $ts > $now - 600));
    $attempts[] = $now;
    $state[$key] = $attempts;
    @file_put_contents(sur_rate_path(), json_encode($state, JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function sur_rate_clear(): void
{
    $state = sur_rate_state();
    unset($state[sur_rate_key()]);
    @file_put_contents(sur_rate_path(), json_encode($state, JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function sur_admin_users(): array
{
    try {
        return sru_db()->query("SELECT id,username,display_name FROM users WHERE role='admin' AND active=1 ORDER BY display_name,id")->fetchAll() ?: [];
    } catch (Throwable) {
        return [];
    }
}

function sur_login_with_admin(string $identifier, string $password, array $job): bool
{
    $identifier = trim($identifier);
    if (!sur_rate_allowed() || $identifier === '' || $password === '') return false;
    try {
        if (ctype_digit($identifier)) {
            $stmt = sru_db()->prepare("SELECT id,username,password_hash,display_name,role FROM users WHERE id=? AND role='admin' AND active=1 LIMIT 1");
            $stmt->execute([(int)$identifier]);
        } else {
            $stmt = sru_db()->prepare("SELECT id,username,password_hash,display_name,role FROM users WHERE username=? AND role='admin' AND active=1 LIMIT 1");
            $stmt->execute([$identifier]);
        }
        $user = $stmt->fetch();
        if (!is_array($user) || !password_verify($password, (string)$user['password_hash'])) {
            sur_rate_fail();
            return false;
        }
        sur_session_start();
        session_regenerate_id(true);
        $_SESSION['user_last_activity'] = time();
        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'username' => (string)$user['username'],
            'display_name' => (string)$user['display_name'],
            'role' => 'admin',
        ];
        sur_authorize_session($job, 'admin-password');
        sur_rate_clear();
        return true;
    } catch (Throwable) {
        sur_rate_fail();
        return false;
    }
}

function sur_login_with_code(string $code, array $job): bool
{
    if (!sur_rate_allowed() || !sur_access_not_expired($job)) return false;
    $normalized = sur_normalize_code($code);
    $expected = (string)($job['rescue_code_hash'] ?? '');
    if ($normalized === '' || $expected === '' || !hash_equals($expected, hash('sha256', $normalized))) {
        sur_rate_fail();
        return false;
    }
    sur_authorize_session($job, 'recovery-code');
    sur_rate_clear();
    return true;
}

function sur_job_is_stalled(array $job, int $seconds = 120): bool
{
    if (($job['status'] ?? '') !== 'running') return false;
    $updated = strtotime((string)($job['updated_at'] ?? '')) ?: 0;
    return $updated > 0 && time() - $updated > $seconds;
}

function sur_can_safe_unlock(array $job): bool
{
    return ($job['status'] ?? '') === 'running'
        && ($job['live_changes_started'] ?? false) !== true
        && ($job['migration_applied'] ?? false) !== true;
}

function sur_safe_unlock(string $id): array
{
    return sru_lock($id, function () use ($id): array {
        $job = sru_job_read($id);
        if (!sur_can_safe_unlock($job)) throw new RuntimeException('پس از شروع تغییرات زنده، بازگشایی مستقیم مجاز نیست؛ باید Rollback انجام شود.');
        $job['status'] = 'failed';
        $job['stage'] = 'cancelled_before_apply';
        $job['progress'] = 100;
        $job['message'] = 'عملیات پیش از تغییر فایل‌ها لغو شد و سامانه با همان نسخه قبلی بازگشایی شد.';
        $job['error'] = '';
        sru_job_write($job);
        sru_clear_maintenance();
        $pendingId = (string)($job['pending_id'] ?? '');
        if (sru_safe_id($pendingId)) sru_discard_pending($pendingId);
        sru_history([
            'event_code' => 'update_cancelled_before_apply',
            'success' => false,
            'actor_user_id' => (int)($job['actor_user_id'] ?? 0),
            'cancelled_before_apply' => true,
            'from_version' => (string)($job['manifest']['current_version'] ?? ''),
            'to_version' => (string)($job['manifest']['version'] ?? ''),
            'message' => 'Update cancelled safely before live changes',
            'engine' => SOKNA_UPDATER_ENGINE,
        ]);
        return $job;
    });
}

function sur_public_job(array $job): array
{
    $public = sru_job_public($job);
    $public['live_changes_started'] = (bool)($job['live_changes_started'] ?? false);
    $public['migration_required'] = is_array(($job['manifest']['migration'] ?? null));
    $public['migration_applied'] = (bool)($job['migration_applied'] ?? false);
    $health = is_array($job['health'] ?? null) ? $job['health'] : [];
    $healthChecks = is_array($health['checks'] ?? null) ? $health['checks'] : [];
    $schemaChecks = [];
    foreach ($healthChecks as $name => $result) {
        if (is_string($name) && str_starts_with($name, 'schema_')) $schemaChecks[$name] = $result === true;
    }
    $public['schema_health'] = [
        'checked' => $schemaChecks !== [],
        'ok' => $schemaChecks !== [] && !in_array(false, $schemaChecks, true),
        'count' => count($schemaChecks),
    ];
    $public['stalled'] = sur_job_is_stalled($job);
    $public['safe_unlock_available'] = sur_can_safe_unlock($job);
    return $public;
}


/* Independent data-recovery helpers. These intentionally do not load bootstrap.php. */
function sur_data_archive_entry_safe(string $path): bool
{
    $path = str_replace('\\', '/', $path);
    return $path !== '' && $path[0] !== '/' && !preg_match('#(^|/)\.\.(/|$)#', $path) && !str_contains($path, "\0");
}

function sur_data_emergency_path(array $state): string
{
    $name = basename((string)($state['emergency_backup'] ?? ''));
    if (!preg_match('/^cafe-backup-emergency-\d{8}-\d{6}-[a-f0-9]{8}\.tar\.gz$/', $name)) {
        throw new RuntimeException('پشتیبان اضطراری معتبر در وضعیت بازیابی ثبت نشده است.');
    }
    $base = sru_root() . '/storage/backups';
    $baseReal = realpath($base);
    $path = realpath($base . '/' . $name);
    if (!$baseReal || !$path || !str_starts_with(str_replace('\\','/',$path), rtrim(str_replace('\\','/',$baseReal),'/') . '/')) {
        throw new RuntimeException('فایل پشتیبان اضطراری پیدا نشد.');
    }
    return $path;
}

function sur_data_open_archive(string $path): PharData
{
    try { return new PharData($path); }
    catch (Throwable $e) { throw new RuntimeException('پشتیبان اضطراری قابل خواندن نیست.', 0, $e); }
}

function sur_data_archive_string(PharData $archive, string $entry): string
{
    if (!isset($archive[$entry])) throw new RuntimeException('فایل ضروری پشتیبان اضطراری وجود ندارد: ' . $entry);
    return (string)$archive[$entry]->getContent();
}

function sur_data_validate_emergency(array $state): array
{
    $path = sur_data_emergency_path($state);
    $archive = sur_data_open_archive($path);
    foreach (new RecursiveIteratorIterator($archive) as $file) {
        $full = str_replace('\\','/', $file->getPathname());
        $marker = '.tar.gz/';
        $pos = strpos($full, $marker);
        $entry = $pos === false ? basename($full) : substr($full, $pos + strlen($marker));
        if (!sur_data_archive_entry_safe($entry)) throw new RuntimeException('پشتیبان اضطراری شامل مسیر ناامن است.');
    }
    $manifestRaw = sur_data_archive_string($archive, 'manifest.json');
    $manifest = json_decode($manifestRaw, true, 64, JSON_THROW_ON_ERROR);
    if (($manifest['format'] ?? '') !== 'cafe-backup-v2' || ($manifest['type'] ?? '') !== 'emergency') {
        throw new RuntimeException('فایل انتخاب‌شده نقطه بازگشت اضطراری نسخه جدید نیست.');
    }
    $indexRaw = sur_data_archive_string($archive, 'files.json');
    if (!hash_equals((string)($manifest['files_index_sha256'] ?? ''), hash('sha256', $indexRaw))) {
        throw new RuntimeException('فهرست پشتیبان اضطراری تغییر کرده است.');
    }
    $entries = json_decode($indexRaw, true, 128, JSON_THROW_ON_ERROR);
    if (!is_array($entries)) throw new RuntimeException('فهرست پشتیبان اضطراری معتبر نیست.');
    $expected = ['manifest.json'=>true,'files.json'=>true];
    foreach ($entries as $entry) {
        $ep = (string)($entry['path'] ?? '');
        if (!sur_data_archive_entry_safe($ep)) throw new RuntimeException('مسیر ناامن در فهرست پشتیبان اضطراری وجود دارد.');
        $expected[$ep] = true;
    }
    $actual = [];
    foreach (new RecursiveIteratorIterator($archive) as $file) {
        if ($file->isDir()) continue;
        $full = str_replace('\\','/', $file->getPathname());
        $marker = '.tar.gz/'; $pos = strpos($full, $marker);
        $ep = $pos === false ? basename($full) : substr($full, $pos + strlen($marker));
        $actual[$ep] = true;
    }
    if (array_diff_key($actual,$expected) || array_diff_key($expected,$actual)) {
        throw new RuntimeException('مجموعه فایل‌های پشتیبان اضطراری با فهرست امضاشده یکسان نیست.');
    }
    foreach ($entries as $entry) {
        $ep = (string)$entry['path'];
        if (!isset($archive[$ep])) throw new RuntimeException('یکی از فایل‌های پشتیبان اضطراری ناقص است.');
        $hash = hash_file('sha256', 'phar://' . $path . '/' . $ep);
        if (!$hash || !hash_equals((string)($entry['sha256'] ?? ''), $hash)) throw new RuntimeException('Checksum پشتیبان اضطراری معتبر نیست: ' . $ep);
    }
    $databasePath = 'phar://' . $path . '/database/database.sql';
    $dbHash = hash_file('sha256', $databasePath);
    if (!$dbHash || !hash_equals((string)($manifest['database_sha256'] ?? ''), $dbHash)) throw new RuntimeException('دیتابیس پشتیبان اضطراری آسیب دیده است.');
    return ['path'=>$path,'archive'=>$archive,'manifest'=>$manifest,'entries'=>$entries,'database_path'=>$databasePath];
}

function sur_cafe_sql_statements(string $sql): array
{
    if (str_starts_with($sql, "-- CAFE-SQL-FRAMED-V2 --\n")) {
        $offset = strlen("-- CAFE-SQL-FRAMED-V2 --\n"); $length = strlen($sql); $result = [];
        while ($offset < $length) {
            $lineEnd = strpos($sql, "\n", $offset); if ($lineEnd === false) break;
            $line = substr($sql, $offset, $lineEnd - $offset); $offset = $lineEnd + 1;
            if ($line === '') continue;
            if (!preg_match('/^CAFE-LEN (\d+)$/', $line, $m)) throw new RuntimeException('قالب SQL اضطراری معتبر نیست.');
            $size = (int)$m[1]; if ($size < 1 || $offset + $size > $length) throw new RuntimeException('طول Statement اضطراری معتبر نیست.');
            $result[] = substr($sql, $offset, $size); $offset += $size;
            if (($sql[$offset] ?? '') === "\n") $offset++;
        }
        return $result;
    }
    $parts = preg_split('/\R-- CAFE-STMT --\R/', trim($sql));
    return array_values(array_filter(array_map('trim', $parts ?: []), static fn(string $part): bool => $part !== ''));
}

function sur_data_restore_database(string $databasePath): void
{
    $sql = (string)file_get_contents($databasePath);
    $statements = sur_cafe_sql_statements($sql);
    if (!$statements) throw new RuntimeException('دیتابیس پشتیبان اضطراری خالی است.');
    $pdo = sru_db();
    foreach ($statements as $statement) $pdo->exec($statement);
}

function sur_data_clear_directory(string $dir): void
{
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $file) { $path=$file->getPathname(); if ($file->isDir() && !$file->isLink()) @rmdir($path); else @unlink($path); }
}

function sur_data_restore_uploads(array $validated): void
{
    $root = sru_root() . '/uploads';
    sur_data_clear_directory($root);
    if (!is_dir($root) && !mkdir($root,0755,true) && !is_dir($root)) throw new RuntimeException('پوشه uploads برای بازگشت اضطراری ساخته نشد.');
    $archive = $validated['archive']; $archivePath = $validated['path'];
    foreach ($validated['entries'] as $entry) {
        $ep = (string)($entry['path'] ?? '');
        if (!str_starts_with($ep,'uploads/')) continue;
        $relative = substr($ep,8);
        if ($relative === '' || !sur_data_archive_entry_safe($relative)) throw new RuntimeException('مسیر uploads اضطراری معتبر نیست.');
        $target = $root . '/' . str_replace('/',DIRECTORY_SEPARATOR,$relative);
        $parent = dirname($target);
        if (!is_dir($parent) && !mkdir($parent,0755,true) && !is_dir($parent)) throw new RuntimeException('پوشه مقصد فایل اضطراری ساخته نشد.');
        $in = fopen('phar://' . $archivePath . '/' . $ep,'rb'); $out = fopen($target,'wb');
        if (!$in || !$out) { if(is_resource($in))fclose($in); if(is_resource($out))fclose($out); throw new RuntimeException('بازگرداندن فایل آپلودی اضطراری انجام نشد.'); }
        stream_copy_to_stream($in,$out); fclose($in); fclose($out);
    }
}

function sur_data_health_check(): array
{
    $checks = [];
    try {
        $tables = array_map(static fn(array $r): string => (string)array_values($r)[0], sru_db()->query('SHOW TABLES')->fetchAll());
        foreach (['settings','schema_migrations','users','cafe_tables','table_sessions','orders','order_items','settlement_records','audit_log'] as $table) $checks['table_'.$table]=in_array($table,$tables,true);
        $checks['settings_readable']=in_array('settings',$tables,true) && (int)sru_db()->query('SELECT COUNT(*) FROM settings')->fetchColumn() >= 0;
    } catch (Throwable) { $checks['database']=false; }
    $checks['version_file']=is_file(sru_root().'/VERSION.txt');
    $checks['login_page']=is_file(sru_root().'/login.php');
    $checks['storage_writable']=is_dir(sru_storage()) && is_writable(sru_storage());
    $checks['uploads_writable']=is_dir(sru_root().'/uploads') && is_writable(sru_root().'/uploads');
    return ['ok'=>!in_array(false,$checks,true),'checks'=>$checks,'checked_at'=>date(DATE_ATOM)];
}

function sur_restore_emergency_data(array $state, int $actorUserId): array
{
    return sru_lock('data-recovery', function() use ($state,$actorUserId): array {
        if (!in_array((string)($state['mode'] ?? ''), ['restore','recovery_required'], true)) throw new RuntimeException('سامانه در وضعیت بازیابی داده نیست.');
        $validated = sur_data_validate_emergency($state);
        sur_data_restore_database((string)$validated['database_path']);
        sur_data_restore_uploads($validated);
        $health = sur_data_health_check();
        if (!$health['ok']) throw new RuntimeException('Health Check پس از بازگشت اضطراری کامل نیست؛ سامانه قفل می‌ماند.');
        sru_history(['event_code'=>'data_emergency_restore','success'=>true,'action'=>'data_emergency_restore','actor_user_id'=>$actorUserId,'message'=>'Emergency data snapshot restored from independent recovery center','recorded_at'=>date(DATE_ATOM),'engine'=>SOKNA_UPDATER_ENGINE]);
        sru_clear_maintenance();
        return $health;
    });
}


function updater_legacy_state(): array
{
    $maintenancePath = updater_root() . '/storage/maintenance.json';
    $maintenance = is_file($maintenancePath) ? json_decode((string)@file_get_contents($maintenancePath), true) : [];
    $legacyWork = updater_root() . '/storage/tmp/updates';
    $legacyJobs = is_dir($legacyWork) ? (glob($legacyWork . '/job*.json') ?: []) : [];
    $currentEngine = is_array($maintenance) ? (string)($maintenance['engine'] ?? '') : '';
    $maintenanceMode = is_array($maintenance) ? (string)($maintenance['mode'] ?? '') : '';
    $dataRecoveryState = is_array($maintenance) && !empty($maintenance['active']) && in_array($maintenanceMode, ['restore','recovery_required'], true);
    return [
        'maintenance_active' => is_array($maintenance) && !empty($maintenance['active']),
        'maintenance_engine' => $currentEngine,
        'data_recovery_state' => $dataRecoveryState,
        'legacy_maintenance' => is_array($maintenance) && !empty($maintenance['active']) && !$dataRecoveryState && $currentEngine !== SOKNA_UPDATER_ENGINE,
        'legacy_job_count' => count($legacyJobs),
        'legacy_work_dir' => $legacyWork,
    ];
}

function updater_reset_legacy_state(int $actorUserId): array
{
    $state = updater_legacy_state();
    $archiveDir = updater_storage_dir() . '/legacy-' . date('Ymd-His');
    if (!is_dir($archiveDir) && !mkdir($archiveDir, 0750, true) && !is_dir($archiveDir)) throw new RuntimeException('پوشه ثبت وضعیت قدیمی ساخته نشد.');
    updater_guard_directory($archiveDir);
    $maintenancePath = updater_root() . '/storage/maintenance.json';
    if (is_file($maintenancePath)) {
        $raw=(string)@file_get_contents($maintenancePath);
        @file_put_contents($archiveDir . '/maintenance.json', $raw, LOCK_EX);
        @unlink($maintenancePath);
    }
    $legacyWork = (string)$state['legacy_work_dir'];
    if (is_dir($legacyWork)) {
        $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($legacyWork,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::LEAVES_ONLY);
        foreach($iterator as $file){ if($file->isFile() && preg_match('/\.(?:json|log)$/i',$file->getFilename())) @copy($file->getPathname(),$archiveDir.'/'.$file->getFilename()); }
        updater_remove_tree($legacyWork);
    }
    sru_history(['event_code'=>'legacy_reset','success'=>true,'action'=>'legacy_reset','actor_user_id'=>$actorUserId,'message'=>'Legacy updater state cleared before canonical runtime activation','engine'=>SOKNA_UPDATER_ENGINE]);
    return updater_legacy_state();
}

function updater_current_version(): string { return trim((string)@file_get_contents(updater_root().'/VERSION.txt')) ?: 'unknown'; }
function updater_latest_job_for_actor(int $actorUserId): ?array { return updater_latest_job($actorUserId); }
function updater_public_job(array $job): array { return sru_job_public($job); }
function updater_step_job(string $id, int $actorUserId): array { return sru_step($id,$actorUserId); }
function updater_abort_job(string $id, int $actorUserId): array { return sru_abort($id,$actorUserId); }
function updater_start_rollback(string $restoreId, int $actorUserId): array { return sru_start_rollback($restoreId,$actorUserId); }

function updater_session_admin(): ?array
{
    sur_session_start();
    $user = $_SESSION['user'] ?? null;
    $last = (int)($_SESSION['user_last_activity'] ?? 0);
    if (!is_array($user) || ($user['role'] ?? '') !== 'admin' || $last <= 0 || time() - $last > 12 * 3600) return null;
    $_SESSION['user_last_activity'] = time();
    return $user;
}

function updater_login_admin_account(string $identifier, string $password): bool
{
    $identifier = trim($identifier);
    if (!sur_rate_allowed() || $identifier === '' || $password === '') return false;
    try {
        if (ctype_digit($identifier)) {
            $stmt = sru_db()->prepare("SELECT id,username,password_hash,display_name,role FROM users WHERE id=? AND role='admin' AND active=1 LIMIT 1");
            $stmt->execute([(int)$identifier]);
        } else {
            $stmt = sru_db()->prepare("SELECT id,username,password_hash,display_name,role FROM users WHERE username=? AND role='admin' AND active=1 LIMIT 1");
            $stmt->execute([$identifier]);
        }
        $user = $stmt->fetch();
        if (!is_array($user) || !password_verify($password, (string)$user['password_hash'])) { sur_rate_fail(); return false; }
        sur_session_start();
        session_regenerate_id(true);
        $_SESSION['user_last_activity'] = time();
        $_SESSION['user'] = [
            'id'=>(int)$user['id'],
            'username'=>(string)$user['username'],
            'display_name'=>(string)$user['display_name'],
            'role'=>'admin',
        ];
        sur_rate_clear();
        sur_csrf_token();
        return true;
    } catch (Throwable) { return false; }
}

function updater_logout_admin(): void
{
    sur_session_start();
    unset($_SESSION['sokna_rescue_auth'], $_SESSION['sokna_rescue_csrf'], $_SESSION['sokna_updater_codes']);
    $_SESSION['user'] = null;
    $_SESSION['user_last_activity'] = 0;
    session_regenerate_id(true);
}
