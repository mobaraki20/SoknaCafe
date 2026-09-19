<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/maintenance.php';
maintenance_guard_json();
require_any_capability(['orders_floor','preparation']);
if($_SERVER['REQUEST_METHOD']!=='POST')json_response(['success'=>false],405);
$data=request_json();if(!csrf_valid($data['csrf_token']??null))json_response(['success'=>false,'message'=>'صفحه منقضی شده؛ تازه‌سازی کنید.'],419);
$user=current_user();$userId=(int)$user['id'];$ordersAllowed=user_has_capability('orders_floor',$user);$preparationAccess=preparation_access_context($user);$preparationAllowed=(bool)$preparationAccess['can_mutate'];$actionableAreas=$preparationAccess['actionable_areas'];$action=(string)($data['action']??'');$callId=(int)($data['call_id']??0);$orderId=(int)($data['order_id']??0);$pdo=db();
try{$pdo->beginTransaction();
if(in_array($action,['accept_call','done_call'],true)){
 if(!$ordersAllowed||$callId<1)throw new RuntimeException('مسئولیت «سفارش و سالن» برای رسیدگی به فراخوان فعال نیست.');
 $stmt=$pdo->prepare('SELECT w.*,ts.status session_status FROM waiter_calls w LEFT JOIN table_sessions ts ON ts.id=w.session_id WHERE w.id=? FOR UPDATE');$stmt->execute([$callId]);$call=$stmt->fetch();if(!$call)throw new RuntimeException('فراخوان پیدا نشد.');
 if($call['session_id']&&($call['session_status']??'')!=='active'){$pdo->prepare("UPDATE waiter_calls SET status='cancelled',active_table_guard=NULL,cancelled_at=NOW(),cancel_reason='table_closed',cancelled_by_user_id=? WHERE id=? AND status IN('new','accepted')")->execute([$userId,$callId]);$pdo->commit();json_response(['success'=>false,'message'=>'میز بسته شده و فراخوان نیز بسته شد.'],409);}
 if($action==='accept_call'){if($call['status']==='new')$pdo->prepare("UPDATE waiter_calls SET status='accepted',accepted_by_user_id=?,accepted_at=NOW() WHERE id=? AND status='new'")->execute([$userId,$callId]);elseif($call['status']==='accepted'&&(int)$call['accepted_by_user_id']!==$userId)throw new RuntimeException('یکی از همکاران این فراخوان را پذیرفته است.');elseif(!in_array($call['status'],['new','accepted'],true))throw new RuntimeException('این فراخوان قبلاً بسته شده است.');audit_log_write('waiter_call.accepted','waiter_call',$callId,[],$userId);}else{if(!in_array($call['status'],['new','accepted'],true))throw new RuntimeException('این فراخوان قبلاً بسته شده است.');$pdo->prepare("UPDATE waiter_calls SET status='done',active_table_guard=NULL,accepted_by_user_id=COALESCE(accepted_by_user_id,?),accepted_at=COALESCE(accepted_at,NOW()),completed_at=NOW() WHERE id=?")->execute([$userId,$callId]);audit_log_write('waiter_call.completed','waiter_call',$callId,[],$userId);}
 $pdo->commit();json_response(['success'=>true,'message'=>'وضعیت فراخوان ثبت شد.']);
}
if($action==='claim_order_area'){
 if(!$preparationAllowed||$orderId<1)throw new RuntimeException('مسئولیت «آماده‌سازی» برای این کار فعال نیست.');
 $area=normalize_preparation_area((string)($data['area']??''));if(!in_array($area,$actionableAreas,true))throw new RuntimeException('این بخش آماده‌سازی برای حساب شما فعال نیست.');
 $orderStmt=$pdo->prepare('SELECT o.id,o.status,o.table_id,t.name table_name FROM orders o JOIN cafe_tables t ON t.id=o.table_id WHERE o.id=? FOR UPDATE');$orderStmt->execute([$orderId]);$order=$orderStmt->fetch();if(!$order)throw new RuntimeException('سفارش پیدا نشد.');if(!in_array((string)$order['status'],['accounted','completed'],true))throw new RuntimeException('فقط سفارش تأییدشده قابل دریافت است.');
 $itemStmt=$pdo->prepare('SELECT id,item_name,quantity,item_note,preparation_station FROM order_items WHERE order_id=? AND quantity>0 ORDER BY id FOR UPDATE');$itemStmt->execute([$orderId]);$items=array_values(array_filter($itemStmt->fetchAll(),static fn(array $x):bool=>preparation_station_requires_work((string)$x['preparation_station'])&&preparation_area_for_station((string)$x['preparation_station'])===$area));if(!$items)throw new RuntimeException('این سفارش آیتمی برای بخش انتخاب‌شده ندارد.');$signature=preparation_items_signature($items);
 $claimStmt=$pdo->prepare('SELECT c.*,u.display_name claimed_by FROM order_preparation_claims c LEFT JOIN users u ON u.id=c.claimed_by_user_id WHERE c.order_id=? AND c.area_key=? FOR UPDATE');$claimStmt->execute([$orderId,$area]);$claim=$claimStmt->fetch();
 if($claim&&hash_equals((string)$claim['item_signature'],$signature)){if((int)($claim['claimed_by_user_id']??0)===$userId){$pdo->commit();json_response(['success'=>true,'message'=>'دریافت این بخش قبلاً به نام شما ثبت شده است.']);}throw new RuntimeException('این بخش قبلاً توسط '.((string)($claim['claimed_by']??'یکی از همکاران')).' گرفته شده است.');}
 $pdo->prepare("INSERT INTO order_preparation_claims(order_id,area_key,claimed_at,claimed_by_user_id,item_signature) VALUES(?,?,NOW(),?,?) ON DUPLICATE KEY UPDATE claimed_at=NOW(),claimed_by_user_id=VALUES(claimed_by_user_id),item_signature=VALUES(item_signature)")->execute([$orderId,$area,$userId,$signature]);
 audit_log_write('preparation.claimed','order',$orderId,['area'=>$area,'table_id'=>(int)$order['table_id'],'item_signature'=>$signature],$userId);$pdo->commit();json_response(['success'=>true,'message'=>preparation_operational_areas()[$area].' سفارش '.$order['table_name'].' به نام شما ثبت شد.']);
}

if($action==='ack_adjustment'){
 $adjustmentId=(int)($data['adjustment_id']??0);if(!$preparationAllowed||$adjustmentId<1)throw new RuntimeException('مسئولیت عملیاتی آماده‌سازی برای این حساب فعال نیست.');
 $stmt=$pdo->prepare("SELECT * FROM preparation_adjustments WHERE id=? FOR UPDATE");$stmt->execute([$adjustmentId]);$adjustment=$stmt->fetch();if(!$adjustment)throw new RuntimeException('اصلاحیه پیدا نشد.');
 $area=normalize_preparation_area((string)$adjustment['area_key']);if(!in_array($area,$actionableAreas,true))throw new RuntimeException('این اصلاحیه مربوط به بخش شما نیست.');
 if((string)$adjustment['status']==='applied'){$pdo->commit();json_response(['success'=>true,'message'=>'این اصلاحیه قبلاً اعمال شده است.']);}
 $pdo->prepare("UPDATE preparation_adjustments SET status='applied',delivered_at=COALESCE(delivered_at,NOW()),acknowledged_at=COALESCE(acknowledged_at,NOW()),acknowledged_by_user_id=COALESCE(acknowledged_by_user_id,?),applied_at=NOW(),applied_by_user_id=? WHERE id=?")->execute([$userId,$userId,$adjustmentId]);
 audit_log_write('preparation.adjustment_applied','preparation_adjustment',$adjustmentId,['order_id'=>(int)$adjustment['order_id'],'area'=>$area,'previous_quantity'=>(int)$adjustment['previous_quantity'],'new_quantity'=>(int)$adjustment['new_quantity']],$userId);
 $pdo->commit();json_response(['success'=>true,'message'=>'اصلاحیه آماده‌سازی اعمال شد.']);
}
throw new RuntimeException('عملیات معتبر نیست.');
}catch(RuntimeException $e){if($pdo->inTransaction())$pdo->rollBack();json_response(['success'=>false,'message'=>$e->getMessage()],409);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('staff queue action: '.$e->getMessage());json_response(['success'=>false,'message'=>'عملیات انجام نشد؛ دوباره تلاش کنید.'],500);}
