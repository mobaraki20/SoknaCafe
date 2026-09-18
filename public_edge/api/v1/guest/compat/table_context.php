<?php
declare(strict_types=1);
require dirname(__DIR__,4).'/bootstrap.php';
require dirname(__DIR__,4).'/guest/compat.php';

if($_SERVER['REQUEST_METHOD']!=='POST')public_json(['success'=>false],405);
$data=public_json_body();public_guest_csrf_or_fail($data);
$bundle=public_guest_require_bundle();$snapshot=$bundle['snapshot'];
$table=public_guest_find_table($snapshot,$data,false);
if(!$table)public_json(['success'=>false,'code'=>'invalid_qr','message'=>customer_message('invalid_qr')],404);

$payload=[
    'table_token'=>(string)$table['token'],
    'device_token'=>trim((string)($data['device_token']??'')),
];
$state=public_guest_action_state($bundle);
if($state['local_fresh']){
    $requestId=public_guest_request_id('guest_table.context',[
        'table_token'=>$payload['table_token'],'device_token'=>$payload['device_token'],
    ],false);
    $relay=public_guest_relay_call('guest_table.context',$payload,$requestId,false,30,6500);
    if(!empty($relay['ok']))public_json($relay['result'],200);
}

$availability=is_array($bundle['availability']??null)?$bundle['availability']:[];
$acceptance=is_array($availability['order_acceptance']??null)?$availability['order_acceptance']:['cafe'=>false,'kitchen'=>false,'bar'=>false];
$messages=is_array($availability['order_acceptance_messages']??null)?$availability['order_acceptance_messages']:[
    'cafe'=>'سفارش‌گیری فعلاً در دسترس نیست.','kitchen'=>'آشپزخانه فعلاً در دسترس نیست.','bar'=>'بار فعلاً در دسترس نیست.',
];
if(!$state['enabled']){
    $acceptance=['cafe'=>false,'kitchen'=>false,'bar'=>false];
    $messages['cafe']='ارتباط زنده با کافه موقتاً در دسترس نیست؛ منو همچنان قابل مشاهده است.';
}
$tableStates=is_array($availability['tables']??null)?$availability['tables']:[];
$tableState=is_array($tableStates[(string)(int)($table['id']??0)]??null)?$tableStates[(string)(int)($table['id']??0)]:[];
$session=is_array($tableState['session']??null)?$tableState['session']:null;
$pending=is_array($tableState['pending_session']??null)?$tableState['pending_session']:null;
$activeCall=is_array($tableState['active_call']??null)?$tableState['active_call']:null;

public_json([
    'success'=>true,
    'table'=>['id'=>(int)($table['id']??0),'name'=>(string)$table['name'],'code'=>(string)$table['code']],
    'session'=>$state['enabled']&&$session?[
        'token'=>(string)($session['token']??''),'started_at'=>(string)($session['started_at']??''),'status'=>(string)($session['status']??'active'),
    ]:null,
    'pending_session'=>$state['enabled']&&$pending?[
        'token'=>(string)($pending['token']??''),'started_at'=>(string)($pending['started_at']??''),'status'=>(string)($pending['status']??'pending'),
    ]:null,
    'can_order'=>$state['enabled']&&!empty($acceptance['cafe']),
    'order_acceptance'=>$acceptance,'order_acceptance_messages'=>$messages,
    'station_states'=>is_array($availability['station_states']??null)?$availability['station_states']:[],
    'station_state_hash'=>(string)($availability['station_state_hash']??''),
    'waiter_enabled'=>$state['enabled']&&!empty($availability['waiter_enabled_table']),
    'active_call'=>$state['enabled']?$activeCall:null,'late_join'=>false,
    'requires_operator_confirmation'=>!empty($snapshot['features']['table_sessions_enabled'])&&$session===null,
    'degraded'=>true,
]);
