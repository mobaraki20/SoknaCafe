<?php
declare(strict_types=1);

require dirname(__DIR__).'/bootstrap.php';
require_once dirname(__DIR__).'/includes/maintenance.php';
require_once dirname(__DIR__).'/includes/waiter_call_service.php';

maintenance_guard_json();
if($_SERVER['REQUEST_METHOD']!=='POST')json_response(['success'=>false],405);
$data=request_json();
if(!csrf_valid($data['csrf_token']??null))json_response(['success'=>false,'message'=>customer_message('page_expired')],419);

$action=trim((string)($data['action']??'status'));
$tableToken=trim((string)($data['table_token']??''));
$publicTableId=(int)($data['public_table_id']??0);
$isPublicRequest=$tableToken===''&&$publicTableId>0;

if($isPublicRequest){
    $stmt=db()->prepare('SELECT access_token FROM cafe_tables WHERE id=? AND active=1 LIMIT 1');
    $stmt->execute([$publicTableId]);$tableToken=(string)($stmt->fetchColumn()?:'');
}
if($tableToken==='')json_response(['success'=>false,'code'=>'invalid_qr','message'=>$isPublicRequest?'میز انتخاب‌شده فعال نیست.':customer_message('invalid_qr')],404);

$data['table_token']=$tableToken;
$data['public_context']=$isPublicRequest;

try{
    if($action==='create')json_response(waiter_call_create(db(),$data));
    if($action==='cancel')json_response(waiter_call_cancel(db(),$data));
    if($action==='status')json_response(waiter_call_status(db(),$data));
    json_response(['success'=>false,'message'=>'عملیات فراخوان معتبر نیست.'],422);
}catch(WaiterCallException $e){
    json_response(array_merge(['success'=>false,'code'=>$e->errorCode,'message'=>$e->getMessage()],$e->details),$e->httpStatus);
}catch(Throwable $e){
    error_log('waiter_call: '.$e->getMessage());
    json_response(['success'=>false,'message'=>customer_message('waiter_connection_error')],500);
}
