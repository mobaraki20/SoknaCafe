#!/usr/bin/env python3
from pathlib import Path
import re
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')

version=read('VERSION.txt').strip(); assert version
sw=read('service-worker.js')
assert f"RELEASE='{version}'" in sw and f"cafe-staff-v{version}" in sw
assert f"'reviewed_for_version' => '{version}'" in read('includes/help_topics.php')

# Reports: one shared scope owner and real Excel presentation, no user-facing CSV.
for p in ['admin/analytics.php','admin/operations_report.php','admin/inventory_report.php','admin/activity_report.php']:
    src=read(p)
    assert "includes/reporting.php" in src or "includes/reporting.php" in src.replace("dirname(__DIR__) . '/",'')
    assert 'xlsx_export.php' in src
    assert 'report_range_resolve' in src
    assert 'report_render_range_fields' in src
    assert 'panel-surface-stack' in src
    assert 'text/csv' not in src and 'fputcsv' not in src and 'خروجی CSV' not in src

reporting=read('includes/reporting.php')
assert "['today','7','30','90','365','custom']" in reporting
assert 'report_valid_settlement_sql' in reporting and 'report_range_query' in reporting
xlsx=read('includes/xlsx_export.php')
assert 'function xlsx_build' in xlsx and 'function xlsx_zip_store' in xlsx
assert "rightToLeft=\"1\"" in xlsx and 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' in xlsx
assert 'ZipArchive is optional' in xlsx

analytics=read('admin/analytics.php')
assert "panel_header('فروش و عملکرد','analytics')" in analytics
assert 'peakTables' in analytics and "$selectedShift===''" in analytics and ':null' in analytics
assert 'درآمد آیتم قبل از تخصیص تخفیف فاکتور' in analytics
ops=read('admin/operations_report.php')
assert "panel_header('عملیات','operations_report')" in ops
assert '$staffUnavailable' in ops and 'عدد با مبنای دیگری جایگزین نشده است' in ops
assert 'mobile-card-table' not in ops
inv=read('admin/inventory_report.php')
assert 'فروش دارای هزینه کامل' in inv and 'سود ناخالص تقریبی همان فروش' in inv
assert 'mobile-card-table' not in inv
assert "name'=>'مصرف مواد'" in inv and "name'=>'آیتم‌های منو'" in inv
activity=read('admin/activity_report.php')
assert 'details_json' in activity and 'audit_human_summary' in activity

# Audit presentation must use details rather than generic raw action strings.
audit=read('includes/audit_presentation.php')
assert 'function audit_details' in audit and 'menu.category_active_changed' in audit

# Session owner: Cafe-specific cookie, app-owned files, scoped path and sliding expiry.
boot=read('bootstrap.php'); auth=read('includes/auth.php')
assert "session_name('SOKNA_CAFE_SID')" in boot
assert "__DIR__ . '/storage'" in boot and "$runtimeStorage . '/sessions'" in boot and 'session_save_path($sessionStorage)' in boot
assert 'app_request_mount_path()' in boot and "(?:admin|operator|waiter|api|staff)" not in boot
assert 'cookie_lifetime' in boot and '12 * 3600' in boot
assert 'auth_refresh_session_cookie();' in auth and 'setcookie(session_name(), session_id()' in auth

# Form/search component has one visible focus ring and resets native search decorations.
pc=read('assets/css/panel-components.css')
focus_visible=re.search(r'\.form-control:focus-visible\s*\{([^}]*)\}',pc,re.S)
assert focus_visible and 'outline:none' in focus_visible.group(1)
assert '::-webkit-search-cancel-button' in pc and 'appearance:none' in pc
assert pc.count('.panel-surface-stack{')==1
assert pc.count('.panel-list-card{')==1
assert '.panel-list-row.panel-list-row-3' in pc
assert '.report-data-list' in pc and '.report-data-row' in pc

# Sidebar is a single neutral owner, not page-group rainbow patches.
pl=read('assets/css/panel-layout.css')
assert pl.count('.side-nav-group{')==1
assert 'side-nav-group:nth-child' not in pl
layout=read('includes/panel_layout.php')
for label in ['فروش و عملکرد','عملیات','انبار و سود','فعالیت کاربران']:
    assert label in layout
assert re.search(r"'گزارش‌ها'\s*=>\s*\[\s*'analytics'\s*=>.*?'operations_report'\s*=>.*?'inventory_report'\s*=>.*?'activity_report'\s*=>", layout, re.S)

# Action menus have a single viewport-placement owner and no page popover positioning hacks.
menus=read('assets/js/panel-menus.js')
assert 'visualViewport' in menus and 'data-menu-placement' in menus
assert '.row-action-popover.is-viewport-popover' in pc
for p in ['assets/css/items-management.css','assets/css/inventory.css','assets/css/operator-live.css']:
    src=read(p)
    assert 'row-action-popover{inset-' not in src and 'row-action-popover{position:absolute' not in src

# Inventory master data: explicit targets, no edit-key collision and no CSS !important fight.
iitems=read('admin/inventory_items.php')
icats=read('admin/inventory_categories.php')
mcats=read('admin/categories.php'); mform=read('admin/category_form.php')
assert "value=\"set_active\"" in iitems and 'desired_active' in iitems
assert "review_status='needs_review'" in iitems and 'active=1' in iitems
assert '$editingKey' in icats and '$actionKey' in icats and '$formKey' in icats
assert '$_GET[\'edit\']' in icats and '$_POST[\'category_key\']' in icats
assert 'panel-list-row panel-list-row-3' in icats and 'panel-surface-stack' in icats
assert 'desired_active' in mcats and 'menu.category_active_changed' in mcats and 'menu.category_deleted' in mcats
assert 'data-table' not in mcats
assert 'maxlength="120"' in mform and 'عضویت در منوها' in mform and 'نمایش برای' in mform
assert 'items.php?view=arrange' in mcats and 'category_order.php' not in mcats and 'sort_order' not in ''.join(line for line in mform.splitlines() if '<label' in line or '<input' in line)
invcss=read('assets/css/inventory.css')
assert '.inventory-management-row>.panel-list-value{display:none!important}' not in invcss

# Inventory unit language explains stock unit vs purchase package.
iitem=read('admin/inventory_item_form.php')
assert 'واحد ثبت موجودی' in iitem and 'واحدهای خرید' in iitem
assert 'ورود، مصرف و موجودی با این واحد ثبت می‌شوند.' in iitem
assert 'حذف این واحد' in iitem and 'data-unit-row' in iitem

# Operator tabs stay centered; the longer attention label gets enough width to remain one line.
opcss=read('assets/css/operator-live.css')
assert 'justify-content:center' in opcss
assert 'grid-template-columns:minmax(0,1.35fr) repeat(2,minmax(0,1fr))' in opcss
assert 'white-space:nowrap' in opcss

# Recipe choice change must not rebuild the select while panel-choice is dispatching change.
item=read('admin/item_form.php')
change_blocks=re.findall(r"addEventListener\('change',[^;]+(?:;[^;]+){0,8}", item)
assert 'recipe-unit-label' in item and 'requestAnimationFrame' in item and ".recipe-item')?.addEventListener('change'" in item
assert 'data-choice-mode="browse"' in item

# Existing 1.32.4 guest/QR fixes stay intact.
index=read('menu/index.php'); qr=read('admin/qr.php')
assert 'بله، همین میز' in index and 'QR میز فعلی' in index
assert 'qr-table-list' in qr

print('Current panel/reporting contracts passed: report scope/export, shared UI owners, session isolation, categories, inventory, menus and protected prior fixes are locked.')
