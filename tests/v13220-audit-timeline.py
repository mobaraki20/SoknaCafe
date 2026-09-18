#!/usr/bin/env python3
from pathlib import Path
R=Path(__file__).resolve().parents[1]
read=lambda p:(R/p).read_text(encoding='utf-8')
f=read('includes/function_domains/audit.php'); a=read('includes/audit_presentation.php'); page=read('admin/activity_report.php'); tpl=read('admin/print_templates.php'); schema=read('database/schema.sql')
assert 'actor_display_name_snapshot' in f and 'audit_log_write_strict' in f
assert 'audit_family' in a and 'audit_human_context' in a and 'order.item_quantity_adjusted' in a
assert 'audit-day-group' in page and 'cursor_created' in page and 'cursor_id' in page
assert 'نوع فعالیت' in page and 'جست‌وجو' in page and 'LEFT JOIN users' in page
assert 'idx_audit_created' in schema
assert 'همین قالب از قبل فعال است' in tpl and 'audit_log_write_strict' in tpl
print('1.32.20 audit timeline/integrity contract PASS')
