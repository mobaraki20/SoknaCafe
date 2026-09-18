#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')
version=read('VERSION.txt').strip(); assert version
sw=read('service-worker.js'); assert f"const RELEASE='{version}'" in sw and f"const CACHE='cafe-staff-v{version}'" in sw
# Quick Order safety + protected layout.
q=read('assets/js/staff-quick-order.js'); qp=read('staff/quick-order.php'); qc=read('assets/css/quick-order.css'); qa=read('staff/api_quick_order.php')
for n in ['localStorage','state.uncertain','quickOrderUncertainNotice','reviewPendingOrder','OPERATOR_STATUS_API']: assert n in q+qp, n
for retired in ['pending_order_ids','pendingOrderIds','pending_action','pendingAction','فعلاً منتظر بماند']:
    assert retired not in q+qp+qa, retired
assert "max-width: 1023px" in q and '12 * 3600 * 1000' in q
assert 'clamp(440px,35vw,480px)' in qc and '@media(max-width:1023px)' in qc
assert 'سفارش مهمان منتظر بررسی است' in qa
assert 'offline queue' not in (q+qp).lower()
# Invoices.
inv=read('includes/invoices_page.php'); assert '$perPage = 30' in inv or '$perPage=30' in inv
assert "'voided'" in inv and "'reversal'" in inv and 'contextParams' in inv and 'OFFSET' in inv
assert 'DATE(sr.settled_at)' not in inv
# Subscribers.
sub=read('includes/subscribers_page.php'); assert 'ledger_page' in sub and 'subscriber_ledger' in sub and '<dialog' in sub
assert 'financial' in sub.lower() or 'مالی' in sub
# Settings IA.
setp=read('admin/settings.php'); sets=read('assets/js/settings-sections.js')
for n in ['settingsBrand','settingsGuest','settingsOperations','settingsIntegrations','settingsSystem']: assert n in setp+sets, n
assert 'تنظیمات اتصال اقامتگاه با موفقیت' not in setp
# Analytics and preserved schema history.
an=read('admin/analytics.php')
assert 'فروش و عملکرد' in an and 'menu_metrics_daily' in an and 'menu_search_terms_daily' in an
assert 'function analytics_number' in an and 'fa_digits' in an
# Tables safety/performance.
tab=read('admin/tables.php')
assert 'table_live_session_locked' in tab and 'table.status_changed' in tab
assert 'COUNT(DISTINCT orders.id)' not in tab and 'JOIN orders' not in tab
assert 'tables-page-grid is-editor-mode' in tab or 'is-editor-mode' in tab
assert 'table_number' in tab
# QR.
qr=read('admin/qr.php'); idx=read('menu/index.php')
assert 'sheet' in qr and 'canonical' in qr.lower() and 'sokna-table-qrs.html' not in qr
assert 'QR' in idx and '404' in idx and 'no-store' in idx
# Recovery + help.
maint=read('includes/maintenance.php'); assert 'recovery_required' in maint and 'operations.lock' in maint and 'CAFE-SQL-FRAMED-V2' in maint
helpdata=read('includes/help_topics.php'); assert f"'reviewed_for_version' => '{version}'" in helpdata
print(f'Sokna {version} current release contracts passed against canonical schema.')
