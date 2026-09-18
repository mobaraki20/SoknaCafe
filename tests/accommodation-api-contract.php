<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/functions.php';
require dirname(__DIR__) . '/includes/accommodation.php';

$fail=[];$checks=0;
function acc_check(bool $ok,string $message):void{global $fail,$checks;$checks++;if(!$ok)$fail[]=$message;}
$GLOBALS['SOKNA_ACCOMMODATION_CONFIG_OVERRIDE']=['enabled'=>true,'base_url'=>'https://stay.example.test/api.php','api_key'=>'secret-test-key'];
$calls=[];
$GLOBALS['SOKNA_ACCOMMODATION_TRANSPORT']=function(string $method,string $url,array $headers,string $body,int $timeout) use (&$calls):array{
    $calls[]=['method'=>$method,'url'=>$url,'headers'=>$headers,'body'=>$body,'timeout'=>$timeout];
    parse_str((string)parse_url($url,PHP_URL_QUERY),$query);
    $action=$query['action']??'';
    if($action==='capabilities')return ['transport_ok'=>true,'http_status'=>200,'payload'=>['ok'=>true,'api_version'=>'2.0','capabilities'=>['unified_search'=>true,'invoice_snapshot'=>true,'idempotent_charge'=>true,'charge_void'=>true]]];
    if($action==='search'){
        if(($query['query']??'')==='AUTH')return ['transport_ok'=>true,'http_status'=>401,'payload'=>['ok'=>false,'error'=>'unauthorized']];
        if(($query['query']??'')==='NONE')return ['transport_ok'=>true,'http_status'=>200,'payload'=>['ok'=>true,'reservations'=>[]]];
        return ['transport_ok'=>true,'http_status'=>200,'payload'=>['ok'=>true,'reservations'=>[['reservation_code'=>'SK-1405-00012','guest_name'=>'مهمان تست','room_names'=>['سیف'],'check_in'=>'2026-08-04','check_out'=>'2026-08-07','masked_mobile'=>'0912***4567','charge_allowed'=>true,'charge_block_reason'=>null]]]];
    }
    if($action==='reservation'){
        if(($query['reservation_code']??'')==='BLOCKED')return ['transport_ok'=>true,'http_status'=>200,'payload'=>['ok'=>true,'reservation'=>['reservation_code'=>'BLOCKED','guest_name'=>'مهمان','room_names'=>['سیف'],'check_in'=>'2026-07-01','check_out'=>'2026-07-02','masked_mobile'=>'0912***4567','charge_allowed'=>false,'charge_block_reason'=>'اقامت پایان یافته است.']]];
        return ['transport_ok'=>true,'http_status'=>200,'payload'=>['ok'=>true,'reservation'=>['reservation_code'=>'SK-1405-00012','guest_name'=>'مهمان تست','room_names'=>['سیف'],'check_in'=>'2026-08-04','check_out'=>'2026-08-07','masked_mobile'=>'0912***4567','charge_allowed'=>true,'charge_block_reason'=>null]]];
    }
    if($action==='charge'){
        $payload=json_decode($body,true);
        if(($payload['external_order_id']??'')==='CAFE-S-88')return ['transport_ok'=>false,'http_status'=>0,'payload'=>[],'error'=>'timeout'];
        if(($payload['external_order_id']??'')==='CAFE-S-CONFLICT')return ['transport_ok'=>true,'http_status'=>409,'payload'=>['ok'=>false,'error'=>'external_order_conflict','message'=>'conflict','tracking_id'=>'HOUSE-CONFLICT-1']];
        if(($payload['external_order_id']??'')==='CAFE-S-SCHEMA')return ['transport_ok'=>true,'http_status'=>503,'payload'=>['ok'=>false,'error'=>'schema_not_ready','message'=>'schema repair required','tracking_id'=>'HOUSE-SCHEMA-1']];
        if(($payload['external_order_id']??'')==='CAFE-S-TEMP')return ['transport_ok'=>true,'http_status'=>503,'payload'=>['ok'=>false,'error'=>'temporary_failure','message'=>'deadlock retry','tracking_id'=>'HOUSE-TEMP-1']];
        if(($payload['external_order_id']??'')==='CAFE-S-INTERNAL')return ['transport_ok'=>true,'http_status'=>500,'payload'=>['ok'=>false,'error'=>'internal_error','message'=>'خطای داخلی سامانه رخ داد.','tracking_id'=>'HOUSE-INTERNAL-1']];
        return ['transport_ok'=>true,'http_status'=>200,'payload'=>['ok'=>true,'status'=>'posted','transaction_id'=>'TX-1','external_order_id'=>$payload['external_order_id']??'','idempotent'=>($payload['external_order_id']??'')==='CAFE-S-99','tracking_id'=>'HOUSE-POSTED-1']];
    }
    if($action==='void'){
        $payload=json_decode($body,true);
        if(($payload['external_order_id']??'')==='CAFE-S-VOID-MISSING')return ['transport_ok'=>true,'http_status'=>200,'payload'=>['ok'=>true,'status'=>'voided','idempotent'=>false]];
        return ['transport_ok'=>true,'http_status'=>200,'payload'=>['ok'=>true,'status'=>'voided','transaction_id'=>'VOID-1','original_transaction_id'=>'TX-1','external_order_id'=>$payload['external_order_id']??'','idempotent'=>true]];
    }
    return ['transport_ok'=>true,'http_status'=>404,'payload'=>['ok'=>false,'error'=>'not_found']];
};

$result=accommodation_search_active('مهمان');
acc_check(($result['success']??false)===true,'Unified search must succeed.');
acc_check(count($result['reservations']??[])===1,'Unified search must return one reservation.');
$reservation=$result['reservations'][0]??[];
acc_check(($reservation['room_names']??[])===['سیف'],'room_names must remain an array.');
acc_check(($reservation['charge_allowed']??false)===true,'Charge permission must be preserved.');
acc_check(($reservation['check_in']??'')==='۱۴۰۵/۰۵/۱۳'&&($reservation['check_out']??'')==='۱۴۰۵/۰۵/۱۶','ISO reservation dates must be displayed as Jalali dates.');
acc_check(!array_key_exists('balance',$reservation)&&!array_key_exists('notes',$reservation),'Search must not expose internal accommodation data.');
$none=accommodation_search_active('NONE');
acc_check(($none['success']??false)===true&&($none['reservations']??[])===[],'Empty search result is not a connection error.');
$unauthorized=accommodation_search_active('AUTH');
acc_check(($unauthorized['success']??true)===false&&($unauthorized['code']??'')==='unauthorized','Unauthorized must be surfaced.');
$exact=accommodation_reservation_exact('SK-1405-00012');
acc_check(($exact['success']??false)===true&&($exact['reservation']['charge_allowed']??false)===true,'Exact reservation recheck must succeed.');
$blocked=accommodation_reservation_exact('BLOCKED');
acc_check(($blocked['success']??false)===true&&($blocked['reservation']['charge_allowed']??true)===false,'Non-chargeable reservation must remain visible with disabled permission.');

$invoice=['version'=>1,'number'=>'S-77','issued_at'=>'2026-08-05T18:40:00+03:30','table_name'=>'میز ۱۲','subtotal'=>850000,'discount'=>0,'total'=>850000,'currency'=>'TOMAN','unexpected'=>'legacy','items'=>[['name'=>'غذا','quantity'=>1,'unit_price'=>850000,'line_total'=>850000,'note'=>null,'legacy_key'=>'x']]];
$base=['reservation_code'=>'SK-1405-00012','amount'=>850000,'invoice_snapshot_json'=>json_encode($invoice,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'remote_transaction_id'=>'TX-1'];
$posted=accommodation_charge_remote($base+['external_order_id'=>'CAFE-S-77']);
acc_check(($posted['success']??false)===true&&($posted['transaction_id']??'')==='TX-1'&&($posted['tracking_id']??'')==='HOUSE-POSTED-1','Successful charge must return transaction ID and tracking ID.');
$idempotent=accommodation_charge_remote($base+['external_order_id'=>'CAFE-S-99']);
acc_check(($idempotent['success']??false)===true&&($idempotent['idempotent']??false)===true,'Idempotent charge is a confirmed success.');
$network=accommodation_charge_remote($base+['external_order_id'=>'CAFE-S-88']);
acc_check(($network['success']??true)===false&&($network['ambiguous']??false)===true,'Timeout must remain ambiguous.');
$conflict=accommodation_charge_remote($base+['external_order_id'=>'CAFE-S-CONFLICT']);
acc_check(($conflict['success']??true)===false&&($conflict['code']??'')==='external_order_conflict'&&($conflict['ambiguous']??true)===false,'Conflict must stop blind retry.');
acc_check(accommodation_transfer_retry_allowed(['status'=>'failed','last_error_code'=>'external_order_conflict'])===false,'Conflict must not be retryable.');
acc_check(accommodation_transfer_retry_allowed(['status'=>'failed','last_error_code'=>'reservation_not_chargeable'])===false,'Expired/non-chargeable reservation must not be retryable.');
acc_check(accommodation_public_error_message('reservation_not_chargeable','این رزرو امکان ثبت هزینه ندارد. بازه اقامت پایان یافته است.')==='این رزرو امکان ثبت هزینه ندارد. بازه اقامت پایان یافته است.','Non-chargeable public message must not duplicate its prefix.');
$schema=accommodation_charge_remote($base+['external_order_id'=>'CAFE-S-SCHEMA']);
acc_check(($schema['success']??true)===false&&($schema['code']??'')==='schema_not_ready'&&($schema['ambiguous']??true)===false&&($schema['outcome']??'')==='deterministic_failure','schema_not_ready must be deterministic even with HTTP 503.');
acc_check(($schema['tracking_id']??'')==='HOUSE-SCHEMA-1','House tracking_id must propagate through the integration result.');
$temp=accommodation_charge_remote($base+['external_order_id'=>'CAFE-S-TEMP']);
acc_check(($temp['success']??true)===false&&($temp['code']??'')==='temporary_failure'&&($temp['ambiguous']??true)===false&&($temp['retryable']??false)===true,'temporary_failure must be deterministic and retryable with the same external_order_id.');
acc_check(str_contains(accommodation_public_error_with_tracking('schema_not_ready','',($schema['tracking_id']??'')),'HOUSE-SCHEMA-1'),'Public deterministic errors must include the remote tracking ID.');
$internal=accommodation_charge_remote($base+['external_order_id'=>'CAFE-S-INTERNAL']);
acc_check(($internal['success']??true)===false&&($internal['code']??'')==='internal_error'&&($internal['ambiguous']??false)===true&&($internal['tracking_id']??'')==='HOUSE-INTERNAL-1','Remote internal_error remains ambiguous and must preserve its tracking ID.');
$callCountBeforeInvalid=count($calls);$invalidInvoice=$invoice;$invalidInvoice['items'][0]['line_total']=840000;
$invalid=accommodation_charge_remote(array_merge($base,['external_order_id'=>'CAFE-S-BAD','invoice_snapshot_json'=>json_encode($invalidInvoice,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]));
acc_check(($invalid['success']??true)===false&&($invalid['code']??'')==='invalid_request'&&($invalid['ambiguous']??true)===false,'Invalid local invoice arithmetic must be rejected before transport.');
acc_check(count($calls)===$callCountBeforeInvalid,'Invalid local snapshot must not reach the accommodation server.');
$void=accommodation_void_remote($base+['external_order_id'=>'CAFE-S-99'],'ابطال تست');
acc_check(($void['success']??false)===true&&($void['transaction_id']??'')==='VOID-1'&&($void['original_transaction_id']??'')==='TX-1','Void must preserve both transaction IDs.');
$voidMissing=accommodation_void_remote($base+['external_order_id'=>'CAFE-S-VOID-MISSING'],'ابطال تست');
acc_check(($voidMissing['success']??true)===false&&($voidMissing['ambiguous']??false)===true,'Void without transaction IDs remains unresolved.');
acc_check(accommodation_external_order_id(142)==='CAFE-S-142','External order ID must be stable.');
acc_check(accommodation_validate_base_url('https://stay.example.test/api.php')==='https://stay.example.test/api.php','HTTPS endpoint must be accepted.');
$httpRejected=false;try{accommodation_validate_base_url('http://stay.example.test/api.php');}catch(InvalidArgumentException){$httpRejected=true;}acc_check($httpRejected,'Non-local HTTP must be rejected.');

$searchCalls=array_values(array_filter($calls,fn($c)=>str_contains($c['url'],'action=search')));
acc_check(count($searchCalls)>=3,'Unified search endpoint must be called.');
foreach($searchCalls as $call){parse_str((string)parse_url($call['url'],PHP_URL_QUERY),$q);acc_check(array_key_exists('query',$q)&&!array_key_exists('type',$q),'Search must send query without legacy type.');}

$allHeaders=array_merge(...array_map(static fn($c):array=>$c['headers']??[],$calls));
acc_check((bool)array_filter($allHeaders,static fn($h):bool=>str_starts_with((string)$h,'X-Tracking-ID: CAFE-')),'Cafe must send its own correlation ID to House.');

$chargeCall=array_values(array_filter($calls,fn($c)=>str_contains($c['url'],'action=charge')))[0]??[];
$chargePayload=json_decode((string)($chargeCall['body']??''),true);
acc_check(($chargePayload['currency']??'')==='TOMAN'&&is_array($chargePayload['invoice']??null),'Charge must send TOMAN and structured invoice snapshot.');
acc_check(array_keys($chargePayload)===['external_order_id','reservation_code','amount','currency','invoice'],'Charge root must contain only the API 2.0 contract keys.');
acc_check(array_keys($chargePayload['invoice'])===['version','number','issued_at','table_name','subtotal','discount','total','items'],'Invoice must not contain nested currency or legacy keys.');
acc_check(array_keys($chargePayload['invoice']['items'][0])===['name','quantity','unit_price','line_total','note'],'Invoice items must be canonical and free of legacy keys.');
acc_check(!array_key_exists('sort_order',$chargePayload['invoice']['items'][0]),'Cafe must not send House-internal sort_order.');
acc_check(str_contains(implode("\n",$calls[0]['headers']),'Authorization: Bearer secret-test-key'),'Bearer header is required.');

$GLOBALS['SOKNA_ACCOMMODATION_CONFIG_OVERRIDE']['enabled']=false;
$disabled=accommodation_http_request('search','GET',['query'=>'x']);
acc_check(accommodation_error_code($disabled['payload'],$disabled['http_status'])==='connection_disabled','Disabled live connection must not call remote API.');

if($fail){fwrite(STDERR,"FAILED: ".count($fail)." of {$checks} accommodation API checks\n");foreach($fail as $item)fwrite(STDERR,"- {$item}\n");exit(1);}echo "OK: {$checks} accommodation API 2.0 checks passed.\n";
