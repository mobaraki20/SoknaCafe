#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
runtime=(ROOT/'includes/updater_engine/1.5.3/runtime.php').read_text(encoding='utf-8')
console=(ROOT/'includes/updater_engine/1.5.3/console.php').read_text(encoding='utf-8')
health=(ROOT/'includes/schema_health.php').read_text(encoding='utf-8')
schema=(ROOT/'database/schema.sql').read_text(encoding='utf-8')
assert 'function sokna_schema_health_checks(PDO $pdo): array' in health
assert "TABLE_NAME='settlement_records'" in health
assert "COLUMN_NAME='request_id'" in health
assert "INDEX_NAME='uq_settlement_request_id'" in health
assert "cafe_table_number_not_null" in health
assert "['table_sessions', 'orders', 'waiter_calls', 'settlement_records']" in health
assert "['business_date', 'business_shift_key', 'business_shift_label', 'business_cutoff_snapshot']" in health
assert 'request_id VARCHAR(96) NULL' in schema
assert 'UNIQUE KEY uq_settlement_request_id (request_id)' in schema
assert "sru_root() . '/includes/schema_health.php'" in runtime
assert "str_starts_with($name, 'schema_')" in runtime
assert "'migration_required'" in runtime and "'migration_applied'" in runtime
assert "'schema_health'" in runtime
assert 'ساختار حیاتی دیتابیس' in console
assert 'تغییر دیتابیس:' in console
assert '.version-now b{font-size:17px;white-space:nowrap' in console
print('Pre-RC updater/schema observability PASS: current critical DB schema is read-only checked, migration outcome is explicit, and long dev versions stay on one line.')
