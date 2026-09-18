<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_login(['admin']);
sokna_module_require('personnel');
require dirname(__DIR__) . '/includes/panel_layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    try {
        $action = (string)($_POST['action'] ?? 'connect');
        $actorUserId = (int)(current_user()['id'] ?? 0);
        if ($action === 'test') {
            if (!sokna_center_connection_enabled()) throw new RuntimeException('ابتدا اتصال مرکز سکنا را انجام دهید.');
            sokna_center_test_connection($actorUserId);
            flash('success', 'اتصال مرکز سکنا تأیید شد.');
        } elseif ($action === 'connect') {
            $pairingKey = trim((string)($_POST['sokna_center_pairing_key'] ?? ''));
            if ($pairingKey === '') throw new InvalidArgumentException('کلید اتصال مرکز سکنا را وارد کنید.');
            sokna_center_connect_with_key($pairingKey, $actorUserId);
            flash('success', 'مرکز سکنا متصل و آزمایش شد.');
        } else {
            throw new InvalidArgumentException('عملیات اتصال معتبر نیست.');
        }
    } catch (InvalidArgumentException|SoknaCenterAuthException $e) {
        flash('error', $e->getMessage());
    } catch (Throwable) {
        flash('error', 'اتصال مرکز سکنا انجام نشد.');
    }
    redirect('center_settings.php');
}

$connected = sokna_center_connection_enabled() && sokna_center_base_url() !== '' && sokna_center_secret() !== '';
$connectionNeedsReview = $connected && trim(setting('sokna_center_last_error')) !== '';
$statusLabel = !$connected ? 'متصل نشده' : ($connectionNeedsReview ? 'نیازمند بررسی' : 'متصل');
$statusClass = $connectionNeedsReview ? 'text-danger' : ($connected ? 'text-success' : '');
panel_header('تنظیم اتصال مرکز سکنا', 'center_settings');
?>
<section class="card settings-section">
    <div class="card-head">
        <div>
            <h2>اتصال به مرکز سکنا</h2>
            <small>اتصال امن کافه به مرکز سکنا فقط با کلید اتصال انجام می‌شود.</small>
        </div>
    </div>
    <div class="card-body">
        <div class="integration-health-grid">
            <div><span>وضعیت اتصال</span><strong class="<?= e($statusClass) ?>"><?= e($statusLabel) ?></strong></div>
        </div>

        <form method="post" class="form-grid" style="margin-top:16px"><?= csrf_field() ?>
            <div class="form-group full">
                <label for="soknaCenterPairingKey">کلید اتصال مرکز سکنا</label>
                <input class="form-control ltr-input" id="soknaCenterPairingKey" dir="ltr" type="password" name="sokna_center_pairing_key" value="" placeholder="<?= $connected?'برای تغییر اتصال، کلید جدید را وارد کنید':'کلید را از مرکز سکنا وارد کنید' ?>" autocomplete="new-password">
            </div>
            <div class="form-group full">
                <div class="panel-action-bar">
                    <button class="btn btn-primary" name="action" value="connect"><?= $connected?'اتصال مجدد / تغییر کلید':'اتصال و آزمایش' ?></button>
                    <?php if($connected): ?><button class="btn btn-light" name="action" value="test" formnovalidate>آزمایش اتصال</button><?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</section>
<?php panel_footer(); ?>
