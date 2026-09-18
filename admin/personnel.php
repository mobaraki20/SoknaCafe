<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_login();
sokna_module_require('personnel');
require dirname(__DIR__) . '/includes/panel_layout.php';
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');

$user = current_user();
$actorUserId = (int)($user['id'] ?? 0);
$target = '';
$token = '';
$errorMessage = '';

try {
    if (!sokna_center_connection_enabled()) throw new RuntimeException('اتصال مرکز سکنا هنوز فعال نشده است.');
    if (!$user || $actorUserId < 1) throw new RuntimeException('ابتدا وارد سامانه شوید.');

    // Handoff is a local cryptographic operation. Availability/entitlement probes are only
    // navigation/diagnostic hints and must never be a prerequisite for launching Center.
    // Center remains the authorization source of truth and rejects an unauthorized handoff.
    $target = sokna_center_handoff_target();
    $token = sokna_center_handoff_token($user, 'CAFE');
    audit_log_write('center_handoff_started', 'integration', 'sokna_center', ['local_user_id'=>$actorUserId], $actorUserId);
} catch (Throwable $e) {
    $reason = $e instanceof SoknaCenterAuthException ? $e->reasonCode : (sokna_center_connection_enabled() ? 'local_failure' : 'integration_disabled');
    audit_log_write('center_handoff_failed', 'integration', 'sokna_center', ['reason'=>$reason], $actorUserId > 0 ? $actorUserId : null);
    if ($e instanceof SoknaCenterAuthException) {
        $errorMessage = $e->getMessage();
    } elseif (!sokna_center_connection_enabled()) {
        $errorMessage = 'اتصال مرکز سکنا هنوز فعال نشده است.';
    } else {
        $errorMessage = sokna_center_unavailable_message();
    }
}

panel_header('پرسنل و حقوق', 'personnel');
?>
<section class="card">
    <div class="card-head"><div><h2>پرسنل و حقوق</h2><small>اطلاعات پرسنلی، همکاری‌ها، حقوق و پرداخت‌ها در مرکز سکنا مدیریت می‌شوند.</small></div></div>
    <div class="card-body">
        <?php if($token !== '' && $target !== ''): ?>
            <div class="alert alert-info">در حال ورود امن به مرکز سکنا…</div>
            <form id="soknaCenterHandoff" method="post" action="<?= e($target) ?>">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <noscript><button class="btn btn-primary" type="submit">ورود به پرسنل و حقوق</button></noscript>
            </form>
            <script>requestAnimationFrame(()=>document.getElementById('soknaCenterHandoff')?.submit());</script>
        <?php else: ?>
            <div class="alert alert-warning"><?= e($errorMessage ?: sokna_center_unavailable_message()) ?></div>
            <div class="panel-action-bar" style="margin-top:16px">
                <a class="btn btn-primary" href="personnel.php">تلاش دوباره</a>
                <?php if(($user['role'] ?? '') === 'admin'): ?><a class="btn btn-light" href="center_settings.php">بررسی تنظیم اتصال</a><?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php panel_footer(); ?>
