<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/bootstrap.php';

$body=public_json_body();
$installationId=trim((string)($body['installation_id']??''));
$requestId=trim((string)($body['request_id']??''));
$kind=trim((string)($body['kind']??''));
$clientToken=trim((string)($body['client_token']??''));
$deviceToken=trim((string)($body['device_token']??''));
$tableToken=trim((string)($body['table_token']??''));
$tableRef=trim((string)($body['table_ref']??''));
$payload=is_array($body['payload']??null)?$body['payload']:[];

if($installationId===''||$requestId===''||!in_array($kind,['guest_order.submit','waiter_call.create'],true)){
    public_json(['ok'=>false,'error'=>'invalid_guest_request'],400);
}
if(strlen($clientToken)<16||strlen($clientToken)>80||strlen($deviceToken)<16||strlen($deviceToken)>80){
    public_json(['ok'=>false,'error'=>'invalid_guest_identity'],400);
}
$bundle=public_guest_bundle($installationId);
if(!$bundle||empty($bundle['snapshot'])) public_json(['ok'=>false,'error'=>'guest_menu_unpublished'],404);
$action=public_guest_action_state($bundle);
if(!$action['enabled']) public_json(['ok'=>false,'error'=>'local_unavailable','action_state'=>$action],503);

$table=$tableToken!==''?public_guest_table_from_token($bundle['snapshot'],$tableToken):public_guest_table_from_ref($bundle['snapshot'],$tableRef);
if(!$table) public_json(['ok'=>false,'error'=>'invalid_table'],404);

$availability=is_array($bundle['availability']??null)?$bundle['availability']:[];
if($kind==='guest_order.submit'){
    if(empty($availability['order_acceptance']['cafe'])) public_json(['ok'=>false,'error'=>'ordering_paused'],409);
    if($tableToken==='') public_json(['ok'=>false,'error'=>'table_qr_required'],409);
}else{
    $publicContext=$tableToken==='';
    $allowed=$publicContext?($availability['waiter_enabled_public']??false):($availability['waiter_enabled_table']??false);
    if(!$allowed) public_json(['ok'=>false,'error'=>'waiter_disabled'],409);
    $payload['public_context']=$publicContext;
}

$payload['table_token']=(string)$table['token'];
$payload['client_token']=$clientToken;
$payload['device_token']=$deviceToken;
$now=time();
$envelope=[
    'request_id'=>$requestId,
    'kind'=>$kind,
    'created_at'=>gmdate('c',$now),
    'expires_at'=>gmdate('c',$now+45),
    'actor_projection_id'=>'guest:'.substr(hash('sha256',$installationId.'|'.$deviceToken),0,32),
    'payload'=>$payload,
];
$result=public_guest_enqueue($installationId,$envelope);
$status=(int)($result['status']??200);
unset($result['status']);
public_json($result,$status);
