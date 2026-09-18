<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false], 405);
$data = request_json();
if (!csrf_valid($data['csrf_token'] ?? null)) {
    json_response(['success' => false, 'message' => customer_message('page_expired')], 419);
}
$orderCode = trim((string)($data['order_code'] ?? ''));
$clientToken = trim((string)($data['client_token'] ?? ''));
if ($orderCode === '' || $clientToken === '' || strlen($orderCode) > 32 || strlen($clientToken) > 80) {
    json_response(['success' => false, 'message' => 'اطلاعات پیگیری معتبر نیست.'], 422);
}
$stmt = db()->prepare("SELECT o.status,o.accepted_at,o.updated_at,o.session_id,ts.status AS session_status,ts.ended_at AS session_ended_at FROM orders o LEFT JOIN table_sessions ts ON ts.id=o.session_id WHERE o.public_code=? AND o.client_token=? LIMIT 1");
$stmt->execute([$orderCode, $clientToken]);
$order = $stmt->fetch();
if (!$order) json_response(['success' => false, 'message' => 'سفارش پیدا نشد.'], 404);
if (($order['session_status'] ?? null) === 'closed') {
    json_response([
        'success' => true,
        'expired' => true,
        'status' => 'session_closed',
        'status_label' => 'نشست این میز پایان یافته است.',
        'updated_at' => $order['session_ended_at'] ?: $order['updated_at'],
    ]);
}
json_response([
    'success' => true,
    'status' => $order['status'],
    'status_label' => order_status_label($order['status']),
    'accepted' => $order['accepted_at'] !== null || in_array((string)$order['status'], ['accounted','completed'], true),
    'updated_at' => $order['updated_at'],
]);
