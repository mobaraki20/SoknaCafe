<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
require_once dirname(__DIR__).'/includes/relay_dispatch.php';

$publicBase=rtrim((string)(getenv('SOKNA_PUBLIC_TEST_URL')?:'http://127.0.0.1:18080'),'/');
$installation=(string)(getenv('SOKNA_PUBLIC_TEST_INSTALLATION')?:'test-installation');
$secret=(string)(getenv('SOKNA_PUBLIC_TEST_SECRET')?:'test-relay-secret-01234567890123456789');
$publicDsn=(string)(getenv('SOKNA_PUBLIC_TEST_DSN')?:'mysql:host=127.0.0.1;port=3306;dbname=sokna_public;charset=utf8mb4');
$publicUser=(string)(getenv('SOKNA_PUBLIC_TEST_DB_USER')?:'root');
$publicPass=(string)(getenv('SOKNA_PUBLIC_TEST_DB_PASS')?:'root');

function rt_fail(string $m,mixed $ctx=null):never{fwrite(STDERR,"FAIL: $m\n");if($ctx!==null)fwrite(STDERR,json_encode($ctx,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n");exit(1);}
function rt_pass(string $m):void{fwrite(STDOUT,"PASS: $m\n");}
function rt_http(string $method,string $url,array $body=[],array $headers=[]):array{
    $opts=['method'=>$method,'header'=>implode("\r\n",array_merge(['Accept: application/json'],$headers)),'ignore_errors'=>true,'timeout'=>10];
    if($method!=='GET'){$opts['header'].="\r\nContent-Type: application/json";$opts['content']=sokna_relay_canonical_json($body);}
    $ctx=stream_context_create(['http'=>$opts]);$raw=@file_get_contents($url,false,$ctx);$status=0;
    foreach(($http_response_header??[])as $line)if(preg_match('#^HTTP/\S+\s+(\d+)#',$line,$m))$status=(int)$m[1];
    $json=is_string($raw)?json_decode($raw,true):null;return ['status'=>$status,'json'=>is_array($json)?$json:[],'raw'=>(string)$raw];
}
function rt_signed(string $path,array $body):array{
    global $publicBase,$installation,$secret;
    $raw=sokna_relay_canonical_json($body);$ts=(string)time();$nonce=bin2hex(random_bytes(12));$sig=sokna_relay_sign($secret,'POST',$path,$ts,$nonce,$raw);
    return rt_http('POST',$publicBase.$path,$body,[
        'X-Sokna-Installation: '.$installation,'X-Sokna-Timestamp: '.$ts,'X-Sokna-Nonce: '.$nonce,'X-Sokna-Signature: '.$sig
    ]);
}
function rt_guest_enqueue(string $requestId,string $kind,string $client,string $device,string $tableToken,array $payload):array{
    global $publicBase,$installation;
    return rt_http('POST',$publicBase.'/api/v1/guest/enqueue.php',[
        'installation_id'=>$installation,'request_id'=>$requestId,'kind'=>$kind,'client_token'=>$client,'device_token'=>$device,'table_token'=>$tableToken,'payload'=>$payload,
    ]);
}
function rt_guest_result(string $requestId,string $client):array{
    global $publicBase,$installation;
    return rt_http('GET',$publicBase.'/api/v1/guest/result.php?installation_id='.rawurlencode($installation).'&request_id='.rawurlencode($requestId).'&client_token='.rawurlencode($client));
}

$local=db();$public=new PDO($publicDsn,$publicUser,$publicPass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);

// Minimal canonical Local business state.
$local->exec("INSERT INTO menus(menu_key,name,status,sort_order) VALUES('main','Main','active',1)");
$menuId=(int)$local->lastInsertId();
$local->exec("INSERT INTO categories(category_key,name,audience,sort_order,active) VALUES('drinks','Drinks','guest_staff',1,1)");
$categoryId=(int)$local->lastInsertId();
$local->prepare('INSERT INTO menu_categories(menu_id,category_id,sort_order) VALUES(?,?,1)')->execute([$menuId,$categoryId]);
$local->prepare("INSERT INTO items(item_code,category_id,name,price,available,active,staff_only,takeaway_allowed,preparation_station,sort_order) VALUES('CI-1',?,'CI Latte',100000,1,1,0,1,'cold_bar',1)")->execute([$categoryId]);
$itemId=(int)$local->lastInsertId();
$local->prepare('INSERT INTO menu_items(menu_id,item_id) VALUES(?,?)')->execute([$menuId,$itemId]);
$tableToken='ci-table-token-1234567890123456';
$local->prepare("INSERT INTO cafe_tables(name,table_number,code,access_token,active,sort_order) VALUES('میز ۱',1,'T1',?,1,1)")->execute([$tableToken]);
$tableId=(int)$local->lastInsertId();

$bind=rt_signed('/api/v1/local/bind.php',['display_name'=>'Realtime CI Cafe']);
if($bind['status']!==200)rt_fail('bind',$bind);

$snapshot=[
    'format'=>'sokna-guest-snapshot-v1',
    'menus'=>[['menu_key'=>'main','name'=>'Main','sort_order'=>1]],
    'catalogs'=>['main'=>[
        'menu'=>['menu_key'=>'main','name'=>'Main','sort_order'=>1],
        'categories'=>[['id'=>$categoryId,'category_key'=>'drinks','name'=>'Drinks','image_path'=>'','icon_key'=>'','sort_order'=>1]],
        'items'=>[['id'=>$itemId,'item_code'=>'CI-1','category_id'=>$categoryId,'name'=>'CI Latte','description'=>'','price'=>100000,'image_path'=>'','available'=>true,'featured'=>false,'takeaway_allowed'=>true,'suggested_item_id'=>null,'sort_order'=>1,'tags'=>[]]],
    ]],
    'tables'=>[['name'=>'میز ۱','code'=>'T1','token'=>$tableToken,'public_ref'=>substr(hash('sha256',$tableToken),0,32),'table_number'=>1,'sort_order'=>1,'zone_label'=>'']],
    'theme'=>['cafe_name'=>'Realtime CI','primary_color'=>'#365b4c','accent_color'=>'#b85c38','background_color'=>'#f7f3ec','logo_path'=>'','menu_theme'=>'courtyard','menu_font'=>'vazirmatn','menu_density'=>'balanced','menu_layout'=>'editorial','seo_description'=>'','social_footer_enabled'=>false,'social_links'=>[]],
    'messages'=>[],
    'marketing'=>['campaign'=>null,'events'=>[]],
    'features'=>['public_waiter_call_enabled'=>true,'events_enabled'=>false,'campaigns_enabled'=>false],
];
$manifest=[];$content=['format'=>'sokna-guest-snapshot-v1','snapshot'=>$snapshot,'media_manifest'=>$manifest];$hash=sokna_relay_request_hash($content);$revision='guest-'.substr($hash,0,32);
$pub=rt_signed('/api/v1/local/guest/publish.php',['revision_id'=>$revision,'content_hash'=>$hash,'generated_at'=>gmdate('c'),'snapshot'=>$snapshot,'media_manifest'=>$manifest]);
if($pub['status']!==200)rt_fail('publish snapshot',$pub);
rt_pass('published snapshot is active');

$heartbeat=rt_signed('/api/v1/local/heartbeat.php',['local_version'=>'1.36.4-dev.29','runtime_status'=>'running','telemetry'=>['healthy'=>true,'stale'=>false]]);
if($heartbeat['status']!==200)rt_fail('heartbeat',$heartbeat);
$availability=['generated_at'=>gmdate('c'),'order_acceptance'=>['cafe'=>true,'kitchen'=>true,'bar'=>true],'waiter_enabled_table'=>true,'waiter_enabled_public'=>true,'items'=>[(string)$itemId=>['item_code'=>'CI-1','available'=>true,'order_available'=>true,'blocked_scope'=>null]]];
$availability['version']=hash('sha256',sokna_relay_canonical_json($availability));
$av=rt_signed('/api/v1/local/guest/availability.php',$availability);if($av['status']!==200)rt_fail('availability',$av);
rt_pass('fresh heartbeat and availability enable guest actions');

$client='client-token-1234567890';$device='device-token-1234567890';$requestId='guest-order:ci-001';
$orderPayload=['session_token'=>'','customer_note'=>'','items'=>[['id'=>$itemId,'quantity'=>1,'unit_price'=>100000,'note'=>'','fulfillment_mode'=>'dine_in']]];
$enq=rt_guest_enqueue($requestId,'guest_order.submit',$client,$device,$tableToken,$orderPayload);
if($enq['status']!==202||($enq['json']['state']??'')!=='queued')rt_fail('guest enqueue',$enq);
$dup=rt_guest_enqueue($requestId,'guest_order.submit',$client,$device,$tableToken,$orderPayload);
if($dup['status']!==200||empty($dup['json']['deduplicated']))rt_fail('guest retry dedupe',$dup);
rt_pass('browser retry deduplicates despite server-generated timestamps');

$claim1=rt_signed('/api/v1/local/claim.php',['lease_seconds'=>5]);
if($claim1['status']!==200||($claim1['json']['request']['request_id']??'')!==$requestId)rt_fail('first claim',$claim1);
$localResult1=sokna_relay_process_claim($local,['envelope'=>$claim1['json']['request']]);
if(($localResult1['state']??'')!=='committed')rt_fail('first Local commit',$localResult1);
if((int)$local->query('SELECT COUNT(*) FROM orders')->fetchColumn()!==1)rt_fail('first commit order count');
rt_pass('Public request commits through canonical Local order service');

// Simulate network loss after Local COMMIT but before Public ACK.
$expireLease=$public->prepare("UPDATE realtime_requests SET lease_expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND),expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE installation_id=? AND request_id=?");
$expireLease->execute([$installation,$requestId]);
$ambiguous=rt_guest_result($requestId,$client);
if($ambiguous['status']!==200||($ambiguous['json']['state']??'')!=='claimed'||!empty($ambiguous['json']['terminal']))rt_fail('claimed ambiguity must not be falsely expired',$ambiguous);
rt_pass('claimed request remains ambiguous after Public TTL until Local reconciliation');
$claim2=rt_signed('/api/v1/local/claim.php',['lease_seconds'=>5]);
if($claim2['status']!==200||($claim2['json']['request']['request_id']??'')!==$requestId)rt_fail('reclaim after lost ACK',$claim2);
$reconcileNow=(strtotime((string)$claim2['json']['request']['expires_at'])?:time())+5;
$localResult2=sokna_relay_process_claim($local,['envelope'=>$claim2['json']['request']],null,$reconcileNow);
if(($localResult2['state']??'')!=='committed'||empty($localResult2['deduplicated']))rt_fail('Local dedupe after reclaim',$localResult2);
if((int)$local->query('SELECT COUNT(*) FROM orders')->fetchColumn()!==1)rt_fail('ambiguous retry created duplicate order');
rt_pass('lost ACK re-claim returns canonical Local result without second Order');

$ack=rt_signed('/api/v1/local/ack.php',['request_id'=>$requestId,'lease_token'=>(string)$claim2['json']['lease_token'],'state'=>'committed','error_code'=>'','result'=>$localResult2['result']]);
if($ack['status']!==200)rt_fail('ACK after reclaim',$ack);
$result=rt_guest_result($requestId,$client);
if($result['status']!==200||($result['json']['state']??'')!=='committed'||empty($result['json']['result']['order_code']))rt_fail('guest committed result',$result);
rt_pass('guest success becomes visible only after terminal Local commit ACK');

// Local business rejection remains terminal and does not mutate.
$badClient='client-token-bad-123456';$badDevice='device-token-bad-123456';$badReq='guest-order:ci-bad-price';
$badPayload=['session_token'=>'','customer_note'=>'','items'=>[['id'=>$itemId,'quantity'=>1,'unit_price'=>999,'note'=>'','fulfillment_mode'=>'dine_in']]];
$badEnq=rt_guest_enqueue($badReq,'guest_order.submit',$badClient,$badDevice,$tableToken,$badPayload);if($badEnq['status']!==202)rt_fail('bad-price enqueue',$badEnq);
$badClaim=rt_signed('/api/v1/local/claim.php',['lease_seconds'=>5]);if($badClaim['status']!==200)rt_fail('bad-price claim',$badClaim);
$badLocal=sokna_relay_process_claim($local,['envelope'=>$badClaim['json']['request']]);
if(($badLocal['state']??'')!=='rejected'||($badLocal['error_code']??'')!=='prices_changed')rt_fail('bad-price terminal rejection',$badLocal);
if((int)$local->query('SELECT COUNT(*) FROM orders')->fetchColumn()!==1)rt_fail('rejected request mutated orders');
$badAck=rt_signed('/api/v1/local/ack.php',['request_id'=>$badReq,'lease_token'=>(string)$badClaim['json']['lease_token'],'state'=>'rejected','error_code'=>(string)$badLocal['error_code'],'result'=>$badLocal['result']]);if($badAck['status']!==200)rt_fail('bad ACK',$badAck);
rt_pass('Local business rejection is terminal without mutation');

// Expired request must never surprise-commit.
$lateClient='client-token-late-12345';$lateReq='guest-order:ci-expired';
$late=rt_guest_enqueue($lateReq,'guest_order.submit',$lateClient,$device,$tableToken,$orderPayload);if($late['status']!==202)rt_fail('late enqueue',$late);
$public->prepare("UPDATE realtime_requests SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE installation_id=? AND request_id=?")->execute([$installation,$lateReq]);
$empty=rt_signed('/api/v1/local/claim.php',['lease_seconds'=>5]);
if($empty['status']!==404||($empty['json']['error']??'')!=='empty_queue')rt_fail('expired request should not claim',$empty);
if((int)$local->query('SELECT COUNT(*) FROM orders')->fetchColumn()!==1)rt_fail('expired request surprise committed');
rt_pass('expired guest request never reaches Local mutation');

// Waiter call uses the same Relay boundary and canonical Local owner.
$waitClient='waiter-client-123456789';$waitReq='waiter-call:ci-001';
$wait=rt_guest_enqueue($waitReq,'waiter_call.create',$waitClient,$device,$tableToken,['session_token'=>'']);
if($wait['status']!==202)rt_fail('waiter enqueue',$wait);
$waitClaim=rt_signed('/api/v1/local/claim.php',['lease_seconds'=>5]);if($waitClaim['status']!==200)rt_fail('waiter claim',$waitClaim);
$waitLocal=sokna_relay_process_claim($local,['envelope'=>$waitClaim['json']['request']]);
if(($waitLocal['state']??'')!=='committed'||empty($waitLocal['result']['call_code']))rt_fail('waiter Local commit',$waitLocal);
$waitAck=rt_signed('/api/v1/local/ack.php',['request_id'=>$waitReq,'lease_token'=>(string)$waitClaim['json']['lease_token'],'state'=>'committed','error_code'=>'','result'=>$waitLocal['result']]);if($waitAck['status']!==200)rt_fail('waiter ACK',$waitAck);
if((int)$local->query('SELECT COUNT(*) FROM waiter_calls')->fetchColumn()!==1)rt_fail('waiter call count');
rt_pass('waiter call commits through canonical Local owner');

// Published content remains readable when Local becomes stale, but no new
// mutation may enter the durable queue in degraded mode.
$page=rt_http('GET',$publicBase.'/guest/?installation_id='.rawurlencode($installation).'&table='.rawurlencode($tableToken));
if($page['status']!==200||!str_contains($page['raw'],'CI Latte'))rt_fail('fresh Public guest renderer',$page);
if(!str_contains($page['raw'],'path=assets%2Fcss%2Fguest-menu.css'))rt_fail('Public renderer missing canonical guest stylesheet',$page);
if(!str_contains($page['raw'],'path=assets%2Fjs%2Fmenu.js'))rt_fail('Public renderer missing canonical menu runtime',$page);
if(str_contains($page['raw'],'guest/app.js'))rt_fail('Public renderer still references forked guest runtime',$page);
if(!str_contains($page['raw'],'api/v1/guest/compat/create_order.php?installation_id='.rawurlencode($installation)))rt_fail('Public runtime API is not installation-bound',$page);
rt_pass('published guest menu renders through canonical shared CSS/JS and installation-bound APIs');

$public->prepare("UPDATE installation_heartbeats SET last_seen_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 MINUTE) WHERE installation_id=?")->execute([$installation]);
$public->prepare("UPDATE guest_availability_state SET last_sync_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 MINUTE) WHERE installation_id=?")->execute([$installation]);
$degradedPage=rt_http('GET',$publicBase.'/guest/?installation_id='.rawurlencode($installation).'&table='.rawurlencode($tableToken));
if($degradedPage['status']!==200||!str_contains($degradedPage['raw'],'CI Latte')||!str_contains($degradedPage['raw'],'ارتباط زنده'))rt_fail('degraded Public guest renderer',$degradedPage);
rt_pass('stale Local keeps published menu readable with degraded notice');

$beforeBlocked=(int)$public->query("SELECT COUNT(*) FROM realtime_requests WHERE installation_id=".$public->quote($installation))->fetchColumn();
$blocked=rt_guest_enqueue('guest-order:ci-stale','guest_order.submit','client-token-stale-12345','device-token-stale-12345',$tableToken,$orderPayload);
if($blocked['status']!==503||($blocked['json']['error']??'')!=='local_unavailable')rt_fail('stale mutation must be blocked',$blocked);
$afterBlocked=(int)$public->query("SELECT COUNT(*) FROM realtime_requests WHERE installation_id=".$public->quote($installation))->fetchColumn();
if($afterBlocked!==$beforeBlocked)rt_fail('stale mutation entered queue',[$beforeBlocked,$afterBlocked]);
rt_pass('degraded mode rejects new mutation before queue insertion');

echo "Phase 3 guest realtime dual-DB integration PASS\n";
