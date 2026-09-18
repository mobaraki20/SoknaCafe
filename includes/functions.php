<?php
declare(strict_types=1);

require_once __DIR__ . '/request_path.php';

function config(): array
{
    return isset($GLOBALS['config']) && is_array($GLOBALS['config']) ? $GLOBALS['config'] : [];
}

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Unicode-safe text helpers with a dependency-free fallback for constrained hosts. */
function text_substr(string $value, int $start, ?int $length = null, ?string $encoding = 'UTF-8'): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($value, $start, $length, $encoding ?: 'UTF-8');
    }
    $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($characters)) {
        return $length === null ? substr($value, $start) : substr($value, $start, $length);
    }
    return implode('', array_slice($characters, $start, $length));
}

function text_length(string $value, ?string $encoding = 'UTF-8'): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, $encoding ?: 'UTF-8');
    }
    $count = preg_match_all('/./us', $value, $matches);
    return $count === false ? strlen($value) : $count;
}

function text_lower(string $value, ?string $encoding = 'UTF-8'): string
{
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($value, $encoding ?: 'UTF-8');
    }
    return strtolower($value);
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/**
 * Render one recoverable error contract for authenticated pages and APIs.
 * Do not expose raw exception text here; callers provide an operational message.
 */
function render_recovery_error_page(
    int $status,
    string $title,
    string $message,
    string $primaryHref = '',
    string $primaryLabel = 'بازگشت'
): never {
    $status = in_array($status, [404, 409], true) ? $status : 500;
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    $jsonRequest = str_contains($accept, 'application/json')
        || str_contains(strtolower((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json')
        || str_contains($script, '/api/');
    if ($jsonRequest) {
        json_response([
            'ok' => false,
            'error' => $status === 404 ? 'not_found' : 'not_ready',
            'message' => $message,
        ], $status);
    }

    if ($primaryHref === '') {
        if (str_contains($script, '/operator/')) $primaryHref = asset('operator/index.php');
        elseif (str_contains($script, '/waiter/')) $primaryHref = asset('waiter/index.php');
        elseif (str_contains($script, '/admin/')) $primaryHref = asset('admin/index.php');
        else $primaryHref = asset('index.php');
    }

    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, max-age=0');
    $code = fa_digits($status);
    ?>
    <!doctype html>
    <html lang="fa" dir="rtl">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
      <meta name="robots" content="noindex,nofollow">
      <title><?= e($title) ?> | Sokna</title>
      <style>
        :root{color-scheme:light;font-family:Vazirmatn,Tahoma,Arial,sans-serif;background:#f7f3ec;color:#202522}
        *{box-sizing:border-box}body{min-height:100dvh;margin:0;display:grid;place-items:center;padding:20px;background:radial-gradient(circle at top,#fff 0,#f7f3ec 58%)}
        main{width:min(560px,100%);padding:clamp(22px,5vw,38px);border:1px solid #e5dfd6;border-radius:24px;background:#fff;box-shadow:0 20px 60px rgba(32,37,34,.10);text-align:center}
        .code{display:inline-grid;place-items:center;min-width:72px;min-height:36px;padding:5px 12px;border-radius:999px;background:#eef3f0;color:#365b4c;font-weight:900;font-variant-numeric:tabular-nums}
        h1{margin:18px 0 8px;font-size:clamp(1.25rem,4vw,1.7rem)}p{margin:0 auto;color:#626c66;line-height:2;max-width:44ch}
        a{display:inline-flex;align-items:center;justify-content:center;min-height:46px;margin-top:22px;padding:9px 18px;border-radius:13px;background:#365b4c;color:#fff;text-decoration:none;font-weight:850}
        a:focus-visible{outline:3px solid #f3c780;outline-offset:3px}
      </style>
    </head>
    <body><main><span class="code"><?= e($code) ?></span><h1><?= e($title) ?></h1><p><?= e($message) ?></p><a href="<?= e($primaryHref) ?>"><?= e($primaryLabel) ?></a></main></body>
    </html>
    <?php
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_valid(?string $token): bool
{
    return is_string($token) && $token !== '' && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function verify_csrf(?string $token): void
{
    if (!csrf_valid($token)) {
        http_response_code(419);
        exit('درخواست نامعتبر است. صفحه را تازه‌سازی کنید.');
    }
}

function json_response(array $payload, int $status = 200): never
{
    $pushMeta = [];
    $inventoryMeta = [];
    $printMeta = [];
    if ($status >= 200 && $status < 300) {
        if (function_exists('push_response_metadata')) {
            try { $pushMeta = push_response_metadata(); }
            catch (Throwable $e) { error_log('push response metadata unavailable: ' . $e->getMessage()); }
        }
        if (function_exists('inventory_response_metadata')) {
            try { $inventoryMeta = inventory_response_metadata(); }
            catch (Throwable $e) { error_log('inventory response metadata unavailable: ' . $e->getMessage()); }
        }
        if (($payload['success'] ?? true) !== false && function_exists('print_response_metadata')) {
            try { $printMeta = print_response_metadata(); }
            catch (Throwable $e) { error_log('print response metadata unavailable: ' . $e->getMessage()); }
        }
    }
    if ($pushMeta) $payload['_push'] = $pushMeta;
    if ($inventoryMeta) $payload['_inventory'] = $inventoryMeta;
    if ($printMeta) $payload['_print_wake'] = $printMeta;

    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, max-age=0');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // Finish the committed user mutation first. Durable outboxes are recovery truth;
    // after-response drains are only accelerators and must never change the HTTP result.
    $hasAfterResponseWork = ($pushMeta && function_exists('push_after_response_drain'))
        || ($inventoryMeta && function_exists('inventory_after_response_drain'));
    if ($hasAfterResponseWork && function_exists('fastcgi_finish_request')) {
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        fastcgi_finish_request();
        if ($inventoryMeta && function_exists('inventory_after_response_drain')) {
            try { inventory_after_response_drain(); }
            catch (Throwable $e) { error_log('inventory after-response hook: ' . $e->getMessage()); }
        }
        if ($pushMeta && function_exists('push_after_response_drain')) {
            try { push_after_response_drain(); }
            catch (Throwable $e) { error_log('push after-response hook: ' . $e->getMessage()); }
        }
    }
    exit;
}

function json_script(mixed $value): string
{
    return (string)json_encode(
        $value,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
}

function request_json(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true);
    return is_array($data) ? $data : [];
}

function setting(string $key, string $default = ''): string
{
    if (!array_key_exists('sokna_setting_cache', $GLOBALS) || !is_array($GLOBALS['sokna_setting_cache'])) {
        try {
            $rows = db()->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
            $GLOBALS['sokna_setting_cache'] = [];
            foreach ($rows as $row) {
                $GLOBALS['sokna_setting_cache'][(string)$row['setting_key']] = (string)$row['setting_value'];
            }
        } catch (Throwable) {
            $GLOBALS['sokna_setting_cache'] = [];
        }
    }
    $cache = $GLOBALS['sokna_setting_cache'];
    return array_key_exists($key, $cache) ? (string)$cache[$key] : $default;
}

function clear_setting_cache(): void
{
    unset($GLOBALS['sokna_setting_cache']);
}

function setting_bool(string $key, bool $default = false): bool
{
    $fallback = $default ? '1' : '0';
    return setting($key, $fallback) === '1';
}

/** Table sessions are a core operational invariant in current Sokna. */
function waiter_call_allowed(bool $publicContext = false): bool
{
    return $publicContext
        ? setting_bool('public_waiter_call_enabled', false)
        : setting_bool('waiter_call_enabled', true);
}

function table_sessions_enabled(): bool
{
    return true;
}

function valid_hex_color(string $value, string $default = '#6f4e37'): string
{
    $value = trim($value);
    return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : $default;
}

/** Pick the more readable of a dark or light foreground for a configured color. */
function contrast_text_color(string $background, string $dark = '#202522', string $light = '#ffffff'): string
{
    $hex = ltrim(valid_hex_color($background, '#365b4c'), '#');
    $rgb = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    $linear = array_map(static function (int $channel): float {
        $value = $channel / 255;
        return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
    }, $rgb);
    $luminance = 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    $whiteContrast = 1.05 / ($luminance + 0.05);
    $darkHex = ltrim(valid_hex_color($dark, '#202522'), '#');
    $darkRgb = [hexdec(substr($darkHex, 0, 2)), hexdec(substr($darkHex, 2, 2)), hexdec(substr($darkHex, 4, 2))];
    $darkLinear = array_map(static function (int $channel): float {
        $value = $channel / 255;
        return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
    }, $darkRgb);
    $darkLuminance = 0.2126 * $darkLinear[0] + 0.7152 * $darkLinear[1] + 0.0722 * $darkLinear[2];
    $darkContrast = ($luminance + 0.05) / ($darkLuminance + 0.05);
    return $whiteContrast >= $darkContrast ? valid_hex_color($light, '#ffffff') : valid_hex_color($dark, '#202522');
}


function normalize_app_url(string $url): string
{
    $url = trim($url);
    if ($url === '') return '';
    if (preg_match('/[\r\n]/', $url)) return '';
    $parts = parse_url($url);
    if (!is_array($parts)) return '';
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    $host = strtolower((string)($parts['host'] ?? ''));
    if (!in_array($scheme, ['http','https'], true) || $host === '') return '';
    if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) return '';
    $validHost = filter_var($host, FILTER_VALIDATE_IP) !== false
        || (bool)preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i', $host)
        || $host === 'localhost';
    if (!$validHost) return '';
    $port = isset($parts['port']) ? ':' . (int)$parts['port'] : '';
    $path = '/' . ltrim((string)($parts['path'] ?? ''), '/');
    $path = preg_replace('#/+#', '/', $path) ?: '';
    $path = rtrim($path, '/');
    if ($path === '/') $path = '';
    return $scheme . '://' . $host . $port . $path;
}

/** Current request address for internal assets and navigation. */
function detected_app_base_url(): string
{
    global $config;
    if (PHP_SAPI === 'cli' || empty($_SERVER['HTTP_HOST'])) return '';

    $trustProxy = (bool)($config['app']['trust_proxy_headers'] ?? false);
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int)($_SERVER['SERVER_PORT'] ?? 80) === 443);
    if ($trustProxy) {
        $forwardedProto = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0] ?? ''));
        if (in_array($forwardedProto, ['http','https'], true)) $https = $forwardedProto === 'https';
    }
    $scheme = $https ? 'https' : 'http';
    $rawHost = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($rawHost === '' || preg_match('/[\r\n]/', $rawHost)) return '';

    $basePath = app_request_mount_path();
    return normalize_app_url($scheme . '://' . $rawHost . $basePath);
}

/** Stable address used for QR codes and links that leave the current browser request. */
function app_canonical_url(): string
{
    global $config;
    $stored = normalize_app_url(setting('app_canonical_url', ''));
    if ($stored !== '') return $stored;
    $configured = normalize_app_url((string)($config['app']['url'] ?? ''));
    if ($configured !== '') return $configured;
    return detected_app_base_url();
}

/** Runtime address. Internal pages follow the domain currently serving the request. */
function app_base_url(): string
{
    $detected = detected_app_base_url();
    return $detected !== '' ? $detected : app_canonical_url();
}

function app_release_version(): string
{
    static $version = null;
    if ($version !== null) return $version;
    $raw = trim((string)@file_get_contents(dirname(__DIR__) . '/VERSION.txt'));
    $version = preg_match('/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$/', $raw) ? $raw : 'dev';
    return $version;
}

function asset(string $path): string
{
    $clean = ltrim($path, '/');
    $url = app_base_url() . '/' . $clean;
    if (str_starts_with($clean, 'assets/')) {
        // Release identity alone cannot invalidate an asset changed by a corrective
        // update inside the same release line. Use a content address as well; unlike
        // mtime it survives ZIP extraction and cannot point at stale CSS/JS/SVG.
        static $assetDigests = [];
        if (!array_key_exists($clean, $assetDigests)) {
            $file = dirname(__DIR__) . '/' . $clean;
            $digest = is_file($file) ? @hash_file('sha256', $file) : false;
            $assetDigests[$clean] = is_string($digest) ? substr($digest, 0, 12) : '';
        }
        $revision = app_release_version();
        if ($assetDigests[$clean] !== '') $revision .= '.' . $assetDigests[$clean];
        $url .= '?v=' . rawurlencode($revision);
    }
    return $url;
}

function canonical_asset(string $path): string
{
    return rtrim(app_canonical_url(), '/') . '/' . ltrim($path, '/');
}

function guest_page_url(string $path, array $params = []): string
{
    $base = asset($path);
    return $params ? $base . (str_contains($base, '?') ? '&' : '?') . http_build_query($params) : $base;
}

function public_guest_base_url(): string
{
    global $config;
    $relay=is_array($config['relay']??null)?$config['relay']:[];
    if(empty($relay['enabled'])) return '';
    $base=normalize_app_url((string)($relay['public_base_url']??''));
    $installation=trim((string)($relay['installation_id']??''));
    if($base===''||$installation==='') return '';
    return rtrim($base,'/').'/guest/?installation_id='.rawurlencode($installation);
}

function table_menu_url(string $token): string
{
    $public=public_guest_base_url();
    if($public!=='') return $public . '&table=' . rawurlencode($token);
    return canonical_asset('menu/') . '?table=' . rawurlencode($token);
}

function toman_number(int|float|string $amount): string
{
    return fa_digits(number_format((float)$amount, 0, '.', ','));
}

function toman(int|float|string $amount): string
{
    return toman_number($amount) . ' تومان';
}

function parse_toman_amount_text(string|int|null $value): ?int
{
    $raw = trim(en_digits((string)($value ?? '')));
    if ($raw === '') return null;
    $raw = str_replace(['تومان', ',', '،', '٬', ' '], '', $raw);
    if ($raw === '' || !ctype_digit($raw)) return null;
    $amount = (int)$raw;
    return $amount >= 0 ? $amount : null;
}

// Canonical owner: includes/function_domains/events.php
require_once __DIR__ . '/function_domains/events.php';


function unique_table_access_token(PDO $pdo): string
{
    for ($attempt = 0; $attempt < 24; $attempt++) {
        $token = random_token();
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM cafe_tables WHERE access_token=? OR previous_access_token=?');
            $stmt->execute([$token, $token]);
        } catch (Throwable) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM cafe_tables WHERE access_token=?');
            $stmt->execute([$token]);
        }
        if ((int)$stmt->fetchColumn() === 0) return $token;
    }
    throw new RuntimeException('ساخت توکن امن QR انجام نشد. دوباره تلاش کن.');
}

function safe_business_error_message(Throwable $e, string $fallback): string
{
    if ($e instanceof PDOException) return $fallback;
    if ($e instanceof RuntimeException || $e instanceof InvalidArgumentException || $e instanceof DomainException) {
        $message = trim($e->getMessage());
        return $message !== '' ? text_substr($message, 0, 500) : $fallback;
    }
    return $fallback;
}

// Canonical owner: includes/function_domains/audit.php
require_once __DIR__ . '/function_domains/audit.php';


/** Best-effort audit for informational activity that must never block the primary operation. */

/** Strict audit for financial, permission and inventory-sensitive mutations. Call inside the same DB transaction. */

/** Calculate the invoice discount through one shared rule used by editing, display and checkout. */
function invoice_discount_amount(int $subtotal, ?string $type, int $value): int
{
    $subtotal = max(0, $subtotal);
    $value = max(0, $value);
    return match ($type) {
        'percent' => (int)round($subtotal * min(100, $value) / 100),
        'fixed' => min($subtotal, $value),
        default => 0,
    };
}


// Canonical owner: includes/function_domains/jalali.php
require_once __DIR__ . '/function_domains/jalali.php';

function capability_definitions(): array
{
    return [
        'orders_floor' => 'سفارش و سالن',
        'cashier_accounts' => 'صندوق و حساب',
        'preparation' => 'آماده‌سازی',
        'shift_supervision' => 'سرپرستی شیفت',
        'inventory_view' => 'مشاهده انبار',
        'inventory_cost_view' => 'هزینه و ارزش انبار',
        'inventory_operations' => 'ثبت عملیات انبار',
        'inventory_finalize' => 'نهایی‌کردن و اصلاح انبار',
        'inventory_manage' => 'مدیریت ساختار انبار',
    ];
}

function user_capabilities(?int $userId = null): array
{
    $user = current_user();
    $userId ??= (int)($user['id'] ?? 0);
    if ($userId < 1) return [];
    $role = (string)($user['role'] ?? '');
    if ((int)($user['id'] ?? 0) !== $userId || $role === '') {
        try {
            $stmt = db()->prepare('SELECT role FROM users WHERE id=? AND active=1');
            $stmt->execute([$userId]);
            $role = (string)($stmt->fetchColumn() ?: '');
        } catch (Throwable) {
            $role = '';
        }
    }
    if ($role === 'admin') return array_keys(capability_definitions());
    try {
        $stmt = db()->prepare('SELECT capability,enabled FROM user_capabilities WHERE user_id=?');
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll();
        $known = array_keys(capability_definitions());
        return array_values(array_map(
            static fn(array $row): string => (string)$row['capability'],
            array_filter(
                $rows,
                static fn(array $row): bool => (int)$row['enabled'] === 1
                    && in_array((string)$row['capability'], $known, true)
            )
        ));
    } catch (Throwable) {
        return [];
    }
}

function user_has_capability(string $capability, ?array $user = null): bool
{
    $user ??= current_user();
    if (!$user || empty($user['id'])) return false;
    if (($user['role'] ?? '') === 'admin') return true;
    return in_array($capability, user_capabilities((int)$user['id']), true);
}

/** Human-facing responsibility bundles. Backend capabilities stay granular for audit/backward compatibility. */
function team_responsibility_definitions(): array
{
    $definitions = [
        'orders_floor' => ['label'=>'سالن و سفارش','description'=>'ثبت سفارش، میزها، سفارش مهمان و کارهای روزمره سالن'],
        'preparation' => ['label'=>'آماده‌سازی','description'=>'دیدن سفارش‌های بخش و ثبت «گرفتم» برای بار یا آشپزخانه'],
        'cashier_accounts' => ['label'=>'صندوق و حساب','description'=>'حساب میز، تخفیف، تسویه و اصلاح فاکتور'],
        'shift_supervision' => ['label'=>'سرپرستی و مدیریت','description'=>'کنترل شیفت، توقف سفارش‌گیری و عملیات مدیریتی'],
    ];
    // Module visibility and team permissions are separate concerns. When Inventory is disabled,
    // hide its responsibility from the editor but preserve existing grants in storage so a later
    // re-enable restores the team's previous setup without inventing new access.
    if (!function_exists('sokna_module_configured_enabled') || sokna_module_configured_enabled('inventory')) {
        $supplyEnabled = !function_exists('sokna_module_enabled') || sokna_module_enabled('supply');
        $definitions['inventory_purchase'] = $supplyEnabled
            ? ['label'=>'انبار و خرید','description'=>'موجودی، ورود و ضایعات، کمبودها و فرآیند خرید']
            : ['label'=>'انبار','description'=>'موجودی، ورود و ضایعات و شمارش؛ خرید و تأمین در سامانه غیرفعال است'];
    }
    return $definitions;
}

function inventory_capability_keys(): array
{
    return ['inventory_view','inventory_cost_view','inventory_operations','inventory_finalize','inventory_manage'];
}

function team_responsibilities_from_capabilities(array $capabilities): array
{
    $responsibilities = [];
    foreach (['orders_floor','preparation','cashier_accounts','shift_supervision'] as $key) {
        if (in_array($key, $capabilities, true)) $responsibilities[] = $key;
    }
    foreach (['inventory_view','inventory_operations','inventory_finalize','inventory_manage'] as $key) {
        if (in_array($key, $capabilities, true)) {
            $responsibilities[] = 'inventory_purchase';
            break;
        }
    }
    return $responsibilities;
}

/** Map the compact UI bundles back to stable backend capabilities. */
function team_capabilities_from_responsibilities(array $responsibilities, bool $inventoryCost = false): array
{
    $knownResponsibilities = array_keys(team_responsibility_definitions());
    $responsibilities = array_values(array_intersect(
        $knownResponsibilities,
        array_map('strval', $responsibilities)
    ));
    $capabilities = [];
    foreach (['orders_floor','preparation','cashier_accounts','shift_supervision'] as $key) {
        if (in_array($key, $responsibilities, true)) $capabilities[] = $key;
    }
    if (in_array('inventory_purchase', $responsibilities, true)) {
        // Daily inventory/purchase work is one human-facing responsibility.
        // Sensitive finalization/structure changes are derived from shift supervision.
        $capabilities[] = 'inventory_view';
        $capabilities[] = 'inventory_operations';
        if ($inventoryCost) $capabilities[] = 'inventory_cost_view';
    }
    return array_values(array_unique($capabilities));
}

function user_has_inventory_access(?array $user = null): bool
{
    $user ??= current_user();
    if (!$user || empty($user['id'])) return false;
    if (($user['role'] ?? '') === 'admin') return true;
    foreach (inventory_capability_keys() as $capability) {
        if (user_has_capability($capability, $user)) return true;
    }
    return false;
}

/** Day-to-day inventory + purchasing responsibility. Legacy higher inventory grants remain compatible. */
function user_can_inventory_operate(?array $user = null): bool
{
    $user ??= current_user();
    if (!$user || empty($user['id'])) return false;
    if (($user['role'] ?? '') === 'admin') return true;
    return user_has_capability('inventory_operations', $user)
        || user_has_capability('inventory_finalize', $user)
        || user_has_capability('inventory_manage', $user);
}

/** Sensitive stock correction/finalization is managerial, not another everyday checkbox. */
function user_can_inventory_finalize(?array $user = null): bool
{
    $user ??= current_user();
    if (!$user || empty($user['id'])) return false;
    if (($user['role'] ?? '') === 'admin') return true;
    return user_has_capability('inventory_finalize', $user)
        || user_has_capability('inventory_manage', $user)
        || (user_has_capability('inventory_operations', $user) && user_has_capability('shift_supervision', $user));
}

function user_can_inventory_manage(?array $user = null): bool
{
    $user ??= current_user();
    if (!$user || empty($user['id'])) return false;
    if (($user['role'] ?? '') === 'admin') return true;
    return user_has_capability('inventory_manage', $user)
        || (user_has_capability('inventory_operations', $user) && user_has_capability('shift_supervision', $user));
}

function user_can_inventory_cost(?array $user = null): bool
{
    $user ??= current_user();
    if (!$user || empty($user['id'])) return false;
    return ($user['role'] ?? '') === 'admin' || user_has_capability('inventory_cost_view', $user);
}

function user_can_manage_purchases(?array $user = null): bool
{
    if (function_exists('sokna_module_enabled') && !sokna_module_enabled('supply')) return false;
    return user_can_inventory_operate($user);
}

/** Kitchen/bar staff may report supply needs without receiving purchase or stock permissions. */
function user_can_report_supply_needs(?array $user = null): bool
{
    if (function_exists('sokna_module_enabled') && !sokna_module_enabled('supply')) return false;
    $user ??= current_user();
    if (!$user || empty($user['id'])) return false;
    if (($user['role'] ?? '') === 'admin') return true;
    return user_has_capability('preparation', $user)
        || user_has_capability('inventory_operations', $user)
        || user_has_capability('shift_supervision', $user);
}

function supply_need_allowed_departments(?array $user = null): array
{
    $user ??= current_user();
    if (!$user || empty($user['id'])) return [];
    if (($user['role'] ?? '') === 'admin' || user_can_inventory_operate($user) || user_has_capability('shift_supervision', $user)) {
        return ['kitchen','bar','shared'];
    }
    $areas = user_preparation_areas((int)$user['id']);
    return array_values(array_intersect(['kitchen','bar'], $areas));
}

function role_label(string $role): string
{
    return $role === 'admin' ? 'مدیر سامانه' : 'عضو تیم';
}

/** Whether the current account may create a staff order. */
function staff_quick_order_allowed(?array $user = null): bool
{
    $user ??= current_user();
    if (!$user || empty($user['id'])) return false;
    return ($user['role'] ?? '') === 'admin'
        || user_has_capability('orders_floor', $user);
}

function preparation_stations(): array
{
    return [
        'kitchen' => 'آشپزخانه',
        'hot_bar' => 'بار گرم',
        'cold_bar' => 'بار سرد',
        'none' => 'بدون آماده‌سازی',
    ];
}

function normalize_preparation_station(string $station): string
{
    if ($station === 'other') return 'cold_bar';
    return array_key_exists($station, preparation_stations()) ? $station : 'cold_bar';
}

function preparation_station_requires_work(string $station): bool
{
    return normalize_preparation_station($station) !== 'none';
}

function normalize_fulfillment_mode(string $mode): string
{
    return $mode === 'takeaway' ? 'takeaway' : 'dine_in';
}

function fulfillment_mode_label(string $mode): string
{
    return normalize_fulfillment_mode($mode) === 'takeaway' ? 'بیرون‌بر' : 'داخل کافه';
}

function station_label(string $station): string
{
    $station = normalize_preparation_station($station);
    return preparation_stations()[$station];
}

/** Operational areas are deliberately fewer than menu stations. */
function preparation_operational_areas(): array
{
    return [
        'kitchen' => 'آشپزخانه',
        'bar' => 'بار',
    ];
}

function preparation_area_for_station(string $station): string
{
    return normalize_preparation_station($station) === 'kitchen' ? 'kitchen' : 'bar';
}

function normalize_preparation_area(string $area): string
{
    return array_key_exists($area, preparation_operational_areas()) ? $area : 'bar';
}

function user_preparation_areas(?int $userId = null): array
{
    $user = current_user();
    $userId ??= (int)($user['id'] ?? 0);
    if ($userId < 1) return [];
    $role = (string)($user['role'] ?? '');
    if ((int)($user['id'] ?? 0) !== $userId || $role === '') {
        try {
            $stmt = db()->prepare('SELECT role FROM users WHERE id=? AND active=1');
            $stmt->execute([$userId]);
            $role = (string)($stmt->fetchColumn() ?: '');
        } catch (Throwable) {
            $role = '';
        }
    }
    try {
        $stmt = db()->prepare('SELECT area_key FROM user_preparation_areas WHERE user_id=? ORDER BY FIELD(area_key,\'kitchen\',\'bar\')');
        $stmt->execute([$userId]);
        return array_values(array_filter(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)), static fn(string $area): bool => isset(preparation_operational_areas()[$area])));
    } catch (Throwable) {
        return [];
    }
}

function station_busy_states(): array
{
    $states = [];
    foreach (preparation_operational_areas() as $key => $_label) {
        $states[$key] = setting_bool('station_busy.' . $key, false);
    }
    return $states;
}

function station_is_busy(string $station): bool
{
    if (!preparation_station_requires_work($station)) return false;
    return setting_bool('station_busy.' . preparation_area_for_station($station), false);
}

function station_state_hash(): string
{
    return substr(hash('sha256', json_encode(station_busy_states(), JSON_UNESCAPED_UNICODE)), 0, 16);
}

function order_acceptance_states(): array
{
    // These switches apply only to guest online ordering. Staff quick order and
    // invoice adjustments deliberately remain available during an online pause.
    return [
        'cafe' => setting_bool('orders_accepting.cafe', true),
        'kitchen' => setting_bool('orders_accepting.kitchen', true),
        'bar' => setting_bool('orders_accepting.bar', true),
    ];
}

function order_acceptance_revision(): int
{
    return max(0, (int)setting('orders_accepting.revision', '0'));
}

function order_acceptance_enabled_for_station(string $station): bool
{
    $states = order_acceptance_states();
    if (!$states['cafe']) return false;
    if (!preparation_station_requires_work($station)) return true;
    return $states[preparation_area_for_station($station)] ?? false;
}

function order_acceptance_message(string $scope): string
{
    $scope = in_array($scope, ['cafe','kitchen','bar'], true) ? $scope : 'cafe';
    return customer_message('ordering_pause_' . $scope);
}

function order_acceptance_blocked_scope_for_station(string $station): ?string
{
    $states = order_acceptance_states();
    if (!$states['cafe']) return 'cafe';
    if (!preparation_station_requires_work($station)) return null;
    $area = preparation_area_for_station($station);
    return !($states[$area] ?? false) ? $area : null;
}

function order_preparation_groups(array $items): array
{
    $groups = [
        'kitchen' => ['key'=>'kitchen','label'=>'آشپزخانه','items'=>[]],
        'bar' => ['key'=>'bar','label'=>'بار','items'=>[]],
    ];
    foreach ($items as $item) {
        $station = normalize_preparation_station((string)($item['preparation_station'] ?? 'cold_bar'));
        if (!preparation_station_requires_work($station)) continue;
        $area = preparation_area_for_station($station);
        $item['preparation_station'] = $station;
        $item['preparation_area'] = $area;
        $groups[$area]['items'][] = $item;
    }
    return array_values(array_filter($groups, static fn(array $group): bool => $group['items'] !== []));
}

function order_preparation_signature(array $order, array $items): string
{
    $normalizedItems = array_map(static function (array $item): array {
        return [
            'id' => (int)($item['id'] ?? 0),
            'name' => (string)($item['item_name'] ?? ''),
            'quantity' => (int)($item['quantity'] ?? 0),
            'note' => trim((string)($item['item_note'] ?? '')),
            'fulfillment_mode' => normalize_fulfillment_mode((string)($item['fulfillment_mode'] ?? 'dine_in')),
            'station' => normalize_preparation_station((string)($item['preparation_station'] ?? 'other')),
        ];
    }, $items);
    usort($normalizedItems, static fn(array $a, array $b): int => [$a['id'], $a['name'], $a['fulfillment_mode']] <=> [$b['id'], $b['name'], $b['fulfillment_mode']]);
    $payload = [
        'id' => (int)($order['id'] ?? 0),
        'note' => trim((string)($order['customer_note'] ?? '')),
        'items' => $normalizedItems,
    ];
    return substr(hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), 0, 32);
}

function order_statuses(): array
{
    return [
        'pending_approval' => 'منتظر تأیید حضور',
        'new' => 'منتظر تأیید سفارش',
        'accounted' => 'تأییدشده',
        'completed' => 'تسویه‌شده',
        'cancelled' => 'لغوشده',
    ];
}

function order_status_label(string $status): string
{
    return order_statuses()[$status] ?? $status;
}

/** Guest changes are allowed only while this specific order is still unconfirmed. */
function guest_order_status_is_mutable(string $status): bool
{
    return in_array($status, ['pending_approval', 'new'], true);
}

/** Human-facing order number. Resets per operational business day; the DB id remains globally stable. */
function order_display_number(array|int $order): int
{
    $id=is_array($order)?(int)($order['id']??0):(int)$order;
    $number=is_array($order)?(int)($order['business_order_number']??0):0;
    if($number>0)return $number;
    if($id<1)return 0;
    static $cache=[];
    if(array_key_exists($id,$cache))return $cache[$id];
    try{
        $stmt=db()->prepare('SELECT business_order_number FROM orders WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $number=(int)($stmt->fetchColumn()?:0);
        if($number>0)return $cache[$id]=$number;
    }catch(Throwable $e){/* schema may be pre-migration in maintenance/tests; preserve legacy fallback */}
    return $cache[$id]=$id;
}

/** Allocate a collision-free number inside the caller's existing order transaction. */
function order_allocate_business_number(PDO $pdo,string $businessDate): int
{
    if(!$pdo->inTransaction())throw new RuntimeException('تخصیص شماره سفارش باید داخل تراکنش ثبت سفارش انجام شود.');
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$businessDate))throw new RuntimeException('تاریخ روز عملیاتی برای شماره سفارش معتبر نیست.');
    $seed=$pdo->prepare('INSERT INTO order_business_sequences(business_date,last_number) VALUES(?,0) ON DUPLICATE KEY UPDATE business_date=VALUES(business_date)');
    $seed->execute([$businessDate]);
    $lock=$pdo->prepare('SELECT last_number FROM order_business_sequences WHERE business_date=? FOR UPDATE');
    $lock->execute([$businessDate]);
    $current=$lock->fetchColumn();
    if($current===false)throw new RuntimeException('شمارنده روزانه سفارش در دسترس نیست.');
    $next=(int)$current+1;
    if($next<1||$next>4294967295)throw new RuntimeException('ظرفیت شماره سفارش روزانه تکمیل شده است.');
    $update=$pdo->prepare('UPDATE order_business_sequences SET last_number=? WHERE business_date=?');
    $update->execute([$next,$businessDate]);
    return $next;
}

function order_display_code(array|int $order): string
{
    $id = is_array($order) ? (int)($order['id'] ?? 0) : (int)$order;
    $sourceDate = is_array($order) && !empty($order['created_at']) ? (string)$order['created_at'] : date('Y-m-d H:i:s');
    [$year] = jalali_date_parts(new DateTimeImmutable($sourceDate, new DateTimeZone(app_timezone())));
    return sprintf('O-%d-%06d', $year, $id);
}

function order_display_label(array|int $order): string
{
    return 'سفارش ' . fa_digits((string)order_display_number($order));
}

function normalize_order_integer(mixed $value): ?int
{
    if (is_int($value)) return $value;
    if (is_float($value) && floor($value) === $value) return (int)$value;
    if (is_string($value) && preg_match('/^\d+$/', trim($value))) return (int)trim($value);
    return null;
}

/** Normalize the guest-order contract without touching the database. */
function normalize_order_request_payload(array $data): array
{
    $tableToken = trim((string)($data['table_token'] ?? ''));
    $sessionToken = trim((string)($data['session_token'] ?? ''));
    $deviceToken = trim((string)($data['device_token'] ?? ''));
    $clientToken = trim((string)($data['client_token'] ?? ''));
    $customerNote = text_substr(trim((string)($data['customer_note'] ?? '')), 0, 1000);
    $items = $data['items'] ?? null;

    if ($tableToken === '' || strlen($tableToken) > 80) {
        throw new InvalidArgumentException('QR میز معتبر نیست.');
    }
    if (strlen($sessionToken) > 80 || strlen($deviceToken) > 80) {
        throw new InvalidArgumentException('اطلاعات نشست معتبر نیست.');
    }
    if (strlen($clientToken) < 16 || strlen($clientToken) > 80) {
        throw new InvalidArgumentException('شناسه ارسال سفارش معتبر نیست.');
    }
    if (!is_array($items) || $items === [] || count($items) > 100) {
        throw new InvalidArgumentException('حداقل یک آیتم معتبر انتخاب کن.');
    }

    $lines = [];
    $seen = [];
    foreach ($items as $row) {
        if (!is_array($row)) throw new InvalidArgumentException('ساختار یکی از آیتم‌ها معتبر نیست.');
        $itemId = normalize_order_integer($row['id'] ?? null);
        $quantity = normalize_order_integer($row['quantity'] ?? null);
        $expectedPrice = normalize_order_integer($row['unit_price'] ?? null);
        if ($itemId === null || $itemId < 1 || $quantity === null || $quantity < 1 || $quantity > 20) {
            throw new InvalidArgumentException('تعداد یا شناسه یکی از آیتم‌ها معتبر نیست.');
        }
        if ($expectedPrice !== null && $expectedPrice < 0) {
            throw new InvalidArgumentException('قیمت ثبت‌شده در سبد معتبر نیست.');
        }
        $fulfillmentMode = normalize_fulfillment_mode((string)($row['fulfillment_mode'] ?? 'dine_in'));
        $lineKey = $itemId . '|' . $fulfillmentMode;
        if (isset($seen[$lineKey])) {
            throw new InvalidArgumentException('یک آیتم با روش سرو یکسان چند بار در سفارش تکرار شده است.');
        }
        $seen[$lineKey] = true;
        $lines[] = [
            'id' => $itemId,
            'quantity' => $quantity,
            'note' => text_substr(trim((string)($row['note'] ?? '')), 0, 500),
            'expected_price' => $expectedPrice,
            'fulfillment_mode' => $fulfillmentMode,
        ];
    }

    return [
        'table_token' => $tableToken,
        'session_token' => $sessionToken,
        'device_token' => $deviceToken,
        'client_token' => $clientToken,
        'customer_note' => $customerNote,
        'items' => $lines,
    ];
}

/** Mode-aware staff order rows. A catalog item may appear once per fulfillment mode. */
function normalize_staff_quick_order_rows(mixed $items): array
{
    if (!is_array($items) || $items === [] || count($items) > 30) {
        throw new InvalidArgumentException('حداقل یک آیتم و حداکثر ۳۰ ردیف انتخاب کنید.');
    }
    $rows = [];
    $seen = [];
    $totalQuantity = 0;
    foreach ($items as $row) {
        if (!is_array($row)) throw new InvalidArgumentException('ساختار یکی از آیتم‌ها معتبر نیست.');
        $itemId = normalize_order_integer($row['id'] ?? null);
        $quantity = normalize_order_integer($row['quantity'] ?? null);
        $mode = normalize_fulfillment_mode((string)($row['fulfillment_mode'] ?? 'dine_in'));
        if ($itemId === null || $itemId < 1 || $quantity === null || $quantity < 1 || $quantity > 50) {
            throw new InvalidArgumentException('تعداد یکی از آیتم‌ها معتبر نیست.');
        }
        $key=$itemId.'|'.$mode;
        if(isset($seen[$key])) throw new InvalidArgumentException('یک آیتم با روش سرو یکسان چند بار ثبت شده است.');
        $seen[$key]=true;
        $expectedPrice = normalize_order_integer($row['expected_price'] ?? ($row['unit_price'] ?? null));
        if ($expectedPrice !== null && $expectedPrice < 0) throw new InvalidArgumentException('قیمت ثبت‌شده در سبد معتبر نیست.');
        $rows[]=[
            'id'=>$itemId,'quantity'=>$quantity,
            'note'=>text_substr(trim((string)($row['note'] ?? '')),0,500),
            'expected_price'=>$expectedPrice,'fulfillment_mode'=>$mode,
        ];
        $totalQuantity += $quantity;
        if($totalQuantity>100) throw new InvalidArgumentException('تعداد کل سفارش نمی‌تواند بیشتر از ۱۰۰ باشد.');
    }
    return $rows;
}

/** Shared transactional catalog owner for every path that creates/increases an order line. */
function order_catalog_items_locked(PDO $pdo, array $itemIds): array
{
    $itemIds = array_values(array_unique(array_filter(array_map('intval', $itemIds), static fn(int $id): bool => $id > 0)));
    if (!$itemIds) return [];
    $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
    $scheduleSql = item_schedule_sql('i');
    $menuMembershipSql = menu_catalog_orderable_membership_sql('i');
    $stmt = $pdo->prepare("SELECT i.id,i.name,i.price,i.preparation_station,i.available,i.active,i.staff_only,i.takeaway_allowed,
        COALESCE(c.active,0) category_active,COALESCE(c.audience,'guest_staff') category_audience,
        ($scheduleSql) schedule_active,($menuMembershipSql) menu_active
        FROM items i LEFT JOIN categories c ON c.id=i.category_id
        WHERE i.id IN($placeholders) FOR UPDATE");
    $stmt->execute($itemIds);
    $items = [];
    foreach ($stmt->fetchAll() as $row) $items[(int)$row['id']] = $row;
    return $items;
}

function order_catalog_item_is_orderable(?array $item, string $context = 'staff'): bool
{
    if ($item === null
        || (int)($item['active'] ?? 0) !== 1
        || (int)($item['available'] ?? 0) !== 1
        || (int)($item['category_active'] ?? 0) !== 1
        || (int)($item['schedule_active'] ?? 0) !== 1
        || (int)($item['menu_active'] ?? 0) !== 1) return false;
    if ($context === 'guest') {
        return (int)($item['staff_only'] ?? 0) !== 1 && (string)($item['category_audience'] ?? 'guest_staff') === 'guest_staff';
    }
    return true;
}

function order_catalog_item_allows_fulfillment(?array $item, string $mode): bool
{
    $mode=normalize_fulfillment_mode($mode);
    return $mode!=='takeaway' || ($item !== null && (int)($item['takeaway_allowed'] ?? 1) === 1);
}

/** Optimistic signature for a mutable guest order; prevents stale tabs from overwriting newer edits. */
function guest_order_edit_signature(array $order, array $items): string
{
    $rows = [];
    foreach ($items as $line) {
        $rows[] = [
            'item_id'=>(int)($line['item_id'] ?? $line['id'] ?? 0),
            'quantity'=>(int)($line['quantity'] ?? 0),
            'unit_price'=>(int)($line['unit_price'] ?? 0),
            'note'=>trim((string)($line['item_note'] ?? $line['note'] ?? '')),
            'fulfillment_mode'=>normalize_fulfillment_mode((string)($line['fulfillment_mode'] ?? 'dine_in')),
        ];
    }
    usort($rows, static fn(array $a,array $b): int => ($a['item_id'] <=> $b['item_id']) ?: strcmp($a['fulfillment_mode'],$b['fulfillment_mode']) ?: strcmp($a['note'],$b['note']));
    return substr(hash('sha256', json_encode([
        'id'=>(int)($order['id'] ?? 0),
        'status'=>(string)($order['status'] ?? ''),
        'customer_note'=>trim((string)($order['customer_note'] ?? '')),
        'updated_at'=>(string)($order['updated_at'] ?? ''),
        'items'=>$rows,
    ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)), 0, 32);
}

function normalize_staff_order_request_token(mixed $token): string
{
    $token = trim((string)$token);
    if (!preg_match('/^[A-Za-z0-9-]{16,80}$/', $token)) {
        throw new InvalidArgumentException('شناسه امن ثبت سفارش معتبر نیست؛ صفحه را تازه کنید.');
    }
    return 'staff-' . $token;
}

/** Compact, readable token used inside table medallions. */
function table_display_token(string $name, ?int $tableNumber = null, bool $allowLegacyFallback = true): string
{
    if ($tableNumber !== null && $tableNumber > 0) return fa_digits((string)$tableNumber);
    // Management and QR identity surfaces can disable the legacy name fallback so a
    // number embedded in the human label is never mistaken for the canonical table_number.
    if (!$allowLegacyFallback) return '؟';
    $normalized = en_digits(trim($name));
    if (preg_match('/\d+/u', $normalized, $match)) return fa_digits($match[0]);
    $clean = trim((string)preg_replace('/^(?:میز|table)\s*/iu', '', $name));
    $label = $clean !== '' ? $clean : $name;
    if (function_exists('mb_substr')) return text_substr($label, 0, 2);
    return preg_match('/^.{1,2}/us', $label, $chars) ? $chars[0] : $label;
}

function table_medallion_html(string $name, string $state = 'state-free', string $size = 'sm', ?string $status = null, ?int $tableNumber = null, bool $allowLegacyFallback = true): string
{
    $states = ['state-free','state-active','state-order','state-call','state-attention','state-disabled'];
    $sizes = ['sm','md','lg','xl'];
    if (!in_array($state, $states, true)) $state = 'state-free';
    if (!in_array($size, $sizes, true)) $size = 'sm';
    $statusHtml = $status !== null && trim($status) !== '' ? '<span>' . e($status) . '</span>' : '';
    return '<span class="table-medallion table-medallion-' . e($size) . ' ' . e($state) . '" aria-label="' . e(fa_digits($name . ($status ? '، ' . $status : ''))) . '"><small>میز</small><strong>' . e(table_display_token($name, $tableNumber, $allowLegacyFallback)) . '</strong>' . $statusHtml . '</span>';
}

function random_token(int $bytes = 18): string
{
    return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
}


// Canonical owner: includes/function_domains/media.php
require_once __DIR__ . '/function_domains/media.php';

function csv_safe_cell(mixed $value): string
{
    $text = (string)$value;
    if ($text !== '' && preg_match('/^[=+\-@]/u', ltrim($text))) {
        return "'" . $text;
    }
    return $text;
}

function order_status_transitions(): array
{
    return [
        // One confirmation is enough. Confirmed-order corrections use the audited bill flow,
        // never a generic status switch.
        'pending_approval' => ['accounted', 'cancelled'],
        'new' => ['accounted', 'cancelled'],
        'accounted' => ['completed'],
        'completed' => [],
        'cancelled' => [],
    ];
}

function allowed_order_statuses_from(string $current): array
{
    return order_status_transitions()[$current] ?? [];
}

/** Run a non-core enqueue without allowing a secondary module to reject a valid order. */
function order_side_effect_best_effort_tx(PDO $pdo, string $label, callable $operation): mixed
{
    try {
        return $operation();
    } catch (Throwable $e) {
        error_log('Order side effect [' . $label . ']: ' . $e->getMessage());
        // Deadlocks/connection failures may invalidate the whole transaction. In that
        // case do not pretend the core order is safe; let the caller roll it back/retry.
        if (!$pdo->inTransaction()) throw $e;
        return null;
    }
}

/** Canonical lock order for every order mutation: table -> session -> order. */
function lock_order_context(PDO $pdo, int $orderId): array
{
    if ($orderId < 1) throw new RuntimeException('سفارش پیدا نشد.');
    $probeStmt = $pdo->prepare('SELECT table_id,session_id FROM orders WHERE id=? LIMIT 1');
    $probeStmt->execute([$orderId]);
    $probe = $probeStmt->fetch();
    if (!$probe) throw new RuntimeException('سفارش پیدا نشد.');
    $tableId = (int)$probe['table_id'];
    $sessionId = (int)($probe['session_id'] ?? 0);

    $tableLock = $pdo->prepare('SELECT id,name FROM cafe_tables WHERE id=? FOR UPDATE');
    $tableLock->execute([$tableId]);
    $table = $tableLock->fetch();
    if (!$table) throw new RuntimeException('میز سفارش پیدا نشد.');

    $session = null;
    if ($sessionId > 0) {
        $sessionLock = $pdo->prepare('SELECT id,status FROM table_sessions WHERE id=? FOR UPDATE');
        $sessionLock->execute([$sessionId]);
        $session = $sessionLock->fetch() ?: null;
        if (!$session) throw new RuntimeException('نشست این سفارش پیدا نشد.');
    }

    $orderStmt = $pdo->prepare('SELECT id,status,accepted_by_user_id,session_id,table_id FROM orders WHERE id=? FOR UPDATE');
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch();
    if (!$order) throw new RuntimeException('سفارش پیدا نشد.');
    if ((int)$order['table_id'] !== $tableId || (int)($order['session_id'] ?? 0) !== $sessionId) {
        throw new RuntimeException('میز سفارش هم‌زمان تغییر کرده؛ دوباره بررسی کن.');
    }
    return ['table'=>$table,'table_id'=>$tableId,'session'=>$session,'session_id'=>$sessionId,'order'=>$order];
}

/**
 * The single confirmation operation used by every staff workflow.
 * Callers must already hold the table, session and order locks in that order.
 */
function confirm_order_locked(PDO $pdo, array $order, int $actorUserId): bool
{
    $orderId = (int)($order['id'] ?? 0);
    $sessionId = (int)($order['session_id'] ?? 0);
    $oldStatus = (string)($order['status'] ?? '');
    if ($orderId < 1 || !in_array($oldStatus, ['pending_approval','new'], true)) {
        throw new RuntimeException('این سفارش دیگر منتظر تأیید نیست.');
    }

    if ($sessionId > 0) {
        $pdo->prepare("UPDATE table_sessions SET status='active',live_table_guard=table_id,opened_by_user_id=COALESCE(opened_by_user_id,?) WHERE id=? AND status='pending'")
            ->execute([$actorUserId,$sessionId]);
    }
    $update = $pdo->prepare("UPDATE orders SET status='accounted',accepted_at=COALESCE(accepted_at,NOW()),accepted_by_user_id=COALESCE(accepted_by_user_id,?) WHERE id=? AND status=?");
    $update->execute([$actorUserId,$orderId,$oldStatus]);
    if ($update->rowCount() !== 1) throw new RuntimeException('این سفارش هم‌زمان توسط همکار دیگری بررسی شد.');

    $pdo->prepare("INSERT INTO order_status_history(order_id,from_status,to_status,actor_user_id) VALUES(?,?,'accounted',?)")
        ->execute([$orderId,$oldStatus,$actorUserId]);
    $prepStmt=$pdo->prepare("SELECT COUNT(*) FROM order_items WHERE order_id=? AND quantity>0 AND preparation_station<>'none'");
    $prepStmt->execute([$orderId]);
    $hasPreparation=(int)$prepStmt->fetchColumn()>0;
    if($hasPreparation) print_enqueue_prep_order($pdo,$orderId,$actorUserId);
    inventory_enqueue_order_event_tx($pdo,'accounted',$orderId,$actorUserId,[],'inventory:order-accounted:'.$orderId);
    return $hasPreparation;
}

function form_state_store(string $key, array $values, array $errors = []): void
{
    unset($values['csrf_token']);
    foreach ($values as $name => $value) {
        if (str_contains(text_lower((string)$name), 'password') || str_contains(text_lower((string)$name), 'token')) unset($values[$name]);
    }
    $_SESSION['form_state'][$key] = ['values'=>$values,'errors'=>$errors,'stored_at'=>time()];
}

function form_state_pull(string $key): array
{
    $state = $_SESSION['form_state'][$key] ?? ['values'=>[],'errors'=>[]];
    unset($_SESSION['form_state'][$key]);
    return is_array($state) ? $state : ['values'=>[],'errors'=>[]];
}

function form_old(array $state, string $key, mixed $default = ''): mixed
{
    return array_key_exists($key, $state['values'] ?? []) ? $state['values'][$key] : $default;
}

function form_field_error(array $state, string $key): string
{
    return (string)($state['errors'][$key] ?? '');
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function render_flashes(): string
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    $html = '';
    foreach ($items as $item) {
        $html .= '<div class="alert alert-' . e($item['type']) . '">' . e($item['message']) . '</div>';
    }
    return $html;
}

function fa_digits(string|int $value): string
{
    return strtr((string)$value, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']);
}

function time_ago(string $datetime): string
{
    $seconds = max(0, time() - strtotime($datetime));
    if ($seconds < 60) return 'لحظاتی پیش';
    if ($seconds < 3600) return fa_digits((string)floor($seconds / 60)) . ' دقیقه پیش';
    if ($seconds < 86400) return fa_digits((string)floor($seconds / 3600)) . ' ساعت پیش';
    return fa_digits((string)floor($seconds / 86400)) . ' روز پیش';
}

function en_digits(string|int $value): string
{
    return strtr((string)$value, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
}

/** Canonical normalization for Persian user-entered search terms. */
function normalize_persian_search(string|int $value): string
{
    $text = text_lower(en_digits((string)$value), 'UTF-8');
    $text = strtr($text, ['ي'=>'ی','ى'=>'ی','ك'=>'ک','ة'=>'ه','ۀ'=>'ه','-'=>' ','‐'=>' ','‑'=>' ','–'=>' ','—'=>' ','_'=>' ']);
    $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text) ?? $text;
    $text = preg_replace('/[\x{200C}\x{200D}\x{200E}\x{200F}\x{202A}-\x{202E}]+/u', ' ', $text) ?? $text;
    return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
}

function persian_search_tokens(string|int $value): array
{
    $normalized = normalize_persian_search($value);
    if ($normalized === '') return [];
    return array_values(array_filter(explode(' ', $normalized), static fn(string $token): bool => $token !== ''));
}

/** Technical/canonical identifiers keep their stored Latin representation and explicit LTR direction. */
function canonical_identifier_html(string|int $value, string $class = 'canonical-id'): string
{
    $safeClass = preg_replace('/[^a-zA-Z0-9_-]/', '', $class) ?: 'canonical-id';
    return '<bdi dir="ltr" class="' . e($safeClass) . '">' . e(en_digits((string)$value)) . '</bdi>';
}


/** Human-facing reference for financial documents while preserving the canonical id for audit/search. */
function financial_document_reference_parts(string|int $value): array
{
    $canonical = en_digits(trim((string)$value));
    if (preg_match('/^([A-Z]+)-(\d{4})-(\d+)$/', $canonical, $match)) {
        return [
            'canonical' => $canonical,
            'prefix' => (string)$match[1],
            'year' => (int)$match[2],
            'sequence' => (int)$match[3],
        ];
    }
    return ['canonical'=>$canonical,'prefix'=>'','year'=>null,'sequence'=>null];
}

function financial_document_human_label(string|int $value): string
{
    $parts = financial_document_reference_parts($value);
    if ($parts['sequence'] === null) return (string)$parts['canonical'];
    $prefix = (string)$parts['prefix'];
    $label = $prefix === 'R' ? 'سند برگشت' : ($prefix === 'I' ? 'فاکتور' : 'سند');
    return $label . ' ' . fa_digits((int)$parts['sequence']);
}

/** Normalize a human document query such as «فاکتور ۴۹» to the searchable numeric/canonical token. */
function financial_document_search_token(string $value): string
{
    $value = trim(normalize_persian_search($value));
    $value = preg_replace('/^(?:فاکتور|سند\s+برگشت|سند)\s+/u', '', $value) ?? $value;
    return trim(en_digits($value));
}

/** Shared receipt-item presenter used by invoice detail and linked financial histories. */
function financial_receipt_presented_items(array $items): array
{
    $grouped = [];
    $order = [];
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $name = trim((string)($item['name'] ?? ''));
        $note = trim((string)($item['note'] ?? ''));
        $unit = (int)($item['unit_price'] ?? 0);
        $qty = max(0, (int)($item['quantity'] ?? 0));
        if ($name === '' || $qty < 1) continue;
        $key = $name . '|' . $unit . '|' . $note;
        if (!isset($grouped[$key])) {
            $grouped[$key] = ['name'=>$name,'note'=>$note,'unit_price'=>$unit,'quantity'=>0,'line_total'=>0];
            $order[] = $key;
        }
        $grouped[$key]['quantity'] += $qty;
        $grouped[$key]['line_total'] += (int)($item['line_total'] ?? ($qty * $unit));
    }
    return array_map(static fn(string $key): array => $grouped[$key], $order);
}

/** Server-side owner for the initial visual value of user-facing numeric controls. */
function numeric_input_display_value(string|int|float|null $value): string
{
    $raw = (string)($value ?? '');
    return fa_digits(str_replace(['.', ','], ['٫', '٬'], $raw));
}

function money_input_display_value(string|int|null $value): string
{
    $raw = trim((string)($value ?? ''));
    if ($raw === '') return '';
    $parsed = parse_toman_amount_text($raw);
    return $parsed === null ? numeric_input_display_value($raw) : toman_number($parsed);
}

/** Validate an optional Jalali date range without silently changing user input. */
function resolve_optional_jalali_range(string $fromInput, string $toInput): array
{
    $fromInput = trim($fromInput);
    $toInput = trim($toInput);
    $from = $fromInput === '' ? null : parse_jalali_date($fromInput);
    $to = $toInput === '' ? null : parse_jalali_date($toInput);
    $error = '';
    if ($fromInput !== '' && !$from) $error = 'تاریخ شروع معتبر نیست.';
    elseif ($toInput !== '' && !$to) $error = 'تاریخ پایان معتبر نیست.';
    elseif ($from && $to && $to < $from) $error = 'پایان بازه نمی‌تواند قبل از شروع باشد.';
    return ['from'=>$from,'to'=>$to,'error'=>$error];
}

function bool_from_mixed(mixed $value, bool $default = false): bool
{
    if ($value === null || $value === '') return $default;
    if (is_bool($value)) return $value;
    return in_array(text_lower(trim((string)$value), 'UTF-8'), ['1','true','yes','on','بله','فعال'], true);
}

function friendly_duration(?string $startedAt): string
{
    if (!$startedAt) return '—';
    $seconds = max(0, time() - strtotime($startedAt));
    $minutes = (int)floor($seconds / 60);
    if ($minutes < 1) return 'تازه شروع شده';
    if ($minutes < 60) return fa_digits($minutes) . ' دقیقه';
    $hours = (int)floor($minutes / 60);
    $remain = $minutes % 60;
    return fa_digits($hours) . ' ساعت' . ($remain ? ' و ' . fa_digits($remain) . ' دقیقه' : '');
}

/** Human presentation for measured operational durations. */
function human_duration_seconds(int|float|null $seconds): string
{
    if ($seconds === null || (float)$seconds <= 0) return '—';
    $seconds = (int)round((float)$seconds);
    if ($seconds < 120) return fa_digits($seconds) . ' ثانیه';
    $minutes = (int)round($seconds / 60);
    if ($minutes < 60) return fa_digits($minutes) . ' دقیقه';
    $hours = intdiv($minutes, 60);
    $remain = $minutes % 60;
    return fa_digits($hours) . ' ساعت' . ($remain > 0 ? ' و ' . fa_digits($remain) . ' دقیقه' : '');
}

function active_table_session(int $tableId): ?array
{
    $stmt = db()->prepare("SELECT * FROM table_sessions WHERE table_id=? AND status='active' ORDER BY started_at DESC,id DESC LIMIT 1");
    $stmt->execute([$tableId]);
    return $stmt->fetch() ?: null;
}

function pending_table_session(int $tableId): ?array
{
    $stmt = db()->prepare("SELECT * FROM table_sessions WHERE table_id=? AND status='pending' ORDER BY started_at DESC,id DESC LIMIT 1");
    $stmt->execute([$tableId]);
    return $stmt->fetch() ?: null;
}

function live_table_session(int $tableId, bool $forUpdate = false): ?array
{
    $sql = "SELECT * FROM table_sessions WHERE table_id=? AND status IN('active','pending')
        ORDER BY FIELD(status,'active','pending'),started_at DESC,id DESC LIMIT 1";
    if ($forUpdate) $sql .= ' FOR UPDATE';
    $stmt = db()->prepare($sql);
    $stmt->execute([$tableId]);
    return $stmt->fetch() ?: null;
}

function create_table_session(int $tableId, ?int $userId = null, ?int $continuedFrom = null, ?string $startedAt = null, string $status = 'active', ?array $businessSnapshot = null): array
{
    $status = $status === 'pending' ? 'pending' : 'active';
    $pdo = db();
    $token = random_token(24);
    $effectiveStartedAt = $startedAt ?: date('Y-m-d H:i:s');
    $business = null;
    if (is_array($businessSnapshot)) {
        $snapshotDate = trim((string)($businessSnapshot['business_date'] ?? ''));
        $snapshotKey = preg_replace('/[^a-z0-9_\-]/i', '', trim((string)($businessSnapshot['business_shift_key'] ?? '')));
        $snapshotLabel = text_substr(trim((string)($businessSnapshot['business_shift_label'] ?? '')), 0, 60);
        try { $snapshotCutoff = business_clock_normalize((string)($businessSnapshot['business_cutoff_snapshot'] ?? '')); }
        catch (Throwable) { $snapshotCutoff = ''; }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $snapshotDate) && $snapshotKey !== '' && $snapshotLabel !== '' && $snapshotCutoff !== '') {
            $business = ['business_date'=>$snapshotDate,'shift_key'=>$snapshotKey,'shift_label'=>$snapshotLabel,'cutoff'=>$snapshotCutoff];
        }
    }
    $business ??= business_assignment($effectiveStartedAt);
    $stmt = $pdo->prepare("INSERT INTO table_sessions(public_token,table_id,status,live_table_guard,continued_from_session_id,opened_by_user_id,started_at,business_date,business_shift_key,business_shift_label,business_cutoff_snapshot) VALUES(?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$token, $tableId, $status, $tableId, $continuedFrom, $userId, $effectiveStartedAt, (string)$business['business_date'], (string)$business['shift_key'], (string)$business['shift_label'], (string)$business['cutoff']]);
    $id = (int)$pdo->lastInsertId();
    if ($status === 'active') {
        $pdo->prepare("UPDATE waiter_calls SET session_id=? WHERE table_id=? AND session_id IS NULL AND status IN('new','accepted') AND created_at>=DATE_SUB(NOW(),INTERVAL 30 MINUTE)")->execute([$id,$tableId]);
    }
    $fetch = $pdo->prepare('SELECT * FROM table_sessions WHERE id=?');
    $fetch->execute([$id]);
    return $fetch->fetch() ?: ['id'=>$id,'public_token'=>$token,'table_id'=>$tableId,'status'=>$status];
}

function close_table_session(int $sessionId, ?int $userId, string $reason = 'freed'): void
{
    $pdo = db();
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        $tableLookup = $pdo->prepare('SELECT table_id FROM table_sessions WHERE id=? LIMIT 1');
        $tableLookup->execute([$sessionId]);
        $tableId = (int)($tableLookup->fetchColumn() ?: 0);
        if ($tableId < 1) {
            if ($ownsTransaction) $pdo->commit();
            return;
        }

        // Keep the same table-then-session lock order used by order creation.
        $tableLock = $pdo->prepare('SELECT id FROM cafe_tables WHERE id=? FOR UPDATE');
        $tableLock->execute([$tableId]);
        $lookup = $pdo->prepare("SELECT table_id,started_at,status FROM table_sessions WHERE id=? AND status IN('active','pending','followup') LIMIT 1 FOR UPDATE");
        $lookup->execute([$sessionId]);
        $session = $lookup->fetch();
        if (!$session) {
            if ($ownsTransaction) $pdo->commit();
            return;
        }

        $pendingStmt = $pdo->prepare("SELECT id FROM orders WHERE session_id=? AND status='pending_approval' ORDER BY id FOR UPDATE");
        $pendingStmt->execute([$sessionId]);
        $pendingOrderIds = array_map('intval', array_column($pendingStmt->fetchAll(), 'id'));
        if ($pendingOrderIds) {
            $placeholders = implode(',', array_fill(0, count($pendingOrderIds), '?'));
            $pdo->prepare("UPDATE orders SET status='cancelled' WHERE id IN($placeholders)")->execute($pendingOrderIds);
            $history = $pdo->prepare("INSERT INTO order_status_history(order_id,from_status,to_status,actor_user_id) VALUES(?,'pending_approval','cancelled',?)");
            foreach ($pendingOrderIds as $orderId) $history->execute([$orderId, $userId]);
        }

        $stmt = $pdo->prepare("UPDATE table_sessions SET status='closed',live_table_guard=NULL,ended_at=COALESCE(ended_at,NOW()),ended_reason=?,closed_by_user_id=? WHERE id=? AND status IN('active','pending','followup')");
        $stmt->execute([$reason, $userId, $sessionId]);
        $pdo->prepare("UPDATE waiter_calls SET status='cancelled',active_table_guard=NULL,cancelled_at=NOW(),cancel_reason='table_closed',cancelled_by_user_id=? WHERE status IN ('new','accepted') AND (session_id=? OR (session_id IS NULL AND table_id=? AND created_at>=?))")
            ->execute([$userId, $sessionId, $session['table_id'], $session['started_at']]);
        if ($ownsTransaction) $pdo->commit();
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function register_session_client(int $sessionId, string $deviceToken): void
{
    if ($sessionId < 1 || $deviceToken === '' || text_length($deviceToken) > 80) return;
    $stmt = db()->prepare('INSERT INTO table_session_clients(session_id,device_token,first_seen_at,last_seen_at) VALUES(?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE last_seen_at=NOW()');
    $stmt->execute([$sessionId, $deviceToken]);
}


/** @return array<int,string> */

// Canonical owner: includes/function_domains/favicon.php
require_once __DIR__ . '/function_domains/favicon.php';

// Canonical owner: includes/function_domains/messages.php
require_once __DIR__ . '/function_domains/messages.php';


/** Event lifecycle derived in the café timezone. */


function safe_external_url(string $url): string
{
    $url = trim($url);
    if ($url === '') return '';
    if (!filter_var($url, FILTER_VALIDATE_URL)) return '';
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http','https'], true) ? $url : '';
}

/** Return a supported visual theme for the guest-facing menu. */
function menu_theme(): string
{
    $theme = setting('menu_theme', 'courtyard');
    return in_array($theme, ['courtyard', 'night-courtyard', 'kilim-wood'], true) ? $theme : 'courtyard';
}

function font_catalog(): array
{
    return [
        'vazirmatn' => [
            'label' => 'وزیرمتن',
            'family' => 'Vazirmatn',
            'file' => 'assets/fonts/Vazirmatn-Variable.woff2',
            'description' => 'فونت ثابت کل سامانه',
        ],
    ];
}

function font_available(string $font): bool
{
    $catalog = font_catalog();
    if (!isset($catalog[$font])) return false;
    return is_file(dirname(__DIR__) . '/' . $catalog[$font]['file']);
}

function menu_font(): string
{
    return 'vazirmatn';
}

function ui_font(): string
{
    return 'vazirmatn';
}

function ui_font_family(string $font = ''): string
{
    $font = $font !== '' ? $font : ui_font();
    $catalog = font_catalog();
    $family = $catalog['vazirmatn']['family'];
    return '"' . $family . '", Tahoma, "Segoe UI", Arial, sans-serif';
}

/** Return preload and @font-face only when the licensed local WOFF2 file exists. */
function ui_font_head(string $font = ''): string
{
    $font = $font !== '' ? $font : ui_font();
    $catalog = font_catalog();
    if (!isset($catalog[$font])) $font = 'vazirmatn';
    $entry = $catalog[$font];
    $absolute = dirname(__DIR__) . '/' . $entry['file'];
    if (is_file($absolute)) {
        $url = asset($entry['file']);
        return '<link rel="preload" href="' . e($url) . '" as="font" type="font/woff2" crossorigin>'
            . '<style>@font-face{font-family:"Vazirmatn";src:url("' . e($url) . '") format("woff2");font-style:normal;font-weight:100 900;font-display:swap}</style>';
    }
    // Emergency fallback only until the pinned official font is copied to this server.
    return '<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-Variable-font-face.css">';
}

function ini_size_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '') return 0;
    $unit = strtolower(substr($value, -1));
    $number = (float)$value;
    return match ($unit) {
        'g' => (int)round($number * 1024 * 1024 * 1024),
        'm' => (int)round($number * 1024 * 1024),
        'k' => (int)round($number * 1024),
        default => (int)$number,
    };
}

/** Small, read-only server health snapshot used by the admin settings screen. */
function image_system_health(): array
{
    $uploads = dirname(__DIR__) . '/uploads';
    $uploadsReady = is_dir($uploads) ? is_writable($uploads) : is_writable(dirname($uploads));
    $gd = extension_loaded('gd');
    $details = $gd && function_exists('gd_info') ? gd_info() : [];
    return [
        'gd' => $gd,
        'webp' => $gd && function_exists('imagewebp') && (bool)($details['WebP Support'] ?? true),
        'jpeg' => $gd && function_exists('imagecreatefromjpeg'),
        'png' => $gd && function_exists('imagecreatefrompng'),
        'uploads_writable' => $uploadsReady,
        'upload_max_filesize' => (string)ini_get('upload_max_filesize'),
        'post_max_size' => (string)ini_get('post_max_size'),
        'memory_limit' => (string)ini_get('memory_limit'),
    ];
}

/** Render one icon from the local, dependency-free SVG sprite. */
function ui_icon(string $name, string $class = '', string $label = ''): string
{
    $safeName = preg_replace('/[^a-z0-9_-]+/i', '', $name) ?: 'info';
    $safeClass = trim(preg_replace('/[^a-z0-9 _-]+/i', '', $class) ?? '');
    $href = asset('assets/icons/ui-sprite.svg') . '#icon-' . $safeName;
    $aria = $label !== '' ? ' role="img" aria-label="' . e($label) . '"' : ' aria-hidden="true" focusable="false"';
    return '<svg class="ui-icon' . ($safeClass !== '' ? ' ' . e($safeClass) : '') . '"' . $aria . '><use href="' . e($href) . '"></use></svg>';
}

function menu_density(): string
{
    $density = setting('menu_density', 'balanced');
    return in_array($density, ['balanced', 'compact'], true) ? $density : 'balanced';
}

/** Visual composition of the guest menu. Both layouts share data and interaction logic. */
function menu_layout(): string
{
    $layout = setting('menu_layout', 'editorial');
    return in_array($layout, ['editorial', 'catalog'], true) ? $layout : 'editorial';
}

/** Convert a phone number to the digits-only international format expected by wa.me. */
function whatsapp_number(string $value): string
{
    $digits = preg_replace('/\D+/', '', en_digits(trim($value))) ?? '';
    if (str_starts_with($digits, '00')) $digits = substr($digits, 2);
    if (str_starts_with($digits, '0') && strlen($digits) === 11) $digits = '98' . substr($digits, 1);
    return preg_match('/^[1-9][0-9]{7,14}$/', $digits) ? $digits : '';
}

function whatsapp_url(): string
{
    if (!setting_bool('whatsapp_enabled', false)) return '';
    $number = whatsapp_number(setting('whatsapp_number'));
    if ($number === '') return '';
    $message = trim(setting('whatsapp_message', 'سلام، از طریق سایت سکنا پیام می‌دم.'));
    return 'https://wa.me/' . $number . ($message !== '' ? '?text=' . rawurlencode($message) : '');
}


/** WhatsApp registration link for an event. The event choice itself is explicit, so it uses the saved business number even if the generic footer button is hidden. */
function event_whatsapp_url(array $event): string
{
    $number = whatsapp_number(setting('whatsapp_number'));
    if ($number === '') return '';
    $title = trim((string)($event['title'] ?? 'رویداد'));
    $custom = trim((string)($event['registration_value'] ?? ''));
    $message = $custom !== '' ? $custom : 'سلام، برای رویداد «' . $title . '» اطلاعات و ثبت‌نام می‌خواهم.';
    return 'https://wa.me/' . $number . '?text=' . rawurlencode($message);
}

function social_links(): array
{
    $links = [];
    $cafeInstagram = safe_external_url(setting('instagram_cafe_url'));
    $houseInstagram = safe_external_url(setting('instagram_house_url'));
    $whatsapp = whatsapp_url();
    if ($cafeInstagram !== '') $links[] = ['key'=>'instagram_cafe','label'=>'اینستاگرام کافه','url'=>$cafeInstagram,'icon'=>'instagram'];
    if ($houseInstagram !== '') $links[] = ['key'=>'instagram_house','label'=>'اینستاگرام خانه سکنا','url'=>$houseInstagram,'icon'=>'home'];
    if ($whatsapp !== '') $links[] = ['key'=>'whatsapp','label'=>'گفت‌وگو در واتس‌اپ','url'=>$whatsapp,'icon'=>'whatsapp'];
    return $links;
}


function category_icon_registry(): array
{
    return [
        'نوشیدنی گرم' => [
            ['key'=>'bean','label'=>'قهوه و اسپرسو','keywords'=>['قهوه','اسپرسو','لاته','کاپوچینو','موکا','آمریکانو','بار گرم']],
            ['key'=>'brew','label'=>'قهوه دمی','keywords'=>['قهوه دمی','دمی','کمکس','وی ۶۰','v60','فرنچ پرس','ایروپرس']],
            ['key'=>'tea','label'=>'چای','keywords'=>['چای','تی بار','تی تایم']],
            ['key'=>'herbal','label'=>'دمنوش و گیاهی','keywords'=>['دمنوش','بابونه','به لیمو','گل گاوزبان','گیاهی گرم']],
            ['key'=>'cup-hot','label'=>'نوشیدنی گرم عمومی','keywords'=>['نوشیدنی گرم','هات درینک']],
        ],
        'نوشیدنی سرد' => [
            ['key'=>'iced-coffee','label'=>'قهوه سرد','keywords'=>['آیس کافی','آیس کافه','قهوه سرد','کلد برو']],
            ['key'=>'mocktail','label'=>'ماکتیل','keywords'=>['ماکتیل','موهیتو','لیموناد']],
            ['key'=>'juice','label'=>'آبمیوه','keywords'=>['آبمیوه','آب میوه','فروت جوس']],
            ['key'=>'sharbat','label'=>'شربت','keywords'=>['شربت','شربت خانه']],
            ['key'=>'shake','label'=>'شیک','keywords'=>['میلک شیک','شیک']],
            ['key'=>'smoothie','label'=>'اسموتی','keywords'=>['اسموتی']],
            ['key'=>'healthy-drink','label'=>'نوشیدنی سلامت','keywords'=>['دیتاکس','نوشیدنی سلامت']],
            ['key'=>'protein','label'=>'پروتئینی','keywords'=>['پروتئین','پروتئینی','ورزشی']],
            ['key'=>'energy-drink','label'=>'انرژی‌درینک','keywords'=>['انرژی درینک','انرژی‌درینک','انرژی زا','انرژی‌زا']],
            ['key'=>'water','label'=>'آب','keywords'=>['آب معدنی','آب گازدار','آب']],
            ['key'=>'sugar-free','label'=>'بدون قند','keywords'=>['بدون قند','زیرو','بدون شکر','شوگر فری']],
            ['key'=>'cold-drink','label'=>'نوشیدنی سرد عمومی','keywords'=>['بار سرد','نوشیدنی سرد']],
            ['key'=>'snowflake','label'=>'سرد عمومی','keywords'=>['سرد']],
        ],
        'صبحانه و بیکری' => [
            ['key'=>'brunch','label'=>'برانچ','keywords'=>['برانچ']],
            ['key'=>'breakfast','label'=>'صبحانه','keywords'=>['صبحانه','املت','نیمرو']],
            ['key'=>'bakery','label'=>'نان و بیکری','keywords'=>['بیکری','نان','نان تازه']],
            ['key'=>'pastry','label'=>'شیرینی و کروسان','keywords'=>['شیرینی','کروسان','کوکی']],
            ['key'=>'waffle','label'=>'وافل','keywords'=>['وافل']],
            ['key'=>'donut','label'=>'دونات','keywords'=>['دونات']],
        ],
        'غذا' => [
            ['key'=>'iranian-food','label'=>'غذای ایرانی','keywords'=>['غذای ایرانی','خورشت','خورش','پلو','چلو','کباب']],
            ['key'=>'grill','label'=>'گریل و استیک','keywords'=>['استیک','گریل','باربیکیو','bbq']],
            ['key'=>'fried','label'=>'سوخاری','keywords'=>['سوخاری','فرايد','فراید']],
            ['key'=>'seafood','label'=>'دریایی','keywords'=>['دریایی','ماهی','میگو','سالمون']],
            ['key'=>'sushi','label'=>'سوشی و آسیایی','keywords'=>['سوشی','ژاپنی']],
            ['key'=>'noodles','label'=>'نودل و آسیایی گرم','keywords'=>['نودل','رامن','آسیایی']],
            ['key'=>'soup','label'=>'سوپ','keywords'=>['سوپ']],
            ['key'=>'burger','label'=>'برگر','keywords'=>['برگر']],
            ['key'=>'hot-dog','label'=>'هات‌داگ','keywords'=>['هات داگ','هات‌داگ']],
            ['key'=>'pizza','label'=>'پیتزا','keywords'=>['پیتزا']],
            ['key'=>'pasta','label'=>'پاستا','keywords'=>['پاستا']],
            ['key'=>'sandwich','label'=>'ساندویچ و کلاب','keywords'=>['ساندویچ','کلاب']],
            ['key'=>'salad','label'=>'سالاد','keywords'=>['سالاد']],
            ['key'=>'appetizer','label'=>'پیش‌غذا','keywords'=>['پیش غذا','پیش‌غذا']],
            ['key'=>'fries','label'=>'سیب‌زمینی و دورچین','keywords'=>['سیب زمینی','سیب‌زمینی','دورچین']],
            ['key'=>'vegan','label'=>'گیاهی و وگان','keywords'=>['وگان','گیاهخواری','گیاهی']],
            ['key'=>'diet','label'=>'رژیمی و سبک','keywords'=>['رژیمی','کم کالری','فیت']],
            ['key'=>'kids-menu','label'=>'منوی کودک','keywords'=>['کودک','بچه','کیدز']],
            ['key'=>'sharing','label'=>'اشتراکی و دورهمی','keywords'=>['اشتراکی','دورهمی','چند نفره']],
            ['key'=>'combo','label'=>'پک و کمبو','keywords'=>['کمبو','پک','دو نفره','دونفره']],
            ['key'=>'sauce','label'=>'سس و افزودنی','keywords'=>['سس','افزودنی','تاپینگ']],
            ['key'=>'food','label'=>'غذاهای عمومی','keywords'=>['غذا','خوراک']],
        ],
        'دسر و شیرینی' => [
            ['key'=>'cake','label'=>'کیک','keywords'=>['کیک']],
            ['key'=>'dessert','label'=>'دسر','keywords'=>['دسر']],
            ['key'=>'ice-cream','label'=>'بستنی','keywords'=>['بستنی','ژلاتو']],
            ['key'=>'chocolate','label'=>'شکلات','keywords'=>['شکلات','چاکلت']],
        ],
        'ویژه و فروشگاهی' => [
            ['key'=>'service','label'=>'خوراک روز','keywords'=>['خوراک روز','غذای روز','پیشنهاد سرآشپز']],
            ['key'=>'seasonal','label'=>'فصلی','keywords'=>['فصلی','تابستان','زمستان','بهار','پاییز']],
            ['key'=>'retail','label'=>'محصول بسته‌بندی','keywords'=>['فروشگاهی','بسته بندی','بسته‌بندی','محصولات فروش']],
            ['key'=>'gift','label'=>'هدیه و گیفت','keywords'=>['هدیه','گیفت']],
            ['key'=>'sparkles','label'=>'ویژه‌ها','keywords'=>['ویژه','اسپشیال']],
            ['key'=>'list','label'=>'متفرقه','keywords'=>['متفرقه']],
        ],
    ];
}

function category_icon_library(): array
{
    $library = [];
    foreach (category_icon_registry() as $groupLabel => $entries) {
        foreach ($entries as $entry) $library[$groupLabel][(string)$entry['key']] = (string)$entry['label'];
    }
    return $library;
}

function category_visual_icon(?string $iconKey, string $name = ''): string
{
    $registry = category_icon_registry();
    $allowed = [];
    foreach ($registry as $entries) foreach ($entries as $entry) $allowed[(string)$entry['key']] = true;
    $iconKey = trim((string)$iconKey);
    if ($iconKey !== '' && isset($allowed[$iconKey])) return $iconKey;

    $normalized = str_replace(['ي','ك','‌','-','_','/'], ['ی','ک',' ',' ',' ',' '], trim($name));
    $normalized = preg_replace('/\s+/u', ' ', $normalized) ?: $normalized;
    foreach ($registry as $entries) {
        foreach ($entries as $entry) {
            foreach (($entry['keywords'] ?? []) as $keyword) {
                $needle = str_replace(['ي','ك','‌','-','_','/'], ['ی','ک',' ',' ',' ',' '], trim((string)$keyword));
                $needle = preg_replace('/\s+/u', ' ', $needle) ?: $needle;
                if ($needle !== '' && str_contains($normalized, $needle)) return (string)$entry['key'];
            }
        }
    }
    return 'sparkles';
}

function campaign_selection_mode(): string
{
    $mode = setting('campaign_selection_mode', 'priority');
    return in_array($mode, ['priority','rotation'], true) ? $mode : 'priority';
}

/** @return array<int,array<string,mixed>> */
function eligible_campaigns(): array
{
    if (!sokna_module_enabled('marketing') || !setting_bool('campaigns_enabled', true)) return [];
    try {
        return db()->query("SELECT * FROM campaigns WHERE active=1 AND (starts_at IS NULL OR starts_at<=NOW()) AND (ends_at IS NULL OR ends_at>=NOW()) ORDER BY sort_order,id")->fetchAll();
    } catch (Throwable) {
        return [];
    }
}

/** @param array<int,array<string,mixed>> $rows */
function select_campaign_from_eligible(array $rows, string $mode, string $visitorSeed): ?array
{
    if (!$rows) return null;
    if ($mode !== 'rotation' || count($rows) === 1) return $rows[0];
    $eligibleSignature = implode(',', array_map(static fn(array $row): string => (string)(int)($row['id'] ?? 0), $rows));
    $hash = hash('sha256', $visitorSeed . '|' . $eligibleSignature);
    $index = (int)(hexdec(substr($hash, 0, 8)) % count($rows));
    return $rows[$index];
}

function active_campaign(?string $visitorSeed = null): ?array
{
    $rows = eligible_campaigns();
    $seed = $visitorSeed ?? session_id();
    if ($seed === '') $seed = 'guest';
    return select_campaign_from_eligible($rows, campaign_selection_mode(), $seed);
}

function campaign_url(array $campaign): string
{
    $type = (string)($campaign['action_type'] ?? 'none');
    $value = trim((string)($campaign['action_value'] ?? ''));
    if ($type === 'external') return safe_external_url($value);
    if ($type === 'category' && ctype_digit($value)) return '#category-' . $value;
    if ($type === 'events') return sokna_module_enabled('marketing') && setting_bool('events_enabled', true) ? '#events' : '';
    if ($type === 'about') return asset('about.php');
    return '';
}

/** Fetch active, currently valid tags for a set of item ids. */
function item_tags_for(array $itemIds): array
{
    $itemIds = array_values(array_unique(array_filter(array_map('intval', $itemIds), static fn(int $id): bool => $id > 0)));
    if (!$itemIds) return [];
    $marks = implode(',', array_fill(0, count($itemIds), '?'));
    try {
        $stmt = db()->prepare("SELECT it.item_id,t.id,t.title,t.slug,t.tag_type,t.color_key,t.icon FROM item_tags it JOIN tags t ON t.id=it.tag_id WHERE it.item_id IN ($marks) AND t.active=1 AND (t.starts_at IS NULL OR t.starts_at<=NOW()) AND (t.ends_at IS NULL OR t.ends_at>=NOW()) ORDER BY t.tag_type='marketing' DESC,t.sort_order,t.id");
        $stmt->execute($itemIds);
        $result = [];
        foreach ($stmt->fetchAll() as $row) $result[(int)$row['item_id']][] = $row;
        return $result;
    } catch (Throwable) {
        return [];
    }
}

function item_schedule_sql(string $alias = 'i'): string
{
    $a = preg_replace('/[^A-Za-z0-9_]/', '', $alias) ?: 'i';
    return "($a.schedule_start IS NULL OR $a.schedule_start<=NOW())
        AND ($a.schedule_end IS NULL OR $a.schedule_end>=NOW())
        AND ($a.daily_start IS NULL OR $a.daily_end IS NULL OR
            ($a.daily_start<=$a.daily_end
                AND ($a.schedule_days IS NULL OR $a.schedule_days='' OR FIND_IN_SET(DAYOFWEEK(NOW()),$a.schedule_days))
                AND TIME(NOW()) BETWEEN $a.daily_start AND $a.daily_end)
            OR
            ($a.daily_start>$a.daily_end AND (
                (TIME(NOW())>=$a.daily_start AND ($a.schedule_days IS NULL OR $a.schedule_days='' OR FIND_IN_SET(DAYOFWEEK(NOW()),$a.schedule_days)))
                OR
                (TIME(NOW())<=$a.daily_end AND ($a.schedule_days IS NULL OR $a.schedule_days='' OR FIND_IN_SET(IF(DAYOFWEEK(NOW())=1,7,DAYOFWEEK(NOW())-1),$a.schedule_days)))
            )))";
}

function fa_datetime_input(?string $value): string
{
    if (!$value) return '';
    $time = strtotime($value);
    return $time ? date('Y-m-d\TH:i', $time) : '';
}

function metric_label(string $key): string
{
    return [
        'menu_view'=>'بازدید منو','item_add'=>'افزودن به سبد','order_submit'=>'سفارش ثبت‌شده',
        'event_open'=>'مشاهده رویدادها','campaign_click'=>'کلیک کمپین','instagram_click'=>'کلیک اینستاگرام',
        'whatsapp_click'=>'کلیک واتس‌اپ','accommodation_click'=>'کلیک اقامتگاه','about_open'=>'مشاهده درباره سکنا',
        'search_no_result'=>'جست‌وجوی بدون نتیجه',
        'search_query'=>'جست‌وجو در منو'
    ][$key] ?? $key;
}

/** Stable signature of the items a preparation area is responsible for. */
function preparation_items_signature(array $items): string
{
    $normalized=[];
    foreach ($items as $item) {
        $quantity=(int)($item['quantity']??0);
        if ($quantity<1) continue;
        $normalized[]=[
            'id'=>(int)($item['id']??0),
            'name'=>(string)($item['item_name']??$item['name']??''),
            'quantity'=>$quantity,
            'note'=>trim((string)($item['item_note']??$item['note']??'')),
            'fulfillment_mode'=>normalize_fulfillment_mode((string)($item['fulfillment_mode']??'dine_in')),
            'station'=>normalize_preparation_station((string)($item['preparation_station']??'cold_bar')),
        ];
    }
    usort($normalized,static fn(array $a,array $b):int=>($a['id']<=>$b['id'])?:strcmp($a['name'],$b['name'])?:strcmp($a['fulfillment_mode'],$b['fulfillment_mode']));
    return hash('sha256',json_encode($normalized,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
}

/** @return int[] IDs of preparation corrections that are still operationally open. */
function preparation_adjustments_pending_ids(PDO $pdo, int $sessionId, bool $forUpdate = false): array
{
    if ($sessionId < 1) return [];
    $sql = "SELECT id FROM preparation_adjustments WHERE session_id=? AND status NOT IN('applied','cancelled') ORDER BY id" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$sessionId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function preparation_adjustments_pending_count(PDO $pdo, int $sessionId, bool $forUpdate = false): int
{
    return count(preparation_adjustments_pending_ids($pdo, $sessionId, $forUpdate));
}

/** Settlement never closes preparation work; record the coexistence for audit only. */
function preparation_adjustments_audit_settlement_notice(int $sessionId, array $adjustmentIds, int $actorUserId, string $destination): void
{
    if ($sessionId < 1 || !$adjustmentIds) return;
    audit_log_write('settlement.with_open_preparation_adjustment', 'table_session', $sessionId, [
        'destination' => text_substr($destination, 0, 40),
        'adjustment_ids' => array_values(array_map('intval', $adjustmentIds)),
        'adjustment_count' => count($adjustmentIds),
        'note' => 'settlement completed while preparation correction remains open',
    ], $actorUserId);
}
