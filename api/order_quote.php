<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
require_once dirname(__DIR__).'/includes/maintenance.php';
require_once dirname(__DIR__).'/includes/guest_order_quote_service.php';

maintenance_guard_json();
if($_SERVER['REQUEST_METHOD']!=='POST')json_response(['success'=>false],405);
$data=request_json();
if(!csrf_valid($data['csrf_token']??null))json_response(['success'=>false,'code'=>'page_expired','message'=>customer_message('page_expired')],419);
try{
    json_response(guest_order_quote(db(),$data));
}catch(GuestOrderEditException $e){
    json_response(array_merge(['success'=>false,'code'=>$e->errorCode,'message'=>$e->getMessage()],$e->details),$e->httpStatus);
}catch(GuestOrderException $e){
    json_response(array_merge(['success'=>false,'code'=>$e->errorCode,'message'=>$e->getMessage()],$e->details),$e->httpStatus);
}catch(Throwable $e){
    error_log('Cafe order_quote error: '.$e->getMessage());
    json_response(['success'=>false,'code'=>'quote_failed','message'=>'پیش‌نمایش مالی سفارش آماده نشد؛ دوباره تلاش کنید.'],500);
}
