<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/relay_protocol.php';

$base=rtrim((string)(getenv('SOKNA_PUBLIC_TEST_URL')?:'http://127.0.0.1:18080'),'/');
$installation=(string)(getenv('SOKNA_PUBLIC_TEST_INSTALLATION')?:'test-installation');
$secret=(string)(getenv('SOKNA_PUBLIC_TEST_SECRET')?:'test-relay-secret-01234567890123456789');
$dsn=(string)(getenv('SOKNA_PUBLIC_TEST_DSN')?:'mysql:host=127.0.0.1;port=3306;dbname=sokna_public;charset=utf8mb4');
$dbUser=(string)(getenv('SOKNA_PUBLIC_TEST_DB_USER')?:'root');
$dbPass=(string)(getenv('SOKNA_PUBLIC_TEST_DB_PASS')?:'root');

function p4fail(string $message,mixed $context=null):never{
    fwrite(STDERR,"FAIL: $message\n");
    if($context!==null)fwrite(STDERR,json_encode($context,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n");
    exit(1);
}
function p4pass(string $message):void{fwrite(STDOUT,"PASS: $message\n");}
function p4http(string $method,string $url,array $body=[],array $headers=[]):array{
    $raw=$body?sokna_relay_canonical_json($body):'{}';
    $opts=['method'=>$method,'header'=>implode("\r\n",array_merge(['Content-Type: application/json','Accept: application/json'],$headers)),'ignore_errors'=>true,'timeout'=>10];
    if($method!=='GET')$opts['content']=$raw;
    $ctx=stream_context_create(['http'=>$opts]);
    $out=@file_get_contents($url,false,$ctx);
    $status=0;
    foreach(($http_response_header??[]) as $line)if(preg_match('#^HTTP/\S+\s+(\d+)#',$line,$m))$status=(int)$m[1];
    $json=is_string($out)?json_decode($out,true):null;
    return ['status'=>$status,'json'=>is_array($json)?$json:[],'raw'=>(string)$out];
}
function p4local(string $path,array $body):array{
    global $base,$installation,$secret;
    $raw=sokna_relay_canonical_json($body);$ts=(string)time();$nonce=bin2hex(random_bytes(12));
    $sig=sokna_relay_sign($secret,'POST',$path,$ts,$nonce,$raw);
    return p4http('POST',$base.$path,$body,[
        'X-Sokna-Installation: '.$installation,
        'X-Sokna-Timestamp: '.$ts,
        'X-Sokna-Nonce: '.$nonce,
        'X-Sokna-Signature: '.$sig,
    ]);
}
function p4login(string $username,string $password):string{
    global $base,$installation;
    $r=p4http('POST',$base.'/api/v1/auth/login.php',['installation_id'=>$installation,'username'=>$username,'password'=>$password]);
    if($r['status']!==200||empty($r['json']['token']))p4fail('login '.$username,$r);
    return (string)$r['json']['token'];
}
function p4read(string $token,string $model):array{
    global $base;
    return p4http('GET',$base.'/api/v1/remote/read.php?model='.rawurlencode($model),[],['Authorization: Bearer '.$token]);
}

$bind=p4local('/api/v1/local/bind.php',['display_name'=>'Phase4 Cafe']);
if($bind['status']!==200)p4fail('bind',$bind);

$password='phase4-password';
$rows=[
    ['projection_id'=>'user:floor','username'=>'floor','display_name'=>'Floor','role'=>'staff','password_hash'=>password_hash($password,PASSWORD_DEFAULT),'capabilities'=>['operations.read','orders.read'],'preparation_areas'=>[],'projection_version'=>1,'active'=>true],
    ['projection_id'=>'user:prep','username'=>'prep','display_name'=>'Prep','role'=>'staff','password_hash'=>password_hash($password,PASSWORD_DEFAULT),'capabilities'=>['preparation.read'],'preparation_areas'=>['kitchen'],'projection_version'=>1,'active'=>true],
    ['projection_id'=>'user:super','username'=>'super','display_name'=>'Supervisor','role'=>'staff','password_hash'=>password_hash($password,PASSWORD_DEFAULT),'capabilities'=>['operations.read','preparation.monitor'],'preparation_areas'=>[],'projection_version'=>1,'active'=>true],
    ['projection_id'=>'user:stock','username'=>'stock','display_name'=>'Stock','role'=>'staff','password_hash'=>password_hash($password,PASSWORD_DEFAULT),'capabilities'=>['inventory.read'],'preparation_areas'=>[],'projection_version'=>1,'active'=>true],
    ['projection_id'=>'user:admin','username'=>'admin4','display_name'=>'Admin','role'=>'admin','password_hash'=>password_hash($password,PASSWORD_DEFAULT),'capabilities'=>['*'],'preparation_areas'=>[],'projection_version'=>1,'active'=>true],
];
$sync=p4local('/api/v1/local/projection_sync.php',['projections'=>$rows]);
if($sync['status']!==200||(int)($sync['json']['synced']??0)!==5)p4fail('projection sync',$sync);
p4pass('minimal projected identities synced');

$now=gmdate('c');
$models=[
    ['format'=>'sokna-remote-read-v1','model_key'=>'operations','payload'=>['orders'=>[['id'=>1]],'waiter_calls'=>[],'tables'=>[]]],
    ['format'=>'sokna-remote-read-v1','model_key'=>'preparation','payload'=>['tasks'=>[['key'=>'k','area'=>'kitchen'],['key'=>'b','area'=>'bar']],'adjustments'=>[['id'=>1,'area_key'=>'kitchen'],['id'=>2,'area_key'=>'bar']]]],
    ['format'=>'sokna-remote-read-v1','model_key'=>'inventory','payload'=>['enabled'=>true,'items'=>[['id'=>1,'name'=>'Milk']],'low_stock_count'=>1]],
    ['format'=>'sokna-remote-read-v1','model_key'=>'inventory_cost','payload'=>['enabled'=>true,'items'=>[['id'=>1,'stock_value'=>100]],'total_stock_value'=>100]],
    ['format'=>'sokna-remote-read-v1','model_key'=>'reports','payload'=>['enabled'=>true,'today_summary'=>['revenue'=>500,'receipts'=>2],'summary_30'=>['revenue'=>900]]],
];
foreach($models as &$m){
    $m['source_version']=hash('sha256',sokna_relay_canonical_json($m['payload']));
    $m['generated_at']=$now;
}
unset($m);
$rs=p4local('/api/v1/local/read_model_sync.php',['models'=>$models]);
if($rs['status']!==200||(int)($rs['json']['synced']??0)!==5)p4fail('read model sync',$rs);
p4pass('versioned remote read models synced');

$hb=p4local('/api/v1/local/heartbeat.php',['local_version'=>'1.36.4-dev.30','runtime_status'=>'running','telemetry'=>['healthy'=>true]]);
if($hb['status']!==200)p4fail('heartbeat',$hb);

$floor=p4login('floor',$password);
$prep=p4login('prep',$password);
$super=p4login('super',$password);
$stock=p4login('stock',$password);
$admin=p4login('admin4',$password);

$r=p4read($floor,'operations');
if($r['status']!==200||empty($r['json']['payload']['orders']))p4fail('floor operations read',$r);
p4pass('floor sees operations read model');

$r=p4read($prep,'operations');
if($r['status']!==403)p4fail('prep must not see operations',$r);
p4pass('capability denies unrelated operations model');

$r=p4read($prep,'preparation');
if($r['status']!==200||count($r['json']['payload']['tasks']??[])!==1||($r['json']['payload']['tasks'][0]['area']??'')!=='kitchen')p4fail('prep area filtering',$r);
p4pass('preparation user sees only assigned area');

$r=p4read($super,'preparation');
if($r['status']!==200||count($r['json']['payload']['tasks']??[])!==2)p4fail('supervisor global monitor',$r);
p4pass('supervisor monitor sees all preparation areas read-only');

$r=p4read($stock,'inventory');
if($r['status']!==200)p4fail('stock inventory read',$r);
$r=p4read($stock,'inventory_cost');
if($r['status']!==403)p4fail('stock cost denied',$r);
p4pass('inventory cost requires separate authority');

$r=p4read($admin,'reports');
if($r['status']!==200||(int)($r['json']['payload']['today_summary']['revenue']??0)!==500)p4fail('admin cached report',$r);
p4pass('admin sees cached report summary');

$pdo=new PDO($dsn,$dbUser,$dbPass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->prepare("UPDATE remote_read_models SET last_sync_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 MINUTE) WHERE installation_id=? AND model_key='operations'")->execute([$installation]);
$pdo->prepare("UPDATE installation_heartbeats SET last_seen_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 MINUTE) WHERE installation_id=?")->execute([$installation]);
$r=p4read($floor,'operations');
if($r['status']!==200||empty($r['json']['stale'])||!empty($r['json']['connectivity']['local_fresh']))p4fail('stale degraded read',$r);
p4pass('stale read remains available and explicitly reports cached/offline state');

echo "Phase 4 remote read HTTP/MariaDB integration PASS\n";
