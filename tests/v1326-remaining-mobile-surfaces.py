#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')
users=read('admin/users.php'); push=read('admin/push_devices.php'); printing=read('admin/printing.php'); events=read('admin/events.php'); dash=read('admin/index.php'); financial=read('admin/financial_periods.php'); maintenance=read('admin/maintenance.php'); css=read('assets/css/panel-components.css')
# High-density operational pages use purpose-built compact lists instead of generic table-to-card inflation.
assert 'team-account-list' in users and 'team-account-actions' in users and 'mobile-card-table' not in users
assert 'push-health-strip' in push and 'push-device-list' in push and 'push-log-list' in push and 'mobile-card-table' not in push
assert 'print5-job-list' in printing and 'print5-job-row' in printing
queue=printing.split('id="print-queue"',1)[1].split('print5-advanced',1)[0]
assert 'data-table' not in queue
# Technical push errors remain available in diagnostics, but collapsed from the main scan path.
assert 'جزئیات آخرین خطا' in push and 'جزئیات فنی' in push
assert "safe_business_error_message($e, 'آماده‌سازی اعلان‌ها انجام نشد" in push
# Existing event/dashboard data can stay in tables on desktop, with compact mobile ownership layered on the same source markup.
assert '.events-management-card .mobile-card-table tr' in css
assert '.dashboard-main-grid .table-card:first-child .mobile-card-table tr' in css
# Financial periods now use the shared financial list family; unrelated low-frequency maintenance remains on the legacy mobile-card pattern until its own reviewed migration.
assert 'financial-period-list financial-list' in financial and 'mobile-card-table' not in financial
assert 'mobile-card-table' in maintenance
print('v1.32.14 remaining-mobile-surface contracts PASS')
