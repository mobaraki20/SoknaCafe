<?php
declare(strict_types=1);

/**
 * Keep Vazirmatn self-hosted on the restaurant server.
 * The official pinned WOFF2 is bundled with the project. If it is missing or
 * invalid, install/update/first web request downloads the same version atomically.
 */
function vazirmatn_font_path(): string
{
    return dirname(__DIR__) . '/assets/fonts/Vazirmatn-Variable.woff2';
}

function vazirmatn_font_valid(?string $path = null): bool
{
    $path ??= vazirmatn_font_path();
    if (!is_file($path)) return false;
    $size = @filesize($path);
    if (!is_int($size) || $size < 80000 || $size > 180000) return false;
    $handle = @fopen($path, 'rb');
    if (!$handle) return false;
    $magic = fread($handle, 4);
    fclose($handle);
    return $magic === 'wOF2';
}

function font_runtime_fetch(string $url): string|false
{
    $context = stream_context_create([
        'http' => ['timeout'=>5, 'follow_location'=>1, 'max_redirects'=>4, 'user_agent'=>'Sokna/'.app_release_version().' font installer'],
        'ssl' => ['verify_peer'=>true, 'verify_peer_name'=>true],
    ]);
    $data = @file_get_contents($url, false, $context);
    if (is_string($data) && strlen($data) >= 80000 && strlen($data) <= 180000 && substr($data, 0, 4) === 'wOF2') return $data;
    return false;
}

function font_runtime_ensure_vazirmatn(bool $force = false): bool
{
    $target = vazirmatn_font_path();
    if (vazirmatn_font_valid($target)) return true;

    $statusDir = dirname(__DIR__) . '/storage';
    $statusFile = $statusDir . '/font-install-status.json';
    if (!$force && is_file($statusFile) && (time() - (int)@filemtime($statusFile)) < 21600) return false;
    if (!is_dir(dirname($target)) && !@mkdir(dirname($target), 0755, true) && !is_dir(dirname($target))) return false;

    $urls = [
        'https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/fonts/webfonts/Vazirmatn%5Bwght%5D.woff2',
        'https://raw.githubusercontent.com/rastikerdar/vazirmatn/v33.003/fonts/webfonts/Vazirmatn%5Bwght%5D.woff2',
    ];
    $ok = false;
    $message = 'download_failed';
    foreach ($urls as $url) {
        $data = font_runtime_fetch($url);
        if ($data === false) continue;
        $temp = $target . '.tmp-' . bin2hex(random_bytes(5));
        if (@file_put_contents($temp, $data, LOCK_EX) === strlen($data) && vazirmatn_font_valid($temp) && @rename($temp, $target)) {
            @chmod($target, 0644);
            $ok = true;
            $message = 'installed';
            break;
        }
        @unlink($temp);
    }
    if (!is_dir($statusDir)) @mkdir($statusDir, 0755, true);
    @file_put_contents($statusFile, json_encode(['ok'=>$ok,'message'=>$message,'checked_at'=>date(DATE_ATOM)], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), LOCK_EX);
    return $ok;
}
