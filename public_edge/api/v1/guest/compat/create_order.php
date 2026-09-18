<?php
declare(strict_types=1);
require dirname(__DIR__,4).'/bootstrap.php';
require dirname(__DIR__,4).'/guest/compat.php';

if($_SERVER['REQUEST_METHOD']!=='POST')public_json(['success'=>false],405);
$data=public_json_body();public_guest_csrf_or_fail($data);
$bundle=public_guest_require_bundle();$snapshot=$bundle['snapshot'];
$table=public_guest_find_table($snapshot,$data,false);
if(!$table)public_json(['success'=>false,'code'=>'invalid_qr','message'=>customer_message('invalid_qr')],404);
$action=public_guest_action_state($bundle);
if(!$action['enabled'])public_json(['success'=>false,'code'=>'local_unavailable','message'=>'ارتباط زنده با کافه موقتاً در دسترس نیست.'],503);

$client=trim((string)($data['client_token']??''));
$device=trim((string)($data['device_token']??''));
if(strlen($client)<16||strlen($client)>80||strlen($device)>80)public_json(['success'=>false,'code'=>'invalid_order','message'=>'اطلاعات سفارش معتبر نیست.'],422);
$payload=[
    'table_token'=>(string)$table['token'],
    'session_token'=>trim((string)($data['session_token']??'')),
    'device_token'=>$device,'client_token'=>$client,
    'customer_note'=>(string)($data['customer_note']??''),
    'items'=>is_array($data['items']??null)?$data['items']:[],
];
$requestId=public_guest_request_id('guest_order.submit',['client_token'=>$client,'table_token'=>(string)$table['token']]);
$relay=public_guest_relay_call('guest_order.submit',$payload,$requestId,true,45,12000);
public_json($relay['result'],(int)$relay['http_status']);
