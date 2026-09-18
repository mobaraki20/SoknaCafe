<?php
declare(strict_types=1);
require_once __DIR__ . '/relay_protocol.php';

function sokna_relay_dispatch_registry(): array
{
    return [];
}

function sokna_relay_processed_row(PDO $pdo, string $requestId, bool $forUpdate = false): ?array
{
    $sql = 'SELECT request_id,request_hash,status,result_json,error_code FROM relay_processed_requests WHERE request_id=?';
    if ($forUpdate && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') $sql .= ' FOR UPDATE';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$requestId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function sokna_relay_decode_result(?string $json): array
{
    if (!$json) return [];
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

function sokna_relay_process_claim(PDO $pdo, array $claim, ?array $registry = null, ?int $now = null): array
{
    $envelope = is_array($claim['envelope'] ?? null) ? $claim['envelope'] : [];
    $allowSynthetic = $registry !== null && array_key_exists('system.synthetic_commit', $registry);
    $validation = sokna_relay_validate_realtime_envelope($envelope, $allowSynthetic);
    if (!$validation['ok']) return ['state'=>'rejected','error_code'=>'invalid_envelope','result'=>['fields'=>$validation['errors']]];
    $now ??= time();
    if ((int)$validation['expires_ts'] <= $now) return ['state'=>'expired','error_code'=>'request_expired','result'=>[]];

    $requestId = (string)$envelope['request_id'];
    $requestHash = sokna_relay_request_hash($envelope);
    $registry ??= sokna_relay_dispatch_registry();
    $handler = $registry[(string)$envelope['kind']] ?? null;
    if (!is_callable($handler)) return ['state'=>'rejected','error_code'=>'unsupported_kind','result'=>[]];

    $pdo->beginTransaction();
    try {
        $existing = sokna_relay_processed_row($pdo, $requestId, true);
        if ($existing) {
            if (!hash_equals((string)$existing['request_hash'], $requestHash)) {
                $pdo->rollBack();
                return ['state'=>'rejected','error_code'=>'request_id_conflict','result'=>[]];
            }
            if (in_array((string)$existing['status'], ['committed','rejected'], true)) {
                $pdo->commit();
                return [
                    'state'=>(string)$existing['status'],
                    'error_code'=>(string)($existing['error_code'] ?? ''),
                    'result'=>sokna_relay_decode_result((string)($existing['result_json'] ?? '')),
                    'deduplicated'=>true,
                ];
            }
            throw new RuntimeException('Relay request is already processing.');
        }

        $insert = $pdo->prepare('INSERT INTO relay_processed_requests(request_id,request_hash,kind,status,created_at,updated_at) VALUES(?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)');
        $insert->execute([$requestId,$requestHash,(string)$envelope['kind'],'processing']);
        $result = $handler($pdo, $envelope);
        if (!is_array($result)) $result = ['ok'=>true];
        $encoded = json_encode($result, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) throw new RuntimeException('Relay result encoding failed.');
        $update = $pdo->prepare('UPDATE relay_processed_requests SET status=?,result_json=?,error_code=NULL,updated_at=CURRENT_TIMESTAMP WHERE request_id=?');
        $update->execute(['committed',$encoded,$requestId]);
        $pdo->commit();
        return ['state'=>'committed','error_code'=>'','result'=>$result,'deduplicated'=>false];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
