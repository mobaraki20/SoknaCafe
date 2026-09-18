<?php
declare(strict_types=1);
require dirname(__DIR__,4).'/bootstrap.php';
require dirname(__DIR__,4).'/guest/compat.php';

if($_SERVER['REQUEST_METHOD']!=='POST')public_json(['success'=>false],405);
$data=public_json_body();public_guest_csrf_or_fail($data);
$payload=[
    'order_code'=>trim((string)($data['order_code']??'')),
    'client_token'=>trim((string)($data['client_token']??'')),
];
$requestId=public_guest_request_id('guest_order.status',$payload,false);
$relay=public_guest_relay_call('guest_order.status',$payload,$requestId,false,30,7000);
public_json($relay['result'],(int)$relay['http_status']);
