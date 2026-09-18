<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
require_once dirname(__DIR__).'/includes/push.php';
if($_SERVER['REQUEST_METHOD']!=='POST')json_response(['success'=>false,'message'=>'روش درخواست معتبر نیست.'],405);
$data=request_json();$token=trim((string)($data['token']??''));
try{
    $claims=push_action_token_decode($token);$userId=(int)($claims['uid']??0);$action=(string)($claims['action']??'');$subjectId=(int)($claims['sid']??0);
    if(!in_array($action,['accept_call','approve_order'],true)||$userId<1||$subjectId<1)throw new RuntimeException('این اقدام اعلان معتبر نیست.');
    $userStmt=db()->prepare('SELECT id,role,active FROM users WHERE id=? LIMIT 1');$userStmt->execute([$userId]);$user=$userStmt->fetch();
    if(!$user||(int)$user['active']!==1||!in_array('orders_floor',user_capabilities($userId),true))json_response(['success'=>false,'message'=>'دسترسی این کاربر تغییر کرده است.'],403);

    $pdo=db();
    if($action==='accept_call'){
        $callId=$subjectId;$pdo->beginTransaction();
        if(!push_action_claim_once($claims)){$pdo->rollBack();json_response(['success'=>false,'message'=>'این اقدام اعلان قبلاً استفاده شده یا دیگر معتبر نیست.'],409);}
        $stmt=$pdo->prepare('SELECT w.*,ts.status session_status FROM waiter_calls w LEFT JOIN table_sessions ts ON ts.id=w.session_id WHERE w.id=? FOR UPDATE');$stmt->execute([$callId]);$call=$stmt->fetch();
        if(!$call){$pdo->rollBack();json_response(['success'=>false,'message'=>'فراخوان پیدا نشد.'],404);}
        if($call['session_id']&&($call['session_status']??'')!=='active'){$pdo->prepare("UPDATE waiter_calls SET status='cancelled',active_table_guard=NULL,cancelled_at=NOW(),cancel_reason='table_closed' WHERE id=? AND status IN('new','accepted')")->execute([$callId]);$pdo->commit();json_response(['success'=>false,'message'=>'این میز بسته شده است.'],409);}
        if($call['status']==='done'){$pdo->commit();json_response(['success'=>true,'persisted'=>true,'status'=>'done','message'=>'این فراخوان قبلاً بسته شده است.','idempotent'=>true,'notification_title'=>'فراخوان بسته شده']);}
        if($call['status']==='accepted'&&(int)$call['accepted_by_user_id']!==$userId){$pdo->commit();json_response(['success'=>false,'message'=>'این فراخوان توسط همکار دیگری در حال رسیدگی است.'],409);}
        if(!in_array($call['status'],['new','accepted'],true)){$pdo->commit();json_response(['success'=>false,'message'=>'این فراخوان قبلاً بسته شده است.'],409);}
        $pdo->prepare("UPDATE waiter_calls SET status='done',active_table_guard=NULL,accepted_by_user_id=COALESCE(accepted_by_user_id,?),accepted_at=COALESCE(accepted_at,NOW()),completed_at=NOW() WHERE id=? AND status IN('new','accepted')")->execute([$userId,$callId]);
        audit_log_write('waiter_call.completed','waiter_call',$callId,['source'=>'notification_action'],$userId);
        $pdo->commit();json_response(['success'=>true,'persisted'=>true,'status'=>'done','message'=>'رسیدگی به فراخوان ثبت و فراخوان بسته شد.','notification_title'=>'فراخوان بسته شد']);
    }

    $orderId=$subjectId;$requestId='push-action-'.bin2hex(random_bytes(8));$pdo->beginTransaction();
    if(!push_action_claim_once($claims)){$pdo->rollBack();json_response(['success'=>false,'message'=>'این اقدام اعلان قبلاً استفاده شده یا دیگر معتبر نیست.'],409);}
    $locked=lock_order_context($pdo,$orderId);$row=$locked['order'];$oldStatus=(string)$row['status'];$sessionId=(int)$locked['session_id'];$reviewUrl=push_pending_order_review_url($orderId,(int)$locked['table_id']);
    if($oldStatus==='accounted'){$pdo->commit();json_response(['success'=>true,'message'=>'این سفارش قبلاً تأیید شده است.','idempotent'=>true,'notification_title'=>'سفارش تأیید شده']);}
    if(!guest_order_status_is_mutable($oldStatus)){$pdo->commit();json_response(['success'=>false,'message'=>'این سفارش دیگر منتظر تأیید نیست.'],409);}
    if(!push_pending_order_quick_approvable($orderId)){$pdo->commit();json_response(['success'=>false,'message'=>'این سفارش برای تأیید نیاز به بررسی داخل برنامه دارد.','review_url'=>$reviewUrl],409);}
    if($sessionId>0&&accommodation_transfer_blocks_invoice_edit($sessionId)){$pdo->commit();json_response(['success'=>false,'message'=>'این سفارش به یک عملیات مالی در حال پیگیری متصل است؛ داخل برنامه بررسی کنید.','review_url'=>$reviewUrl],409);}
    $dispatch=confirm_order_locked($pdo,['id'=>$orderId,'session_id'=>$sessionId,'status'=>$oldStatus],$userId);
    audit_log_write('order.status_changed','order',$orderId,['from_status'=>$oldStatus,'to_status'=>'accounted','request_id'=>$requestId,'session_id'=>$sessionId,'table_id'=>(int)$locked['table_id'],'source'=>'notification_action'],$userId);
    if($dispatch){$tableName=(string)($locked['table']['name']??'میز');order_side_effect_best_effort_tx($pdo,'push order confirmation',static fn()=>push_enqueue_confirmed_order_tx($pdo,$orderId,$tableName,$requestId));}
    $pdo->commit();if($dispatch)inventory_register_after_response_order($orderId);
    json_response(['success'=>true,'persisted'=>true,'status'=>'accounted','message'=>'سفارش تأیید و برای آماده‌سازی ثبت شد.','review_url'=>$reviewUrl,'silent_success'=>true]);
}catch(Throwable $e){if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();error_log('push action failed: '.get_class($e));json_response(['success'=>false,'message'=>safe_business_error_message($e,'انجام اقدام اعلان ممکن نشد؛ سامانه را باز کنید.')],422);}
