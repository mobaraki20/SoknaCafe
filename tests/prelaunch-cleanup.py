#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
read = lambda p: (ROOT / p).read_text(encoding='utf-8')

version=read('VERSION.txt').strip(); assert re.fullmatch(r'\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?',version)
assert not (ROOT / 'database/migrations').exists(), 'historical database migration archive must be removed pre-launch'
assert not (ROOT / 'migrations').exists(), 'historical updater migration archive must be removed pre-launch'

engines = sorted(p.name for p in (ROOT / 'includes/updater_engine').iterdir() if p.is_dir())
assert engines == ['1.5.1','1.5.2','1.5.3'], engines
builder = read('tools/build-release.php')
assert "'min_updater'=>'1.5.1'" in builder
assert "str_starts_with($path, 'includes/updater_engine/')" not in builder, 'builder must allow obsolete engine cleanup while protecting active engine files'
assert '$protectedUpdaterPaths' in builder

# Git history is the release archive; the active source keeps only current/canonical documentation.
doc_names = [p.name for p in (ROOT / 'docs').iterdir() if p.is_file()]
for name in doc_names:
    assert not re.match(r'(?:CHANGELOG|GO_LIVE_CHECKLIST|RELEASE_NOTES|RELEASE_RECORD|SCOPE|TEST_REPORT)_V1\.(?:2[0-9]|3[0-5])', name), name
for stale in ['RELEASE_HISTORY_FA.md','SOKNA_CHANGE_REGISTER.md','CHANGE_SNAPSHOT_MORNING_GORGAN_FA.md','WORKING_CHANGELOG_AFTER_1.33.0_FA.md']:
    assert stale not in doc_names, stale
for current in ['ARCHITECTURE_MODULE_MAP_1.36_FA.md','SUPPLY_WORKFLOW_1.36_FA.md','TEST_REPORT_V1.36.3_FINAL_FA.md','PRELAUNCH_CLEANUP_FA.md']:
    assert current in doc_names, current

schema = read('database/schema.sql')
for retired in ['waiter_table_assignments','staff_order_acknowledgements','user_shift_responsibilities','order_preparation_state','inventory_purchase_cycles','inventory_purchase_lines','retention_runs']:
    assert retired not in schema, retired

# Pre-release URL/settings compatibility is deliberately removed.
reporting = read('includes/reporting.php')
assert "$_GET['days']" not in reporting and 'Backward compatibility with 1.32.4' not in reporting
printing = read('admin/printing.php')
assert "$_GET['download_template']" not in printing
form = read('admin/event_form.php')
assert '$legacyFee' not in form
messages = read('includes/functions.php') + read('includes/function_domains/messages.php')
assert 'legacy_defaults' not in messages and 'legacy_setting' not in messages
assert 'normalize_staff_quick_order_entries' not in messages and 'normalize_staff_quick_order_lines' not in messages
assert 'unusedLegacyServiceAt' not in messages and 'allowAdminRecovery' not in messages

release_gate = read('tests/run-release-gate.sh')
assert 'VERSION.txt' in release_gate and 'updater-current-contract.py' in release_gate and 'updater-current-browser.py' in release_gate
for obsolete_test in ['final-invariants.py','v1350-supply-takeaway-contract.py','menu-catalog-migration-contract.py','updater-v150-contract.py','updater-v150-browser.py','v1350-operations-browser.py']:
    assert not re.search(r'(?<![A-Za-z0-9_-])' + re.escape(obsolete_test) + r'(?![A-Za-z0-9_-])', release_gate), obsolete_test
assert not (ROOT / 'includes/supply.php').exists(), 'Supply legacy include owner must not return'

modules = read('includes/modules.php')
assert 'retired_tables' not in modules
supply = read('modules/Supply/domain.php')
assert "preg_match('/^need:" not in supply, 'obsolete pre-aggregation Supply group key must be removed'

print('PASS pre-launch cleanup: active tree contains current schema/docs/updater only; pre-release compatibility artifacts are removed.')
