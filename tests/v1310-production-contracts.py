#!/usr/bin/env python3
from pathlib import Path
import hashlib,re
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')

version=(ROOT/'VERSION.txt').read_text().strip(); assert version
sw=read('service-worker.js')
assert f"const RELEASE='{version}'" in sw and f"const CACHE='cafe-staff-v{version}'" in sw

# Quick Order: only approved workflow/reliability changes; visual owner stays frozen.
qo=read('assets/js/staff-quick-order.js'); qapi=read('staff/api_quick_order.php'); qpage=read('staff/quick-order.php')
assert 'pendingAction' not in qo and 'pendingOrderIds' not in qo and 'فعلاً منتظر بماند' not in qo
assert 'data-qo-pending-status="accounted"' in qo and 'data-qo-pending-status="cancelled"' in qo
assert qo.index('data-qo-pending-status="accounted"') < qo.index('data-qo-pending-status="cancelled"')
assert 'window.OPERATOR_STATUS_API' in qpage and 'reviewPendingOrder' in qo
assert 'سفارش مهمان منتظر بررسی است؛ ابتدا آن را تأیید یا رد کنید.' in qapi
assert qapi.count('$pdo->commit();') >= 1
qcss=read('assets/css/quick-order.css')
assert 'clamp(440px,35vw,480px)' in qcss and '@media(max-width:1023px)' in qcss
assert '.quick-order-takeaway-mode' in qcss and '.quick-order-cart-line.is-takeaway-editing' in qcss

# Tables: canonical numeric identity, no frontend hiding, destructive action last.
tables=read('admin/tables.php'); funcs=read('includes/functions.php'); op=read('assets/js/operator.js')
assert 'table_number' in funcs and 'table_display_token' in funcs
assert 'backfill_unambiguous_table_numbers' not in tables and 'table_number IS NULL' not in tables
assert 'name="table_number" required' in tables and 'شماره میز باید فقط عدد' in tables
menu_start=tables.index('data-action-menu-label=')
menu_slice=tables[menu_start:tables.index('<?php endforeach; ?>',menu_start)]
assert menu_slice.index('QR میز') < menu_slice.index('غیرفعال‌کردن') < menu_slice.index('danger-action')
assert "status.key === 'free' ? 'آماده ثبت سفارش'" in qo
assert "table.active?'بدون فاکتور':''" in op

# QR single-table page: normal task first, security second, destructive rotation last.
qr=read('admin/qr.php')
single=qr[qr.index("<?php elseif($single)"):qr.index("<?php else: ?>",qr.index("<?php elseif($single)"))]
assert single.index('data-qr-card') < single.index('مدیریت و امنیت QR') < single.index('ابطال فوری و ساخت QR جدید')
assert 'QR فعلی همان لحظه از کار می‌افتد' in single and 'نسخه جدید را چاپ و جایگزین کنید' in single

# Shared overlay contract.
choice=read('assets/js/panel-choice.js'); time=read('assets/js/panel-time-picker.js'); layout=read('includes/panel_layout.php')
assert all(x in choice for x in ("'compact'", "'browse'", "'adaptive'", "'embedded'"))
assert 'MutationObserver' in choice
assert '<div class="panel-confirm-backdrop"' in layout
actions=layout[layout.index('<div class="panel-confirm-actions">'):layout.index('</div>',layout.index('<div class="panel-confirm-actions">'))]
assert actions.index('data-panel-confirm-ok') < actions.index('data-panel-confirm-cancel')
assert 'panel-time-grip' not in time or 'touchstart' not in time

# Report/analytics/date behavior.
ops=read('admin/operations_report.php'); analytics=read('admin/analytics.php'); reporting=read('includes/reporting.php'); jalali=read('assets/js/panel-jalali.js')
assert 'مبنای گزارش: روز عملیاتی' in ops and 'روز عملیاتی و سابقه تغییرات' not in ops
assert 'report_render_range_fields' in ops and 'از تاریخ' in reporting and 'تا تاریخ' in reporting and 'data-custom-date-field' in reporting
assert 'فروش و عملکرد' in analytics and 'report_render_range_fields' in analytics and 'data-choice-label="شیفت"' in analytics
assert 'امروز' in jalali and 'data-jalali-today' in jalali and 'btn btn-primary' in jalali

# Mobile invoice, analytics and sidebar visual contracts.
inv=read('includes/invoices_page.php'); css=read('assets/css/panel-components.css'); layoutcss=read('assets/css/panel-layout.css')
for cls in ('invoice-day-group','invoice-transaction-row','invoice-receipt-summary','invoice-receipt-lines'): assert cls in inv
assert '.invoice-transaction-row' in css and '.invoice-receipt-summary' in css
assert '.analytics-compact-table td.analytics-money-cell{white-space:nowrap' in css
assert re.search(r'\.sidebar-user-copy strong\{[^}]*color:var\(--sokna-sidebar-text,#edf5f0\)',layoutcss)

# Login stays approved centered modal and timezone stays literal.
login=read('login.php'); panelcss=read('assets/css/panel.css')
assert 'Asia/Tehran' in login
assert '<div class="login-account-backdrop"' in login
assert 'login-account-option' in login and 'focus-within' not in panelcss[panelcss.find('.login-account-option'):panelcss.find('.login-account-option')+2400]

# Native prompt leakage is covered by panel-native-ui-contract.py.

print('1.31.7 production contracts passed: approved Quick Order scope, table/QR identity, semantic overlays, report/date behavior, responsive financial UI and sidebar/login contracts are present.')
