<?php
declare(strict_types=1);
require dirname(__DIR__,4).'/bootstrap.php';
require dirname(__DIR__,4).'/guest/compat.php';

if($_SERVER['REQUEST_METHOD']!=='POST')public_json(['success'=>false],405);
$data=public_json_body();public_guest_csrf_or_fail($data);
$bundle=public_guest_require_bundle();$snapshot=$bundle['snapshot'];
$action=trim((string)($data['action']??'status'));
$isPublic=trim((string)($data['table_token']??''))===''&&(int)($data['public_table_id']??0)>0;
$table=public_guest_find_table($snapshot,$data,true);
if(!$table)public_json(['success'=>false,'code'=>'invalid_qr','message'=>$isPublic?'میز انتخاب‌شده فعال نیست.':customer_message('invalid_qr')],404);

$payload=[
    'table_token'=>(string)$table['token'],'public_context'=>$isPublic,
    'session_token'=>trim((string)($data['session_token']??'')),
    'device_token'=>trim((string)($data['device_token']??'')),
    'client_token'=>trim((string)($data['client_token']??'')),
    'call_code'=>trim((string)($data['call_code']??'')),
];
if($action==='create'){
    $state=public_guest_action_state($bundle);
    $allowed=$isPublic?!empty($bundle['availability']['waiter_enabled_public']):!empty($bundle['availability']['waiter_enabled_table']);
    if(!$state['enabled'])public_json(['success'=>false,'code'=>'local_unavailable','message'=>'ارتباط زنده با کافه موقتاً در دسترس نیست.'],503);
    if(!$allowed)public_json(['success'=>false,'code'=>'waiter_disabled','message'=>'فراخوان گارسون فعلاً فعال نیست.'],409);
    $client=$payload['client_token'];
    if(strlen($client)<16||strlen($client)>80)public_json(['success'=>false,'code'=>'invalid_request','message'=>'درخواست کامل نیست.'],422);
    $requestId=public_guest_request_id('waiter_call.create',['client_token'=>$client,'table_token'=>$payload['table_token']]);
    $relay=public_guest_relay_call('waiter_call.create',$payload,$requestId,false,45,12000);
    public_json($relay['result'],(int)$relay['http_status']);
}
if($action==='cancel'){
    $requestId=public_guest_request_id('waiter_call.cancel',['call_code'=>$payload['call_code'],'client_token'=>$payload['client_token'],'table_token'=>$payload['table_token']]);
    $relay=public_guest_relay_call('waiter_call.cancel',$payload,$requestId,false,30,8000);
    public_json($relay['result'],(int)$relay['http_status']);
}
if($action==='status'){
    $requestId=public_guest_request_id('waiter_call.status',['call_code'=>$payload['call_code'],'table_token'=>$payload['table_token']],false);
    $relay=public_guest_relay_call('waiter_call.status',$payload,$requestId,false,30,7000);
    public_json($relay['result'],(int)$relay['http_status']);
}
public_json(['success'=>false,'code'=>'invalid_action','message'=>'عملیات فراخوان معتبر نیست.'],422);
