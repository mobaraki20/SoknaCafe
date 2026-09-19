<?php
declare(strict_types=1);

/**
 * Phase 7 transport-only owner for the Accommodation integration.
 *
 * Business classification, settlement/recovery and persistence remain in
 * includes/accommodation.php. This file owns HTTPS mechanics only.
 */
function accommodation_transport_request(string $action,string $method='GET',array $parameters=[],int $timeoutSeconds=7): array{
 if(!accommodation_live_operations_enabled())return ['transport_ok'=>true,'http_status'=>409,'payload'=>['ok'=>false,'error'=>'connection_disabled','message'=>'ارتباط زنده اقامتگاه خاموش است.'],'raw'=>'','tracking_id'=>''];
 $key=accommodation_api_key();if($key==='')return ['transport_ok'=>true,'http_status'=>401,'payload'=>['ok'=>false,'error'=>'unauthorized','message'=>'کلید API تنظیم نشده است.'],'raw'=>'','tracking_id'=>''];
 try{$url=accommodation_api_url($action);}catch(Throwable $e){return ['transport_ok'=>false,'http_status'=>0,'payload'=>[],'error'=>$e->getMessage(),'raw'=>'','tracking_id'=>''];}
 $method=strtoupper($method);$body='';if($method==='GET'&&$parameters)$url.='&'.http_build_query($parameters,'','&',PHP_QUERY_RFC3986);elseif($method!=='GET')$body=json_encode($parameters,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
 $requestTrackingId='CAFE-'.date('YmdHis').'-'.strtoupper(bin2hex(random_bytes(4)));
 $headers=['Accept: application/json','Content-Type: application/json','Authorization: Bearer '.$key,'X-Tracking-ID: '.$requestTrackingId];$transport=$GLOBALS['SOKNA_ACCOMMODATION_TRANSPORT']??null;if(is_callable($transport)){ $r=$transport($method,$url,$headers,$body,$timeoutSeconds);return is_array($r)?array_merge(['transport_ok'=>false,'http_status'=>0,'payload'=>[],'raw'=>'','tracking_id'=>''],$r):['transport_ok'=>false,'http_status'=>0,'payload'=>[],'error'=>'پاسخ Transport معتبر نیست.','raw'=>'','tracking_id'=>''];}
 $status=0;$raw='';$error='';$responseTrackingId='';if(function_exists('curl_init')){$ch=curl_init($url);$opt=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>min(3,$timeoutSeconds),CURLOPT_TIMEOUT=>$timeoutSeconds,CURLOPT_HTTPHEADER=>$headers,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_USERAGENT=>'Sokna-Cafe/'.app_release_version(),CURLOPT_HEADERFUNCTION=>static function($ch,string $line) use (&$responseTrackingId): int{$len=strlen($line);if(stripos($line,'X-Sokna-Tracking-ID:')===0)$responseTrackingId=trim(substr($line,strlen('X-Sokna-Tracking-ID:')));return $len;}];if($method!=='GET'){$opt[CURLOPT_CUSTOMREQUEST]=$method;$opt[CURLOPT_POSTFIELDS]=$body;}curl_setopt_array($ch,$opt);$res=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);if($res===false)$error=(string)curl_error($ch);else $raw=(string)$res;curl_close($ch);}else{$ctx=stream_context_create(['http'=>['method'=>$method,'header'=>implode("\r\n",$headers),'content'=>$method==='GET'?'':$body,'timeout'=>$timeoutSeconds,'ignore_errors'=>true],'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);$res=@file_get_contents($url,false,$ctx);if($res===false)$error='اتصال HTTPS برقرار نشد.';else $raw=(string)$res;foreach(($http_response_header??[]) as $line){if(preg_match('#^HTTP/\S+\s+(\d{3})#',$line,$m))$status=(int)$m[1];if(stripos($line,'X-Sokna-Tracking-ID:')===0)$responseTrackingId=trim(substr($line,strlen('X-Sokna-Tracking-ID:')));}}
 if($error!=='')return ['transport_ok'=>false,'http_status'=>$status,'payload'=>[],'error'=>'ارتباط با اقامتگاه برقرار نشد یا زمان پاسخ تمام شد.','raw'=>'','tracking_id'=>$responseTrackingId];$payload=json_decode($raw,true);if(!is_array($payload))return ['transport_ok'=>true,'http_status'=>$status,'payload'=>[],'error'=>'پاسخ اقامتگاه JSON معتبر نبود.','raw'=>'','tracking_id'=>$responseTrackingId];return ['transport_ok'=>true,'http_status'=>$status,'payload'=>$payload,'raw'=>'','tracking_id'=>accommodation_tracking_id($payload,['tracking_id'=>$responseTrackingId])];
}

