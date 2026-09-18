<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
header('Content-Type: application/xml; charset=utf-8');
$urls = [canonical_asset('menu/')];
if (setting_bool('public_about_enabled', true)) $urls[] = canonical_asset('about.php');
echo '<?xml version="1.0" encoding="UTF-8"?>';
?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><?php foreach ($urls as $url): ?><url><loc><?= e($url) ?></loc><changefreq>weekly</changefreq></url><?php endforeach; ?></urlset>
