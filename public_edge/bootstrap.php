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
    $token = public_bearer_token(); if ($token === '') public_json(['ok'=>false,'error'=>'unauthorized'],401);
    $hash = hash('sha256',$token);
    $stmt=public_db()->prepare('SELECT s.id,s.installation_id,s.projection_id,s.expires_at,p.capabilities_json,p.active,i.remote_enabled,i.order_intake_enabled FROM public_sessions s JOIN auth_projections p ON p.installation_id=s.installation_id AND p.projection_id=s.projection_id JOIN installations i ON i.installation_id=s.installation_id WHERE s.token_hash=? LIMIT 1');
    $stmt->execute([$hash]); $row=$stmt->fetch();
    if (!$row || !(int)$row['active'] || !(int)$row['remote_enabled'] || strtotime((string)$row['expires_at']) <= time()) public_json(['ok'=>false,'error'=>'unauthorized'],401);
    $caps=json_decode((string)$row['capabilities_json'],true); $row['capabilities']=is_array($caps)?$caps:[]; return $row;
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
        'guest_order.submit'=>'guest.order.submit','waiter_call.create'=>'guest.waiter_call.create','settlement.commit'=>'finance.settle','preparation.mutate'=>'preparation.mutate',
        'order.edit','order.cancel'=>'orders.mutate','table_draft.create','table_draft.edit','table_draft.finalize','table_draft.cancel'=>'orders.table_draft',default=>'relay.denied',
    };
}
