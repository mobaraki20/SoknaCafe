<?php
declare(strict_types=1);

/** Sokna House API 2.0 server-to-server integration. */
function accommodation_transfer_statuses(): array{return ['pending'=>'در انتظار انتقال','posted'=>'منتقل‌شده','failed'=>'ناموفق','void_pending'=>'در انتظار برگشت','void_failed'=>'برگشت ناموفق','voided'=>'برگشت‌خورده'];}
function accommodation_transfer_status_label(string $status): string{return accommodation_transfer_statuses()[$status]??$status;}
function accommodation_transfer_display(array $transfer): array
{
    $status=(string)($transfer['status']??'');
    $resolved=!empty($transfer['resolved_at']);
    $localFinalize=bool_from_mixed($transfer['local_finalize_pending']??false);
    $localReversal=bool_from_mixed($transfer['local_reversal_pending']??false);
    if($localFinalize)return ['label'=>'منتقل‌شده · تکمیل تسویه کافه','tone'=>'danger','needs_action'=>true];
    if($localReversal)return ['label'=>'برگشت اقامتگاه ثبت شد · سند کافه ناقص','tone'=>'danger','needs_action'=>true];
    if($resolved&&in_array($status,['pending','failed'],true))return ['label'=>$status==='failed'?'ناموفق · تسویه با روش دیگر':'تعیین تکلیف با روش دیگر','tone'=>'muted','needs_action'=>false];
    if(accommodation_transfer_terminal_charge_failure($transfer))return ['label'=>'ناموفق قطعی · تسویه با روش دیگر','tone'=>'danger','needs_action'=>true];
    return match($status){
        'posted'=>['label'=>'منتقل‌شده','tone'=>'success','needs_action'=>false],
        'voided'=>['label'=>'برگشت‌خورده','tone'=>'muted','needs_action'=>false],
        'failed'=>['label'=>'ناموفق · نیازمند رسیدگی','tone'=>'danger','needs_action'=>!$resolved],
        'void_pending'=>['label'=>'در انتظار برگشت','tone'=>'warning','needs_action'=>!$resolved],
        'void_failed'=>['label'=>'برگشت ناموفق','tone'=>'danger','needs_action'=>!$resolved],
        'pending'=>['label'=>'در انتظار انتقال','tone'=>'warning','needs_action'=>!$resolved],
        default=>['label'=>accommodation_transfer_status_label($status),'tone'=>'muted','needs_action'=>false],
    };
}
function accommodation_transfer_effective_time(array $transfer): ?string{
 $status=(string)($transfer['status']??'');
 if(!empty($transfer['resolved_at']))$value=$transfer['resolved_at'];
 else $value=match($status){'posted'=>$transfer['posted_at']??null,'voided'=>$transfer['voided_at']??null,'pending','failed','void_pending','void_failed'=>$transfer['last_attempt_at']??null,default=>null};
 return ($value?:($transfer['created_at']??null))?:(null);
}
function accommodation_transfer_effective_time_sql(string $alias='at'): string{
 $a=preg_replace('/[^a-zA-Z0-9_]/','',$alias)?:'at';
 return "COALESCE($a.resolved_at,CASE WHEN $a.status='posted' THEN $a.posted_at WHEN $a.status='voided' THEN $a.voided_at WHEN $a.status IN('pending','failed','void_pending','void_failed') THEN $a.last_attempt_at ELSE NULL END,$a.created_at)";
}
function accommodation_transfer_table_snapshot(array $transfer): string{
 $snapshot=json_decode((string)($transfer['invoice_snapshot_json']??''),true);
 $name=is_array($snapshot)?trim((string)($snapshot['session']['table_name']??'')):'';
 return $name!==''?$name:(string)($transfer['table_name_snapshot']??$transfer['table_name']??'');
}
function accommodation_live_operations_enabled(): bool{$o=$GLOBALS['SOKNA_ACCOMMODATION_CONFIG_OVERRIDE']??null;return is_array($o)&&array_key_exists('enabled',$o)?bool_from_mixed($o['enabled']):setting_bool('accommodation_connection_enabled',false);}
function accommodation_require_live_operations(): void{if(!accommodation_live_operations_enabled())throw new RuntimeException('ارتباط زنده اقامتگاه خاموش است؛ برای تماس با اقامتگاه ابتدا اتصال را فعال کنید.');}
function accommodation_api_base_url(): string{$o=$GLOBALS['SOKNA_ACCOMMODATION_CONFIG_OVERRIDE']??null;$v=is_array($o)&&array_key_exists('base_url',$o)?(string)$o['base_url']:setting('accommodation_api_base_url');return rtrim(trim($v),'/');}
function accommodation_validate_base_url(string $url): string{$url=rtrim(trim($url),'/');if($url===''||filter_var($url,FILTER_VALIDATE_URL)===false)throw new InvalidArgumentException('آدرس اتصال اقامتگاه معتبر نیست.');$p=parse_url($url);$scheme=strtolower((string)($p['scheme']??''));$host=strtolower((string)($p['host']??''));$local=in_array($host,['localhost','127.0.0.1','::1'],true);if($scheme!=='https'&&!$local)throw new InvalidArgumentException('ارتباط اقامتگاه فقط باید روی HTTPS انجام شود.');if(isset($p['user'])||isset($p['pass'])||isset($p['fragment']))throw new InvalidArgumentException('آدرس اتصال نباید شامل نام کاربری، رمز یا بخش اضافه بعد از نشانی باشد.');return $url;}
function accommodation_secret_key(string $version='v2'): string{global $config;$key=trim((string)($config['app']['key']??''));if($key==='')throw new RuntimeException('کلید داخلی سامانه در config.php تنظیم نشده است.');$salt=$version==='v1'?'sokna-accommodation-v1|':'sokna-accommodation-v2|';return hash('sha256',$salt.$key,true);}
function accommodation_encrypt_secret(string $plain): string{if($plain==='')return '';if(!function_exists('openssl_encrypt'))throw new RuntimeException('افزونه OpenSSL برای نگهداری امن کلید API لازم است.');$iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($plain,'aes-256-gcm',accommodation_secret_key('v2'),OPENSSL_RAW_DATA,$iv,$tag,'sokna-accommodation-v2');if($cipher===false||strlen($tag)!==16)throw new RuntimeException('رمزنگاری کلید API انجام نشد.');return 'enc:v2:'.base64_encode($iv.$tag.$cipher);}
function accommodation_decrypt_secret(string $stored): string{if($stored==='')return '';if(str_starts_with($stored,'enc:v2:')){$aad='sokna-accommodation-v2';$version='v2';$offset=7;}elseif(str_starts_with($stored,'enc:v1:')){$aad='sokna-accommodation';$version='v1';$offset=7;}else return '';$raw=base64_decode(substr($stored,$offset),true);if($raw===false||strlen($raw)<29)return '';$plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',accommodation_secret_key($version),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16),$aad);return is_string($plain)?$plain:'';}
function accommodation_api_key(): string{$o=$GLOBALS['SOKNA_ACCOMMODATION_CONFIG_OVERRIDE']??null;return is_array($o)&&array_key_exists('api_key',$o)?trim((string)$o['api_key']):accommodation_decrypt_secret(setting('accommodation_api_key_encrypted'));}
function accommodation_key_fingerprint(string $key): string{return $key===''?'':strtoupper(substr(hash('sha256',$key),0,10));}
function accommodation_save_api_key(string $key): void{$key=trim($key);if($key==='')return;$st=db()->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');$st->execute(['accommodation_api_key_encrypted',accommodation_encrypt_secret($key)]);$st->execute(['accommodation_api_key_fingerprint',accommodation_key_fingerprint($key)]);}
function accommodation_api_url(string $action): string{$base=accommodation_validate_base_url(accommodation_api_base_url());if(preg_match('#/api\.php$#i',$base))return $base.'?action='.rawurlencode($action);return $base.'/api.php?action='.rawurlencode($action);}
function accommodation_error_code(array $payload,int $httpStatus=0): string{$v=strtolower(trim((string)($payload['error']??$payload['code']??'')));if($v!=='')return preg_replace('/[^a-z0-9_-]+/','_',$v)?:'remote_error';if(in_array($httpStatus,[401,403],true))return 'unauthorized';if($httpStatus===429)return 'rate_limited';if($httpStatus>=500)return 'internal_error';return '';}
function accommodation_error_message(array $payload,string $fallback): string{$m=trim((string)($payload['message']??''));return text_substr($m!==''?$m:$fallback,0,500);}
function accommodation_tracking_id(array $payload,array $response=[]): string{return text_substr(trim((string)($payload['tracking_id']??$response['tracking_id']??'')),0,120);}
function accommodation_response_policy(string $operation,string $code,int $httpStatus): array
{
    $financial=in_array($operation,['charge','void'],true);
    if(!$financial)return ['outcome'=>'deterministic_failure','ambiguous'=>false,'retryable'=>false];
    $deterministic=[
        'unauthorized','module_disabled','connection_disabled','https_required','invalid_request','rate_limited','unsupported_media_type','payload_too_large',
        'reservation_not_found','reservation_not_chargeable','invoice_total_mismatch','unsupported_currency','unsupported_invoice_version','external_order_conflict',
        'unknown_external_order_id','charge_not_voidable','schema_not_ready','temporary_failure',
    ];
    if(in_array($code,$deterministic,true))return ['outcome'=>'deterministic_failure','ambiguous'=>false,'retryable'=>in_array($code,['rate_limited','schema_not_ready','temporary_failure','unauthorized','module_disabled','connection_disabled','https_required'],true)];
    $ambiguous=in_array($code,['internal_error','invalid_response','ambiguous_response','transport_error','timeout','network_error'],true)||$httpStatus>=500;
    return ['outcome'=>$ambiguous?'ambiguous_failure':'deterministic_failure','ambiguous'=>$ambiguous,'retryable'=>$ambiguous];
}
function accommodation_message_with_prefix(string $prefix,string $message): string
{
    $prefix=trim($prefix);$message=trim($message);
    if($message==='')return $prefix;
    $needle=rtrim($prefix," .،؛:!?؟\t\n\r\0\x0B");
    if($needle!==''&&str_starts_with($message,$needle))return $message;
    return rtrim($prefix). ' ' . $message;
}
function accommodation_error_affects_connection_health(string $code): bool
{
    return in_array($code,[
        'transport_error','timeout','network_error','invalid_response','internal_error',
        'unauthorized','module_disabled','connection_disabled','https_required','schema_not_ready','temporary_failure','rate_limited',
    ],true);
}
function accommodation_result_connection_healthy(array $result): bool
{
    if(bool_from_mixed($result['success']??false))return true;
    return !accommodation_error_affects_connection_health((string)($result['code']??''));
}
function accommodation_public_error_message(string $code,string $message): string{return match($code){
    'unauthorized','connection_disabled','https_required'=>'اتصال اقامتگاه نیازمند بررسی مدیر است.',
    'module_disabled'=>'قابلیت اتصال کافه در سامانه اقامتگاه فعال نیست.',
    'schema_not_ready'=>'ساختار داده اتصال کافه در سامانه اقامتگاه آماده نیست؛ مدیر سامانه اقامتگاه باید بخش «اتصال کافه» را بررسی کند.',
    'temporary_failure'=>'سامانه اقامتگاه موقتاً نتوانست عملیات را انجام دهد؛ با همان شناسه انتقال دوباره تلاش کنید.',
    'rate_limited'=>'تعداد درخواست‌ها به اقامتگاه بیش از حد مجاز است؛ کمی بعد دوباره تلاش کنید.',
    'reservation_not_found'=>'رزرو موردنظر پیدا نشد.',
    'reservation_not_chargeable'=>accommodation_message_with_prefix('این رزرو امکان ثبت هزینه ندارد.',$message),
    'external_order_conflict'=>'شناسه این فاکتور قبلاً با اطلاعات متفاوت ثبت شده است؛ تلاش خودکار متوقف شد و مدیر باید مغایرت را بررسی کند.',
    'invoice_total_mismatch','unsupported_currency','unsupported_invoice_version','invalid_request','unsupported_media_type','payload_too_large'=>'اطلاعات فاکتور با قرارداد اقامتگاه سازگار نیست؛ بررسی فنی لازم است.',
    'unknown_external_order_id'=>'شناسه انتقال در سامانه اقامتگاه پیدا نشد؛ بررسی مدیر لازم است.',
    'charge_not_voidable'=>'این هزینه در سامانه اقامتگاه قابل برگشت نیست.',
    'internal_error'=>'سامانه اقامتگاه هنگام ثبت هزینه خطای داخلی برگرداند؛ مدیر باید کد پیگیری را بررسی کند.',
    'invalid_response'=>'پاسخ سامانه اقامتگاه معتبر نبود؛ نتیجه عملیات نیازمند بررسی است.',
    default=>text_substr(trim($message)!==''?$message:'ارتباط با اقامتگاه انجام نشد.',0,500)
};}
function accommodation_public_error_with_tracking(string $code,string $message,string $trackingId=''): string
{
    $public=accommodation_public_error_message($code,$message);$trackingId=trim($trackingId);
    return $trackingId===''?$public:text_substr(rtrim($public)." کد پیگیری: ".$trackingId,0,500);
}

function accommodation_http_request(string $action,string $method='GET',array $parameters=[],int $timeoutSeconds=7): array{
 if(!accommodation_live_operations_enabled())return ['transport_ok'=>true,'http_status'=>409,'payload'=>['ok'=>false,'error'=>'connection_disabled','message'=>'ارتباط زنده اقامتگاه خاموش است.'],'raw'=>'','tracking_id'=>''];
 $key=accommodation_api_key();if($key==='')return ['transport_ok'=>true,'http_status'=>401,'payload'=>['ok'=>false,'error'=>'unauthorized','message'=>'کلید API تنظیم نشده است.'],'raw'=>'','tracking_id'=>''];
 try{$url=accommodation_api_url($action);}catch(Throwable $e){return ['transport_ok'=>false,'http_status'=>0,'payload'=>[],'error'=>$e->getMessage(),'raw'=>'','tracking_id'=>''];}
 $method=strtoupper($method);$body='';if($method==='GET'&&$parameters)$url.='&'.http_build_query($parameters,'','&',PHP_QUERY_RFC3986);elseif($method!=='GET')$body=json_encode($parameters,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
 $requestTrackingId='CAFE-'.date('YmdHis').'-'.strtoupper(bin2hex(random_bytes(4)));
 $headers=['Accept: application/json','Content-Type: application/json','Authorization: Bearer '.$key,'X-Tracking-ID: '.$requestTrackingId];$transport=$GLOBALS['SOKNA_ACCOMMODATION_TRANSPORT']??null;if(is_callable($transport)){ $r=$transport($method,$url,$headers,$body,$timeoutSeconds);return is_array($r)?array_merge(['transport_ok'=>false,'http_status'=>0,'payload'=>[],'raw'=>'','tracking_id'=>''],$r):['transport_ok'=>false,'http_status'=>0,'payload'=>[],'error'=>'پاسخ Transport معتبر نیست.','raw'=>'','tracking_id'=>''];}
 $status=0;$raw='';$error='';$responseTrackingId='';if(function_exists('curl_init')){$ch=curl_init($url);$opt=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>min(3,$timeoutSeconds),CURLOPT_TIMEOUT=>$timeoutSeconds,CURLOPT_HTTPHEADER=>$headers,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_USERAGENT=>'Sokna-Cafe/'.app_release_version(),CURLOPT_HEADERFUNCTION=>static function($ch,string $line) use (&$responseTrackingId): int{$len=strlen($line);if(stripos($line,'X-Sokna-Tracking-ID:')===0)$responseTrackingId=trim(substr($line,strlen('X-Sokna-Tracking-ID:')));return $len;}];if($method!=='GET'){$opt[CURLOPT_CUSTOMREQUEST]=$method;$opt[CURLOPT_POSTFIELDS]=$body;}curl_setopt_array($ch,$opt);$res=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);if($res===false)$error=(string)curl_error($ch);else $raw=(string)$res;curl_close($ch);}else{$ctx=stream_context_create(['http'=>['method'=>$method,'header'=>implode("\r\n",$headers),'content'=>$method==='GET'?'':$body,'timeout'=>$timeoutSeconds,'ignore_errors'=>true],'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);$res=@file_get_contents($url,false,$ctx);if($res===false)$error='اتصال HTTPS برقرار نشد.';else $raw=(string)$res;foreach(($http_response_header??[]) as $line){if(preg_match('#^HTTP/\S+\s+(\d{3})#',$line,$m))$status=(int)$m[1];if(stripos($line,'X-Sokna-Tracking-ID:')===0)$responseTrackingId=trim(substr($line,strlen('X-Sokna-Tracking-ID:')));}}
 if($error!=='')return ['transport_ok'=>false,'http_status'=>$status,'payload'=>[],'error'=>'ارتباط با اقامتگاه برقرار نشد یا زمان پاسخ تمام شد.','raw'=>'','tracking_id'=>$responseTrackingId];$payload=json_decode($raw,true);if(!is_array($payload))return ['transport_ok'=>true,'http_status'=>$status,'payload'=>[],'error'=>'پاسخ اقامتگاه JSON معتبر نبود.','raw'=>'','tracking_id'=>$responseTrackingId];return ['transport_ok'=>true,'http_status'=>$status,'payload'=>$payload,'raw'=>'','tracking_id'=>accommodation_tracking_id($payload,['tracking_id'=>$responseTrackingId])];
}
function accommodation_result_from_response(array $response,string $operation): array
{
    $financial=in_array($operation,['charge','void'],true);
    $p=is_array($response['payload']??null)?$response['payload']:[];$http=(int)($response['http_status']??0);$trackingId=accommodation_tracking_id($p,$response);
    if(!($response['transport_ok']??false))return ['success'=>false,'ambiguous'=>$financial,'outcome'=>$financial?'ambiguous_failure':'deterministic_failure','retryable'=>$financial,'code'=>'transport_error','message'=>(string)($response['error']??'ارتباط برقرار نشد.'),'payload'=>[],'tracking_id'=>$trackingId];
    if($p===[]&&trim((string)($response['error']??''))!=='')return ['success'=>false,'ambiguous'=>$financial,'outcome'=>$financial?'ambiguous_failure':'deterministic_failure','retryable'=>$financial,'code'=>'invalid_response','message'=>(string)$response['error'],'payload'=>[],'tracking_id'=>$trackingId];
    $ok=($p['ok']??null)===true&&$http>=200&&$http<300;
    if(!$ok){
        $code=accommodation_error_code($p,$http)?:'remote_error';$policy=accommodation_response_policy($operation,$code,$http);
        return ['success'=>false,'ambiguous'=>$policy['ambiguous'],'outcome'=>$policy['outcome'],'retryable'=>$policy['retryable'],'code'=>$code,'message'=>accommodation_error_message($p,'اقامتگاه درخواست را نپذیرفت.'),'payload'=>$p,'tracking_id'=>$trackingId];
    }
    if($operation==='search'||$operation==='reservation'||$operation==='capabilities')return ['success'=>true,'ambiguous'=>false,'outcome'=>'success','retryable'=>false,'code'=>'','message'=>'','payload'=>$p,'tracking_id'=>$trackingId];
    $transaction=trim((string)($p['transaction_id']??''));
    if($transaction==='')return ['success'=>false,'ambiguous'=>true,'outcome'=>'ambiguous_failure','retryable'=>true,'code'=>'ambiguous_response','message'=>'پاسخ اقامتگاه شناسه تراکنش ندارد.','payload'=>$p,'tracking_id'=>$trackingId];
    return ['success'=>true,'ambiguous'=>false,'outcome'=>'success','retryable'=>false,'code'=>'','message'=>'','payload'=>$p,'tracking_id'=>$trackingId,'idempotent'=>bool_from_mixed($p['idempotent']??false),'transaction_id'=>text_substr($transaction,0,120),'original_transaction_id'=>text_substr((string)($p['original_transaction_id']??''),0,120)];
}
function accommodation_display_digits(string $value): string
{
    if(function_exists('fa_digits'))return fa_digits($value);
    return strtr($value,['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']);
}
function accommodation_display_api_date(string $value): string
{
    $value=trim($value);
    if(preg_match('/^(\d{4})-(\d{2})-(\d{2})$/',$value,$m)){
        try{[$jy,$jm,$jd]=gregorian_to_jalali((int)$m[1],(int)$m[2],(int)$m[3]);return accommodation_display_digits(sprintf('%04d/%02d/%02d',$jy,$jm,$jd));}catch(Throwable){}
    }
    return accommodation_display_digits(text_substr($value,0,40));
}
function accommodation_normalize_reservation(array $row): ?array{$code=trim((string)($row['reservation_code']??''));$guest=trim((string)($row['guest_name']??''));$rooms=is_array($row['room_names']??null)?array_values(array_filter(array_map(static fn($x):string=>text_substr(trim((string)$x),0,120),$row['room_names']))):[];if($code===''||$guest===''||!$rooms)return null;$allowed=bool_from_mixed($row['charge_allowed']??false);return ['reservation_code'=>text_substr($code,0,80),'guest_name'=>text_substr($guest,0,160),'room_names'=>$rooms,'room_name'=>text_substr(implode('، ',$rooms),0,240),'check_in'=>accommodation_display_api_date((string)($row['check_in']??'')),'check_out'=>accommodation_display_api_date((string)($row['check_out']??'')),'charge_allowed'=>$allowed,'can_charge'=>$allowed,'charge_block_reason'=>text_substr(trim((string)($row['charge_block_reason']??'')),0,300),'phone_hint'=>text_substr(trim((string)($row['masked_mobile']??'')),0,40)];}
function accommodation_reservations_from_payload(array $payload): array{$rows=$payload['reservations']??[];if(!is_array($rows))return [];$out=[];foreach($rows as $row)if(is_array($row)&&($r=accommodation_normalize_reservation($row)))$out[$r['reservation_code']]=$r;return array_values($out);}
function accommodation_search_active(string $query): array{$query=text_substr(trim($query),0,160);if($query==='')return ['success'=>false,'code'=>'invalid_request','message'=>'عبارت جست‌وجو را وارد کن.','reservations'=>[]];$r=accommodation_result_from_response(accommodation_http_request('search','GET',['query'=>$query]),'search');if(!$r['success'])return $r+['reservations'=>[]];return ['success'=>true,'code'=>'','message'=>'','reservations'=>accommodation_reservations_from_payload($r['payload'])];}
function accommodation_reservation_exact(string $code): array{$code=text_substr(trim($code),0,80);if($code==='')return ['success'=>false,'code'=>'invalid_request','message'=>'کد رزرو خالی است.'];$r=accommodation_result_from_response(accommodation_http_request('reservation','GET',['reservation_code'=>$code]),'reservation');if(!$r['success'])return $r;$reservation=is_array($r['payload']['reservation']??null)?accommodation_normalize_reservation($r['payload']['reservation']):null;if(!$reservation)return ['success'=>false,'code'=>'invalid_response','message'=>'پاسخ رزرو کامل نیست.'];return ['success'=>true,'reservation'=>$reservation];}
function accommodation_external_order_id(int $sessionId): string{return 'CAFE-S-'.$sessionId;}
function accommodation_canonical_json(array $value): string{return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function accommodation_contract_error_codes(): array
{
    return ['invalid_request','invoice_total_mismatch','unsupported_currency','unsupported_invoice_version'];
}
function accommodation_normalize_invoice_snapshot(array $snapshot): array
{
    $items = [];
    foreach ((array)($snapshot['items'] ?? []) as $item) {
        if (!is_array($item)) continue;
        $note = trim((string)($item['note'] ?? ''));
        $items[] = [
            'name' => text_substr(trim((string)($item['name'] ?? '')), 0, 240),
            'quantity' => (int)($item['quantity'] ?? 0),
            'unit_price' => (int)($item['unit_price'] ?? 0),
            'line_total' => (int)($item['line_total'] ?? 0),
            'note' => $note === '' ? null : text_substr($note, 0, 500),
        ];
    }
    return [
        'version' => (int)($snapshot['version'] ?? 1),
        'number' => text_substr(trim((string)($snapshot['number'] ?? '')), 0, 40),
        'issued_at' => text_substr(trim((string)($snapshot['issued_at'] ?? '')), 0, 60),
        'table_name' => text_substr(trim((string)($snapshot['table_name'] ?? '')), 0, 160),
        'subtotal' => (int)($snapshot['subtotal'] ?? 0),
        'discount' => (int)($snapshot['discount'] ?? 0),
        'total' => (int)($snapshot['total'] ?? 0),
        'items' => $items,
    ];
}
function accommodation_validate_invoice_snapshot(array $snapshot): void
{
    if ((int)$snapshot['version'] !== 1) throw new RuntimeException('نسخه اطلاعات ثبت‌شده فاکتور برای اتصال اقامتگاه معتبر نیست.');
    if (trim((string)$snapshot['number']) === '' || trim((string)$snapshot['issued_at']) === '' || trim((string)$snapshot['table_name']) === '') throw new RuntimeException('مشخصات ثبت‌شده فاکتور کامل نیست.');
    if (!$snapshot['items']) throw new RuntimeException('فاکتور ثبت‌شده هیچ ردیفی ندارد.');
    $subtotal = 0;
    foreach ($snapshot['items'] as $item) {
        if ((int)$item['quantity'] < 1 || (int)$item['unit_price'] < 0 || (int)$item['line_total'] !== (int)$item['quantity'] * (int)$item['unit_price']) throw new RuntimeException('محاسبات یکی از ردیف‌های ثبت‌شده فاکتور معتبر نیست.');
        $subtotal += (int)$item['line_total'];
    }
    if ($subtotal !== (int)$snapshot['subtotal'] || (int)$snapshot['subtotal'] - (int)$snapshot['discount'] !== (int)$snapshot['total'] || (int)$snapshot['total'] < 0) throw new RuntimeException('جمع اطلاعات ثبت‌شده فاکتور با حساب اقامتگاه سازگار نیست.');
}

function accommodation_charge_remote(array $transfer): array
{
    $snapshot = json_decode((string)($transfer['invoice_snapshot_json'] ?? ''), true);
    if (!is_array($snapshot)) return ['success'=>false,'ambiguous'=>false,'code'=>'invalid_request','message'=>'اطلاعات ثبت‌شده فاکتور معتبر نیست.'];
    try {
        $snapshot = accommodation_normalize_invoice_snapshot($snapshot);
        accommodation_validate_invoice_snapshot($snapshot);
    } catch (RuntimeException $e) {
        return ['success'=>false,'ambiguous'=>false,'code'=>'invalid_request','message'=>$e->getMessage()];
    }
    $payload = [
        'external_order_id'=>(string)$transfer['external_order_id'],
        'reservation_code'=>(string)$transfer['reservation_code'],
        'amount'=>(int)$transfer['amount'],
        'currency'=>'TOMAN',
        'invoice'=>$snapshot,
    ];
    if ((int)$payload['amount'] !== (int)$snapshot['total']) return ['success'=>false,'ambiguous'=>false,'code'=>'invoice_total_mismatch','message'=>'مبلغ انتقال با جمع فاکتور ثبت‌شده یکسان نیست.'];
    return accommodation_result_from_response(accommodation_http_request('charge','POST',$payload,10),'charge');
}
function accommodation_void_remote(array $transfer,string $reason): array{return accommodation_result_from_response(accommodation_http_request('void','POST',['external_order_id'=>(string)$transfer['external_order_id'],'reason'=>text_substr(trim($reason),0,300),'requested_at'=>date(DATE_ATOM)],10),'void');}
function accommodation_update_connection_state(bool $success,string $message=''): void{accommodation_setting_write($success?'accommodation_api_last_success_at':'accommodation_api_last_error_at',date('Y-m-d H:i:s'));if($success)accommodation_setting_write('accommodation_api_last_error','');else accommodation_setting_write('accommodation_api_last_error',text_substr($message,0,500));}
function accommodation_audit(string $action,string $entityType,string|int|null $entityId,array $details=[],?int $actorUserId=null): void{audit_log_write($action,$entityType,$entityId,$details,$actorUserId);}
function accommodation_history_exists(): bool
{
    static $cached=null;
    if($cached!==null)return $cached;
    try{$cached=(bool)db()->query("SELECT 1 FROM accommodation_transfers LIMIT 1")->fetchColumn();}
    catch(Throwable){$cached=false;}
    return $cached;
}
function accommodation_transfer_is_unresolved(string $status): bool{return in_array($status,['pending','failed','void_pending','void_failed'],true);}
function accommodation_transfer_is_ambiguous(array $transfer): bool
{
    if((int)($transfer['suspicious_response']??0)===1)return true;
    return in_array((string)($transfer['last_error_code']??''),['internal_error','invalid_response','ambiguous_response','transport_error','timeout','network_error'],true);
}
function accommodation_transfer_retry_allowed(array $transfer): bool
{
    if(!in_array((string)($transfer['status']??''),['pending','failed'],true)||!empty($transfer['resolved_at']))return false;
    $code=(string)($transfer['last_error_code']??'');
    if($code==='')return (string)($transfer['status']??'')==='pending';
    return bool_from_mixed(accommodation_response_policy('charge',$code,0)['retryable']??false);
}
function accommodation_transfer_terminal_charge_failure(array $transfer): bool
{
    return (string)($transfer['status']??'')==='failed'
        &&empty($transfer['resolved_at'])
        &&!accommodation_transfer_is_ambiguous($transfer)
        &&!accommodation_transfer_retry_allowed($transfer);
}
function accommodation_transfer_local_fields_sql(string $alias='at'): string
{
    $a=preg_replace('/[^a-zA-Z0-9_]/','',$alias)?:'at';
    return "(SELECT sr.id FROM settlement_records sr WHERE sr.accommodation_transfer_id=$a.id AND sr.status='completed' ORDER BY sr.id DESC LIMIT 1) settlement_record_id,".
        "(SELECT rev.id FROM settlement_records sr JOIN settlement_records rev ON rev.reverses_settlement_id=sr.id AND rev.status='reversal' WHERE sr.accommodation_transfer_id=$a.id AND sr.status='completed' ORDER BY rev.id DESC LIMIT 1) reversal_settlement_record_id";
}
function accommodation_attention_where_sql(string $alias='at'): string
{
    $a=preg_replace('/[^a-zA-Z0-9_]/','',$alias)?:'at';
    $completed="EXISTS(SELECT 1 FROM settlement_records sr WHERE sr.accommodation_transfer_id=$a.id AND sr.status='completed')";
    $reversed="EXISTS(SELECT 1 FROM settlement_records sr JOIN settlement_records rev ON rev.reverses_settlement_id=sr.id AND rev.status='reversal' WHERE sr.accommodation_transfer_id=$a.id AND sr.status='completed')";
    return "(($a.status IN('pending','failed','void_pending','void_failed') AND $a.resolved_at IS NULL) OR ($a.status='posted' AND NOT $completed) OR ($a.status='voided' AND $completed AND NOT $reversed))";
}
function accommodation_enrich_transfer(array $r): array
{
    $status=(string)($r['status']??'');
    $hasSettlement=(int)($r['settlement_record_id']??0)>0;
    $hasReversal=(int)($r['reversal_settlement_record_id']??0)>0;
    $r['local_settlement_complete']=$hasSettlement;
    $r['local_finalize_pending']=$status==='posted'&&!$hasSettlement;
    $r['local_reversal_pending']=$status==='voided'&&$hasSettlement&&!$hasReversal;
    $r['retry_allowed']=accommodation_transfer_retry_allowed($r);
    $r['ambiguous']=accommodation_transfer_is_ambiguous($r);
    $r['detached']=((string)($r['session_status']??'')==='followup');
    $r['can_detach']=$r['ambiguous']&&!$r['detached']&&empty($r['resolved_at'])&&in_array($status,['pending','failed'],true);
    $r['can_finalize_local']=$r['local_finalize_pending'];
    $r['needs_action']=(accommodation_transfer_is_unresolved($status)&&empty($r['resolved_at'])) || $r['local_finalize_pending'] || $r['local_reversal_pending'];
    $display=accommodation_transfer_display($r);
    $r['status_label']=$display['label'];
    $r['status_tone']=$display['tone'];
    return $r;
}
function accommodation_transfer_by_id(int $id,bool $forUpdate=false): ?array
{
    $localFields=accommodation_transfer_local_fields_sql('at');
    $sql="SELECT at.*,ts.status session_status,ts.table_id,t.name table_name,$localFields FROM accommodation_transfers at JOIN table_sessions ts ON ts.id=at.session_id JOIN cafe_tables t ON t.id=ts.table_id WHERE at.id=? LIMIT 1".($forUpdate?' FOR UPDATE':'');
    $st=db()->prepare($sql);$st->execute([$id]);$r=$st->fetch()?:null;
    return $r?accommodation_enrich_transfer($r):null;
}
function accommodation_transfer_by_session(int $sessionId,bool $forUpdate=false): ?array
{
    $localFields=accommodation_transfer_local_fields_sql('at');
    $sql="SELECT at.*,ts.status session_status,ts.table_id,t.name table_name,$localFields FROM accommodation_transfers at JOIN table_sessions ts ON ts.id=at.session_id JOIN cafe_tables t ON t.id=ts.table_id WHERE at.session_id=? LIMIT 1".($forUpdate?' FOR UPDATE':'');
    $st=db()->prepare($sql);$st->execute([$sessionId]);$r=$st->fetch()?:null;
    return $r?accommodation_enrich_transfer($r):null;
}
function accommodation_attention_counts(): array
{
    $defaults=['pending'=>0,'failed'=>0,'void_pending'=>0,'void_failed'=>0,'local_finalize'=>0,'local_reversal'=>0];
    try{
        $sql="SELECT
            COALESCE(SUM(at.status='pending' AND at.resolved_at IS NULL),0) pending,
            COALESCE(SUM(at.status='failed' AND at.resolved_at IS NULL),0) failed,
            COALESCE(SUM(at.status='void_pending' AND at.resolved_at IS NULL),0) void_pending,
            COALESCE(SUM(at.status='void_failed' AND at.resolved_at IS NULL),0) void_failed,
            COALESCE(SUM(at.status='posted' AND NOT EXISTS(SELECT 1 FROM settlement_records sr WHERE sr.accommodation_transfer_id=at.id AND sr.status='completed')),0) local_finalize,
            COALESCE(SUM(at.status='voided' AND EXISTS(SELECT 1 FROM settlement_records sr WHERE sr.accommodation_transfer_id=at.id AND sr.status='completed') AND NOT EXISTS(SELECT 1 FROM settlement_records sr JOIN settlement_records rev ON rev.reverses_settlement_id=sr.id AND rev.status='reversal' WHERE sr.accommodation_transfer_id=at.id AND sr.status='completed')),0) local_reversal
            FROM accommodation_transfers at";
        $row=db()->query($sql)->fetch()?:[];
        foreach($defaults as $key=>$value)$defaults[$key]=(int)($row[$key]??0);
    }catch(Throwable){}
    return $defaults;
}
function accommodation_attention_count(): int{return array_sum(accommodation_attention_counts());}
function accommodation_attention_rows(?int $limit=50): array
{
    $limitSql='';
    if($limit!==null){$limit=max(1,min(200,$limit));$limitSql=' LIMIT '.$limit;}
    $localFields=accommodation_transfer_local_fields_sql('at');
    $where=accommodation_attention_where_sql('at');
    $rows=db()->query("SELECT at.*,ts.status session_status,ts.table_id,t.name table_name,u.display_name operator_name,$localFields FROM accommodation_transfers at JOIN table_sessions ts ON ts.id=at.session_id JOIN cafe_tables t ON t.id=ts.table_id LEFT JOIN users u ON u.id=at.operator_user_id WHERE $where ORDER BY at.updated_at ASC,at.id ASC".$limitSql)->fetchAll();
    foreach($rows as &$r)$r=accommodation_enrich_transfer($r);unset($r);
    return $rows;
}
function accommodation_calculate_session_invoice_locked(PDO $pdo,int $sessionId): array{return settlement_calculate_session_invoice_locked($pdo,$sessionId);}
function accommodation_invoice_snapshot_locked(PDO $pdo,array $invoice,string $tableName,?string $invoiceNumber=null): array{
 $invoice['session']['table_name']=$tableName;
 return settlement_invoice_snapshot_locked($pdo,$invoice,$invoiceNumber?:('I-'.(int)$invoice['session']['id']));
}
function accommodation_prepare_transfer_for_table(int $tableId,int $userId,array $reservation,bool $printFinal=false,int $expectedSessionId=0,int $expectedTotal=-1,string $expectedSignature=''): array{
 accommodation_require_live_operations();
 $code=text_substr(trim((string)($reservation['reservation_code']??'')),0,80);
 $guest=text_substr(trim((string)($reservation['guest_name']??'')),0,160);
 $room=text_substr(trim((string)($reservation['room_name']??implode('، ',(array)($reservation['room_names']??[])))),0,240);
 $phone=text_substr(trim((string)($reservation['phone_hint']??'')),0,40);
 if($tableId<1||$code===''||$guest===''||$room==='')throw new RuntimeException('رزرو انتخاب‌شده کامل نیست.');
 $pdo=db();$pdo->beginTransaction();
 try{
  $t=$pdo->prepare('SELECT id,name FROM cafe_tables WHERE id=? AND active=1 FOR UPDATE');$t->execute([$tableId]);$table=$t->fetch();if(!$table)throw new RuntimeException('میز پیدا نشد.');
  $s=$pdo->prepare("SELECT id FROM table_sessions WHERE table_id=? AND status='active' ORDER BY id DESC LIMIT 1 FOR UPDATE");$s->execute([$tableId]);$sessionId=(int)($s->fetchColumn()?:0);if($sessionId<1)throw new RuntimeException('این میز حساب فعال ندارد.');
  settlement_assert_session_editable_locked($pdo,$sessionId,'تسویه حساب اقامتگاه');
  $invoice=settlement_calculate_session_invoice_locked($pdo,$sessionId);$account=settlement_account_state_locked($pdo,$invoice);settlement_assert_expected_account($account,$expectedSessionId,$expectedTotal,$expectedSignature);if((int)$invoice['total']<1)throw new RuntimeException('مبلغ نهایی معتبر نیست.');
  $ex=$pdo->prepare('SELECT * FROM accommodation_transfers WHERE session_id=? FOR UPDATE');$ex->execute([$sessionId]);$existing=$ex->fetch();
  if($existing){
   $snapshot=json_decode((string)$existing['invoice_snapshot_json'],true);
   if(!is_array($snapshot))throw new RuntimeException('اطلاعات انتقال قبلی معتبر نیست.');
   $normalized=accommodation_normalize_invoice_snapshot($snapshot);
   accommodation_validate_invoice_snapshot($normalized);
   $hash=hash('sha256',accommodation_canonical_json(['external_order_id'=>(string)$existing['external_order_id'],'reservation_code'=>$code,'amount'=>(int)$invoice['total'],'currency'=>'TOMAN','invoice'=>$normalized]));
   if((string)$existing['reservation_code']!==$code||(int)$existing['amount']!==(int)$invoice['total'])throw new RuntimeException('برای این حساب انتقال دیگری با رزرو یا مبلغ متفاوت ساخته شده است.');
   if(!hash_equals((string)$existing['payload_hash'],$hash)){
    $deterministicContractFailure=(string)$existing['status']==='failed'&&in_array((string)($existing['last_error_code']??''),accommodation_contract_error_codes(),true)&&(int)($existing['suspicious_response']??0)===0;
    if(!$deterministicContractFailure)throw new RuntimeException('اطلاعات انتقال قبلی با وضعیت فعلی سازگار نیست و باید توسط مدیر بررسی شود.');
    $pdo->prepare('UPDATE accommodation_transfers SET invoice_snapshot_json=?,payload_hash=? WHERE id=?')->execute([accommodation_canonical_json($normalized),$hash,(int)$existing['id']]);
    accommodation_audit('accommodation_contract_snapshot_normalized','accommodation_transfer',(int)$existing['id'],['previous_error_code'=>(string)($existing['last_error_code']??'')],$userId);
   }
   if($printFinal && (int)($existing['print_final_requested']??0)!==1){
    $pdo->prepare('UPDATE accommodation_transfers SET print_final_requested=1 WHERE id=?')->execute([(int)$existing['id']]);
   }
   $pdo->commit();return accommodation_transfer_by_id((int)$existing['id'])?:$existing;
  }
  $issued=financial_period_issue_invoice_locked($pdo,date('Y-m-d H:i:s'),$userId);$periodId=(int)$issued['period']['id'];$invoiceNumber=(string)$issued['invoice_number'];
  $snapshot=accommodation_normalize_invoice_snapshot(accommodation_invoice_snapshot_locked($pdo,$invoice,(string)$table['name'],$invoiceNumber));accommodation_validate_invoice_snapshot($snapshot);$snapshotJson=accommodation_canonical_json($snapshot);
  $payloadHash=hash('sha256',accommodation_canonical_json(['external_order_id'=>accommodation_external_order_id($sessionId),'reservation_code'=>$code,'amount'=>(int)$invoice['total'],'currency'=>'TOMAN','invoice'=>$snapshot]));
  $in=$pdo->prepare("INSERT INTO accommodation_transfers(session_id,financial_period_id,external_order_id,reservation_code,guest_name_snapshot,room_name_snapshot,phone_hint_snapshot,amount,invoice_snapshot_json,payload_hash,status,operator_user_id,print_final_requested) VALUES(?,?,?,?,?,?,?,?,?,?,'pending',?,?)");
  $in->execute([$sessionId,$periodId,accommodation_external_order_id($sessionId),$code,$guest,$room,$phone?:null,(int)$invoice['total'],$snapshotJson,$payloadHash,$userId,$printFinal?1:0]);
  $id=(int)$pdo->lastInsertId();$pdo->commit();
  accommodation_audit('accommodation_transfer_created','accommodation_transfer',$id,['session_id'=>$sessionId,'reservation_code'=>$code,'amount'=>(int)$invoice['total'],'invoice_number'=>$invoiceNumber,'payload_hash'=>$payloadHash],$userId);
  return accommodation_transfer_by_id($id)??[];
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function accommodation_finalize_local_checkout(int $transferId,int $actorUserId,bool $printFinal=false): array
{
    $pdo=db();$pdo->beginTransaction();
    try{
        $st=$pdo->prepare('SELECT * FROM accommodation_transfers WHERE id=? FOR UPDATE');
        $st->execute([$transferId]);
        $tr=$st->fetch();
        if(!$tr||$tr['status']!=='posted')throw new RuntimeException('انتقال اقامتگاه هنوز در وضعیت ثبت‌شده نیست.');

        $existing=$pdo->prepare("SELECT id,invoice_number FROM settlement_records WHERE accommodation_transfer_id=? AND status='completed' ORDER BY id DESC LIMIT 1 FOR UPDATE");
        $existing->execute([$transferId]);
        if($settlement=$existing->fetch()){
            $pdo->commit();
            return ['already_settled'=>true,'settlement_id'=>(int)$settlement['id'],'invoice_number'=>(string)$settlement['invoice_number'],'session_id'=>(int)$tr['session_id'],'total'=>(int)$tr['amount']];
        }

        $ss=$pdo->prepare('SELECT * FROM table_sessions WHERE id=? FOR UPDATE');
        $ss->execute([(int)$tr['session_id']]);
        $session=$ss->fetch();
        if(!$session)throw new RuntimeException('حساب مرتبط با انتقال اقامتگاه پیدا نشد.');
        if((string)$session['status']==='closed')throw new RuntimeException('انتقال در اقامتگاه ثبت شده اما حساب کافه بسته است و سند تسویه متناظر پیدا نشد؛ این مغایرت باید توسط مدیر بررسی شود.');
        if(!in_array((string)$session['status'],['active','followup'],true))throw new RuntimeException('حساب مرتبط در وضعیت قابل تکمیل تسویه نیست.');

        $invoice=settlement_calculate_session_invoice_locked($pdo,(int)$tr['session_id'],['active','followup']);
        if((int)$invoice['total']!==(int)$tr['amount'])throw new RuntimeException('مبلغ فعلی با مبلغ ثبت‌شده انتقال تطابق ندارد.');
        $snapshot=json_decode((string)$tr['invoice_snapshot_json'],true);
        if(!is_array($snapshot))throw new RuntimeException('اطلاعات ثبت‌شده فاکتور اقامتگاه معتبر نیست.');
        $invoiceNumber=trim((string)($snapshot['number']??''));
        if($invoiceNumber==='')throw new RuntimeException('شماره فاکتور اقامتگاه ثبت نشده است.');
        $final=settlement_finalize_locked($pdo,$invoice,'accommodation',$actorUserId,$printFinal,[
            'accommodation_transfer_id'=>$transferId,
            'financial_period_id'=>(int)$tr['financial_period_id'],
            'invoice_number'=>$invoiceNumber,
            'invoice_snapshot'=>$snapshot,
        ]);
        $pdo->commit();
        accommodation_audit('accommodation.local_settlement_finalized','accommodation_transfer',$transferId,['settlement_id'=>(int)$final['settlement_id'],'session_id'=>(int)$tr['session_id']],$actorUserId);
        return ['already_settled'=>false]+$final;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function accommodation_attempt_charge(int $transferId,int $actorUserId,?bool $printFinal=null): array
{
    $pdo=db();
    $pdo->beginTransaction();
    try {
        $st=$pdo->prepare('SELECT * FROM accommodation_transfers WHERE id=? FOR UPDATE');
        $st->execute([$transferId]);
        $tr=$st->fetch();
        if(!$tr) throw new RuntimeException('انتقال پیدا نشد.');
        $effectivePrintFinal=$printFinal??bool_from_mixed($tr['print_final_requested']??false);
        if($printFinal===true && (int)($tr['print_final_requested']??0)!==1){
            $pdo->prepare('UPDATE accommodation_transfers SET print_final_requested=1 WHERE id=?')->execute([$transferId]);
            $tr['print_final_requested']=1;
            $effectivePrintFinal=true;
        }
        if($tr['status']==='posted'){
            $pdo->commit();
            $checkout=accommodation_finalize_local_checkout($transferId,$actorUserId,$effectivePrintFinal);
            return ['success'=>true,'posted'=>true,'idempotent'=>true,'message'=>'انتقال اقامتگاه قبلاً ثبت شده بود؛ تسویه محلی کافه تکمیل شد.','transfer'=>accommodation_transfer_by_id($transferId)??$tr,'checkout'=>$checkout];
        }
        if(in_array((string)$tr['status'],['voided','void_pending','void_failed'],true)) throw new RuntimeException('این انتقال در فرایند برگشت است.');
        accommodation_require_live_operations();
        if((string)($tr['last_error_code']??'')==='external_order_conflict') throw new RuntimeException(accommodation_public_error_message('external_order_conflict',''));

        $snapshot=json_decode((string)($tr['invoice_snapshot_json']??''),true);
        if(!is_array($snapshot)) throw new RuntimeException('اطلاعات ثبت‌شده فاکتور اقامتگاه معتبر نیست.');
        $normalized=accommodation_normalize_invoice_snapshot($snapshot);
        accommodation_validate_invoice_snapshot($normalized);
        $normalizedJson=accommodation_canonical_json($normalized);
        $currentJson=accommodation_canonical_json($snapshot);
        $payloadHash=hash('sha256',accommodation_canonical_json([
            'external_order_id'=>(string)$tr['external_order_id'],
            'reservation_code'=>(string)$tr['reservation_code'],
            'amount'=>(int)$tr['amount'],
            'currency'=>'TOMAN',
            'invoice'=>$normalized,
        ]));
        $canNormalize=(string)$tr['status']==='failed'
            && in_array((string)($tr['last_error_code']??''),accommodation_contract_error_codes(),true)
            && (int)($tr['suspicious_response']??0)===0;
        if($currentJson!==$normalizedJson && !$canNormalize){
            throw new RuntimeException('اطلاعات ثبت‌شده این انتقال به‌دلیل نتیجه نامشخص یا وضعیت فعلی قابل بازنویسی نیست.');
        }
        if($canNormalize && ($currentJson!==$normalizedJson || !hash_equals((string)$tr['payload_hash'],$payloadHash))){
            accommodation_audit('accommodation_contract_snapshot_normalized','accommodation_transfer',$transferId,[
                'previous_error_code'=>(string)($tr['last_error_code']??''),
            ],$actorUserId);
        }
        $pdo->prepare("UPDATE accommodation_transfers SET invoice_snapshot_json=?,payload_hash=?,status='pending',attempt_count=attempt_count+1,last_attempt_at=NOW(),last_error_code=NULL,last_error=NULL WHERE id=?")
            ->execute([$normalizedJson,$payloadHash,$transferId]);
        $pdo->commit();
    } catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        throw $e;
    }

    $tr=accommodation_transfer_by_id($transferId);
    $r=accommodation_charge_remote($tr??[]);
    if($r['success']){
        $pdo->prepare("UPDATE accommodation_transfers SET status='posted',remote_transaction_id=?,posted_at=COALESCE(posted_at,NOW()),last_error_code=NULL,last_error=NULL,suspicious_response=0 WHERE id=?")
            ->execute([(string)$r['transaction_id'],$transferId]);
        accommodation_update_connection_state(true);
        $checkout=accommodation_finalize_local_checkout($transferId,$actorUserId,$effectivePrintFinal);
        accommodation_audit('accommodation_charge_posted','accommodation_transfer',$transferId,[
            'idempotent'=>bool_from_mixed($r['idempotent']??false),
            'transaction_id'=>$r['transaction_id'],
            'tracking_id'=>(string)($r['tracking_id']??''),
        ],$actorUserId);
        return ['success'=>true,'posted'=>true,'idempotent'=>bool_from_mixed($r['idempotent']??false),'tracking_id'=>(string)($r['tracking_id']??''),'message'=>'هزینه در حساب اقامتگاه ثبت و میز تسویه شد.'.(!empty($checkout['print_warning'])?' '.$checkout['print_warning']:''),'transfer'=>accommodation_transfer_by_id($transferId),'checkout'=>$checkout];
    }

    $amb=bool_from_mixed($r['ambiguous']??false);
    $code=text_substr((string)($r['code']??'remote_error'),0,80);
    $trackingId=text_substr((string)($r['tracking_id']??''),0,120);
    $publicMessage=accommodation_public_error_with_tracking($code,(string)($r['message']??''),$trackingId);
    $storedMessage=text_substr($publicMessage,0,500);
    $pdo->prepare('UPDATE accommodation_transfers SET status=?,last_error_code=?,last_error=?,suspicious_response=? WHERE id=?')
        ->execute([$amb?'pending':'failed',$code,$storedMessage,$amb?1:0,$transferId]);
    accommodation_update_connection_state(accommodation_result_connection_healthy($r),$storedMessage);
    accommodation_audit('accommodation_charge_failed','accommodation_transfer',$transferId,[
        'code'=>$code,
        'ambiguous'=>$amb,
        'remote_message'=>text_substr((string)($r['message']??''),0,500),
        'tracking_id'=>$trackingId,
        'outcome'=>(string)($r['outcome']??($amb?'ambiguous_failure':'deterministic_failure')),
    ],$actorUserId);
    return [
        'success'=>false,
        'posted'=>false,
        'ambiguous'=>$amb,
        'code'=>$code,
        'message'=>$amb?accommodation_public_error_with_tracking($code,'نتیجه انتقال نامشخص است؛ حساب قفل می‌ماند و فقط همین انتقال با همان شناسه قابل پیگیری است.',$trackingId):$publicMessage,
        'tracking_id'=>$trackingId,
        'outcome'=>(string)($r['outcome']??($amb?'ambiguous_failure':'deterministic_failure')),
        'retryable'=>bool_from_mixed($r['retryable']??false),
        'transfer'=>accommodation_transfer_by_id($transferId),
    ];
}
function accommodation_detach_to_followup(int $transferId,int $actorUserId): array
{
    $pdo=db();
    $probe=$pdo->prepare('SELECT at.session_id,ts.table_id FROM accommodation_transfers at JOIN table_sessions ts ON ts.id=at.session_id WHERE at.id=? LIMIT 1');
    $probe->execute([$transferId]);
    $ids=$probe->fetch();
    if(!$ids)throw new RuntimeException('انتقال اقامتگاه پیدا نشد.');
    $pdo->beginTransaction();
    try{
        $tableLock=$pdo->prepare('SELECT id FROM cafe_tables WHERE id=? FOR UPDATE');$tableLock->execute([(int)$ids['table_id']]);
        $sessionLock=$pdo->prepare('SELECT * FROM table_sessions WHERE id=? FOR UPDATE');$sessionLock->execute([(int)$ids['session_id']]);$session=$sessionLock->fetch();
        $transferLock=$pdo->prepare('SELECT * FROM accommodation_transfers WHERE id=? FOR UPDATE');$transferLock->execute([$transferId]);$transfer=$transferLock->fetch();
        if(!$session||!$transfer)throw new RuntimeException('حساب یا انتقال اقامتگاه پیدا نشد.');
        $transfer['session_status']=$session['status'];
        if(!accommodation_transfer_is_ambiguous($transfer))throw new RuntimeException('فقط انتقالی با نتیجه نامشخص قابل انتقال به پیگیری است.');
        if(!in_array((string)$transfer['status'],['pending','failed'],true)||!empty($transfer['resolved_at']))throw new RuntimeException('این انتقال دیگر نیازمند پیگیری باز نیست.');
        if((string)$session['status']==='followup'){$pdo->commit();return ['success'=>true,'idempotent'=>true,'transfer'=>accommodation_transfer_by_id($transferId)];}
        if((string)$session['status']!=='active')throw new RuntimeException('این حساب در وضعیت قابل آزادسازی نیست.');
        $pending=$pdo->prepare("SELECT COUNT(*) FROM orders WHERE session_id=? AND status IN('pending_approval','new')");$pending->execute([(int)$session['id']]);
        if((int)$pending->fetchColumn()>0)throw new RuntimeException('ابتدا سفارش‌های تازه این میز را تأیید یا رد کن.');
        $detach=$pdo->prepare("UPDATE table_sessions SET status='followup',live_table_guard=NULL,ended_at=NOW(),ended_reason='accommodation_followup',closed_by_user_id=? WHERE id=? AND status='active'");
        $detach->execute([$actorUserId,(int)$session['id']]);
        if($detach->rowCount()!==1)throw new RuntimeException('آزادسازی میز هم‌زمان با تغییر دیگری مواجه شد؛ دوباره وضعیت را بررسی کن.');
        $pdo->prepare("UPDATE waiter_calls SET status='cancelled',active_table_guard=NULL,cancelled_at=NOW(),cancel_reason='account_moved_to_followup',cancelled_by_user_id=? WHERE status IN('new','accepted') AND session_id=?")->execute([$actorUserId,(int)$session['id']]);
        accommodation_audit('accommodation.account_moved_to_followup','accommodation_transfer',$transferId,['session_id'=>(int)$session['id'],'table_id'=>(int)$session['table_id'],'external_order_id'=>(string)$transfer['external_order_id']],$actorUserId);
        $pdo->commit();
        return ['success'=>true,'idempotent'=>false,'transfer'=>accommodation_transfer_by_id($transferId)];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function accommodation_attempt_void(int $transferId,int $actorUserId,string $reason='ابطال تسویه توسط مدیر'): array
{
    $reason=text_substr(trim($reason),0,300);
    if($reason==='')throw new RuntimeException('دلیل برگشت را ثبت کن.');
    $pdo=db();$pdo->beginTransaction();
    try{
        $st=$pdo->prepare('SELECT * FROM accommodation_transfers WHERE id=? FOR UPDATE');
        $st->execute([$transferId]);
        $tr=$st->fetch();
        if(!$tr)throw new RuntimeException('انتقال پیدا نشد.');
        if($tr['status']==='voided'){
            $pdo->commit();
            return ['success'=>true,'voided'=>true,'idempotent'=>true,'message'=>'برگشت اقامتگاه قبلاً ثبت شده است.','transfer'=>accommodation_transfer_by_id($transferId)??$tr];
        }
        if(!in_array($tr['status'],['posted','void_pending','void_failed'],true))throw new RuntimeException('فقط هزینه ثبت‌شده قابل برگشت است.');
        accommodation_require_live_operations();
        $pdo->prepare("UPDATE accommodation_transfers SET status='void_pending',void_requested_by_user_id=?,attempt_count=attempt_count+1,last_attempt_at=NOW(),last_error_code=NULL,last_error=NULL,suspicious_response=0 WHERE id=?")
            ->execute([$actorUserId,$transferId]);
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}

    $tr=accommodation_transfer_by_id($transferId);
    $r=accommodation_void_remote($tr??[],$reason);
    if($r['success']){
        $pdo->prepare("UPDATE accommodation_transfers SET status='voided',remote_void_transaction_id=?,remote_original_transaction_id=?,voided_by_user_id=?,voided_at=COALESCE(voided_at,NOW()),last_error_code=NULL,last_error=NULL,suspicious_response=0 WHERE id=?")
            ->execute([(string)$r['transaction_id'],(string)$r['original_transaction_id'],$actorUserId,$transferId]);
        accommodation_update_connection_state(true);
        accommodation_audit('accommodation.void_posted','accommodation_transfer',$transferId,[
            'idempotent'=>bool_from_mixed($r['idempotent']??false),
            'transaction_id'=>(string)$r['transaction_id'],
            'tracking_id'=>(string)($r['tracking_id']??''),
            'reason'=>$reason,
        ],$actorUserId);
        return ['success'=>true,'voided'=>true,'idempotent'=>bool_from_mixed($r['idempotent']??false),'tracking_id'=>(string)($r['tracking_id']??''),'message'=>'برگشت هزینه در اقامتگاه ثبت شد.','transfer'=>accommodation_transfer_by_id($transferId)];
    }
    $amb=bool_from_mixed($r['ambiguous']??false);
    $code=(string)($r['code']??'remote_error');$trackingId=text_substr((string)($r['tracking_id']??''),0,120);
    $msg=accommodation_public_error_with_tracking($code,(string)($r['message']??''),$trackingId);
    $nextStatus=$amb?'void_pending':'void_failed';
    $pdo->prepare("UPDATE accommodation_transfers SET status=?,last_error_code=?,last_error=?,suspicious_response=? WHERE id=?")
        ->execute([$nextStatus,$code,$msg,$amb?1:0,$transferId]);
    accommodation_update_connection_state(accommodation_result_connection_healthy($r),$msg);
    accommodation_audit('accommodation.void_failed','accommodation_transfer',$transferId,['code'=>$code,'ambiguous'=>$amb,'tracking_id'=>$trackingId,'outcome'=>(string)($r['outcome']??($amb?'ambiguous_failure':'deterministic_failure'))],$actorUserId);
    return ['success'=>false,'voided'=>false,'ambiguous'=>$amb,'code'=>$code,'tracking_id'=>$trackingId,'outcome'=>(string)($r['outcome']??($amb?'ambiguous_failure':'deterministic_failure')),'retryable'=>bool_from_mixed($r['retryable']??false),'message'=>$amb?accommodation_public_error_with_tracking($code,'نتیجه برگشت نامشخص است؛ با همان شناسه دوباره تلاش کنید.',$trackingId):$msg,'transfer'=>accommodation_transfer_by_id($transferId)];
}
function accommodation_finalize_local_reversal(int $transferId,int $actorUserId,int $targetTableId,string $reason): array
{
    $reason=text_substr(trim($reason),0,300);
    if($reason==='')throw new RuntimeException('دلیل تکمیل سند برگشتی را وارد کن.');
    if($targetTableId<1)throw new RuntimeException('میز مقصد را انتخاب کن.');
    $pdo=db();$pdo->beginTransaction();
    try{
        $transferStmt=$pdo->prepare('SELECT * FROM accommodation_transfers WHERE id=? FOR UPDATE');
        $transferStmt->execute([$transferId]);
        $transfer=$transferStmt->fetch();
        if(!$transfer)throw new RuntimeException('انتقال اقامتگاه پیدا نشد.');
        if((string)$transfer['status']!=='voided')throw new RuntimeException('برگشت اقامتگاه هنوز قطعی نشده است.');

        $settlementStmt=$pdo->prepare("SELECT * FROM settlement_records WHERE accommodation_transfer_id=? AND status='completed' ORDER BY id DESC LIMIT 1 FOR UPDATE");
        $settlementStmt->execute([$transferId]);
        $record=$settlementStmt->fetch();
        if(!$record){
            $pdo->commit();
            return ['success'=>true,'idempotent'=>true,'no_local_settlement'=>true,'message'=>'برای این انتقال سند تسویه محلی وجود ندارد و اقدام دیگری لازم نیست.'];
        }
        $existing=$pdo->prepare("SELECT id,invoice_number FROM settlement_records WHERE reverses_settlement_id=? AND status='reversal' LIMIT 1 FOR UPDATE");
        $existing->execute([(int)$record['id']]);
        if($reversal=$existing->fetch()){
            $pdo->commit();
            return ['success'=>true,'idempotent'=>true,'reversal_settlement_id'=>(int)$reversal['id'],'reversal_invoice_number'=>(string)$reversal['invoice_number'],'message'=>'سند برگشتی کافه قبلاً تکمیل شده است.'];
        }
        $result=settlement_reopen_locked($pdo,$record,$targetTableId,$reason,$actorUserId);
        $pdo->commit();
        accommodation_audit('accommodation.local_reversal_finalized','accommodation_transfer',$transferId,[
            'original_settlement_id'=>(int)$record['id'],
            'reversal_settlement_id'=>(int)($result['reversal_settlement_id']??0),
            'target_table_id'=>$targetTableId,
        ],$actorUserId);
        return ['success'=>true,'message'=>'سند برگشتی کافه تکمیل و حساب برای ادامه کار باز شد.']+$result;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function accommodation_resolve_failed_for_alternate_settlement_locked(PDO $pdo,int $sessionId,int $actorUserId,string $method): void
{
    $st=$pdo->prepare('SELECT * FROM accommodation_transfers WHERE session_id=? LIMIT 1 FOR UPDATE');
    $st->execute([$sessionId]);
    $transfer=$st->fetch();
    if(!$transfer||!empty($transfer['resolved_at']))return;
    $status=(string)$transfer['status'];
    if(in_array($status,['posted','void_pending','void_failed','voided'],true))throw new RuntimeException('این حساب به اقامتگاه متصل است؛ ابتدا وضعیت انتقال یا برگشت را بررسی کن.');
    if($status==='pending'||accommodation_transfer_is_ambiguous($transfer))throw new RuntimeException('نتیجه انتقال اقامت هنوز نامشخص است؛ برای جلوگیری از دریافت دوباره، ابتدا همان انتقال را پیگیری کن.');
    if($status!=='failed')return;
    $method=text_substr(trim($method),0,40);
    $pdo->prepare('UPDATE accommodation_transfers SET resolved_at=NOW(),resolved_by_user_id=?,resolution_method=? WHERE id=? AND resolved_at IS NULL')
        ->execute([$actorUserId,$method,(int)$transfer['id']]);
    accommodation_audit('accommodation_failed_resolved_by_alternate_settlement','accommodation_transfer',(int)$transfer['id'],['session_id'=>$sessionId,'resolution_method'=>$method],$actorUserId);
}
function accommodation_transfer_blocks_invoice_edit(int $sessionId): bool{if($sessionId<1)return false;try{$st=db()->prepare("SELECT 1 FROM accommodation_transfers WHERE session_id=? AND status IN('pending','posted','void_pending','void_failed') AND resolved_at IS NULL LIMIT 1");$st->execute([$sessionId]);return(bool)$st->fetchColumn();}catch(Throwable $e){error_log('accommodation invoice lock check: '.$e->getMessage());return true;}}
function accommodation_setting_write(string $key,string $value): void{$st=db()->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');$st->execute([$key,$value]);}
function accommodation_save_connection_settings(bool $enabled,string $baseUrl,string $newApiKey,int $actorUserId): void{$baseUrl=trim($baseUrl);if($baseUrl!=='')$baseUrl=accommodation_validate_base_url($baseUrl);if($enabled&&$baseUrl==='')throw new RuntimeException('آدرس اتصال اقامتگاه را وارد کن.');if(trim($newApiKey)!=='')accommodation_save_api_key($newApiKey);if($enabled&&accommodation_api_key()==='')throw new RuntimeException('کلید اتصال اقامتگاه را ثبت کن.');accommodation_setting_write('accommodation_connection_enabled',$enabled?'1':'0');accommodation_setting_write('accommodation_api_base_url',$baseUrl);accommodation_audit('accommodation.settings_updated','settings','accommodation_connection',['enabled'=>$enabled,'base_host'=>$baseUrl!==''?(parse_url($baseUrl,PHP_URL_HOST)?:''):'','api_key_replaced'=>trim($newApiKey)!==''],$actorUserId);}
function accommodation_test_connection(int $actorUserId): array{if(!accommodation_live_operations_enabled())throw new RuntimeException('ابتدا اتصال را فعال و ذخیره کن.');$r=accommodation_result_from_response(accommodation_http_request('capabilities','GET',[],5),'capabilities');if(!$r['success']){$msg=accommodation_public_error_message((string)$r['code'],(string)$r['message']);accommodation_update_connection_state(false,$msg);throw new RuntimeException($msg);}$p=$r['payload'];$caps=$p['capabilities']??[];$ok=(string)($p['api_version']??'')==='2.0'&&($caps['unified_search']??false)===true&&($caps['invoice_snapshot']??false)===true&&($caps['idempotent_charge']??false)===true&&($caps['charge_void']??false)===true;if(!$ok)throw new RuntimeException('API اقامتگاه نسخه ۲.۰ یا قابلیت‌های الزامی را اعلام نکرد.');accommodation_update_connection_state(true);accommodation_audit('accommodation.connection_test_succeeded','settings','accommodation_connection',['api_version'=>'2.0'],$actorUserId);return ['success'=>true,'api_version'=>'2.0','capabilities'=>$caps];}
