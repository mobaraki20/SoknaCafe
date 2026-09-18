<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';

header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    json_response(['ok'=>false,'error'=>['code'=>'method_not_allowed','message'=>'این درخواست پشتیبانی نمی‌شود.']], 405);
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = (int)($_GET['per_page'] ?? 50);
if ($perPage < 1 || $perPage > 100) {
    json_response(['ok'=>false,'error'=>['code'=>'invalid_pagination','message'=>'صفحه‌بندی معتبر نیست.']], 422);
}

try {
    sokna_center_verify_user_directory_request($page, $perPage);
} catch (SoknaCenterAuthException $e) {
    $status = $e->reasonCode === 'integration_disabled' ? 503 : 401;
    json_response(['ok'=>false,'error'=>['code'=>'unauthorized','message'=>'درخواست معتبر نیست.']], $status);
} catch (Throwable) {
    json_response(['ok'=>false,'error'=>['code'=>'temporarily_unavailable','message'=>'سرویس موقتاً در دسترس نیست.']], 503);
}

try {
    $pdo = db();
    $total = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $offset = ($page - 1) * $perPage;
    $stmt = $pdo->prepare('SELECT id,display_name,role,active,updated_at FROM users ORDER BY active DESC,display_name ASC,id ASC LIMIT ? OFFSET ?');
    $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();

    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $items[] = sokna_center_directory_public_user($row);
    }
    $pages = max(1, (int)ceil($total / $perPage));
    json_response([
        'ok'=>true,
        'data'=>$items,
        'pagination'=>[
            'page'=>$page,
            'per_page'=>$perPage,
            'total'=>$total,
            'pages'=>$pages,
            'has_next'=>$page < $pages,
        ],
    ]);
} catch (Throwable) {
    json_response(['ok'=>false,'error'=>['code'=>'temporarily_unavailable','message'=>'سرویس موقتاً در دسترس نیست.']], 503);
}
