<?php
declare(strict_types=1);

function print_agent_api_bearer_token(): string
{
    $header = trim((string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    if ($header === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp((string)$name, 'Authorization') === 0) {
                $header = trim((string)$value);
                break;
            }
        }
    }
    return preg_match('/^Bearer\s+(.+)$/i', $header, $m) ? trim($m[1]) : '';
}

function print_agent_api_auth(PDO $pdo): array
{
    $token = print_agent_api_bearer_token();
    if ($token === '') json_response(['success'=>false,'code'=>'unauthorized','message'=>'کلید Agent ارسال نشده است.'],401);
    $hash = hash('sha256',$token);
    $stmt=$pdo->prepare('SELECT * FROM print_agents WHERE token_hash=? AND active=1 LIMIT 1');
    $stmt->execute([$hash]);
    $agent=$stmt->fetch();
    if(!$agent) json_response(['success'=>false,'code'=>'unauthorized','message'=>'کلید Agent معتبر نیست.'],401);
    return $agent;
}

function print_agent_api_request(int $maxBytes = 262144): array
{
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
        header('Allow: POST');
        json_response(['success'=>false,'code'=>'method_not_allowed','message'=>'فقط POST مجاز است.'],405);
    }
    $contentType = strtolower(trim(explode(';',(string)($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
    if ($contentType !== 'application/json') json_response(['success'=>false,'code'=>'unsupported_media_type','message'=>'Content-Type باید application/json باشد.'],415);
    $stream=fopen('php://input','rb');
    if($stream===false)json_response(['success'=>false,'code'=>'invalid_body','message'=>'بدنه درخواست خوانده نشد.'],400);
    $raw='';
    while(!feof($stream) && strlen($raw)<=$maxBytes){$chunk=fread($stream,min(8192,$maxBytes+1-strlen($raw)));if($chunk===false)break;$raw.=$chunk;}
    fclose($stream);
    if(strlen($raw)>$maxBytes)json_response(['success'=>false,'code'=>'payload_too_large','message'=>'بدنه درخواست بیش از حد مجاز است.'],413);
    if(trim($raw)==='' || !str_starts_with(ltrim($raw),'{'))json_response(['success'=>false,'code'=>'invalid_json_object','message'=>'بدنه باید یک JSON object باشد.'],400);
    try{$data=json_decode($raw,true,128,JSON_THROW_ON_ERROR);}catch(JsonException){json_response(['success'=>false,'code'=>'invalid_json','message'=>'JSON درخواست معتبر نیست.'],400);}
    if(!is_array($data)||array_is_list($data))json_response(['success'=>false,'code'=>'invalid_json_object','message'=>'بدنه باید یک JSON object باشد.'],400);
    return $data;
}

function print_agent_api_request_id(array $data): string
{
    $raw=array_key_exists('request_id',$data)?$data['request_id']:($_SERVER['HTTP_X_REQUEST_ID']??'');
    if(!is_string($raw))json_response(['success'=>false,'code'=>'invalid_field_type','field'=>'request_id','message'=>'نوع request_id باید رشته باشد.'],422);
    $id=trim($raw);
    if($id==='' || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{7,79}$/',$id)) {
        json_response(['success'=>false,'code'=>'request_id_required','message'=>'شناسه یکتای درخواست معتبر نیست.'],422);
    }
    return $id;
}

function print_agent_api_https_required(): void
{
    global $config;
    $trustProxy=(bool)($config['app']['trust_proxy_headers']??false);
    $https=strtolower((string)($_SERVER['HTTPS']??''));
    $secure=$https==='on'||$https==='1'||(int)($_SERVER['SERVER_PORT']??0)===443;
    if($trustProxy && isset($_SERVER['HTTP_X_FORWARDED_PROTO'])){
        $forwarded=strtolower(trim(explode(',',(string)$_SERVER['HTTP_X_FORWARDED_PROTO'])[0]??''));
        if(in_array($forwarded,['http','https'],true))$secure=$forwarded==='https';
    }
    if($secure)return;
    $hostHeader=trim((string)($_SERVER['HTTP_HOST']??$_SERVER['SERVER_NAME']??''));
    $host=$hostHeader!==''?parse_url('http://'.$hostHeader,PHP_URL_HOST):null;$host=is_string($host)?strtolower(trim($host,'[]')):'';
    if(in_array($host,['localhost','127.0.0.1','::1'],true))return;
    json_response(['success'=>false,'code'=>'https_required','message'=>'Print API فقط روی HTTPS مجاز است.'],403);
}

function print_agent_api_safe_text(mixed $value,int $max): string
{
    return text_substr(trim((string)$value),0,$max);
}

/**
 * Durable local receipt contract shared by supported Windows Agents (6.0+).
 * Agent source: `r-` + GUID in N format (32 hexadecimal characters).
 */
function print_agent_api_local_receipt_id_valid(mixed $value): bool
{
    $receipt = trim((string)$value);
    return preg_match('/^r-[A-Fa-f0-9]{32}$/', $receipt) === 1;
}

function print_agent_api_content_sha256_valid(mixed $value): bool
{
    $hash = trim((string)$value);
    return preg_match('/^[A-Fa-f0-9]{64}$/', $hash) === 1;
}


function print_agent_api_string_field(array $data,string $key,int $max,bool $required=true): string
{
    if(!array_key_exists($key,$data)){
        if($required)json_response(['success'=>false,'code'=>'missing_field','field'=>$key,'message'=>'فیلد لازم ارسال نشده است.'],422);
        return '';
    }
    if(!is_string($data[$key]))json_response(['success'=>false,'code'=>'invalid_field_type','field'=>$key,'message'=>'نوع فیلد معتبر نیست.'],422);
    $value=trim($data[$key]);
    if(strlen($value)>$max)json_response(['success'=>false,'code'=>'field_too_long','field'=>$key,'message'=>'طول فیلد بیش از حد مجاز است.'],422);
    if($required&&$value==='')json_response(['success'=>false,'code'=>'missing_field','field'=>$key,'message'=>'فیلد لازم خالی است.'],422);
    return $value;
}

function print_agent_api_int_field(array $data,string $key,int $min,int $max,bool $required=true): ?int
{
    if(!array_key_exists($key,$data)){if($required)json_response(['success'=>false,'code'=>'missing_field','field'=>$key,'message'=>'فیلد لازم ارسال نشده است.'],422);return null;}
    if(!is_int($data[$key]))json_response(['success'=>false,'code'=>'invalid_field_type','field'=>$key,'message'=>'نوع فیلد معتبر نیست.'],422);
    $value=$data[$key];if($value<$min||$value>$max)json_response(['success'=>false,'code'=>'invalid_field_value','field'=>$key,'message'=>'مقدار فیلد خارج از محدوده است.'],422);return $value;
}

function print_agent_api_bool_field(array $data,string $key,bool $default=false,bool $required=false): bool
{
    if(!array_key_exists($key,$data)){
        if($required)json_response(['success'=>false,'code'=>'missing_field','field'=>$key,'message'=>'فیلد لازم ارسال نشده است.'],422);
        return $default;
    }
    if(!is_bool($data[$key]))json_response(['success'=>false,'code'=>'invalid_field_type','field'=>$key,'message'=>'نوع فیلد باید boolean باشد.'],422);
    return $data[$key];
}


/** Heartbeat-only optional evidence: absent and explicit JSON null both mean no evidence. */
function print_agent_api_optional_string_field(array $data,string $key,int $max): ?string
{
    if(!array_key_exists($key,$data)||$data[$key]===null)return null;
    if(!is_string($data[$key]))json_response(['success'=>false,'code'=>'invalid_field_type','field'=>$key,'message'=>'نوع فیلد معتبر نیست.'],422);
    $value=trim($data[$key]);
    if(strlen($value)>$max)json_response(['success'=>false,'code'=>'field_too_long','field'=>$key,'message'=>'طول فیلد بیش از حد مجاز است.'],422);
    return $value===''?null:$value;
}

function print_agent_api_optional_int_field(array $data,string $key,int $min,int $max): ?int
{
    if(!array_key_exists($key,$data)||$data[$key]===null)return null;
    if(!is_int($data[$key]))json_response(['success'=>false,'code'=>'invalid_field_type','field'=>$key,'message'=>'نوع فیلد معتبر نیست.'],422);
    $value=$data[$key];
    if($value<$min||$value>$max)json_response(['success'=>false,'code'=>'invalid_field_value','field'=>$key,'message'=>'مقدار فیلد خارج از محدوده است.'],422);
    return $value;
}

function print_agent_api_optional_bool_field(array $data,string $key): ?bool
{
    if(!array_key_exists($key,$data)||$data[$key]===null)return null;
    if(!is_bool($data[$key]))json_response(['success'=>false,'code'=>'invalid_field_type','field'=>$key,'message'=>'نوع فیلد باید boolean باشد.'],422);
    return $data[$key];
}
