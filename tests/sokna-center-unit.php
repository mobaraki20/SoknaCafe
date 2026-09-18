<?php
declare(strict_types=1);
$config=['app'=>['key'=>str_repeat('a',64),'url'=>'','trust_proxy_headers'=>false]];
$_SERVER['HTTPS']='on';
$_SERVER['SERVER_PORT']='443';
$_SERVER['HTTP_HOST']='cafe.example.com';
$_SERVER['SCRIPT_NAME']='/Menu/admin/center_settings.php';
require dirname(__DIR__).'/includes/functions.php';
require dirname(__DIR__).'/includes/sokna_center.php';

$GLOBALS['SOKNA_CENTER_CONFIG_OVERRIDE']=[
    'enabled'=>true,
    'base_url'=>'https://center.example.com',
    'secret'=>str_repeat('b',64),
    'origin'=>'https://cafe.example.com/Menu',
    'return_url'=>'https://cafe.example.com/Menu/center_return.php',
];

function fail_test(string $m): never { fwrite(STDERR,$m."\n"); exit(1); }
function expect_reason(callable $fn, string $reason): void {
    try { $fn(); } catch (SoknaCenterAuthException $e) { if($e->reasonCode===$reason)return; fail_test("expected $reason got {$e->reasonCode}"); }
    catch (Throwable $e) { fail_test("expected $reason got ".get_class($e)); }
    fail_test("expected failure $reason");
}
function decode_payload(string $token): array {
    $parts=explode('.',$token); if(count($parts)!==3) fail_test('token format');
    $raw=sokna_center_base64url_decode($parts[1]); if($raw===false)fail_test('payload decode');
    $payload=json_decode($raw,true); if(!is_array($payload))fail_test('payload json'); return $payload;
}

if(sokna_center_request_origin()!=='https://cafe.example.com') fail_test('origin mismatch');

// Encryption/storage codec must preserve the secret byte-for-byte; no trim/hash/base64 transform before HMAC.
$exactSecret='  CenterSecret-ABC_xyz-0123456789-KEEP-SPACES  ';
$encrypted=sokna_center_encrypt_secret($exactSecret);
if(!str_starts_with($encrypted,'enc:v2:')||sokna_center_decrypt_secret($encrypted)!==$exactSecret) fail_test('secret encryption roundtrip failed');

// SC1 exact prefix + Base64URL decode/padding + exact v/center/issuer/secret contract.
$pairPayload=json_encode(['v'=>1,'center'=>'https://center.example.com','issuer'=>'cafe','secret'=>$exactSecret],JSON_UNESCAPED_SLASHES);
$pairKey='SC1.'.sokna_center_base64url_encode((string)$pairPayload);
$parsed=sokna_center_parse_pairing_key($pairKey);
if(($parsed['base_url']??'')!=='https://center.example.com'||($parsed['secret']??'')!==$exactSecret) fail_test('pairing key parse/exact secret failed');
$failed=false;try{sokna_center_parse_pairing_key('sc1.'.substr($pairKey,4));}catch(InvalidArgumentException){$failed=true;}if(!$failed)fail_test('SC1 prefix must be exact');

// Canonical Center token claims are iss/sub/aud/iat/exp (not issuer/local_user_id/audience/issued_at/expires_at).
$handoff=sokna_center_build_token('handoff','17');
$payload=decode_payload($handoff);
$required=['iss','sub','context','aud','purpose','iat','exp','nonce','origin','return_url'];
foreach($required as $key) if(!array_key_exists($key,$payload)) fail_test('missing handoff claim '.$key);
if(($payload['iss']??'')!=='cafe'||($payload['sub']??'')!=='17'||($payload['aud']??'')!=='https://center.example.com'||($payload['context']??'')!=='CAFE'||($payload['purpose']??'')!=='handoff') fail_test('handoff claims');
if(($payload['origin']??'')!=='https://cafe.example.com'||($payload['return_url']??'')!=='https://cafe.example.com/Menu/center_return.php') fail_test('handoff origin/return');
if((int)$payload['exp']-(int)$payload['iat']!==60||strlen((string)$payload['nonce'])<32) fail_test('handoff ttl/nonce');
foreach(['issuer','local_user_id','audience','issued_at','expires_at','role','capabilities','password','password_hash','trust_mode'] as $forbidden) if(array_key_exists($forbidden,$payload)) fail_test('forbidden/noncanonical claim '.$forbidden);

// Exact header and HMAC-SHA256 over base64url(header).base64url(payload), binary=true.
$parts=explode('.',$handoff); if(count($parts)!==3) fail_test('compact token parts');
$headerRaw=sokna_center_base64url_decode($parts[0]); $header=json_decode((string)$headerRaw,true);
if($header!==['alg'=>'HS256','typ'=>'SOKNA-HANDOFF','v'=>1]) fail_test('handoff header contract');
$expectedSig=sokna_center_base64url_encode(hash_hmac('sha256',$parts[0].'.'.$parts[1],str_repeat('b',64),true));
if(!hash_equals($expectedSig,$parts[2])) fail_test('exact HMAC contract');
sokna_center_verify_compact($handoff,str_repeat('b',64),'SOKNA-HANDOFF');
expect_reason(fn()=>sokna_center_verify_compact($handoff,str_repeat('c',64),'SOKNA-HANDOFF'),'signature_invalid');


// Official Cafe -> Core payroll reminder summary uses its own S2S contract, without altering Handoff.
$s2s=sokna_center_build_s2s_read_token('payroll_reminder_summary',17,str_repeat('b',64));
$s2sParts=explode('.',$s2s); if(count($s2sParts)!==3) fail_test('s2s token format');
$s2sHeader=json_decode((string)sokna_center_base64url_decode($s2sParts[0]),true);
$s2sPayload=decode_payload($s2s);
if($s2sHeader!==['alg'=>'HS256','typ'=>'SOKNA-S2S','v'=>1]) fail_test('payroll s2s header contract');
foreach(['issuer'=>'cafe','audience'=>'center','purpose'=>'payroll_reminder_summary','context'=>'CAFE','sub'=>'17'] as $k=>$v) if((string)($s2sPayload[$k]??'')!==$v) fail_test('payroll s2s claim '.$k);
if((int)($s2sPayload['expires_at']??0)-(int)($s2sPayload['timestamp']??0)!==60||strlen((string)($s2sPayload['nonce']??''))<20) fail_test('payroll s2s ttl/nonce');
$expectedS2sSig=sokna_center_base64url_encode(hash_hmac('sha256',$s2sParts[0].'.'.$s2sParts[1],str_repeat('b',64),true));
if(!hash_equals($expectedS2sSig,$s2sParts[2])) fail_test('payroll s2s exact HMAC');

$payrollSeen=false;
$GLOBALS['SOKNA_CENTER_TRANSPORT']=function(string $method,string $url,array $headers,string $body,int $timeout) use (&$payrollSeen): array {
    if($method!=='GET'||!str_ends_with($url,'/api/s2s/payroll_reminders.php')) fail_test('payroll endpoint mismatch');
    if($body!=='') fail_test('payroll read must not send body');
    if($timeout!==2) fail_test('payroll timeout contract');
    $auth=''; foreach($headers as $header) if(str_starts_with($header,'Authorization: Sokna-HMAC ')) $auth=substr($header,strlen('Authorization: Sokna-HMAC '));
    if($auth==='') fail_test('payroll authorization missing');
    $claims=sokna_center_verify_compact($auth,str_repeat('b',64),'SOKNA-S2S');
    if(($claims['purpose']??'')!=='payroll_reminder_summary'||($claims['sub']??'')!=='17') fail_test('payroll authorization claims');
    $payrollSeen=true;
    return ['transport_ok'=>true,'http_status'=>200,'raw'=>json_encode(['ok'=>true,'data'=>['count'=>12,'has_attention'=>true,'employee_name'=>'MUST_BE_IGNORED']])];
};
if(sokna_center_payroll_reminder_count(17,2)!==12||!$payrollSeen) fail_test('payroll count-only read failed');
$GLOBALS['SOKNA_CENTER_TRANSPORT']=fn()=>['transport_ok'=>true,'http_status'=>403,'raw'=>'{}'];
expect_reason(fn()=>sokna_center_payroll_reminder_count(17,2),'trust_rejected');
$GLOBALS['SOKNA_CENTER_TRANSPORT']=fn()=>['transport_ok'=>true,'http_status'=>200,'raw'=>'{"ok":true,"data":{"count":-1}}'];
expect_reason(fn()=>sokna_center_payroll_reminder_count(17,2),'payload_invalid');
unset($GLOBALS['SOKNA_CENTER_TRANSPORT']);

$pairToken=sokna_center_build_token('pair','17','CAFE',null,null,'pair');
$probeToken=sokna_center_build_token('probe','17','CAFE',null,null,'strict');
if((decode_payload($pairToken)['purpose']??'')!=='pair') fail_test('pair must be explicit');
if((decode_payload($probeToken)['purpose']??'')!=='probe') fail_test('probe must be strict operation');
$failed=false;try{sokna_center_build_token('handoff','17','HOUSE');}catch(InvalidArgumentException){$failed=true;}if(!$failed)fail_test('non CAFE handoff must be rejected');

// Re-pair must use the NEW explicit secret, even if an OLD secret is already loaded in Cafe config.
$newPairSecret='NewCenterSecret-ABCDEFGHIJKLMNOPQRSTUVWXYZ-1234567890';
$pairSeen=false;
$GLOBALS['SOKNA_CENTER_CONFIG_OVERRIDE']['secret']=str_repeat('o',64); // stale/old active secret on purpose
$GLOBALS['SOKNA_CENTER_TRANSPORT']=function(string $method,string $url,array $headers,string $body,int $timeout) use (&$pairSeen,$newPairSecret): array {
    if($method!=='POST'||!str_ends_with($url,'/auth/pair.php')) fail_test('pair endpoint mismatch');
    parse_str($body,$fields); $token=(string)($fields['token']??'');
    $claims=sokna_center_verify_compact($token,$newPairSecret,'SOKNA-HANDOFF');
    foreach(['iss'=>'cafe','sub'=>'17','context'=>'CAFE','aud'=>'https://center.example.com','purpose'=>'pair'] as $k=>$v) if((string)($claims[$k]??'')!==$v) fail_test('pair canonical claim '.$k);
    if((int)($claims['exp']??0)-(int)($claims['iat']??0)!==60) fail_test('pair ttl');
    $pairSeen=true;
    return ['transport_ok'=>true,'http_status'=>200,'raw'=>json_encode(['ok'=>true,'data'=>['context'=>'CAFE','issuer'=>'cafe','origin'=>'https://cafe.example.com','return_url'=>'https://cafe.example.com/Menu/center_return.php','version'=>'1.0']])];
};
$result=sokna_center_pair_remote('https://center.example.com',$newPairSecret,17);
if(!$pairSeen||($result['version']??'')!=='1.0') fail_test('explicit pair/new-secret flow failed');
unset($GLOBALS['SOKNA_CENTER_TRANSPORT']);
$GLOBALS['SOKNA_CENTER_CONFIG_OVERRIDE']['secret']=str_repeat('b',64);

// Center error.code/http status must be retained only as safe diagnostics, never token/secret.
$failed=false;
try {
    sokna_center_remote_result(['transport_ok'=>true,'http_status'=>401,'raw'=>json_encode(['ok'=>false,'error'=>['code'=>'HANDOFF_SIGNATURE']])],'pair');
} catch (SoknaCenterAuthException $e) {
    $failed=true;
    if(($e->diagnostics['http_status']??0)!==401||($e->diagnostics['remote_error_code']??'')!=='HANDOFF_SIGNATURE') fail_test('remote diagnostics missing');
}
if(!$failed) fail_test('remote rejection expected');

$public=sokna_center_directory_public_user(['id'=>7,'display_name'=>'امیر محمدی','username'=>'amir','password_hash'=>'SECRET','role'=>'operator','active'=>1,'updated_at'=>'2026-08-12 01:00:00','session'=>'NOPE']);
if(array_keys($public)!==['local_user_id','display_name','role','active','updated_at']) fail_test('directory allow-list changed');
if(($public['local_user_id']??'')!=='7'||($public['display_name']??'')!=='امیر محمدی'||($public['active']??false)!==true) fail_test('directory mapping');
foreach(['username','password','password_hash','session','token','csrf_token'] as $forbidden) if(array_key_exists($forbidden,$public)) fail_test('sensitive directory field '.$forbidden);

$seen=[];
$GLOBALS['SOKNA_CENTER_NONCE_STORE']=function(string $nonce,int $expiresAt) use (&$seen): bool { if(isset($seen[$nonce]))return false; $seen[$nonce]=$expiresAt; return true; };
$now=time();
$makeDirectoryToken=static function(array $overrides=[]) use ($now): string {
    $claims=array_merge([
        'issuer'=>'center','audience'=>'cafe','purpose'=>'user_directory','context'=>'CAFE',
        'timestamp'=>$now,'expires_at'=>$now+60,'nonce'=>'nonce_1234567890abcdef','page'=>1,'per_page'=>50,
    ],$overrides);
    return sokna_center_sign_compact($claims,str_repeat('b',64),'SOKNA-S2S');
};
$_SERVER['HTTP_AUTHORIZATION']='Sokna-HMAC '.$makeDirectoryToken();
$verified=sokna_center_verify_user_directory_request(1,50);
if(($verified['purpose']??'')!=='user_directory') fail_test('directory auth success');
expect_reason(fn()=>sokna_center_verify_user_directory_request(1,50),'nonce_replay');

$_SERVER['HTTP_AUTHORIZATION']='';
expect_reason(fn()=>sokna_center_verify_user_directory_request(1,50),'signature_missing');
$_SERVER['HTTP_AUTHORIZATION']='Sokna-HMAC '.$makeDirectoryToken(['nonce'=>'nonce_bad_signature_01']).'x';
expect_reason(fn()=>sokna_center_verify_user_directory_request(1,50),'signature_invalid');
$_SERVER['HTTP_AUTHORIZATION']='Sokna-HMAC '.$makeDirectoryToken(['nonce'=>'nonce_expired_123456','timestamp'=>$now-120,'expires_at'=>$now-60]);
expect_reason(fn()=>sokna_center_verify_user_directory_request(1,50),'request_expired');
$_SERVER['HTTP_AUTHORIZATION']='Sokna-HMAC '.$makeDirectoryToken(['nonce'=>'nonce_wrong_context_123','context'=>'HOUSE']);
expect_reason(fn()=>sokna_center_verify_user_directory_request(1,50),'claims_invalid');
$_SERVER['HTTP_AUTHORIZATION']='Sokna-HMAC '.$makeDirectoryToken(['nonce'=>'nonce_page_mismatch_123']);
expect_reason(fn()=>sokna_center_verify_user_directory_request(2,50),'pagination_mismatch');

$failed=false;try{sokna_center_validate_base_url('http://center.example.com');}catch(Throwable){$failed=true;}if(!$failed)fail_test('http must be rejected');
$failed=false;try{sokna_center_validate_base_url('https://center.example.com/?token=x');}catch(Throwable){$failed=true;}if(!$failed)fail_test('query must be rejected');

if(sokna_center_personnel_entitlement_from_data(['can_open_personnel'=>true])!==true) fail_test('personnel entitlement allow');
if(sokna_center_personnel_entitlement_from_data(['entitlements'=>['can_open_personnel'=>false]])!==false) fail_test('personnel entitlement deny');
if(sokna_center_personnel_entitlement_from_data(['entitlements'=>['personnel'=>'1']])!==true) fail_test('personnel entitlement compatible bool');
if(sokna_center_personnel_entitlement_from_data(['version'=>'legacy'])!==null) fail_test('missing personnel entitlement must stay unknown');

echo "Sokna Center 1.31.7 unit/security/signature contracts passed.\n";
