#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
read=lambda rel:(ROOT/rel).read_text(encoding='utf-8')
inv=read('includes/invoices_page.php')
sub=read('includes/subscribers_page.php')
acc=read('admin/accommodation.php')
per=read('admin/financial_periods.php')
css=read('assets/css/panel-components.css')
docs=read('docs/UI_DESIGN_SYSTEM_FA.md')
checks=[]
def ok(cond,msg):
    checks.append((bool(cond),msg))
    if not cond: print('FAIL:',msg)

for src,name in [(inv,'invoices'),(sub,'subscribers'),(acc,'accommodation'),(per,'financial periods')]:
    ok('financial-workspace' in src, f'{name} declares the shared financial workspace owner')

for src,name in [(inv,'invoices'),(sub,'subscribers'),(acc,'accommodation')]:
    ok('financial-page-shell' in src and 'financial-page-head' in src and 'financial-page-toolbar' in src and 'financial-list' in src,
       f'{name} uses the shared Financial Page Shell composition')

ok('financial-filter-layer' in inv and 'data-financial-filter-open' in inv and 'financial-ui.js' in inv,
   'invoice advanced filters use the shared overlay/sheet flow')
ok('invoice-filter-disclosure' not in inv, 'legacy in-page invoice advanced filter disclosure is retired')
ok('financial-document-workspace panel-page-flow' in inv, 'invoice detail remains in the same financial family')
ok('subscriber-directory-workspace panel-page-flow' in sub, 'subscriber directory uses financial page flow')
ok('subscriber-profile-workspace panel-page-flow' in sub, 'subscriber profile uses financial page flow')
ok('financial-history-row' in sub and 'subscriber-ledger-disclosure' in sub, 'subscriber ledger uses one compact financial-history disclosure')
ok('subscriber-ledger-metadata' not in sub, 'subscriber ledger does not repeat invoice registration metadata')
ok('financial-summary-strip' not in sub, 'subscriber summary is owned by the shared page shell, not a separate card')
ok('financial-filter-toggle' in sub and 'فقط بدهکار' in sub, 'subscriber boolean filtering uses compact shared filter control')
ok('accommodation-history-list financial-list' in acc, 'accommodation uses one continuous financial history list')
ok('accommodation-history-desktop' not in acc and 'accommodation-history-mobile' not in acc, 'accommodation has no duplicate desktop/mobile rendering')
ok('format_jalali_human_datetime($eventTime)' in acc, 'accommodation uses the shared human-first date/time language')
ok('mobile-card-table' not in per and 'financial-period-kpis' not in per, 'financial periods keep the modern record/history family')
ok('financial-metric-strip' in per and 'financial-period-list financial-list' in per, 'financial periods use current-record summary plus continuous history')
ok("if((int)$row['active']!==1)" in sub, 'healthy subscriber status stays quiet; inactive remains visible')
ok("if($display['needs_action']||(string)$row['status']!=='posted')" in acc, 'healthy posted accommodation state stays quiet while recovery states remain visible')

for owner in ['.financial-page-shell','.financial-page-head','.financial-page-toolbar','.financial-list','.financial-row','.financial-row-main','.financial-row-amount','.financial-filter-layer','.financial-filter-sheet']:
    ok(owner in css, f'CSS owns {owner}')
for owner in ['.subscriber-profile-workspace .subscriber-profile-top','.subscriber-profile-workspace .subscriber-balance-block','.subscriber-ledger-disclosure>summary']:
    ok(owner in css, f'CSS owns {owner}')
ok('Financial UI Family' in docs, 'design system documents Financial UI Family contract')
ok('Financial Composition v2' in docs, 'design system documents the Composition v2 contract')

if any(not good for good,_ in checks): raise SystemExit(1)
print(f'financial UI family contract passed: {len(checks)} checks')
