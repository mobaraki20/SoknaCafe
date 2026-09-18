<?php
declare(strict_types=1);
require __DIR__ . '/../includes/functions.php';
$dir = sys_get_temp_dir() . '/sokna-square-' . bin2hex(random_bytes(4));
mkdir($dir, 0700, true);
$square = $dir . '/square.png';
$wide = $dir . '/wide.png';
$pngSquare = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAYAAABytg0kAAAAFElEQVR42mP8z8Dwn4GBgYGJAQoAHgQCAZqN3zQAAAAASUVORK5CYII=');
$pngWide = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAMAAAACCAYAAACddGYaAAAAFUlEQVR42mP8z8Dwn4GBgYGJgQEAJwQCAoDIWnAAAAAASUVORK5CYII=');
file_put_contents($square, $pngSquare);
file_put_contents($wide, $pngWide);
assert_square_uploaded_image(['error'=>UPLOAD_ERR_OK,'tmp_name'=>$square]);
$failed = false;
try { assert_square_uploaded_image(['error'=>UPLOAD_ERR_OK,'tmp_name'=>$wide]); } catch (RuntimeException $e) { $failed = str_contains($e->getMessage(), 'مربع'); }
@unlink($square); @unlink($wide); @rmdir($dir);
if (!$failed) { fwrite(STDERR, "Non-square item image was accepted.\n"); exit(1); }
echo "Square item image validation passed.\n";
