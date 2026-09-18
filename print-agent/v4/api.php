<?php
declare(strict_types=1);
require dirname(__DIR__,2) . '/bootstrap.php';
require_once dirname(__DIR__,2) . '/includes/print_agent_api.php';

header('Cache-Control: no-store');
header('Content-Type: application/json; charset=utf-8');
print_agent_api_https_required();

const PRINT_V4_PROTOCOL = 4;
const PRINT_V4_LEASE_SECONDS = 45;
const PRINT_V4_MAX_ATTEMPTS = 5;
const PRINT_V4_EMPTY_CLAIM_RETENTION_SECONDS = 7 * 24 * 60 * 60;
const PRINT_V4_EMPTY_CLAIM_CLEANUP_BATCH = 2000;
const PRINT_V4_RECONCILIATION_MAX_ID_GAP = 10000;

function print_v4_require_version(array $data): string
{
    $protocol=print_agent_api_int_field($data,'protocol_version',PRINT_V4_PROTOCOL,PRINT_V4_PROTOCOL,true);
    $version=print_agent_api_string_field($data,'agent_version',40,true);
    if(!preg_match('/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$/',$version)){
        json_response(['success'=>false,'code'=>'invalid_agent_version','field'=>'agent_version','message'=>'نسخه Agent معتبر نیست.'],422);
    }
    if(version_compare($version,print_agent_minimum_version(),'<')){
        json_response(['success'=>false,'code'=>'agent_upgrade_required','message'=>'Print Agent v4 نیازمند نسخه '.print_agent_minimum_version().' یا جدیدتر است.','minimum_agent_version'=>print_agent_minimum_version()],426);
    }
    return $version;
}

function print_v4_optional_wire_time(array $data,string $key): string
{
    if(!array_key_exists($key,$data)||$data[$key]===null||$data[$key]==='')return '';
    $value=print_agent_api_string_field($data,$key,48,false);
    if($value==='')return '';
    if(preg_match('/(?:Z|[+\-]\d{2}:\d{2})$/i',$value)!==1){
        json_response(['success'=>false,'code'=>'timestamp_offset_required','field'=>$key,'message'=>'زمان باید timezone/offset صریح داشته باشد.'],422);
    }
    try{$parsed=new DateTimeImmutable($value);}catch(Throwable){json_response(['success'=>false,'code'=>'invalid_timestamp','field'=>$key,'message'=>'فرمت زمان معتبر نیست.'],422);}
    return $parsed->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
}

function print_v4_optional_int(array $data,string $key,int $min,int $max,int $default=0): int
{
    $value=print_agent_api_optional_int_field($data,$key,$min,$max);
    return $value===null?$default:$value;
}

function print_v4_agent_destinations(PDO $pdo,int $agentId): array
{
    $stmt=$pdo->prepare("SELECT d.destination_key,d.label,d.destination_type,d.preparation_areas_json,d.agent_id,d.windows_queue_name,d.fallback_agent_id,d.fallback_windows_queue_name,d.active,d.paper_width_mm,d.printable_width_mm,d.copies,d.layout_mode,pa.active primary_active,pa.retired_at primary_retired_at,pa.last_seen_at primary_last_seen_at,pa.last_heartbeat_at primary_last_heartbeat_at,pa.health_json primary_health_json,pa.printers_json primary_printers_json,fa.active fallback_active,fa.retired_at fallback_retired_at,fa.last_seen_at fallback_last_seen_at,fa.last_heartbeat_at fallback_last_heartbeat_at,fa.health_json fallback_health_json,fa.printers_json fallback_printers_json FROM print_destinations d LEFT JOIN print_agents pa ON pa.id=d.agent_id LEFT JOIN print_agents fa ON fa.id=d.fallback_agent_id WHERE d.agent_id=? OR d.fallback_agent_id=? ORDER BY d.destination_key");
    $stmt->execute([$agentId,$agentId]);
    $rows=[];
    foreach($stmt->fetchAll() as $row){
        $base=[
            'destination_key'=>(string)$row['destination_key'],
            'label'=>(string)$row['label'],
            'destination_type'=>(string)$row['destination_type'],
            'preparation_areas_json'=>$row['preparation_areas_json'],
            'active'=>(int)$row['active'],
            'paper_width_mm'=>(float)$row['paper_width_mm'],
            'printable_width_mm'=>(float)$row['printable_width_mm'],
            'copies'=>(int)$row['copies'],
            'layout_mode'=>(string)$row['layout_mode'],
        ];
        $primaryAgent=['active'=>(int)($row['primary_active']??0),'retired_at'=>$row['primary_retired_at']??null,'last_seen_at'=>$row['primary_last_seen_at']??null,'last_heartbeat_at'=>$row['primary_last_heartbeat_at']??null,'health_json'=>$row['primary_health_json']??'{}','printers_json'=>$row['primary_printers_json']??'[]'];
        $fallbackAgent=['active'=>(int)($row['fallback_active']??0),'retired_at'=>$row['fallback_retired_at']??null,'last_seen_at'=>$row['fallback_last_seen_at']??null,'last_heartbeat_at'=>$row['fallback_last_heartbeat_at']??null,'health_json'=>$row['fallback_health_json']??'{}','printers_json'=>$row['fallback_printers_json']??'[]'];
        $primaryRoute=$base+['windows_queue_name'=>(string)($row['windows_queue_name']??''),'route_role'=>'primary'];
        $fallbackRoute=$base+['windows_queue_name'=>(string)($row['fallback_windows_queue_name']??''),'route_role'=>'fallback'];
        $primaryReady=!empty($row['agent_id'])&&print_v4_destination_server_ready($primaryAgent,$primaryRoute);
        $fallbackReady=!empty($row['fallback_agent_id'])&&print_v4_destination_server_ready($fallbackAgent,$fallbackRoute);
        if((int)($row['agent_id']??0)===$agentId && $primaryReady){
            $rows[(string)$row['destination_key']]=$primaryRoute;
            continue;
        }
        if((int)($row['fallback_agent_id']??0)===$agentId && !$primaryReady && $fallbackReady){
            $rows[(string)$row['destination_key']]=$fallbackRoute;
        }
    }
    return $rows;
}

function print_v4_destination_server_ready(array $agent,array $destination,int $maxAgeSeconds=45): bool
{
    if((int)($destination['active']??0)!==1)return false;
    return print_agent_queue_ready($agent,(string)($destination['windows_queue_name']??''),$maxAgeSeconds);
}

function print_v4_destination_snapshot(array $destination): array
{
    return [
        'destination_key'=>(string)($destination['destination_key']??''),
        'label'=>(string)($destination['label']??''),
        'destination_type'=>(string)($destination['destination_type']??''),
        'preparation_areas_json'=>$destination['preparation_areas_json']??null,
        'windows_queue_name'=>(string)($destination['windows_queue_name']??''),
        'active'=>(int)($destination['active']??0),
        'paper_width_mm'=>(float)($destination['paper_width_mm']??80),
        'printable_width_mm'=>(float)($destination['printable_width_mm']??72),
        'copies'=>(int)($destination['copies']??1),
        'layout_mode'=>(string)($destination['layout_mode']??'combined'),
    ];
}

function print_v4_attempt_destination(array $row,array $fallback): array
{
    $raw=(string)($row['destination_snapshot_json']??'');
    if($raw!==''){
        try{
            $decoded=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
            if(is_array($decoded)&&trim((string)($decoded['destination_key']??''))!=='')return $decoded;
        }catch(Throwable){ }
    }
    // A claimed Attempt must never silently inherit a destination that changed after Claim.
    // Missing/corrupt snapshot is an operational fault; fail closed rather than risk misrouting a receipt.
    throw new RuntimeException('Print Attempt destination snapshot is missing or invalid.');
}

function print_v4_terminal_attempt_state(string $state): bool
{
    return in_array($state,['submitted','failed','unknown','recovery_hold','cancelled','expired'],true);
}

function print_v4_request_column(string $action): string
{
    return match($action){
        'accept'=>'accept_request_id',
        'renew'=>'renew_request_id',
        'start'=>'start_request_id',
        'report'=>'report_request_id',
        default=>throw new InvalidArgumentException('Unsupported Print API mutation.'),
    };
}

function print_v4_assert_request_id_not_reused(PDO $pdo,int $agentId,string $action,string $requestId,int $attemptId): void
{
    $column=print_v4_request_column($action);
    $stmt=$pdo->prepare("SELECT id FROM print_attempts WHERE agent_id=? AND $column=? AND id<>? LIMIT 1");
    $stmt->execute([$agentId,$requestId,$attemptId]);
    if($stmt->fetchColumn()!==false){
        $pdo->rollBack();
        json_response(['success'=>false,'code'=>'request_id_conflict','message'=>'request_id قبلاً برای Attempt دیگری استفاده شده است.'],409);
    }
}

function print_v4_request_hash(string $action,array $data): string
{
    $keys=match($action){
        'claim'=>['request_id','agent_version','protocol_version','limit','ready_destination_keys'],
        'accept'=>['request_id','agent_version','protocol_version','attempt_id','lease_token','local_receipt_id','content_sha256'],
        'renew'=>['request_id','agent_version','protocol_version','attempt_id','lease_token'],
        'start'=>['request_id','agent_version','protocol_version','attempt_id','lease_token'],
        'report'=>['request_id','agent_version','protocol_version','attempt_id','lease_token','local_receipt_id','status','spooler_job_id','retryable','error_code','error_message'],
        default=>throw new InvalidArgumentException('Unsupported request fingerprint action.'),
    };
    $body=[];
    foreach($keys as $key){
        if(!array_key_exists($key,$data)){ $body[$key]=null; continue; }
        $value=$data[$key];
        if($key==='ready_destination_keys'&&is_array($value)){
            $value=array_values(array_map(static fn($v)=>is_string($v)?trim($v):$v,$value));
        }elseif(in_array($key,['request_id','agent_version','lease_token','local_receipt_id','content_sha256','status','spooler_job_id','error_code','error_message'],true)&&is_string($value)){
            $value=trim($value);
            if($key==='content_sha256')$value=strtolower($value);
        }elseif($key==='retryable'){
            $value=bool_from_mixed($value);
        }
        $body[$key]=$value;
    }
    return hash('sha256',json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
}

function print_v4_request_hash_column(string $action): string
{
    return match($action){
        'accept'=>'accept_request_hash','renew'=>'renew_request_hash','start'=>'start_request_hash','report'=>'report_request_hash',
        default=>throw new InvalidArgumentException('Unsupported Print API request hash column.'),
    };
}

function print_v4_request_body_matches(array $attempt,string $action,string $requestId,string $requestHash): bool
{
    $idColumn=print_v4_request_column($action);$hashColumn=print_v4_request_hash_column($action);
    if((string)($attempt[$idColumn]??'')!==$requestId)return true;
    $stored=trim((string)($attempt[$hashColumn]??''));
    // dev.19 did not persist body fingerprints. Legacy-null rows keep their old state/binding
    // checks and become protected once a mutation is written by dev.20.
    return $stored==='' || hash_equals($stored,$requestHash);
}

function print_v4_db_transient(Throwable $e): bool
{
    if(!$e instanceof PDOException)return false;
    $info=$e->errorInfo??[];$driver=(int)($info[1]??0);
    return in_array($driver,[1205,1213,2006,2013],true) || in_array((string)$e->getCode(),['40001','08S01'],true);
}

function print_v4_probe_payload(PDO $pdo,array $agent): array
{
    return [
        'success'=>true,
        'protocol_version'=>PRINT_V4_PROTOCOL,
        'minimum_agent_version'=>print_agent_minimum_version(),
        'recommended_agent_version'=>print_agent_recommended_version(),
        'server_time'=>print_v4_server_time(),
        'agent'=>['id'=>(int)$agent['id'],'name'=>(string)$agent['name']],
        'destinations'=>array_values(print_v4_agent_destinations($pdo,(int)$agent['id'])),
        'capabilities'=>['durable_claim','durable_claim_snapshot_v1','claim_conflict_rekey_v1','durable_accept','attempt_status','submission_fence','attempt_history','unknown_resolution','content_sha256','manual_failover','retry_cycles','utc_wire_time','local_wake_v1','preview_bridge_v1'],
        'capability_semantics'=>'protocol_support_only',
        'server_instance_id'=>hash_hmac('sha256','sokna-print-server-instance-v1',(string)($GLOBALS['config']['app']['key']??'')),
    ];
}

function print_v4_token_hash(string $token): string{return hash('sha256',$token);}

function print_v4_lease_secret(): string
{
    global $config;
    $key=trim((string)($config['app']['key']??''));
    if($key==='')throw new RuntimeException('کلید داخلی سامانه برای Print API v4 تنظیم نشده است.');
    return hash('sha256','sokna-print-v4-lease|'.$key,true);
}
function print_v4_lease_expiry(): string{return date('Y-m-d H:i:s',time()+PRINT_V4_LEASE_SECONDS);}
function print_v4_wire_time(?string $databaseTime): ?string{return print_database_time_to_utc($databaseTime);}
function print_v4_server_time(): string{return (new DateTimeImmutable('now',new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z');}

function print_v4_sanitize_printers(array $printers): array
{
    if(count($printers)>100)json_response(['success'=>false,'code'=>'field_too_many_items','field'=>'printers','message'=>'تعداد پرینترهای heartbeat بیش از حد مجاز است.'],422);
    $out=[];
    foreach($printers as $index=>$p){
        if(!is_array($p)||array_is_list($p))json_response(['success'=>false,'code'=>'invalid_field_type','field'=>'printers.'.$index,'message'=>'هر پرینتر باید JSON object باشد.'],422);
        $name=print_agent_api_string_field($p,'name',190,true);
        $out[]=[
            'name'=>$name,
            'default'=>print_agent_api_bool_field($p,'default',false,false),
            'network'=>print_agent_api_bool_field($p,'network',false,false),
            'offline'=>print_agent_api_bool_field($p,'offline',false,false),
            'paused'=>print_agent_api_bool_field($p,'paused',false,false),
            'paper_out'=>print_agent_api_bool_field($p,'paper_out',false,false),
            'error'=>print_agent_api_bool_field($p,'error',false,false),
            'status'=>print_agent_api_string_field($p,'status',80,false),
            'jobs'=>print_v4_optional_int($p,'jobs',0,1000000),
            'driver'=>print_agent_api_string_field($p,'driver',190,false),
            'port'=>print_agent_api_string_field($p,'port',190,false),
        ];
    }
    return $out;
}

function print_v4_cleanup_empty_claim_requests(PDO $pdo): void
{
    // Maintenance is deliberately outside the Claim transaction: cleanup failure must never block printing.
    try{
        $cutoff=date('Y-m-d H:i:s',time()-PRINT_V4_EMPTY_CLAIM_RETENTION_SECONDS);
        $sql="DELETE FROM print_claim_requests WHERE created_at<? AND attempt_ids_json IS NOT NULL AND JSON_LENGTH(attempt_ids_json)=0 ORDER BY id LIMIT ".PRINT_V4_EMPTY_CLAIM_CLEANUP_BATCH;
        $pdo->prepare($sql)->execute([$cutoff]);
    }catch(Throwable){
        // Best-effort bounded maintenance. The primary Claim path remains available on cleanup failure.
    }
}

function print_v4_expire_reservations(PDO $pdo): void
{
    $stmt=$pdo->query("SELECT a.id,a.job_id,a.attempt_no,a.cycle_attempt_no FROM print_attempts a JOIN print_jobs j ON j.id=a.job_id WHERE a.state='reserved' AND a.lease_expires_at<NOW() AND j.status='reserved' ORDER BY a.id FOR UPDATE");
    foreach($stmt->fetchAll() as $a){
        $pdo->prepare("UPDATE print_attempts SET state='expired',finished_at=NOW(),outcome='lease_expired',error_code='lease_expired',error_message='Agent پیش از Accept رزرو را پایدار نکرد.' WHERE id=? AND state='reserved'")->execute([(int)$a['id']]);
        $cycleAttempt=max(1,(int)($a['cycle_attempt_no']??1));
        if($cycleAttempt>=PRINT_V4_MAX_ATTEMPTS){
            $pdo->prepare("UPDATE print_jobs SET status='failed',blocked_reason=NULL,claim_token=NULL,claimed_by_agent_id=NULL,claimed_at=NULL,lease_expires_at=NULL,last_error_code='reservation_retry_exhausted',last_error='چرخه Retry ایمن پیش از Accept به سقف مجاز رسید؛ بررسی انسانی لازم است.' WHERE id=? AND status='reserved'")->execute([(int)$a['job_id']]);
        }else{
            $delay=min(20,2 ** max(1,$cycleAttempt))+random_int(0,3);
            $nextAttempt=date('Y-m-d H:i:s',time()+$delay);
            $pdo->prepare("UPDATE print_jobs SET status='pending',claim_token=NULL,claimed_by_agent_id=NULL,claimed_at=NULL,lease_expires_at=NULL,next_attempt_at=?,last_error_code='reservation_expired',last_error='رزرو پیش از Accept منقضی شد؛ Retry ایمن زمان‌بندی شد.' WHERE id=? AND status='reserved'")->execute([$nextAttempt,(int)$a['job_id']]);
        }
    }
}

function print_v4_attempt_response(array $row,array $destination,string $leaseToken): array
{
    return [
        'job'=>[
            'id'=>(int)$row['job_id'],'public_token'=>(string)$row['public_token'],'job_type'=>(string)$row['job_type'],'required'=>(int)$row['required']===1,
            'entity_type'=>(string)$row['entity_type'],'entity_id'=>$row['entity_id']!==null?(string)$row['entity_id']:null,'created_at'=>print_v4_wire_time((string)$row['created_at']) ?? (string)$row['created_at'],
            'contract_version'=>(int)$row['contract_version'],'content_sha256'=>(string)$row['content_sha256'],'payload_json'=>(string)$row['payload_json'],
        ],
        'attempt'=>[
            'id'=>(int)$row['attempt_id'],'attempt_no'=>(int)$row['attempt_no'],'retry_cycle'=>(int)($row['retry_cycle']??0),'cycle_attempt_no'=>(int)($row['cycle_attempt_no']??1),
            'state'=>(string)($row['attempt_state']??$row['state']??'reserved'),'lease_token'=>$leaseToken,'lease_expires_at'=>print_v4_wire_time((string)$row['attempt_lease_expires_at']),
        ],
        'destination'=>print_v4_attempt_destination($row,$destination),
    ];
}

/**
 * Claim replay must be byte-stable for all ownership fields. Lease tokens are
 * deliberately excluded from the database snapshot and are derived again from
 * the durable claim request identity.
 */
function print_v4_claim_snapshot(array $items): array
{
    $snapshot=[];
    foreach($items as $item){
        if(!is_array($item)||!is_array($item['job']??null)||!is_array($item['attempt']??null)||!is_array($item['destination']??null))
            throw new RuntimeException('Print Claim response snapshot is invalid.');
        unset($item['attempt']['lease_token']);
        $snapshot[]=$item;
    }
    return $snapshot;
}

function print_v4_claim_snapshot_restore(array $snapshot,string $claimRequestId): array
{
    $items=[];
    foreach($snapshot as $item){
        if(!is_array($item)||!is_array($item['job']??null)||!is_array($item['attempt']??null)||!is_array($item['destination']??null))
            throw new RuntimeException('Print Claim response snapshot is invalid.');
        $jobId=(int)($item['job']['id']??0);$attemptId=(int)($item['attempt']['id']??0);
        if($jobId<1||$attemptId<1||trim((string)($item['destination']['destination_key']??''))==='')
            throw new RuntimeException('Print Claim response snapshot is invalid.');
        $item['attempt']['lease_token']=hash_hmac('sha256',$claimRequestId.':'.(string)$jobId,print_v4_lease_secret());
        $items[]=$item;
    }
    return $items;
}

function print_v4_claim_snapshot_encode(array $items): string
{
    return json_encode(print_v4_claim_snapshot($items),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
}

/**
 * Rebuild a durable snapshot for claims created before response_snapshot_json existed.
 * This is intentionally based on the immutable Attempt destination snapshot and the
 * persisted Job payload; live destination mappings are never consulted here.
 */
function print_v4_rebuild_legacy_claim_snapshot(PDO $pdo,int $agentId,array $attemptIds,string $claimRequestId): array
{
    $attemptIds=array_values(array_unique(array_filter(array_map('intval',$attemptIds),static fn(int $id): bool=>$id>0)));
    if(!$attemptIds)return [];
    $ph=implode(',',array_fill(0,count($attemptIds),'?'));
    $stmt=$pdo->prepare("SELECT a.id attempt_id,a.attempt_no,a.retry_cycle,a.cycle_attempt_no,a.state attempt_state,a.lease_expires_at attempt_lease_expires_at,a.destination_snapshot_json,j.id job_id,j.public_token,j.job_type,j.required,j.entity_type,j.entity_id,j.created_at,j.contract_version,j.content_sha256,j.payload_json,j.destination_key FROM print_attempts a JOIN print_jobs j ON j.id=a.job_id WHERE a.agent_id=? AND a.id IN ($ph) ORDER BY a.id");
    $stmt->execute(array_merge([$agentId],$attemptIds));
    $items=[];
    foreach($stmt->fetchAll() as $row){
        $leaseToken=hash_hmac('sha256',$claimRequestId.':'.(string)$row['job_id'],print_v4_lease_secret());
        $items[]=print_v4_attempt_response($row,[],$leaseToken);
    }
    if(count($items)!==count($attemptIds))throw new RuntimeException('Print Claim response snapshot is incomplete.');
    return $items;
}

function print_v4_claim_reconciliation_hash(array $data): string
{
    $fields=$data['mismatch_fields']??[];
    if(is_array($fields)){sort($fields,SORT_STRING);$fields=array_values($fields);}
    return hash('sha256',json_encode([
        'request_id'=>$data['request_id']??null,
        'claim_request_id'=>$data['claim_request_id']??null,
        'attempt_id'=>$data['attempt_id']??null,
        'local_server_job_id'=>$data['local_server_job_id']??null,
        'local_content_sha256'=>strtolower(trim((string)($data['local_content_sha256']??''))),
        'local_destination_key'=>$data['local_destination_key']??null,
        'local_max_attempt_id'=>$data['local_max_attempt_id']??null,
        'mismatch_fields'=>$fields,
    ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
}

if(!print_tables_available())json_response(['success'=>false,'code'=>'printing_v4_not_installed','message'=>'ماژول Print API v4 در این نصب کامل نیست.'],503);
$pdo=db();
$agent=print_agent_api_auth($pdo);$agentId=(int)$agent['id'];
$actionRaw=$_GET['action']??'heartbeat';
if(!is_string($actionRaw))json_response(['success'=>false,'code'=>'invalid_action','message'=>'action معتبر نیست.'],404);
$action=print_agent_api_safe_text($actionRaw,30);
$data=print_agent_api_request();

try{
    if($action==='probe'){
        print_v4_require_version($data);
        json_response(print_v4_probe_payload($pdo,$agent));
    }

    if($action==='heartbeat'){
        $requestId=print_agent_api_request_id($data);
        $version=print_v4_require_version($data);
        if(array_key_exists('printers',$data)&&(!is_array($data['printers'])||!array_is_list($data['printers'])))json_response(['success'=>false,'code'=>'invalid_field_type','field'=>'printers','message'=>'printers باید آرایه باشد.'],422);
        $printers=print_v4_sanitize_printers($data['printers']??[]);
        $health=[
            'request_id'=>$requestId,
            'service_started_at'=>print_v4_optional_wire_time($data,'service_started_at'),
            'worker_ok'=>print_agent_api_bool_field($data,'worker_ok',true,false),
            'config_ok'=>print_agent_api_bool_field($data,'config_ok',true,false),
            'instance_lock_ok'=>print_agent_api_bool_field($data,'instance_lock_ok',true,false),
            // Optional transport diagnostics are kept inside the existing health_json owner.
            // They are operational evidence only and never participate in print ownership/state transitions.
            'last_successful_action'=>print_agent_api_optional_string_field($data,'last_successful_action',40),
            'last_api_success_at'=>print_v4_optional_wire_time($data,'last_api_success_at'),
            'last_api_error_code'=>print_agent_api_optional_string_field($data,'last_api_error_code',80),
            'consecutive_api_failures'=>print_v4_optional_int($data,'consecutive_api_failures',0,100000),
            'last_api_latency_ms'=>print_agent_api_optional_int_field($data,'last_api_latency_ms',0,600000),
            'printer_discovery_at'=>print_v4_optional_wire_time($data,'printer_discovery_at'),
            'bridge_protocol_version'=>print_v4_optional_int($data,'bridge_protocol_version',0,10),
            'bridge_port'=>print_v4_optional_int($data,'bridge_port',0,65535),
            'bridge_origin'=>print_agent_api_optional_string_field($data,'bridge_origin',240),
            'pending_report_count'=>print_v4_optional_int($data,'pending_report_count',0,100000),
            'auth_blocked_report_count'=>print_v4_optional_int($data,'auth_blocked_report_count',0,100000),
            'reconciliation_report_count'=>print_v4_optional_int($data,'reconciliation_report_count',0,100000),
            'printer_discovery_last_failure_at'=>print_v4_optional_wire_time($data,'printer_discovery_last_failure_at'),
            'printer_discovery_error'=>print_agent_api_optional_string_field($data,'printer_discovery_error',160),
            'printer_discovery_age_milliseconds'=>print_agent_api_optional_int_field($data,'printer_discovery_age_milliseconds',0,86400000),
            'printer_discovery_fresh'=>print_agent_api_optional_bool_field($data,'printer_discovery_fresh'),
            'printer_discovery_generation'=>print_v4_optional_int($data,'printer_discovery_generation',0,2147483647),
        ];
        $lastPoll=print_v4_optional_wire_time($data,'last_poll_success_at');
        $lastSubmission=print_v4_optional_wire_time($data,'last_submission_at');
        print_v4_cleanup_empty_claim_requests($pdo);
        $pdo->beginTransaction();
        print_v4_expire_reservations($pdo);
        $bridgeProtocol=print_v4_optional_int($data,'bridge_protocol_version',0,10);
        $bridgePort=print_v4_optional_int($data,'bridge_port',0,65535);
        $bridgePairing=print_agent_api_optional_string_field($data,'bridge_pairing_id',128)??'';
        $bridgeOrigin=print_agent_api_optional_string_field($data,'bridge_origin',240)??'';
        $bridgeUsable=$bridgeProtocol===1&&$bridgePort>=1024&&$bridgePort<=65535&&preg_match('/^[A-Za-z0-9_-]{20,128}$/',$bridgePairing)===1&&$bridgeOrigin!=='';
        $pdo->prepare("UPDATE print_agents SET hostname=?,agent_version=?,os_version=?,printers_json=?,health_json=?,bridge_protocol_version=?,bridge_port=?,bridge_pairing_id=?,bridge_origin=?,bridge_runtime_seen_at=?,uptime_seconds=?,last_poll_success_at=?,local_backlog_count=?,local_unknown_count=?,last_submission_at=?,sqlite_health=?,disk_free_mb=?,last_heartbeat_at=NOW(),last_seen_at=NOW(),last_error=NULL WHERE id=?")
            ->execute([
                print_agent_api_string_field($data,'hostname',190,false)?:null,$version,print_agent_api_string_field($data,'os_version',190,false)?:null,
                json_encode($printers,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),
                json_encode($health,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),
                $bridgeUsable?1:0,$bridgeUsable?$bridgePort:0,$bridgeUsable?$bridgePairing:null,$bridgeUsable?$bridgeOrigin:null,$bridgeUsable?date('Y-m-d H:i:s'):null,
                print_v4_optional_int($data,'uptime_seconds',0,2147483647),$lastPoll!==''?date('Y-m-d H:i:s',strtotime($lastPoll)?:time()):null,
                print_v4_optional_int($data,'local_backlog_count',0,1000000),print_v4_optional_int($data,'local_unknown_count',0,1000000),
                $lastSubmission!==''?date('Y-m-d H:i:s',strtotime($lastSubmission)?:time()):null,
                print_agent_api_string_field($data,'sqlite_health',30,false)?:null,print_v4_optional_int($data,'disk_free_mb',0,2147483647),$agentId
            ]);
        print_reconcile_blocked_jobs($pdo);
        $pdo->commit();
        json_response(print_v4_probe_payload($pdo,$agent)+['request_id'=>$requestId]);
    }

    if($action==='claim_reconcile'){
        $requestId=print_agent_api_request_id($data);$version=print_v4_require_version($data);
        $claimRequestId=print_agent_api_string_field($data,'claim_request_id',80);
        if(!preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{7,79}$/',$claimRequestId))json_response(['success'=>false,'code'=>'invalid_claim_request_id','message'=>'شناسه Claim معتبر نیست.'],422);
        $attemptId=print_agent_api_int_field($data,'attempt_id',1,PHP_INT_MAX)??0;
        $localJobId=print_agent_api_int_field($data,'local_server_job_id',1,PHP_INT_MAX)??0;
        $localMaxAttemptId=print_agent_api_int_field($data,'local_max_attempt_id',$attemptId,PHP_INT_MAX-1)??0;
        $localHash=strtolower(print_agent_api_string_field($data,'local_content_sha256',64));
        if(!print_agent_api_content_sha256_valid($localHash))json_response(['success'=>false,'code'=>'invalid_content_sha256','message'=>'Hash محلی معتبر نیست.'],422);
        $localDestination=print_agent_api_string_field($data,'local_destination_key',40);
        $mismatchInput=$data['mismatch_fields']??null;
        if(!is_array($mismatchInput)||!array_is_list($mismatchInput)||count($mismatchInput)<1||count($mismatchInput)>8)json_response(['success'=>false,'code'=>'invalid_mismatch_fields','message'=>'فهرست فیلدهای متعارض معتبر نیست.'],422);
        $allowedMismatch=['server_job_id','content_sha256','payload_json','destination_key','queue_name','server_scope'];$mismatch=[];
        foreach($mismatchInput as $field){if(!is_string($field)||!in_array($field,$allowedMismatch,true))json_response(['success'=>false,'code'=>'invalid_mismatch_fields','message'=>'یکی از فیلدهای متعارض معتبر نیست.'],422);$mismatch[]=$field;}
        $mismatch=array_values(array_unique($mismatch));sort($mismatch,SORT_STRING);$data['mismatch_fields']=$mismatch;
        $resolutionHash=print_v4_claim_reconciliation_hash($data);

        $pdo->beginTransaction();
        // Lock the Claim first so concurrent retries of the same reconciliation
        // cannot both pass an initially-empty ledger lookup.
        $claimStmt=$pdo->prepare("SELECT * FROM print_claim_requests WHERE agent_id=? AND request_id=? FOR UPDATE");
        $claimStmt->execute([$agentId,$claimRequestId]);$claim=$claimStmt->fetch();
        if(!$claim){$pdo->rollBack();json_response(['success'=>false,'code'=>'claim_request_not_found','message'=>'Claim پایدار پیدا نشد.'],404);}
        $duplicate=$pdo->prepare("SELECT c.request_id claim_request_id,r.request_hash,r.old_attempt_id,r.replacement_attempt_id FROM print_claim_reconciliations r JOIN print_claim_requests c ON c.id=r.claim_request_row_id WHERE r.agent_id=? AND r.request_id=? LIMIT 1 FOR UPDATE");
        $duplicate->execute([$agentId,$requestId]);$duplicateRow=$duplicate->fetch();
        if($duplicateRow){
            if(!hash_equals((string)($duplicateRow['request_hash']??''),$resolutionHash)){$pdo->rollBack();json_response(['success'=>false,'code'=>'request_body_conflict','message'=>'این request_id قبلاً با بدنه متفاوت استفاده شده است.'],409);}
            $pdo->commit();json_response(['success'=>true,'status'=>'replacement_reserved','claim_request_id'=>(string)$duplicateRow['claim_request_id'],'old_attempt_id'=>(int)$duplicateRow['old_attempt_id'],'replacement_attempt_id'=>(int)$duplicateRow['replacement_attempt_id'],'idempotent'=>true,'server_time'=>print_v4_server_time()]);
        }

        $attemptIds=json_decode((string)($claim['attempt_ids_json']??''),true);
        if(!is_array($attemptIds)||!in_array($attemptId,array_map('intval',$attemptIds),true)){$pdo->rollBack();json_response(['success'=>false,'code'=>'attempt_not_in_claim','message'=>'Attempt متعارض متعلق به این Claim نیست.'],409);}
        $storedSnapshot=$claim['response_snapshot_json']??null;
        if($storedSnapshot===null){
            // Legacy dev.22-and-earlier claims have durable attempt ids but no response snapshot.
            // Rebuild once from persisted immutable evidence, then continue the normal audited rekey path.
            $legacyItems=print_v4_rebuild_legacy_claim_snapshot($pdo,$agentId,$attemptIds,$claimRequestId);
            $storedSnapshot=print_v4_claim_snapshot_encode($legacyItems);
            $pdo->prepare("UPDATE print_claim_requests SET response_snapshot_json=? WHERE id=? AND response_snapshot_json IS NULL")
                ->execute([$storedSnapshot,(int)$claim['id']]);
        }
        $decodedSnapshot=json_decode((string)$storedSnapshot,true,512,JSON_THROW_ON_ERROR);
        if(!is_array($decodedSnapshot)||!array_is_list($decodedSnapshot))throw new RuntimeException('Print Claim response snapshot is invalid.');
        $snapshotItem=null;
        foreach($decodedSnapshot as $item){if((int)($item['attempt']['id']??0)===$attemptId){$snapshotItem=$item;break;}}
        if(!is_array($snapshotItem)||!is_array($snapshotItem['job']??null)||!is_array($snapshotItem['attempt']??null)||!is_array($snapshotItem['destination']??null))throw new RuntimeException('Print Claim response snapshot is incomplete.');
        $attemptStmt=$pdo->prepare("SELECT a.*,j.id job_id,j.public_token,j.job_type,j.required,j.entity_type,j.entity_id,j.created_at,j.contract_version,j.content_sha256,j.payload_json,j.destination_key,j.status job_status,j.attempt_count job_attempt_count,j.retry_cycle job_retry_cycle,j.accepted_at job_accepted_at,j.local_receipt_id job_local_receipt_id FROM print_attempts a JOIN print_jobs j ON j.id=a.job_id WHERE a.id=? AND a.agent_id=? FOR UPDATE");
        $attemptStmt->execute([$attemptId,$agentId]);$old=$attemptStmt->fetch();
        $oldState=(string)($old['state']??'');$jobState=(string)($old['job_status']??'');
        $unsafe=!$old||!in_array($oldState,['reserved','expired'],true)||($oldState==='reserved'&&$jobState!=='reserved')||($oldState==='expired'&&$jobState!=='pending')||!empty($old['local_receipt_id'])||!empty($old['accepted_at'])||!empty($old['started_at'])||!empty($old['spooler_job_id'])||!empty($old['report_request_id'])||!empty($old['job_local_receipt_id'])||!empty($old['job_accepted_at']);
        if(!$unsafe){
            $siblings=$pdo->prepare("SELECT id,state FROM print_attempts WHERE job_id=? ORDER BY id FOR UPDATE");$siblings->execute([(int)$old['job_id']]);
            foreach($siblings->fetchAll() as $sibling){
                $siblingId=(int)$sibling['id'];
                if($siblingId===$attemptId)continue;
                if($siblingId>$attemptId||in_array((string)$sibling['state'],['reserved','claimed','started','unknown','recovery_hold'],true)){$unsafe=true;break;}
            }
        }
        if($unsafe){$pdo->rollBack();json_response(['success'=>false,'code'=>'claim_reconciliation_unsafe','message'=>'Attempt شواهد Accept/Start دارد یا دیگر فقط رزروشده نیست؛ تعیین تکلیف خودکار ممنوع است.','requires_human_resolution'=>true],409);}

        $snapshotJob=$snapshotItem['job'];$snapshotDestination=$snapshotItem['destination'];$actualMismatch=[];
        if((int)($snapshotJob['id']??0)!==$localJobId)$actualMismatch[]='server_job_id';
        if(!hash_equals(strtolower((string)($snapshotJob['content_sha256']??'')),$localHash))$actualMismatch[]='content_sha256';
        if((string)($snapshotDestination['destination_key']??'')!==$localDestination)$actualMismatch[]='destination_key';
        if(count($actualMismatch)===0||count(array_diff($actualMismatch,$mismatch))>0){$pdo->rollBack();json_response(['success'=>false,'code'=>'claim_mismatch_not_proven','message'=>'Evidence ارسالی تعارض واقعی با Snapshot پایدار Claim را اثبات نمی‌کند.','requires_human_resolution'=>true],409);}

        $existingResolution=$pdo->prepare("SELECT request_id FROM print_claim_reconciliations WHERE claim_request_row_id=? AND old_attempt_id=? LIMIT 1 FOR UPDATE");
        $existingResolution->execute([(int)$claim['id'],$attemptId]);
        if($existingResolution->fetchColumn()!==false){$pdo->rollBack();json_response(['success'=>false,'code'=>'claim_reconciliation_conflict','message'=>'این Attempt قبلاً با درخواست دیگری تعیین تکلیف شده است.'],409);}

        $highestStmt=$pdo->query("SELECT id FROM print_attempts ORDER BY id DESC LIMIT 1 FOR UPDATE");$highest=(int)($highestStmt->fetchColumn()?:0);
        if($localMaxAttemptId>$highest+PRINT_V4_RECONCILIATION_MAX_ID_GAP){$pdo->rollBack();json_response(['success'=>false,'code'=>'attempt_id_gap_unsafe','message'=>'فاصله شناسه محلی برای rekey خودکار بیش از حد ایمن است؛ تعیین تکلیف انسانی لازم است.','requires_human_resolution'=>true],409);}
        $replacementId=max($highest,$localMaxAttemptId)+1;
        if($replacementId<1||$replacementId>=PHP_INT_MAX){$pdo->rollBack();json_response(['success'=>false,'code'=>'attempt_id_space_exhausted','message'=>'فضای شناسه Attempt برای rekey امن کافی نیست.'],409);}
        $newAttemptNo=max((int)$old['attempt_no'],(int)$old['job_attempt_count'])+1;
        $newRetryCycle=max((int)($old['retry_cycle']??0),(int)$old['job_retry_cycle'])+1;
        $leaseToken=hash_hmac('sha256',$claimRequestId.':'.(string)$old['job_id'],print_v4_lease_secret());$leaseHash=print_v4_token_hash($leaseToken);$leaseExpires=print_v4_lease_expiry();
        $expireOld=$pdo->prepare("UPDATE print_attempts SET state='expired',finished_at=COALESCE(finished_at,NOW()),outcome='identity_collision_rekey',error_code='claim_identity_collision',error_message='Agent durable history already owns this numeric attempt_id; reservation was never accepted and was safely replaced.' WHERE id=? AND state IN('reserved','expired')");$expireOld->execute([$attemptId]);
        if($expireOld->rowCount()!==1){$pdo->rollBack();json_response(['success'=>false,'code'=>'claim_reconciliation_race','message'=>'وضعیت Attempt هنگام reconciliation تغییر کرد؛ چاپ خودکار ممنوع است.'],409);}
        $pdo->prepare("INSERT INTO print_attempts(id,job_id,attempt_no,retry_cycle,cycle_attempt_no,agent_id,state,claim_request_id,lease_token_hash,lease_expires_at,destination_snapshot_json,leased_at) VALUES(?,?,?,?,1,?,'reserved',?,?,?,?,NOW())")
            ->execute([$replacementId,(int)$old['job_id'],$newAttemptNo,$newRetryCycle,$agentId,$claimRequestId,$leaseHash,$leaseExpires,json_encode($snapshotDestination,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)]);
        $reserveJob=$pdo->prepare("UPDATE print_jobs SET status='reserved',attempt_count=?,retry_cycle=?,retry_cycle_started_at=NOW(),retry_cycle_started_by_user_id=NULL,claimed_by_agent_id=?,claimed_at=NOW(),lease_expires_at=?,accepted_at=NULL,local_receipt_id=NULL,next_attempt_at=NULL,last_error_code='claim_identity_rekey',last_error='تعارض شناسه تاریخچه Agent پیش از Accept با Attempt تازه و Audit پایدار رفع شد.' WHERE id=? AND status=?");
        $reserveJob->execute([$newAttemptNo,$newRetryCycle,$agentId,$leaseExpires,(int)$old['job_id'],$jobState]);
        if($reserveJob->rowCount()!==1){$pdo->rollBack();json_response(['success'=>false,'code'=>'claim_reconciliation_race','message'=>'وضعیت Job هنگام reconciliation تغییر کرد؛ چاپ خودکار ممنوع است.'],409);}

        foreach($attemptIds as &$candidate){if((int)$candidate===$attemptId){$candidate=$replacementId;break;}}unset($candidate);
        $snapshot=[];$replaced=false;
        foreach($decodedSnapshot as $item){
            if((int)($item['attempt']['id']??0)===$attemptId){
                $item['attempt']['id']=$replacementId;$item['attempt']['attempt_no']=$newAttemptNo;$item['attempt']['retry_cycle']=$newRetryCycle;$item['attempt']['cycle_attempt_no']=1;$item['attempt']['state']='reserved';$item['attempt']['lease_expires_at']=print_v4_wire_time($leaseExpires);unset($item['attempt']['lease_token']);$replaced=true;
            }
            $snapshot[]=$item;
        }
        if(!$replaced)throw new RuntimeException('Print Claim response snapshot is incomplete.');
        $evidence=['old_attempt_id'=>$attemptId,'replacement_attempt_id'=>$replacementId,'local_server_job_id'=>$localJobId,'server_job_id'=>(int)$old['job_id'],'mismatch_fields'=>$mismatch,'local_content_sha256_prefix'=>substr($localHash,0,16),'local_destination_key'=>$localDestination,'agent_version'=>$version];
        $pdo->prepare("UPDATE print_claim_requests SET attempt_ids_json=?,response_snapshot_json=? WHERE id=?")
            ->execute([json_encode(array_values($attemptIds),JSON_THROW_ON_ERROR),json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),(int)$claim['id']]);
        $pdo->prepare("INSERT INTO print_claim_reconciliations(claim_request_row_id,agent_id,request_id,request_hash,old_attempt_id,replacement_attempt_id,evidence_json) VALUES(?,?,?,?,?,?,?)")
            ->execute([(int)$claim['id'],$agentId,$requestId,$resolutionHash,$attemptId,$replacementId,json_encode($evidence,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)]);
        $pdo->commit();json_response(['success'=>true,'status'=>'replacement_reserved','claim_request_id'=>$claimRequestId,'old_attempt_id'=>$attemptId,'replacement_attempt_id'=>$replacementId,'idempotent'=>false,'server_time'=>print_v4_server_time()]);
    }

    if($action==='claim'){
        $requestId=print_agent_api_request_id($data);$version=print_v4_require_version($data);
        $limit=print_agent_api_int_field($data,'limit',1,5,false) ?? 3;
        if(!array_key_exists('ready_destination_keys',$data)||!is_array($data['ready_destination_keys'])||!array_is_list($data['ready_destination_keys']))json_response(['success'=>false,'code'=>'invalid_ready_destination_keys','message'=>'ready_destination_keys باید آرایه صریح باشد؛ آرایه خالی یعنی هیچ مقصد آماده نیست.'],422);
        $readyInput=[];foreach($data['ready_destination_keys'] as $candidate){if(!is_string($candidate)||trim($candidate)===''||strlen($candidate)>40)json_response(['success'=>false,'code'=>'invalid_ready_destination_keys','message'=>'یکی از مقصدهای آماده معتبر نیست.'],422);$readyInput[]=trim($candidate);}
        $readyInput=array_values(array_unique($readyInput));
        $data['ready_destination_keys']=$readyInput;$data['limit']=$limit;
        $claimHash=print_v4_request_hash('claim',$data);
        $pdo->beginTransaction();
        print_v4_expire_reservations($pdo);
        print_reconcile_blocked_jobs($pdo);
        $destinations=print_v4_agent_destinations($pdo,$agentId);
        $ready=[];
        foreach($destinations as $key=>$d){
            if(!print_v4_destination_server_ready($agent,$d))continue;
            if(!in_array($key,$readyInput,true))continue;
            $ready[$key]=$d;
        }
        // Claim request itself is durable, including an empty result. This prevents a replay from claiming newer work.
        $claimMarker=$pdo->prepare("INSERT IGNORE INTO print_claim_requests(agent_id,request_id,request_hash,agent_version,attempt_ids_json,response_snapshot_json,created_at) VALUES(?,?,?,?,NULL,NULL,NOW())");
        $claimMarker->execute([$agentId,$requestId,$claimHash,$version]);
        $ownsClaimRequest=$claimMarker->rowCount()===1;
        if(!$ownsClaimRequest){
            $saved=$pdo->prepare("SELECT request_hash,agent_version,attempt_ids_json,response_snapshot_json FROM print_claim_requests WHERE agent_id=? AND request_id=? FOR UPDATE");
            $saved->execute([$agentId,$requestId]);
            $savedRow=$saved->fetch();
            if(!$savedRow||((string)($savedRow['request_hash']??'')!==''&&!hash_equals((string)$savedRow['request_hash'],$claimHash))){
                $pdo->rollBack();json_response(['success'=>false,'code'=>'request_body_conflict','message'=>'این Claim request_id قبلاً با بدنه متفاوت استفاده شده است.'],409);
            }
            $attemptIdsJson=$savedRow['attempt_ids_json'];
            if($attemptIdsJson===null){
                $pdo->rollBack();
                json_response(['success'=>false,'code'=>'claim_replay_incomplete','message'=>'Claim قبلی هنوز به نتیجه پایدار نرسیده است؛ همان request_id را دوباره ارسال کنید.'],409);
            }
            $attemptIds=json_decode((string)$attemptIdsJson,true,512,JSON_THROW_ON_ERROR);
            if(!is_array($attemptIds))$attemptIds=[];
            $items=[];
            $storedSnapshot=$savedRow['response_snapshot_json']??null;
            if($storedSnapshot!==null){
                $decoded=json_decode((string)$storedSnapshot,true,512,JSON_THROW_ON_ERROR);
                if(!is_array($decoded)||!array_is_list($decoded))throw new RuntimeException('Print Claim response snapshot is invalid.');
                $items=print_v4_claim_snapshot_restore($decoded,$requestId);
            }elseif($attemptIds){
                $items=print_v4_rebuild_legacy_claim_snapshot($pdo,$agentId,$attemptIds,$requestId);
                $pdo->prepare("UPDATE print_claim_requests SET response_snapshot_json=? WHERE agent_id=? AND request_id=? AND response_snapshot_json IS NULL")
                    ->execute([print_v4_claim_snapshot_encode($items),$agentId,$requestId]);
            }else{
                $pdo->prepare("UPDATE print_claim_requests SET response_snapshot_json=JSON_ARRAY() WHERE agent_id=? AND request_id=? AND response_snapshot_json IS NULL")
                    ->execute([$agentId,$requestId]);
            }
            $pdo->prepare('UPDATE print_agents SET agent_version=?,last_poll_success_at=NOW(),last_seen_at=NOW(),last_error=NULL WHERE id=?')->execute([$version,$agentId]);
            $pdo->commit();
            json_response(['success'=>true,'request_id'=>$requestId,'jobs'=>$items,'server_time'=>print_v4_server_time(),'idempotent'=>true]);
        }
        $claimed=[];$claimedAttemptIds=[];
        foreach($ready as $destinationKey=>$destination){
            if(count($claimed)>=$limit)break;
            // FIFO fence: any older unresolved job blocks later jobs for this destination.
            $q=$pdo->prepare("SELECT * FROM print_jobs WHERE destination_key=? AND NOT(status IN('submitted','cancelled') OR (status IN('failed','unknown','recovery_hold') AND resolved_at IS NOT NULL)) ORDER BY id LIMIT ? FOR UPDATE");
            $q->bindValue(1,$destinationKey,PDO::PARAM_STR);$q->bindValue(2,$limit-count($claimed),PDO::PARAM_INT);$q->execute();
            foreach($q->fetchAll() as $job){
                if(count($claimed)>=$limit)break;
                if((string)$job['status']!=='pending')break;
                if(!empty($job['next_attempt_at']) && strtotime((string)$job['next_attempt_at'])>time())break;
                $attemptNo=(int)$job['attempt_count']+1;
                $retryCycle=(int)($job['retry_cycle']??0);
                $cycleStmt=$pdo->prepare('SELECT COALESCE(MAX(cycle_attempt_no),0)+1 FROM print_attempts WHERE job_id=? AND retry_cycle=?');$cycleStmt->execute([(int)$job['id'],$retryCycle]);$cycleAttemptNo=(int)$cycleStmt->fetchColumn();
                if($cycleAttemptNo>PRINT_V4_MAX_ATTEMPTS){
                    $pdo->prepare("UPDATE print_jobs SET status='failed',last_error_code='safe_retry_exhausted',last_error='چرخه Retry ایمن پیش از Submission به سقف مجاز رسید.' WHERE id=?")->execute([(int)$job['id']]);
                    break;
                }
                $leaseToken=hash_hmac('sha256',$requestId.':'.(string)$job['id'],print_v4_lease_secret());
                $leaseHash=print_v4_token_hash($leaseToken);$leaseExpires=print_v4_lease_expiry();
                $destinationSnapshot=json_encode(print_v4_destination_snapshot($destination),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
                $pdo->prepare("INSERT INTO print_attempts(job_id,attempt_no,retry_cycle,cycle_attempt_no,agent_id,state,claim_request_id,lease_token_hash,lease_expires_at,destination_snapshot_json,leased_at) VALUES(?,?,?,?,?,'reserved',?,?,?,?,NOW())")
                    ->execute([(int)$job['id'],$attemptNo,$retryCycle,$cycleAttemptNo,$agentId,$requestId,$leaseHash,$leaseExpires,$destinationSnapshot]);
                $attemptId=(int)$pdo->lastInsertId();$claimedAttemptIds[]=$attemptId;
                $pdo->prepare("UPDATE print_jobs SET status='reserved',blocked_reason=NULL,attempt_count=?,claimed_by_agent_id=?,claimed_at=NOW(),lease_expires_at=?,claim_token=NULL,last_error_code=NULL,last_error=NULL WHERE id=? AND status='pending'")
                    ->execute([$attemptNo,$agentId,$leaseExpires,(int)$job['id']]);
                $job['job_id']=(int)$job['id'];$job['attempt_id']=$attemptId;$job['attempt_no']=$attemptNo;$job['retry_cycle']=$retryCycle;$job['cycle_attempt_no']=$cycleAttemptNo;$job['attempt_state']='reserved';$job['attempt_lease_expires_at']=$leaseExpires;$job['destination_snapshot_json']=$destinationSnapshot;
                $claimed[]=print_v4_attempt_response($job,$destination,$leaseToken);
            }
        }
        $pdo->prepare("UPDATE print_claim_requests SET attempt_ids_json=?,response_snapshot_json=? WHERE agent_id=? AND request_id=? AND attempt_ids_json IS NULL")
            ->execute([json_encode($claimedAttemptIds,JSON_THROW_ON_ERROR),print_v4_claim_snapshot_encode($claimed),$agentId,$requestId]);
        $pdo->prepare('UPDATE print_agents SET agent_version=?,last_poll_success_at=NOW(),last_seen_at=NOW(),last_error=NULL WHERE id=?')->execute([$version,$agentId]);
        $pdo->commit();
        json_response(['success'=>true,'request_id'=>$requestId,'jobs'=>$claimed,'server_time'=>print_v4_server_time(),'idempotent'=>false]);
    }

    if($action==='attempt_status'){
        print_v4_require_version($data);
        $attemptId=print_agent_api_int_field($data,'attempt_id',1,PHP_INT_MAX) ?? 0;
        $leaseToken=print_agent_api_string_field($data,'lease_token',128);
        $localReceipt=print_agent_api_string_field($data,'local_receipt_id',96,false);
        $stmt=$pdo->prepare("SELECT a.*,j.status job_status,j.resolved_at job_resolved_at,j.id job_id FROM print_attempts a JOIN print_jobs j ON j.id=a.job_id WHERE a.id=? AND a.agent_id=? LIMIT 1");
        $stmt->execute([$attemptId,$agentId]);$attempt=$stmt->fetch();
        if(!$attempt)json_response(['success'=>false,'code'=>'attempt_not_found','message'=>'Attempt پیدا نشد.'],404);
        if(!hash_equals((string)$attempt['lease_token_hash'],print_v4_token_hash($leaseToken)))json_response(['success'=>false,'code'=>'invalid_lease','message'=>'Lease معتبر نیست.'],403);
        $state=(string)$attempt['state'];$jobState=(string)$attempt['job_status'];
        $receiptStored=(string)($attempt['local_receipt_id']??'');$receiptMatches=$localReceipt===''?$receiptStored==='':($receiptStored!==''&&hash_equals($receiptStored,$localReceipt));
        $terminal=print_v4_terminal_attempt_state($state);$human=in_array($state,['unknown','recovery_hold'],true)&&empty($attempt['job_resolved_at']);
        $leaseExpired=$state==='reserved' && strtotime((string)$attempt['lease_expires_at'])<time();
        if(!$receiptMatches && $receiptStored!=='')$next='reconcile';
        elseif($human)$next='human_resolution';
        elseif($state==='claimed')$next='start';
        elseif($state==='started')$next='report';
        elseif($state==='reserved'&&!$leaseExpired)$next='accept';
        elseif($state==='reserved'&&$leaseExpired)$next='reconcile';
        elseif($terminal)$next='none';
        else $next='reconcile';
        json_response(['success'=>true,'attempt_id'=>$attemptId,'job_id'=>(int)$attempt['job_id'],'attempt_state'=>$state,'job_state'=>$jobState,'receipt_matches'=>$receiptMatches,'next_action'=>$next,'terminal'=>$terminal||$leaseExpired,'requires_human_resolution'=>$human,'lease_expires_at'=>$state==='reserved'?print_v4_wire_time((string)$attempt['lease_expires_at']):null,'server_time'=>print_v4_server_time()]);
    }

    if(in_array($action,['accept','renew','start','report'],true)){
        $requestId=print_agent_api_request_id($data);print_v4_require_version($data);
        $attemptId=print_agent_api_int_field($data,'attempt_id',1,PHP_INT_MAX) ?? 0;$leaseToken=print_agent_api_string_field($data,'lease_token',128);
        $pdo->beginTransaction();
        $stmt=$pdo->prepare("SELECT a.*,j.public_token,j.status job_status,j.content_sha256,j.local_receipt_id job_local_receipt_id,j.resolved_at job_resolved_at,j.resolution_state job_resolution_state,j.id job_id FROM print_attempts a JOIN print_jobs j ON j.id=a.job_id WHERE a.id=? AND a.agent_id=? FOR UPDATE");
        $stmt->execute([$attemptId,$agentId]);$attempt=$stmt->fetch();
        if(!$attempt){$pdo->rollBack();json_response(['success'=>false,'code'=>'attempt_not_found','message'=>'Attempt پیدا نشد.'],404);}
        if(!hash_equals((string)$attempt['lease_token_hash'],print_v4_token_hash($leaseToken))){$pdo->rollBack();json_response(['success'=>false,'code'=>'invalid_lease','message'=>'Lease معتبر نیست.'],403);}
        print_v4_assert_request_id_not_reused($pdo,$agentId,$action,$requestId,$attemptId);
        $requestHash=print_v4_request_hash($action,$data);
        if(!print_v4_request_body_matches($attempt,$action,$requestId,$requestHash)){$pdo->rollBack();json_response(['success'=>false,'code'=>'request_body_conflict','message'=>'این request_id قبلاً با بدنه متفاوت استفاده شده است.'],409);}

        if($action==='renew'){
            if((string)$attempt['renew_request_id']===$requestId){$pdo->commit();json_response(['success'=>true,'status'=>(string)$attempt['state'],'lease_expires_at'=>print_v4_wire_time((string)$attempt['lease_expires_at']),'idempotent'=>true]);}
            if((string)$attempt['state']!=='reserved'){$pdo->rollBack();json_response(['success'=>false,'code'=>'invalid_transition','message'=>'فقط Lease رزروشده قابل تمدید است.'],409);}
            if(strtotime((string)$attempt['lease_expires_at'])<time()){$pdo->rollBack();json_response(['success'=>false,'code'=>'lease_expired','message'=>'Lease منقضی شده است.'],409);}
            $exp=print_v4_lease_expiry();
            $pdo->prepare("UPDATE print_attempts SET lease_expires_at=?,renew_request_id=?,renew_request_hash=? WHERE id=?")->execute([$exp,$requestId,$requestHash,$attemptId]);
            $pdo->prepare("UPDATE print_jobs SET lease_expires_at=? WHERE id=? AND status='reserved'")->execute([$exp,(int)$attempt['job_id']]);
            $pdo->commit();json_response(['success'=>true,'status'=>'reserved','lease_expires_at'=>print_v4_wire_time($exp),'idempotent'=>false]);
        }

        if($action==='accept'){
            $localReceipt=print_agent_api_string_field($data,'local_receipt_id',96);
            $hashRaw=print_agent_api_string_field($data,'content_sha256',128);$hash=strtolower($hashRaw);
            if(!print_agent_api_local_receipt_id_valid($localReceipt)){
                $pdo->rollBack();json_response(['success'=>false,'code'=>'invalid_local_receipt_id','message'=>'شناسه Receipt محلی Agent معتبر نیست.'],422);
            }
            if(!print_agent_api_content_sha256_valid($hash)){
                $pdo->rollBack();json_response(['success'=>false,'code'=>'invalid_content_sha256','message'=>'Hash محتوای Job معتبر نیست.'],422);
            }
            if(!hash_equals(strtolower((string)$attempt['content_sha256']),$hash)){
                $pdo->rollBack();json_response(['success'=>false,'code'=>'content_hash_mismatch','message'=>'Hash محتوای محلی با Job رزروشده سازگار نیست.'],422);
            }
            if((string)$attempt['local_receipt_id']!==''){
                $same=hash_equals((string)$attempt['local_receipt_id'],$localReceipt);
                $currentState=(string)$attempt['state'];
                $pdo->commit();
                if(!$same)json_response(['success'=>false,'code'=>'local_receipt_conflict','message'=>'این Attempt قبلاً با Receipt دیگری Accept شده است.'],409);
                json_response(['success'=>true,'status'=>$currentState,'current_state'=>$currentState,'idempotent'=>true]);
            }
            if((string)$attempt['state']!=='reserved'||strtotime((string)$attempt['lease_expires_at'])<time()){$pdo->rollBack();json_response(['success'=>false,'code'=>'lease_expired','message'=>'Lease پیش از Accept منقضی شده است.'],409);}
            try{
                $acceptAttempt=$pdo->prepare("UPDATE print_attempts SET state='claimed',local_receipt_id=?,accept_request_id=?,accept_request_hash=?,accepted_at=NOW() WHERE id=? AND state='reserved'");
                $acceptAttempt->execute([$localReceipt,$requestId,$requestHash,$attemptId]);
                if($acceptAttempt->rowCount()!==1){$pdo->rollBack();json_response(['success'=>false,'code'=>'invalid_transition','message'=>'Attempt دیگر رزروشده نیست.'],409);}
            }catch(PDOException $e){
                $info=$e->errorInfo;$duplicate=((string)$e->getCode()==='23000' && (int)($info[1]??0)===1062);$constraint=(string)($info[2]??'');
                if($duplicate && str_contains($constraint,'uq_print_attempt_agent_receipt')){$pdo->rollBack();json_response(['success'=>false,'code'=>'local_receipt_conflict','message'=>'Receipt محلی قبلاً استفاده شده است.'],409);}
                throw $e;
            }
            $acceptJob=$pdo->prepare("UPDATE print_jobs SET status='claimed',accepted_at=NOW(),local_receipt_id=?,lease_expires_at=NULL WHERE id=? AND status='reserved'");
            $acceptJob->execute([$localReceipt,(int)$attempt['job_id']]);
            if($acceptJob->rowCount()!==1){$pdo->rollBack();json_response(['success'=>false,'code'=>'job_reservation_conflict','message'=>'رزرو Job با این Attempt سازگار نیست؛ چاپ خودکار ممنوع است.'],409);}
            $pdo->commit();json_response(['success'=>true,'status'=>'claimed','idempotent'=>false]);
        }

        if($action==='start'){
            $currentState=(string)$attempt['state'];
            if($currentState==='started'){$pdo->commit();json_response(['success'=>true,'status'=>'started','idempotent'=>true]);}
            if($currentState!=='claimed'){
                $terminal=print_v4_terminal_attempt_state($currentState);
                $requiresHuman=in_array($currentState,['unknown','recovery_hold'],true)&&empty($attempt['job_resolved_at']);
                $pdo->rollBack();
                json_response(['success'=>false,'code'=>'invalid_transition','message'=>'فقط Attempt پذیرفته‌شده قابل Start است.','current_state'=>$currentState,'terminal'=>$terminal,'requires_human_resolution'=>$requiresHuman],409);
            }
            $pdo->prepare("UPDATE print_attempts SET state='started',start_request_id=?,start_request_hash=?,started_at=NOW() WHERE id=?")->execute([$requestId,$requestHash,$attemptId]);
            $pdo->commit();json_response(['success'=>true,'status'=>'started','idempotent'=>false]);
        }

        if($action==='report'){
            $status=print_agent_api_string_field($data,'status',24,true);
            if(!in_array($status,['submitted','failed','unknown','recovery_hold'],true)){$pdo->rollBack();json_response(['success'=>false,'code'=>'invalid_status','message'=>'وضعیت گزارش معتبر نیست.'],422);}
            $localReceipt=print_agent_api_string_field($data,'local_receipt_id',96,true);
            if($localReceipt===''||!hash_equals((string)$attempt['local_receipt_id'],$localReceipt)){$pdo->rollBack();json_response(['success'=>false,'code'=>'local_receipt_conflict','message'=>'Receipt محلی با Attempt سازگار نیست.'],409);}
            $spooler=print_agent_api_optional_string_field($data,'spooler_job_id',96)??'';
            $errorCode=print_agent_api_optional_string_field($data,'error_code',80)??'';
            $errorMessage=print_agent_api_optional_string_field($data,'error_message',500)??'';
            $retryable=print_agent_api_bool_field($data,'retryable',false,true);
            $existingState=(string)$attempt['state'];
            if($status==='submitted'&&$spooler===''){$pdo->rollBack();json_response(['success'=>false,'code'=>'spooler_job_id_required','message'=>'برای submitted شناسه Spooler لازم است.'],422);}

            if(print_v4_terminal_attempt_state($existingState)){
                $existingSpooler=(string)($attempt['spooler_job_id']??'');
                $sameEvidence=$existingSpooler===$spooler;
                $lateEvidenceCompatible=$existingSpooler==='' || ($spooler!==''&&hash_equals($existingSpooler,$spooler));
                // Late submitted evidence is allowed only for an attempt that actually crossed Start,
                // remains unresolved by a human, and is bound to the same durable local receipt.
                $lateSubmitted=$status==='submitted'
                    && in_array($existingState,['unknown','recovery_hold'],true)
                    && !empty($attempt['started_at'])
                    && empty($attempt['job_resolved_at'])
                    && $spooler!==''
                    && $lateEvidenceCompatible;
                if($lateSubmitted){
                    $pdo->prepare("UPDATE print_attempts SET state='submitted',report_request_id=?,report_request_hash=?,spooler_job_id=?,submitted_at=NOW(),finished_at=NOW(),outcome='submitted',error_code=NULL,error_message=NULL WHERE id=? AND state IN('unknown','recovery_hold')")
                        ->execute([$requestId,$requestHash,$spooler,$attemptId]);
                    $pdo->prepare("UPDATE print_jobs SET status='submitted',submitted_at=NOW(),last_error_code=NULL,last_error=NULL,next_attempt_at=NULL,resolution_state='late_submitted_evidence',resolved_at=NOW(),resolved_by_user_id=NULL,resolution_note='Spooler evidence پس از وضعیت مبهم دریافت شد.' WHERE id=? AND status IN('unknown','recovery_hold') AND resolved_at IS NULL")
                        ->execute([(int)$attempt['job_id']]);
                    $pdo->prepare("UPDATE print_agents SET last_submission_at=NOW(),last_seen_at=NOW(),last_error=NULL WHERE id=?")->execute([$agentId]);
                    $pdo->commit();json_response(['success'=>true,'status'=>'submitted','physical_print_confirmed'=>false,'late_evidence'=>true,'idempotent'=>false]);
                }
                $pdo->commit();
                if($existingState!==$status||!$sameEvidence)json_response(['success'=>false,'code'=>'terminal_conflict','message'=>'Attempt قبلاً با نتیجه یا Evidence دیگری بسته شده است.','current_state'=>$existingState,'terminal'=>true,'requires_human_resolution'=>in_array($existingState,['unknown','recovery_hold'],true)&&empty($attempt['job_resolved_at'])],409);
                json_response(['success'=>true,'status'=>$status,'physical_print_confirmed'=>false,'idempotent'=>true]);
            }
            if($existingState!=='started'){$pdo->rollBack();json_response(['success'=>false,'code'=>'invalid_transition','message'=>'Report فقط بعد از Start پذیرفته می‌شود.','current_state'=>$existingState,'terminal'=>false,'requires_human_resolution'=>false],409);}
            $attemptNo=(int)$attempt['attempt_no'];$cycleAttemptNo=max(1,(int)($attempt['cycle_attempt_no']??1));
            $pdo->prepare("UPDATE print_attempts SET state=?,report_request_id=?,report_request_hash=?,spooler_job_id=?,submitted_at=IF(?='submitted',NOW(),submitted_at),finished_at=NOW(),outcome=?,error_code=?,error_message=? WHERE id=?")
                ->execute([$status,$requestId,$requestHash,$spooler?:null,$status,$status,$errorCode?:null,$errorMessage?:null,$attemptId]);
            if($status==='submitted'){
                $pdo->prepare("UPDATE print_jobs SET status='submitted',submitted_at=NOW(),last_error_code=NULL,last_error=NULL,next_attempt_at=NULL WHERE id=?")->execute([(int)$attempt['job_id']]);
                $pdo->prepare("UPDATE print_agents SET last_submission_at=NOW(),last_seen_at=NOW(),last_error=NULL WHERE id=?")->execute([$agentId]);
                $pdo->commit();json_response(['success'=>true,'status'=>'submitted','physical_print_confirmed'=>false,'idempotent'=>false]);
            }
            if($status==='unknown'||$status==='recovery_hold'){
                $pdo->prepare("UPDATE print_jobs SET status=?,last_error_code=?,last_error=?,next_attempt_at=NULL WHERE id=?")->execute([$status,$errorCode?:$status,$errorMessage?:'نتیجه پس از Submission Fence قابل اثبات نیست.',(int)$attempt['job_id']]);
                $pdo->commit();json_response(['success'=>true,'status'=>$status,'requires_human_resolution'=>true,'idempotent'=>false]);
            }
            // failed is safe only before the submission fence; the Agent explicitly classifies it as retryable or terminal.
            if($retryable && $cycleAttemptNo<PRINT_V4_MAX_ATTEMPTS){
                $delay=min(30,(2 ** max(1,$cycleAttemptNo)))+random_int(0,4);
                $nextAttempt=date('Y-m-d H:i:s',time()+$delay);
                $pdo->prepare("UPDATE print_jobs SET status='pending',claimed_by_agent_id=NULL,claimed_at=NULL,accepted_at=NULL,local_receipt_id=NULL,next_attempt_at=?,last_error_code=?,last_error=? WHERE id=?")->execute([$nextAttempt,$errorCode?:'pre_submit_failure',$errorMessage?:'خطای اثبات‌شده پیش از ارسال؛ Retry ایمن زمان‌بندی شد.',(int)$attempt['job_id']]);
                $pdo->commit();json_response(['success'=>true,'status'=>'pending','retry_scheduled'=>true,'retry_after_seconds'=>$delay,'idempotent'=>false]);
            }
            $pdo->prepare("UPDATE print_jobs SET status='failed',last_error_code=?,last_error=?,next_attempt_at=NULL WHERE id=?")->execute([$errorCode?:'pre_submit_failure',$errorMessage?:'خطای قطعی پیش از ارسال.',(int)$attempt['job_id']]);
            $pdo->commit();json_response(['success'=>true,'status'=>'failed','requires_human_resolution'=>true,'idempotent'=>false]);
        }

    }

    json_response(['success'=>false,'code'=>'unknown_action','message'=>'عملیات Print API v4 شناخته نشد.'],404);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    error_log('print api v4 action='.$action.' class='.get_class($e).' message='.text_substr($e->getMessage(),0,240));
    if($e->getMessage()==='Print Attempt destination snapshot is missing or invalid.')
        json_response(['success'=>false,'code'=>'destination_snapshot_invalid','message'=>'Snapshot مقصد این Attempt معتبر نیست؛ ادامه خودکار برای جلوگیری از چاپ اشتباه متوقف شد.'],409);
    if(in_array($e->getMessage(),['Print Claim response snapshot is invalid.','Print Claim response snapshot is incomplete.'],true))
        json_response(['success'=>false,'code'=>'claim_snapshot_invalid','message'=>'Snapshot پایدار Claim معتبر/کامل نیست؛ ادامه خودکار برای جلوگیری از تغییر مالکیت متوقف شد.'],409);
    if($e instanceof PDOException && (string)$e->getCode()==='23000' && (int)($e->errorInfo[1]??0)===1062){
        $constraint=(string)($e->errorInfo[2]??'');
        if(str_contains($constraint,'uq_print_attempt_agent_receipt'))json_response(['success'=>false,'code'=>'local_receipt_conflict','message'=>'Receipt محلی قبلاً استفاده شده است.'],409);
        if(str_contains($constraint,'uq_print_attempt_')&&str_contains($constraint,'_request'))json_response(['success'=>false,'code'=>'request_id_conflict','message'=>'request_id هم‌زمان برای Attempt دیگری ثبت شده است.'],409);
        json_response(['success'=>false,'code'=>'db_integrity_conflict','message'=>'تعارض یکتایی پایگاه داده رخ داد و هیچ state جدیدی commit نشد.'],409);
    }
    if(print_v4_db_transient($e)){
        header('Retry-After: 1');
        json_response(['success'=>false,'code'=>'db_transient','message'=>'پایگاه داده موقتاً در دسترس نیست؛ همان request_id را دوباره ارسال کنید.'],503);
    }
    json_response(['success'=>false,'code'=>'internal_error','message'=>'عملیات Print API انجام نشد.'],500);
}
