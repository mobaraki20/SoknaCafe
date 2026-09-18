<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/push.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success'=>false],405);
$data = request_json();
$queueId = (int)($data['queue_id'] ?? 0);
$token = trim((string)($data['token'] ?? ''));
if ($queueId < 1 || $token === '') json_response(['success'=>false,'message'=>'درخواست پردازش اعلان معتبر نیست.'],422);

try {
    $claims = push_kick_token_decode($token);
    if ((int)($claims['qid'] ?? 0) !== $queueId) throw new RuntimeException('توکن پردازش اعلان با رویداد همخوان نیست.');
    $result = push_process_queue(1, $queueId);
    json_response(['success'=>true,'processed'=>(int)($result['processed']??0)]);
} catch (Throwable $e) {
    error_log('push kick: ' . $e->getMessage());
    json_response(['success'=>false,'message'=>'پردازش اعلان انجام نشد.'],403);
}
