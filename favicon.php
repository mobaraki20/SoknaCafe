<?php
declare(strict_types=1);

$size = (int)($_GET['size'] ?? 32);
if (!in_array($size, [32, 180, 192, 512], true)) $size = 32;
$root = __DIR__;
$configured = is_file($root . '/config.php') && is_file($root . '/install.lock');
$relative = 'assets/icons/favicon-' . $size . '.png';

if ($configured) {
    try {
        require $root . '/bootstrap.php';
        $paths = favicon_paths();
        $relative = $paths[$size] ?? $relative;
    } catch (Throwable $e) {
        error_log('favicon fallback: ' . $e->getMessage());
    }
}

if (!preg_match('#^(uploads|assets/icons)/[A-Za-z0-9._-]+$#', $relative)) {
    $relative = 'assets/icons/favicon-' . $size . '.png';
}
$file = $root . '/' . $relative;
if (!is_file($file)) $file = $root . '/assets/icons/favicon-' . $size . '.png';
if (!is_file($file)) {
    http_response_code(404);
    exit;
}

$etag = '"' . hash_file('sha256', $file) . '"';
header('Content-Type: image/png');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=0, must-revalidate');
header('ETag: ' . $etag);
if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
    http_response_code(304);
    exit;
}
header('Content-Length: ' . (string)filesize($file));
readfile($file);
