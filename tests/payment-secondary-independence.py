#!/usr/bin/env python3
from pathlib import Path
import re,json
ROOT=Path(__file__).resolve().parents[1]
settlement=(ROOT/'includes/settlement.php').read_text(encoding='utf-8')
routes={p:(ROOT/p).read_text(encoding='utf-8') for p in ['operator/api_table_session.php','operator/api_subscribers.php','operator/api_accommodation.php']}
assert 'function settlement_enqueue_final_print_best_effort_locked' in settlement
helper=settlement[settlement.index('function settlement_enqueue_final_print_best_effort_locked'):settlement.index('/**\n * Finalize one allocation payment',settlement.index('function settlement_enqueue_final_print_best_effort_locked'))]
for needle in ["$savepoint = 'settlement_print_side_effect'","SAVEPOINT ' . $savepoint","ROLLBACK TO SAVEPOINT ' . $savepoint","RELEASE SAVEPOINT ' . $savepoint",'print_enqueue_final_invoice','enqueue_failed']:
    assert needle in helper, needle
assert 'settlement_enqueue_final_print_best_effort_locked($pdo, $settlementId, $destination, $actorUserId, $printFinal)' in settlement
assert re.search(r"'print_requested'\s*=>\s*\$printFinal", settlement)
assert re.search(r"'print_queued'\s*=>", settlement) and re.search(r"'print_warning'\s*=>", settlement)
for path,src in routes.items():
    assert not re.search(r'\$printFinal\s*&&\s*!print_destination_ready',src), path
    assert not re.search(r'if\(\$printFinal&&!print_destination_ready',src), path
assert 'print_warning' in routes['operator/api_table_session.php']
assert 'print_warning' in routes['operator/api_subscribers.php']
assert 'print_warning' in (ROOT/'includes/accommodation.php').read_text(encoding='utf-8')
print('Payment secondary independence contracts passed: settlement no longer pre-blocks on printer readiness, print enqueue is savepoint-isolated/fail-soft, and explicit print failure returns a settlement-success warning.')
