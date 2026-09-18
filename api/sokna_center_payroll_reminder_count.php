<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';

header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    json_response(['success'=>false,'count'=>null], 405);
}

require_login();
$data = request_json();
if (!csrf_valid($data['csrf_token'] ?? null)) {
    json_response(['success'=>false,'count'=>null], 419);
}

$user = current_user();
$userId = (int)($user['id'] ?? 0);
if ($userId < 1 || !sokna_center_connection_enabled()) {
    json_response(['success'=>true,'count'=>null]);
}

// Cafe never recreates Core HR permissions. For staff, the existing launcher entitlement
// is only a fail-closed local prerequisite; Core remains the final authority for the count.
if (($user['role'] ?? '') !== 'admin') {
    $state = sokna_center_personnel_access_state($userId);
    if (($state['state'] ?? '') !== 'allow') {
        json_response(['success'=>true,'count'=>null]);
    }
}

// Do not hold the PHP session lock while a slow/offline Core is contacted.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

try {
    $count = sokna_center_payroll_reminder_count($userId, 2);
    json_response(['success'=>true,'count'=>$count]);
} catch (SoknaCenterAuthException $e) {
    $status = (int)($e->diagnostics['http_status'] ?? 0);
    $timeout = (int)($e->diagnostics['timeout'] ?? 0);
    error_log(sprintf(
        'center payroll reminder read action=count reason=%s http_status=%d timeout=%d',
        preg_replace('/[^a-z0-9_\-]/i', '', $e->reasonCode),
        $status,
        $timeout
    ));
    json_response(['success'=>true,'count'=>null]);
} catch (Throwable) {
    error_log('center payroll reminder read action=count reason=unexpected_failure http_status=0 timeout=0');
    json_response(['success'=>true,'count'=>null]);
}
