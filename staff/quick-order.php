<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_any_capability(['orders_floor','cashier_accounts']);

$user = current_user();
$mode = (string)($_GET['mode'] ?? 'normal');
if (!in_array($mode, ['normal','late_accounting'], true)) $mode = 'normal';
if ($mode === 'late_accounting' && !user_has_capability('cashier_accounts', $user)) deny_access_and_return();
if ($mode === 'normal' && !user_has_capability('orders_floor', $user)) deny_access_and_return();
$base = app_base_url();
$tableId = max(0, (int)($_GET['table_id'] ?? 0));
if ($mode === 'late_accounting' && $tableId < 1) { http_response_code(400); exit('میز برای ثبت قلم جاافتاده مشخص نیست.'); }
$origin = (string)($_GET['origin'] ?? '');
if (!in_array($origin, ['global','table-panel'], true)) $origin = $tableId > 0 ? 'table-panel' : 'global';
$return = trim((string)($_GET['return'] ?? ''));
if ($return === '' || str_contains($return, "\n") || str_contains($return, "\r") || preg_match('~^(?:https?:)?//~i', $return)) {
    $return = $base . '/operator/index.php';
}
if (!str_starts_with($return, '/')) {
    $return = $base . '/operator/index.php';
}
$font = ui_font();
$primary = valid_hex_color(setting('primary_color', '#365b4c'), '#365b4c');
$accent = valid_hex_color(setting('accent_color', '#b85c38'), '#b85c38');
$background = valid_hex_color(setting('background_color', '#f7f3ec'), '#f7f3ec');
$displayName = trim((string)($user['display_name'] ?? '')) ?: 'عضو تیم';
$successReturn = $base . '/operator/index.php?work=tables#tables';
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <title><?= $mode === 'late_accounting' ? 'افزودن قلم جاافتاده' : 'ثبت سفارش' ?> | <?= e(setting('cafe_name', 'سکنا')) ?></title>
  <?= favicon_head_tags() ?>
  <?= ui_font_head($font) ?>
  <link rel="stylesheet" href="<?= e(asset('assets/css/tokens.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('assets/css/panel-components.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('assets/css/quick-order.css')) ?>">
  <style>:root{--font-ui:<?= ui_font_family($font) ?>;--primary:<?= e($primary) ?>;--primary-dark:<?= e($primary) ?>;--accent:<?= e($accent) ?>;--app-bg:<?= e($background) ?>}</style>
</head>
<body class="quick-order-page font-<?= e($font) ?>">
<a class="skip-link" href="#quickOrderMain">رفتن به محتوای اصلی</a>
<div class="quick-order-page-shell" id="quickOrderPage" data-initial-table="<?= $tableId ?>" data-return-url="<?= e($return) ?>" data-success-url="<?= e($successReturn) ?>" data-origin="<?= e($origin) ?>" data-mode="<?= e($mode) ?>">
  <header class="quick-order-page-header">
    <a class="quick-order-back" id="quickOrderBack" href="<?= e($return) ?>" aria-label="بازگشت"><?= ui_icon('chevron-right') ?></a>
    <div class="quick-order-page-heading">
      <strong id="quickOrderPageTitle"><?= $mode === 'late_accounting' ? 'افزودن قلم جاافتاده' : 'ثبت سفارش' ?></strong>
      <span class="quick-order-table-context hidden" id="quickOrderHeaderContext"><b id="quickOrderSelectedTableName">—</b><small id="quickOrderSelectedTableMeta"></small></span>
    </div>
    <button class="quick-order-change-table hidden" id="quickOrderChangeTable" type="button">تغییر میز</button>
  </header>

  <main class="quick-order-page-main" id="quickOrderMain">
    <section class="quick-order-table-view" id="quickOrderTableStage" aria-labelledby="quickOrderTableTitle">
      <header class="quick-order-table-stage-head"><div><h1 id="quickOrderTableTitle">انتخاب میز</h1><p>میز موردنظر را انتخاب کنید.</p></div><span id="quickOrderTableCount"></span></header>
      <div class="quick-order-table-groups" id="quickOrderTableGroups"><div class="empty-state">در حال دریافت میزها…</div></div>
    </section>

    <section class="quick-order-workspace hidden" id="quickOrderWorkspace">
      <input id="quickOrderTable" type="hidden" value="">
      <?php if($mode === 'late_accounting'): ?><div class="quick-order-late-accounting-note" role="status"><strong>فقط برای قلم تحویل‌شده</strong><span>این ثبت فقط حساب و موجودی را اصلاح می‌کند و دوباره به بار یا آشپزخانه ارسال نمی‌شود.</span></div><?php endif; ?>
      <button class="quick-order-mobile-pending-banner hidden" id="quickOrderMobilePendingBanner" type="button"><span><strong id="quickOrderMobilePendingCount">سفارش مهمان منتظر است</strong><small>پیش از ثبت سفارش جدید بررسی شود.</small></span><b>بررسی سفارش</b></button>
      <div class="quick-order-uncertain hidden" id="quickOrderUncertainNotice" role="status" aria-live="polite"><strong>نتیجه ثبت قبلی هنوز مشخص نیست.</strong><span>سبد تا تعیین نتیجه ثابت می‌ماند؛ «بررسی و تلاش دوباره» همان درخواست را با همان شناسه پیگیری می‌کند.</span></div>
      <nav class="quick-order-menu-switcher hidden" id="quickOrderMenus" aria-label="انتخاب منوی فروش"></nav>
      <div class="quick-order-layout">
        <nav class="quick-order-category-pane" aria-label="دسته‌بندی‌های منو">
          <div class="quick-order-pane-title"><strong>دسته‌بندی‌ها</strong><button class="quick-order-category-search" id="quickOrderCategorySearch" type="button" aria-label="جست‌وجوی سریع آیتم"><?= ui_icon('search') ?></button></div>
          <div class="quick-order-categories" id="quickOrderCategories"></div>
        </nav>

        <section class="quick-order-catalog-pane" aria-labelledby="quickOrderCategoryTitle">
          <div class="quick-order-catalog-head">
            <button class="quick-order-category-back" id="quickOrderCategoryBack" type="button"><?= ui_icon('chevron-right') ?><span>دسته‌بندی‌ها</span></button>
            <strong id="quickOrderCategoryTitle">دسته‌بندی‌ها</strong>
            <button class="quick-order-search-toggle" id="quickOrderSearchToggle" type="button" aria-label="جست‌وجوی آیتم" aria-expanded="false"><?= ui_icon('search') ?></button>
          </div>
          <label class="quick-order-search is-collapsed" id="quickOrderSearchWrap">
            <span class="sr-only">جست‌وجوی نام آیتم</span>
            <input class="form-control" id="quickOrderSearch" type="search" inputmode="search" enterkeyhint="search" data-keyboard-dismiss-on-enter dir="rtl" placeholder="جست‌وجوی نام آیتم" autocomplete="off">
            <button class="hidden" id="quickOrderSearchClear" type="button" aria-label="پاک‌کردن جست‌وجو"><?= ui_icon('close') ?></button>
          </label>
          <div class="quick-order-items" id="quickOrderItems"><div class="empty-state">در حال دریافت اقلام…</div></div>
        </section>

        <aside class="quick-order-cart" id="quickOrderCart" aria-labelledby="quickOrderCartTitle" aria-hidden="false">
          <header class="quick-order-cart-head">
            <div><small>سبد سفارش</small><h2 id="quickOrderCartTitle">هنوز آیتمی انتخاب نشده</h2></div>
            <div class="quick-order-cart-head-actions"><button class="quick-order-takeaway-tool hidden" id="quickOrderTakeawayTool" type="button" aria-pressed="false">بیرون‌بر</button><details class="quick-order-cart-more"><summary aria-label="گزینه‌های بیشتر سبد" title="گزینه‌های بیشتر">⋯</summary><div class="quick-order-cart-more-menu"><button type="button" data-qo-global-note><?= ui_icon('message') ?><span>افزودن یادداشت کلی</span></button><button type="button" data-qo-clear-proxy><?= ui_icon('trash') ?><span>پاک‌کردن سبد</span></button></div></details><button class="quick-order-clear-icon" id="quickOrderClear" type="button" data-qo-clear-action="clear" aria-label="پاک‌کردن سبد" title="پاک‌کردن سبد"><?= ui_icon('trash') ?></button><button class="quick-order-cart-close" id="quickOrderCartClose" type="button" aria-label="بستن سبد"><?= ui_icon('close') ?></button></div>
          </header>
          <div class="quick-order-cart-body" id="quickOrderCartBody">
            <section class="quick-order-pending hidden" id="quickOrderPending" aria-live="polite"></section>
            <details class="quick-order-current hidden" id="quickOrderCurrentAccount"><summary class="quick-order-section-head"><div><small>حساب فعلی میز</small><strong id="quickOrderCurrentTotal">۰</strong></div><span><b id="quickOrderCurrentCount"></b><i>مشاهده</i></span></summary><div class="quick-order-current-lines" id="quickOrderCurrentLines"></div></details>
            <section class="quick-order-takeaway-mode hidden" id="quickOrderTakeawayMode" aria-label="انتخاب موارد بیرون‌بر"><strong>موارد بیرون‌بر</strong><div><button class="btn btn-primary btn-sm" id="quickOrderTakeawayDone" type="button">تأیید</button><button class="btn btn-light btn-sm" id="quickOrderTakeawayAll" type="button">همه بیرون‌بر</button></div></section><div class="quick-order-cart-lines" id="quickOrderCartLines"><div class="empty-state">با لمس یک آیتم، اینجا اضافه می‌شود.</div></div>
            <div class="quick-order-note"><button class="quick-order-note-toggle" id="quickOrderNoteToggle" type="button" aria-expanded="false"><?= ui_icon('message') ?><span>یادداشت کلی</span></button><label class="hidden" id="quickOrderNoteWrap"><span class="sr-only">یادداشت کلی سفارش</span><textarea class="form-control" id="quickOrderNote" rows="2" maxlength="500" placeholder="مثلاً نوشیدنی‌ها بعد از غذا"></textarea></label></div>
          </div>
          <footer class="quick-order-cart-footer">
            <div class="quick-order-totals" aria-label="خلاصه مالی سفارش"><div class="is-final"><span>جمع سفارش جدید <small class="quick-order-fulfillment-summary hidden" id="quickOrderFulfillmentSummary"></small></span><strong id="quickOrderTotal">۰</strong></div><div id="quickOrderPreviousRow" class="hidden"><span>مانده فعلی حساب</span><strong id="quickOrderPreviousTotal">۰</strong></div><div id="quickOrderProjectedRow" class="hidden"><span>جمع حساب پس از ثبت</span><strong id="quickOrderProjectedTotal">۰</strong></div></div>
            <button class="btn btn-primary quick-order-submit" id="quickOrderSubmit" type="button" disabled>یک آیتم انتخاب کنید</button>
          </footer>
        </aside>
      </div>

      <button class="quick-order-cart-backdrop hidden" id="quickOrderCartBackdrop" type="button" aria-label="بستن سبد"></button>
      <button class="quick-order-mobile-cartbar" id="quickOrderMobileCartBar" type="button" disabled><span><strong id="quickOrderMobileCartCount">هنوز آیتمی انتخاب نشده</strong><small><span id="quickOrderMobileCartTotal">۰</span> تومان</small></span><b>مشاهده سبد</b></button>
    </section>
  </main>
</div>

<div class="panel-confirm-layer hidden" id="panelConfirmLayer" role="dialog" aria-modal="true" aria-labelledby="panelConfirmTitle" aria-describedby="panelConfirmMessage" aria-hidden="true">
  <div class="panel-confirm-backdrop" data-panel-confirm-cancel aria-hidden="true"></div>
  <section class="panel-confirm-card"><div class="panel-confirm-icon" aria-hidden="true"><span data-panel-confirm-icon-info><?= ui_icon('info') ?></span><span class="hidden" data-panel-confirm-icon-danger><?= ui_icon('warning') ?></span></div><div class="panel-confirm-copy"><h2 id="panelConfirmTitle">تأیید</h2><p id="panelConfirmMessage"></p></div><div class="panel-confirm-actions"><button class="btn btn-primary" type="button" data-panel-confirm-ok>تأیید</button><button class="btn btn-light" type="button" data-panel-confirm-cancel>انصراف</button></div></section>
</div>
<div id="panelToast" class="panel-toast hidden" role="status" aria-live="polite"></div>
<script>
window.STAFF_QUICK_ORDER_API=<?= json_script(asset('staff/api_quick_order.php')) ?>;
window.OPERATOR_STATUS_API=<?= json_script(asset('operator/api_status.php')) ?>;
window.SOKNA_ICON_SPRITE=<?= json_script(asset('assets/icons/ui-sprite.svg')) ?>;
window.QUICK_ORDER_USER_KEY=<?= json_script((string)($user['id'] ?? '0')) ?>;
</script>
<script>window.SOKNA_PUSH_DRAIN_URL=<?= json_script(asset('api/push_drain.php')) ?>;window.SOKNA_PRINT_BRIDGE_CAPABILITY_URL=<?= json_script(asset('api/print_bridge_capability.php')) ?>;</script><script defer src="<?= e(asset('assets/js/push-runtime.js')) ?>"></script><script defer src="<?= e(asset('assets/js/panel-core.js')) ?>"></script>
<script defer src="<?= e(asset('assets/js/fulfillment-policy.js')) ?>"></script><script defer src="<?= e(asset('assets/js/staff-quick-order.js')) ?>"></script>
</body>
</html>
