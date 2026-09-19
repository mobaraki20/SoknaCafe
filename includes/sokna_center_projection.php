<?php
declare(strict_types=1);

/** Phase 7C outbound Cafe user projection. Cafe remains local user authority. */
function sokna_center_user_projection(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id,display_name,role,active,updated_at FROM users ORDER BY id ASC');
    $users = [];
    foreach ($stmt->fetchAll() as $row) $users[] = sokna_center_directory_public_user($row);
    $canonical = json_encode($users, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    return [
        'version'=>1,
        'source_version'=>hash('sha256', $canonical),
        'generated_at'=>gmdate('Y-m-d\\TH:i:s\\Z'),
        'users'=>$users,
    ];
}

function sokna_center_build_user_projection_token(string $sourceVersion, ?string $secret = null): string
{
    if (!preg_match('/^[a-f0-9]{64}$/', $sourceVersion)) throw new InvalidArgumentException('نسخه Projection معتبر نیست.');
    $secret = $secret ?? sokna_center_secret();
    if ($secret === '') throw new RuntimeException('اتصال مرکز سکنا هنوز تنظیم نشده است.');
    $now = time();
    return sokna_center_sign_compact([
        'issuer'=>'cafe',
        'audience'=>'center',
        'purpose'=>'user_projection',
        'context'=>'CAFE',
        'timestamp'=>$now,
        'expires_at'=>$now + 60,
        'nonce'=>sokna_center_base64url_encode(random_bytes(24)),
        'source_version'=>$sourceVersion,
    ], $secret, 'SOKNA-S2S');
}

function sokna_center_push_user_projection(PDO $pdo, int $timeoutSeconds = 5): array
{
    if (!sokna_center_connection_enabled()) return ['status'=>'disabled','source_version'=>''];
    if (!sokna_center_outbound_user_projection_supported()) return ['status'=>'unsupported','source_version'=>''];

    $projection = sokna_center_user_projection($pdo);
    $token = sokna_center_build_user_projection_token((string)$projection['source_version']);
    $response = sokna_center_http_post_form(
        sokna_center_endpoint('/api/s2s/cafe_users_sync.php'),
        [
            'token'=>$token,
            'projection'=>json_encode($projection, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),
        ],
        sokna_center_request_origin(),
        max(2, min(10, $timeoutSeconds))
    );

    $http = (int)($response['http_status'] ?? 0);
    if (!($response['transport_ok'] ?? false) || $http === 0 || $http >= 500) throw new RuntimeException('Center user projection transport failed.');
    $payload = json_decode((string)($response['raw'] ?? ''), true);
    $ack = is_array($payload) && ($payload['ok'] ?? null) === true && is_array($payload['data'] ?? null)
        ? (string)($payload['data']['source_version'] ?? '') : '';
    if ($http < 200 || $http >= 300 || !hash_equals((string)$projection['source_version'], $ack)) throw new RuntimeException('Center user projection was not acknowledged.');

    sokna_center_setting_write('sokna_center_user_projection_last_version', $ack);
    sokna_center_setting_write('sokna_center_user_projection_last_success_at', date('Y-m-d H:i:s'));
    return ['status'=>'synced','source_version'=>$ack,'count'=>count((array)$projection['users'])];
}
