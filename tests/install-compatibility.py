#!/usr/bin/env python3
from pathlib import Path
import re
root=Path(__file__).resolve().parents[1]
schema=(root/'database/schema.sql').read_text(encoding='utf-8')
installer=(root/'install.php').read_text(encoding='utf-8')
functions=(root/'includes/functions.php').read_text(encoding='utf-8')
waiter=(root/'api/waiter_call.php').read_text(encoding='utf-8')
table_api=(root/'operator/api_table_session.php').read_text(encoding='utf-8')

assert 'GENERATED ALWAYS' not in schema
assert "live_table_guard INT UNSIGNED NULL" in schema
assert "active_table_guard INT UNSIGNED NULL" in schema
assert 'UNIQUE KEY uq_table_sessions_one_live_table (live_table_guard)' in schema
assert 'UNIQUE KEY uq_waiter_calls_one_active_table (active_table_guard)' in schema
assert "ON UPDATE RESTRICT ON DELETE RESTRICT" in schema
assert len(re.findall(r'^CREATE TABLE IF NOT EXISTS ', schema, flags=re.M)) >= 32
for table in ['invoice_discount_audit','subscribers','subscriber_ledger']:
    assert f'CREATE TABLE IF NOT EXISTS {table}' in schema
assert 'user_shift_responsibilities' not in schema
assert "extension_loaded($extension)" in installer and "'pdo_mysql'=>'PDO MySQL'" in installer
assert "'sodium'=>'Sodium'" in installer
assert 'install_assert_empty_database' in installer
assert 'install_probe_privileges' in installer
assert 'install_drop_created_tables' in installer
assert "MySQL 5.7.8 یا MariaDB 10.2" in installer
assert "live_table_guard,continued_from_session_id" in functions
assert "status='closed',live_table_guard=NULL" in functions
assert "active_table_guard,business_date,business_shift_key,business_shift_label,business_cutoff_snapshot) VALUES" in waiter
assert "status='cancelled',active_table_guard=NULL" in waiter
assert "SET table_id=?,active_table_guard=?,session_id=?" in table_api
assert "favicon_32_path" in installer and "1.30.1-rc2-baseline" in installer
assert "operator_user" not in installer and "operator_pass" not in installer
assert "waiter_table_assignments" not in schema and "staff_order_acknowledgements" not in schema
assert "phone VARCHAR" not in schema
print('Install compatibility checks passed.')
