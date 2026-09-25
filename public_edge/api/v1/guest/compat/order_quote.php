<?php
declare(strict_types=1);
require dirname(__DIR__,4).'/bootstrap.php';
require dirname(__DIR__,4).'/guest/compat.php';

if($_SERVER['REQUEST_METHOD']!=='POST')public_json(['success'=>false],405);
$data=public_json_body();public_guest_csrf_or_fail($data);
$bundle=public_guest_require_bundle();$snapshot=$bundle['snapshot'];
$table=public_guest_find_table($snapshot,$data,false);
if(!$table)public_json(['success'=>false,'code'=>'invalid_qr','message'=>customer_message('invalid_qr')],404);
$state=public_guest_action_state($bundle);
if(!$state['enabled']||!$state['local_fresh'])public_json(['success'=>false,'code'=>'local_unavailable','message'=>'پیش‌نمایش مالی سفارش فعلاً در دسترس نیست؛ چند لحظه بعد دوباره تلاش کنید.'],503);

$payload=[
    'quote_mode'=>(string)($data['quote_mode']??'create'),
    'table_token'=>(string)$table['token'],
    'session_token'=>trim((string)($data['session_token']??'')),
    'device_token'=>trim((string)($data['device_token']??'')),
    'client_token'=>trim((string)($data['client_token']??'')),
    'order_code'=>trim((string)($data['order_code']??'')),
    'expected_signature'=>trim((string)($data['expected_signature']??'')),
    'customer_note'=>(string)($data['customer_note']??''),
    'items'=>is_array($data['items']??null)?$data['items']:[],
];
$requestId=public_guest_request_id('guest_order.quote',[
    'mode'=>$payload['quote_mode'],'table_token'=>$payload['table_token'],'device_token'=>$payload['device_token'],
    'order_code'=>$payload['order_code'],'expected_signature'=>$payload['expected_signature'],'items'=>$payload['items'],
],false);
$relay=public_guest_relay_call('guest_order.quote',$payload,$requestId,false,30,8000);
public_json($relay['result'],(int)$relay['http_status']);
