<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/push.php';
require_once dirname(__DIR__) . '/includes/waiter_call_service.php';
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
    try {
        $createData=$data;
        $createData['public_context']=$isPublicRequest;
        if($isPublicRequest){
            $tokenStmt=db()->prepare('SELECT access_token FROM cafe_tables WHERE id=? AND active=1 LIMIT 1');
            $tokenStmt->execute([$publicTableId]);
            $createData['table_token']=(string)($tokenStmt->fetchColumn()?:'');
        }
        json_response(waiter_call_create(db(),$createData));
    } catch (WaiterCallException $e) {
        json_response(array_merge(['success'=>false,'code'=>$e->errorCode,'message'=>$e->getMessage()],$e->details),$e->httpStatus);
    } catch (Throwable $e) {
        error_log('waiter create: '.$e->getMessage());
        json_response(['success'=>false,'message'=>customer_message('waiter_connection_error')],500);
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
