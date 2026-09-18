<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
require_once dirname(__DIR__).'/includes/maintenance.php';
require_once dirname(__DIR__).'/includes/guest_table_context_service.php';

maintenance_guard_json();
if($_SERVER['REQUEST_METHOD']!=='POST')json_response(['success'=>false],405);
$data=request_json();
if(!csrf_valid($data['csrf_token']??null))json_response(['success'=>false,'message'=>customer_message('page_expired')],419);
try{
    json_response(guest_table_context(db(),$data));
}catch(GuestTableContextException $e){
    json_response(['success'=>false,'code'=>$e->errorCode,'message'=>$e->getMessage()],$e->httpStatus);
}catch(Throwable $e){
    error_log('table_context: '.$e->getMessage());
    json_response(['success'=>false,'message'=>customer_message('order_failed')],500);
}
