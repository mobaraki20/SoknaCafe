<?php
declare(strict_types=1);
require dirname(__DIR__,4).'/bootstrap.php';
require dirname(__DIR__,4).'/guest/compat.php';

if($_SERVER['REQUEST_METHOD']!=='POST')public_json(['success'=>false],405);
$data=public_json_body();public_guest_csrf_or_fail($data);
$bundle=public_guest_require_bundle();$snapshot=$bundle['snapshot'];
$table=public_guest_find_table($snapshot,$data,false);
if(!$table)public_json(['success'=>false,'code'=>'invalid_qr','message'=>customer_message('invalid_qr')],404);

$action=trim((string)($data['action']??'list'));
$base=[
    'table_token'=>(string)$table['token'],
    'session_token'=>trim((string)($data['session_token']??'')),
    'device_token'=>trim((string)($data['device_token']??'')),
];
if($base['device_token']===''||strlen($base['device_token'])>80)public_json(['success'=>false,'code'=>'invalid_context','message'=>'اطلاعات میز یا دستگاه معتبر نیست.'],422);

if($action==='list'){
    $requestId=public_guest_request_id('guest_order.list',['table_token'=>$base['table_token'],'device_token'=>$base['device_token']],false);
    $relay=public_guest_relay_call('guest_order.list',$base+['action'=>'list'],$requestId,false,30,7000);
    public_json($relay['result'],(int)$relay['http_status']);
}
if(!in_array($action,['update','cancel'],true))public_json(['success'=>false,'code'=>'invalid_action','message'=>'عملیات سفارش معتبر نیست.'],422);

$payload=$base+[
    'action'=>$action,
    'order_code'=>trim((string)($data['order_code']??'')),
    'expected_signature'=>trim((string)($data['expected_signature']??'')),
    'customer_note'=>(string)($data['customer_note']??''),
    'items'=>is_array($data['items']??null)?$data['items']:[],
];
$kind=$action==='update'?'order.edit':'order.cancel';
$identity=[
    'order_code'=>$payload['order_code'],'device_token'=>$payload['device_token'],
    'expected_signature'=>$payload['expected_signature'],'customer_note'=>$payload['customer_note'],'items'=>$payload['items'],
];
$requestId=public_guest_request_id($kind,$identity,true);
$relay=public_guest_relay_call($kind,$payload,$requestId,false,45,12000);
public_json($relay['result'],(int)$relay['http_status']);
