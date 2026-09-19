<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/maintenance.php';
require_once dirname(__DIR__) . '/includes/table_draft.php';

require_any_capability(['orders_floor']);
maintenance_guard_json();

$user=current_user();
$pdo=db();

if($_SERVER['REQUEST_METHOD']==='GET'){
    $tableId=(int)($_GET['table_id']??0);
    try{
        json_response(table_draft_get($pdo,$tableId,$user));
    }catch(TableDraftException $e){
        json_response(array_merge(['success'=>false,'code'=>$e->errorCode,'message'=>$e->getMessage()],$e->details),$e->httpStatus);
    }catch(Throwable $e){
        error_log('table draft get: '.$e->getMessage());
        json_response(['success'=>false,'message'=>'دریافت پیش‌نویس انجام نشد.'],500);
    }
}

if($_SERVER['REQUEST_METHOD']!=='POST')json_response(['success'=>false,'message'=>'روش درخواست معتبر نیست.'],405);
$data=request_json();
if(!csrf_valid($data['csrf_token']??null))json_response(['success'=>false,'code'=>'page_expired','message'=>'صفحه منقضی شده؛ دوباره تلاش کنید.'],419);
$action=trim((string)($data['action']??'save'));

try{
    $pdo->beginTransaction();
    if($action==='save'){
        $result=table_draft_save_tx($pdo,$data,$user);
    }elseif($action==='cancel'){
        $result=table_draft_cancel_tx($pdo,$data,$user);
    }elseif($action==='finalize'){
        $result=table_draft_finalize_tx($pdo,$data,$user);
    }else{
        throw new TableDraftException('invalid_action','عملیات پیش‌نویس معتبر نیست.',422);
    }
    $pdo->commit();
    if($action==='finalize'){
        staff_order_after_commit($result);
        unset($result['_after_commit_order_id']);
    }
    json_response($result);
}catch(TableDraftException $e){
    if($pdo->inTransaction())$pdo->rollBack();
    json_response(array_merge(['success'=>false,'code'=>$e->errorCode,'message'=>$e->getMessage()],$e->details),$e->httpStatus);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    error_log('table draft '.$action.': '.$e->getMessage());
    json_response(['success'=>false,'message'=>'عملیات پیش‌نویس انجام نشد؛ دوباره تلاش کنید.'],500);
}
