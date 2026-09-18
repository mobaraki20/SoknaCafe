<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';

header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    json_response(['success'=>false,'message'=>'این درخواست پشتیبانی نمی‌شود.'], 405);
}
require_login();
$data = request_json();
if (!csrf_valid($data['csrf_token'] ?? null)) {
    json_response(['success'=>false,'message'=>'صفحه منقضی شده؛ دوباره بارگذاری کنید.'], 419);
}
$user = current_user();
$userId = (int)($user['id'] ?? 0);
if ($userId < 1 || !sokna_center_connection_enabled()) {
    json_response(['success'=>true,'allowed'=>false,'supported'=>true]);
}

try {
    $allowed = sokna_center_refresh_personnel_access($userId);
    json_response([
        'success'=>true,
        'allowed'=>$allowed === true,
        'supported'=>$allowed !== null,
    ]);
} catch (Throwable $e) {
    error_log('center personnel entitlement refresh: '.$e->getMessage());
    json_response(['success'=>false,'message'=>sokna_center_unavailable_message()], 503);
}
