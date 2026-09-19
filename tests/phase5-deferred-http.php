<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/relay_protocol.php';

$base=rtrim((string)(getenv('SOKNA_PUBLIC_TEST_URL')?:'http://127.0.0.1:18080'),'/');
$installation=(string)(getenv('SOKNA_PUBLIC_TEST_INSTALLATION')?:'test-installation');
$secret=(string)(getenv('SOKNA_PUBLIC_TEST_SECRET')?:'test-relay-secret-01234567890123456789');
$dsn=(string)(getenv('SOKNA_PUBLIC_TEST_DSN')?:'mysql:host=127.0.0.1;port=3306;dbname=sokna_public;charset=utf8mb4');
$dbUser=(string)(getenv('SOKNA_PUBLIC_TEST_DB_USER')?:'root');
$dbPass=(string)(getenv('SOKNA_PUBLIC_TEST_DB_PASS')?:'root');

function p5fail(string $m,mixed $ctx=null):never{fwrite(STDERR,"FAIL: $m\n");if($ctx!==null)fwrite(STDERR,json_encode($ctx,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n");exit(1);}
function p5pass(string $m):void{fwrite(STDOUT,"PASS: $m\n");}
function p5http(string $method,string $url,array $body=[],array $headers=[]):array{
    $raw=$body?sokna_relay_canonical_json($body):'{}';
    $opts=['method'=>$method,'header'=>implode("\r\n",array_merge(['Content-Type: application/json','Accept: application/json'],$headers)),'ignore_errors'=>true,'timeout'=>10];
    if($method!=='GET')$opts['content']=$raw;
    $ctx=stream_context_create(['http'=>$opts]);$out=@file_get_contents($url,false,$ctx);$status=0;
    foreach(($http_response_header??[]) as $line)if(preg_match('#^HTTP/\S+\s+(\d+)#',$line,$m))$status=(int)$m[1];
    $json=is_string($out)?json_decode($out,true):null;return ['status'=>$status,'json'=>is_array($json)?$json:[],'raw'=>(string)$out];
}
function p5local(string $path,array $body):array{
    global $base,$installation,$secret;$raw=sokna_relay_canonical_json($body);$ts=(string)time();$nonce=bin2hex(random_bytes(12));$sig=sokna_relay_sign($secret,'POST',$path,$ts,$nonce,$raw);
    return p5http('POST',$base.$path,$body,['X-Sokna-Installation: '.$installation,'X-Sokna-Timestamp: '.$ts,'X-Sokna-Nonce: '.$nonce,'X-Sokna-Signature: '.$sig]);
}
function p5login(string $username,string $password):string{
    global $base,$installation;$r=p5http('POST',$base.'/api/v1/auth/login.php',['installation_id'=>$installation,'username'=>$username,'password'=>$password]);
    if($r['status']!==200||empty($r['json']['token']))p5fail('login '.$username,$r);return (string)$r['json']['token'];
}
function p5enqueue(string $token,array $envelope):array{global $base;return p5http('POST',$base.'/api/v1/deferred/enqueue.php',$envelope,['Authorization: Bearer '.$token]);}

$bind=p5local('/api/v1/local/bind.php',['display_name'=>'Phase5 Cafe']);if($bind['status']!==200)p5fail('bind',$bind);
$password='phase5-password';
$projections=[
 ['projection_id'=>'user:501','username'=>'p5-worker','display_name'=>'P5 Worker','role'=>'operator','password_hash'=>password_hash($password,PASSWORD_DEFAULT),'capabilities'=>['supply.need.defer','deferred.context'],'preparation_areas'=>['kitchen'],'projection_version'=>1,'active'=>true],
 ['projection_id'=>'user:502','username'=>'p5-other','display_name'=>'P5 Other','role'=>'operator','password_hash'=>password_hash($password,PASSWORD_DEFAULT),'capabilities'=>['deferred.context'],'preparation_areas'=>[],'projection_version'=>1,'active'=>true],
];
$sync=p5local('/api/v1/local/projection_sync.php',['projections'=>$projections]);if($sync['status']!==200||(int)($sync['json']['synced']??0)!==2)p5fail('projection sync',$sync);
$token=p5login('p5-worker',$password);$other=p5login('p5-other',$password);
$pdo=new PDO($dsn,$dbUser,$dbPass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$realtimeBefore=(int)$pdo->query("SELECT COUNT(*) FROM realtime_requests WHERE installation_id=".$pdo->quote($installation))->fetchColumn();

$now=time();$today=gmdate('Y-m-d',$now);
$envelope=['request_id'=>'p5-deferred-001','kind'=>'supply.need.create','created_at'=>gmdate('c',$now),'occurred_at'=>gmdate('c',$now),'payload'=>['inventory_item_id'=>10,'quantity_major'=>'2','department'=>'kitchen','note'=>'CI']];
$enq=p5enqueue($token,$envelope);if($enq['status']!==202||($enq['json']['state']??'')!=='pending_sync')p5fail('deferred enqueue',$enq);p5pass('deferred enqueue is pending, not committed');
$dup=p5enqueue($token,$envelope);if($dup['status']!==200||empty($dup['json']['deduplicated']))p5fail('deferred enqueue idempotency',$dup);p5pass('deferred duplicate is idempotent');
$changed=$envelope;$changed['payload']['quantity_major']='3';$conflict=p5enqueue($token,$changed);if($conflict['status']!==409||($conflict['json']['error']??'')!=='request_id_conflict')p5fail('request id conflict',$conflict);p5pass('same request id with new payload is rejected');

$denied=$envelope;$denied['request_id']='p5-deferred-denied';$denied['created_at']=gmdate('c',$now+1);
$deny=p5enqueue($other,$denied);if($deny['status']!==403)p5fail('deferred capability denial',$deny);p5pass('deferred enqueue respects projected capability');

$leak=p5http('GET',$base.'/api/v1/deferred/result.php?request_id=p5-deferred-001',[],['Authorization: Bearer '.$other]);
if($leak['status']!==403)p5fail('cross actor result isolation',$leak);p5pass('deferred result is actor isolated');

$claim=p5local('/api/v1/local/deferred/claim.php',['lease_seconds'=>30]);
if($claim['status']!==200||($claim['json']['request']['request_id']??'')!=='p5-deferred-001'||empty($claim['json']['lease_token']))p5fail('deferred claim',$claim);
$lease=(string)$claim['json']['lease_token'];p5pass('Local claims deferred work with separate lease');
$ack=p5local('/api/v1/local/deferred/ack.php',['request_id'=>'p5-deferred-001','lease_token'=>$lease,'state'=>'needs_review','error_code'=>'ci_review','result'=>['review_id'=>77]]);
if($ack['status']!==200||($ack['json']['state']??'')!=='needs_review')p5fail('needs review ack',$ack);p5pass('Local can terminalize deferred as needs_review');
$reconcile=p5local('/api/v1/local/deferred/reconcile.php',['request_id'=>'p5-deferred-001','state'=>'committed','result'=>['local_id'=>88],'error_code'=>'']);
if($reconcile['status']!==200||($reconcile['json']['state']??'')!=='committed')p5fail('review reconcile',$reconcile);p5pass('explicit review can reconcile Public state to committed');

$result=p5http('GET',$base.'/api/v1/deferred/result.php?request_id=p5-deferred-001',[],['Authorization: Bearer '.$token]);
if($result['status']!==200||($result['json']['state']??'')!=='committed')p5fail('committed result',$result);

$pending=$envelope;$pending['request_id']='p5-deferred-002';$pending['created_at']=gmdate('c',$now+2);
$e2=p5enqueue($token,$pending);if($e2['status']!==202)p5fail('period pending fixture',$e2);
$status=p5local('/api/v1/local/deferred/period-status.php',['from_date'=>$today,'to_date'=>$today]);
if($status['status']!==200||(int)($status['json']['counts']['pending_sync']??0)<1||(int)($status['json']['blocking']??0)<1)p5fail('period status blocking',$status);p5pass('period status sees pending deferred work');

$claim2=p5local('/api/v1/local/deferred/claim.php',['lease_seconds'=>30]);if($claim2['status']!==200||($claim2['json']['request']['request_id']??'')!=='p5-deferred-002')p5fail('second deferred claim',$claim2);
$ack2=p5local('/api/v1/local/deferred/ack.php',['request_id'=>'p5-deferred-002','lease_token'=>(string)$claim2['json']['lease_token'],'state'=>'rejected','error_code'=>'ci_cleanup','result'=>[]]);
if($ack2['status']!==200)p5fail('second deferred cleanup',$ack2);

$realtimeAfter=(int)$pdo->query("SELECT COUNT(*) FROM realtime_requests WHERE installation_id=".$pdo->quote($installation))->fetchColumn();
if($realtimeAfter!==$realtimeBefore)p5fail('deferred queue mutated realtime queue',[$realtimeBefore,$realtimeAfter]);
p5pass('Deferred lifecycle never touches realtime queue');

echo "Phase 5 Public deferred HTTP/MariaDB integration PASS\n";
