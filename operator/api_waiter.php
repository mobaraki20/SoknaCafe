<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/maintenance.php';
maintenance_guard_json();
require_any_capability(['orders_floor','shift_supervision']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false], 405);
}
$data = request_json();
if (!csrf_valid($data['csrf_token'] ?? null)) {
    json_response(['success' => false, 'message' => 'صفحه رو تازه کن.'], 419);
}

$action = (string)($data['action'] ?? 'status');
if ($action === 'toggle_feature') {
    if (!user_has_capability('shift_supervision')) {
        json_response(['success'=>false,'message'=>'مسئولیت «سرپرستی شیفت» برای این کار فعال نیست.'],403);
    }
    $enabled = !setting_bool('waiter_call_enabled', true);
    $stmt = db()->prepare('INSERT INTO settings(setting_key,setting_value) VALUES("waiter_call_enabled",?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
    $stmt->execute([$enabled ? '1' : '0']);
    json_response(['success' => true, 'enabled' => $enabled]);
}

if (!user_has_capability('orders_floor')) {
    json_response(['success'=>false,'message'=>'مسئولیت «سفارش و سالن» برای رسیدگی به فراخوان فعال نیست.'],403);
}

$callId = (int)($data['call_id'] ?? 0);
$status = (string)($data['status'] ?? '');
if ($callId < 1 || !in_array($status, ['accepted', 'done', 'cancelled'], true)) {
    json_response(['success' => false, 'message' => 'درخواست درست نیست.'], 422);
}

$userId = (int)current_user()['id'];
$pdo = db();
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT w.status,w.session_id,w.accepted_by_user_id,ts.status session_status FROM waiter_calls w LEFT JOIN table_sessions ts ON ts.id=w.session_id WHERE w.id=? FOR UPDATE');
    $stmt->execute([$callId]);
    $call = $stmt->fetch();
    if (!$call) throw new RuntimeException('فراخوان پیدا نشد.');

    if ($call['session_id'] && ($call['session_status'] ?? '') !== 'active') {
        $pdo->prepare("UPDATE waiter_calls SET status='cancelled',active_table_guard=NULL,cancelled_at=NOW(),cancel_reason='table_closed',cancelled_by_user_id=? WHERE id=? AND status IN('new','accepted')")
            ->execute([$userId, $callId]);
        $pdo->commit();
        json_response(['success' => false, 'message' => 'میز بسته شده و این فراخوان هم بسته شد.'], 409);
    }

    if ($status === 'accepted') {
        if ($call['status'] === 'new') {
            $pdo->prepare("UPDATE waiter_calls SET status='accepted',accepted_by_user_id=?,accepted_at=NOW() WHERE id=?")
                ->execute([$userId, $callId]);
        } elseif ($call['status'] === 'accepted' && (int)($call['accepted_by_user_id'] ?? 0) !== $userId) {
            throw new RuntimeException('یکی از همکارا این فراخوان رو پذیرفته.');
        } elseif (!in_array($call['status'], ['new', 'accepted'], true)) {
            throw new RuntimeException('این فراخوان قبلاً بسته شده.');
        }
    } elseif ($status === 'done') {
        if (!in_array($call['status'], ['new', 'accepted'], true)) throw new RuntimeException('این فراخوان قبلاً بسته شده.');
        $pdo->prepare("UPDATE waiter_calls SET status='done',active_table_guard=NULL,accepted_by_user_id=COALESCE(accepted_by_user_id,?),accepted_at=COALESCE(accepted_at,NOW()),completed_at=NOW() WHERE id=?")
            ->execute([$userId, $callId]);
    } else {
        if (!in_array($call['status'], ['new', 'accepted'], true)) throw new RuntimeException('این فراخوان قبلاً بسته شده.');
        $pdo->prepare("UPDATE waiter_calls SET status='cancelled',active_table_guard=NULL,cancelled_at=NOW(),cancel_reason='manual',cancelled_by_user_id=? WHERE id=?")
            ->execute([$userId, $callId]);
    }

    $pdo->commit();
    json_response(['success' => true]);
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_response(['success' => false, 'message' => $e->getMessage()], 422);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('operator waiter action: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'این کار انجام نشد؛ دوباره امتحان کن.'], 500);
}
