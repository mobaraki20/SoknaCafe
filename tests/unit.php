<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/functions.php';
require dirname(__DIR__) . '/includes/push.php';
require dirname(__DIR__) . '/includes/maintenance.php';
require dirname(__DIR__) . '/includes/settlement.php';
define('SOKNA_UPDATER_ROOT', dirname(__DIR__));
define('SOKNA_UPDATER_ENGINE_VERSION', '1.5.3');
define('SOKNA_UPDATER_ENGINE_DIR', dirname(__DIR__) . '/includes/updater_engine/1.5.3');
require SOKNA_UPDATER_ENGINE_DIR . '/runtime.php';

$failures = [];
$checks = 0;

function check(bool $condition, string $message): void
{
    global $failures, $checks;
    $checks++;
    if (!$condition) $failures[] = $message;
}

check(csv_safe_cell('=SUM(A1:A2)') === "'=SUM(A1:A2)", 'CSV formula beginning with = must be escaped.');
check(csv_safe_cell('  +1') === "'  +1", 'CSV formula after leading whitespace must be escaped.');
check(csv_safe_cell('قهوه') === 'قهوه', 'Normal Persian text must remain unchanged.');
check(invoice_discount_amount(1000000, 'percent', 10) === 100000, 'Percent invoice discount is incorrect.');
check(invoice_discount_amount(1000000, 'fixed', 150000) === 150000, 'Fixed invoice discount is incorrect.');
check(invoice_discount_amount(100000, 'fixed', 150000) === 100000, 'Fixed invoice discount must not exceed subtotal.');
check(invoice_discount_amount(100000, null, 50000) === 0, 'Unknown invoice discount type must be ignored.');
check(settlement_proportional_discount_target(1000, 100, 300, false) === 30, 'Itemized settlement discount target must be proportional.');
check(settlement_proportional_discount_target(1000, 100, 500, false) === 50, 'Itemized settlement cumulative discount must be path independent.');
check(settlement_proportional_discount_target(1000, 100, 999, true) === 100, 'Final itemized payment must absorb all remaining discount rounding.');
check(settlement_proportional_discount_target(1500, 150, 500, false) === 50, 'Adding a late-accounted item must not rewrite discount already earned by an earlier receipt.');
check((150 - 50) === 100, 'Expanded discounted account must leave only the new remaining discount for later receipts.');
$allocatedLines = settlement_allocate_line_discounts([['gross_amount'=>300],['gross_amount'=>200]], 50);
check(array_sum(array_column($allocatedLines,'discount_amount')) === 50 && array_sum(array_column($allocatedLines,'net_amount')) === 450, 'Itemized line discounts must reconcile exactly to receipt totals.');
$fingerprintA=settlement_request_fingerprint(12,'direct','itemized',[11=>1,15=>2]);
$fingerprintB=settlement_request_fingerprint(12,'direct','itemized',[15=>2,11=>1]);
check(hash_equals($fingerprintA,$fingerprintB), 'Itemized request fingerprint must be stable regardless of selection order.');
check(str_starts_with(ui_font_family('vazirmatn'), '"Vazirmatn"'), 'Vazirmatn must remain the first UI font even before the local fetch completes.');

$transitions = order_status_transitions();
check($transitions['new'] === ['accounted', 'cancelled'], 'New order must need only one confirmation.');
check($transitions['accounted'] === ['completed'], 'Confirmed orders must leave cancellation to audited bill correction, not generic status changes.');
check($transitions['completed'] === [], 'Completed order must be terminal.');
check(!in_array('new', allowed_order_statuses_from('completed'), true), 'Completed order must not return to new.');
check(allowed_order_statuses_from('pending_approval') === ['accounted','cancelled'], 'Pending approval transitions must remain narrow and explicit.');
check(guest_order_status_is_mutable('pending_approval'), 'First unconfirmed guest order must remain editable.');
check(guest_order_status_is_mutable('new'), 'A later unconfirmed order on an active table must remain editable independently.');
check(!guest_order_status_is_mutable('accounted') && !guest_order_status_is_mutable('completed'), 'Confirmed guest orders must not remain editable.');

check(en_digits('۱۲۳٤') === '1234', 'Persian and Arabic digits must normalize.');
check(parse_toman_amount_text('۱٬۲۰۰٬۰۰۰ تومان') === 1200000, 'Persian event price text must normalize to a Toman amount.');
check(event_display_fee_amount(['fee_amount'=>'۱٬۲۰۰٬۰۰۰','admission_text'=>'']) === 1200000, 'Explicit Persian event fee must display as a numeric amount.');
check(event_display_fee_amount(['fee_amount'=>null,'admission_text'=>'1200000']) === 1200000, 'Legacy numeric admission text must remain visible as the event fee.');
check(event_display_admission_text(['fee_amount'=>null,'admission_text'=>'۱۲۰۰۰۰۰ تومان']) === '', 'Legacy numeric admission text must not be repeated as an admission condition.');
check(event_display_admission_text(['fee_amount'=>1200000,'admission_text'=>'۱۲۰۰۰۰۰']) === '', 'Numeric admission text must never be duplicated beside an explicit fee.');
check(event_display_admission_text(['fee_amount'=>1200000,'admission_text'=>'هزینه برای هر نفر است']) === 'هزینه برای هر نفر است', 'Real event attendance notes must remain visible.');
check(table_display_token('تراس', 24, false) === '۲۴', 'Canonical table number must win over the human table label.');
if (function_exists('mb_strtolower')) {
    check(bool_from_mixed('فعال') === true, 'Persian true value must parse.');
    check(bool_from_mixed('0', true) === false, 'Zero must parse as false.');
}

[$jy,$jm,$jd] = gregorian_to_jalali(2026, 7, 25);
check([$jy,$jm,$jd] === [1405,5,3], 'Gregorian to Jalali conversion is incorrect.');
check(str_contains(format_jalali_compact('2026-07-25 18:45:00'), '۱۴۰۵/۰۵/۰۳'), 'Compact Jalali date is incorrect.');
check(jalali_to_gregorian(1405,5,3) === [2026,7,25], 'Jalali to Gregorian conversion is incorrect.');
check(parse_jalali_date('۱۴۰۵/۰۵/۰۳') === '2026-07-25', 'Jalali input parsing is incorrect.');
check(parse_jalali_date('1405/13/01') === null, 'Invalid Jalali month must be rejected.');

$period1405 = financial_period_bounds_for_date('2026-08-05');
check($period1405['jalali_year'] === 1405 && $period1405['start_date'] === '2026-03-21' && $period1405['end_date'] === '2027-03-20', 'Financial period 1405 bounds are incorrect.');
$periodBoundary = financial_period_bounds_for_date('2027-03-20');
check($periodBoundary['jalali_year'] === 1405, 'Last day of financial period 1405 must remain in 1405.');
$periodNext = financial_period_bounds_for_date('2027-03-21');
check($periodNext['jalali_year'] === 1406, 'First day of financial period 1406 must open 1406.');

$orderPayload = normalize_order_request_payload([
    'table_token' => str_repeat('t', 24),
    'session_token' => '',
    'device_token' => str_repeat('d', 24),
    'client_token' => str_repeat('c', 24),
    'customer_note' => '  کنار پنجره  ',
    'items' => [['id'=>12, 'quantity'=>3, 'note'=>'کم‌شیرین', 'unit_price'=>185000]],
]);
check($orderPayload['customer_note'] === 'کنار پنجره', 'Canonical customer note must be normalized.');
check($orderPayload['items'][0]['quantity'] === 3, 'Canonical order quantity must remain unchanged.');
check($orderPayload['items'][0]['expected_price'] === 185000, 'Client price snapshot must be preserved for comparison.');
$duplicateLineRejected = false;
try {
    normalize_order_request_payload([
        'table_token' => str_repeat('t', 24),
        'client_token' => str_repeat('x', 24),
        'items' => [
            ['id'=>1, 'quantity'=>1, 'unit_price'=>10],
            ['id'=>1, 'quantity'=>1, 'unit_price'=>10],
        ],
    ]);
} catch (InvalidArgumentException) {
    $duplicateLineRejected = true;
}
check($duplicateLineRejected, 'Duplicate order lines must be rejected.');

$staffRows = normalize_staff_quick_order_rows([
    ['id'=>9,'quantity'=>2,'note'=>'بدون شکر','fulfillment_mode'=>'dine_in'],
    ['id'=>9,'quantity'=>1,'fulfillment_mode'=>'takeaway'],
    ['id'=>3,'quantity'=>1,'fulfillment_mode'=>'dine_in'],
]);
check(count($staffRows) === 3 && $staffRows[0]['note'] === 'بدون شکر', 'Staff quick-order rows must preserve current line-level fulfillment semantics.');
$duplicateStaffModeRejected=false;
try { normalize_staff_quick_order_rows([['id'=>9,'quantity'=>1],['id'=>9,'quantity'=>2]]); } catch (InvalidArgumentException) { $duplicateStaffModeRejected=true; }
check($duplicateStaffModeRejected, 'Staff quick order must reject duplicate item/mode rows.');
check(normalize_staff_order_request_token('12345678-1234-1234') === 'staff-12345678-1234-1234', 'Staff order request token must be namespaced.');
$staffLimitRejected=false;
try { normalize_staff_quick_order_rows([['id'=>1,'quantity'=>51]]); } catch (InvalidArgumentException) { $staffLimitRejected=true; }
check($staffLimitRejected, 'Staff quick order must reject unsafe quantities.');

$responsiveCss = file_get_contents(dirname(__DIR__) . '/assets/css/responsive.css');
check(is_string($responsiveCss) && str_contains($responsiveCss, '.mobile-card-table'), 'Critical mobile card-table styles are missing.');
check(is_string($responsiveCss) && str_contains($responsiveCss, 'min-height:44px'), 'Mobile touch targets must preserve a 44px minimum.');

check(maintenance_safe_backup_name('sokna-backup-20260725-120000-a1b2c3d4.tar.gz'), 'Valid backup filename must be accepted.');
check(!maintenance_safe_backup_name('cafe-backup-full-20260725-120000-a1b2c3d4.tar.gz'), 'Removed legacy full-backup filename must not be accepted.');
check(!maintenance_safe_backup_name('../config.php'), 'Unsafe backup filename must be rejected.');
check(maintenance_archive_entry_is_safe('app/admin/index.php') && !maintenance_archive_entry_is_safe('../config.php'), 'Archive path validation is incorrect.');
check(count(maintenance_sql_statements("SELECT 1;
-- CAFE-STMT --
SELECT 2;
-- CAFE-STMT --
")) === 2, 'Backup SQL statement splitting is incorrect.');
check(updater_protected_path('config.php') && updater_protected_path('uploads/menu.jpg'), 'Updater protected paths are incomplete.');
check(!updater_protected_path('admin/index.php'), 'Normal application paths must remain updateable.');
check(updater_safe_id(str_repeat('a', 24)) && !updater_safe_id('../bad'), 'Pending update identifier validation is incorrect.');


check(array_keys(capability_definitions()) === ['orders_floor','cashier_accounts','preparation','shift_supervision','inventory_view','inventory_cost_view','inventory_operations','inventory_finalize','inventory_manage'], 'Capability definitions are incorrect.');

check(array_keys(preparation_stations()) === ['kitchen','hot_bar','cold_bar','none'], 'Preparation station order is incorrect.');
check(!preparation_station_requires_work('none') && preparation_station_requires_work('kitchen'), 'No-preparation service items must bypass preparation while real stations remain actionable.');
check(normalize_preparation_station('unknown') === 'cold_bar' && normalize_preparation_station('other') === 'cold_bar', 'Unknown and legacy other station must normalize to bar.');
check(station_label('hot_bar') === 'بار گرم', 'Preparation station label is incorrect.');
$sampleGroups = order_preparation_groups([['item_name'=>'لاته','preparation_station'=>'hot_bar'],['item_name'=>'سالاد','preparation_station'=>'cold_bar']]);
check(array_column($sampleGroups, 'key') === ['bar'] && count($sampleGroups[0]['items'] ?? []) === 2, 'Hot and cold bar items must merge into the single operational bar group.');
$sigA = order_preparation_signature(['id'=>1,'status'=>'new','updated_at'=>'2026-07-25 10:00:00'], [['id'=>2,'item_name'=>'لاته','quantity'=>1,'item_note'=>'','preparation_station'=>'hot_bar']]);
$sigB = order_preparation_signature(['id'=>1,'status'=>'new','updated_at'=>'2026-07-25 10:00:00'], [['id'=>2,'item_name'=>'لاته','quantity'=>2,'item_note'=>'','preparation_station'=>'hot_bar']]);
check($sigA !== $sigB && strlen($sigA) === 32, 'Order preparation signature must detect content changes.');
$sigStatusOnly = order_preparation_signature(['id'=>1,'status'=>'accounted','updated_at'=>'2026-07-25 10:05:00'], [['id'=>2,'item_name'=>'لاته','quantity'=>1,'item_note'=>'','preparation_station'=>'hot_bar']]);
check($sigA === $sigStatusOnly, 'Status-only changes must not force preparation acknowledgement.');
$vapid = push_generate_vapid_keypair();
check(strlen(push_b64url_decode($vapid['public_key'])) === 65, 'Generated VAPID public key must be an uncompressed P-256 point.');
$jwt = push_vapid_jwt('https://push.example.test/send/abc', ['private_pem'=>$vapid['private_pem'],'public_key'=>$vapid['public_key'],'subject'=>'mailto:test@example.com']);
$jwtParts = explode('.', $jwt);
check(count($jwtParts) === 3 && strlen(push_b64url_decode($jwtParts[2])) === 64, 'VAPID JWT must use a 64-byte JOSE signature.');
$shortPoint = push_ec_public_point(['ec'=>['x'=>str_repeat("\x01",31),'y'=>str_repeat("\x02",31)]], 'invalid');
check(strlen($shortPoint) === 65 && $shortPoint[0] === "\x04" && $shortPoint[1] === "\x00" && $shortPoint[33] === "\x00", 'P-256 coordinates must be left-padded to a fixed 65-byte uncompressed point.');
$receiver = push_generate_vapid_keypair();
$encryptedPush = push_encrypt_payload('{"title":"test"}', $receiver['public_key'], push_b64url_encode(random_bytes(16)));
check(strlen($encryptedPush) > 102 && substr($encryptedPush, 20, 1) === "\x41", 'Encrypted Web Push record header is invalid.');
$pushSource = file_get_contents(dirname(__DIR__) . '/includes/push.php');
check(is_string($pushSource) && str_contains($pushSource, 'push_enqueue_event') && str_contains($pushSource, 'push_event_queue'), 'Web Push must be persisted without contacting providers in the customer request.');
check(is_string($pushSource) && !str_contains($pushSource, 'ORDER BY updated_at DESC LIMIT 24'), 'Web Push must not silently drop eligible devices behind a global destination cap.');

$foundationCss = file_get_contents(dirname(__DIR__) . '/assets/css/app.css');
check(is_string($foundationCss) && str_contains($foundationCss, '.theme-courtyard'), 'Canonical courtyard theme styles are missing.');
check(is_string($foundationCss) && str_contains($foundationCss, '.search-result-card'), 'Canonical search result styles are missing.');
$panelComponentsCss = file_get_contents(dirname(__DIR__) . '/assets/css/panel-components.css');
check(is_string($panelComponentsCss) && str_contains($panelComponentsCss, '.row-action-menu[data-action-menu]') && str_contains($panelComponentsCss, '.row-action-popover.is-viewport-popover'), 'Canonical panel action-menu owner is missing.');
check(is_string($foundationCss) && !str_contains($foundationCss, '.row-action-popover'), 'Legacy action-menu positioning must not remain in app.css.');
$iconSprite = file_get_contents(dirname(__DIR__) . '/assets/icons/ui-sprite.svg');
check(is_string($iconSprite) && str_contains($iconSprite, 'icon-cart') && str_contains($iconSprite, 'icon-search'), 'Core SVG icons are missing.');
$menuSource = file_get_contents(dirname(__DIR__) . '/assets/js/menu.js');
check(is_string($menuSource) && str_contains($menuSource, 'renderSearch') && is_file(dirname(__DIR__) . '/assets/js/horizontal-rail.js') && str_contains((string)file_get_contents(dirname(__DIR__) . '/assets/js/horizontal-rail.js'), 'window.SoknaHorizontalRail'), 'Direct search or shared desktop rail flow is missing.');
$itemFormSource = file_get_contents(dirname(__DIR__) . '/admin/item_form.php');
check(is_string($itemFormSource) && str_contains($itemFormSource, 'copy_from') && str_contains($itemFormSource, 'ساخت آیتم مشابه'), 'Item duplication flow is missing.');
$eventListSource = file_get_contents(dirname(__DIR__) . '/admin/events.php');
check(is_string($eventListSource) && str_contains($eventListSource, 'ساخت نوبت جدید') && str_contains($eventListSource, 'پایان‌یافته'), 'Event recurrence and past-event flow are missing.');
check(order_display_number(['id'=>127]) === 127 && order_display_label(['id'=>127]) === 'سفارش ۱۲۷' && preg_match('/^O-[0-9]{4}-000127$/', order_display_code(['id'=>127])) === 1, 'Human/canonical order reference formatting is incorrect.');
check(table_display_token('میز ۱۲') === '۱۲' && table_display_token('تراس') === 'تر' && table_display_token('میز ۱۲', null, false) === '؟', 'Table medallion token formatting or formal-number fallback contract is incorrect.');
$futureEvent = ['active'=>1,'starts_at'=>'2099-01-01 10:00:00','ends_at'=>'2099-01-01 12:00:00'];
$pastEvent = ['active'=>1,'starts_at'=>'2020-01-01 10:00:00','ends_at'=>'2020-01-01 12:00:00'];
check(event_lifecycle_status($futureEvent, new DateTimeImmutable('2026-07-26 10:00:00', new DateTimeZone(app_timezone()))) === 'upcoming', 'Future event lifecycle status is incorrect.');
check(event_lifecycle_status($pastEvent, new DateTimeImmutable('2026-07-26 10:00:00', new DateTimeZone(app_timezone()))) === 'past', 'Past event lifecycle status is incorrect.');

$guestCss = file_get_contents(dirname(__DIR__) . '/assets/css/guest-menu.css');
check(is_string($guestCss) && str_contains($guestCss, '.featured-menu-rail') && str_contains($guestCss, '.item-detail-panel'), 'Canonical guest discovery or detail styles are missing.');
check(is_string($guestCss) && str_contains($guestCss, '.waiter-fab') && str_contains($guestCss, '.cart-drawer'), 'Canonical guest waiter or cart styles are missing.');
$panelCss = file_get_contents(dirname(__DIR__) . '/assets/css/panel.css');
$panelLayoutCss = file_get_contents(dirname(__DIR__) . '/assets/css/panel-layout.css');
check(is_string($panelLayoutCss) && str_contains($panelLayoutCss, '.panel-shell') && str_contains($panelLayoutCss, '.sidebar'), 'Canonical panel stylesheet is missing.');
$guestRouteSource = file_get_contents(dirname(__DIR__) . '/menu/index.php');
$guestViewSource = file_get_contents(dirname(__DIR__) . '/includes/guest_menu_view.php');
$guestSource = is_string($guestRouteSource) && is_string($guestViewSource)
    ? $guestRouteSource . "\n" . $guestViewSource
    : false;
check(is_string($guestSource) && substr_count($guestSource, 'class="waiter-fab"') === 1, 'Guest menu must expose exactly one waiter action.');
check(is_string($guestSource) && str_contains($guestSource, 'category-orbit') && !str_contains($guestSource, 'guest-main-tabs'), 'Guest category navigation must use one compact strip.');
check(is_string($guestSource) && str_contains($guestSource, 'assets/css/guest-menu.css') && str_contains($guestSource, 'menu-layout-<?= e($layout) ?>'), 'Guest menu must load the canonical shared-layout layer.');
check(is_string($guestSource) && str_contains($guestSource, 'class="guest-search-panel" id="searchPanel"') && !str_contains($guestSource, 'guest-search-panel hidden'), 'Guest search must be directly available without an extra open step.');
check(!str_contains($guestSource, 'assets/js/menu-preview.js') && substr_count($guestSource, 'assets/js/menu.js') === 1, 'Public and table menu must use one shared runtime owner.');
check(str_contains($guestSource, '$showTableUi=$table!==null') && str_contains($guestSource, '$showOrderUi=$canOrder'), 'Public and table UI gates are missing.');
$staffSource = file_get_contents(dirname(__DIR__) . '/waiter/index.php');
$panelLayoutSource = file_get_contents(dirname(__DIR__) . '/includes/panel_layout.php');
check(is_string($staffSource) && !str_contains($staffSource, 'staff-role-state') && str_contains($staffSource, 'id="waiterSound"'), 'Preparation panel must keep the local sound control without duplicating push-device settings.');
check(is_string($panelLayoutSource) && str_contains($panelLayoutSource, 'id="waiterNotify"') && str_contains($panelLayoutSource, 'device-notifications.js'), 'Push-device controls must be owned by the shared panel shell for eligible staff.');
$categorySource = file_get_contents(dirname(__DIR__) . '/admin/category_form.php');
check(is_string($categorySource) && str_contains($categorySource, 'image_path') && str_contains($categorySource, 'icon_key'), 'Category image/icon settings are missing from the existing category form.');
$schemaSource = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
check(is_string($schemaSource) && str_contains($schemaSource, 'icon_key') && str_contains($schemaSource, 'image_path'), 'Category visual fields are missing from the schema.');
check(is_string($schemaSource) && str_contains($schemaSource, 'uq_table_sessions_one_live_table'), 'Live table sessions must have a database uniqueness guard.');
$authSource = file_get_contents(dirname(__DIR__) . '/includes/auth.php');
check(is_string($authSource) && str_contains($authSource, 'function is_logged_in') && str_contains($authSource, 'function login('), 'Login runtime functions are missing.');
$orderApiSource = file_get_contents(dirname(__DIR__) . '/api/create_order.php');
$orderServiceSource = file_get_contents(dirname(__DIR__) . '/includes/guest_order_service.php');
check(
    is_string($orderApiSource)
    && is_string($orderServiceSource)
    && str_contains($orderApiSource, 'guest_order_commit(db(),$data)')
    && str_contains($orderServiceSource, "client_token']")
    && str_contains($orderServiceSource, 'order_status_history'),
    'Order idempotency or initial status history is missing.'
);
$menuJsSource = file_get_contents(dirname(__DIR__) . '/assets/js/menu.js');
check(is_string($menuJsSource) && str_contains($menuJsSource, 'data.client_token || context.submittedTarget?.client_token || pendingToken'), 'Guest tracking must use the server-confirmed client token.');
$panelLayout = file_get_contents(dirname(__DIR__) . '/includes/panel_layout.php');
check(is_string($panelLayout) && str_contains($panelLayout, '$font = ui_font()') && !str_contains($panelLayout, 'سفارش سریع و آسان'), 'Operational panels must use the selected global Persian font and omit promotional copy.');
check(str_contains($panelLayout, 'assets/css/panel.css') && !preg_match('/assets\/css\/v\d+/', $panelLayout), 'Panel must load one canonical stylesheet instead of historical layers.');
$maintenancePage = file_get_contents(dirname(__DIR__) . '/admin/maintenance.php');
check(is_string($maintenancePage) && str_contains($maintenancePage, 'href="update/"') && str_contains($panelLayout, "'updater'=>'maintenance'"), 'Updater must remain reachable through the Maintenance owner instead of a duplicate sidebar entry.');

if ($failures) {
    fwrite(STDERR, "FAILED: " . count($failures) . " of {$checks} checks\n");
    foreach ($failures as $failure) fwrite(STDERR, "- {$failure}\n");
    exit(1);
}

echo "OK: {$checks} unit checks passed.\n";
