#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
read = lambda p: (ROOT / p).read_text(encoding='utf-8')

# Core contracts.
auth = read('includes/auth.php')
functions = read('includes/functions.php')
message_owner = read('includes/function_domains/messages.php')
create_order = read('api/create_order.php')
menu = read('assets/js/menu.js')
schema = read('database/schema.sql')
table_session = read('operator/api_table_session.php')
maintenance = read('includes/maintenance.php')
sw = read('service-worker.js')
assert 'function is_logged_in()' in auth and 'function login(string $username, string $password)' in auth
assert 'function fa_datetime(' not in functions
for needle in ['normalize_order_request_payload','WHERE client_token=? LIMIT 1 FOR UPDATE','order_status_history',"'items_unavailable'", "'prices_changed'", 'FOR UPDATE']:
    assert needle in create_order
assert 'data.client_token || submittedTarget?.client_token || pendingToken' in menu
assert 'live_table_guard INT UNSIGNED NULL' in schema and 'uq_table_sessions_one_live_table' in schema
assert "status IN('active','pending')" in table_session and 'sort($lockIds, SORT_NUMERIC)' in table_session
assert 'START TRANSACTION WITH CONSISTENT SNAPSHOT' in maintenance
assert 'function maintenance_restore_sql_file' in maintenance

# Release identity.
release = read('VERSION.txt').strip()
assert re.fullmatch(r'\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?', release)
assert f"const RELEASE='{release}'" in sw
assert f"const CACHE='cafe-staff-v{release}'" in sw

# One current guest/panel/public stylesheet architecture.
for required in ['assets/css/app.css','assets/css/guest-menu.css','assets/css/panel.css','assets/css/public-page.css']:
    assert (ROOT / required).is_file()
retired_css = list((ROOT / 'assets/css').glob('v*.css')) + [ROOT/'assets/css/guest-order-refinement.css']
assert not [p for p in retired_css if p.exists()], 'historical CSS layers remain: ' + ', '.join(p.name for p in retired_css if p.exists())
index = read('menu/index.php')
panel_layout = read('includes/panel_layout.php')
assert 'assets/css/guest-menu.css' in index and not re.search(r'assets/css/v\d+\.css', index)
assert 'assets/css/panel.css' in panel_layout and not re.search(r'assets/css/v\d+\.css', panel_layout)
assert 'assets/js/menu-preview.js' not in index and index.count('assets/js/menu.js') == 1
settings = read('admin/settings.php')
assert 'پیش‌نمایش منوی عمومی' in settings
for retired in ['preview-v163','preview-cart','preview-add','preview-table-label','میز ۱۲']:
    assert retired not in settings, f'legacy preview control remains: {retired}'
assert '.guest-menu-page' not in read('assets/css/panel.css')
assert '.menu-page-v' not in read('assets/css/public-page.css')

# One canonical guest-card and bottom-dock implementation; retired orientation/float code cannot return.
foundation_css = read('assets/css/app.css')
guest_css = read('assets/css/guest-menu.css')
for retired_selector in ['.menu-item-v12','.item-media-v12','.item-copy-v12','.item-action-row','.featured-menu-card','.featured-menu-copy']:
    assert retired_selector not in foundation_css, f'guest card styling leaked back into foundation CSS: {retired_selector}'
assert guest_css.count('Canonical Sokna 1.29.1 guest interface') == 1
assert 'Quiet Editorial Hospitality' not in guest_css
assert 'grid-template-areas:"media copy"' in guest_css and 'direction:ltr' in guest_css
assert guest_css.count('Small waiter bell and order dock') == 1
assert 'class="guest-action-dock"' in index
assert 'updateFloatingOffsets' not in menu and '--cart-dock-clearance' not in guest_css

# Final guest UI contracts: no native confirmations, no duplicate waiter close,
# one compact quantity family, and no repeated per-row currency labels.
assert 'window.confirm' not in menu
for retired_id in ['itemDetailCategory','itemDetailPrimaryTotal','submitOrderTotal','guestOrdersTotal','waiterModalClose']:
    assert retired_id not in index and retired_id not in menu, f'retired guest UI node remains: {retired_id}'
assert "customer_message('featured_title')" in index and 'انتخاب‌های ویژه کافه' not in index
assert 'guestConfirmModal' in index and 'guest-confirm-modal' in guest_css
assert 'cart-currency-note' in menu and 'guest-orders-currency' in menu
assert "customer_message('search_placeholder')" in index and "'default'=>'چی دوست داری بخوری؟'" in message_owner
assert 'input::-webkit-search-cancel-button' in guest_css
assert 'operational_note' not in index and 'item-operational-note' not in guest_css and 'item-operational-note' not in menu
assert "guest_order_status_is_mutable((string)$order['status'])" in read('api/guest_orders.php')
assert "['pending_approval', 'new']" in read('includes/functions.php')
assert 'Canonical Sokna 1.29.1 guest interface' in guest_css

# Sokna 1.29 operational-account architecture.
operator_page = read('includes/operator_page.php')
operator_js = read('assets/js/operator.js')
assert all(label in operator_page for label in ['نیازمند اقدام','میزها','جمع اقلام'])
assert 'data-settlement="direct"' in operator_page and 'data-settlement="subscriber"' in operator_page
assert 'checkout_payment_method' not in schema and 'discount_reason' not in schema
assert not (ROOT/'admin/operator.php').exists()
assert 'render_operator_page();' in read('operator/index.php')
assert 'data-move-table' in operator_page and 'reprint_prep' in read('operator/api_bill.php')
assert 'invoice_discount_audit' in schema and 'subscriber_ledger' in schema

# One versioned in-app updater, no cPanel runtime or legacy entry.
for required in ['admin/update/index.php','includes/updater_engine/1.5.1/runtime.php','includes/updater_engine/1.5.1/console.php','includes/updater_engine/1.5.2/runtime.php','includes/updater_engine/1.5.2/console.php','includes/updater_engine/1.5.3/runtime.php','includes/updater_engine/1.5.3/console.php']:
    assert (ROOT / required).is_file()
assert sorted(p.name for p in (ROOT/'includes/updater_engine').iterdir() if p.is_dir()) == ['1.5.1','1.5.2','1.5.3']
for retired in ['admin/update.php','includes/updater_runtime.php','upgrade.php','staff/shift.php']:
    assert not (ROOT / retired).exists(), f'legacy update path remains: {retired}'
loader = read('admin/update/index.php')
runtime = read('includes/updater_engine/1.5.3/runtime.php')
builder = read('tools/build-release.php')
assert 'previous' in loader and 'token_get_all($source, TOKEN_PARSE)' in loader
assert 'function updater_activate_engine' in runtime and 'updater_retire_legacy_runtime' in runtime
assert "'updater_engine'=>$targetEngine" in builder
assert 'Sokna-Updater-Runtime' not in builder and 'cPanel' not in builder
assert 'href="update/"' in read('admin/maintenance.php') and "'updater'=>'maintenance'" in panel_layout

# Action page contains only actionable work; no generic table overview or shift responsibility.
queue = read('waiter/index.php')
assert 'آماده‌سازی' in queue
assert 'صف آماده‌سازی' in queue
assert 'مسئولیت شیفت' not in queue
assert 'responsibility_active' not in functions and 'active_shift_responsibilities' not in functions
assert 'user_shift_responsibilities' not in schema and 'user_shift_responsibilities' not in maintenance
assert 'table-state-card' not in queue
assert 'staff/quick-order.php' in panel_layout and 'data-open-quick-order' not in panel_layout

# Agent distribution is external and version-pinned; Cafe owns API/DB/Admin only.
printing = read('includes/printing.php')
assert not (ROOT/'print-agent-v6').exists()
assert not list((ROOT/'print-agent').glob('Sokna-Print-Agent-*'))
assert 'mobaraki20/Pagent' in printing and '/releases/download/' in printing
assert 'latest/download' not in printing
assert (ROOT/'print-agent/v4/api.php').is_file()
assert not (ROOT/'print-agent/api.php').exists()
assert not (ROOT/'migrations').exists()
for table in ['print_agents','print_attempts','print_claim_requests','print_destinations','print_jobs','print_templates']:
    assert f'CREATE TABLE IF NOT EXISTS {table}' in schema
assert (ROOT/'docs/PRINT_AGENT_DISTRIBUTION_CONTRACT_FA.md').is_file()

# Build hygiene: generated interpreter/runtime artifacts must never enter Source/Full/Update packages.
assert not list(ROOT.rglob('__pycache__')), 'Python __pycache__ directory leaked into release source.'
assert not list(ROOT.rglob('*.pyc')) and not list(ROOT.rglob('*.pyo')), 'Generated Python bytecode leaked into release source.'

# Historical previews/release archives are not part of the active pre-launch tree; Git is the archive.
assert not (ROOT / 'demo').exists()
assert not (ROOT / 'tools/build-standalone-preview.mjs').exists()
assert not (ROOT / 'tools/build-panel-preview.mjs').exists()
assert not (ROOT / 'tools/guest-preview-template.html').exists()
for current_doc in ('docs/ARCHITECTURE_MODULE_MAP_1.36_FA.md','docs/SUPPLY_WORKFLOW_1.36_FA.md','docs/TEST_REPORT_V1.36.3_FINAL_FA.md','docs/PRELAUNCH_CLEANUP_FA.md','docs/AI_HANDOFF/DEVELOPER_HANDOFF_1.36.3_FINAL_FA.md'):
    assert (ROOT / current_doc).is_file(), current_doc
for p in (ROOT/'docs').iterdir():
    if p.is_file():
        assert not re.match(r'(?:CHANGELOG|GO_LIVE_CHECKLIST|RELEASE_NOTES|RELEASE_RECORD|SCOPE|TEST_REPORT)_V1\.(?:2[0-9]|3[0-5])', p.name), p.name
assert not (ROOT/'database/migrations').exists() and not (ROOT/'migrations').exists()

assert 'waiter_table_assignments' not in schema and 'staff_order_acknowledgements' not in schema
assert 'phone VARCHAR' not in schema
assert 'legacy_capabilities_to_responsibilities' not in functions
# Sokna 1.29.2 operational and financial contracts.
assert 'claim_order_area' in read('waiter/api_action.php')
assert 'ready_order' not in read('waiter/api_action.php') and 'receive_order' not in read('waiter/api_action.php')
active_help = read('help.php')
assert 'هنگام شروع، «دریافت شد»' not in active_help and 'پس از تکمیل کل سفارش «آماده شد»' not in active_help
assert all(x in read('operator/api_controls.php') for x in ["['cafe','kitchen','bar']", 'order_acceptance'])
assert 'sokna-print-template-v1' in schema and 'sokna-print-document-v2' in read('includes/printing.php')
assert (ROOT / 'operator/api_settlements.php').is_file()
assert "accommodation_http_request('capabilities'" in read('includes/accommodation.php')
assert 'search_type' not in read('operator/api_accommodation.php')
# 1.29.2: solid panel dialogs, preserved form state and compact one-page quick order.
panel_core = read('assets/js/panel-core.js')
panel_form = read('assets/js/panel-form-state.js')
panel_media = read('assets/js/panel-media.js')
assert 'CafeUI.dialog' in panel_core and 'CafeUI.runAction' in panel_core
assert 'sessionStorage' in panel_form and '.field-error' in read('assets/css/panel-components.css')
quick_markup = read('staff/quick-order.php')
quick_js = read('assets/js/staff-quick-order.js')
assert 'quickOrderPage' in quick_markup and 'quickOrderWorkspace' in quick_markup and 'quickOrderMobileCartBar' in quick_markup
assert 'mobileView' in quick_js and 'requestToken' in quick_js and 'quickOrderReviewStage' not in quick_markup

# Category navigation is deterministic while preserving the frozen guest UI.
category_navigation=read('assets/js/category-navigation.js')
assert 'SoknaCategoryNavigation' in category_navigation and 'lockedTarget' in category_navigation and 'requestAnimationFrame' in category_navigation
assert 'SoknaCategoryNavigation?.install()' in menu

# Three independent order-acceptance states and clear station-load controls.
controls = read('operator/api_controls.php')
orders_api = read('operator/api_orders.php')
assert all(token in controls for token in ["['cafe','kitchen','bar']", 'order_acceptance'])
assert '$acceptanceStates = order_acceptance_states();' in orders_api and orders_api.count("'order_acceptance' => $acceptanceStates") >= 2
acceptance_helpers = read('includes/functions.php')
assert all(token in acceptance_helpers for token in ['orders_accepting.cafe','orders_accepting.kitchen','orders_accepting.bar'])
assert 'data-order-acceptance' in operator_page and 'data-station-choice' in operator_page
settings_page = read('admin/settings.php')
assert 'name="ordering_enabled"' not in settings_page and "'ordering_enabled'=>isset" not in settings_page
assert "order_acceptance_states()" in read('admin/index.php')

# Searchable immutable invoices, fiscal periods and reversal documents.
assert 'CREATE TABLE IF NOT EXISTS financial_periods' in schema
assert 'invoice_number VARCHAR(40) NOT NULL' in schema and 'invoice_snapshot_json JSON NOT NULL' in schema
assert 'reverses_settlement_id BIGINT UNSIGNED NULL' in schema
assert (ROOT/'admin/invoices.php').is_file() and (ROOT/'operator/invoices.php').is_file()
assert (ROOT/'admin/financial_periods.php').is_file()
assert 'financial_period_issue_invoice_locked' in read('includes/settlement.php')
assert "status='reversal'" in read('includes/settlement.php')

# Financial and invoice source data cannot be deleted by a retention screen.
assert not (ROOT/'admin/data_retention.php').exists()
assert 'retention_runs' not in schema and 'data_retention_days' not in schema
assert 'data_retention' not in panel_layout

# Mobile subscriber profile, separated campaign/tag forms and reusable media picker.
subscriber_page = read('includes/subscribers_page.php')
assert 'invoices.php?id=' in subscriber_page and 'subscriber-profile' in subscriber_page and 'financial_receipt_presented_items' in subscriber_page
assert (ROOT/'admin/campaign_form.php').is_file() and (ROOT/'admin/tag_form.php').is_file()
assert 'data-image-picker' in panel_media and 'image-source-picker' in read('assets/css/panel-components.css')

assert (ROOT/'assets/css/panel-layout.css').is_file() and (ROOT/'assets/css/quick-order.css').is_file()
assert not (ROOT/'assets/css/staff-quick-order-refinement.css').exists()
assert 'window.CAFE_ORDERING_ENABLED' not in index and 'window.CAFE_ORDERING_ENABLED' not in menu


# 1.36 guest-review and passive-background interaction invariants.
pushjs = read('assets/js/device-notifications.js')
guestcss = read('assets/css/guest-menu.css')
assert index.index('id="orderNoteDisclosure"') < index.index('id="cartSuggestion"')
assert '</section><div class="guest-takeaway-layer' in index
assert 'lastSuggestionSourceId' in menu and 'sourceStillPresent' in menu
assert "querySelector('header')" in menu and '.guest-takeaway-list' in menu
assert '.guest-takeaway-backdrop{touch-action:none}' in guestcss and '.guest-takeaway-sheet>header{touch-action:pan-x' in guestcss
refresh_block = pushjs[pushjs.index('const refresh = async'):pushjs.index('const enable = async')]
assert 'CafeUI?.toast' not in refresh_block
assert 'subscription = null' not in refresh_block

print(f'Sokna {release} release invariants passed.')
