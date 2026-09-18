<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/maintenance.php';
maintenance_guard_json();
if($_SERVER['REQUEST_METHOD']!=='POST')json_response(['success'=>false],405);
$data=request_json();
if(!csrf_valid($data['csrf_token']??null))json_response(['success'=>false,'message'=>customer_message('page_expired')],419);
$tableToken=trim((string)($data['table_token']??''));$deviceToken=trim((string)($data['device_token']??''));
if($tableToken==='')json_response(['success'=>false,'message'=>customer_message('invalid_qr')],422);
$stmt=db()->prepare('SELECT id,name,code FROM cafe_tables WHERE access_token=? AND active=1 LIMIT 1');$stmt->execute([$tableToken]);$table=$stmt->fetch();
if(!$table)json_response(['success'=>false,'message'=>customer_message('invalid_qr')],404);
$session=null;$lateJoin=false;$pendingSession=null;
if(table_sessions_enabled()){
    $session=active_table_session((int)$table['id']);
    if(!$session)$pendingSession=pending_table_session((int)$table['id']);
    if($session&&$deviceToken!==''){
        $knownStmt=db()->prepare('SELECT id FROM table_session_clients WHERE session_id=? AND device_token=? LIMIT 1');$knownStmt->execute([$session['id'],$deviceToken]);$known=(bool)$knownStmt->fetchColumn();
        $alertMinutes=max(5,(int)setting('new_device_alert_minutes','20'));
        $lateJoin=!$known&&(time()-strtotime($session['started_at'])>$alertMinutes*60);
        register_session_client((int)$session['id'],$deviceToken);
    }
}
$call=db()->prepare("SELECT public_code,status,created_at FROM waiter_calls WHERE table_id=? AND status IN('new','accepted') ORDER BY created_at DESC LIMIT 1");$call->execute([$table['id']]);
$activeCall=$call->fetch()?:null;
json_response([
    'success'=>true,
    'table'=>['id'=>(int)$table['id'],'name'=>$table['name'],'code'=>$table['code']],
    'session'=>$session?['token'=>$session['public_token'],'started_at'=>$session['started_at'],'status'=>$session['status']]:null,
    'pending_session'=>$pendingSession?['token'=>$pendingSession['public_token'],'started_at'=>$pendingSession['started_at'],'status'=>$pendingSession['status']]:null,
    'can_order'=>order_acceptance_states()['cafe'],
    'order_acceptance'=>order_acceptance_states(),
    'order_acceptance_messages'=>[
        'cafe'=>order_acceptance_message('cafe'),
        'kitchen'=>order_acceptance_message('kitchen'),
        'bar'=>order_acceptance_message('bar'),
    ],
    'station_states'=>station_busy_states(),'station_state_hash'=>station_state_hash(),
    'waiter_enabled'=>setting_bool('waiter_call_enabled',true),'active_call'=>$activeCall,'late_join'=>$lateJoin,
    'requires_operator_confirmation'=>table_sessions_enabled() && $session===null,
]);
