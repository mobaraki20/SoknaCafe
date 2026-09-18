<?php
declare(strict_types=1);

/**
 * Sokna Center integration owner.
 *
 * Ownership boundary:
 * - Cafe owns local users/authentication/operational roles.
 * - Center owns employee/assignment/compensation/payroll/authorization.
 * - No HR data or credentials are synchronized into either side.
 */
final class SoknaCenterAuthException extends RuntimeException
{
    public function __construct(
        public readonly string $reasonCode,
        string $message = 'درخواست معتبر نیست.',
        int $code = 0,
        ?Throwable $previous = null,
        public readonly array $diagnostics = []
    ) {
        parent::__construct($message, $code, $previous);
    }
}

function sokna_center_personnel_module_enabled(): bool
{
    // Unit/contract tests may load this integration owner without the full application registry.
    return !function_exists('sokna_module_enabled') || sokna_module_enabled('personnel');
}

function sokna_center_connection_enabled(): bool
{
    // Runtime module state is the outer feature gate. Connection state only says whether the
    // already-enabled Personnel integration has a valid paired relation.
    if (!sokna_center_personnel_module_enabled()) return false;
    $override = $GLOBALS['SOKNA_CENTER_CONFIG_OVERRIDE'] ?? null;
    if (is_array($override) && array_key_exists('enabled', $override)) return (bool)$override['enabled'];
    return setting_bool('sokna_center_connection_enabled', false);
}

function sokna_center_validate_base_url(string $url): string
{
    $url = rtrim(trim($url), '/');
    if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
        throw new InvalidArgumentException('کلید اتصال مرکز سکنا معتبر نیست.');
    }
    $parts = parse_url($url);
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    $host = strtolower((string)($parts['host'] ?? ''));
    $local = in_array($host, ['localhost','127.0.0.1','::1'], true);
    if ($scheme !== 'https' && !$local) {
        throw new InvalidArgumentException('کلید اتصال مرکز سکنا معتبر نیست.');
    }
    if (isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']) || isset($parts['query'])) {
        throw new InvalidArgumentException('کلید اتصال مرکز سکنا معتبر نیست.');
    }
    return $url;
}

function sokna_center_base_url(): string
{
    $override = $GLOBALS['SOKNA_CENTER_CONFIG_OVERRIDE'] ?? null;
    $value = is_array($override) && array_key_exists('base_url', $override)
        ? (string)$override['base_url']
        : setting('sokna_center_base_url');
    return rtrim(trim($value), '/');
}

function sokna_center_secret_key(): string
{
    global $config;
    $key = trim((string)($config['app']['key'] ?? ''));
    if ($key === '' || str_starts_with($key, 'CHANGE_ME')) {
        throw new RuntimeException('کلید داخلی سامانه برای نگهداری امن اتصال مرکز سکنا آماده نیست.');
    }
    return hash('sha256', 'sokna-center-cafe-v2|' . $key, true);
}

function sokna_center_encrypt_secret(string $plain): string
{
    if ($plain === '') return '';
    if (!function_exists('openssl_encrypt')) {
        throw new RuntimeException('ذخیره امن کلید اتصال در این سرور آماده نیست.');
    }
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', sokna_center_secret_key(), OPENSSL_RAW_DATA, $iv, $tag, 'sokna-center-cafe-v2');
    if ($cipher === false || strlen($tag) !== 16) throw new RuntimeException('ذخیره امن کلید اتصال انجام نشد.');
    return 'enc:v2:' . base64_encode($iv . $tag . $cipher);
}

function sokna_center_decrypt_secret(string $stored): string
{
    if ($stored === '') return '';
    $version = str_starts_with($stored, 'enc:v2:') ? 2 : (str_starts_with($stored, 'enc:v1:') ? 1 : 0);
    if ($version === 0) return '';
    $raw = base64_decode(substr($stored, 7), true);
    if ($raw === false || strlen($raw) < 29 || !function_exists('openssl_decrypt')) return '';
    $aad = $version === 2 ? 'sokna-center-cafe-v2' : 'sokna-center-cafe-v1';
    $key = $version === 2
        ? sokna_center_secret_key()
        : (function () { global $config; $appKey = trim((string)($config['app']['key'] ?? '')); return hash('sha256', 'sokna-center-cafe-v1|' . $appKey, true); })();
    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16), $aad);
    return is_string($plain) ? $plain : '';
}

function sokna_center_secret(): string
{
    $override = $GLOBALS['SOKNA_CENTER_CONFIG_OVERRIDE'] ?? null;
    if (is_array($override) && array_key_exists('secret', $override)) return (string)$override['secret'];
    return sokna_center_decrypt_secret(setting('sokna_center_handoff_secret_encrypted'));
}

function sokna_center_secret_fingerprint(string $secret): string
{
    return $secret === '' ? '' : strtoupper(substr(hash('sha256', $secret), 0, 10));
}

function sokna_center_base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function sokna_center_base64url_decode(string $value): string|false
{
    $pad = strlen($value) % 4;
    if ($pad) $value .= str_repeat('=', 4 - $pad);
    return base64_decode(strtr($value, '-_', '+/'), true);
}

/** Pairing key remains an opaque manager-facing value issued by Center. */
function sokna_center_parse_pairing_key(string $key): array
{
    $key = trim($key);
    if (!str_starts_with($key, 'SC1.')) throw new InvalidArgumentException('کلید اتصال مرکز سکنا معتبر نیست.');
    $raw = sokna_center_base64url_decode(substr($key, 4));
    if ($raw === false) throw new InvalidArgumentException('کلید اتصال مرکز سکنا معتبر نیست.');
    $data = json_decode($raw, true);
    if (!is_array($data) || (int)($data['v'] ?? 0) !== 1 || strtolower((string)($data['issuer'] ?? '')) !== 'cafe') {
        throw new InvalidArgumentException('این کلید برای اتصال کافه صادر نشده است.');
    }
    $baseUrl = sokna_center_validate_base_url((string)($data['center'] ?? ''));
    $secret = (string)($data['secret'] ?? '');
    if (strlen($secret) < 32) throw new InvalidArgumentException('کلید اتصال مرکز سکنا ناقص است.');
    return ['base_url'=>$baseUrl,'secret'=>$secret];
}

function sokna_center_setting_write(string $key, string $value): void
{
    $stmt = db()->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
    $stmt->execute([$key, $value]);
    clear_setting_cache();
}

function sokna_center_setting_delete(string $key): void
{
    $stmt = db()->prepare('DELETE FROM settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    clear_setting_cache();
}

/**
 * Stage a candidate secret through the same encrypted Settings storage used by the
 * active relation. This makes pair/re-pair immune to stale in-memory secrets while
 * preserving the currently active relation until Center accepts the candidate.
 */
function sokna_center_stage_pair_secret(string $secret): string
{
    if ($secret === '' || strlen($secret) < 32) throw new InvalidArgumentException('کلید اتصال مرکز سکنا ناقص است.');
    sokna_center_setting_delete('sokna_center_pair_candidate_secret_encrypted');
    sokna_center_setting_write('sokna_center_pair_candidate_secret_encrypted', sokna_center_encrypt_secret($secret));
    $loaded = sokna_center_decrypt_secret(setting('sokna_center_pair_candidate_secret_encrypted'));
    if ($loaded === '' || !hash_equals($secret, $loaded)) {
        sokna_center_setting_delete('sokna_center_pair_candidate_secret_encrypted');
        throw new RuntimeException('اعتبارسنجی امن کلید اتصال انجام نشد.');
    }
    return $loaded;
}

function sokna_center_clear_pair_secret_stage(): void
{
    sokna_center_setting_delete('sokna_center_pair_candidate_secret_encrypted');
}

/** Persist integration config only. Event audit is owned by pair/probe/handoff flows. */
function sokna_center_save_connection_settings(bool $enabled, string $baseUrl, string $newSecret): void
{
    $baseUrl = trim($baseUrl);
    if ($baseUrl !== '') $baseUrl = sokna_center_validate_base_url($baseUrl);
    $effectiveSecret = $newSecret !== '' ? $newSecret : sokna_center_secret();
    if ($enabled && ($baseUrl === '' || $effectiveSecret === '')) throw new RuntimeException('تنظیم اتصال مرکز سکنا کامل نیست.');
    if ($newSecret !== '' && strlen($newSecret) < 32) throw new InvalidArgumentException('کلید اتصال مرکز سکنا ناقص است.');

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $save = $pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
        $save->execute(['sokna_center_connection_enabled', $enabled ? '1' : '0']);
        $save->execute(['sokna_center_base_url', $baseUrl]);
        if ($newSecret !== '') {
            $save->execute(['sokna_center_handoff_secret_encrypted', sokna_center_encrypt_secret($newSecret)]);
            $save->execute(['sokna_center_handoff_secret_fingerprint', sokna_center_secret_fingerprint($newSecret)]);
        }
        $pdo->commit();
        clear_setting_cache();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function sokna_center_origin_from_url(string $url): string
{
    $parts = parse_url(trim($url));
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    $host = strtolower((string)($parts['host'] ?? ''));
    if (!in_array($scheme, ['http','https'], true) || $host === '') throw new RuntimeException('نشانی این نصب کافه قابل تشخیص نیست.');
    $port = isset($parts['port']) ? ':' . (int)$parts['port'] : '';
    return $scheme . '://' . $host . $port;
}

function sokna_center_request_origin(): string
{
    $override = $GLOBALS['SOKNA_CENTER_CONFIG_OVERRIDE'] ?? null;
    if (is_array($override) && !empty($override['origin'])) return sokna_center_origin_from_url((string)$override['origin']);
    return sokna_center_origin_from_url(app_base_url());
}

/** Stable return target; it redirects the authenticated user to their own Cafe home. */
function sokna_center_return_url(): string
{
    $override = $GLOBALS['SOKNA_CENTER_CONFIG_OVERRIDE'] ?? null;
    if (is_array($override) && !empty($override['return_url'])) return (string)$override['return_url'];
    return rtrim(app_base_url(), '/') . '/center_return.php';
}

function sokna_center_sign_compact(array $payload, string $secret, string $type = 'SOKNA-HANDOFF'): string
{
    if ($secret === '') throw new RuntimeException('کلید اتصال مرکز سکنا تنظیم نشده است.');
    $header = ['alg'=>'HS256','typ'=>$type,'v'=>1];
    $h = sokna_center_base64url_encode((string)json_encode($header, JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    $p = sokna_center_base64url_encode((string)json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    $s = sokna_center_base64url_encode(hash_hmac('sha256', $h . '.' . $p, $secret, true));
    return $h . '.' . $p . '.' . $s;
}

function sokna_center_build_token(
    string $purpose,
    string|int $localUserKey,
    string $context = 'CAFE',
    ?string $baseUrl = null,
    ?string $secret = null,
    string $trustMode = 'strict'
): string {
    $purpose = strtolower(trim($purpose));
    if (!in_array($purpose, ['pair','probe','handoff'], true)) throw new InvalidArgumentException('نوع درخواست اتصال مرکز سکنا معتبر نیست.');
    if (strtoupper(trim($context)) !== 'CAFE') throw new InvalidArgumentException('Context مرکز سکنا برای کافه باید CAFE باشد.');
    if (!in_array($trustMode, ['pair','strict'], true)) throw new InvalidArgumentException('Trust mode معتبر نیست.');
    if ($purpose === 'pair' && $trustMode !== 'pair') throw new InvalidArgumentException('Pair باید صریح باشد.');
    if ($purpose !== 'pair' && $trustMode !== 'strict') throw new InvalidArgumentException('این عملیات فقط با اتصال تأییدشده مرکز سکنا مجاز است.');

    $baseUrl = sokna_center_validate_base_url($baseUrl ?? sokna_center_base_url());
    $secret = $secret ?? sokna_center_secret();
    $localUserId = (int)$localUserKey;
    if ($localUserId < 1) throw new RuntimeException('حساب کاربری کافه معتبر نیست.');
    $now = time();
    return sokna_center_sign_compact([
        'iss' => 'cafe',
        'sub' => (string)$localUserId,
        'context' => 'CAFE',
        'aud' => $baseUrl,
        'purpose' => $purpose,
        'iat' => $now,
        'exp' => $now + 60,
        'nonce' => bin2hex(random_bytes(24)),
        'origin' => sokna_center_request_origin(),
        'return_url' => sokna_center_return_url(),
    ], $secret, 'SOKNA-HANDOFF');
}

function sokna_center_endpoint(string $path, ?string $baseUrl = null): string
{
    $base = sokna_center_validate_base_url($baseUrl ?? sokna_center_base_url());
    return $base . '/' . ltrim($path, '/');
}

function sokna_center_http_post_form(string $url, array $fields, string $origin, int $timeoutSeconds = 7): array
{
    $body = http_build_query($fields, '', '&', PHP_QUERY_RFC3986);
    $headers = [
        'Accept: application/json',
        'Content-Type: application/x-www-form-urlencoded',
        'Origin: ' . $origin,
        'User-Agent: Sokna-Cafe/' . app_release_version(),
    ];
    $status = 0; $raw = ''; $error = '';
    $transport = $GLOBALS['SOKNA_CENTER_TRANSPORT'] ?? null;
    if (is_callable($transport)) {
        $response = $transport('POST', $url, $headers, $body, $timeoutSeconds);
        return is_array($response) ? array_merge(['transport_ok'=>false,'http_status'=>0,'raw'=>'','error'=>''], $response) : ['transport_ok'=>false,'http_status'=>0,'raw'=>'','error'=>'transport_invalid'];
    }
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_CONNECTTIMEOUT=>min(3,$timeoutSeconds),
            CURLOPT_TIMEOUT=>$timeoutSeconds,
            CURLOPT_HTTPHEADER=>$headers,
            CURLOPT_POST=>true,
            CURLOPT_POSTFIELDS=>$body,
            CURLOPT_SSL_VERIFYPEER=>true,
            CURLOPT_SSL_VERIFYHOST=>2,
        ]);
        $result = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($result === false) $error = (string)curl_error($ch); else $raw = (string)$result;
        curl_close($ch);
    } else {
        $ctx = stream_context_create([
            'http'=>[
                'method'=>'POST','header'=>implode("\r\n",$headers),'content'=>$body,'timeout'=>$timeoutSeconds,'ignore_errors'=>true,
            ],
            'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true],
        ]);
        $result = @file_get_contents($url, false, $ctx);
        if ($result === false) $error = 'transport_failed'; else $raw = (string)$result;
        foreach (($http_response_header ?? []) as $line) if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) { $status = (int)$m[1]; break; }
    }
    return ['transport_ok'=>$error==='','http_status'=>$status,'raw'=>$raw,'error'=>$error];
}


/**
 * Small read-only S2S token for Cafe -> Center summaries.
 * This deliberately does not reuse or alter the Handoff/Probe claim contract.
 */
function sokna_center_build_s2s_read_token(string $purpose, int $localUserId, ?string $secret = null): string
{
    if ($purpose !== 'payroll_reminder_summary') throw new InvalidArgumentException('Purpose خواندن مرکز سکنا معتبر نیست.');
    if ($localUserId < 1) throw new RuntimeException('حساب کاربری کافه معتبر نیست.');
    $secret = $secret ?? sokna_center_secret();
    if ($secret === '') throw new RuntimeException('اتصال مرکز سکنا هنوز تنظیم نشده است.');
    $now = time();
    return sokna_center_sign_compact([
        'issuer'=>'cafe',
        'audience'=>'center',
        'purpose'=>$purpose,
        'context'=>'CAFE',
        'sub'=>(string)$localUserId,
        'timestamp'=>$now,
        'expires_at'=>$now + 60,
        'nonce'=>sokna_center_base64url_encode(random_bytes(24)),
    ], $secret, 'SOKNA-S2S');
}

/** Read-only GET transport; Authorization is never logged by this owner. */
function sokna_center_http_get(string $url, array $headers, int $timeoutSeconds = 2): array
{
    $timeoutSeconds = max(1, min(5, $timeoutSeconds));
    $headers[] = 'Accept: application/json';
    $headers[] = 'User-Agent: Sokna-Cafe/' . app_release_version();
    $status = 0; $raw = ''; $error = '';
    $transport = $GLOBALS['SOKNA_CENTER_TRANSPORT'] ?? null;
    if (is_callable($transport)) {
        $response = $transport('GET', $url, $headers, '', $timeoutSeconds);
        return is_array($response)
            ? array_merge(['transport_ok'=>false,'http_status'=>0,'raw'=>'','error'=>''], $response)
            : ['transport_ok'=>false,'http_status'=>0,'raw'=>'','error'=>'transport_invalid'];
    }
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_CONNECTTIMEOUT=>min(1,$timeoutSeconds),
            CURLOPT_TIMEOUT=>$timeoutSeconds,
            CURLOPT_HTTPHEADER=>$headers,
            CURLOPT_SSL_VERIFYPEER=>true,
            CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_FOLLOWLOCATION=>false,
        ]);
        $result = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($result === false) $error = text_substr((string)curl_error($ch), 0, 180); else $raw = (string)$result;
        curl_close($ch);
    } else {
        $ctx = stream_context_create([
            'http'=>[
                'method'=>'GET','header'=>implode("\r\n",$headers),'timeout'=>$timeoutSeconds,'ignore_errors'=>true,
            ],
            'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true],
        ]);
        $result = @file_get_contents($url, false, $ctx);
        if ($result === false) $error = 'transport_failed'; else $raw = (string)$result;
        foreach (($http_response_header ?? []) as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) { $status = (int)$m[1]; break; }
        }
    }
    return ['transport_ok'=>$error==='','http_status'=>$status,'raw'=>$raw,'error'=>$error];
}

/**
 * Official Core count-only Payroll Reminder read. No HR detail crosses this boundary.
 */
function sokna_center_payroll_reminder_count(int $localUserId, int $timeoutSeconds = 2): int
{
    if (!sokna_center_connection_enabled()) throw new SoknaCenterAuthException('integration_disabled', sokna_center_unavailable_message());
    $secret = sokna_center_secret();
    if ($secret === '') throw new SoknaCenterAuthException('integration_disabled', sokna_center_unavailable_message());
    $token = sokna_center_build_s2s_read_token('payroll_reminder_summary', $localUserId, $secret);
    $response = sokna_center_http_get(
        sokna_center_endpoint('/api/s2s/payroll_reminders.php'),
        ['Authorization: Sokna-HMAC ' . $token],
        $timeoutSeconds
    );
    $http = (int)($response['http_status'] ?? 0);
    $transportOk = (bool)($response['transport_ok'] ?? false);
    $error = strtolower((string)($response['error'] ?? ''));
    $diagnostics = [
        'http_status'=>$http,
        'timeout'=>(!$transportOk && (str_contains($error,'timeout') || str_contains($error,'timed out'))) ? 1 : 0,
    ];
    if (!$transportOk || $http === 0 || $http >= 500) {
        throw new SoknaCenterAuthException('center_unavailable', sokna_center_unavailable_message(), 0, null, $diagnostics);
    }
    if ($http === 401 || $http === 403) {
        throw new SoknaCenterAuthException('trust_rejected', 'دسترسی این خلاصه تأیید نشد.', 0, null, $diagnostics);
    }
    if ($http < 200 || $http >= 300) {
        throw new SoknaCenterAuthException('remote_rejected', sokna_center_unavailable_message(), 0, null, $diagnostics);
    }
    $payload = json_decode((string)($response['raw'] ?? ''), true);
    $count = is_array($payload) && ($payload['ok'] ?? null) === true && is_array($payload['data'] ?? null)
        ? ($payload['data']['count'] ?? null)
        : null;
    if (!(is_int($count) || (is_string($count) && preg_match('/^\d+$/', $count))) || (int)$count < 0) {
        throw new SoknaCenterAuthException('payload_invalid', sokna_center_unavailable_message(), 0, null, $diagnostics);
    }
    return (int)$count;
}

function sokna_center_update_connection_state(bool $success, string $error = ''): void
{
    $now = date('Y-m-d H:i:s');
    if ($success) {
        sokna_center_setting_write('sokna_center_last_success_at', $now);
        sokna_center_setting_write('sokna_center_last_error', '');
        sokna_center_setting_write('sokna_center_last_error_at', '');
        return;
    }
    sokna_center_setting_write('sokna_center_last_error', text_substr(trim($error), 0, 200));
    sokna_center_setting_write('sokna_center_last_error_at', $now);
}

function sokna_center_unavailable_message(): string
{
    return 'مرکز سکنا موقتاً در دسترس نیست.';
}

function sokna_center_remote_result(array $response, string $operation): array
{
    if (!($response['transport_ok'] ?? false)) {
        throw new SoknaCenterAuthException('transport', sokna_center_unavailable_message());
    }
    $http = (int)($response['http_status'] ?? 0);
    if ($http >= 500 || $http === 0) throw new SoknaCenterAuthException('center_unavailable', sokna_center_unavailable_message());
    $payload = json_decode((string)($response['raw'] ?? ''), true);
    if ($http < 200 || $http >= 300 || !is_array($payload) || ($payload['ok'] ?? null) !== true) {
        $reason = $http === 401 || $http === 403 ? 'trust_rejected' : 'remote_rejected';
        $remoteCode = is_array($payload) && is_array($payload['error'] ?? null)
            ? trim((string)($payload['error']['code'] ?? ''))
            : '';
        $message = $operation === 'pair' ? 'کلید اتصال مرکز سکنا تأیید نشد.' : 'مرکز سکنا اتصال فعلی را تأیید نکرد.';
        throw new SoknaCenterAuthException($reason, $message, 0, null, [
            'http_status'=>$http,
            'remote_error_code'=>text_substr($remoteCode, 0, 80),
        ]);
    }
    $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
    if (strtoupper((string)($data['context'] ?? '')) !== 'CAFE' || strtolower((string)($data['issuer'] ?? '')) !== 'cafe') {
        throw new SoknaCenterAuthException('contract_mismatch', 'مرکز سکنا اتصال فعلی را تأیید نکرد.');
    }
    if (isset($data['origin']) && sokna_center_origin_from_url((string)$data['origin']) !== sokna_center_request_origin()) {
        throw new SoknaCenterAuthException('origin_mismatch', 'مرکز سکنا اتصال فعلی را تأیید نکرد.');
    }
    if (isset($data['return_url']) && rtrim((string)$data['return_url'], '/') !== rtrim(sokna_center_return_url(), '/')) {
        throw new SoknaCenterAuthException('return_url_mismatch', 'مرکز سکنا اتصال فعلی را تأیید نکرد.');
    }
    return $data;
}

function sokna_center_pair_remote(string $baseUrl, string $secret, int $actorUserId): array
{
    $token = sokna_center_build_token('pair', $actorUserId, 'CAFE', $baseUrl, $secret, 'pair');

    // Mandatory local verification before any network call. This proves the exact
    // compact token can be verified with the exact secret passed to HMAC.
    $claims = sokna_center_verify_compact($token, $secret, 'SOKNA-HANDOFF');
    $now = time();
    if (
        ($claims['iss'] ?? '') !== 'cafe' ||
        (string)($claims['sub'] ?? '') !== (string)$actorUserId ||
        ($claims['context'] ?? '') !== 'CAFE' ||
        ($claims['aud'] ?? '') !== $baseUrl ||
        ($claims['purpose'] ?? '') !== 'pair' ||
        (int)($claims['iat'] ?? 0) < $now - 5 ||
        (int)($claims['exp'] ?? 0) > $now + 60 ||
        (int)($claims['exp'] ?? 0) <= (int)($claims['iat'] ?? 0)
    ) {
        throw new SoknaCenterAuthException('local_pair_contract_invalid', 'اتصال مرکز سکنا انجام نشد.');
    }

    $response = sokna_center_http_post_form(sokna_center_endpoint('/auth/pair.php', $baseUrl), ['token'=>$token], sokna_center_request_origin(), 7);
    return sokna_center_remote_result($response, 'pair');
}

/** Explicit pair/re-pair. Normal probe/handoff never uses this endpoint or trust mode. */
function sokna_center_connect_with_key(string $pairingKey, int $actorUserId): array
{
    if (!sokna_center_personnel_module_enabled()) throw new RuntimeException('قابلیت پرسنل و حقوق در سامانه غیرفعال است.');
    $diag = [];
    try {
        $parsed = sokna_center_parse_pairing_key($pairingKey);
        $baseUrl = (string)$parsed['base_url'];
        $extractedSecret = (string)$parsed['secret'];
        $diag['secret_fp_extracted'] = sokna_center_secret_fingerprint($extractedSecret);

        // Stage through encrypted Cafe Settings, reload, and use the reloaded candidate
        // for HMAC. The active relation is not replaced until Center accepts Pair.
        $storedCandidateSecret = sokna_center_stage_pair_secret($extractedSecret);
        $diag['secret_fp_storage'] = sokna_center_secret_fingerprint($storedCandidateSecret);
        if (!hash_equals($extractedSecret, $storedCandidateSecret)) {
            throw new RuntimeException('اعتبارسنجی امن کلید اتصال انجام نشد.');
        }

        $hmacSecret = $storedCandidateSecret;
        $diag['secret_fp_hmac'] = sokna_center_secret_fingerprint($hmacSecret);
        $pair = sokna_center_pair_remote($baseUrl, $hmacSecret, $actorUserId);

        sokna_center_save_connection_settings(true, $baseUrl, $storedCandidateSecret);
        $activeSecret = sokna_center_secret();
        if ($activeSecret === '' || !hash_equals($extractedSecret, $activeSecret)) {
            throw new RuntimeException('کلید اتصال ذخیره‌شده با کلید جدید مطابقت ندارد.');
        }
        $diag['secret_fp_storage'] = sokna_center_secret_fingerprint($activeSecret);
        sokna_center_update_connection_state(true);
        audit_log_write('center_pair_success', 'integration', 'sokna_center', [
            'center_host'=>(string)(parse_url($baseUrl, PHP_URL_HOST) ?: ''),
            'version'=>text_substr((string)($pair['version'] ?? ''), 0, 40),
        ], $actorUserId);
    } catch (Throwable $e) {
        if ($e instanceof SoknaCenterAuthException) {
            $diag = array_merge($diag, $e->diagnostics);
        }
        $reason = $e instanceof SoknaCenterAuthException ? $e->reasonCode : ($e instanceof InvalidArgumentException ? 'invalid_pairing_key' : 'local_failure');
        audit_log_write('center_pair_failed', 'integration', 'sokna_center', array_merge(['reason'=>$reason], array_filter($diag, static fn($v)=>$v!=='' && $v!==null)), $actorUserId);
        if ($e instanceof SoknaCenterAuthException || $e instanceof InvalidArgumentException) throw $e;
        throw new RuntimeException('اتصال مرکز سکنا انجام نشد.');
    } finally {
        try { sokna_center_clear_pair_secret_stage(); } catch (Throwable) {}
    }

    // Pair succeeded and is persisted. Test the newly registered relation in STRICT mode.
    return sokna_center_test_connection($actorUserId);
}

function sokna_center_test_connection(int $actorUserId, ?string $baseUrl = null, ?string $secret = null, bool $audit = true): array
{
    if (!sokna_center_personnel_module_enabled()) throw new RuntimeException('قابلیت پرسنل و حقوق در سامانه غیرفعال است.');
    $baseUrl = sokna_center_validate_base_url($baseUrl ?? sokna_center_base_url());
    $secret = $secret ?? sokna_center_secret();
    if ($secret === '') throw new RuntimeException('اتصال مرکز سکنا هنوز تنظیم نشده است.');
    try {
        $token = sokna_center_build_token('probe', $actorUserId, 'CAFE', $baseUrl, $secret, 'strict');
        $response = sokna_center_http_post_form(sokna_center_endpoint('/auth/probe.php', $baseUrl), ['token'=>$token], sokna_center_request_origin(), 7);
        $data = sokna_center_remote_result($response, 'probe');
        sokna_center_update_connection_state(true);
        if ($audit) audit_log_write('center_probe_success', 'integration', 'sokna_center', ['version'=>text_substr((string)($data['version'] ?? ''), 0, 40)], $actorUserId);
        return $data;
    } catch (Throwable $e) {
        $public = $e instanceof SoknaCenterAuthException ? $e->getMessage() : sokna_center_unavailable_message();
        $reason = $e instanceof SoknaCenterAuthException ? $e->reasonCode : 'local_failure';
        sokna_center_update_connection_state(false, $public);
        if ($audit) audit_log_write('center_probe_failed', 'integration', 'sokna_center', ['reason'=>$reason], $actorUserId);
        if ($e instanceof SoknaCenterAuthException) throw $e;
        throw new RuntimeException($public);
    }
}

/**
 * Center owns HR authorization. Cafe only caches a short-lived visibility hint
 * so the sidebar does not need a blocking Center request on every page load.
 * Staff visibility is fail-closed: missing/unknown entitlement data is not permission.
 * Admin may retain the management launcher for backward compatibility, while Center still authorizes the handoff.
 */
function sokna_center_personnel_entitlement_from_data(array $data): ?bool
{
    $candidates = [];
    if (array_key_exists('can_open_personnel', $data)) $candidates[] = $data['can_open_personnel'];
    $entitlements = is_array($data['entitlements'] ?? null) ? $data['entitlements'] : [];
    if (array_key_exists('can_open_personnel', $entitlements)) $candidates[] = $entitlements['can_open_personnel'];
    if (array_key_exists('personnel', $entitlements)) $candidates[] = $entitlements['personnel'];
    foreach ($candidates as $value) {
        if (is_bool($value)) return $value;
        if ($value === 1 || $value === 0 || $value === '1' || $value === '0') return (bool)(int)$value;
    }
    return null;
}

function sokna_center_personnel_cache_key(int $localUserId): string
{
    return 'sokna_center_personnel_access_' . max(0, $localUserId);
}

function sokna_center_personnel_cache_fingerprint(): string
{
    return hash('sha256', sokna_center_base_url() . '|' . sokna_center_secret_fingerprint(sokna_center_secret()));
}

/** @return array{state:string,fresh:bool,checked_at:int} */
function sokna_center_personnel_access_state(int $localUserId, int $ttlSeconds = 600): array
{
    if ($localUserId < 1 || !sokna_center_connection_enabled()) return ['state'=>'deny','fresh'=>true,'checked_at'=>0];
    $raw = setting(sokna_center_personnel_cache_key($localUserId));
    $cached = json_decode($raw, true);
    if (!is_array($cached)) return ['state'=>'unknown','fresh'=>false,'checked_at'=>0];
    $checkedAt = max(0, (int)($cached['checked_at'] ?? 0));
    $fresh = $checkedAt > 0 && (time() - $checkedAt) < max(60, $ttlSeconds)
        && hash_equals((string)($cached['fingerprint'] ?? ''), sokna_center_personnel_cache_fingerprint());
    if (!$fresh) return ['state'=>'unknown','fresh'=>false,'checked_at'=>$checkedAt];
    $allowed = $cached['allowed'] ?? null;
    if ($allowed === true || $allowed === 1) return ['state'=>'allow','fresh'=>true,'checked_at'=>$checkedAt];
    if ($allowed === false || $allowed === 0) return ['state'=>'deny','fresh'=>true,'checked_at'=>$checkedAt];
    return ['state'=>'unknown','fresh'=>true,'checked_at'=>$checkedAt];
}

function sokna_center_cache_personnel_access(int $localUserId, ?bool $allowed): void
{
    if ($localUserId < 1) return;
    sokna_center_setting_write(sokna_center_personnel_cache_key($localUserId), json_encode([
        'allowed'=>$allowed,
        'checked_at'=>time(),
        'fingerprint'=>sokna_center_personnel_cache_fingerprint(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/** Refresh the visibility hint using the existing strict probe contract. */
function sokna_center_refresh_personnel_access(int $actorUserId): ?bool
{
    $data = sokna_center_test_connection($actorUserId, null, null, false);
    $allowed = sokna_center_personnel_entitlement_from_data($data);
    sokna_center_cache_personnel_access($actorUserId, $allowed);
    return $allowed;
}

function sokna_center_handoff_target(): string
{
    if (!sokna_center_connection_enabled()) throw new RuntimeException('اتصال مرکز سکنا هنوز فعال نشده است.');
    return sokna_center_endpoint('/auth/handoff.php');
}

function sokna_center_handoff_token(array $user, string $context = 'CAFE'): string
{
    $id = (int)($user['id'] ?? 0);
    if ($id < 1) throw new RuntimeException('حساب کاربری کافه معتبر نیست.');
    return sokna_center_build_token('handoff', $id, $context, null, null, 'strict');
}

/** Read Authorization consistently under Apache/FastCGI. */
function sokna_center_authorization_header(): string
{
    $header = trim((string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
    if ($header === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp((string)$name, 'Authorization') === 0) { $header = trim((string)$value); break; }
        }
    }
    return $header;
}

/** Verify a compact HMAC token without exposing the shared secret. */
function sokna_center_verify_compact(string $token, string $secret, string $expectedType): array
{
    $parts = explode('.', trim($token));
    if (count($parts) !== 3) throw new SoknaCenterAuthException('signature_missing');
    [$h,$p,$s] = $parts;
    $headerRaw = sokna_center_base64url_decode($h);
    $payloadRaw = sokna_center_base64url_decode($p);
    $signature = sokna_center_base64url_decode($s);
    if ($headerRaw === false || $payloadRaw === false || $signature === false) throw new SoknaCenterAuthException('signature_invalid');
    $header = json_decode($headerRaw, true);
    $payload = json_decode($payloadRaw, true);
    if (!is_array($header) || !is_array($payload) || ($header['alg'] ?? '') !== 'HS256' || ($header['typ'] ?? '') !== $expectedType || (int)($header['v'] ?? 0) !== 1) {
        throw new SoknaCenterAuthException('signature_invalid');
    }
    $expected = hash_hmac('sha256', $h . '.' . $p, $secret, true);
    if (!hash_equals($expected, $signature)) throw new SoknaCenterAuthException('signature_invalid');
    return $payload;
}

/** Durable replay guard using the existing settings table; no schema change is required. */
function sokna_center_claim_directory_nonce(string $nonce, int $expiresAt): bool
{
    $override = $GLOBALS['SOKNA_CENTER_NONCE_STORE'] ?? null;
    if (is_callable($override)) return (bool)$override($nonce, $expiresAt);

    $key = 'sokna_center_nonce_' . hash('sha256', $nonce);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $cleanup = $pdo->prepare("DELETE FROM settings WHERE setting_key LIKE 'sokna_center_nonce_%' AND CAST(setting_value AS UNSIGNED) < ?");
        $cleanup->execute([time() - 60]);
        try {
            $insert = $pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?)');
            $insert->execute([$key, (string)$expiresAt]);
        } catch (PDOException $e) {
            if ((string)$e->getCode() === '23000') { $pdo->rollBack(); return false; }
            throw $e;
        }
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}



/** Canonical allow-list mapper for Center user discovery. */
function sokna_center_directory_public_user(array $row): array
{
    return [
        'local_user_id' => (string)(int)($row['id'] ?? 0),
        'display_name' => (string)($row['display_name'] ?? ''),
        'role' => (string)($row['role'] ?? ''),
        'active' => (int)($row['active'] ?? 0) === 1,
        'updated_at' => (string)($row['updated_at'] ?? ''),
    ];
}

/**
 * Authenticate Center -> Cafe user-directory calls.
 * Wire contract: Authorization: Sokna-HMAC <SOKNA-S2S compact token>
 */
function sokna_center_verify_user_directory_request(int $page, int $perPage): array
{
    if (!sokna_center_connection_enabled()) throw new SoknaCenterAuthException('integration_disabled');
    $secret = sokna_center_secret();
    if ($secret === '') throw new SoknaCenterAuthException('integration_disabled');
    $header = sokna_center_authorization_header();
    if (!preg_match('/^Sokna-HMAC\s+(.+)$/i', $header, $m)) throw new SoknaCenterAuthException('signature_missing');
    $payload = sokna_center_verify_compact(trim($m[1]), $secret, 'SOKNA-S2S');

    $issuer = strtolower(trim((string)($payload['issuer'] ?? '')));
    $audience = strtolower(trim((string)($payload['audience'] ?? '')));
    $purpose = strtolower(trim((string)($payload['purpose'] ?? '')));
    $context = strtoupper(trim((string)($payload['context'] ?? '')));
    $timestamp = (int)($payload['timestamp'] ?? 0);
    $expiresAt = (int)($payload['expires_at'] ?? 0);
    $nonce = trim((string)($payload['nonce'] ?? ''));
    $signedPage = (int)($payload['page'] ?? 0);
    $signedPerPage = (int)($payload['per_page'] ?? 0);
    $now = time();

    if ($issuer !== 'center' || $audience !== 'cafe' || $purpose !== 'user_directory' || $context !== 'CAFE') {
        throw new SoknaCenterAuthException('claims_invalid');
    }
    if ($timestamp < 1 || $expiresAt < 1 || $expiresAt - $timestamp < 1 || $expiresAt - $timestamp > 60 || $timestamp > $now + 15 || $expiresAt < $now || $timestamp < $now - 60) {
        throw new SoknaCenterAuthException('request_expired');
    }
    if (!preg_match('/^[A-Za-z0-9_-]{16,128}$/', $nonce)) throw new SoknaCenterAuthException('nonce_invalid');
    if ($signedPage !== $page || $signedPerPage !== $perPage) throw new SoknaCenterAuthException('pagination_mismatch');
    if (!sokna_center_claim_directory_nonce($nonce, $expiresAt)) throw new SoknaCenterAuthException('nonce_replay');
    return $payload;
}
