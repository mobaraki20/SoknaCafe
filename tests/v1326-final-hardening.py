#!/usr/bin/env python3
from pathlib import Path
import re
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')

reporting=read('includes/reporting.php')
activity=read('admin/activity_report.php')
operations=read('admin/operations_report.php')
analytics=read('admin/analytics.php')
center=read('includes/sokna_center.php')
layout=read('includes/panel_layout.php')
panel_core=read('assets/js/panel-core.js')
personnel=read('admin/personnel.php')
entitlement_api=read('api/sokna_center_personnel_access.php')

# Complete-export contract: UI can stay bounded, XLSX cannot silently reuse UI limits.
assert 'function report_export_guard' in reporting and '5000' in reporting
assert 'LIMIT 101' in activity and '$hasMore=count($rows)>100' in activity
assert '$exportSql=' in activity and "ORDER BY a.created_at DESC,a.id DESC" in activity and '$exportStmt=$pdo->prepare($exportSql)' in activity
assert "report_export_guard(count($exportRows),'فعالیت کاربران')" in activity
assert 'LIMIT 100' in operations and "$exportOrderStmt=db()->prepare($orderDetailSql.' ORDER BY o.created_at DESC')" in operations
assert 'LIMIT 500' not in operations
assert "report_export_guard((int)($orderStats['total']??0)" in operations
assert 'LIMIT 12' in analytics and '$allItemRows=analytics_rows($pdo,$itemImpactSql,$settlementParams)' in analytics
assert '$itemGroupCountSql=' in analytics and 'report_export_guard($itemGroupCount' in analytics
assert "foreach($allItemRows as $r)" in analytics

# Center owns personnel authorization. Cafe only consumes a short-lived fail-closed hint.
assert 'sokna_center_personnel_entitlement_from_data' in center
assert 'sokna_center_personnel_access_state' in center
assert 'sokna_center_refresh_personnel_access' in center
assert "'allowed'=>$allowed" in center and "'checked_at'=>time()" in center
assert 'sokna_center_personnel_cache_fingerprint' in center
assert 'data-center-personnel-link' in layout and 'data-center-personnel-refresh' in layout
assert "user_has_capability('personnel'" not in layout and "user_has_capability('payroll'" not in layout
assert 'refreshPersonnelEntitlement' in panel_core and "method: 'POST'" in panel_core
assert 'csrf_valid' in entitlement_api and 'sokna_center_refresh_personnel_access' in entitlement_api
assert 'sokna_center_handoff_token' in personnel
assert 'must never be a prerequisite for launching Center' in personnel

# No native numeric controls in normal panel and no legacy blind flip assignment in active application code.
active_dirs=['admin','operator','staff','waiter','includes']
for directory in active_dirs:
    for path in (ROOT/directory).rglob('*.php'):
        if 'updater_engine' in path.parts: continue
        text=path.read_text(encoding='utf-8')
        assert 'type="number"' not in text, f'native numeric input remains: {path.relative_to(ROOT)}'
        assert not re.search(r'\b(?:active|available|featured)\s*=\s*1\s*-\s*', text), f'blind state flip remains: {path.relative_to(ROOT)}'

# Financial surfaces use human invoice references while canonical IDs remain available for audit/reservation metadata.
for source in [read('includes/invoices_page.php'), read('admin/accommodation.php'), read('includes/subscribers_page.php')]:
    assert 'financial_document_human_label' in source
assert 'canonical_identifier_html' in read('includes/invoices_page.php')
assert 'canonical_identifier_html' in read('admin/accommodation.php')

# Reviewed JS surfaces must not put raw browser error.message directly in user-facing DOM/toasts.
for path in (ROOT/'assets/js').glob('*.js'):
    text=path.read_text(encoding='utf-8')
    assert not re.search(r'(?:toast|textContent|innerText|innerHTML)[^\n]{0,100}error\.message', text), f'raw request error display: {path.name}'

print('1.32.14 final cross-system hardening contracts passed.')
