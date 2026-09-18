<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_login(['admin']);
require_once dirname(__DIR__) . '/includes/maintenance.php';
require dirname(__DIR__) . '/includes/panel_layout.php';

maintenance_ensure_storage();
$healthPath = maintenance_storage_dir() . '/health-last.json';

function maintenance_page_release_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
}

function maintenance_page_resume_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
}

function maintenance_page_stream_download(
    string $path,
    string $downloadName,
    string $contentType = 'application/octet-stream',
    ?callable $onComplete = null,
    bool $deleteAfter = false
): void {
    $size = (int)(filesize($path) ?: 0);
    if ($size < 1) throw new RuntimeException('فایل پشتیبان برای دانلود معتبر نیست.');
    $downloadName = basename($downloadName);
    if (!preg_match('/^[A-Za-z0-9._-]+$/', $downloadName)) throw new RuntimeException('نام فایل دانلود معتبر نیست.');
    $handle = fopen($path, 'rb');
    if (!$handle) throw new RuntimeException('فایل پشتیبان برای دانلود باز نشد.');

    maintenance_page_release_session();
    while (ob_get_level() > 0) @ob_end_clean();
    @set_time_limit(0);
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . (string)$size);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store, private');

    $sent = 0;
    try {
        while (!feof($handle)) {
            $chunk = fread($handle, 1024 * 1024);
            if ($chunk === false) break;
            if ($chunk === '') continue;
            echo $chunk;
            $sent += strlen($chunk);
            @flush();
            if (connection_aborted()) break;
        }
    } finally {
        fclose($handle);
    }

    if ($onComplete !== null && $sent === $size && connection_status() === CONNECTION_NORMAL) {
        try {
            $onComplete();
        } catch (Throwable $e) {
            error_log('off-server export tracking failed: ' . $e->getMessage());
        }
    }
    if ($deleteAfter) @unlink($path);
    exit;
}

function maintenance_page_latest_restorable(): ?array
{
    foreach (maintenance_list_backups(false) as $backup) {
        $manifest = $backup['manifest'] ?? null;
        if (!is_array($manifest)) continue;
        if (($manifest['type'] ?? '') !== 'backup') continue;
        if (($manifest['format'] ?? '') !== 'sokna-backup-v3' || empty($manifest['portable_app_identity'])) continue;
        if (($backup['valid'] ?? null) !== true) continue;
        if (($manifest['version'] ?? '') !== maintenance_version()) continue;
        return $backup;
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    $action = (string)($_POST['action'] ?? '');
    $actor = (int)(current_user()['id'] ?? 0);

    if ($action === 'export_secure') {
        $securePath = null;
        try {
            $passphrase = (string)($_POST['export_passphrase'] ?? '');
            $confirmation = (string)($_POST['export_passphrase_confirm'] ?? '');
            unset($_POST['export_passphrase'], $_POST['export_passphrase_confirm']);
            if (!hash_equals($passphrase, $confirmation)) throw new RuntimeException('تکرار رمز بازیابی با رمز اصلی یکسان نیست.');
            maintenance_secure_backup_validate_passphrase($passphrase);
            $name = basename((string)($_POST['name'] ?? ''));
            $backup = $name !== '' ? maintenance_backup_metadata(maintenance_backup_path($name), true) : maintenance_page_latest_restorable();
            if ($backup === null) throw new RuntimeException('هنوز پشتیبان سالم و قابل بازیابی برای دانلود وجود ندارد.');
            $secure = maintenance_secure_backup_create_export($backup, $passphrase);
            $securePath = (string)$secure['path'];
            // Do not retain the passphrase after the envelope has been created.
            if (function_exists('sodium_memzero')) sodium_memzero($passphrase);
            maintenance_page_stream_download(
                $securePath,
                (string)$secure['download_name'],
                'application/octet-stream',
                static function () use ($backup, $actor): void { maintenance_record_offserver_export($backup, $actor); },
                true
            );
        } catch (Throwable $e) {
            if (is_string($securePath) && $securePath !== '') @unlink($securePath);
            maintenance_page_resume_session();
            error_log('maintenance secure export: ' . $e->getMessage());
            flash('error', safe_business_error_message($e,'دریافت پشتیبان امن انجام نشد.'));
            redirect('maintenance.php');
        }
    }

    try {
        if ($action === 'upload') {
            $file=$_FILES['backup_file']??null;
            if(!is_array($file)||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('فایل پشتیبان برای بارگذاری انتخاب نشده یا انتقال آن کامل نشده است.');
            $tmp=(string)($file['tmp_name']??'');if($tmp===''||!is_uploaded_file($tmp))throw new RuntimeException('فایل بارگذاری‌شده معتبر نیست.');
            $passphrase=(string)($_POST['backup_passphrase']??'');unset($_POST['backup_passphrase']);
            maintenance_page_release_session();
            try{
                $imported=maintenance_import_backup_file($tmp,$actor,$passphrase);
                if(function_exists('sodium_memzero')&&$passphrase!=='')sodium_memzero($passphrase);
                maintenance_page_resume_session();
                flash('success','پشتیبان بارگذاری و سلامت آن بررسی شد؛ اکنون برای بازیابی آماده است.');
            }
            catch(Throwable $e){if(function_exists('sodium_memzero')&&$passphrase!=='')sodium_memzero($passphrase);maintenance_page_resume_session();throw $e;}
        } elseif ($action === 'create') {
            $job = maintenance_job_start('backup', ['source' => 'admin']);
            maintenance_page_release_session();
            try {
                $backup = maintenance_create_backup($actor);
                if (($backup['valid'] ?? false) !== true) throw new RuntimeException('پشتیبان ساخته شد اما بررسی نهایی کامل نبود.');
                maintenance_job_finish($job, true, 'پشتیبان سالم ساخته شد.');
                maintenance_page_resume_session();
                flash('success', 'پشتیبان جدید ساخته و سلامت آن کامل بررسی شد.');
            } catch (Throwable $e) {
                maintenance_job_finish($job, false, $e->getMessage());
                throw $e;
            }
        } elseif ($action === 'validate') {
            $name = basename((string)($_POST['name'] ?? ''));
            $path = maintenance_backup_path($name);
            $job = maintenance_job_start('validate', ['backup' => $name]);
            maintenance_page_release_session();
            try {
                $validated = maintenance_validate_archive($path);
                maintenance_write_backup_sidecar($path, $validated['manifest'], true);
                maintenance_job_finish($job, true, 'بررسی کامل شد.');
                maintenance_page_resume_session();
                flash('success', 'سلامت فایل پشتیبان کامل بررسی شد.');
            } catch (Throwable $e) {
                $meta = maintenance_backup_metadata($path, false);
                maintenance_write_backup_sidecar($path, is_array($meta['manifest'] ?? null) ? $meta['manifest'] : [], false, $e->getMessage());
                maintenance_job_finish($job, false, $e->getMessage());
                throw $e;
            }
        } elseif ($action === 'restore') {
            if (trim((string)($_POST['confirm_text'] ?? '')) !== 'بازیابی') throw new RuntimeException('برای تأیید، واژه «بازیابی» را دقیق وارد کنید.');
            if (!isset($_POST['confirm_understood'])) throw new RuntimeException('تأیید پیامد بازیابی لازم است.');
            $name = basename((string)($_POST['name'] ?? ''));
            maintenance_page_release_session();
            maintenance_restore_backup($name, true);
            maintenance_page_resume_session();
            logout_user();
            redirect('../login.php?restored=1');
        } elseif ($action === 'delete') {
            $name = basename((string)($_POST['name'] ?? ''));
            maintenance_lock(function () use ($name): void {
                $path = maintenance_backup_path($name);
                $meta = maintenance_backup_metadata($path, false);
                $manifest = $meta['manifest'] ?? [];
                if (($manifest['type'] ?? '') === 'backup' && ($meta['valid'] ?? null) === true) {
                    $valid = array_filter(maintenance_list_backups(false), static fn(array $b): bool => (
                        ($b['manifest']['type'] ?? '') === 'backup'
                        && ($b['valid'] ?? null) === true
                        && $b['name'] !== $name
                    ));
                    if (!$valid) throw new RuntimeException('آخرین پشتیبان سالم را نمی‌توان حذف کرد. ابتدا یک پشتیبان تازه بسازید.');
                }
                if (!unlink($path)) throw new RuntimeException('حذف فایل پشتیبان انجام نشد.');
                @unlink(maintenance_backup_sidecar_path($path));
            });
            flash('success', 'فایل پشتیبان حذف شد.');
        } elseif ($action === 'health') {
            $health = maintenance_health_check();
            @file_put_contents($healthPath, json_encode($health, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX);
            flash($health['ok'] ? 'success' : 'error', $health['ok'] ? 'بررسی سلامت سامانه موفق بود.' : 'بررسی سلامت مورد نیازمند رسیدگی پیدا کرد.');
        } else {
            throw new RuntimeException('عملیات شناخته‌شده نیست.');
        }
    } catch (Throwable $e) {
        maintenance_page_resume_session();
        error_log('maintenance action: '.$e->getMessage());
        flash('error', safe_business_error_message($e,'عملیات نگهداری انجام نشد.'));
    }
    maintenance_page_resume_session();
    redirect('maintenance.php');
}

$backups = maintenance_list_backups(false);
foreach ($backups as &$backup) {
    if (!is_array($backup['manifest'])) {
        $backup['manifest'] = [
            'type' => 'backup',
            'version' => '—',
            'created_at' => $backup['modified_at'],
        ];
    }
}
unset($backup);

$restorable = array_values(array_filter($backups, static fn(array $b): bool => (
    ($b['manifest']['type'] ?? '') === 'backup'
    && ($b['manifest']['format'] ?? '') === 'sokna-backup-v3'
    && !empty($b['manifest']['portable_app_identity'])
    && ($b['valid'] ?? null) === true
    && ($b['manifest']['version'] ?? '') === maintenance_version()
)));
$lastValid = $restorable[0] ?? null;
if ($lastValid !== null) maintenance_offserver_reminder_bootstrap();
$offserver = maintenance_offserver_export_status();
$scheduler = maintenance_backup_worker_status();

$lastHealth = [];
if (is_file($healthPath)) {
    $decoded = json_decode((string)@file_get_contents($healthPath), true);
    if (is_array($decoded)) $lastHealth = $decoded;
}
$state = maintenance_state();
$free = @disk_free_space(maintenance_storage_dir());
$lastValidTs = $lastValid ? (strtotime((string)($lastValid['manifest']['created_at'] ?? $lastValid['modified_at'])) ?: 0) : 0;
$backupFresh = $lastValidTs > 0 && $lastValidTs >= time() - ((MAINTENANCE_AUTO_BACKUP_HOURS + 2) * 3600);
$lastExportAt = is_string($offserver['last_export_at'] ?? null) ? (string)$offserver['last_export_at'] : '';

panel_header('پشتیبان و بازیابی', 'maintenance');
?>

<?php if (!empty($state['active'])): ?>
<div class="alert <?= ($state['mode'] ?? '') === 'recovery_required' ? 'alert-error' : 'alert-warning' ?>">
  <strong><?= ($state['mode'] ?? '') === 'recovery_required' ? 'بازیابی اضطراری لازم است' : 'سامانه در حالت نگهداری است' ?></strong><br>
  <?= e((string)($state['message'] ?? '')) ?>
</div>
<?php endif; ?>

<?php if (!empty($offserver['due'])): ?>
<div class="alert alert-warning backup-export-reminder">
  <div><strong>زمان ذخیره یک نسخه خارج از سرور رسیده است.</strong><span>بیش از ۷ روز از آخرین دانلود امن گذشته است. آخرین پشتیبان سالم را دانلود و در یک محل جدا از هاست نگهداری کنید.</span></div>
  <button class="btn btn-sm btn-primary" type="button" data-secure-export="<?= $lastValid ? e((string)$lastValid['name']) : '' ?>" <?= $lastValid ? '' : 'disabled' ?>>دانلود امن آخرین پشتیبان</button>
</div>
<?php endif; ?>

<section class="card backup-summary <?= (!$backupFresh || empty($scheduler['active'])) ? 'is-warning' : 'is-ok' ?>">
  <div class="backup-summary-head">
    <div>
      <small>وضعیت حفاظت از اطلاعات</small>
      <h2><?= !$lastValid ? 'هنوز پشتیبان سالم ندارید' : (!$backupFresh ? 'آخرین پشتیبان سالم قدیمی شده است' : (!empty($scheduler['active']) ? 'اطلاعات شما پشتیبان سالم و به‌روز دارد' : 'پشتیبان سالم دارید؛ اجرای خودکار هنوز تأیید نشده است')) ?></h2>
      <p><?php if (!$lastValid): ?>برای شروع، یک پشتیبان از اطلاعات و تصاویر سامانه بسازید.<?php elseif (!empty($scheduler['active'])): ?>پشتیبان خودکار فعال است و هر نسخه پیش از قابل‌بازیابی شدن کامل بررسی می‌شود.<?php else: ?>تا زمان فعال‌شدن اجرای خودکار روی هاست، می‌توانید از همین صفحه پشتیبان تازه بگیرید.<?php endif; ?></p>
    </div>
    <form method="post" data-confirm="از اطلاعات و تصاویر فعلی یک نسخه بازیابی جدید ساخته می‌شود." data-confirm-title="ساخت نسخه بازیابی؟" data-confirm-ok="ساخت نسخه">
      <?= csrf_field() ?>
      <button class="btn btn-primary" name="action" value="create">گرفتن پشتیبان جدید</button>
    </form>
  </div>
  <div class="backup-summary-grid">
    <div><span>آخرین پشتیبان سالم</span><strong><?= $lastValid ? e(format_jalali_compact((string)($lastValid['manifest']['created_at'] ?? $lastValid['modified_at']))) : '—' ?></strong><small><?= $backupFresh ? 'آماده بازیابی' : ($lastValid ? 'بهتر است یک پشتیبان تازه ساخته شود' : 'هنوز ساخته نشده') ?></small></div>
    <div class="<?= !empty($offserver['due']) ? 'needs-attention' : '' ?>"><span>نسخه خارج از سرور</span><strong><?= $lastExportAt !== '' ? e(format_jalali_compact($lastExportAt)) : 'هنوز دانلود نشده' ?></strong><small><?php if (!empty($offserver['due'])): ?>نیازمند دانلود<?php elseif ($lastExportAt !== ''): ?>یادآوری بعدی هر ۷ روز<?php else: ?>پس از ۷ روز یادآوری می‌شود<?php endif; ?></small></div>
    <div class="<?= empty($scheduler['active']) ? 'needs-attention' : '' ?>"><span>پشتیبان خودکار</span><strong><?= !empty($scheduler['active']) ? 'فعال' : (!empty($scheduler['seen']) ? 'نیازمند رسیدگی' : 'هنوز تأیید نشده') ?></strong><small>هر ۶ ساعت؛ ۲۸ نسخه اخیر روی سرور نگهداری می‌شود</small></div>
  </div>
</section>

<div class="maintenance-manager-grid">
  <section class="card offserver-card">
    <div class="card-head"><div><h2>یک نسخه خارج از سرور نگه دارید</h2><small>حداقل هفته‌ای یک‌بار آخرین پشتیبان سالم را روی لپ‌تاپ، حافظه امن یا فضای شخصی خود ذخیره کنید.</small></div></div>
    <div class="card-body">
      <div class="offserver-status-row">
        <div><span>آخرین دانلود ثبت‌شده</span><strong><?= $lastExportAt !== '' ? e(format_jalali_compact($lastExportAt)) : 'هنوز انجام نشده' ?></strong></div>
        <span class="badge <?= !empty($offserver['due']) ? 'badge-warning' : 'badge-completed' ?>"><?= !empty($offserver['due']) ? 'زمان دانلود رسیده' : 'وضعیت عادی' ?></span>
      </div>
      <button class="btn btn-light" type="button" data-secure-export="<?= $lastValid ? e((string)$lastValid['name']) : '' ?>" <?= $lastValid ? '' : 'disabled' ?>>دانلود امن آخرین پشتیبان سالم</button>
      <p class="muted">نسخه خارج از سرور با رمز بازیابی شما به فایل <span dir="ltr">.skb</span> تبدیل می‌شود. رمز روی سرور ذخیره نمی‌شود؛ آن را جدا و امن نگه دارید، چون بدون آن بازیابی این فایل ممکن نیست.</p>
    </div>
  </section>

  <section class="card">
    <div class="card-head"><div><h2>نسخه برنامه</h2><small>برای نصب نسخه جدید یا بازگشت امن خود برنامه؛ اطلاعات روزمره از بخش پشتیبان بالا مدیریت می‌شود.</small></div></div>
    <div class="card-body">
      <a class="btn btn-light" href="update/">مرکز به‌روزرسانی و بازیابی</a>
    </div>
  </section>
</div>

<section class="card restore-list-card">
  <div class="card-head"><div><h2>بازیابی اطلاعات</h2><small>همان فایل پشتیبان سکنا را می‌توانید روی این سرور بارگذاری و بازیابی کنید؛ تنظیمات فنی سرور جدید دست‌نخورده می‌ماند.</small></div></div>
  <div class="card-body">
    <form method="post" enctype="multipart/form-data" class="backup-upload-form">
      <?= csrf_field() ?><input type="hidden" name="action" value="upload">
      <label class="form-group full"><span>فایل پشتیبان سکنا</span><input class="form-control" type="file" name="backup_file" accept=".skb,.gz,.tar.gz,application/gzip,application/octet-stream" required><small class="muted">فایل امن <span dir="ltr">.skb</span> یا پشتیبان قدیمی <span dir="ltr">.tar.gz</span> پذیرفته می‌شود. قبل از تأیید شما هیچ داده‌ای تغییر نمی‌کند.</small></label>
      <label class="form-group full"><span>رمز بازیابی فایل امن</span><input class="form-control" type="password" name="backup_passphrase" minlength="12" maxlength="256" autocomplete="new-password"><small class="muted">فقط برای فایل <span dir="ltr">.skb</span> لازم است. این رمز ذخیره یا ثبت نمی‌شود.</small></label>
      <button class="btn btn-light" type="submit">بارگذاری و بررسی پشتیبان</button>
    </form>
  </div>
  <div class="card-body backup-version-list">
    <?php if (!$restorable): ?>
      <div class="empty-state">هنوز نسخه سالم و قابل بازیابی برای نسخه فعلی سامانه وجود ندارد.</div>
    <?php endif; ?>
    <?php foreach ($restorable as $backup): $manifest = $backup['manifest']; ?>
      <article class="backup-version-row">
        <div class="backup-version-copy">
          <strong><?= e(format_jalali_compact((string)($manifest['created_at'] ?? $backup['modified_at']))) ?></strong>
          <small><?= e(maintenance_format_bytes((int)$backup['size'])) ?> · سالم و آماده بازیابی</small>
        </div>
        <button
          class="btn btn-sm btn-outline"
          type="button"
          data-restore-backup="<?= e($backup['name']) ?>"
          data-restore-date="<?= e(format_jalali_compact((string)$manifest['created_at'])) ?>"
          data-restore-size="<?= e(maintenance_format_bytes((int)$backup['size'])) ?>"
        >بازگرداندن اطلاعات</button>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<details class="card maintenance-technical">
  <summary><span><strong>جزئیات و ابزارهای نگهداری</strong><small>برای بررسی فنی، آرشیوهای قدیمی و مدیریت فایل‌های پشتیبان</small></span><?= ui_icon('chevron-left') ?></summary>
  <div class="maintenance-technical-body">
    <section class="maintenance-health-strip">
      <div><span>آخرین بررسی سلامت</span><strong><?= empty($lastHealth) ? '—' : (!empty($lastHealth['ok']) ? 'سالم' : 'نیازمند رسیدگی') ?></strong><small><?= !empty($lastHealth['checked_at']) ? e(format_jalali_compact((string)$lastHealth['checked_at'])) : 'هنوز اجرا نشده' ?></small></div>
      <div><span>فضای آزاد سرور</span><strong><?= $free === false ? '—' : e(maintenance_format_bytes((int)$free)) ?></strong><small>پیش از ساخت و بازیابی دوباره کنترل می‌شود</small></div>
      <div><span>وضعیت عملیات</span><strong><?= !empty($state['active']) ? 'در حال نگهداری' : 'آماده' ?></strong><small><?= !empty($state['active']) ? e((string)($state['message'] ?? '')) : 'عملیات بازیابی فعالی وجود ندارد' ?></small></div>
      <form method="post"><?= csrf_field() ?><button class="btn btn-sm btn-light" name="action" value="health">اجرای بررسی سلامت</button></form>
    </section>

    <div class="maintenance-archive-head"><div><h3>فایل‌های پشتیبان روی سرور</h3><small>نسخه‌های پشتیبان موجود و وضعیت اعتبارسنجی آن‌ها.</small></div></div>
    <div class="data-table-wrap">
      <table class="data-table mobile-card-table maintenance-archive-table">
        <thead><tr><th>زمان</th><th>نسخه</th><th>حجم</th><th>وضعیت</th><th>عملیات</th></tr></thead>
        <tbody>
        <?php foreach ($backups as $backup):
          $manifest = $backup['manifest'];
          $version = (string)($manifest['version'] ?? '—');
        ?>
          <tr>
            <td data-label="زمان"><?= e(format_jalali_compact((string)($manifest['created_at'] ?? $backup['modified_at']))) ?></td>
            <td data-label="نسخه"><span dir="ltr"><?= e($version) ?></span></td>
            <td data-label="حجم"><?= e(maintenance_format_bytes((int)$backup['size'])) ?></td>
            <td data-label="وضعیت">
              <?php if (($backup['valid'] ?? null) === true): ?><span class="text-success">سالم</span>
              <?php elseif (($backup['valid'] ?? null) === false): ?><span class="text-danger">آسیب‌دیده یا نامعتبر</span>
              <?php else: ?><span class="muted">هنوز بررسی نشده</span><?php endif; ?>
            </td>
            <td data-label="عملیات">
              <div class="actions mobile-action-grid">
                <?php $canSecureExport = (($backup['valid'] ?? null) === true) && (($manifest['type'] ?? '') === 'backup') && (($manifest['version'] ?? '') === maintenance_version()); ?><button class="btn btn-sm btn-light" type="button" data-secure-export="<?= $canSecureExport ? e((string)$backup['name']) : '' ?>" <?= $canSecureExport ? '' : 'disabled' ?>>دانلود امن</button>
                <?php if (($backup['valid'] ?? null) !== true): ?>
                  <form method="post"><?= csrf_field() ?><input type="hidden" name="name" value="<?= e($backup['name']) ?>"><button class="btn btn-sm btn-outline" name="action" value="validate">بررسی فایل</button></form>
                <?php endif; ?>
                <form method="post" data-confirm="این نسخه بازیابی از سرور حذف می‌شود و دیگر از همین فایل قابل بازگردانی نیست." data-confirm-title="حذف نسخه بازیابی؟" data-confirm-ok="حذف نسخه" data-confirm-danger="1"><?= csrf_field() ?><input type="hidden" name="name" value="<?= e($backup['name']) ?>"><button class="btn btn-sm btn-danger" name="action" value="delete">حذف</button></form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$backups): ?><tr><td colspan="5" class="empty-state">هنوز فایل پشتیبانی روی سرور وجود ندارد.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</details>

<dialog class="panel-action-dialog" id="secureExportDialog">
  <form method="post" id="secureExportForm">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="export_secure">
    <input type="hidden" name="name" id="secureExportBackupName">
    <div class="panel-action-dialog-head"><small>نسخه خارج از سرور</small><h3>ساخت فایل پشتیبان رمزگذاری‌شده</h3><p>یک رمز بازیابی قوی انتخاب کنید. سکنا این رمز را ذخیره نمی‌کند.</p></div>
    <div class="alert alert-warning">اگر این رمز را گم کنید، فایل <span dir="ltr">.skb</span> قابل بازیابی نخواهد بود. رمز را جدا از خود فایل نگه دارید.</div>
    <label class="form-group"><span>رمز بازیابی</span><input class="form-control" type="password" name="export_passphrase" minlength="12" maxlength="256" autocomplete="new-password" required></label>
    <label class="form-group"><span>تکرار رمز بازیابی</span><input class="form-control" type="password" name="export_passphrase_confirm" minlength="12" maxlength="256" autocomplete="new-password" required></label>
    <div class="actions"><button class="btn btn-light" type="button" id="cancelSecureExport">انصراف</button><button class="btn btn-primary" type="submit">ساخت و دانلود فایل امن</button></div>
  </form>
</dialog>

<dialog class="panel-action-dialog restore-dialog" id="restoreDialog">
  <form method="post" id="restoreForm">
    <?= csrf_field() ?>
    <input type="hidden" name="name" id="restoreBackupName">
    <div class="panel-action-dialog-head"><small>بازیابی اطلاعات</small><h3>بازگرداندن اطلاعات به نسخه انتخاب‌شده</h3><p id="restoreSummary"></p></div>
    <div class="alert alert-warning">تمام تغییرات ثبت‌شده بعد از زمان این پشتیبان ممکن است از بین بروند. پیش از شروع، سامانه به‌صورت خودکار یک نقطه ایمنی از وضعیت فعلی می‌سازد.</div>
    <label class="confirm-check"><input type="checkbox" name="confirm_understood" required> زمان نسخه و پیامد بازگرداندن اطلاعات را بررسی کردم.</label>
    <div class="form-group"><label>برای تأیید بنویسید: بازیابی</label><input class="form-control" name="confirm_text" autocomplete="off" required></div>
    <div class="actions"><button class="btn btn-light" type="button" id="cancelRestore">انصراف</button><button class="btn btn-danger" name="action" value="restore">شروع بازیابی</button></div>
  </form>
</dialog>

<script>
(()=>{
  const dialog=document.getElementById('restoreDialog');
  const form=document.getElementById('restoreForm');
  const name=document.getElementById('restoreBackupName');
  const summary=document.getElementById('restoreSummary');
  const reset=()=>{form.reset();name.value='';summary.textContent='';};
  document.querySelectorAll('[data-restore-backup]').forEach(button=>button.addEventListener('click',()=>{
    reset();
    name.value=button.dataset.restoreBackup||'';
    summary.textContent=`${button.dataset.restoreDate||''} · ${button.dataset.restoreSize||''}`;
    dialog.showModal();
  }));
  document.getElementById('cancelRestore')?.addEventListener('click',()=>{dialog.close();reset();});
  dialog.addEventListener('close',reset);
  dialog.addEventListener('cancel',reset);

  const exportDialog=document.getElementById('secureExportDialog');
  const exportForm=document.getElementById('secureExportForm');
  const exportName=document.getElementById('secureExportBackupName');
  const resetExport=()=>{exportForm.reset();exportName.value='';};
  document.querySelectorAll('[data-secure-export]').forEach(button=>button.addEventListener('click',()=>{
    if(button.disabled)return;
    resetExport();
    exportName.value=button.dataset.secureExport||'';
    exportDialog.showModal();
    exportForm.elements.export_passphrase?.focus();
  }));
  document.getElementById('cancelSecureExport')?.addEventListener('click',()=>{exportDialog.close();resetExport();});
  exportDialog.addEventListener('close',resetExport);
  exportDialog.addEventListener('cancel',resetExport);
})();
</script>
<?php panel_footer(); ?>
