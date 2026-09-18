#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
read=lambda rel:(ROOT/rel).read_text(encoding='utf-8')
inv=read('includes/invoices_page.php')
sub=read('includes/subscribers_page.php')
acc=read('admin/accommodation.php')
css=read('assets/css/panel-components.css')
js=read('assets/js/financial-ui.js')
checks=[]
def ok(cond,msg):
    checks.append((bool(cond),msg))
    if not cond: print('FAIL:',msg)

# One page shell per normal index; no independent summary card before the list shell.
ok(inv.count('data-financial-index-shell="invoices"')==1, 'invoice archive has exactly one Financial Page Shell')
ok(sub.count('data-financial-index-shell="subscribers"')==1, 'subscriber directory has exactly one Financial Page Shell')
ok(acc.count('data-financial-index-shell="accommodation"')==1, 'accommodation history has exactly one Financial Page Shell')
ok('financial-summary-strip' not in sub, 'subscriber directory has no duplicate summary surface')

# Shared toolbar/search language.
for src,name in [(inv,'invoice'),(sub,'subscriber'),(acc,'accommodation')]:
    ok('financial-page-toolbar' in src and 'financial-search-field' in src, f'{name} uses shared toolbar/search owner')

# Advanced filters are out of document flow and dismissible via shared gesture owner.
ok('financial-filter-layer hidden' in inv and 'data-financial-filter-sheet' in inv and 'data-financial-filter-handle' in inv, 'invoice advanced filters use overlay sheet markup')
ok('CafeUI.bindSwipeDismiss' in js and 'CafeUI.dialog.open' in js and 'CafeUI.dialog.close' in js, 'financial filter sheet uses shared dialog/swipe owners')
ok('.financial-filter-layer{position:fixed' in css and '@media(max-width:720px)' in css and '.financial-filter-sheet{width:100%' in css, 'filter overlay has mobile bottom-sheet geometry')

# No duplicate information in ledger / normal accommodation rows.
ledger=sub[sub.index('<section class="card subscriber-ledger-card'):sub.index('<?php if($ledgerPages>1):')]
ok('subscriber-ledger-metadata' not in ledger and 'اطلاعات ثبت' not in ledger, 'subscriber ledger does not repeat registration metadata')
ok('financial-receipt-total' not in ledger and 'مبلغ نهایی' not in ledger, 'subscriber ledger does not repeat row amount as a second final total')
ok(ledger.count('subscriber-ledger-disclosure')>=1 and 'ledger-invoice-detail' not in ledger, 'subscriber invoice history has one disclosure owner')
acc_start=acc.index('<section class="card accommodation-history-card')
acc_end=acc.index('<?php if($attentionTotal>0): ?>', acc_start)
normal_acc=acc[acc_start:acc_end]
ok('financial-canonical-meta' not in normal_acc and 'canonical_identifier_html' not in normal_acc, 'normal accommodation rows hide canonical reservation id')
ok('data-row-href=' in normal_acc and 'financial-row-chevron' in normal_acc and 'accommodation-inline-document' not in normal_acc, 'accommodation document destination is owned by the whole financial row')

# Quiet healthy state.
ok("if($display['needs_action']||(string)$row['status']!=='posted')" in normal_acc, 'healthy posted accommodation state remains quiet while posted/local-mismatch stays visible')
ok("if((int)$row['active']!==1)" in sub, 'active subscriber state remains quiet in directory')

# Family tokens/owners are shared rather than page-local values.
for owner in ['.financial-page-shell','.financial-page-head','.financial-page-toolbar','.financial-row','.financial-row-amount','.financial-empty-state']:
    ok(css.count(owner)>=1, f'financial shared owner exists: {owner}')
ok('.subscriber-directory-workspace .subscriber-list-balance' in css and '.accommodation-history-item' in css and '.financial-invoice-workspace .invoice-transaction-row' in css, 'domain-specific rows extend shared primitives instead of replacing them')

if any(not good for good,_ in checks): raise SystemExit(1)
print(f'financial composition contract passed: {len(checks)} checks')
