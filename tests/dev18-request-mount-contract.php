<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/request_path.php';
function same(string $actual,string $expected,string $label): void { if($actual!==$expected){fwrite(STDERR,"FAIL $label: [$actual] != [$expected]\n");exit(1);} }
$root=dirname(__DIR__);
same(app_request_mount_path('/cafe/menu/index.php',$root.'/menu/index.php','/var/www/html',$root),'/cafe','menu nested route');
same(app_request_mount_path('/cafe/admin/update/index.php',$root.'/admin/update/index.php','/var/www/html',$root),'/cafe','deep admin route');
same(app_request_mount_path('/cafe/staff/quick-order.php',$root.'/staff/quick-order.php','/var/www/html',$root),'/cafe','staff route');
same(app_request_mount_path('/index.php',$root.'/index.php',$root,$root),'','root install');
same(app_request_mount_path('/Menu/admin/center_settings.php',$root.'/admin/center_settings.php','/srv/unknown',$root),'/Menu','alias install path');
echo "dev18 request mount contract PASS\n";
