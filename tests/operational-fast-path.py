#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def read(p): return (ROOT/p).read_text(encoding='utf-8')
status=read('operator/api_status.php'); quick=read('staff/api_quick_order.php'); quick_service=read('includes/staff_order_service.php'); bill=read('operator/api_bill.php')
inv=read('includes/inventory.php'); funcs=read('includes/functions.php'); push=read('includes/push.php'); runtime=read('assets/js/push-runtime.js')
operator=read('assets/js/operator.js'); waiter=read('assets/js/waiter.js')
for name,body in [('status',status),('bill',bill)]:
    assert 'inventory_process_order_events_for_order' not in body, f'{name}: inventory materialization is still in request critical path'
    assert 'inventory_register_after_response_order' in body, f'{name}: committed inventory event is not scheduled for acceleration'
    # For each API the first scheduled materialization must be after a commit in source order.
    assert body.find('$pdo->commit()') < body.find('inventory_register_after_response_order'), f'{name}: inventory scheduling occurs before commit'
# Quick Order now delegates commit semantics to the canonical Staff Order service.
assert 'inventory_process_order_events_for_order' not in quick and 'inventory_process_order_events_for_order' not in quick_service
assert 'inventory_register_after_response_order' in quick_service, 'quick: committed inventory event is not scheduled for acceleration'
assert quick.find('$pdo->commit()') < quick.find('staff_order_after_commit($result)'), 'quick: after-commit owner is invoked before commit'
assert 'function staff_order_after_commit' in quick_service and quick_service.find('function staff_order_after_commit') < quick_service.find('inventory_register_after_response_order'), 'quick: inventory scheduling escaped after-commit owner'
assert 'function inventory_after_response_drain' in inv and 'function inventory_response_metadata' in inv
assert "asset('api/inventory_kick.php')" in inv
assert 'fastcgi_finish_request' in funcs and 'inventory_after_response_drain' in funcs
assert "data?._inventory?.kick" in runtime and 'inventoryKick' in runtime
assert 'curl_multi_init' in push and 'push_send_deliveries_bounded' in push
assert 'CURLOPT_CONNECTTIMEOUT => 2' in push and 'CURLOPT_TIMEOUT => 6' in push
assert "state.orders=state.orders.filter" in operator and 'window.setTimeout(()=>{ void load' in operator
assert 'tasks=tasks.filter' in waiter and 'window.setTimeout(()=>{void load();},0)' in waiter
print('operational-fast-path: PASS')
