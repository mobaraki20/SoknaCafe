<?php
declare(strict_types=1);

require dirname(__DIR__).'/bootstrap.php';
require_once dirname(__DIR__).'/includes/maintenance.php';
require_once dirname(__DIR__).'/includes/guest_order_manage_service.php';

maintenance_guard_json();
if($_SERVER['REQUEST_METHOD']!=='POST')json_response(['success'=>false],405);
$data=request_json();
if(!csrf_valid($data['csrf_token']??null)){
    json_response(['success'=>false,'code'=>'page_expired','message'=>customer_message('page_expired')],419);
}
$action=trim((string)($data['action']??'list'));
$data['action']=$action;
$pdo=db();
try{
    if($action==='list')json_response(guest_order_manage_list($pdo,$data));
    if(!in_array($action,['update','cancel'],true))json_response(['success'=>false,'message'=>'عملیات سفارش معتبر نیست.'],422);
    json_response(guest_order_manage_mutate($pdo,$data));
}catch(GuestOrderEditException $e){
    json_response(array_merge(['success'=>false,'code'=>$e->errorCode,'message'=>$e->getMessage()],$e->details),$e->httpStatus);
}catch(RuntimeException $e){
    $code=$e->getCode();$status=is_int($code)&&$code>=400&&$code<=599?$code:409;
    json_response(['success'=>false,'message'=>$e->getMessage()],$status);
}catch(Throwable $e){
    error_log('guest_orders: '.$e->getMessage());
    json_response(['success'=>false,'message'=>customer_message('order_failed')],500);
}
