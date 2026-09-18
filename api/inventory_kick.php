<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/inventory.php';
require_login();

if (!sokna_module_runtime_ready('inventory')) {
    json_response(['success'=>true,'processed'=>0,'inventory_enabled'=>sokna_module_enabled('inventory')]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success'=>false],405);
$data = request_json();
if (!csrf_valid($data['csrf_token'] ?? null)) json_response(['success'=>false],419);
$orderId = (int)($data['order_id'] ?? 0);
if ($orderId < 1) json_response(['success'=>false],422);

// Do not hold the user's PHP session while best-effort materialization touches the DB.
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
try {
    $result = inventory_process_order_events_for_order($orderId, 5);
    json_response(['success'=>true,'processed'=>(int)($result['processed'] ?? 0)]);
} catch (Throwable $e) {
    error_log('inventory kick order=' . $orderId . ': ' . $e->getMessage());
    json_response(['success'=>false],503);
}
