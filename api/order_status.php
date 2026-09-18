<?php
declare(strict_types=1);

require dirname(__DIR__).'/bootstrap.php';
require_once dirname(__DIR__).'/includes/guest_order_status_service.php';

if($_SERVER['REQUEST_METHOD']!=='POST')json_response(['success'=>false],405);
$data=request_json();
if(!csrf_valid($data['csrf_token']??null)){
    json_response(['success'=>false,'message'=>customer_message('page_expired')],419);
}
try{
    json_response(guest_order_status_lookup(db(),$data));
}catch(GuestOrderStatusException $e){
    json_response(['success'=>false,'code'=>$e->errorCode,'message'=>$e->getMessage()],$e->httpStatus);
}catch(Throwable $e){
    error_log('order_status: '.$e->getMessage());
    json_response(['success'=>false,'message'=>customer_message('order_failed')],500);
}
