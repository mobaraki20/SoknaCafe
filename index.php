<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// `/` is the staff application gateway. The public/table menu has one owner at `/menu/`.
// Preserve pre-/menu/ QR/bookmark compatibility without restoring a second menu owner.
$legacyMenuParams = [];
foreach (['table', 'menu'] as $key) {
    $value = trim((string)($_GET[$key] ?? ''));
    if ($value !== '') $legacyMenuParams[$key] = $value;
}
if ($legacyMenuParams) redirect(asset('menu/') . '?' . http_build_query($legacyMenuParams));
if (is_logged_in()) redirect(user_home_path());
redirect(asset('login.php'));
