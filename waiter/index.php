<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_any_capability(['preparation','shift_supervision']);
require dirname(__DIR__) . '/includes/panel_layout.php';
$user=current_user();
$assignedAreas=user_preparation_areas((int)$user['id']);
$canPrepare=!is_admin() && user_has_capability('preparation',$user) && !empty($assignedAreas);
$monitorOnly=!$canPrepare;
panel_header('آماده‌سازی','waiter');
?>
<section class="staff-queue-toolbar" aria-labelledby="staffQueueTitle">
  <div>
    <span class="dashboard-kicker">آشپزخانه و بار</span>
    <h2 id="staffQueueTitle"><?= $monitorOnly ? 'پایش آماده‌سازی' : 'صف آماده‌سازی' ?></h2>
  </div>
  <div class="staff-queue-utilities">
    <?php if(user_can_report_supply_needs($user)): ?><a class="btn btn-light btn-sm" href="<?= e(asset('operator/supply-needs.php')) ?>"><?= ui_icon('plus') ?> درخواست خرید</a><?php endif; ?>
    <div class="waiter-connection" id="waiterConnection"><span></span><strong>در حال اتصال…</strong><small id="waiterLastSync"></small></div>
    <button class="btn btn-light btn-sm" id="waiterSound" type="button">روشن‌کردن صدا</button>
  </div>
</section>



<section class="staff-action-section">
  <div class="staff-action-head"><div><h2><?= $monitorOnly ? 'نیازمند دریافت' : 'نیازمند دریافت' ?></h2><p><?= $monitorOnly ? 'سفارش‌هایی که هنوز توسط آشپزخانه یا بار دریافت نشده‌اند اینجا نمایش داده می‌شوند.' : 'برای پذیرفتن رسیدگی به سفارش، «گرفتم» را بزن.' ?></p></div><button class="btn btn-light btn-sm" type="button" id="staffRefreshQueue"><?= ui_icon('refresh') ?> تازه‌سازی</button></div>
  <div class="staff-action-queue" id="staffActionQueue"><div class="card empty-state">در حال دریافت سفارش‌های آماده‌سازی…</div></div>
</section>

<?php if($canPrepare): ?><section class="staff-action-section staff-today-section"><div class="staff-action-head"><div><h2>سفارش‌های امروز</h2><p>سفارش‌های بخش‌های مجاز شما، همراه با نام و زمان دریافت، برای مراجعه دوباره باقی می‌مانند.</p></div></div><div class="staff-action-queue" id="staffTodayOrders"><div class="card empty-state">در حال دریافت سفارش‌های امروز…</div></div></section><?php endif; ?>


<script>
window.WAITER_FEED_API=<?= json_script(asset('waiter/api_feed.php?mode=preparation')) ?>;
window.WAITER_ACTION_API=<?= json_script(asset('waiter/api_action.php')) ?>;
window.WAITER_STATUS_API=<?= json_script(asset('operator/api_status.php')) ?>;
window.CAFE_CURRENCY=<?= json_script('تومان') ?>;
window.WAITER_CAN_CLAIM=<?= $canPrepare?'true':'false' ?>;
window.WAITER_MONITOR_ONLY=<?= $monitorOnly?'true':'false' ?>;
</script>
<?php panel_footer('<script defer src="'.e(asset('assets/js/waiter.js')).'"></script>'); ?>
