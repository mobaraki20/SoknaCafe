<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_login(['admin']);
require_once dirname(__DIR__) . '/includes/panel_layout.php';
require_once dirname(__DIR__) . '/includes/guest_publish.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    try {
        $result = guest_publish_now(db());
        audit_log_write('guest_menu.published','guest_publish',0,[
            'revision_id'=>(string)$result['revision_id'],'content_hash'=>(string)$result['content_hash'],
        ]);
        flash('success','نسخه منوی مهمان منتشر شد: ' . (string)$result['revision_id']);
    } catch (Throwable $e) {
        error_log('guest publish: ' . $e->getMessage());
        flash('error', safe_business_error_message($e,'انتشار منوی مهمان انجام نشد؛ نسخه منتشرشده قبلی همچنان فعال است.'));
    }
    redirect('guest_publish.php');
}

$preview = null;
try { $preview = guest_publish_build_package(db()); } catch (Throwable $e) { $preview = ['error'=>$e->getMessage()]; }
panel_header('انتشار منوی مهمان','settings');
?>
<div class="panel-surface-stack">
<section class="card">
  <div class="card-head"><div><h2>انتشار عمومی</h2><small>ذخیره‌های مدیریت فقط داخل سکنا می‌مانند. با این دکمه یک نسخه immutable تازه روی Public فعال می‌شود.</small></div></div>
  <div class="card-body">
    <?php if(isset($preview['error'])): ?><div class="notice error"><?= e((string)$preview['error']) ?></div><?php else: ?>
    <dl class="panel-kv-list">
      <div><dt>Revision آماده</dt><dd><code><?= e((string)$preview['revision_id']) ?></code></dd></div>
      <div><dt>رسانه‌ها</dt><dd><?= fa_digits((string)count($preview['media_manifest'] ?? [])) ?></dd></div>
      <div><dt>زمان ساخت Preview</dt><dd><?= e((string)$preview['generated_at']) ?></dd></div>
    </dl>
    <form method="post" class="panel-action-bar"><?= csrf_field() ?><button class="btn btn-primary" type="submit">انتشار منوی مهمان</button><a class="btn btn-light" href="settings.php#settingsGuest">بازگشت به تنظیمات</a></form>
    <p class="muted">اگر آپلود یا validation شکست بخورد، Public pointer قبلی تغییر نمی‌کند.</p>
    <?php endif; ?>
  </div>
</section>
</div>
<?php panel_footer(); ?>
