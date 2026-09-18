<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/relay_protocol.php';

$base=rtrim((string)(getenv('SOKNA_PUBLIC_TEST_URL')?:'http://127.0.0.1:18080'),'/');
$installation=(string)(getenv('SOKNA_PUBLIC_TEST_INSTALLATION')?:'test-installation');
$secret=(string)(getenv('SOKNA_PUBLIC_TEST_SECRET')?:'test-relay-secret-01234567890123456789');
$dsn=(string)(getenv('SOKNA_PUBLIC_TEST_DSN')?:'mysql:host=127.0.0.1;port=3306;dbname=sokna_public;charset=utf8mb4');
$dbUser=(string)(getenv('SOKNA_PUBLIC_TEST_DB_USER')?:'root');
$dbPass=(string)(getenv('SOKNA_PUBLIC_TEST_DB_PASS')?:'root');

function die3(string $m,mixed $ctx=null):never{fwrite(STDERR,"FAIL: $m\n");if($ctx!==null)fwrite(STDERR,json_encode($ctx,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");exit(1);}
function ok3(string $m):void{echo "PASS: $m\n";}
function req3(string $method,string $url,array $body,array $headers=[]):array{
    $raw=sokna_relay_canonical_json($body);$ctx=stream_context_create(['http'=>['method'=>$method,'header'=>implode("\r\n",array_merge(['Content-Type: application/json','Accept: application/json'],$headers)),'content'=>$raw,'ignore_errors'=>true,'timeout'=>10]]);
    $out=@file_get_contents($url,false,$ctx);$status=0;foreach(($http_response_header??[])as $line)if(preg_match('#^HTTP/\S+\s+(\d+)#',$line,$m))$status=(int)$m[1];
    $json=is_string($out)?json_decode($out,true):null;return ['status'=>$status,'json'=>is_array($json)?$json:[],'raw'=>(string)$out];
}
function local3(string $path,array $body):array{
    global $base,$installation,$secret;$raw=sokna_relay_canonical_json($body);$ts=(string)time();$nonce=bin2hex(random_bytes(12));$sig=sokna_relay_sign($secret,'POST',$path,$ts,$nonce,$raw);
    return req3('POST',$base.$path,$body,['X-Sokna-Installation: '.$installation,'X-Sokna-Timestamp: '.$ts,'X-Sokna-Nonce: '.$nonce,'X-Sokna-Signature: '.$sig]);
}
$bind=local3('/api/v1/local/bind.php',['display_name'=>'Phase3 Cafe']);if($bind['status']!==200)die3('bind',$bind);

$png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',true);
if(!is_string($png))die3('fixture decode');
$sha=hash('sha256',$png);
$media=['sha256'=>$sha,'mime'=>'image/png','extension'=>'png','size'=>strlen($png),'content_base64'=>base64_encode($png)];
$m=local3('/api/v1/local/guest/media.php',$media);if(!in_array($m['status'],[200,201],true)||empty($m['json']['ok']))die3('media upload',$m);ok3('content-addressed media upload');
$m2=local3('/api/v1/local/guest/media.php',$media);if($m2['status']!==200||empty($m2['json']['deduplicated']))die3('media dedupe',$m2);ok3('media upload is idempotent');

$snapshot=['format'=>'sokna-guest-snapshot-v1','menus'=>[['menu_key'=>'main','name'=>'Main','sort_order'=>1]],'catalogs'=>['main'=>['menu'=>['menu_key'=>'main','name'=>'Main','sort_order'=>1],'categories'=>[],'items'=>[]]],'tables'=>[['name'=>'1','code'=>'T1','token'=>'table-token-1','table_number'=>1,'sort_order'=>1,'zone_label'=>'']],'theme'=>['cafe_name'=>'CI Cafe','logo_path'=>'uploads/logo.png'],'messages'=>[],'marketing'=>['campaign'=>null,'events'=>[]],'features'=>['public_waiter_call_enabled'=>true]];
$manifest=['uploads/logo.png'=>['sha256'=>$sha,'mime'=>'image/png','extension'=>'png','size'=>strlen($png)]];
$content=['format'=>'sokna-guest-snapshot-v1','snapshot'=>$snapshot,'media_manifest'=>$manifest];$hash=sokna_relay_request_hash($content);
$rev='guest-'.substr($hash,0,32);$payload=['revision_id'=>$rev,'content_hash'=>$hash,'generated_at'=>gmdate('c'),'snapshot'=>$snapshot,'media_manifest'=>$manifest];
$p=local3('/api/v1/local/guest/publish.php',$payload);if($p['status']!==200||($p['json']['revision_id']??'')!==$rev)die3('first publish',$p);ok3('first immutable revision activated');

$pdo=new PDO($dsn,$dbUser,$dbPass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$stmt=$pdo->prepare('SELECT revision_id FROM guest_active_revisions WHERE installation_id=?');$stmt->execute([$installation]);if((string)$stmt->fetchColumn()!==$rev)die3('active pointer after first publish');ok3('active pointer points at complete revision');

$missingSha=str_repeat('a',64);$badManifest=['uploads/missing.png'=>['sha256'=>$missingSha,'mime'=>'image/png','extension'=>'png','size'=>1]];
$badContent=['format'=>'sokna-guest-snapshot-v1','snapshot'=>$snapshot,'media_manifest'=>$badManifest];$badHash=sokna_relay_request_hash($badContent);$badRev='guest-'.substr($badHash,0,32);
$bad=local3('/api/v1/local/guest/publish.php',['revision_id'=>$badRev,'content_hash'=>$badHash,'generated_at'=>gmdate('c'),'snapshot'=>$snapshot,'media_manifest'=>$badManifest]);
if($bad['status']!==409||($bad['json']['error']??'')!=='missing_media')die3('incomplete publish must fail',$bad);ok3('incomplete revision rejected before activation');
$stmt->execute([$installation]);if((string)$stmt->fetchColumn()!==$rev)die3('pointer changed after failed publish');ok3('failed publish preserves prior active pointer');

$availability=['generated_at'=>gmdate('c'),'order_acceptance'=>['cafe'=>true,'kitchen'=>true,'bar'=>true],'waiter_enabled'=>true,'items'=>['1'=>['item_code'=>'A','available'=>true,'order_available'=>true,'blocked_scope'=>null]]];
$availability['version']=hash('sha256',sokna_relay_canonical_json($availability));
$a=local3('/api/v1/local/guest/availability.php',$availability);if($a['status']!==200||($a['json']['version']??'')!==$availability['version'])die3('availability sync',$a);ok3('availability channel syncs independently');
$count=(int)$pdo->query("SELECT COUNT(*) FROM guest_publish_revisions WHERE installation_id=".$pdo->quote($installation))->fetchColumn();if($count!==1)die3('failed revision must not persist',$count);ok3('failed revision did not persist');
echo "Phase 3 guest publish HTTP/MariaDB integration PASS\n";
