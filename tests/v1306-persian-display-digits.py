#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
subs=(ROOT/'includes/subscribers_page.php').read_text(encoding='utf-8')
inv=(ROOT/'includes/invoices_page.php').read_text(encoding='utf-8')
acc=(ROOT/'admin/accommodation.php').read_text(encoding='utf-8')
analytics=(ROOT/'admin/analytics.php').read_text(encoding='utf-8')
func=(ROOT/'includes/functions.php').read_text(encoding='utf-8')
about=(ROOT/'about.php').read_text(encoding='utf-8')
panel=(ROOT/'includes/panel_layout.php').read_text(encoding='utf-8')
choice=(ROOT/'assets/js/panel-choice.js').read_text(encoding='utf-8')
operator=(ROOT/'assets/js/operator.js').read_text(encoding='utf-8')
waiter=(ROOT/'assets/js/waiter.js').read_text(encoding='utf-8')
quick=(ROOT/'assets/js/staff-quick-order.js').read_text(encoding='utf-8')
guest=(ROOT/'assets/js/menu.js').read_text(encoding='utf-8')
tables=(ROOT/'admin/tables.php').read_text(encoding='utf-8')
qr=(ROOT/'admin/qr.php').read_text(encoding='utf-8')
assert "fa_digits((string)$row['mobile'])" in subs
assert "fa_digits((string)$view['mobile'])" in subs
assert "fa_digits((string)$detail['subscriber_mobile'])" in inv
assert "canonical_identifier_html((string)$detail['invoice_number'])" in inv
assert "financial_document_human_label((string)$row['invoice_number'])" in inv
assert "canonical_identifier_html((string)$row['reservation_code'])" in acc
assert acc.count("fa_digits((string)$row['room_name_snapshot'])") >= 2
assert 'fa_digits($tableName)' in acc
assert "fa_digits(setting('public_phone'))" in about
assert "return 'سفارش ' . fa_digits((string)order_display_number($order));" in func and 'function order_display_code' in func
assert 'toman_number((int)$r[\'total\'])' in analytics and 'analytics-money-cell' in analytics
assert 'toman_number((int)$r[\'revenue\'])' in analytics
assert 'panel-choice.js' in panel and 'panel-choice-source' in choice
assert 'faTextDigits' in operator and 'faTextDigits' in waiter
assert 'textDigits(table.name)' in quick and 'textFaDigits(table.name)' in guest
assert "fa_digits((string)$table['name'])" in tables
assert "fa_digits((string)$t['name'])" in qr and "fa_digits((string)$single['name'])" in qr
print('Persian display-digit contract passed: user-facing quantities/phones and human invoice references stay Persian while canonical audit IDs remain ASCII LTR; analytics money rows stay localized.')
