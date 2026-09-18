<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
$basePath=(string)(parse_url(app_base_url(),PHP_URL_PATH)??'');$basePath=rtrim($basePath,'/');
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-cache, max-age=0, must-revalidate');
$manifest = [
  'id'=>($basePath?:'').'/staff-app',
  'name'=>setting('cafe_name','کافه من').' | تیم کافه',
  'short_name'=>'تیم '.setting('cafe_name','کافه'),
  'description'=>'پنل سبک اپراتور، سالن و آماده‌سازی',
  'lang'=>'fa','dir'=>'rtl',
  'start_url'=>($basePath?:'').'/login.php?source=pwa',
  'scope'=>($basePath?:'').'/',
  'display'=>'standalone',
  'background_color'=>valid_hex_color(setting('background_color','#f7f3ec'),'#f7f3ec'),
  'theme_color'=>valid_hex_color(setting('primary_color','#365b4c'),'#365b4c'),
  'icons'=>[
    ['src'=>($basePath?:'').'/favicon.php?size=192&v='.rawurlencode(favicon_revision()),'sizes'=>'192x192','type'=>'image/png','purpose'=>'any maskable'],
    ['src'=>($basePath?:'').'/favicon.php?size=512&v='.rawurlencode(favicon_revision()),'sizes'=>'512x512','type'=>'image/png','purpose'=>'any maskable']
  ],
  'shortcuts'=>[
    ['name'=>'سالن و آماده‌سازی','short_name'=>'کار تیم','url'=>($basePath?:'').'/waiter/index.php'],
    ['name'=>'پنل سفارش‌ها','short_name'=>'سفارش‌ها','url'=>($basePath?:'').'/operator/index.php']
  ]
];
$payload = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($payload)) { http_response_code(500); exit; }
$etag = '"' . hash('sha256', $payload) . '"';
header('ETag: ' . $etag);
if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) { http_response_code(304); exit; }
echo $payload;
