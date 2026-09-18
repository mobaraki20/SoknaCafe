#!/usr/bin/env python3
from pathlib import Path
import sys
R=Path(__file__).resolve().parents[1]
inv=(R/'includes/invoices_page.php').read_text(); sub=(R/'includes/subscribers_page.php').read_text(); css=(R/'assets/css/panel-components.css').read_text()
checks={
'human invoice archive/search': 'financial_document_search_token' in inv and 'financial_document_human_label' in inv,
'no archive normal status badge': "if((string)$row['status']!=='completed')" in inv,
'no misleading per-page day count': "fa_digits(count($dayItems))" not in inv,
'receipt shared presenter invoice': 'financial_receipt_presented_items' in inv,
'receipt shared presenter subscriber': 'financial_receipt_presented_items' in sub,
'reversal relation separate': 'reversal_settlement_id' in sub and 'reversal_invoice_number' in sub,
'ledger human time': 'format_jalali_human_datetime' in sub,
'ledger auto filter': 'data-auto-submit' in sub,
'compact metadata responsive': '@media(max-width:379px)' in css and '.invoice-receipt-meta dl{grid-template-columns:1fr}' in css,
'bidi receipt math': 'invoice-line-math' in inv and 'unicode-bidi:isolate' in css,
}
fail=[k for k,v in checks.items() if not v]
if fail:
 print('1.32.15 financial contracts FAILED:', ', '.join(fail)); sys.exit(1)
print('1.32.15 financial contracts PASS')
