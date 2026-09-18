#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')
sub=read('includes/subscribers.php')
sub_page=read('includes/subscribers_page.php')
schema=read('database/schema.sql')
invoice=read('includes/invoices_page.php')
printing=read('includes/printing.php')
settle_api=read('operator/api_settlements.php')
operator=read('assets/js/operator.js')
acc=read('admin/accommodation.php')
acc_core=read('includes/accommodation.php')
css=read('assets/css/panel-components.css')
functions=read('includes/functions.php')

# Subscriber payment retries must resolve to one durable ledger entry.
assert 'idempotency_key VARCHAR(190) NULL' in schema
assert 'uq_subscriber_ledger_idempotency' in schema
assert 'subscriber:payment:' in sub_page and "name=\"request_token\"" in sub_page
assert 'WHERE idempotency_key=? LIMIT 1 FOR UPDATE' in sub
assert "'idempotent'=>true" in sub and 'idempotency_key' in schema
# Do not accidentally add the new subscriber key to unrelated finance tables.
subscriber_chunk=schema.split('CREATE TABLE IF NOT EXISTS subscriber_ledger',1)[1].split(') ENGINE=InnoDB',1)[0]
assert 'idempotency_key VARCHAR(190) NULL' in subscriber_chunk

# Reprint is intentional-repeat capable, but the same UI request is duplicate-safe.
assert '?string $reprintIdempotencyKey = null' in printing
assert 'final.reprint.settlement.' in settle_api and "request_id" in settle_api
assert "requestId('settlement-reprint')" in operator
assert "if(empty($job['duplicate'])) audit_log_write" in settle_api

# Financial presentation keeps canonical IDs canonical and separates business date from event time.
assert 'canonical_identifier_html' in invoice and "business_date" in invoice and "settled_at" in invoice
assert "if ($filterError !== '') $conditions[] = '0=1';" in invoice
assert "status']==='reversal'?'− '" in invoice or "$sign = $isReversal ? -1 : 1" in invoice
assert 'financial-filter-layer' in invoice and 'data-financial-filter-sheet' in invoice and 'detailId > 0' in invoice
assert 'fa_digits((string)$detail[\'invoice_number\'])' not in invoice
assert 'function canonical_identifier_html' in functions

# Accommodation is exception-first and resolved failures do not masquerade as open failures.
assert 'accommodation_attention_rows(10)' in acc
assert 'همه انتقال‌ها تعیین تکلیف شده‌اند' not in acc
assert 'accommodation-issues-card' in acc and 'if($attentionTotal>0)' in acc
assert 'accommodation_transfer_display' in acc_core and 'تسویه با روش دیگر' in acc_core
assert 'accommodation_transfer_effective_time' in acc_core
assert 'accommodation_transfer_table_snapshot' in acc_core
assert 'last_error\']) ?></small>' not in acc
assert 'accommodation_public_error_message' in acc

# One semantic owner for voided/reversal badge in panel-components.
assert css.count('.badge-voided') == 1, css.count('.badge-voided')
print('v1.32.15 financial hardening contracts passed: subscriber payment idempotency, reprint request safety, canonical financial presentation, advanced-filter sheet, and exception-first accommodation history.')
