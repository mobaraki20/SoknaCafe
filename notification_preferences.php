<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require_login();
require_once __DIR__ . '/includes/push.php';
require __DIR__ . '/includes/panel_layout.php';

$user = current_user();
$userId = (int)($user['id'] ?? 0);
$available = push_user_available_preferences($userId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    $selected = array_values(array_filter(array_map('strval', (array)($_POST['enabled'] ?? [])), static fn(string $key): bool => array_key_exists($key, $available)));
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO user_notification_preferences(user_id,preference_key,enabled) VALUES(?,?,?) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled)');
        $saved = [];
        foreach ($available as $key=>$definition) {
            $enabled = in_array($key, $selected, true) ? 1 : 0;
            $stmt->execute([$userId,$key,$enabled]);
            $saved[$key] = $enabled;
        }
        audit_log_write_strict($pdo,'push.user_preferences_changed','user',$userId,['preferences'=>$saved],$userId);
        $pdo->commit();
        flash('success','تنظیم اعلان‌های شما ذخیره شد.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('push preferences save: '.$e->getMessage());
        flash('error',safe_business_error_message($e,'ذخیره تنظیم اعلان‌ها انجام نشد.'));
    }
    redirect(asset('notification_preferences.php'));
}

$values = [];
foreach ($available as $key=>$definition) $values[$key] = push_user_preference_enabled($userId,$key);
panel_header('اعلان‌های من','notification_preferences');
?>
<div class="panel-page-flow notification-preferences-page" data-visual-quality-page="notification_preferences">
<section class="card notification-preferences-card">
  <div class="card-head"><div class="panel-copy-stack"><h2>اعلان‌های من</h2><small>فقط اعلان‌هایی نمایش داده می‌شوند که به مسئولیت شما مربوط هستند.</small></div></div>
  <div class="card-body panel-card-flow">
    <?php if(!$available): ?>
      <div class="empty-state">برای این حساب اعلان عملیاتی قابل تنظیمی وجود ندارد.</div>
    <?php else: ?>
      <form method="post" class="notification-preferences-form">
        <?= csrf_field() ?>
        <div class="notification-preference-list">
        <?php foreach($available as $key=>$definition): ?>
          <label class="notification-preference-row">
            <span><strong><?= e((string)$definition['label']) ?></strong><?php if($key==='preparation'): ?><small>فقط برای بخش‌های آماده‌سازی‌ای که به شما تخصیص داده شده‌اند.</small><?php elseif($key==='pending_guest_order'): ?><small>اعلان سفارش‌هایی که مهمان ثبت کرده و نیازمند بررسی سالن هستند.</small><?php else: ?><small>فراخوان مهمان از میز برای رسیدگی سالن.</small><?php endif; ?></span>
            <input type="checkbox" name="enabled[]" value="<?= e($key) ?>" <?= !empty($values[$key])?'checked':'' ?>>
          </label>
        <?php endforeach; ?>
        </div>
        <p class="notification-preferences-note">خاموش‌کردن Push، وظیفه یا سفارش را از پنل حذف نمی‌کند؛ پنل همچنان مرجع عملیات است.</p>
        <div class="form-actions"><button class="btn btn-primary" type="submit">ذخیره تنظیم اعلان‌ها</button></div>
      </form>
    <?php endif; ?>
  </div>
</section>
</div>
<?php panel_footer(); ?>
