<?php
declare(strict_types=1);

require dirname(__DIR__).'/bootstrap.php';
require_once dirname(__DIR__).'/includes/relay_dispatch.php';

$base=rtrim((string)(getenv('SOKNA_PUBLIC_TEST_URL')?:'http://127.0.0.1:18080'),'/');
$installation=(string)(getenv('SOKNA_PUBLIC_TEST_INSTALLATION')?:'test-installation');
$secret=(string)(getenv('SOKNA_PUBLIC_TEST_SECRET')?:'test-relay-secret-01234567890123456789');
$publicDsn=(string)(getenv('SOKNA_PUBLIC_TEST_DSN')?:'mysql:host=127.0.0.1;port=3306;dbname=sokna_public;charset=utf8mb4');
$publicUser=(string)(getenv('SOKNA_PUBLIC_TEST_DB_USER')?:'root');
$publicPass=(string)(getenv('SOKNA_PUBLIC_TEST_DB_PASS')?:'root');

function p6ch_fail(string $message,mixed $context=null):never{
    fwrite(STDERR,"FAIL: $message\n");
    if($context!==null)fwrite(STDERR,json_encode($context,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n");
    exit(1);
}
function p6ch_pass(string $message):void{fwrite(STDOUT,"PASS: $message\n");}
function p6ch_http(string $method,string $url,array $body=[],array $headers=[]):array{
    $raw=$body?sokna_relay_canonical_json($body):'{}';
    $opts=['method'=>$method,'header'=>implode("\r\n",array_merge(['Accept: application/json','Content-Type: application/json'],$headers)),'ignore_errors'=>true,'timeout'=>10];
    if($method!=='GET')$opts['content']=$raw;
    $ctx=stream_context_create(['http'=>$opts]);
    $out=@file_get_contents($url,false,$ctx);$status=0;
    foreach(($http_response_header??[])as $line)if(preg_match('#^HTTP/\S+\s+(\d+)#',$line,$m))$status=(int)$m[1];
    $json=is_string($out)?json_decode($out,true):null;
    return ['status'=>$status,'json'=>is_array($json)?$json:[],'raw'=>(string)$out];
}
function p6ch_local(string $path,array $body):array{
    global $base,$installation,$secret;
    $raw=sokna_relay_canonical_json($body);$ts=(string)time();$nonce=bin2hex(random_bytes(12));
    $sig=sokna_relay_sign($secret,'POST',$path,$ts,$nonce,$raw);
    return p6ch_http('POST',$base.$path,$body,[
        'X-Sokna-Installation: '.$installation,
        'X-Sokna-Timestamp: '.$ts,
        'X-Sokna-Nonce: '.$nonce,
        'X-Sokna-Signature: '.$sig,
    ]);
}
function p6ch_login(string $username,string $password):string{
    global $base,$installation;
    $r=p6ch_http('POST',$base.'/api/v1/auth/login.php',['installation_id'=>$installation,'username'=>$username,'password'=>$password]);
    if($r['status']!==200||empty($r['json']['token']))p6ch_fail('login '.$username,$r);
    return (string)$r['json']['token'];
}
function p6ch_enqueue(string $token,string $requestId,string $kind,array $payload):array{
    global $base;
    $now=time();
    return p6ch_http('POST',$base.'/api/v1/realtime/enqueue.php',[
        'request_id'=>$requestId,'kind'=>$kind,'created_at'=>gmdate('c',$now),'expires_at'=>gmdate('c',$now+120),'payload'=>$payload,
    ],['Authorization: Bearer '.$token]);
}

$local=db();
$public=new PDO($publicDsn,$publicUser,$publicPass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$suffix=substr(bin2hex(random_bytes(6)),0,10);
$password='phase6c-http-password';

$userStmt=$local->prepare("INSERT INTO users(username,password_hash,display_name,role,active) VALUES(?,?,?,?,1)");
$userStmt->execute(['p6c-http-'.$suffix,password_hash($password,PASSWORD_DEFAULT),'P6C HTTP Staff','operator']);
$userId=(int)$local->lastInsertId();
$local->prepare("INSERT INTO user_capabilities(user_id,capability,enabled) VALUES(?,'orders_floor',1)")->execute([$userId]);

$userStmt->execute(['p6c-http-denied-'.$suffix,password_hash($password,PASSWORD_DEFAULT),'P6C HTTP Denied','operator']);
$deniedUserId=(int)$local->lastInsertId();

$menuKey='p6c-http-'.$suffix;$categoryKey='p6c-http-cat-'.$suffix;
$local->prepare("INSERT INTO menus(menu_key,name,status,sort_order) VALUES(?,?,'active',970)")->execute([$menuKey,'P6C HTTP Menu']);
$menuId=(int)$local->lastInsertId();
$local->prepare("INSERT INTO categories(category_key,name,audience,sort_order,active) VALUES(?,?,'guest_staff',970,1)")->execute([$categoryKey,'P6C HTTP Category']);
$categoryId=(int)$local->lastInsertId();
$local->prepare('INSERT INTO menu_categories(menu_id,category_id,sort_order) VALUES(?,?,1)')->execute([$menuId,$categoryId]);
$local->prepare("INSERT INTO items(item_code,category_id,name,price,available,active,staff_only,sellable_kind,takeaway_allowed,preparation_station,sort_order) VALUES(?,?,?,?,1,1,0,'menu_item',1,'none',1)")
    ->execute(['P6C-HTTP-'.$suffix,$categoryId,'P6C HTTP Item',42000]);
$itemId=(int)$local->lastInsertId();
$local->prepare('INSERT INTO menu_items(menu_id,item_id) VALUES(?,?)')->execute([$menuId,$itemId]);
$tableNumber=33000+random_int(1,500);
$local->prepare("INSERT INTO cafe_tables(name,table_number,code,access_token,active,sort_order) VALUES(?,?,?,?,1,970)")
    ->execute(['P6C HTTP Table',$tableNumber,'P6C-HTTP-'.$suffix,'p6c-http-table-'.$suffix.'-token']);
$tableId=(int)$local->lastInsertId();

$bind=p6ch_local('/api/v1/local/bind.php',['display_name'=>'Phase 6C HTTP Cafe']);
if($bind['status']!==200)p6ch_fail('bind',$bind);

$projections=[
    ['projection_id'=>'user:'.$userId,'username'=>'p6c-http-'.$suffix,'display_name'=>'P6C HTTP Staff','role'=>'operator','password_hash'=>password_hash($password,PASSWORD_DEFAULT),'capabilities'=>['orders.table_draft'],'preparation_areas'=>[],'projection_version'=>1,'active'=>true],
    ['projection_id'=>'user:'.$deniedUserId,'username'=>'p6c-http-denied-'.$suffix,'display_name'=>'P6C HTTP Denied','role'=>'operator','password_hash'=>password_hash($password,PASSWORD_DEFAULT),'capabilities'=>[],'preparation_areas'=>[],'projection_version'=>1,'active'=>true],
];
$sync=p6ch_local('/api/v1/local/projection_sync.php',['projections'=>$projections]);
if($sync['status']!==200||(int)($sync['json']['synced']??0)!==2)p6ch_fail('projection sync',$sync);
$hb=p6ch_local('/api/v1/local/heartbeat.php',['local_version'=>'1.36.4-dev.34','runtime_status'=>'running','telemetry'=>['healthy'=>true]]);
if($hb['status']!==200)p6ch_fail('fresh heartbeat',$hb);

$token=p6ch_login('p6c-http-'.$suffix,$password);
$deniedToken=p6ch_login('p6c-http-denied-'.$suffix,$password);

$payload=[
    'table_id'=>$tableId,'expected_version'=>0,'expected_session_id'=>0,'note'=>'remote shared draft',
    'items'=>[['id'=>$itemId,'quantity'=>1,'expected_price'=>42000,'note'=>'','fulfillment_mode'=>'dine_in']],
];
$denied=p6ch_enqueue($deniedToken,'p6c-http-denied-'.$suffix,'table_draft.create',$payload);
if($denied['status']!==403||($denied['json']['error']??'')!=='forbidden')p6ch_fail('projected capability denial',$denied);
p6ch_pass('Public denies Table Draft without projected capability');

$requestId='p6c-http-create-'.$suffix;
$enq=p6ch_enqueue($token,$requestId,'table_draft.create',$payload);
if($enq['status']!==202||($enq['json']['state']??'')!=='queued')p6ch_fail('draft create enqueue',$enq);
p6ch_pass('fresh Local heartbeat permits Realtime Table Draft enqueue');

$claim=p6ch_local('/api/v1/local/claim.php',['lease_seconds'=>20]);
if($claim['status']!==200||($claim['json']['request']['request_id']??'')!==$requestId)p6ch_fail('draft create claim',$claim);
$localResult=sokna_relay_process_claim($local,['envelope'=>$claim['json']['request']]);
if(($localResult['state']??'')!=='committed'||empty($localResult['result']['draft']['id']))p6ch_fail('draft create Local commit',$localResult);
$draftId=(int)$localResult['result']['draft']['id'];
if((int)($localResult['result']['draft']['version']??0)!==1)p6ch_fail('draft create version',$localResult);
$ack=p6ch_local('/api/v1/local/ack.php',['request_id'=>$requestId,'lease_token'=>(string)$claim['json']['lease_token'],'state'=>'committed','error_code'=>'','result'=>$localResult['result']]);
if($ack['status']!==200)p6ch_fail('draft create ack',$ack);
p6ch_pass('Realtime Table Draft commits only through Local canonical owner');

$result=p6ch_http('GET',$base.'/api/v1/realtime/result.php?request_id='.rawurlencode($requestId),[],['Authorization: Bearer '.$token]);
if($result['status']!==200||($result['json']['state']??'')!=='committed'||(int)($result['json']['result']['draft']['id']??0)!==$draftId)p6ch_fail('draft committed result',$result);

// Public projection remains authorized while Local current authority is revoked.
$local->prepare("UPDATE user_capabilities SET enabled=0 WHERE user_id=? AND capability='orders_floor'")->execute([$userId]);
$editPayload=$payload;$editPayload['expected_version']=1;$editPayload['note']='must be rejected locally';
$editId='p6c-http-edit-'.$suffix;
$editEnq=p6ch_enqueue($token,$editId,'table_draft.edit',$editPayload);
if($editEnq['status']!==202)p6ch_fail('edit accepted by projected Public capability',$editEnq);
$editClaim=p6ch_local('/api/v1/local/claim.php',['lease_seconds'=>20]);
if($editClaim['status']!==200||($editClaim['json']['request']['request_id']??'')!==$editId)p6ch_fail('edit claim',$editClaim);
$editLocal=sokna_relay_process_claim($local,['envelope'=>$editClaim['json']['request']]);
if(($editLocal['state']??'')!=='rejected'||($editLocal['error_code']??'')!=='forbidden')p6ch_fail('Local authority revalidation',$editLocal);
$editAck=p6ch_local('/api/v1/local/ack.php',['request_id'=>$editId,'lease_token'=>(string)$editClaim['json']['lease_token'],'state'=>'rejected','error_code'=>(string)$editLocal['error_code'],'result'=>$editLocal['result']]);
if($editAck['status']!==200)p6ch_fail('rejected edit ack',$editAck);
$versionStmt=$local->prepare('SELECT version FROM table_drafts WHERE id=?');$versionStmt->execute([$draftId]);
if((int)$versionStmt->fetchColumn()!==1)p6ch_fail('rejected remote edit mutated Local draft');
p6ch_pass('Local revalidates current actor permission even when Public projection is stale');

// A Table Draft request is Local-required: once heartbeat is stale it cannot even enter the Realtime queue.
$before=(int)$public->query("SELECT COUNT(*) FROM realtime_requests WHERE installation_id=".$public->quote($installation))->fetchColumn();
$public->prepare("UPDATE installation_heartbeats SET last_seen_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 MINUTE) WHERE installation_id=?")->execute([$installation]);
$stale=p6ch_enqueue($token,'p6c-http-stale-'.$suffix,'table_draft.get',['table_id'=>$tableId]);
if($stale['status']!==503||($stale['json']['error']??'')!=='local_unavailable')p6ch_fail('stale Local must reject Table Draft enqueue',$stale);
$after=(int)$public->query("SELECT COUNT(*) FROM realtime_requests WHERE installation_id=".$public->quote($installation))->fetchColumn();
if($after!==$before)p6ch_fail('Local-unavailable Table Draft entered durable Realtime queue',[$before,$after]);
p6ch_pass('Table Draft is Realtime/Local-required and never queues while Local is unavailable');

echo "Phase 6C Public Realtime Table Draft HTTP/MariaDB integration PASS\n";
