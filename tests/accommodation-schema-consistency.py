#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
read = lambda p: (ROOT / p).read_text(encoding='utf-8')
schema = read('database/schema.sql')

for token in ['settlement_destination','checkout_voided_at','checkout_voided_by_user_id','fk_table_sessions_checkout_voided_by']:
    assert token in schema, f'Missing current session field: {token}'
for token in [
    'session_id','external_order_id','reservation_code','guest_name_snapshot',
    'room_name_snapshot','amount','status','remote_transaction_id',
    'remote_void_transaction_id','remote_original_transaction_id','last_attempt_at',
    'posted_at','voided_at','attempt_count','last_error','operator_user_id','resolved_at',
]:
    assert token in schema, f'Missing transfer field: {token}'
for token in ['UNIQUE KEY uq_accommodation_transfer_session','UNIQUE KEY uq_accommodation_external_order']:
    assert token in schema, f'Missing idempotency constraint: {token}'
assert 'checkout_payment_method' not in schema
assert not (ROOT / 'upgrade.php').exists()
for path in ROOT.rglob('*.php'):
    text = path.read_text(encoding='utf-8', errors='ignore')
    assert 'DELETE FROM accommodation_transfers' not in text, f'Financial transfer deletion found: {path.relative_to(ROOT)}'
ops=read('admin/operations_report.php')
assert "settlement_records sr" in ops and "sr.destination='accommodation'" in ops and 'accommodationVisible' in ops
print('Accommodation schema consistency passed: canonical schema, idempotency and reversals agree.')
