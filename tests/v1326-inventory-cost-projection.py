#!/usr/bin/env python3
from pathlib import Path
import subprocess
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')
core=read('includes/inventory.php')

assert 'function inventory_projection_replay_rows' in core
assert 'function inventory_projection_replay_locked' in core
assert 'function inventory_rebuild_projection_locked' in core
assert "in_array($type, ['quantity_correction','cost_adjustment'], true)" in core
assert 'late correction cannot dump the whole historical difference' in core
assert "'unit_cost_snapshot'=>$component['average_unit_cost']" in core
assert "'cost_status'=>(string)($component['balance_cost_status'] ?? 'unknown')" in core
assert "'lock_cost_basis'=>$snapshotHasCostBasis" in core
assert "$effectiveAt = trim((string)($event['created_at'] ?? '')) ?: null;" in core
assert 'inventory_apply_order_consumption_locked' in core and '$effectiveAt);' in core
assert 'inventory_rebuild_projection_locked($pdo,$itemId)' in core
subprocess.run(['php',str(ROOT/'tests/v1326-inventory-cost-projection.php')],check=True,cwd=ROOT)
print('v1.32.14 inventory cost projection contracts passed: accounted-time cost snapshots, effective event time and chronological rebuild are wired to the immutable ledger.')
