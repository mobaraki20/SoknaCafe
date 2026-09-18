<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/relay_protocol.php';

$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) { http_response_code(503); header('Content-Type: application/json'); exit('{"ok":false,"error":"public_not_configured"}'); }
$publicConfig = require $configFile;

function public_db(): PDO
{
    static $pdo;
    global $publicConfig;
    if ($pdo instanceof PDO) return $pdo;
    $db = $publicConfig['db'];
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s',$db['host'],$db['port']??'3306',$db['name'],$db['charset']??'utf8mb4');
    $pdo = new PDO($dsn,$db['user'],$db['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    return $pdo;
}
function public_json(array $payload, int $status = 200): never
{
    http_response_code($status); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit;
}
function public_raw_body(): string
{
    static $raw = null;
    if ($raw === null) $raw = (string)file_get_contents('php://input');
    return $raw;
}
function public_json_body(): array
{
    $data = json_decode(public_raw_body(), true);
    if (!is_array($data)) public_json(['ok'=>false,'error'=>'invalid_json'],400);
    return $data;
}
function public_bearer_token(): string
{
    $header = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    return preg_match('/^Bearer\s+(.+)$/i',$header,$m) ? trim($m[1]) : '';
}
function public_session(): array
{
    $token=public_bearer_token();
    if($token==='')public_json(['ok'=>false,'error'=>'unauthorized'],401);
    $hash=hash('sha256',$token);
    $stmt=public_db()->prepare('SELECT s.id,s.installation_id,s.projection_id,s.expires_at,p.display_name,p.role,p.capabilities_json,p.preparation_areas_json,p.projection_version,p.active,i.remote_enabled,i.order_intake_enabled FROM public_sessions s JOIN auth_projections p ON p.installation_id=s.installation_id AND p.projection_id=s.projection_id JOIN installations i ON i.installation_id=s.installation_id WHERE s.token_hash=? LIMIT 1');
    $stmt->execute([$hash]);$row=$stmt->fetch();
    if(!$row||!(int)$row['active']||!(int)$row['remote_enabled']||strtotime((string)$row['expires_at'])<=time())public_json(['ok'=>false,'error'=>'unauthorized'],401);
    $caps=json_decode((string)$row['capabilities_json'],true);
    $areas=json_decode((string)($row['preparation_areas_json']??'[]'),true);
    $row['capabilities']=is_array($caps)?array_values(array_map('strval',$caps)):[];
    $row['preparation_areas']=is_array($areas)?array_values(array_intersect(['kitchen','bar'],array_map('strval',$areas))):[];
    return $row;
}

function public_session_has_capability(array $session,string $capability):bool
{
    $caps=is_array($session['capabilities']??null)?$session['capabilities']:[];
    if(in_array('*',$caps,true)||in_array($capability,$caps,true))return true;
    if($capability==='preparation.read'&&in_array('preparation.monitor',$caps,true))return true;
    return false;
}

function public_remote_model_capability(string $modelKey):?string
{
    return match($modelKey){
        'operations'=>'operations.read',
        'preparation'=>'preparation.read',
        'inventory'=>'inventory.read',
        'inventory_cost'=>'inventory.cost.read',
        'reports'=>'reports.read',
        default=>null,
    };
}

function public_remote_connectivity(string $installationId,int $freshSeconds=45):array
{
    $stmt=public_db()->prepare('SELECT i.remote_enabled,h.last_seen_at FROM installations i LEFT JOIN installation_heartbeats h ON h.installation_id=i.installation_id WHERE i.installation_id=? LIMIT 1');
    $stmt->execute([$installationId]);$row=$stmt->fetch()?:[];
    $last=(string)($row['last_seen_at']??'');$ts=strtotime($last)?:0;
    return [
        'remote_enabled'=>(bool)($row['remote_enabled']??false),
        'local_fresh'=>$ts>=time()-max(10,$freshSeconds),
        'last_seen_at'=>$last,
    ];
}

function public_remote_filter_preparation(array $payload,array $session):array
{
    if(public_session_has_capability($session,'preparation.monitor')||in_array('*',$session['capabilities']??[],true))return $payload;
    $areas=is_array($session['preparation_areas']??null)?$session['preparation_areas']:[];
    $allow=static fn(array $row):bool=>in_array((string)($row['area']??$row['area_key']??''),$areas,true);
    $payload['tasks']=array_values(array_filter(is_array($payload['tasks']??null)?$payload['tasks']:[],$allow));
    $payload['adjustments']=array_values(array_filter(is_array($payload['adjustments']??null)?$payload['adjustments']:[],$allow));
    return $payload;
}
function public_verify_local_signature(): string
{
    global $publicConfig;
    $installationId=trim((string)($_SERVER['HTTP_X_SOKNA_INSTALLATION']??''));
    $timestamp=trim((string)($_SERVER['HTTP_X_SOKNA_TIMESTAMP']??''));
    $nonce=trim((string)($_SERVER['HTTP_X_SOKNA_NONCE']??''));
    $signature=trim((string)($_SERVER['HTTP_X_SOKNA_SIGNATURE']??''));
    $secrets=is_array($publicConfig['installation_secrets']??null)?$publicConfig['installation_secrets']:[];
    $secret=(string)($secrets[$installationId]??''); if($installationId===''||$secret==='') public_json(['ok'=>false,'error'=>'unknown_installation'],401);
    $body=public_raw_body();
    $path=(string)(parse_url((string)($_SERVER['REQUEST_URI']??'/'),PHP_URL_PATH)?:'/');
    $skew=(int)($publicConfig['app']['relay_clock_skew_seconds']??300);
    if(!sokna_relay_verify_signature($secret,(string)($_SERVER['REQUEST_METHOD']??'GET'),$path,$timestamp,$nonce,$body,$signature,$skew)) public_json(['ok'=>false,'error'=>'bad_signature'],401);
    $pdo=public_db(); $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM request_nonces WHERE expires_at < UTC_TIMESTAMP()')->execute();
        $ins=$pdo->prepare('INSERT INTO request_nonces(installation_id,nonce,expires_at) VALUES(?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND))');
        try{$ins->execute([$installationId,$nonce,max(60,$skew*2)]);}catch(PDOException){$pdo->rollBack();public_json(['ok'=>false,'error'=>'replay_detected'],409);}
        $pdo->commit();
    } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    return $installationId;
}
function public_emergency_auth(): void
{
    global $publicConfig;
    $expected=(string)($publicConfig['app']['emergency_key']??'');
    $provided=(string)($_SERVER['HTTP_X_SOKNA_EMERGENCY_KEY']??'');
    if($expected===''||$provided===''||!hash_equals($expected,$provided)) public_json(['ok'=>false,'error'=>'unauthorized'],401);
}
function public_capability_for_kind(string $kind): string
{
    return match($kind){
        'guest_order.submit','guest_order.list','guest_order.status','guest_table.context'=>'guest.order.submit',
        'waiter_call.create','waiter_call.status','waiter_call.cancel'=>'guest.waiter_call.create',
        'settlement.commit'=>'finance.settle','preparation.mutate'=>'preparation.mutate',
        'order.edit','order.cancel'=>'orders.mutate','table_draft.create','table_draft.edit','table_draft.finalize','table_draft.cancel'=>'orders.table_draft',default=>'relay.denied',
    };
}


function public_guest_bundle(string $installationId): array
{
    $stmt=public_db()->prepare('SELECT i.installation_id,i.display_name,i.active,i.remote_enabled,i.order_intake_enabled,r.revision_id,r.snapshot_json,r.media_manifest_json,r.generated_at,a.payload_json availability_json,a.last_sync_at,h.last_seen_at heartbeat_at FROM installations i LEFT JOIN guest_active_revisions ar ON ar.installation_id=i.installation_id LEFT JOIN guest_publish_revisions r ON r.installation_id=ar.installation_id AND r.revision_id=ar.revision_id LEFT JOIN guest_availability_state a ON a.installation_id=i.installation_id LEFT JOIN installation_heartbeats h ON h.installation_id=i.installation_id WHERE i.installation_id=? LIMIT 1');
    $stmt->execute([$installationId]);
    $row=$stmt->fetch();
    if(!$row || !(int)$row['active']) return [];
    $snapshot=json_decode((string)($row['snapshot_json']??''),true);
    $manifest=json_decode((string)($row['media_manifest_json']??''),true);
    $availability=json_decode((string)($row['availability_json']??''),true);
    $row['snapshot']=is_array($snapshot)?$snapshot:[];
    $row['media_manifest']=is_array($manifest)?$manifest:[];
    $row['availability']=is_array($availability)?$availability:[];
    return $row;
}

function public_guest_table_from_token(array $snapshot,string $token): ?array
{
    if($token==='') return null;
    foreach(($snapshot['tables']??[]) as $row){
        if(is_array($row) && isset($row['token']) && hash_equals((string)$row['token'],$token)) return $row;
    }
    return null;
}

function public_guest_table_from_ref(array $snapshot,string $ref): ?array
{
    if($ref==='') return null;
    foreach(($snapshot['tables']??[]) as $row){
        if(is_array($row) && isset($row['public_ref']) && hash_equals((string)$row['public_ref'],$ref)) return $row;
    }
    return null;
}

function public_guest_action_state(array $bundle,int $freshSeconds=15): array
{
    $now=time();
    $heartbeat=strtotime((string)($bundle['heartbeat_at']??''))?:0;
    $availabilityAt=strtotime((string)($bundle['last_sync_at']??''))?:0;
    $fresh=max(5,$freshSeconds);
    $localFresh=$heartbeat>=$now-$fresh;
    $availabilityFresh=$availabilityAt>=$now-$fresh;
    $enabled=(bool)($bundle['remote_enabled']??false) && (bool)($bundle['order_intake_enabled']??false) && $localFresh && $availabilityFresh;
    return [
        'enabled'=>$enabled,
        'local_fresh'=>$localFresh,
        'availability_fresh'=>$availabilityFresh,
        'heartbeat_at'=>(string)($bundle['heartbeat_at']??''),
        'last_sync_at'=>(string)($bundle['last_sync_at']??''),
    ];
}

function public_guest_enqueue(string $installationId,array $envelope): array
{
    $validation=sokna_relay_validate_realtime_envelope($envelope);
    if(!$validation['ok']) return ['ok'=>false,'error'=>'invalid_envelope','fields'=>$validation['errors'],'status'=>400];
    $pdo=public_db();
    $hash=sokna_relay_request_hash($envelope);
    $json=sokna_relay_canonical_json($envelope);
    $expires=date('Y-m-d H:i:s',(int)$validation['expires_ts']);
    $pdo->beginTransaction();
    try{
        $q=$pdo->prepare('SELECT request_hash,state,result_json,error_code,envelope_json FROM realtime_requests WHERE installation_id=? AND request_id=? FOR UPDATE');
        $q->execute([$installationId,(string)$envelope['request_id']]);
        $existing=$q->fetch();
        if($existing){
            $oldEnvelope=json_decode((string)($existing['envelope_json']??''),true);
            $oldLogical=is_array($oldEnvelope)?[
                'kind'=>(string)($oldEnvelope['kind']??''),
                'actor_projection_id'=>(string)($oldEnvelope['actor_projection_id']??''),
                'payload'=>is_array($oldEnvelope['payload']??null)?$oldEnvelope['payload']:[],
            ]:[];
            $newLogical=[
                'kind'=>(string)($envelope['kind']??''),
                'actor_projection_id'=>(string)($envelope['actor_projection_id']??''),
                'payload'=>is_array($envelope['payload']??null)?$envelope['payload']:[],
            ];
            if(!hash_equals(hash('sha256',sokna_relay_canonical_json($oldLogical)),hash('sha256',sokna_relay_canonical_json($newLogical)))){
                $pdo->rollBack();
                return ['ok'=>false,'error'=>'request_id_conflict','status'=>409];
            }
            $pdo->commit();
            return [
                'ok'=>true,'state'=>(string)$existing['state'],'deduplicated'=>true,
                'result'=>json_decode((string)($existing['result_json']??''),true),
                'error_code'=>(string)($existing['error_code']??''),'status'=>200,
            ];
        }
        $ins=$pdo->prepare('INSERT INTO realtime_requests(installation_id,request_id,request_hash,kind,actor_projection_id,envelope_json,state,expires_at) VALUES(?,?,?,?,?,?,?,?)');
        $ins->execute([$installationId,(string)$envelope['request_id'],$hash,(string)$envelope['kind'],(string)$envelope['actor_projection_id'],$json,'queued',$expires]);
        $pdo->commit();
        return ['ok'=>true,'state'=>'queued','deduplicated'=>false,'status'=>202];
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        throw $e;
    }
}

function public_guest_media_url(string $installationId,array $manifest,string $sourcePath): string
{
    $meta=$manifest[$sourcePath]??null;
    if(!is_array($meta)) return '';
    $sha=(string)($meta['sha256']??'');
    $ext=(string)($meta['extension']??'');
    if(!preg_match('/^[a-f0-9]{64}$/',$sha)||!preg_match('/^(jpg|png|webp|gif|svg)$/',$ext)) return '';
    return '/guest/media.php?installation_id='.rawurlencode($installationId).'&sha='.rawurlencode($sha).'&ext='.rawurlencode($ext);
}
