<?php

declare(strict_types=1);

// Extracted from includes/functions.php during pre-operational P2 owner cleanup.
// Keep behavior-compatible global function names; functions.php remains the public bootstrap aggregator.

function favicon_default_paths(): array
{
    return [
        32 => 'assets/icons/favicon-32.png',
        180 => 'assets/icons/favicon-180.png',
        192 => 'assets/icons/favicon-192.png',
        512 => 'assets/icons/favicon-512.png',
    ];
}

/** @return array<int,string> */
function favicon_paths(): array
{
    $defaults = favicon_default_paths();
    $result = [];
    foreach ($defaults as $size => $fallback) {
        $candidate = trim(setting('favicon_' . $size . '_path'));
        if ($candidate !== '' && preg_match('#^(uploads|assets/icons)/[A-Za-z0-9._-]+$#', $candidate)) {
            $absolute = dirname(__DIR__, 2) . '/' . $candidate;
            if (is_file($absolute)) {
                $result[$size] = $candidate;
                continue;
            }
        }
        $result[$size] = $fallback;
    }
    return $result;
}

function favicon_revision(): string
{
    $updated = trim(setting('favicon_updated_at'));
    return $updated !== '' ? substr(hash('sha256', $updated), 0, 12) : 'default-1170';
}

function favicon_url(int $size): string
{
    if (!in_array($size, [32, 180, 192, 512], true)) $size = 32;
    return asset('favicon.php?size=' . $size . '&v=' . rawurlencode(favicon_revision()));
}

function favicon_head_tags(): string
{
    return '<link rel="icon" type="image/png" sizes="32x32" href="' . e(favicon_url(32)) . '">' .
        '<link rel="icon" type="image/png" sizes="192x192" href="' . e(favicon_url(192)) . '">' .
        '<link rel="apple-touch-icon" sizes="180x180" href="' . e(favicon_url(180)) . '">' .
        '<link rel="shortcut icon" href="' . e(favicon_url(32)) . '">';
}

/** @return array<int,string> */
function favicon_save_payload(string $payload): array
{
    if ($payload === '') throw new RuntimeException('ابتدا تصویر فاوآیکن را انتخاب کن.');
    try {
        $decoded = json_decode($payload, true, 16, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        throw new RuntimeException('پردازش تصویر فاوآیکن کامل نشد؛ صفحه را تازه‌سازی و دوباره امتحان کن.');
    }
    if (!is_array($decoded)) throw new RuntimeException('داده فاوآیکن معتبر نیست.');

    $required = [32, 180, 192, 512];
    $binaryBySize = [];
    $totalBytes = 0;
    foreach ($required as $size) {
        $dataUrl = (string)($decoded[(string)$size] ?? $decoded[$size] ?? '');
        if (!preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $match)) {
            throw new RuntimeException('یکی از اندازه‌های فاوآیکن ساخته نشد.');
        }
        $binary = base64_decode($match[1], true);
        if ($binary === false || $binary === '') throw new RuntimeException('یکی از فایل‌های فاوآیکن قابل خواندن نیست.');
        $totalBytes += strlen($binary);
        if ($totalBytes > 6 * 1024 * 1024) throw new RuntimeException('حجم خروجی فاوآیکن بیش از حد مجاز است.');
        $info = @getimagesizefromstring($binary);
        if (!$info || (int)$info[0] !== $size || (int)$info[1] !== $size || ($info['mime'] ?? '') !== 'image/png') {
            throw new RuntimeException('ابعاد فاوآیکن ساخته‌شده معتبر نیست.');
        }
        $binaryBySize[$size] = $binary;
    }

    $targetDir = dirname(__DIR__, 2) . '/uploads';
    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        throw new RuntimeException('پوشه تصاویر قابل ساخت نیست.');
    }
    if (!is_writable($targetDir)) throw new RuntimeException('پوشه تصاویر قابل نوشتن نیست.');

    $prefix = 'site-icon-' . date('YmdHis') . '-' . bin2hex(random_bytes(5));
    $paths = [];
    $created = [];
    try {
        foreach ($binaryBySize as $size => $binary) {
            $name = $prefix . '-' . $size . '.png';
            $absolute = $targetDir . '/' . $name;
            if (file_put_contents($absolute, $binary, LOCK_EX) === false) {
                throw new RuntimeException('ذخیره فاوآیکن ناموفق بود.');
            }
            @chmod($absolute, 0644);
            $created[] = $absolute;
            $paths[$size] = 'uploads/' . $name;
        }
    } catch (Throwable $e) {
        foreach ($created as $file) if (is_file($file)) @unlink($file);
        throw $e;
    }
    return $paths;
}

/** @param array<int,string> $paths */
function favicon_delete_paths(array $paths): void
{
    $root = dirname(__DIR__, 2);
    foreach ($paths as $path) {
        $path = trim((string)$path);
        if (!preg_match('#^uploads/site-icon-[A-Za-z0-9._-]+\.png$#', $path)) continue;
        $absolute = $root . '/' . $path;
        if (is_file($absolute)) @unlink($absolute);
    }
}

