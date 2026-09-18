<?php
declare(strict_types=1);
require dirname(__DIR__,4).'/bootstrap.php';
require dirname(__DIR__,4).'/guest/compat.php';

if($_SERVER['REQUEST_METHOD']!=='POST')public_json(['success'=>false],405);
$data=public_json_body();public_guest_csrf_or_fail($data);
$bundle=public_guest_require_bundle();$snapshot=$bundle['snapshot'];
$table=public_guest_find_table($snapshot,$data,false);
if(!$table)public_json(['success'=>false,'code'=>'invalid_qr','message'=>customer_message('invalid_qr')],404);

$availability=is_array($bundle['availability']??null)?$bundle['availability']:[];
$action=public_guest_action_state($bundle);
$acceptance=is_array($availability['order_acceptance']??null)?$availability['order_acceptance']:['cafe'=>false,'kitchen'=>false,'bar'=>false];
if(!$action['enabled'])$acceptance=['cafe'=>false,'kitchen'=>false,'bar'=>false];
$messages=is_array($availability['order_acceptance_messages']??null)?$availability['order_acceptance_messages']:[
    'cafe'=>'سفارش‌گیری فعلاً در دسترس نیست.','kitchen'=>'آشپزخانه فعلاً در دسترس نیست.','bar'=>'بار فعلاً در دسترس نیست.',
];
if(!$action['enabled'])$messages['cafe']='ارتباط زنده با کافه موقتاً در دسترس نیست؛ منو همچنان قابل مشاهده است.';

public_json([
    'success'=>true,
    'table'=>['id'=>(int)($table['id']??0),'name'=>(string)$table['name'],'code'=>(string)$table['code']],
    'session'=>null,'pending_session'=>null,
    'can_order'=>$action['enabled']&&!empty($acceptance['cafe']),
    'order_acceptance'=>$acceptance,'order_acceptance_messages'=>$messages,
    'station_states'=>is_array($availability['station_states']??null)?$availability['station_states']:[],
    'station_state_hash'=>(string)($availability['station_state_hash']??''),
    'waiter_enabled'=>$action['enabled']&&!empty($availability['waiter_enabled_table']),
    'active_call'=>null,'late_join'=>false,
    'requires_operator_confirmation'=>!empty($snapshot['features']['table_sessions_enabled']),
]);
