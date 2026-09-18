<?php
declare(strict_types=1);

function current_user(): ?array
{
    static $validated = false;
    static $validatedUser = null;

    if ($validated) return $validatedUser;
    $user = $_SESSION['user'] ?? null;
    if (!$user) return null;
    $lastActivity = (int)($_SESSION['user_last_activity'] ?? time());
    if (time() - $lastActivity > 12 * 3600) {
        logout_user();
        $validated = true;
        return null;
    }

    try {
        $stmt = db()->prepare('SELECT id,username,display_name,role,active FROM users WHERE id=? LIMIT 1');
        $stmt->execute([(int)($user['id'] ?? 0)]);
        $fresh = $stmt->fetch();
        if (!$fresh || (int)$fresh['active'] !== 1) {
            logout_user();
            $validated = true;
            return null;
        }
        $user = [
            'id' => (int)$fresh['id'],
            'username' => (string)$fresh['username'],
            'display_name' => (string)$fresh['display_name'],
            'role' => (string)$fresh['role'],
        ];
        $_SESSION['user'] = $user;
    } catch (Throwable) {
        // Keep an already authenticated shift usable during a transient DB error.
    }

    $_SESSION['user_last_activity'] = time();
    auth_refresh_session_cookie();
    $validatedUser = $user;
    $validated = true;
    return $validatedUser;
}

function auth_refresh_session_cookie(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE || !ini_get('session.use_cookies')) return;
    $params = session_get_cookie_params();
    setcookie(session_name(), session_id(), [
        'expires' => time() + 12 * 3600,
        'path' => (string)($params['path'] ?? '/'),
        'domain' => (string)($params['domain'] ?? ''),
        'secure' => (bool)($params['secure'] ?? false),
        'httponly' => (bool)($params['httponly'] ?? true),
        'samesite' => (string)($params['samesite'] ?? 'Lax'),
    ]);
}

function is_logged_in(): bool
{
    return current_user() !== null;
}


/** Return the first workspace the current account can actually use. */
function user_home_path(?array $user = null): string
{
    $user ??= current_user();
    if (!$user) return app_base_url() . '/login.php';
    if (($user['role'] ?? '') === 'admin') return app_base_url() . '/admin/index.php';
    if (user_has_capability('orders_floor', $user)
        || user_has_capability('cashier_accounts', $user)
        || user_has_capability('shift_supervision', $user)) {
        return app_base_url() . '/operator/index.php';
    }
    if (user_has_capability('preparation', $user)) {
        return app_base_url() . '/waiter/index.php';
    }
    if (sokna_module_enabled('inventory') && user_has_inventory_access($user)) {
        return app_base_url() . '/admin/inventory.php';
    }
    return app_base_url() . '/help.php';
}

/** Keep post-login redirects on this installation and reject protocol-relative/external targets. */
function safe_local_redirect_target(mixed $candidate, string $fallback): string
{
    $target = trim((string)$candidate);
    if ($target === '' || preg_match('/[\r\n]/', $target) || str_starts_with($target, '//')) return $fallback;
    $parts = parse_url($target);
    if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || isset($parts['user']) || isset($parts['port'])) return $fallback;
    $path = (string)($parts['path'] ?? '');
    if ($path === '' || str_contains($path, "\0") || preg_match('#(^|/)\.\.(/|$)#', $path)) return $fallback;
    if (!str_starts_with($path, '/')) {
        $target = rtrim(app_base_url(), '/') . '/' . ltrim($target, '/');
    }
    return $target;
}



/** Lightweight web-login throttling. No credential or username is persisted; only hashed
 * client/account buckets and recent failure timestamps are stored under protected runtime storage. */
function auth_login_rate_path(): string
{
    $override = PHP_SAPI === 'cli' ? trim((string)(getenv('SOKNA_AUTH_RATE_PATH') ?: '')) : '';
    if ($override !== '') return $override;
    return dirname(__DIR__) . '/storage/security/login-rate.json';
}

function auth_login_rate_client_key(): string
{
    $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown')) ?: 'unknown';
    return hash('sha256', 'ip|' . $ip);
}

function auth_login_rate_account_key(int $userId): string
{
    return hash('sha256', 'account|' . auth_login_rate_client_key() . '|' . max(0, $userId));
}

/** @return array{allowed:bool,retry_after:int,account_failures:int,ip_failures:int} */
function auth_login_rate_status(int $userId): array
{
    return auth_login_rate_update($userId, 'status');
}

function auth_login_rate_fail(int $userId): void
{
    auth_login_rate_update($userId, 'fail');
}

function auth_login_rate_clear(int $userId): void
{
    auth_login_rate_update($userId, 'clear');
}

/** @return array{allowed:bool,retry_after:int,account_failures:int,ip_failures:int} */
function auth_login_rate_update(int $userId, string $operation): array
{
    $path = auth_login_rate_path();
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        // Rate limiting must not lock staff out merely because runtime storage is temporarily unavailable.
        error_log('login rate storage unavailable');
        return ['allowed'=>true,'retry_after'=>0,'account_failures'=>0,'ip_failures'=>0];
    }
    @chmod($dir, 0700);
    $handle = @fopen($path, 'c+');
    if (!$handle) {
        error_log('login rate file unavailable');
        return ['allowed'=>true,'retry_after'=>0,'account_failures'=>0,'ip_failures'=>0];
    }

    $now = time();
    $window = 10 * 60;
    $accountLimit = 8;
    $ipLimit = 30;
    $accountKey = auth_login_rate_account_key($userId);
    $ipKey = auth_login_rate_client_key();
    $result = ['allowed'=>true,'retry_after'=>0,'account_failures'=>0,'ip_failures'=>0];

    try {
        if (!flock($handle, LOCK_EX)) return $result;
        rewind($handle);
        $raw = stream_get_contents($handle);
        $state = json_decode(is_string($raw) ? $raw : '', true);
        if (!is_array($state)) $state = ['accounts'=>[], 'ips'=>[]];
        foreach (['accounts','ips'] as $bucket) if (!is_array($state[$bucket] ?? null)) $state[$bucket] = [];

        $prune = static function (array $timestamps) use ($now, $window): array {
            return array_values(array_filter($timestamps, static fn($ts): bool => is_int($ts) && $ts > $now - $window && $ts <= $now + 5));
        };
        foreach ($state['accounts'] as $key => $timestamps) {
            $kept = $prune(is_array($timestamps) ? $timestamps : []);
            if ($kept) $state['accounts'][$key] = $kept; else unset($state['accounts'][$key]);
        }
        foreach ($state['ips'] as $key => $timestamps) {
            $kept = $prune(is_array($timestamps) ? $timestamps : []);
            if ($kept) $state['ips'][$key] = $kept; else unset($state['ips'][$key]);
        }

        $accountAttempts = $prune((array)($state['accounts'][$accountKey] ?? []));
        $ipAttempts = $prune((array)($state['ips'][$ipKey] ?? []));

        if ($operation === 'clear') {
            unset($state['accounts'][$accountKey]);
            $accountAttempts = [];
        } elseif ($operation === 'fail') {
            $accountAttempts[] = $now;
            $ipAttempts[] = $now;
            $state['accounts'][$accountKey] = $accountAttempts;
            $state['ips'][$ipKey] = $ipAttempts;
        }

        $blocked = count($accountAttempts) >= $accountLimit || count($ipAttempts) >= $ipLimit;
        $oldestRelevant = [];
        if (count($accountAttempts) >= $accountLimit) $oldestRelevant[] = min($accountAttempts);
        if (count($ipAttempts) >= $ipLimit) $oldestRelevant[] = min($ipAttempts);
        $retryAfter = $blocked && $oldestRelevant ? max(1, ($window + min($oldestRelevant)) - $now) : 0;
        $result = [
            'allowed'=>!$blocked,
            'retry_after'=>$retryAfter,
            'account_failures'=>count($accountAttempts),
            'ip_failures'=>count($ipAttempts),
        ];

        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, json_encode($state, JSON_UNESCAPED_SLASHES));
        fflush($handle);
        @chmod($path, 0600);
        flock($handle, LOCK_UN);
    } catch (Throwable $e) {
        error_log('login rate limiter: ' . $e->getMessage());
        $result = ['allowed'=>true,'retry_after'=>0,'account_failures'=>0,'ip_failures'=>0];
    } finally {
        fclose($handle);
    }
    return $result;
}

function login(string $username, string $password): bool
{
    $username = trim($username);
    if ($username === '' || $password === '') return false;

    $stmt = db()->prepare('SELECT id,username,password_hash,display_name,role FROM users WHERE username=? AND active=1 LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, (string)$user['password_hash'])) return false;

    login_user($user);
    return true;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_last_activity'] = time();
    $_SESSION['user'] = [
        'id' => (int)$user['id'],
        'username' => $user['username'],
        'display_name' => $user['display_name'],
        'role' => $user['role'],
    ];
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function request_expects_json(): bool
{
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    $requestedWith = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    return str_contains($accept, 'application/json')
        || str_contains($contentType, 'application/json')
        || $requestedWith === 'xmlhttprequest';
}

/** Keep unauthorized staff out of restricted pages without trapping them on a raw error screen. */
function deny_access_and_return(?array $user = null): never
{
    $user ??= current_user();
    $name = trim((string)($user['display_name'] ?? '')) ?: 'این کاربر';
    $message = $name . ' به این بخش دسترسی ندارد.';

    if (request_expects_json()) {
        json_response(['success' => false, 'message' => $message], 403);
    }

    if (!$user) {
        $target = urlencode($_SERVER['REQUEST_URI'] ?? '/');
        redirect(app_base_url() . '/login.php?next=' . $target);
    }

    flash('warning', $message . ' به صفحه مجاز شما برگشتید.');
    $home = user_home_path($user);
    $currentPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '');
    $homePath = (string)(parse_url($home, PHP_URL_PATH) ?: '');
    if ($homePath !== '' && $currentPath === $homePath) {
        http_response_code(403);
        exit($message);
    }
    redirect($home);
}

function require_login(array $roles = []): void
{
    $user = current_user();
    if (!$user) {
        if (request_expects_json()) json_response(['success' => false, 'message' => 'ابتدا وارد سامانه شوید.'], 401);
        $target = urlencode($_SERVER['REQUEST_URI'] ?? '/');
        redirect(app_base_url() . '/login.php?next=' . $target);
    }
    if ($roles && !in_array($user['role'], $roles, true)) deny_access_and_return($user);
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}


function require_capability(string $capability): void
{
    require_login();
    if (!user_has_capability($capability)) deny_access_and_return();
}

function require_any_capability(array $capabilities): void
{
    require_login();
    foreach ($capabilities as $capability) {
        if (user_has_capability((string)$capability)) return;
    }
    deny_access_and_return();
}
