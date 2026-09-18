#!/usr/bin/env php
<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){fwrite(STDERR,"CLI only
");exit(64);}
require dirname(__DIR__).'/bootstrap.php';
require_once dirname(__DIR__).'/includes/relay_client.php';
require_once dirname(__DIR__).'/includes/remote_read_models.php';
$cfg=sokna_relay_config();
if(empty($cfg['enabled'])){fwrite(STDOUT,"Relay disabled.
");exit(0);}
try{
    $bind=sokna_relay_http('POST','/api/v1/local/bind.php',['display_name'=>(string)(config()['app']['name']??'SOKNA Cafe')]);
    if(empty($bind['ok']))throw new RuntimeException('Relay bind failed: '.(string)($bind['error']??'unknown'));
    $models=sokna_remote_read_models(db());
    $sync=sokna_relay_http('POST','/api/v1/local/read_model_sync.php',['models'=>$models]);
    if(empty($sync['ok']))throw new RuntimeException('Read-model sync failed: '.(string)($sync['error']??'unknown'));
    fwrite(STDOUT,json_encode(['synced'=>(int)($sync['synced']??0),'unchanged'=>(int)($sync['unchanged']??0)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL);
}catch(Throwable $e){
    if(function_exists('sokna_log_event'))sokna_log_event('warning','relay.read_model_sync_failed',['message'=>$e->getMessage()]);
    fwrite(STDERR,$e->getMessage().PHP_EOL);exit(2);
}
