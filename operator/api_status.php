<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/push.php';
require_once dirname(__DIR__) . '/includes/maintenance.php';

maintenance_guard_json();
require_capability('orders_floor');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false], 405);
$data = request_json();
if (!csrf_valid($data['csrf_token'] ?? null)) {
    json_response(['success' => false, 'message' => 'نشست صفحه منقضی شده است؛ صفحه را تازه کن.'], 419);
}

$orderId = (int)($data['order_id'] ?? 0);
$status = trim((string)($data['status'] ?? ''));
$requestId = preg_replace('/[^A-Za-z0-9._:-]/', '', trim((string)($data['request_id'] ?? ''))) ?: bin2hex(random_bytes(8));
$requestId = substr($requestId, 0, 96);
$user = current_user();
$userId = (int)$user['id'];
if ($orderId < 1 || !in_array($status, ['accounted','cancelled'], true)) {
    json_response(['success' => false, 'message' => 'این عملیات فقط برای تأیید یا رد سفارش منتظر است.', 'request_id'=>$requestId], 422);
}

$pdo = db();
$dispatchConfirmedOrder = false;
$tableId = 0;
$sessionId = 0;
try {
    $pdo->beginTransaction();
    $locked = lock_order_context($pdo,$orderId);
    $tableId=(int)$locked['table_id'];
    $sessionId=(int)$locked['session_id'];
    $lockedSession=$locked['session'];
    $row=$locked['order'];

    $oldStatus = (string)$row['status'];
    if (!guest_order_status_is_mutable($oldStatus)) {
        // A lost response must be safe to retry. Confirmed orders are never cancelled
        // through this generic endpoint; corrections belong to the audited bill flow.
        if ($oldStatus === $status && in_array($status, ['accounted','cancelled'], true)) {
            $actorName = '';
            if ((int)($row['accepted_by_user_id'] ?? 0) > 0) {
                $actorStmt = $pdo->prepare('SELECT display_name FROM users WHERE id=? LIMIT 1');
                $actorStmt->execute([(int)$row['accepted_by_user_id']]);
                $actorName = trim((string)($actorStmt->fetchColumn() ?: ''));
            }
            $pdo->commit();
            json_response([
                'success'=>true,
                'persisted'=>true,
                'idempotent'=>true,
                'request_id'=>$requestId,
                'status'=>$oldStatus,
                'current_status'=>$oldStatus,
                'status_label'=>order_status_label($oldStatus),
                'allowed_statuses'=>allowed_order_statuses_from($oldStatus),
                'message'=>$oldStatus==='cancelled' ? 'این سفارش قبلاً رد شده است.' : ($actorName!=='' ? 'این سفارش قبلاً توسط '.$actorName.' تأیید شده است.' : 'این سفارش قبلاً تأیید شده است.'),
                'actor_name'=>$actorName,
            ]);
        }
        throw new RuntimeException('این سفارش دیگر منتظر تأیید نیست؛ وضعیت فعلی: '.order_status_label($oldStatus));
    }

    if ($sessionId > 0 && accommodation_transfer_blocks_invoice_edit($sessionId)) {
        $transfer = accommodation_transfer_by_session($sessionId, true);
        if ($transfer && empty($transfer['resolved_at'])) {
            throw new RuntimeException('این سفارش به انتقال اقامت متصل است؛ ابتدا وضعیت انتقال را تعیین تکلیف کن.');
        }
    }

    if (!in_array($status, allowed_order_statuses_from($oldStatus), true)) {
        throw new RuntimeException('این تغییر وضعیت با روند سفارش سازگار نیست.');
    }

    if ($status === 'cancelled') {
        // Reject only this selected round. Other pending rounds remain independent.
        $pdo->prepare("UPDATE orders SET status='cancelled' WHERE id=? AND status=?")->execute([$orderId,$oldStatus]);
        $pdo->prepare("INSERT INTO order_status_history(order_id,from_status,to_status,actor_user_id) VALUES(?,?,'cancelled',?)")
            ->execute([$orderId,$oldStatus,$userId]);
        if (($lockedSession['status'] ?? '') === 'pending') {
            $remainingStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE session_id=? AND status IN('pending_approval','new')");
            $remainingStmt->execute([$sessionId]);
            if ((int)$remainingStmt->fetchColumn() === 0) {
                // Keep one owner for releasing a rejected pending visit and its waiter calls/guards.
                close_table_session($sessionId, $userId, 'pending_rejected');
            }
        }
    } else {
        $dispatchConfirmedOrder = confirm_order_locked($pdo, [
            'id'=>$orderId,
            'session_id'=>$sessionId,
            'status'=>$oldStatus,
        ], $userId);
    }

    $verifyStmt = $pdo->prepare('SELECT status,updated_at FROM orders WHERE id=? LIMIT 1');
    $verifyStmt->execute([$orderId]);
    $verified = $verifyStmt->fetch();
    if (!$verified || (string)$verified['status'] !== $status) {
        throw new RuntimeException('وضعیت سفارش در پایگاه داده تأیید نشد.');
    }

    audit_log_write('order.status_changed','order',$orderId,[
        'from_status'=>$oldStatus,
        'to_status'=>$status,
        'request_id'=>$requestId,
        'session_id'=>$sessionId,
        'table_id'=>$tableId,
    ],$userId);

    if ($dispatchConfirmedOrder) {
        $tableName=(string)($locked['table']['name']??'میز');
        order_side_effect_best_effort_tx($pdo,'push order confirmation',static fn()=>push_enqueue_confirmed_order_tx($pdo,$orderId,$tableName,$requestId));
    }

    $pdo->commit();
    if ($dispatchConfirmedOrder) inventory_register_after_response_order($orderId);

    json_response([
        'success' => true,
        'persisted' => true,
        'idempotent' => false,
        'request_id' => $requestId,
        'status' => $status,
        'current_status' => (string)$verified['status'],
        'updated_at' => (string)($verified['updated_at'] ?? ''),
        'status_label' => order_status_label($status),
        'allowed_statuses' => allowed_order_statuses_from($status),
        'message' => $status === 'cancelled' ? 'سفارش رد شد و از صف تأیید خارج شد.' : 'سفارش تأیید و برای آماده‌سازی ثبت شد.',
        'actor_name' => (string)($user['display_name'] ?? ''),
    ]);
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_response(['success'=>false,'message'=>$e->getMessage(),'request_id'=>$requestId],409);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Cafe status update [' . $requestId . ']: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'تغییر وضعیت انجام نشد. کد پیگیری: ' . $requestId, 'request_id'=>$requestId], 500);
}
