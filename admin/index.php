<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_login(['admin']);
require dirname(__DIR__) . '/includes/panel_layout.php';
require_once dirname(__DIR__) . '/includes/maintenance.php';

maintenance_offserver_reminder_bootstrap();
$backupExportStatus = maintenance_offserver_export_status();

$pdo = db();
$businessToday = business_current_date();
$scalar = static function (string $sql, array $params=[]) use ($pdo): int {
    try { $stmt=$pdo->prepare($sql);$stmt->execute($params);return (int)$stmt->fetchColumn(); } catch (Throwable $e) { error_log('dashboard scalar: '.$e->getMessage()); return 0; }
};
$stats = [
    'attention_orders' => $scalar("SELECT COUNT(*) FROM orders WHERE status IN ('pending_approval','new')"),
    'open_orders' => $scalar("SELECT COUNT(*) FROM orders WHERE status IN ('pending_approval','new','accounted')"),
    'today_orders' => $scalar('SELECT COUNT(*) FROM orders WHERE business_date=?',[$businessToday]),
    'active_tables' => $scalar('SELECT COUNT(*) FROM cafe_tables WHERE active = 1'),
    'occupied_tables' => $scalar("SELECT COUNT(*) FROM table_sessions WHERE status IN('active','pending')"),
    'waiter_calls' => $scalar("SELECT COUNT(*) FROM waiter_calls WHERE status IN('new','accepted')"),
];
$sales = ['invoice_count'=>0,'net_total'=>0,'discount'=>0,'direct'=>0,'accommodation'=>0,'subscriber'=>0];
try {
    $salesStmt=$pdo->prepare("SELECT
      COALESCE(SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END),0) invoice_count,
      COALESCE(SUM(CASE WHEN status='completed' THEN total WHEN status='reversal' THEN -total ELSE 0 END),0) net_total,
      COALESCE(SUM(CASE WHEN status='completed' THEN discount WHEN status='reversal' THEN -discount ELSE 0 END),0) discount,
      COALESCE(SUM(CASE WHEN status='completed' AND destination='direct' THEN total WHEN status='reversal' AND destination='direct' THEN -total ELSE 0 END),0) direct,
      COALESCE(SUM(CASE WHEN status='completed' AND destination='accommodation' THEN total WHEN status='reversal' AND destination='accommodation' THEN -total ELSE 0 END),0) accommodation,
      COALESCE(SUM(CASE WHEN status='completed' AND destination='subscriber' THEN total WHEN status='reversal' AND destination='subscriber' THEN -total ELSE 0 END),0) subscriber
      FROM settlement_records WHERE business_date=?");
    $salesStmt->execute([$businessToday]);$salesRow=$salesStmt->fetch();
    if (is_array($salesRow)) $sales = array_merge($sales, $salesRow);
} catch (Throwable $e) { error_log('dashboard sales: '.$e->getMessage()); }
$recent = [];
try {
    $recent = $pdo->query('SELECT o.*, t.name AS table_name FROM orders o JOIN cafe_tables t ON t.id=o.table_id ORDER BY o.created_at DESC LIMIT 5')->fetchAll();
} catch (Throwable $e) { error_log('dashboard recent: '.$e->getMessage()); }
$acceptanceStates = order_acceptance_states();
$orderingEnabled = (bool)$acceptanceStates['cafe'];
$waiterEnabled = setting_bool('waiter_call_enabled', true);
$busyStations = array_keys(array_filter(station_busy_states()));
$attentionTotal = $stats['attention_orders'] + $stats['waiter_calls'];
$freeTables = max(0, $stats['active_tables'] - $stats['occupied_tables']);
$todayJalali=jalali_date_input($businessToday);
panel_header('خلاصه مدیریت', 'dashboard');
?>
<?php if (!$orderingEnabled): ?><div class="alert alert-warning"><strong>سفارش‌گیری مهمان متوقف است.</strong> منو دیده می‌شود، اما سفارش تازه ثبت نمی‌شود. تغییر وضعیت فقط از «کار روزانه» انجام می‌شود.</div><?php endif; ?>
<?php if (!empty($backupExportStatus['due'])): ?><div class="alert alert-warning dashboard-backup-reminder"><div><strong>نسخه خارج از سرور به‌روز نیست.</strong><span>بیش از ۷ روز از آخرین دانلود پشتیبان گذشته است.</span></div><a class="btn btn-sm btn-light" href="maintenance.php">دانلود پشتیبان</a></div><?php endif; ?>

<?php if($attentionTotal>0): ?>
<section class="dashboard-priority has-attention">
  <div><small>اولویت همین لحظه</small><h2><?= fa_digits($attentionTotal) ?> مورد نیازمند اقدام</h2><p>سفارش‌های تازه و فراخوان‌های مهمان را پیش از گزارش‌ها رسیدگی کنید.</p></div>
  <a class="btn btn-primary" href="<?= e(asset('operator/index.php#attention')) ?>"><?= ui_icon('operations') ?> کار روزانه</a>
</section>
<?php endif; ?>

<section class="dashboard-kpi-grid" aria-label="خلاصه امروز">
  <article class="dashboard-kpi is-sales"><small>فروش خالص روز کاری</small><strong><?= e(toman((int)$sales['net_total'])) ?></strong><span><?= fa_digits((int)$sales['invoice_count']) ?> فاکتور نهایی</span></article>
  <a class="dashboard-kpi is-actionable" href="<?= e(asset('operator/index.php?work=tables&table_filter=open#tables')) ?>"><small>میزهای باز</small><strong><?= fa_digits($stats['occupied_tables']) ?></strong><span><?= fa_digits($freeTables) ?> میز آزاد از <?= fa_digits($stats['active_tables']) ?></span></a>
  <a class="dashboard-kpi is-actionable" href="<?= e(asset('operator/index.php?work=attention&attention_filter=orders#attention')) ?>"><small>سفارش منتظر تأیید</small><strong><?= fa_digits($stats['attention_orders']) ?></strong><span><?= fa_digits($stats['today_orders']) ?> سفارش از آغاز روز کاری</span></a>
  <a class="dashboard-kpi is-actionable" href="<?= e(asset('operator/index.php?work=attention&attention_filter=calls#attention')) ?>"><small>فراخوان مهمان</small><strong><?= fa_digits($stats['waiter_calls']) ?></strong><span><?= $waiterEnabled?'فراخوان فعال است':'فراخوان غیرفعال است' ?></span></a>
</section>

<div class="dashboard-workspace">
  <section class="card dashboard-finance-card"><div class="card-head"><div><h2>مقصدهای تسویه روز کاری</h2><small>مبالغ خالص پس از کسر اسناد برگشتی</small></div><a class="btn btn-sm btn-light" href="<?= e(asset('admin/invoices.php')) ?>">آرشیو فاکتورها</a></div><div class="card-body dashboard-destination-list">
    <a href="<?= e(asset('admin/invoices.php?destination=direct&from='.rawurlencode($todayJalali).'&to='.rawurlencode($todayJalali))) ?>"><span>تسویه مستقیم</span><strong><?= e(toman((int)$sales['direct'])) ?></strong></a>
    <a href="<?= e(asset('admin/invoices.php?destination=accommodation&from='.rawurlencode($todayJalali).'&to='.rawurlencode($todayJalali))) ?>"><span>حساب اقامتگاه</span><strong><?= e(toman((int)$sales['accommodation'])) ?></strong></a>
    <a href="<?= e(asset('admin/invoices.php?destination=subscriber&from='.rawurlencode($todayJalali).'&to='.rawurlencode($todayJalali))) ?>"><span>حساب مشترک</span><strong><?= e(toman((int)$sales['subscriber'])) ?></strong></a>
    <div class="is-muted"><span>تخفیف ثبت‌شده</span><strong><?= e(toman((int)$sales['discount'])) ?></strong></div>
  </div></section>

  <section class="card dashboard-system-card"><div class="card-head"><div><h2>وضعیت عملیات</h2><small>وضعیت‌های مؤثر بر کار روزانه و دسترسی به ابزارهای مرتبط</small></div></div><div class="card-body dashboard-system-list">
    <a href="<?= e(asset('operator/index.php#controls')) ?>"><span class="system-dot <?= $orderingEnabled?'is-ok':'is-warning' ?>"></span><div><strong><?= $orderingEnabled?'سفارش‌گیری فعال':'سفارش‌گیری متوقف' ?></strong><small>کل کافه</small></div><?= ui_icon('chevron-left') ?></a>
    <a href="<?= e(asset('operator/index.php#controls')) ?>"><span class="system-dot <?= $busyStations?'is-warning':'is-ok' ?>"></span><div><strong><?= $busyStations?fa_digits(count($busyStations)).' بخش شلوغ':'آماده‌سازی عادی' ?></strong><small>آشپزخانه و بار</small></div><?= ui_icon('chevron-left') ?></a>
    <a href="<?= e(asset('admin/printing.php?tab=status')) ?>"><span class="system-dot is-info"></span><div><strong>چاپ و پرینترها</strong><small>مشاهده صف و وضعیت اتصال</small></div><?= ui_icon('chevron-left') ?></a>
  </div></section>
</div>

<div class="page-grid dashboard-main-grid">
  <section class="card dashboard-recent-orders"><div class="card-head"><div><h2>آخرین سفارش‌ها</h2><small>مرور کوتاه؛ فقط وضعیت‌های نیازمند توجه برجسته می‌شوند.</small></div><a class="btn btn-sm btn-outline" href="<?= e(asset('operator/index.php#attention')) ?>">صف سفارش‌ها</a></div>
    <div class="dashboard-recent-list">
    <?php if (!$recent): ?><div class="empty-state">هنوز سفارشی ثبت نشده است.</div><?php endif; ?>
    <?php foreach ($recent as $order): $isException=in_array((string)$order['status'],['pending_approval','new','cancelled','rejected'],true); ?>
      <a class="dashboard-recent-row" href="<?= e(asset('operator/index.php?work=tables#tables')) ?>">
        <span class="dashboard-recent-main"><strong><?= e(order_display_label($order)) ?></strong><small>میز <?= e(fa_digits((string)$order['table_name'])) ?> · <?= e(time_ago($order['created_at'])) ?></small></span>
        <span class="dashboard-recent-amount"><?= e(toman((int)$order['total_amount'])) ?></span>
        <?php if($isException): ?><span class="badge badge-<?= e((string)$order['status']) ?> dashboard-recent-exception"><?= e(order_status_label((string)$order['status'])) ?></span><?php endif; ?>
      </a>
    <?php endforeach; ?>
    </div>
  </section>
  <section class="card management-tasks-card"><div class="card-head"><div><h3>میانبرهای مدیریتی</h3><small>فقط کارهای پرتکرار</small></div></div><div class="card-body"><div class="quick-actions">
    <a class="quick-action is-primary" href="item_form.php"><span><?= ui_icon('plus') ?></span><div><strong>افزودن آیتم</strong><small>نام، قیمت و بخش آماده‌سازی</small></div><?= ui_icon('chevron-left') ?></a>
    <a class="quick-action" href="items.php"><span><?= ui_icon('coffee') ?></span><div><strong>قیمت و سفارش‌پذیری</strong><small>مدیریت منوی قابل سفارش</small></div><?= ui_icon('chevron-left') ?></a>
    <a class="quick-action" href="tables.php"><span><?= ui_icon('table') ?></span><div><strong>میزها و QR</strong><small>فضاها، میزها و کدهای مهمان</small></div><?= ui_icon('chevron-left') ?></a>
    <a class="quick-action" href="maintenance.php"><span><?= ui_icon('backup') ?></span><div><strong>پشتیبان‌گیری</strong><small>قبل از هر تغییر مهم</small></div><?= ui_icon('chevron-left') ?></a>
  </div></div></section>
</div>
<?php panel_footer(); ?>
