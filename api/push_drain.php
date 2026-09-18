<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/push.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success'=>false],405);
$data = request_json();
if (!csrf_valid($data['csrf_token'] ?? null)) json_response(['success'=>false,'message'=>'نشست صفحه منقضی شده است.'],419);

try {
    // Small opportunistic drain: enough for live operations without turning normal page traffic into a queue worker.
    $result = push_process_queue(2);
    json_response(['success'=>true,'processed'=>(int)($result['processed']??0),'sent'=>(int)($result['sent']??0),'failed'=>(int)($result['failed']??0),'retried'=>(int)($result['retried']??0)]);
} catch (Throwable $e) {
    error_log('push opportunistic drain: ' . $e->getMessage());
    json_response(['success'=>false,'message'=>'پردازش صف اعلان انجام نشد.'],503);
}
