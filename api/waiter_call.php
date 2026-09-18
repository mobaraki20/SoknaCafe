<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/push.php';
require_once dirname(__DIR__) . '/includes/maintenance.php';
maintenance_guard_json();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false], 405);
$data = request_json();
if (!csrf_valid($data['csrf_token'] ?? null)) {
    json_response(['success' => false, 'message' => customer_message('page_expired')], 419);
}

$action = (string)($data['action'] ?? 'status');
$tableToken = trim((string)($data['table_token'] ?? ''));
$publicTableId = (int)($data['public_table_id'] ?? 0);
$isPublicRequest = $tableToken === '' && $publicTableId > 0;
if ($action === 'create' && !$isPublicRequest && !waiter_call_allowed(false)) {
    json_response(['success' => false, 'message' => customer_message('waiter_disabled')], 503);
}
if ($action === 'create' && $isPublicRequest && !waiter_call_allowed(true)) {
    json_response(['success' => false, 'message' => 'فراخوان گارسون از منوی عمومی فعال نیست.'], 403);
}

$sessionToken = trim((string)($data['session_token'] ?? ''));
$deviceToken = trim((string)($data['device_token'] ?? ''));
$clientToken = trim((string)($data['client_token'] ?? ''));
$code = trim((string)($data['call_code'] ?? ''));

if ($isPublicRequest) {
    $stmt = db()->prepare('SELECT id,name FROM cafe_tables WHERE id=? AND active=1 LIMIT 1');
    $stmt->execute([$publicTableId]);
} else {
    $stmt = db()->prepare('SELECT id,name FROM cafe_tables WHERE access_token=? AND active=1 LIMIT 1');
    $stmt->execute([$tableToken]);
}
$table = $stmt->fetch();
if (!$table) json_response(['success' => false, 'message' => $isPublicRequest ? 'میز انتخاب‌شده فعال نیست.' : customer_message('invalid_qr')], 404);

$session = null;
if (!$isPublicRequest && table_sessions_enabled() && $sessionToken !== '') {
    $sessionStmt = db()->prepare("SELECT id,public_token FROM table_sessions WHERE public_token=? AND table_id=? AND status='active' LIMIT 1");
    $sessionStmt->execute([$sessionToken, $table['id']]);
    $session = $sessionStmt->fetch() ?: null;
}

if ($action === 'create') {
    if (strlen($clientToken) < 16 || strlen($clientToken) > 80 || strlen($deviceToken) < 16 || strlen($deviceToken) > 80) {
        json_response(['success' => false, 'message' => 'درخواست کامل نیست.'], 422);
    }

    if ($isPublicRequest) {
        $deviceRecent = db()->prepare('SELECT COUNT(*) FROM waiter_calls WHERE device_token=? AND created_at>=DATE_SUB(NOW(),INTERVAL 1 MINUTE)');
        $deviceRecent->execute([$deviceToken]);
        if ((int)$deviceRecent->fetchColumn() >= 3) {
            json_response(['success' => false, 'message' => 'تعداد درخواست‌ها زیاد شده؛ یک دقیقه دیگر دوباره امتحان کن.'], 429);
        }
    }

    $existingByToken = db()->prepare('SELECT public_code,status FROM waiter_calls WHERE client_token=? LIMIT 1');
    $existingByToken->execute([$clientToken]);
    if ($found = $existingByToken->fetch()) {
        json_response(['success' => true, 'call_code' => $found['public_code'], 'status' => $found['status'], 'duplicate' => true, 'owned' => true]);
    }

    $pdo = db();
    $active = null;
    try {
        $pdo->beginTransaction();
        // Locking the table row serializes simultaneous call creation for the same table.
        $tableLock = $pdo->prepare('SELECT id FROM cafe_tables WHERE id=? FOR UPDATE');
        $tableLock->execute([$table['id']]);

        $active = $pdo->prepare("SELECT id,public_code,status FROM waiter_calls WHERE table_id=? AND status IN('new','accepted') ORDER BY id DESC LIMIT 1");
        $active->execute([$table['id']]);
        if ($row = $active->fetch()) {
            $pdo->commit();
            json_response(['success' => true, 'call_code' => $row['public_code'], 'status' => $row['status'], 'shared' => true, 'owned' => false]);
        }

        $recent = $pdo->prepare('SELECT COUNT(*) FROM waiter_calls WHERE table_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL 1 MINUTE)');
        $recent->execute([$table['id']]);
        if ((int)$recent->fetchColumn() >= 3) {
            $pdo->rollBack();
            json_response(['success' => false, 'message' => 'چند لحظه صبر کن و دوباره امتحان کن.'], 429);
        }

        $publicCode = 'W' . date('ymd') . strtoupper(bin2hex(random_bytes(4)));
        $business = business_assignment();
        $insert = $pdo->prepare('INSERT INTO waiter_calls(public_code,client_token,device_token,table_id,session_id,status,active_table_guard,business_date,business_shift_key,business_shift_label,business_cutoff_snapshot) VALUES(?,?,?,?,?,"new",?,?,?,?,?)');
        $insert->execute([$publicCode, $clientToken, $deviceToken ?: null, $table['id'], $session['id'] ?? null, $table['id'], (string)$business['business_date'], (string)$business['shift_key'], (string)$business['shift_label'], (string)$business['cutoff']]);
        if ($session && $deviceToken !== '') register_session_client((int)$session['id'], $deviceToken);
        push_enqueue_event_tx($pdo, 'waiter_call', [
            'title' => 'فراخوان تازه از ' . $table['name'],
            'body' => 'مهمان درخواست حضور گارسون ثبت کرده است.',
            'url' => asset('operator/index.php') . '?attention_filter=calls&call=' . rawurlencode($publicCode),
            'tag' => 'call-' . $publicCode,
            'call_id' => (int)$pdo->lastInsertId(),
        ]);
        $pdo->commit();
        json_response(['success' => true, 'call_code' => $publicCode, 'status' => 'new', 'owned' => true]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ((string)$e->getCode() === '23000') {
            $activeLookup = db()->prepare("SELECT public_code,status FROM waiter_calls WHERE table_id=? AND status IN('new','accepted') ORDER BY id DESC LIMIT 1");
            $activeLookup->execute([$table['id']]);
            if ($row = $activeLookup->fetch()) {
                json_response(['success' => true, 'call_code' => $row['public_code'], 'status' => $row['status'], 'shared' => true, 'owned' => false]);
            }
        }
        error_log('waiter create PDO: ' . $e->getMessage());
        json_response(['success' => false, 'message' => customer_message('waiter_connection_error')], 500);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('waiter create: ' . $e->getMessage());
        json_response(['success' => false, 'message' => customer_message('waiter_connection_error')], 500);
    }
}

if ($action === 'cancel') {
    if ($clientToken === '' || $code === '') {
        json_response(['success' => false, 'message' => 'این درخواست از همین گوشی ثبت نشده.'], 403);
    }
    $update = db()->prepare("UPDATE waiter_calls SET status='cancelled',active_table_guard=NULL,cancelled_at=NOW(),cancel_reason='customer' WHERE public_code=? AND table_id=? AND client_token=? AND status='new'");
    $update->execute([$code, $table['id'], $clientToken]);
    json_response(['success' => true, 'status' => $update->rowCount() ? 'cancelled' : 'unchanged']);
}

if ($code === '') {
    $find = db()->prepare("SELECT public_code,status,updated_at FROM waiter_calls WHERE table_id=? AND status IN('new','accepted') ORDER BY id DESC LIMIT 1");
    $find->execute([$table['id']]);
} else {
    $find = db()->prepare('SELECT public_code,status,updated_at FROM waiter_calls WHERE public_code=? AND table_id=? LIMIT 1');
    $find->execute([$code, $table['id']]);
}
$row = $find->fetch();
if (!$row) json_response(['success' => true, 'status' => 'none']);

$owned = false;
if ($clientToken !== '' && $code !== '') {
    $owner = db()->prepare('SELECT COUNT(*) FROM waiter_calls WHERE public_code=? AND table_id=? AND client_token=?');
    $owner->execute([$code, $table['id'], $clientToken]);
    $owned = (bool)$owner->fetchColumn();
}
json_response([
    'success' => true,
    'call_code' => $row['public_code'],
    'status' => $row['status'],
    'updated_at' => $row['updated_at'],
    'owned' => $owned,
]);
