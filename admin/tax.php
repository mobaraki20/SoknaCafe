<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
sokna_module_require('tax');
require_login(['admin']);
require dirname(__DIR__) . '/includes/panel_layout.php';

$pdo = db();
$user = current_user();
$actorUserId = (int)($user['id'] ?? 0);

function tax_admin_effective_at(string $dateField, string $timeField, string $label): string
{
    $date = trim((string)($_POST[$dateField] ?? ''));
    $time = trim((string)($_POST[$timeField] ?? ''));
    if ($date === '' && $time === '') return date('Y-m-d H:i:s');
    $parsed = parse_optional_jalali_datetime($date, $time, $label);
    if ($parsed === null) throw new RuntimeException($label . ' را کامل وارد کنید.');
    return $parsed;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    $action = (string)($_POST['action'] ?? '');
    try {
        $pdo->beginTransaction();
        if ($action === 'create_rate') {
            $rateBps = tax_rate_bps_normalize($_POST['rate_percent'] ?? '');
            $effectiveAt = tax_admin_effective_at('effective_date_j', 'effective_time', 'زمان شروع نرخ');
            tax_create_rate_version_locked($pdo, $rateBps, $effectiveAt, $actorUserId);
            $pdo->commit();
            flash('success', 'نسخه جدید نرخ مالیات ثبت شد. اسناد قبلی تغییر نمی‌کنند.');
            redirect('tax.php');
        }
        if ($action === 'create_item_policy') {
            $itemId = (int)($_POST['item_id'] ?? 0);
            $policy = (string)($_POST['policy'] ?? 'inherit_default');
            $customRateBps = $policy === 'custom_rate' ? tax_rate_bps_normalize($_POST['custom_rate_percent'] ?? '') : null;
            $effectiveAt = tax_admin_effective_at('policy_effective_date_j', 'policy_effective_time', 'زمان شروع سیاست کالا');
            tax_create_item_policy_version_locked($pdo, $itemId, $policy, $customRateBps, $effectiveAt, $actorUserId);
            $pdo->commit();
            flash('success', 'سیاست مالیاتی کالا ثبت شد. سفارش‌های قبلی با اطلاعات مالیاتی ثبت‌شده قبلی باقی می‌مانند.');
            redirect('tax.php');
        }
        throw new RuntimeException('عملیات مالیات معتبر نیست.');
    } catch (RuntimeException|InvalidArgumentException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('error', $e->getMessage());
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('tax admin: ' . $e->getMessage());
        flash('error', 'تنظیم مالیات ذخیره نشد. دوباره تلاش کنید.');
    }
}

$currentRate = tax_default_rate_row($pdo);
$rateRows = $pdo->query("SELECT tr.*,u.display_name actor_name FROM tax_rate_versions tr LEFT JOIN users u ON u.id=tr.created_by_user_id ORDER BY tr.effective_from DESC,tr.id DESC LIMIT 24")->fetchAll();
$itemRows = $pdo->query("SELECT i.id,i.name,i.active,
    (SELECT p.policy FROM tax_item_policy_versions p WHERE p.item_id=i.id AND p.effective_from<=NOW() ORDER BY p.effective_from DESC,p.id DESC LIMIT 1) current_policy,
    (SELECT p.custom_rate_bps FROM tax_item_policy_versions p WHERE p.item_id=i.id AND p.effective_from<=NOW() ORDER BY p.effective_from DESC,p.id DESC LIMIT 1) current_custom_rate_bps
    FROM items i ORDER BY i.active DESC,i.name,i.id")->fetchAll();
$policyRows = $pdo->query("SELECT tp.*,i.name item_name,u.display_name actor_name FROM tax_item_policy_versions tp JOIN items i ON i.id=tp.item_id LEFT JOIN users u ON u.id=tp.created_by_user_id ORDER BY tp.effective_from DESC,tp.id DESC LIMIT 40")->fetchAll();

$todayJ = jalali_date_input(date('Y-m-d'));
$nowTime = date('H:i');
$ready = sokna_module_runtime_ready('tax');
panel_header('مالیات','tax');
?>
<div class="financial-workspace panel-page-flow" data-visual-quality-page="tax">
  <section class="card financial-record-hero">
    <div class="card-head">
      <div class="panel-copy-stack">
        <small class="muted">مالیات فروش</small>
        <h2><?= $currentRate ? e(tax_rate_bps_label((int)$currentRate['rate_bps'])) : 'بدون نرخ مؤثر' ?></h2>
        <small>قیمت‌های منو همیشه مبلغ پیش از مالیات هستند. مالیات بعد از سهم تخفیف محاسبه می‌شود و هر سفارش نرخ زمان ثبت خودش را نگه می‌دارد.</small>
      </div>
      <span class="panel-status-badge <?= $ready ? 'is-success' : 'is-warning' ?>"><?= $ready ? 'آماده' : 'نیازمند نرخ' ?></span>
    </div>
  </section>

  <div class="panel-helper-note">
    <strong>تاریخچه بازنویسی نمی‌شود.</strong>
    <span>برای تغییر نرخ یا وضعیت یک کالا، نسخه جدید ثبت کنید. فاکتورهای قبلی و سفارش‌های باز با اطلاعات مالیاتی ثبت‌شده قبلی باقی می‌مانند.</span>
  </div>

  <section class="card financial-record-section">
    <div class="card-head"><div><h2>نرخ پیش‌فرض</h2><small>برای همه کالاهایی که سیاست اختصاصی ندارند.</small></div></div>
    <div class="card-body">
      <form method="post" class="form-grid">
        <?= csrf_field() ?><input type="hidden" name="action" value="create_rate">
        <div class="form-group"><label>نرخ مالیات، درصد</label><input class="form-control" type="text" inputmode="decimal" name="rate_percent" placeholder="مثلاً ۱۰" required></div>
        <div class="form-group"><label>تاریخ شروع</label><div class="jalali-date-control"><input class="form-control" id="taxEffectiveDate" name="effective_date_j" data-jalali-date inputmode="none" autocomplete="off" value="<?= e($todayJ) ?>" required><button class="jalali-date-button" type="button" data-open-jalali="taxEffectiveDate" aria-label="انتخاب تاریخ شروع نرخ"><?= ui_icon('calendar') ?></button></div></div>
        <div class="form-group"><label>زمان شروع</label><input class="form-control" type="time" name="effective_time" value="<?= e($nowTime) ?>" step="60" required></div>
        <div class="form-group actions"><button class="btn btn-primary" type="submit">ثبت نسخه جدید نرخ</button></div>
      </form>
    </div>
    <div class="financial-list">
      <?php if (!$rateRows): ?><div class="empty-state">هنوز نرخ پیش‌فرضی ثبت نشده است.</div><?php endif; ?>
      <?php foreach ($rateRows as $row): ?>
        <article class="financial-row"><div class="financial-row-main"><strong><?= e(tax_rate_bps_label((int)$row['rate_bps'])) ?></strong><span>از <?= e(format_jalali_human_datetime((string)$row['effective_from'])) ?></span><small><?= e((string)($row['actor_name'] ?: 'سامانه')) ?></small></div><div class="financial-row-amount"><span class="panel-status-badge <?= $currentRate && (int)$currentRate['id']===(int)$row['id'] ? 'is-success' : 'is-muted' ?>"><?= $currentRate && (int)$currentRate['id']===(int)$row['id'] ? 'مؤثر اکنون' : 'نسخه تاریخی/آینده' ?></span></div></article>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="card financial-record-section">
    <div class="card-head"><div><h2>سیاست مالیاتی کالا</h2><small>پیش‌فرض، معاف یا نرخ اختصاصی؛ تغییر فقط روی سفارش‌های بعدی اثر می‌گذارد.</small></div></div>
    <div class="card-body">
      <form method="post" class="form-grid" data-tax-policy-form>
        <?= csrf_field() ?><input type="hidden" name="action" value="create_item_policy">
        <div class="form-group"><label>کالا</label><select class="form-control" name="item_id" required data-choice-mode="compact"><option value="">انتخاب کالا</option><?php foreach($itemRows as $item): ?><option value="<?= (int)$item['id'] ?>"><?= e((string)$item['name']) ?><?= empty($item['active'])?' · غیرفعال':'' ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label>سیاست</label><select class="form-control" id="taxItemPolicy" name="policy" required data-choice-mode="compact"><option value="inherit_default">نرخ پیش‌فرض</option><option value="exempt">معاف از مالیات</option><option value="custom_rate">نرخ اختصاصی</option></select></div>
        <div class="form-group hidden" data-panel-condition-source="taxItemPolicy" data-panel-condition-value="custom_rate"><label>نرخ اختصاصی، درصد</label><input class="form-control" type="text" inputmode="decimal" name="custom_rate_percent" placeholder="مثلاً ۵"><small class="muted">تنظیم پیشرفته؛ فقط برای کالاهایی که واقعاً نرخ متفاوت دارند.</small></div>
        <div class="form-group"><label>تاریخ شروع</label><div class="jalali-date-control"><input class="form-control" id="taxPolicyEffectiveDate" name="policy_effective_date_j" data-jalali-date inputmode="none" autocomplete="off" value="<?= e($todayJ) ?>" required><button class="jalali-date-button" type="button" data-open-jalali="taxPolicyEffectiveDate" aria-label="انتخاب تاریخ شروع سیاست کالا"><?= ui_icon('calendar') ?></button></div></div>
        <div class="form-group"><label>زمان شروع</label><input class="form-control" type="time" name="policy_effective_time" value="<?= e($nowTime) ?>" step="60" required></div>
        <div class="form-group actions"><button class="btn btn-primary" type="submit">ثبت سیاست جدید</button></div>
      </form>
    </div>
    <div class="financial-list">
      <?php if (!$policyRows): ?><div class="empty-state">هنوز برای هیچ کالا سیاست اختصاصی ثبت نشده است.</div><?php endif; ?>
      <?php foreach ($policyRows as $row): $rate=(string)$row['policy']==='custom_rate' ? tax_rate_bps_label((int)$row['custom_rate_bps']) : ''; ?>
        <article class="financial-row"><div class="financial-row-main"><strong><?= e((string)$row['item_name']) ?></strong><span><?= e(tax_policy_label((string)$row['policy'])) ?><?= $rate!==''?' · '.e($rate):'' ?> · از <?= e(format_jalali_human_datetime((string)$row['effective_from'])) ?></span><small><?= e((string)($row['actor_name'] ?: 'سامانه')) ?></small></div></article>
      <?php endforeach; ?>
    </div>
  </section>
</div>
<?php panel_footer(); ?>
