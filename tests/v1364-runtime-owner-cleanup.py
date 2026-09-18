from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
RUNTIME=[
    'assets/js/operator.js','assets/js/staff-quick-order.js',
    'assets/css/operator-live.css','assets/css/quick-order.css',
    'includes/operator_page.php','staff/quick-order.php',
]
text='\n'.join((ROOT/p).read_text(encoding='utf-8') for p in RUNTIME)
banned=[
    'table-account-return-v13','table-overview-toolbar-v10','table-sort-mobile-v139',
    'table-sort-select-v13','table-account-checkout-v10','is-mobile-compact-v19',
    'itemized-mobile-balance-v19','table-account-order-success-v16','quick-order-cart-more-v13',
    'quick-order-cart-more-menu-v13','data-qo-clear-proxy-v13','data-qo-global-note-v13',
    'bill-table-current-v5',
]
found=[token for token in banned if token in text]
if found: raise SystemExit('FAIL: temporary runtime owners remain: '+', '.join(found))
# The user-visible review screen was removed; server review remains the single financial safety owner.
page=(ROOT/'includes/operator_page.php').read_text(encoding='utf-8')
js=(ROOT/'assets/js/operator.js').read_text(encoding='utf-8')
if 'id="itemizedReviewStep"' in page: raise SystemExit('FAIL: obsolete itemized review UI still exists')
if "action:'checkout_itemized_review'" not in js: raise SystemExit('FAIL: server-side itemized review safety owner missing')
# Current invoice selector must exist in both runtime DOM and canonical desktop CSS.
css=(ROOT/'assets/css/operator-live.css').read_text(encoding='utf-8')
if 'bill-table bill-table-current' not in js or '.bill-table-current' not in css:
    raise SystemExit('FAIL: current bill DOM/CSS ownership is not aligned')
print('PASS: runtime owner cleanup has no recent preview/version patch owners')
