<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/relay_protocol.php';

$base = rtrim((string)(getenv('SOKNA_PUBLIC_TEST_URL') ?: 'http://127.0.0.1:18080'), '/');
$installation = (string)(getenv('SOKNA_PUBLIC_TEST_INSTALLATION') ?: 'test-installation');
$secret = (string)(getenv('SOKNA_PUBLIC_TEST_SECRET') ?: 'test-relay-secret-01234567890123456789');
$emergency = (string)(getenv('SOKNA_PUBLIC_TEST_EMERGENCY') ?: 'test-emergency-secret');
$dbDsn = (string)(getenv('SOKNA_PUBLIC_TEST_DSN') ?: 'mysql:host=127.0.0.1;port=3306;dbname=sokna_public;charset=utf8mb4');
$dbUser = (string)(getenv('SOKNA_PUBLIC_TEST_DB_USER') ?: 'root');
$dbPass = (string)(getenv('SOKNA_PUBLIC_TEST_DB_PASS') ?: 'root');

function fail_test(string $message, mixed $context = null): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    if ($context !== null) fwrite(STDERR, json_encode($context, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT) . "\n");
    exit(1);
}
function pass_test(string $message): void { fwrite(STDOUT, "PASS: {$message}\n"); }

function http_json(string $method, string $url, array $body = [], array $headers = []): array {
    $raw = $body ? sokna_relay_canonical_json($body) : '{}';
    $headerLines = array_merge(['Content-Type: application/json','Accept: application/json'], $headers);
    $ctx = stream_context_create(['http'=>[
        'method'=>strtoupper($method),
        'header'=>implode("\r\n",$headerLines),
        'content'=>$raw,
        'ignore_errors'=>true,
        'timeout'=>10,
    ]]);
    $response = @file_get_contents($url, false, $ctx);
    $status = 0;
    foreach (($http_response_header ?? []) as $line) if (preg_match('#^HTTP/\S+\s+(\d+)#',$line,$m)) $status=(int)$m[1];
    $json = is_string($response) ? json_decode($response,true) : null;
    return ['status'=>$status,'json'=>is_array($json)?$json:[],'raw'=>(string)$response];
}

function local_signed(string $method, string $path, array $body, ?string $fixedNonce = null): array {
    global $base,$installation,$secret;
    $raw = sokna_relay_canonical_json($body);
    $ts = (string)time();
    $nonce = $fixedNonce ?? bin2hex(random_bytes(12));
    $sig = sokna_relay_sign($secret,$method,$path,$ts,$nonce,$raw);
    return http_json($method,$base.$path,$body,[
        'X-Sokna-Installation: '.$installation,
        'X-Sokna-Timestamp: '.$ts,
        'X-Sokna-Nonce: '.$nonce,
        'X-Sokna-Signature: '.$sig,
    ]);
}

$bind = local_signed('POST','/api/v1/local/bind.php',['display_name'=>'CI Cafe']);
if ($bind['status'] !== 200 || empty($bind['json']['ok'])) fail_test('signed installation bind', $bind);
pass_test('signed installation bind');

$password = 'correct-horse-battery-staple';
$projection = [
    'projection_id'=>'user:42',
    'username'=>'ci-user',
    'password_hash'=>password_hash($password,PASSWORD_DEFAULT),
    'capabilities'=>['guest.waiter_call.create'],
    'preparation_areas'=>[],
    'projection_version'=>1,
    'active'=>true,
];
$sync = local_signed('POST','/api/v1/local/projection_sync.php',['projections'=>[$projection]]);
if ($sync['status'] !== 200 || (int)($sync['json']['synced']??0)!==1) fail_test('auth projection sync',$sync);
pass_test('auth projection sync');

$login = http_json('POST',$base.'/api/v1/auth/login.php',['installation_id'=>$installation,'username'=>'ci-user','password'=>$password]);
$token = (string)($login['json']['token']??'');
if ($login['status']!==200 || $token==='') fail_test('projected login',$login);
pass_test('projected login');

$now=time();
$envelope=[
    'request_id'=>'ci-relay-001',
    'kind'=>'waiter_call.create',
    'created_at'=>gmdate('c',$now),
    'expires_at'=>gmdate('c',$now+120),
    'payload'=>['table_ref'=>'table:7','client_token'=>'abcdefghijklmnop'],
];
$enqueue=http_json('POST',$base.'/api/v1/realtime/enqueue.php',$envelope,['Authorization: Bearer '.$token]);
if($enqueue['status']!==202 || ($enqueue['json']['state']??'')!=='queued') fail_test('realtime enqueue',$enqueue);
pass_test('realtime enqueue');

$duplicate=http_json('POST',$base.'/api/v1/realtime/enqueue.php',$envelope,['Authorization: Bearer '.$token]);
if($duplicate['status']!==200 || empty($duplicate['json']['deduplicated'])) fail_test('enqueue idempotency',$duplicate);
pass_test('enqueue idempotency');

$claim=local_signed('POST','/api/v1/local/claim.php',['lease_seconds'=>20]);
if($claim['status']!==200 || ($claim['json']['request']['request_id']??'')!=='ci-relay-001' || empty($claim['json']['lease_token'])) fail_test('claim with lease',$claim);
pass_test('claim with lease');

$lease=(string)$claim['json']['lease_token'];
$ack=local_signed('POST','/api/v1/local/ack.php',['request_id'=>'ci-relay-001','lease_token'=>$lease,'state'=>'committed','error_code'=>'','result'=>['local_id'=>99]]);
if($ack['status']!==200 || ($ack['json']['state']??'')!=='committed') fail_test('terminal ACK',$ack);
pass_test('terminal ACK');

$ack2=local_signed('POST','/api/v1/local/ack.php',['request_id'=>'ci-relay-001','lease_token'=>$lease,'state'=>'committed','error_code'=>'','result'=>['local_id'=>99]]);
if($ack2['status']!==200 || empty($ack2['json']['deduplicated'])) fail_test('duplicate ACK idempotency',$ack2);
pass_test('duplicate ACK idempotency');

$result=http_json('GET',$base.'/api/v1/realtime/result.php?request_id=ci-relay-001',[],['Authorization: Bearer '.$token]);
if($result['status']!==200 || ($result['json']['state']??'')!=='committed' || (int)($result['json']['result']['local_id']??0)!==99) fail_test('canonical committed result',$result);
pass_test('canonical committed result');

$pdo=new PDO($dbDsn,$dbUser,$dbPass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$count=(int)$pdo->query("SELECT COUNT(*) FROM realtime_requests WHERE installation_id=".$pdo->quote($installation)." AND request_id='ci-relay-001'")->fetchColumn();
if($count!==1) fail_test('single durable Public request row',$count);
pass_test('single durable Public request row');

$replayBody=['display_name'=>'CI Cafe'];
$raw=sokna_relay_canonical_json($replayBody);$ts=(string)time();$nonce='fixed-replay-nonce';$sig=sokna_relay_sign($secret,'POST','/api/v1/local/bind.php',$ts,$nonce,$raw);
$headers=['X-Sokna-Installation: '.$installation,'X-Sokna-Timestamp: '.$ts,'X-Sokna-Nonce: '.$nonce,'X-Sokna-Signature: '.$sig];
$first=http_json('POST',$base.'/api/v1/local/bind.php',$replayBody,$headers);
$second=http_json('POST',$base.'/api/v1/local/bind.php',$replayBody,$headers);
if($first['status']!==200 || $second['status']!==409 || ($second['json']['error']??'')!=='replay_detected') fail_test('nonce replay rejection',[$first,$second]);
pass_test('nonce replay rejection');

$expired=[
    'request_id'=>'ci-relay-expired',
    'kind'=>'waiter_call.create',
    'created_at'=>gmdate('c',$now),
    'expires_at'=>gmdate('c',$now+60),
    'payload'=>['table_ref'=>'table:7','client_token'=>'qrstuvwxyzabcdef'],
];
$e=http_json('POST',$base.'/api/v1/realtime/enqueue.php',$expired,['Authorization: Bearer '.$token]);
if($e['status']!==202) fail_test('expiry fixture enqueue',$e);
$pdo->prepare("UPDATE realtime_requests SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE installation_id=? AND request_id='ci-relay-expired'")->execute([$installation]);
$empty=local_signed('POST','/api/v1/local/claim.php',['lease_seconds'=>20]);
if($empty['status']!==404 || ($empty['json']['error']??'')!=='empty_queue') fail_test('expired request never claimed',$empty);
$state=$pdo->prepare("SELECT state FROM realtime_requests WHERE installation_id=? AND request_id='ci-relay-expired'");$state->execute([$installation]);
if((string)$state->fetchColumn()!=='expired') fail_test('expired request terminalized');
pass_test('expired request terminalized without Local delivery');

$control=http_json('POST',$base.'/api/v1/emergency/control.php',['installation_id'=>$installation,'action'=>'disable_remote','reason'=>'CI break glass test','actor'=>'ci'],['X-Sokna-Emergency-Key: '.$emergency]);
if($control['status']!==200 || !empty($control['json']['remote_enabled'])) fail_test('emergency remote disable',$control);
pass_test('emergency remote disable');

$blocked=local_signed('POST','/api/v1/local/claim.php',['lease_seconds'=>20]);
if($blocked['status']!==409 || ($blocked['json']['error']??'')!=='remote_disabled') fail_test('disabled remote blocks claim',$blocked);
pass_test('disabled remote blocks claim');

$reactivate=http_json('POST',$base.'/api/v1/emergency/control.php',['installation_id'=>$installation,'action'=>'enable_remote','reason'=>'CI projection cleanup test','actor'=>'ci'],['X-Sokna-Emergency-Key: '.$emergency]);
if($reactivate['status']!==200) fail_test('remote re-enable for projection cleanup',$reactivate);
$emptyProjection=local_signed('POST','/api/v1/local/projection_sync.php',['projections'=>[]]);
if($emptyProjection['status']!==200 || (int)($emptyProjection['json']['synced']??-1)!==0) fail_test('empty projection sync',$emptyProjection);
$staleLogin=http_json('POST',$base.'/api/v1/auth/login.php',['installation_id'=>$installation,'username'=>'ci-user','password'=>$password]);
if($staleLogin['status']!==401) fail_test('stale projected user is deactivated',$staleLogin);
pass_test('stale projected user is deactivated');

echo "Phase 2 Public HTTP/MariaDB integration PASS\n";
