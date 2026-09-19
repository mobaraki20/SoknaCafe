<?php
declare(strict_types=1);
require_once __DIR__ . '/relay_protocol.php';

final class SoknaRelayBusinessRejection extends RuntimeException
{
    public function __construct(
        public string $errorCode,
        public array $result = []
    ) { parent::__construct($errorCode); }
}

function sokna_relay_dispatch_registry(): array
{
    require_once __DIR__ . '/guest_order_service.php';
    require_once __DIR__ . '/guest_order_manage_service.php';
    require_once __DIR__ . '/guest_order_status_service.php';
    require_once __DIR__ . '/guest_table_context_service.php';
    require_once __DIR__ . '/waiter_call_service.php';
    require_once __DIR__ . '/relay_actor.php';
    require_once __DIR__ . '/table_draft.php';
    return [
        'guest_order.submit' => static function(PDO $pdo, array $envelope): array {
            try {
                return guest_order_commit_tx($pdo, is_array($envelope['payload'] ?? null) ? $envelope['payload'] : []);
            } catch (GuestOrderException $e) {
                throw new SoknaRelayBusinessRejection(
                    $e->errorCode,
                    array_merge(['success'=>false,'code'=>$e->errorCode,'message'=>$e->getMessage()],$e->details)
                );
            }
        },
        'guest_order.list' => static function(PDO $pdo, array $envelope): array {
            try {
                return guest_order_manage_list($pdo, is_array($envelope['payload'] ?? null) ? $envelope['payload'] : []);
            } catch (GuestOrderEditException $e) {
                throw new SoknaRelayBusinessRejection($e->errorCode,array_merge(['success'=>false,'code'=>$e->errorCode,'message'=>$e->getMessage()],$e->details));
            }
        },
        'guest_order.status' => static function(PDO $pdo, array $envelope): array {
            try {
                return guest_order_status_lookup($pdo, is_array($envelope['payload'] ?? null) ? $envelope['payload'] : []);
            } catch (GuestOrderStatusException $e) {
                throw new SoknaRelayBusinessRejection($e->errorCode,['success'=>false,'code'=>$e->errorCode,'message'=>$e->getMessage()]);
            }
        },
        'guest_table.context' => static function(PDO $pdo, array $envelope): array {
            try {
                return guest_table_context($pdo,is_array($envelope['payload'] ?? null)?$envelope['payload']:[]);
            } catch (GuestTableContextException $e) {
                throw new SoknaRelayBusinessRejection($e->errorCode,['success'=>false,'code'=>$e->errorCode,'message'=>$e->getMessage()]);
            }
        },
        'order.edit' => static function(PDO $pdo, array $envelope): array {
            try {
                $payload=is_array($envelope['payload'] ?? null) ? $envelope['payload'] : [];
                $payload['action']='update';
                return guest_order_manage_mutate_tx($pdo,$payload);
            } catch (GuestOrderEditException $e) {
                throw new SoknaRelayBusinessRejection($e->errorCode,array_merge(['success'=>false,'code'=>$e->errorCode,'message'=>$e->getMessage()],$e->details));
            }
        },
        'order.cancel' => static function(PDO $pdo, array $envelope): array {
            try {
                $payload=is_array($envelope['payload'] ?? null) ? $envelope['payload'] : [];
                $payload['action']='cancel';
                return guest_order_manage_mutate_tx($pdo,$payload);
            } catch (GuestOrderEditException $e) {
                throw new SoknaRelayBusinessRejection($e->errorCode,array_merge(['success'=>false,'code'=>$e->errorCode,'message'=>$e->getMessage()],$e->details));
            }
        },
        'table_draft.get' => static function(PDO $pdo, array $envelope): array {
            try {
                $actor=sokna_relay_actor_locked($pdo,(string)($envelope['actor_projection_id']??''));
                $payload=is_array($envelope['payload']??null)?$envelope['payload']:[];
                return table_draft_get($pdo,(int)($payload['table_id']??0),$actor);
            } catch (TableDraftException $e) {
                throw new SoknaRelayBusinessRejection($e->errorCode,array_merge(['success'=>false,'code'=>$e->errorCode,'message'=>$e->getMessage()],$e->details));
            } catch (RuntimeException $e) {
                throw new SoknaRelayBusinessRejection('actor_invalid',['success'=>false,'code'=>'actor_invalid','message'=>$e->getMessage()]);
            }
        },
        'table_draft.create' => static function(PDO $pdo, array $envelope): array {
            try {
                $actor=sokna_relay_actor_locked($pdo,(string)($envelope['actor_projection_id']??''));
                $payload=is_array($envelope['payload']??null)?$envelope['payload']:[];
                $payload['expected_version']=0;
                return table_draft_save_tx($pdo,$payload,$actor);
            } catch (TableDraftException $e) {
                throw new SoknaRelayBusinessRejection($e->errorCode,array_merge(['success'=>false,'code'=>$e->errorCode,'message'=>$e->getMessage()],$e->details));
            } catch (RuntimeException $e) {
                throw new SoknaRelayBusinessRejection('actor_invalid',['success'=>false,'code'=>'actor_invalid','message'=>$e->getMessage()]);
            }
        },
        'table_draft.edit' => static function(PDO $pdo, array $envelope): array {
            try {
                $actor=sokna_relay_actor_locked($pdo,(string)($envelope['actor_projection_id']??''));
                return table_draft_save_tx($pdo,is_array($envelope['payload']??null)?$envelope['payload']:[],$actor);
            } catch (TableDraftException $e) {
                throw new SoknaRelayBusinessRejection($e->errorCode,array_merge(['success'=>false,'code'=>$e->errorCode,'message'=>$e->getMessage()],$e->details));
            } catch (RuntimeException $e) {
                throw new SoknaRelayBusinessRejection('actor_invalid',['success'=>false,'code'=>'actor_invalid','message'=>$e->getMessage()]);
            }
        },
        'table_draft.cancel' => static function(PDO $pdo, array $envelope): array {
            try {
                $actor=sokna_relay_actor_locked($pdo,(string)($envelope['actor_projection_id']??''));
                return table_draft_cancel_tx($pdo,is_array($envelope['payload']??null)?$envelope['payload']:[],$actor);
            } catch (TableDraftException $e) {
                throw new SoknaRelayBusinessRejection($e->errorCode,array_merge(['success'=>false,'code'=>$e->errorCode,'message'=>$e->getMessage()],$e->details));
            } catch (RuntimeException $e) {
                throw new SoknaRelayBusinessRejection('actor_invalid',['success'=>false,'code'=>'actor_invalid','message'=>$e->getMessage()]);
            }
        },
        'table_draft.finalize' => static function(PDO $pdo, array $envelope): array {
            try {
                $actor=sokna_relay_actor_locked($pdo,(string)($envelope['actor_projection_id']??''));
                $result=table_draft_finalize_tx($pdo,is_array($envelope['payload']??null)?$envelope['payload']:[],$actor);
                unset($result['_after_commit_order_id']);
                return $result;
            } catch (TableDraftException $e) {
                throw new SoknaRelayBusinessRejection($e->errorCode,array_merge(['success'=>false,'code'=>$e->errorCode,'message'=>$e->getMessage()],$e->details));
            } catch (RuntimeException $e) {
                throw new SoknaRelayBusinessRejection('actor_invalid',['success'=>false,'code'=>'actor_invalid','message'=>$e->getMessage()]);
            }
        },
        'waiter_call.create' => static function(PDO $pdo, array $envelope): array {
            try {
                $payload=is_array($envelope['payload'] ?? null) ? $envelope['payload'] : [];
                return waiter_call_create_tx($pdo,$payload);
            } catch (WaiterCallException $e) {
                throw new SoknaRelayBusinessRejection(
                    $e->errorCode,
                    array_merge(['success'=>false,'code'=>$e->errorCode,'message'=>$e->getMessage()],$e->details)
                );
            }
        },
        'waiter_call.status' => static function(PDO $pdo, array $envelope): array {
            try {
                return waiter_call_status($pdo,is_array($envelope['payload'] ?? null)?$envelope['payload']:[]);
            } catch (WaiterCallException $e) {
                throw new SoknaRelayBusinessRejection($e->errorCode,array_merge(['success'=>false,'code'=>$e->errorCode,'message'=>$e->getMessage()],$e->details));
            }
        },
        'waiter_call.cancel' => static function(PDO $pdo, array $envelope): array {
            try {
                return waiter_call_cancel_tx($pdo,is_array($envelope['payload'] ?? null)?$envelope['payload']:[]);
            } catch (WaiterCallException $e) {
                throw new SoknaRelayBusinessRejection($e->errorCode,array_merge(['success'=>false,'code'=>$e->errorCode,'message'=>$e->getMessage()],$e->details));
            }
        },
    ];
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

        // Expiry prevents a NEW business mutation, but must not hide a business
        // result already committed before an ACK/network break. Existing dedupe
        // state is therefore resolved above before this fence is evaluated.
        if ((int)$validation['expires_ts'] <= $now) {
            $pdo->commit();
            return ['state'=>'expired','error_code'=>'request_expired','result'=>[],'deduplicated'=>false];
        }

        $insert = $pdo->prepare('INSERT INTO relay_processed_requests(request_id,request_hash,kind,status,created_at,updated_at) VALUES(?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)');
        $insert->execute([$requestId,$requestHash,(string)$envelope['kind'],'processing']);

        try {
            $result = $handler($pdo, $envelope);
            if (!is_array($result)) $result = ['ok'=>true];
            $encoded = json_encode($result, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            if (!is_string($encoded)) throw new RuntimeException('Relay result encoding failed.');
            $update = $pdo->prepare('UPDATE relay_processed_requests SET status=?,result_json=?,error_code=NULL,updated_at=CURRENT_TIMESTAMP WHERE request_id=?');
            $update->execute(['committed',$encoded,$requestId]);
            $pdo->commit();
            return ['state'=>'committed','error_code'=>'','result'=>$result,'deduplicated'=>false];
        } catch (SoknaRelayBusinessRejection $e) {
            $encoded = json_encode($e->result, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            if (!is_string($encoded)) throw new RuntimeException('Relay rejection result encoding failed.');
            $update = $pdo->prepare('UPDATE relay_processed_requests SET status=?,result_json=?,error_code=?,updated_at=CURRENT_TIMESTAMP WHERE request_id=?');
            $update->execute(['rejected',$encoded,$e->errorCode,$requestId]);
            $pdo->commit();
            return ['state'=>'rejected','error_code'=>$e->errorCode,'result'=>$e->result,'deduplicated'=>false];
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
