<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');
echo "User-agent: *
Disallow: /admin/
Disallow: /operator/
Disallow: /waiter/
Disallow: /api/
Sitemap: " . canonical_asset('sitemap.php') . "
";
