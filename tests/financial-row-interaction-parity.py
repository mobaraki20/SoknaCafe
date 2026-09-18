#!/usr/bin/env python3
from pathlib import Path
import json
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')
acc=read('admin/accommodation.php')
inv=read('includes/invoices_page.php')
sub=read('includes/subscribers_page.php')
period=read('admin/financial_periods.php')
js=read('assets/js/panel-core.js')
registry=json.loads(read('tests/defect_class_registry.json'))
checks=[]
def ok(c,m):
    checks.append((bool(c),m))
    if not c: print('FAIL:',m)

ok('data-row-href=' in acc and 'role="link" tabindex="0"' in acc, 'accommodation invoice destination belongs to the whole row')
ok('accommodation-inline-document' not in acc, 'accommodation no longer requires a tiny invoice-only hit target')
ok('accommodation_reservation_human_label' in acc and '<strong><?= e(accommodation_reservation_human_label' in acc, 'accommodation human reservation reference is primary')
ok('financial_document_human_label' in inv and '<strong><?= e(financial_document_human_label' in inv, 'invoice archive human document reference is primary')
ok("if((int)$subscriber['active']!==1)" in sub or "if((int)$row['active']!==1)" in sub, 'subscriber healthy active status is quiet')
ok('data-row-href=' in period and 'financial-period-row financial-row is-navigable' in period, 'financial-period history has whole-row navigation')
ok("const rowNavigationInteractive" in js and "a[href],button,input,select,textarea,summary,details,form" in js, 'shared row navigation ignores nested utility controls')
ok("event.key !== 'Enter'" in js and "window.location.assign" in js, 'shared row navigation supports keyboard Enter')
ok(any(x.get('id')=='financial_row_interaction_parity' for x in registry.get('classes',[])), 'defect class registered')
if any(not c for c,_ in checks): raise SystemExit(1)
print(f'financial row interaction parity PASS: {len(checks)} checks')
