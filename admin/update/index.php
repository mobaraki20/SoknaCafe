<?php
declare(strict_types=1);

/**
 * Stable Sokna updater loader.
 *
 * This file is intentionally tiny and never contains the update workflow.
 * Each updater release is installed in a versioned engine directory, so an
 * update never overwrites the PHP files that are executing the same request.
 */

const SOKNA_UPDATER_LOADER_VERSION = '1.0.0';
define('SOKNA_UPDATER_ROOT', dirname(__DIR__, 2));

header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow', true);

function sul_version_valid(string $version): bool
{
    return (bool)preg_match('/^\d+\.\d+\.\d+$/', $version);
}

function sul_php_valid(string $path): bool
{
    if (!is_file($path) || !is_readable($path)) return false;
    $source = file_get_contents($path);
    if ($source === false || $source === '') return false;
    try {
        token_get_all($source, TOKEN_PARSE);
        return true;
    } catch (Throwable) {
        return false;
    }
}

function sul_pointer(): array
{
    $path = SOKNA_UPDATER_ROOT . '/storage/updater-engine.json';
    if (!is_file($path)) return [];
    try {
        $decoded = json_decode((string)file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : [];
    } catch (Throwable) {
        return [];
    }
}

function sul_candidates(): array
{
    $pointer = sul_pointer();
    $candidates = [];
    foreach (['current', 'previous'] as $key) {
        $version = trim((string)($pointer[$key] ?? ''));
        if (sul_version_valid($version)) $candidates[] = $version;
    }
    $base = SOKNA_UPDATER_ROOT . '/includes/updater_engine';
    foreach (glob($base . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
        $version = basename($dir);
        if (sul_version_valid($version)) $candidates[] = $version;
    }
    $candidates = array_values(array_unique($candidates));
    usort($candidates, static function (string $a, string $b) use ($pointer): int {
        $current = (string)($pointer['current'] ?? '');
        $previous = (string)($pointer['previous'] ?? '');
        if ($a === $current) return -1;
        if ($b === $current) return 1;
        if ($a === $previous) return -1;
        if ($b === $previous) return 1;
        return version_compare($b, $a);
    });
    return $candidates;
}

$errors = [];
foreach (sul_candidates() as $version) {
    $engine = SOKNA_UPDATER_ROOT . '/includes/updater_engine/' . $version;
    $runtime = $engine . '/runtime.php';
    $console = $engine . '/console.php';
    if (!sul_php_valid($runtime) || !sul_php_valid($console)) {
        $errors[] = $version . ': فایل موتور ناقص یا دارای خطای Syntax است.';
        continue;
    }
    try {
        define('SOKNA_UPDATER_ENGINE_VERSION', $version);
        define('SOKNA_UPDATER_ENGINE_DIR', $engine);
        require $runtime;
        require $console;
        exit;
    } catch (Throwable $e) {
        $errors[] = $version . ': ' . $e->getMessage();
    }
}

http_response_code(503);
?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>بازیابی به‌روزرسانی Sokna</title><style>body{margin:0;background:#f6f2eb;color:#29251f;font-family:Tahoma,Arial,sans-serif;line-height:1.9}.box{width:min(680px,calc(100% - 32px));margin:10vh auto;padding:24px;border:1px solid #ddd4c9;border-radius:24px;background:#fff;box-shadow:0 18px 48px #30271918}h1{margin:0 0 10px;font-size:1.45rem}p{color:#6f675f}code{direction:ltr;display:block;padding:12px;border-radius:12px;background:#f4efe7;overflow:auto}.err{margin-top:14px;padding:12px;border-radius:12px;background:#fff0ee;color:#8d302b;font-size:.84rem}</style></head><body><main class="box"><h1>موتور به‌روزرسانی قابل اجرا نیست</h1><p>هیچ موتور سالمی پیدا نشد. فایل‌های برنامه و دیتابیس تغییر داده نشده‌اند. بسته پشتیبانی را برای مدیر فنی ارسال کنید.</p><code>includes/updater_engine/&lt;version&gt;/</code><?php if($errors): ?><div class="err"><?= htmlspecialchars(implode("\n", $errors), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?></main></body></html>
