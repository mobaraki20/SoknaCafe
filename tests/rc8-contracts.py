#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')
checks=[]
def ok(cond,msg):
    if not cond: raise AssertionError(msg)
    checks.append(msg)

version=read('VERSION.txt').strip(); ok(bool(version),'release identity present')
sw=read('service-worker.js'); ok(f"const RELEASE='{version}'" in sw and f"const CACHE='cafe-staff-v{version}'" in sw,'service worker release cache')

# Clean quick-order route: one page, no modal include/launcher.
layout=read('includes/panel_layout.php'); page=read('staff/quick-order.php'); quick=read('assets/js/staff-quick-order.js'); qcss=read('assets/css/quick-order.css')
ok(not (ROOT/'includes/staff_quick_order.php').exists(),'legacy quick-order modal removed')
ok("staff/quick-order.php" in layout and 'data-open-quick-order' not in layout,'one route-based quick-order entry')
ok('quickOrderPage' in page and 'sessionStorage' in quick and 'window.location.assign(returnUrl)' in quick,'single page/state/submit flow')
ok('این میز آزاد است' not in page+quick,'free-table intermediate step removed')
ok('.quick-order-search.is-collapsed{display:none}' in qcss and 'quickOrderSearchToggle' in page,'mobile search is optional')
ok('.quick-order-cart-body{min-height:0;overflow-y:auto' in qcss and '.quick-order-current-lines{max-height:none;overflow:visible' in qcss,'single cart scroll owner')

# Table account direct pending actions and scroll ownership.
op=read('assets/js/operator.js'); opcss=read('assets/css/operator-live.css'); opmarkup=read('includes/operator_page.php')
ok('pendingOrdersMarkup' in op and 'orderReviewMarkup(o,true)' in op and 'data-inline-review-order' not in op,'opened table uses direct pending actions')
ok('>تأیید سفارش</button>' in op and '>رد سفارش</button>' in op,'direct pending labels')
ok('data-review-order' in op,'grid-card review remains contextual')
ok('.table-detail-body-v1190{flex:1 1 auto;min-height:0;overflow-y:auto;overflow-x:hidden' in opcss,'account content has one vertical owner')
ok('.bill-table-wrap{width:100%;max-width:100%;margin-top:8px;overflow:visible' in opcss,'bill table does not own vertical scroll')
ok('tableDetailFooter' in opmarkup and 'table-account-footer-v1301' in opcss,'account footer outside content owner')

# Discount numeric contract.
ok('normalizeNumericInput' in op and 'discountInputDisplay' in op and 'formatDiscountInput' in op,'localized discount formatter')
ok('discount-value-control' in op and '.discount-value-control{display:grid;grid-template-columns:minmax(0,1fr) auto' in opcss,'discount suffix has independent grid cell')
ok('position:absolute' not in opcss.split('.discount-value-control',1)[1].split('\n',1)[0],'discount suffix is not overlaid')

# Zero quantity active-bill contract.
orders=read('operator/api_orders.php'); printing=read('includes/printing.php')
ok('oi.quantity>0' in orders,'active account query removes zero quantity')
ok('oi.quantity>0' in printing,'customer receipt removes zero quantity')
ok('bill-unit-mobile' in op and '.bill-unit-mobile' in opcss,'dense mobile bill keeps unit-price context')

# Analytics decimal safety.
analytics=read('admin/analytics.php')
ok('function analytics_number' in analytics and 'fa_digits' in analytics,'analytics owns decimal Persian formatter')
ok('fa_digits($conversion)' not in analytics and 'fa_digits($zeroRate)' not in analytics,'float KPIs no longer hit int/string formatter')

# Push outbox architecture.
push=read('includes/push.php')
ok('push_enqueue_event_tx' in push and 'push_event_deliveries' in push,'transactional push outbox')
ok('register_shutdown_function' not in push and 'fastcgi_finish_request' not in push,'no request-shutdown transport')
ok((ROOT/'tools/push-worker.php').is_file() and not (ROOT/'staff/api_push_worker.php').exists(),'one independent push worker')

# Icon semantics.
sprite=read('assets/icons/ui-sprite.svg')
ok('id="icon-adjust"' in sprite and "spriteIcon('adjust')" in op,'adjust icon for line correction')
ok("icon('message')" in quick,'note uses message icon')

# Print Agent distribution has a separate owner: Cafe ships API only, Agent ships from pinned Pagent release.
ok(not (ROOT/'print-agent-v6').exists(),'Cafe package does not bundle Agent source')
ok(not list((ROOT/'print-agent').glob('Sokna-Print-Agent-*')),'Cafe package does not bundle Agent binaries')
ok((ROOT/'print-agent/v4/api.php').is_file() and not (ROOT/'print-agent/api.php').exists(),'Cafe ships only the server-owned Print API v4 endpoint')
ok('mobaraki20/Pagent' in printing and '/releases/latest' in printing and '/releases/download/' in printing and 'latest/download' not in printing,'server resolves latest stable Agent from Pagent GitHub Release')
admin_print=read('admin/printing.php');print_css=read('assets/css/panel-components.css')
ok('print_agent_download_url()' in admin_print and 'جزئیات نسخه' in admin_print,'printing page downloads the pinned external Agent release')
ok('print-settings-tabs' not in admin_print and '.print-settings-tabs' not in print_css,'legacy printing tab UI removed')
ok('.print5-status-strip' in print_css and '.print5-overview-grid' in print_css and '.printing-intro' not in print_css and '.print-agent-grid' not in print_css,'one canonical printing operations CSS owner')

# Current release preserves the protected RC8 UI contracts while settlement correction handling is simplified.
ok('sokna.quick-order.v2.' in quick and 'beforeunload' not in quick and 'pagehide' in quick,'versioned table-bound quick-order draft without native unload dialog')
ok('successUrl' in quick and 'quick_order_success' in quick and 'quick_order_table' in quick and "searchParams.set('open_table'" in quick and "searchParams.set('quick_order_order'" in quick and '?work=tables#tables' in read('staff/quick-order.php'),'quick-order success returns to operational tables with the submitted table reopened and fresh order context')
ok("showTableView(true, 'change')" in quick and 'savedDraftHasContent' in quick,'explicit change-table rebind with destination draft conflict protection')
ok('.quick-order-page .skip-link' in qcss and '.skip-link:focus' in qcss,'skip link hidden until keyboard focus')
ok('direction:ltr' in qcss.split('.quick-order-inline-qty',1)[1].split('}',1)[0],'quantity stepper fixes minus-left plus-right')
ok('calc(100dvh - 238px)' not in opcss and 'overscroll-behavior-y:auto' not in opcss,'legacy table scroll magic height and conflicting owner removed')
ok('syncTablesWorkspaceHeight' in op and 'previousScroll=els.tableDetailBody?.scrollTop' in op,'table workspace measured and scroll position preserved')
ok('PreparationAdjustmentSettlementException' not in read('includes/functions.php') and 'preparation_adjustments_audit_settlement_notice' in read('includes/functions.php'),'settlement keeps preparation corrections visible without a blocking override')
for api in ['operator/api_table_session.php','operator/api_subscribers.php','operator/api_accommodation.php']:
    body=read(api);ok('accept_preparation_adjustment' not in body and 'preparation_adjustment_pending' not in body,f'pending preparation correction never blocks settlement: {api}')
admin=read('admin/index.php');ok('table_filter=open' in admin and 'attention_filter=orders' in admin and 'attention_filter=calls' in admin,'dashboard operational KPIs deep-link to filtered work')
ok('destination=direct' in admin and 'destination=accommodation' in admin and 'destination=subscriber' in admin,'today settlement destinations deep-link to filtered archive')
notif=read('assets/js/device-notifications.js');push_api=read('waiter/api_push.php');push_core=read('includes/push.php')
ok('DNS ${' not in notif and 'TLS ${' not in notif and 'cURL ${' not in notif,'staff push toast no longer exposes transport diagnostics')
ok('push_diagnostic_summary' in push_core and 'Sokna Push delivery failed:' in push_core and "'last_error'=>$row['last_error']" not in push_api,'push transport diagnostics are retained server-side without exposing raw provider errors in the device test')
ok('اصلاح تعداد' in opmarkup and 'billItemFinalQuantity' in opmarkup,'bill edit modal uses final quantity contract')
ok('۲ عدد به سفارش جدید اضافه می‌شود' not in op, 'bill edit menu is computed rather than hard-coded sample text')
ok("از حساب حذف می‌شود.`;save.disabled=false;save.textContent='حذف از حساب'" in op,'bill edit zero/removal semantics')
ok('order-review-line' in op and 'order-review-total' in read('assets/css/panel.css'),'order review uses structured rows and highlighted total')
# Guest category rails have one owner and live-layout navigation.
rail=read('assets/js/horizontal-rail.js');catnav=read('assets/js/category-navigation.js');menu=read('assets/js/menu.js');index=read('menu/index.php')
ok('window.SoknaHorizontalRail = {install, installAll, reveal}' in rail and 'function enhanceHorizontalRail' not in menu+menu,'one horizontal rail controller')
ok('const absoluteTop' in catnav and 'positions' not in catnav and 'guest-header' not in catnav,'category navigation uses live positions and excludes non-sticky header')
ok(index.index('horizontal-rail.js') < index.index('category-navigation.js'),'rail owner loads before category navigation')

print(f'OK: {len(checks)} RC8 architecture and regression contracts passed.')
