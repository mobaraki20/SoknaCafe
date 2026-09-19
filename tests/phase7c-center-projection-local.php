<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/sokna_center_projection.php';

$pdo=db();
$pdo->prepare("INSERT INTO users(username,password_hash,display_name,role,active) VALUES(?,?,?,?,1) ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),role=VALUES(role),active=1")
    ->execute(['phase7c-projection-user', password_hash('not-exported', PASSWORD_DEFAULT), 'Phase 7C Projection', 'operator']);

$GLOBALS['SOKNA_CENTER_CONFIG_OVERRIDE']=[
    'enabled'=>true,
    'base_url'=>'https://center.example.test',
    'secret'=>str_repeat('s', 40),
    'origin'=>'https://cafe.example.test',
    'return_url'=>'https://cafe.example.test/center_return.php',
    'outbound_user_projection_supported'=>true,
];
$captured=[];
$GLOBALS['SOKNA_CENTER_TRANSPORT']=function(string $method,string $url,array $headers,string $body,int $timeout) use (&$captured):array{
    parse_str($body,$fields);
    $projection=json_decode((string)($fields['projection']??''),true);
    $captured=['method'=>$method,'url'=>$url,'headers'=>$headers,'fields'=>$fields,'projection'=>$projection];
    return ['transport_ok'=>true,'http_status'=>200,'raw'=>json_encode(['ok'=>true,'data'=>['source_version'=>(string)($projection['source_version']??'')]],JSON_UNESCAPED_SLASHES),'error'=>''];
};

$result=sokna_center_push_user_projection($pdo,5);
if(($result['status']??'')!=='synced') throw new RuntimeException('projection did not sync');
if(!str_ends_with((string)$captured['url'],'/api/s2s/cafe_users_sync.php')) throw new RuntimeException('projection endpoint mismatch');
if(($captured['method']??'')!=='POST') throw new RuntimeException('projection must POST');
$users=$captured['projection']['users']??[];
$target=null;foreach($users as $u) if(($u['display_name']??'')==='Phase 7C Projection'){$target=$u;break;}
if(!is_array($target)) throw new RuntimeException('projection user missing');
$allowed=['local_user_id','display_name','role','active','updated_at'];
if(array_keys($target)!==$allowed) throw new RuntimeException('projection allow-list changed');
foreach(['username','password','password_hash','session','csrf_token'] as $bad) if(array_key_exists($bad,$target)) throw new RuntimeException('sensitive field leaked');
if(!isset($captured['fields']['token']) || substr_count((string)$captured['fields']['token'],'.')!==2) throw new RuntimeException('signed S2S token missing');
echo "Phase 7C Center outbound Local/MariaDB contract PASS.\n";
