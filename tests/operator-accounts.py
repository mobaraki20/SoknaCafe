#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];read=lambda p:(ROOT/p).read_text(encoding='utf-8')
schema=read('database/schema.sql');settlement=read('includes/settlement.php');bill=read('operator/api_bill.php');sub=read('operator/api_subscribers.php');subs=read('includes/subscribers.php');page=read('includes/operator_page.php');today=read('operator/api_settlements.php')
for token in ['invoice_discount_audit','subscribers','subscriber_ledger','settlement_records','settlement_destination']: assert token in schema
assert 'checkout_payment_method' not in schema and 'discount_reason' not in schema
for d in ["'direct' =>","'accommodation' =>","'subscriber' =>"]: assert d in settlement
assert 'payment_method' not in read('operator/api_table_session.php')
assert 'invoice_discount_audit' in bill and "audit_log_write_strict($pdo, 'invoice.discount_changed'" in bill
assert "action:'reprint_prep'" in read('assets/js/operator.js') and 'print_enqueue_prep_reprint' in bill
assert 'data-move-table' in page and 'انتقال به میز دیگر' not in page
assert 'data-settlement="direct"' in page and 'data-settlement="subscriber"' in page
assert "subscriber_insert_ledger_locked(" in sub and "'invoice'" in sub
assert 'uq_subscriber_invoice_session' not in schema and 'idx_subscriber_ledger_session (table_session_id)' in schema and 'invoice_snapshot_json' in schema
assert 'reverses_settlement_id' in schema and "status='reversal'" in settlement
assert 'MAX(balance_after)' not in subs and 'ORDER BY id DESC LIMIT 1' in subs
assert 'manual' not in sub.lower() and "'adjustment'" not in sub.lower()
assert 'subscriber_reverse_entry_locked' in today and 'settlement_reopen_locked' in today
assert 'settlement_today' in settlement and 'final_print_job_id' in schema
print('Operator account model passed: three destinations, reason-free audited discount, immutable subscriber ledger, today settlements, reprint and safe reversal/reopen.')
