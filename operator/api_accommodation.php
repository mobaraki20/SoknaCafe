<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/maintenance.php';
maintenance_guard_json();
require_capability('cashier_accounts');
if($_SERVER['REQUEST_METHOD']!=='POST')json_response(['success'=>false],405);
$data=request_json();
if(!csrf_valid($data['csrf_token']??null))json_response(['success'=>false,'message'=>'صفحه منقضی شده؛ دوباره بارگذاری کن.'],419);
$action=(string)($data['action']??'');
$user=current_user();
$userId=(int)$user['id'];
$requestId=text_substr(preg_replace('/[^A-Za-z0-9._:-]/','',trim((string)($data['request_id']??'')))?:bin2hex(random_bytes(8)),0,64);
$liveEnabled=accommodation_live_operations_enabled();
$liveRequired=static function()use($liveEnabled,$requestId): void{
    if(!$liveEnabled)json_response(['success'=>false,'message'=>'ارتباط زنده اقامتگاه خاموش است. سوابق و بازیابی محلی در دسترس‌اند، اما تماس تازه با اقامتگاه انجام نمی‌شود.','code'=>'connection_disabled','request_id'=>$requestId],409);
};
try{
    if($action==='search'){
        $liveRequired();
        $query=text_substr(trim((string)($data['query']??'')),0,160);
        $result=accommodation_search_active($query);
        accommodation_audit('accommodation_reservation_search','accommodation_connection',null,['query_length'=>text_length($query),'result_count'=>count($result['reservations']??[])],$userId);
        if(!$result['success'])json_response(['success'=>false,'message'=>accommodation_public_error_message((string)($result['code']??''),(string)($result['message']??'')),'code'=>$result['code']??'remote_error','request_id'=>$requestId],422);
        json_response(['success'=>true,'reservations'=>$result['reservations'],'request_id'=>$requestId]);
    }
    if($action==='charge'){
        $liveRequired();
        $tableId=(int)($data['table_id']??0);
        $code=text_substr(trim((string)($data['reservation_code']??($data['reservation']['reservation_code']??''))),0,80);
        $printFinal=bool_from_mixed($data['print_final']??false);
        $expectedSessionId=(int)($data['expected_session_id']??0);
        $expectedTotal=(int)($data['expected_total']??-1);
        $expectedSignature=strtolower(trim((string)($data['expected_signature']??'')));
        if($tableId<1||$code==='')throw new RuntimeException('میز یا رزرو معتبر نیست.');
        $sessionStmt=db()->prepare("SELECT id FROM table_sessions WHERE table_id=? AND status='active' ORDER BY id DESC LIMIT 1");
        $sessionStmt->execute([$tableId]);
        $activeSessionId=(int)($sessionStmt->fetchColumn()?:0);
        if($activeSessionId<1)throw new RuntimeException('این میز حساب فعال ندارد.');
        $pendingAdjustmentIds=preparation_adjustments_pending_ids(db(),$activeSessionId,false);
        $verified=accommodation_reservation_exact($code);
        if(!$verified['success'])json_response(['success'=>false,'message'=>accommodation_public_error_message((string)($verified['code']??''),(string)($verified['message']??'')),'code'=>$verified['code']??'remote_error','request_id'=>$requestId],422);
        $reservation=$verified['reservation'];
        if(!($reservation['charge_allowed']??false))json_response(['success'=>false,'message'=>(string)($reservation['charge_block_reason']?:'این رزرو امکان ثبت هزینه ندارد.'),'code'=>'reservation_not_chargeable','request_id'=>$requestId],422);
        $transfer=accommodation_prepare_transfer_for_table($tableId,$userId,$reservation,$printFinal,$expectedSessionId,$expectedTotal,$expectedSignature);
        $result=accommodation_attempt_charge((int)$transfer['id'],$userId,$printFinal);
        if(!$result['success'])json_response(['success'=>false,'message'=>$result['message'],'code'=>$result['code']??'remote_error','ambiguous'=>$result['ambiguous']??false,'tracking_id'=>$result['tracking_id']??'','outcome'=>$result['outcome']??'','transfer'=>$result['transfer']??null,'request_id'=>$requestId],($result['ambiguous']??false)?409:422);
        if($pendingAdjustmentIds)preparation_adjustments_audit_settlement_notice($activeSessionId,$pendingAdjustmentIds,$userId,'accommodation');
        json_response(['success'=>true,'persisted'=>true,'message'=>$result['message'],'tracking_id'=>$result['tracking_id']??'','transfer'=>$result['transfer'],'checkout'=>$result['checkout']??null,'request_id'=>$requestId,'table_id'=>$tableId,'pending_preparation_adjustments'=>count($pendingAdjustmentIds)]);
    }
    if($action==='retry'){
        $id=(int)($data['transfer_id']??0);
        if($id<1)throw new RuntimeException('انتقال معتبر نیست.');
        $transfer=accommodation_transfer_by_id($id);
        if(!$transfer)throw new RuntimeException('انتقال پیدا نشد.');
        if((string)$transfer['status']!=='posted')$liveRequired();
        $result=accommodation_attempt_charge($id,$userId,null);
        if(!$result['success'])json_response(['success'=>false,'message'=>$result['message'],'code'=>$result['code']??'remote_error','ambiguous'=>$result['ambiguous']??false,'tracking_id'=>$result['tracking_id']??'','outcome'=>$result['outcome']??'','request_id'=>$requestId],409);
        json_response(['success'=>true,'persisted'=>true,'message'=>$result['message'],'tracking_id'=>$result['tracking_id']??'','transfer'=>$result['transfer'],'checkout'=>$result['checkout']??null,'request_id'=>$requestId]);
    }
    if($action==='finalize_local'){
        $id=(int)($data['transfer_id']??0);
        if($id<1)throw new RuntimeException('انتقال معتبر نیست.');
        $checkout=accommodation_finalize_local_checkout($id,$userId,bool_from_mixed($data['print_final']??false));
        json_response(['success'=>true,'persisted'=>true,'message'=>'تسویه محلی کافه تکمیل شد.','transfer'=>accommodation_transfer_by_id($id),'checkout'=>$checkout,'request_id'=>$requestId]);
    }
    if($action==='detach_followup'){
        if(!is_admin()&&!user_has_capability('shift_supervision'))json_response(['success'=>false,'message'=>'فقط مدیر یا مسئول شیفت می‌تواند میز را با حفظ حساب در پیگیری آزاد کند.','request_id'=>$requestId],403);
        $id=(int)($data['transfer_id']??0);
        if($id<1)throw new RuntimeException('انتقال معتبر نیست.');
        $result=accommodation_detach_to_followup($id,$userId);
        json_response(['success'=>true,'persisted'=>true,'message'=>'میز آزاد شد و حساب اقامتگاه برای پیگیری محفوظ ماند.','transfer'=>$result['transfer']??null,'request_id'=>$requestId]);
    }
    if($action==='void'){
        if(!is_admin())json_response(['success'=>false,'message'=>'فقط مدیر سامانه می‌تواند هزینه اقامتگاه را برگشت دهد.'],403);
        $id=(int)($data['transfer_id']??0);
        $reason=text_substr(trim((string)($data['reason']??'')),0,300);
        if($id<1)throw new RuntimeException('انتقال معتبر نیست.');
        $transfer=accommodation_transfer_by_id($id);
        if(!$transfer)throw new RuntimeException('انتقال پیدا نشد.');
        if((string)$transfer['status']!=='voided')$liveRequired();
        $result=accommodation_attempt_void($id,$userId,$reason);
        if(!$result['success'])json_response(['success'=>false,'message'=>$result['message'],'code'=>$result['code']??'remote_error','ambiguous'=>$result['ambiguous']??false,'tracking_id'=>$result['tracking_id']??'','outcome'=>$result['outcome']??'','request_id'=>$requestId],409);
        json_response(['success'=>true,'persisted'=>true,'message'=>$result['message'],'tracking_id'=>$result['tracking_id']??'','transfer'=>$result['transfer'],'request_id'=>$requestId]);
    }
    if($action==='finalize_local_reversal'){
        if(!is_admin())json_response(['success'=>false,'message'=>'تکمیل سند برگشتی فقط برای مدیر سامانه مجاز است.','request_id'=>$requestId],403);
        $id=(int)($data['transfer_id']??0);
        $targetTableId=(int)($data['target_table_id']??0);
        $reason=text_substr(trim((string)($data['reason']??'')),0,300);
        if($id<1)throw new RuntimeException('انتقال معتبر نیست.');
        $result=accommodation_finalize_local_reversal($id,$userId,$targetTableId,$reason);
        json_response(['success'=>true,'persisted'=>true,'message'=>$result['message']??'سند برگشتی کافه تکمیل شد.','transfer'=>accommodation_transfer_by_id($id),'result'=>$result,'request_id'=>$requestId]);
    }
    if($action==='issues')json_response(['success'=>true,'items'=>accommodation_attention_rows(50),'live_enabled'=>$liveEnabled,'can_manage'=>is_admin()||user_has_capability('shift_supervision'),'request_id'=>$requestId]);
    throw new RuntimeException('عملیات اتصال اقامتگاه معتبر نیست.');
}catch(SettlementStateConflict $e){
    json_response(['success'=>false,'code'=>'settlement_changed','message'=>$e->getMessage(),'request_id'=>$requestId],409);
}catch(RuntimeException $e){
    json_response(['success'=>false,'message'=>$e->getMessage(),'request_id'=>$requestId],$e->getCode()===409?409:422);
}catch(Throwable $e){
    error_log('accommodation api ['.$requestId.']: '.$e->getMessage());
    json_response(['success'=>false,'message'=>'عملیات اقامتگاه انجام نشد. کد پیگیری: '.$requestId,'code'=>'local_internal_error','request_id'=>$requestId],500);
}
