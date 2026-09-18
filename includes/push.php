<?php
declare(strict_types=1);

function push_b64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function push_b64url_decode(string $value): string
{
    $value = strtr(trim($value), '-_', '+/');
    $padding = strlen($value) % 4;
    if ($padding) $value .= str_repeat('=', 4 - $padding);
    $decoded = base64_decode($value, true);
    if ($decoded === false) throw new RuntimeException('کلید Base64URL معتبر نیست.');
    return $decoded;
}

function push_ec_public_point(array $details, string $errorMessage): string
{
    $x = $details['ec']['x'] ?? null;
    $y = $details['ec']['y'] ?? null;
    if (!is_string($x) || !is_string($y) || strlen($x) > 32 || strlen($y) > 32) throw new RuntimeException($errorMessage);
    return "\x04" . str_pad($x, 32, "\x00", STR_PAD_LEFT) . str_pad($y, 32, "\x00", STR_PAD_LEFT);
}

function push_generate_vapid_keypair(): array
{
    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    if ($key === false) throw new RuntimeException('ساخت کلید VAPID انجام نشد.');
    $privatePem = '';
    if (!openssl_pkey_export($key, $privatePem)) throw new RuntimeException('خروجی کلید خصوصی VAPID انجام نشد.');
    $details = openssl_pkey_get_details($key);
    return ['private_pem' => $privatePem, 'public_key' => push_b64url_encode(push_ec_public_point($details, 'کلید عمومی VAPID معتبر نیست.'))];
}

function push_vapid_credentials(bool $create = true): array
{
    $pdo = db();
    $keys = ['push.vapid_public_key','push.vapid_private_pem','push.vapid_subject'];
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $stmt = $pdo->prepare("SELECT setting_key,setting_value FROM settings WHERE setting_key IN($placeholders)");
    $stmt->execute($keys);
    $found = [];
    foreach ($stmt->fetchAll() as $row) $found[(string)$row['setting_key']] = (string)$row['setting_value'];
    if (($found['push.vapid_public_key'] ?? '') !== '' && ($found['push.vapid_private_pem'] ?? '') !== '') {
        return [
            'public_key' => $found['push.vapid_public_key'],
            'private_pem' => $found['push.vapid_private_pem'],
            'subject' => $found['push.vapid_subject'] ?? 'mailto:admin@example.com',
        ];
    }
    if (!$create) return [];

    $pdo->query("SELECT GET_LOCK('cafe_push_vapid',5)")->fetchColumn();
    try {
        $stmt->execute($keys);
        $found = [];
        foreach ($stmt->fetchAll() as $row) $found[(string)$row['setting_key']] = (string)$row['setting_value'];
        if (($found['push.vapid_public_key'] ?? '') === '' || ($found['push.vapid_private_pem'] ?? '') === '') {
            $pair = push_generate_vapid_keypair();
            $save = $pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
            $save->execute(['push.vapid_public_key', $pair['public_key']]);
            $save->execute(['push.vapid_private_pem', $pair['private_pem']]);
            $save->execute(['push.vapid_subject', 'mailto:admin@example.com']);
            $found['push.vapid_public_key'] = $pair['public_key'];
            $found['push.vapid_private_pem'] = $pair['private_pem'];
            $found['push.vapid_subject'] = 'mailto:admin@example.com';
        }
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('cafe_push_vapid')")->fetchColumn();
    }
    return [
        'public_key' => $found['push.vapid_public_key'],
        'private_pem' => $found['push.vapid_private_pem'],
        'subject' => $found['push.vapid_subject'] ?? 'mailto:admin@example.com',
    ];
}

function push_hkdf_expand(string $prk, string $info, int $length): string
{
    $output = '';
    $block = '';
    for ($counter = 1; strlen($output) < $length; $counter++) {
        $block = hash_hmac('sha256', $block . $info . chr($counter), $prk, true);
        $output .= $block;
    }
    return substr($output, 0, $length);
}

function push_public_key_pem(string $rawPoint): string
{
    if (strlen($rawPoint) !== 65 || $rawPoint[0] !== "\x04") throw new RuntimeException('کلید عمومی P-256 معتبر نیست.');
    $prefix = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200');
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($prefix . $rawPoint), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

function push_der_length(string $der, int &$offset): int
{
    $length = ord($der[$offset++]);
    if (($length & 0x80) === 0) return $length;
    $count = $length & 0x7f;
    if ($count < 1 || $count > 4 || $offset + $count > strlen($der)) throw new RuntimeException('امضای DER معتبر نیست.');
    $length = 0;
    for ($i = 0; $i < $count; $i++) $length = ($length << 8) | ord($der[$offset++]);
    return $length;
}

function push_ecdsa_der_to_jose(string $der): string
{
    $offset = 0;
    if (($der[$offset++] ?? '') !== "\x30") throw new RuntimeException('امضای ECDSA معتبر نیست.');
    push_der_length($der, $offset);
    $parts = [];
    for ($i = 0; $i < 2; $i++) {
        if (($der[$offset++] ?? '') !== "\x02") throw new RuntimeException('امضای ECDSA معتبر نیست.');
        $length = push_der_length($der, $offset);
        $part = substr($der, $offset, $length);
        $offset += $length;
        $part = ltrim($part, "\x00");
        if (strlen($part) > 32) $part = substr($part, -32);
        $parts[] = str_pad($part, 32, "\x00", STR_PAD_LEFT);
    }
    return $parts[0] . $parts[1];
}

function push_vapid_jwt(string $endpoint, array $credentials): string
{
    $parts = parse_url($endpoint);
    if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) throw new RuntimeException('نشانی Push معتبر نیست.');
    $audience = 'https://' . $parts['host'] . (isset($parts['port']) ? ':' . (int)$parts['port'] : '');
    $header = push_b64url_encode(json_encode(['typ'=>'JWT','alg'=>'ES256'], JSON_UNESCAPED_SLASHES));
    $payload = push_b64url_encode(json_encode([
        'aud' => $audience,
        'exp' => time() + 12 * 3600,
        'sub' => $credentials['subject'] ?? 'mailto:admin@example.com',
    ], JSON_UNESCAPED_SLASHES));
    $input = $header . '.' . $payload;
    $key = openssl_pkey_get_private((string)$credentials['private_pem']);
    if ($key === false || !openssl_sign($input, $derSignature, $key, OPENSSL_ALGO_SHA256)) throw new RuntimeException('امضای VAPID انجام نشد.');
    return $input . '.' . push_b64url_encode(push_ecdsa_der_to_jose($derSignature));
}

function push_encrypt_payload(string $payload, string $p256dh, string $auth): string
{
    $receiverPublic = push_b64url_decode($p256dh);
    $authSecret = push_b64url_decode($auth);
    if (strlen($receiverPublic) !== 65 || strlen($authSecret) < 16) throw new RuntimeException('کلید اشتراک Push معتبر نیست.');

    $serverKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    if ($serverKey === false) throw new RuntimeException('ساخت کلید موقت Push انجام نشد.');
    $serverDetails = openssl_pkey_get_details($serverKey);
    $serverPublic = push_ec_public_point($serverDetails, 'کلید عمومی موقت Push معتبر نیست.');
    $receiverKey = openssl_pkey_get_public(push_public_key_pem($receiverPublic));
    if ($receiverKey === false) throw new RuntimeException('خواندن کلید مشترک Push انجام نشد.');
    $sharedSecret = openssl_pkey_derive($receiverKey, $serverKey, 32);
    if (!is_string($sharedSecret) || strlen($sharedSecret) !== 32) throw new RuntimeException('تولید کلید مشترک Push انجام نشد.');

    $prkKey = hash_hmac('sha256', $sharedSecret, $authSecret, true);
    $ikm = push_hkdf_expand($prkKey, "WebPush: info\x00" . $receiverPublic . $serverPublic, 32);
    $salt = random_bytes(16);
    $prk = hash_hmac('sha256', $ikm, $salt, true);
    $cek = push_hkdf_expand($prk, "Content-Encoding: aes128gcm\x00", 16);
    $nonce = push_hkdf_expand($prk, "Content-Encoding: nonce\x00", 12);
    $ciphertext = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
    if ($ciphertext === false) throw new RuntimeException('رمزنگاری پیام Push انجام نشد.');
    return $salt . pack('N', 4096) . chr(strlen($serverPublic)) . $serverPublic . $ciphertext . $tag;
}

function push_http_error_kind(int $errno, string $message): string
{
    $lower = strtolower($message);
    if ($errno === 6 || str_contains($lower, 'resolve host') || str_contains($lower, 'name resolution')) return 'dns';
    if (in_array($errno, [28], true) || str_contains($lower, 'timed out') || str_contains($lower, 'timeout')) return 'timeout';
    if (in_array($errno, [35,51,53,58,59,60,64,66,77,80,82,83,90,91], true) || str_contains($lower, 'ssl') || str_contains($lower, 'tls') || str_contains($lower, 'certificate')) return 'tls';
    if ($errno !== 0 || str_contains($lower, 'connect')) return 'connect';
    return 'network';
}

function push_http_post(string $endpoint, string $body, array $headers): array
{
    $headerLines = [];
    foreach ($headers as $key => $value) $headerLines[] = $key . ': ' . $value;
    $host = (string)(parse_url($endpoint, PHP_URL_HOST) ?: 'push-service');
    if (function_exists('curl_init')) {
        $ch = curl_init($endpoint);
        if ($ch === false) throw new RuntimeException('راه‌اندازی ارتباط اعلان انجام نشد.');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => 'Sokna-Push/' . app_release_version(),
        ]);
        $result = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = text_substr((string)curl_error($ch), 0, 500);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $timing = [
            'name_lookup_ms' => (int)round((float)curl_getinfo($ch, CURLINFO_NAMELOOKUP_TIME) * 1000),
            'connect_ms' => (int)round((float)curl_getinfo($ch, CURLINFO_CONNECT_TIME) * 1000),
            'tls_ms' => (int)round((float)curl_getinfo($ch, CURLINFO_APPCONNECT_TIME) * 1000),
            'total_ms' => (int)round((float)curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000),
        ];
        curl_close($ch);
        return [
            'status'=>$status,'body'=>is_string($result)?$result:'','headers'=>[],
            'transport'=>'curl','host'=>$host,'error_code'=>$errno,
            'error_name'=>$errno && function_exists('curl_strerror') ? text_substr((string)curl_strerror($errno),0,120) : '',
            'error_message'=>$error,
            'error_kind'=>$errno ? push_http_error_kind($errno,$error) : '', 'timing'=>$timing,
        ];
    }
    $context = stream_context_create(['http' => [
        'method' => 'POST','header' => implode("\r\n", $headerLines),'content' => $body,
        'timeout' => 6,'ignore_errors' => true,'protocol_version' => 1.1,
    ], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $started=microtime(true);$result=file_get_contents($endpoint, false, $context);$last=error_get_last();
    $responseHeaders = $http_response_header ?? [];$status = 0;
    if (isset($responseHeaders[0]) && preg_match('/\s(\d{3})\s/', $responseHeaders[0], $match)) $status = (int)$match[1];
    $message=$result===false?text_substr((string)($last['message']??'خطای نامشخص شبکه'),0,500):'';
    return ['status'=>$status,'body'=>is_string($result)?$result:'','headers'=>$responseHeaders,'transport'=>'stream','host'=>$host,'error_code'=>$result===false?1:0,'error_message'=>$message,'error_kind'=>$result===false?push_http_error_kind(1,$message):'','timing'=>['total_ms'=>(int)round((microtime(true)-$started)*1000)]];
}

function push_diagnostic_summary(array $result): string
{
    $timing = is_array($result['timing'] ?? null) ? $result['timing'] : [];
    $parts = [
        'host=' . (string)($result['host'] ?? 'push-service'),
        'status=' . (int)($result['status'] ?? 0),
        'kind=' . (string)($result['error_kind'] ?? ''),
        'code=' . (int)($result['error_code'] ?? 0),
    ];
    foreach (['name_lookup_ms'=>'dns_ms','connect_ms'=>'connect_ms','tls_ms'=>'tls_ms','total_ms'=>'total_ms'] as $key=>$label) {
        if (array_key_exists($key, $timing)) $parts[] = $label . '=' . (int)$timing[$key];
    }
    return implode(' ', $parts);
}

function push_http_failure_message(array $result): string
{
    $status=(int)($result['status']??0);$kind=(string)($result['error_kind']??'');
    if($status>=200&&$status<300)return '';
    if($status===401||$status===403)return 'سرویس اعلان کلیدهای احراز هویت را نپذیرفت؛ تنظیمات VAPID بررسی شود.';
    if($status===404||$status===410)return 'اشتراک اعلان این دستگاه منقضی شده است؛ اعلان را دوباره فعال کنید.';
    if($status===429)return 'سرویس اعلان فعلاً محدودیت ارسال دارد؛ تلاش مجدد با فاصله انجام می‌شود.';
    if($status>=500)return 'سرویس اعلان موقتاً پاسخ‌گو نیست؛ تلاش مجدد با فاصله انجام می‌شود.';
    return match($kind){
        'dns'=>'سرور نتوانست نام دامنه سرویس اعلان را پیدا کند.',
        'tls'=>'اتصال امن TLS به سرویس اعلان تأیید نشد.',
        'timeout'=>'سرویس اعلان در زمان مقرر پاسخ نداد.',
        'connect'=>'سرور نتوانست به سرویس اعلان متصل شود.',
        default=>$status>0?'سرویس اعلان پاسخ HTTP '.$status.' داد.':'ارتباط شبکه سرور با سرویس اعلان برقرار نشد.',
    };
}

function push_subscription_request(array $subscription, array $notification): array
{
    $credentials = push_vapid_credentials(true);
    $payload = json_encode($notification, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($payload) || strlen($payload) > 3000) throw new RuntimeException('متن اعلان بیش از حد بزرگ است.');
    $body = push_encrypt_payload($payload, (string)$subscription['p256dh'], (string)$subscription['auth_key']);
    $jwt = push_vapid_jwt((string)$subscription['endpoint'], $credentials);
    $urgency = in_array((string)($notification['urgency'] ?? ''), ['very-low','low','normal','high'], true) ? (string)$notification['urgency'] : 'normal';
    $ttl = max(60, min(900, (int)($notification['ttl'] ?? 180)));
    return [
        'endpoint'=>(string)$subscription['endpoint'],
        'body'=>$body,
        'headers'=>[
            'Content-Type' => 'application/octet-stream',
            'Content-Encoding' => 'aes128gcm',
            'Content-Length' => (string)strlen($body),
            'TTL' => (string)$ttl,
            'Urgency' => $urgency,
            'Authorization' => 'vapid t=' . $jwt . ', k=' . $credentials['public_key'],
        ],
    ];
}

function push_send_subscription(array $subscription, array $notification): array
{
    $request = push_subscription_request($subscription, $notification);
    return push_http_post((string)$request['endpoint'], (string)$request['body'], (array)$request['headers']);
}

/**
 * Bounded concurrent Web Push fan-out. The durable delivery rows remain authoritative;
 * curl_multi is only a latency accelerator. Hosts without cURL fall back safely to
 * the same per-device sender with the shorter fast-path timeout.
 *
 * @return array<int,array> keyed by push_event_deliveries.id
 */
function push_send_deliveries_bounded(array $deliveries, string $eventType, array $payload, int $concurrency = 4): array
{
    $concurrency = max(1, min(6, $concurrency));
    $requests = [];
    $results = [];
    foreach ($deliveries as $delivery) {
        $key = (int)($delivery['delivery_id'] ?? 0);
        if ($key < 1) continue;
        try {
            $request = push_subscription_request($delivery, push_notification_for_delivery($eventType, $payload, $delivery));
            $requests[] = ['key'=>$key] + $request;
        } catch (Throwable $e) {
            $message = text_substr($e->getMessage(), 0, 500);
            $results[$key] = ['status'=>0,'error_code'=>1,'error_message'=>$message,'error_kind'=>push_http_error_kind(1,$message),'timing'=>[]];
        }
    }
    if (!$requests) return $results;

    if (!function_exists('curl_multi_init') || !function_exists('curl_init')) {
        foreach ($requests as $request) {
            $results[(int)$request['key']] = push_http_post((string)$request['endpoint'], (string)$request['body'], (array)$request['headers']);
        }
        return $results;
    }

    foreach (array_chunk($requests, $concurrency) as $chunk) {
        $mh = curl_multi_init();
        if ($mh === false) {
            foreach ($chunk as $request) $results[(int)$request['key']] = push_http_post((string)$request['endpoint'], (string)$request['body'], (array)$request['headers']);
            continue;
        }
        $handles = [];
        foreach ($chunk as $request) {
            $headerLines = [];
            foreach ((array)$request['headers'] as $header => $value) $headerLines[] = $header . ': ' . $value;
            $ch = curl_init((string)$request['endpoint']);
            if ($ch === false) {
                $results[(int)$request['key']] = ['status'=>0,'error_code'=>1,'error_message'=>'راه‌اندازی ارتباط اعلان انجام نشد.','error_kind'=>'connect','timing'=>[]];
                continue;
            }
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => (string)$request['body'],
                CURLOPT_HTTPHEADER => $headerLines,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => false,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT => 6,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_USERAGENT => 'Sokna-Push/' . app_release_version(),
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[(int)$request['key']] = $ch;
        }
        if ($handles) {
            $active = null;
            do {
                $multiCode = curl_multi_exec($mh, $active);
                if ($multiCode !== CURLM_OK) break;
                if ($active) {
                    $selected = curl_multi_select($mh, 0.5);
                    if ($selected === -1) usleep(10000);
                }
            } while ($active);
        }
        foreach ($handles as $key => $ch) {
            $errno = curl_errno($ch);
            $error = text_substr((string)curl_error($ch), 0, 500);
            $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $host = (string)(parse_url((string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL), PHP_URL_HOST) ?: 'push-service');
            $results[$key] = [
                'status'=>$status,'body'=>'','headers'=>[],'transport'=>'curl_multi','host'=>$host,'error_code'=>$errno,
                'error_name'=>$errno && function_exists('curl_strerror') ? text_substr((string)curl_strerror($errno),0,120) : '',
                'error_message'=>$error,'error_kind'=>$errno ? push_http_error_kind($errno,$error) : '',
                'timing'=>[
                    'name_lookup_ms'=>(int)round((float)curl_getinfo($ch,CURLINFO_NAMELOOKUP_TIME)*1000),
                    'connect_ms'=>(int)round((float)curl_getinfo($ch,CURLINFO_CONNECT_TIME)*1000),
                    'tls_ms'=>(int)round((float)curl_getinfo($ch,CURLINFO_APPCONNECT_TIME)*1000),
                    'total_ms'=>(int)round((float)curl_getinfo($ch,CURLINFO_TOTAL_TIME)*1000),
                ],
            ];
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
    }
    return $results;
}

function push_action_secret(): string
{
    $key='push.action_secret';
    $secret=setting($key,'');
    if($secret!=='') return $secret;
    $secret=push_b64url_encode(random_bytes(32));
    db()->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute([$key,$secret]);
    return $secret;
}

function push_action_token(int $userId,string $action,int $subjectId,int $ttl=600,array $binding=[]): string
{
    $payload=[
        'v'=>2,
        'uid'=>$userId,
        'action'=>$action,
        'sid'=>$subjectId,
        'stype'=>$action==='accept_call'?'waiter_call':($action==='approve_order'?'order':'subject'),
        'did'=>(int)($binding['delivery_id']??0),
        'subid'=>(int)($binding['subscription_id']??0),
        'nonce'=>push_b64url_encode(random_bytes(18)),
        'exp'=>time()+max(60,min(900,$ttl)),
    ];
    $body=push_b64url_encode(json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    $sig=push_b64url_encode(hash_hmac('sha256',$body,push_action_secret(),true));
    return $body.'.'.$sig;
}

function push_action_token_decode(string $token): array
{
    $parts=explode('.',$token,2);if(count($parts)!==2)throw new RuntimeException('توکن اعلان معتبر نیست.');
    [$body,$sig]=$parts;$expected=push_b64url_encode(hash_hmac('sha256',$body,push_action_secret(),true));
    if(!hash_equals($expected,$sig))throw new RuntimeException('توکن اعلان معتبر نیست.');
    $payload=json_decode(push_b64url_decode($body),true,16,JSON_THROW_ON_ERROR);
    if(!is_array($payload)||(int)($payload['v']??0)!==2||(int)($payload['exp']??0)<time())throw new RuntimeException('این اقدام اعلان منقضی شده است.');
    if((int)($payload['uid']??0)<1||(int)($payload['sid']??0)<1||(int)($payload['did']??0)<1||(int)($payload['subid']??0)<1||trim((string)($payload['nonce']??''))==='')throw new RuntimeException('این اقدام اعلان کامل نیست.');
    return $payload;
}

function push_action_claim_once(array $claims): bool
{
    $deliveryId=(int)($claims['did']??0);$subscriptionId=(int)($claims['subid']??0);$userId=(int)($claims['uid']??0);
    $action=(string)($claims['action']??'');$subjectType=(string)($claims['stype']??'');$subjectId=(int)($claims['sid']??0);$nonce=(string)($claims['nonce']??'');
    if($deliveryId<1||$subscriptionId<1||$userId<1||$subjectId<1||$nonce==='')return false;
    $pdo=db();
    $delivery=$pdo->prepare("SELECT d.id,d.subscription_id,q.event_type,q.payload_json,ps.user_id FROM push_event_deliveries d JOIN push_event_queue q ON q.id=d.queue_id JOIN push_subscriptions ps ON ps.id=d.subscription_id WHERE d.id=? AND d.subscription_id=? AND ps.user_id=? LIMIT 1");
    $delivery->execute([$deliveryId,$subscriptionId,$userId]);$row=$delivery->fetch();if(!$row)return false;
    $payload=json_decode((string)$row['payload_json'],true);if(!is_array($payload))return false;
    $expectedEvent=$action==='accept_call'?'waiter_call':($action==='approve_order'?'pending_order':'');
    $expectedSubject=$action==='accept_call'?(int)($payload['call_id']??0):($action==='approve_order'?(int)($payload['order_id']??0):0);
    if($expectedEvent===''||(string)$row['event_type']!==$expectedEvent||$expectedSubject!==$subjectId)return false;
    try{
        $stmt=$pdo->prepare('INSERT INTO push_action_claims(nonce_hash,delivery_id,subscription_id,user_id,action_name,subject_type,subject_id) VALUES(?,?,?,?,?,?,?)');
        $stmt->execute([hash('sha256',$nonce),$deliveryId,$subscriptionId,$userId,$action,$subjectType,$subjectId]);
        return true;
    }catch(PDOException $e){
        if((string)$e->getCode()==='23000')return false;
        throw $e;
    }
}

function push_pending_order_quick_approvable(int $orderId): bool
{
    if($orderId<1)return false;
    try{
        $stmt=db()->prepare("SELECT o.status,TRIM(COALESCE(o.customer_note,'')) customer_note,EXISTS(SELECT 1 FROM order_items oi WHERE oi.order_id=o.id AND TRIM(COALESCE(oi.item_note,''))<>'') has_item_note FROM orders o WHERE o.id=? LIMIT 1");
        $stmt->execute([$orderId]);$row=$stmt->fetch();
        return $row&&in_array((string)$row['status'],['pending_approval','new'],true)&&(string)$row['customer_note']===''&&(int)$row['has_item_note']===0;
    }catch(Throwable){return false;}
}

function push_pending_order_review_url(int $orderId,int $tableId=0): string
{
    $query=['work'=>'attention','attention_filter'=>'orders','open_order'=>$orderId];
    if($tableId>0)$query['open_table']=$tableId;
    return asset('operator/index.php').'?'.http_build_query($query);
}

function push_enqueue_confirmed_order_tx(PDO $pdo,int $orderId,string $tableName,string $requestId=''): void
{
    push_enqueue_event_tx($pdo,'order',[
        'title'=>customer_message('staff_preparation_order_title',['table'=>$tableName]),
        'body'=>customer_message('staff_preparation_order_body',['order'=>order_display_label($orderId)]),
        'url'=>asset('waiter/index.php').'?order='.$orderId,
        'tag'=>'preparation-order-'.$orderId,
        'order_id'=>$orderId,
    ],$requestId!==''?$requestId:null);
}

function push_notification_for_delivery(string $eventType,array $payload,array $delivery): array
{
    $notification=$payload;
    $notification['urgency'] = $eventType === 'waiter_call' || $eventType === 'pending_order' ? 'high' : 'normal';
    $notification['ttl'] = $eventType === 'waiter_call' ? 120 : 180;
    $binding=['delivery_id'=>(int)($delivery['delivery_id']??0),'subscription_id'=>(int)($delivery['subscription_id']??0)];
    if($eventType==='waiter_call' && (int)($payload['call_id']??0)>0){
        $notification['actions']=[['action'=>'accept_call','title'=>'رسیدگی شد'],['action'=>'open','title'=>'مشاهده']];
        $notification['action_url']=asset('api/push_action.php');
        $notification['action_token']=push_action_token((int)$delivery['user_id'],'accept_call',(int)$payload['call_id'],300,$binding);
    }elseif($eventType==='pending_order' && (int)($payload['order_id']??0)>0){
        if(push_pending_order_quick_approvable((int)$payload['order_id'])){
            $notification['actions']=[['action'=>'approve_order','title'=>'تأیید سفارش'],['action'=>'open','title'=>'بررسی سفارش']];
            $notification['action_url']=asset('api/push_action.php');
            $notification['action_token']=push_action_token((int)$delivery['user_id'],'approve_order',(int)$payload['order_id'],300,$binding);
        }else{
            $notification['actions']=[['action'=>'open','title'=>'بررسی سفارش']];
        }
    }
    return $notification;
}

function push_notification_preference_definitions(): array
{
    return [
        'waiter_call' => ['label'=>'فراخوان مهمان','capability'=>'orders_floor'],
        'pending_guest_order' => ['label'=>'سفارش مهمان منتظر تأیید','capability'=>'orders_floor'],
        'preparation' => ['label'=>'آماده‌سازی سفارش‌ها','capability'=>'preparation'],
    ];
}

function push_preference_key_for_event(string $eventType): ?string
{
    return match ($eventType) {
        'waiter_call' => 'waiter_call',
        'pending_order' => 'pending_guest_order',
        'order' => 'preparation',
        default => null,
    };
}

function push_user_preference_enabled(int $userId, string $preferenceKey, ?PDO $pdo = null): bool
{
    if ($userId < 1 || !array_key_exists($preferenceKey, push_notification_preference_definitions())) return false;
    try {
        $pdo ??= db();
        $stmt = $pdo->prepare('SELECT enabled FROM user_notification_preferences WHERE user_id=? AND preference_key=? LIMIT 1');
        $stmt->execute([$userId,$preferenceKey]);
        $value = $stmt->fetchColumn();
        // Missing row preserves pre-1.33.1 behavior: eligible users receive Push by default.
        return $value === false ? true : (int)$value === 1;
    } catch (Throwable $e) {
        // Preferences must never expand responsibility; permission routing still runs first.
        // If the preference table is temporarily unavailable during upgrade, preserve existing delivery.
        error_log('push preference lookup: ' . $e->getMessage());
        return true;
    }
}

function push_user_available_preferences(int $userId): array
{
    if ($userId < 1) return [];
    $caps = user_capabilities($userId);
    $out = [];
    foreach (push_notification_preference_definitions() as $key=>$definition) {
        if (in_array((string)$definition['capability'], $caps, true)) $out[$key] = $definition;
    }
    return $out;
}

function push_order_areas(array $data): array
{
    $areas = array_values(array_intersect(array_keys(preparation_operational_areas()), array_map('strval', (array)($data['areas'] ?? []))));
    if ($areas || empty($data['order_id'])) return $areas;
    try {
        $stmt = db()->prepare('SELECT DISTINCT preparation_station FROM order_items WHERE order_id=?');
        $stmt->execute([(int)$data['order_id']]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $station) { if (!preparation_station_requires_work((string)$station)) continue; $areas[] = preparation_area_for_station((string)$station); }
    } catch (Throwable) {}
    return array_values(array_unique($areas));
}

function push_recipient_ids(string $eventType, array $data = []): array
{
    if ($eventType === 'diagnostic') {
        $targetUserId = (int)($data['target_user_id'] ?? 0);
        if ($targetUserId < 1) return [];
        $stmt = db()->prepare('SELECT id FROM users WHERE id=? AND active=1 LIMIT 1');
        $stmt->execute([$targetUserId]);
        return $stmt->fetchColumn() ? [$targetUserId] : [];
    }

    $areas = $eventType === 'order' ? push_order_areas($data) : [];
    $preferenceKey = push_preference_key_for_event($eventType);
    $users = db()->query('SELECT id,role FROM users WHERE active=1 ORDER BY id')->fetchAll();
    $adminLive = setting_bool('push.admin_live_operations', false);
    return array_values(array_map('intval',array_column(array_filter($users, static function (array $row) use ($eventType, $areas, $adminLive, $preferenceKey): bool {
        $userId=(int)$row['id'];
        if((string)$row['role']==='admin' && !$adminLive) return false;
        $responsible = false;
        if ($eventType === 'waiter_call' || $eventType === 'pending_order') {
            $responsible = in_array('orders_floor', user_capabilities($userId), true);
        } elseif ($eventType === 'order') {
            if (!in_array('preparation', user_capabilities($userId), true)) return false;
            $responsible = !$areas || (bool)array_intersect($areas, user_preparation_areas($userId));
        }
        if (!$responsible) return false;
        return $preferenceKey === null || push_user_preference_enabled($userId, $preferenceKey);
    }), 'id')));
}

function push_queue_available(?PDO $pdo = null): bool
{
    try {
        $pdo ??= db();
        $stmt = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='push_event_queue' LIMIT 1");
        return (bool)$stmt->fetchColumn();
    } catch (Throwable) {
        return false;
    }
}


function push_event_key(string $eventType, array $data, ?string $requestId = null): ?string
{
    $requestId=trim((string)$requestId);
    if($requestId!=='')return text_substr($eventType.':request:'.$requestId,0,160);
    $explicit=trim((string)($data['event_key']??''));
    if($explicit!=='')return text_substr($eventType.':'.$explicit,0,160);
    $tag=trim((string)($data['tag']??''));
    if($tag===''||$eventType==='order')return null;
    return text_substr($eventType.':'.$tag,0,160);
}

function push_register_kick_queue(int $queueId): void
{
    if ($queueId < 1) return;
    $GLOBALS['sokna_push_kick_queue_ids'] ??= [];
    $GLOBALS['sokna_push_kick_queue_ids'][$queueId] = $queueId;
}

function push_kick_token(int $queueId, int $ttl = 180): string
{
    $payload = ['v'=>1,'purpose'=>'queue_kick','qid'=>$queueId,'exp'=>time()+max(60,min(600,$ttl))];
    $body = push_b64url_encode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $sig = push_b64url_encode(hash_hmac('sha256', $body, push_action_secret(), true));
    return $body . '.' . $sig;
}

function push_kick_token_decode(string $token): array
{
    $parts = explode('.', trim($token), 2);
    if (count($parts) !== 2) throw new RuntimeException('توکن پردازش اعلان معتبر نیست.');
    [$body,$sig] = $parts;
    $expected = push_b64url_encode(hash_hmac('sha256', $body, push_action_secret(), true));
    if (!hash_equals($expected, $sig)) throw new RuntimeException('توکن پردازش اعلان معتبر نیست.');
    $payload = json_decode(push_b64url_decode($body), true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($payload) || (int)($payload['v'] ?? 0) !== 1 || ($payload['purpose'] ?? '') !== 'queue_kick' || (int)($payload['qid'] ?? 0) < 1 || (int)($payload['exp'] ?? 0) < time()) {
        throw new RuntimeException('توکن پردازش اعلان منقضی یا نامعتبر است.');
    }
    return $payload;
}

function push_response_metadata(): array
{
    $ids = array_values(array_map('intval', $GLOBALS['sokna_push_kick_queue_ids'] ?? []));
    if (!$ids) return [];
    $queueId = (int)end($ids);
    if ($queueId < 1) return [];
    try {
        $stmt = db()->prepare("SELECT id,status FROM push_event_queue WHERE id=? LIMIT 1");
        $stmt->execute([$queueId]);
        $row = $stmt->fetch();
        if (!$row || !in_array((string)$row['status'], ['pending','processing'], true)) return [];
        return ['kick'=>['url'=>asset('api/push_kick.php'),'queue_id'=>$queueId,'token'=>push_kick_token($queueId)]];
    } catch (Throwable $e) {
        error_log('push response metadata: ' . $e->getMessage());
        return [];
    }
}

function push_after_response_drain(): void
{
    $ids = array_values(array_map('intval', $GLOBALS['sokna_push_kick_queue_ids'] ?? []));
    if (!$ids) return;
    foreach (array_slice($ids, -2) as $queueId) {
        try { push_process_queue(1, $queueId); }
        catch (Throwable $e) { error_log('push after-response drain: ' . $e->getMessage()); }
    }
}

function push_enqueue_event_tx(PDO $pdo, string $eventType, array $data, ?string $requestId = null): array
{
    if (!in_array($eventType, ['waiter_call','pending_order','order','diagnostic'], true)) {
        return ['queued'=>0,'sent'=>0,'failed'=>1];
    }
    $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (strlen($payload) > 12000) throw new RuntimeException('محتوای اعلان بیش از حد بزرگ است.');
    $eventKey = push_event_key($eventType, $data, $requestId);
    $stmt = $pdo->prepare(
        "INSERT INTO push_event_queue(event_type,request_id,event_key,payload_json,status,available_at)
         VALUES(?,?,?,?,'pending',NOW())
         ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id),request_id=COALESCE(request_id,VALUES(request_id))"
    );
    $stmt->execute([$eventType, $requestId ?: null, $eventKey, $payload]);
    $queueId = (int)$pdo->lastInsertId();
    push_register_kick_queue($queueId);
    return ['queued'=>1,'queue_id'=>$queueId,'sent'=>0,'failed'=>0];
}

function push_enqueue_event(string $eventType, array $data, ?string $requestId = null): array
{
    try {
        $pdo = db();
        if (!push_queue_available($pdo)) {
            error_log('push_event_queue is not installed; polling remains active.');
            return ['queued'=>0,'sent'=>0,'failed'=>1];
        }
        return push_enqueue_event_tx($pdo, $eventType, $data, $requestId);
    } catch (Throwable $e) {
        error_log('push outbox enqueue failed: ' . $e->getMessage());
        return ['queued'=>0,'sent'=>0,'failed'=>1];
    }
}

function push_delivery_retryable(array $result): bool
{
    $status = (int)($result['status'] ?? 0);
    return $status === 0 || $status === 429 || $status >= 500;
}

function push_event_age_seconds(?string $createdAt): int
{
    if (!$createdAt) return PHP_INT_MAX;
    $timestamp = strtotime($createdAt);
    return $timestamp === false ? PHP_INT_MAX : max(0, time() - $timestamp);
}

/** @return array{actionable:bool,reason:string} */
function push_event_actionability(string $eventType, array $payload, ?string $createdAt): array
{
    try {
        $age = push_event_age_seconds($createdAt);
        if ($eventType === 'diagnostic') return ['actionable'=>$age <= 300,'reason'=>$age <= 300 ? 'diagnostic_current' : 'diagnostic_expired'];
        if ($eventType === 'waiter_call') {
            if ($age > 300) return ['actionable'=>false,'reason'=>'waiter_call_expired'];
            $callId = (int)($payload['call_id'] ?? 0);
            if ($callId < 1) return ['actionable'=>false,'reason'=>'waiter_call_missing_subject'];
            $stmt = db()->prepare('SELECT status FROM waiter_calls WHERE id=? LIMIT 1');
            $stmt->execute([$callId]);
            return ['actionable'=>(string)$stmt->fetchColumn()==='new','reason'=>'waiter_call_not_new'];
        }
        if ($eventType === 'pending_order') {
            if ($age > 900) return ['actionable'=>false,'reason'=>'pending_order_expired'];
            $orderId = (int)($payload['order_id'] ?? 0);
            if ($orderId < 1) return ['actionable'=>false,'reason'=>'pending_order_missing_subject'];
            $stmt = db()->prepare('SELECT status FROM orders WHERE id=? LIMIT 1');
            $stmt->execute([$orderId]);
            return ['actionable'=>in_array((string)$stmt->fetchColumn(), ['pending_approval','new'], true),'reason'=>'pending_order_not_pending'];
        }
        if ($eventType === 'order') {
            if ($age > 1800) return ['actionable'=>false,'reason'=>'preparation_event_expired'];
            $orderId = (int)($payload['order_id'] ?? 0);
            if ($orderId < 1) return ['actionable'=>false,'reason'=>'preparation_missing_subject'];
            $orderStmt = db()->prepare("SELECT status FROM orders WHERE id=? LIMIT 1");
            $orderStmt->execute([$orderId]);
            if (!in_array((string)$orderStmt->fetchColumn(), ['accounted','completed'], true)) return ['actionable'=>false,'reason'=>'preparation_order_closed'];
            $areas = push_order_areas($payload);
            if (!$areas) return ['actionable'=>true,'reason'=>'preparation_open'];
            $itemStmt = db()->prepare('SELECT id,item_name,quantity,item_note,preparation_station FROM order_items WHERE order_id=? AND quantity>0 ORDER BY id');
            $itemStmt->execute([$orderId]);
            $byArea = [];
            foreach ($itemStmt->fetchAll() as $item) { if (!preparation_station_requires_work((string)$item['preparation_station'])) continue; $byArea[preparation_area_for_station((string)$item['preparation_station'])][] = $item; }
            $claimStmt = db()->prepare('SELECT area_key,item_signature FROM order_preparation_claims WHERE order_id=?');
            $claimStmt->execute([$orderId]);
            $claims = [];
            foreach ($claimStmt->fetchAll() as $claim) $claims[(string)$claim['area_key']] = (string)$claim['item_signature'];
            foreach ($areas as $area) {
                $items = $byArea[$area] ?? [];
                if (!$items) continue;
                $signature = preparation_items_signature($items);
                if (!isset($claims[$area]) || !hash_equals($claims[$area], $signature)) return ['actionable'=>true,'reason'=>'preparation_unclaimed'];
            }
            return ['actionable'=>false,'reason'=>'preparation_already_claimed'];
        }
        return ['actionable'=>false,'reason'=>'unsupported_event'];
    } catch (Throwable $e) {
        error_log('push actionability: ' . $e->getMessage());
        return ['actionable'=>false,'reason'=>'state_unknown'];
    }
}

function push_expire_queue_event(PDO $pdo, int $queueId, string $reason): void
{
    $message = text_substr('expired:' . $reason, 0, 500);
    $pdo->prepare("UPDATE push_event_deliveries SET status='expired',last_error=? WHERE queue_id=? AND status='pending'")->execute([$message,$queueId]);
    $pdo->prepare("UPDATE push_event_queue SET status='expired',locked_at=NULL,last_error=? WHERE id=?")->execute([$message,$queueId]);
}

function push_process_queue(int $limit = 5, ?int $onlyQueueId = null): array
{
    $limit = max(1, min(20, $limit));
    $pdo = db();
    if (!push_queue_available($pdo)) return ['processed'=>0,'sent'=>0,'failed'=>0,'retried'=>0];

    // Recover only stale locks. User requests may trigger bounded best-effort drains, but the outbox remains authoritative.
    $pdo->exec("UPDATE push_event_queue SET status='pending',locked_at=NULL WHERE status='processing' AND locked_at<DATE_SUB(NOW(),INTERVAL 5 MINUTE)");
    $processed = $sent = $failed = $retried = 0;

    while ($processed < $limit) {
        $pdo->beginTransaction();
        try {
            $sql = "SELECT * FROM push_event_queue WHERE status='pending' AND available_at<=NOW()";
            $params = [];
            if ($onlyQueueId !== null && $onlyQueueId > 0) { $sql .= ' AND id=?'; $params[] = $onlyQueueId; }
            $sql .= " ORDER BY CASE event_type WHEN 'waiter_call' THEN 30 WHEN 'pending_order' THEN 20 WHEN 'order' THEN 10 WHEN 'diagnostic' THEN 5 ELSE 0 END DESC,id LIMIT 1 FOR UPDATE";
            $nextStmt = $pdo->prepare($sql);
            $nextStmt->execute($params);
            $row = $nextStmt->fetch();
            if (!$row) {
                $pdo->commit();
                break;
            }
            $pdo->prepare(
                "UPDATE push_event_queue SET status='processing',locked_at=NOW(),attempt_count=attempt_count+1 WHERE id=?"
            )->execute([(int)$row['id']]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        $processed++;
        $queueId = (int)$row['id'];
        try {
            $payload = json_decode((string)$row['payload_json'], true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($payload)) $payload = [];
            $actionability = push_event_actionability((string)$row['event_type'], $payload, (string)($row['created_at'] ?? ''));
            if (!$actionability['actionable']) {
                push_expire_queue_event($pdo, $queueId, (string)$actionability['reason']);
                continue;
            }
            $recipientIds = push_recipient_ids((string)$row['event_type'], $payload);
            if (!$recipientIds) {
                push_expire_queue_event($pdo, $queueId, 'no_eligible_recipients');
                continue;
            }

            $placeholders = implode(',', array_fill(0, count($recipientIds), '?'));
            $subStmt = $pdo->prepare(
                "SELECT * FROM push_subscriptions
                 WHERE active=1 AND user_id IN($placeholders)
                 ORDER BY updated_at DESC"
            );
            $subStmt->execute($recipientIds);
            $subscriptions = $subStmt->fetchAll();
            if (!$subscriptions) {
                push_expire_queue_event($pdo, $queueId, 'no_active_subscriptions');
                continue;
            }

            {
                $insertDelivery = $pdo->prepare(
                    "INSERT IGNORE INTO push_event_deliveries(queue_id,subscription_id,status)
                     VALUES(?,?,'pending')"
                );
                foreach ($subscriptions as $subscription) {
                    $insertDelivery->execute([$queueId, (int)$subscription['id']]);
                }

                $deliveryStmt = $pdo->prepare(
                    "SELECT d.id delivery_id,d.queue_id,d.subscription_id,d.status delivery_status,
                            d.attempt_count delivery_attempt_count,d.last_http_status delivery_last_http_status,
                            d.last_error delivery_last_error,ps.*
                     FROM push_event_deliveries d
                     JOIN push_subscriptions ps ON ps.id=d.subscription_id
                     WHERE d.queue_id=? AND d.status='pending' AND d.attempt_count<4
                     ORDER BY d.id"
                );
                $deliveryStmt->execute([$queueId]);
                $deliveries = $deliveryStmt->fetchAll();

                $subscriptionUpdate = $pdo->prepare(
                    'UPDATE push_subscriptions SET active=?,last_success_at=?,last_error_at=?,last_error_message=? WHERE id=?'
                );
                $deliveryUpdate = $pdo->prepare(
                    'UPDATE push_event_deliveries SET status=?,attempt_count=attempt_count+1,last_http_status=?,last_error=?,sent_at=? WHERE id=?'
                );
                $log = $pdo->prepare(
                    'INSERT INTO push_delivery_log(subscription_id,event_type,http_status,success,error_message) VALUES(?,?,?,?,?)'
                );

                $deliveryResults = push_send_deliveries_bounded($deliveries, (string)$row['event_type'], $payload, 4);
                foreach ($deliveries as $delivery) {
                    $deliveryKey = (int)$delivery['delivery_id'];
                    $result = $deliveryResults[$deliveryKey] ?? [
                        'status'=>0,
                        'error_code'=>1,
                        'error_message'=>'نتیجه ارسال اعلان ثبت نشد.',
                        'error_kind'=>'network',
                        'timing'=>[],
                    ];
                    $status = (int)($result['status'] ?? 0);
                    $ok = $status >= 200 && $status < 300;
                    $expired = in_array($status, [404,410], true);
                    $retryable = !$ok && !$expired && push_delivery_retryable($result);
                    $attempt = (int)$delivery['delivery_attempt_count'] + 1;
                    $terminalFailure = !$ok && !$expired && (!$retryable || $attempt >= 4);
                    $message = $ok ? null : push_http_failure_message($result);
                    if (!$ok) error_log('Sokna Push delivery failed: ' . push_diagnostic_summary($result));

                    $deliveryState = $ok ? 'sent' : ($expired ? 'expired' : ($terminalFailure ? 'failed' : 'pending'));
                    $deliveryUpdate->execute([
                        $deliveryState,
                        $status,
                        $message,
                        $ok ? date('Y-m-d H:i:s') : null,
                        (int)$delivery['delivery_id'],
                    ]);
                    $subscriptionUpdate->execute([
                        $expired ? 0 : 1,
                        $ok ? date('Y-m-d H:i:s') : $delivery['last_success_at'],
                        $ok ? null : date('Y-m-d H:i:s'),
                        $message,
                        (int)$delivery['subscription_id'],
                    ]);
                    $log->execute([(int)$delivery['subscription_id'], (string)$row['event_type'], $status, $ok?1:0, $message]);

                    if ($ok) $sent++;
                    elseif ($retryable && !$terminalFailure) $retried++;
                    else $failed++;
                }
            }

            $pendingStmt = $pdo->prepare("SELECT COUNT(*) FROM push_event_deliveries WHERE queue_id=? AND status='pending' AND attempt_count<4");
            $pendingStmt->execute([$queueId]);
            $pendingCount = (int)$pendingStmt->fetchColumn();
            $failedStmt = $pdo->prepare("SELECT COUNT(*) FROM push_event_deliveries WHERE queue_id=? AND status='failed'");
            $failedStmt->execute([$queueId]);
            $terminalCount = (int)$failedStmt->fetchColumn();

            if ($pendingCount > 0) {
                $attempt = max(1, (int)$row['attempt_count'] + 1);
                $delay = min(300, 15 * $attempt);
                $pdo->prepare(
                    "UPDATE push_event_queue
                     SET status='pending',available_at=?,locked_at=NULL,last_error=?
                     WHERE id=?"
                )->execute([
                    date('Y-m-d H:i:s', time() + $delay),
                    'بخشی از مقصدها برای تلاش مجدد باقی مانده‌اند.',
                    $queueId,
                ]);
            } else {
                $pdo->prepare(
                    "UPDATE push_event_queue
                     SET status=?,sent_at=NOW(),locked_at=NULL,last_error=?
                     WHERE id=?"
                )->execute([
                    $terminalCount > 0 ? 'failed' : 'sent',
                    $terminalCount > 0 ? 'یک یا چند مقصد پس از تلاش‌های کنترل‌شده ناموفق ماند.' : null,
                    $queueId,
                ]);
            }
        } catch (Throwable $e) {
            $attempt = (int)$row['attempt_count'] + 1;
            $terminal = $attempt >= 4;
            $delay = min(300, 15 * max(1, $attempt));
            $message = text_substr($e->getMessage(),0,500);
            $pdo->prepare(
                "UPDATE push_event_queue SET status=?,available_at=?,locked_at=NULL,last_error=? WHERE id=?"
            )->execute([
                $terminal ? 'failed' : 'pending',
                date('Y-m-d H:i:s', time() + $delay),
                $message,
                $queueId,
            ]);
            $terminal ? $failed++ : $retried++;
        }
    }

    return ['processed'=>$processed,'sent'=>$sent,'failed'=>$failed,'retried'=>$retried];
}
