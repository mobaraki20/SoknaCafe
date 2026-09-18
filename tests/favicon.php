<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/functions.php';

$root = dirname(__DIR__);
$payload = [];
foreach ([32,180,192,512] as $size) {
    $file = $root . '/assets/icons/favicon-' . $size . '.png';
    if (!is_file($file)) throw new RuntimeException('Missing default favicon ' . $size);
    $binary = file_get_contents($file);
    $payload[(string)$size] = 'data:image/png;base64,' . base64_encode((string)$binary);
}
$created = favicon_save_payload(json_encode($payload, JSON_THROW_ON_ERROR));
try {
    foreach ([32,180,192,512] as $size) {
        $path = $created[$size] ?? '';
        if (!preg_match('#^uploads/site-icon-.*-' . $size . '\\.png$#', $path)) throw new RuntimeException('Invalid generated path');
        $info = getimagesize($root . '/' . $path);
        if (!$info || (int)$info[0] !== $size || (int)$info[1] !== $size || ($info['mime'] ?? '') !== 'image/png') {
            throw new RuntimeException('Invalid generated favicon ' . $size);
        }
    }
} finally {
    favicon_delete_paths($created);
}
foreach ($created as $path) if (is_file($root . '/' . $path)) throw new RuntimeException('Favicon cleanup failed');
echo "Favicon checks passed.\n";
