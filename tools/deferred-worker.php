#!/usr/bin/env php
<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){fwrite(STDERR,"CLI only\n");exit(64);}
require dirname(__DIR__).'/bootstrap.php';

$cfg=sokna_relay_config();
if(empty($cfg['enabled'])){fwrite(STDOUT,"Relay disabled.\n");exit(0);}

try{
    sokna_deferred_reconcile_public(20);
    $claim=sokna_relay_http('POST','/api/v1/local/deferred/claim.php',['lease_seconds'=>45]);
    if(($claim['_http_status']??0)===404&&($claim['error']??'')==='empty_queue'){fwrite(STDOUT,"Deferred queue empty.\n");exit(0);}
    if(empty($claim['ok']))throw new RuntimeException('Deferred claim failed: '.(string)($claim['error']??'unknown'));
    $request=is_array($claim['request']??null)?$claim['request']:[];
    $lease=(string)($claim['lease_token']??'');
    $result=sokna_deferred_dispatch($request);
    $ack=sokna_relay_http('POST','/api/v1/local/deferred/ack.php',[
        'request_id'=>(string)($request['request_id']??''),'lease_token'=>$lease,
        'state'=>(string)$result['state'],'result'=>is_array($result['result']??null)?$result['result']:[],
        'error_code'=>(string)($result['error_code']??''),
    ]);
    if(empty($ack['ok']))throw new RuntimeException('Deferred ACK failed: '.(string)($ack['error']??'unknown'));
    fwrite(STDOUT,json_encode(['request_id'=>$request['request_id']??'','state'=>$result['state']??''],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL);
}catch(Throwable $e){
    if(function_exists('sokna_log_event'))sokna_log_event('warning','deferred.worker_failed',['message'=>$e->getMessage()]);
    fwrite(STDERR,$e->getMessage().PHP_EOL);exit(2);
}
