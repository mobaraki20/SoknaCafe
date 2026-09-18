<?php
declare(strict_types=1);
$root = dirname(__DIR__);
require $root . '/includes/functions.php';
require $root . '/includes/auth.php';

function ok(bool $condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
}

$temp = sys_get_temp_dir() . '/sokna-login-rate-' . bin2hex(random_bytes(5)) . '.json';
putenv('SOKNA_AUTH_RATE_PATH=' . $temp);
$_SERVER['REMOTE_ADDR'] = '198.51.100.23';

ok(auth_login_rate_status(11)['allowed'] === true, 'fresh account/IP must be allowed');
for ($i = 0; $i < 7; $i++) auth_login_rate_fail(11);
$status = auth_login_rate_status(11);
ok($status['allowed'] === true && $status['account_failures'] === 7, 'seven failures must remain usable');
auth_login_rate_fail(11);
$status = auth_login_rate_status(11);
ok($status['allowed'] === false && $status['account_failures'] === 8 && $status['retry_after'] > 0, 'eighth failure must throttle this account/IP bucket');
ok(auth_login_rate_status(12)['allowed'] === true, 'another account on same IP must not be blocked by one account threshold');
auth_login_rate_clear(11);
ok(auth_login_rate_status(11)['allowed'] === true, 'successful-login clear must release account bucket');

// Global IP ceiling prevents cycling through the visible staff list indefinitely.
for ($uid = 100; $uid < 122; $uid++) auth_login_rate_fail($uid);
$global = auth_login_rate_status(999);
ok($global['allowed'] === false && $global['ip_failures'] >= 30, 'IP-wide failure ceiling must block account cycling');

@unlink($temp);
@rmdir(dirname($temp));
putenv('SOKNA_AUTH_RATE_PATH');
echo "RC2 login security PASS: per-account/IP and IP-wide throttles are bounded, generic, and clearable on success.\n";
