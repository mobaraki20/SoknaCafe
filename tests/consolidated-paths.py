#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
errors=[]
def text(p):return (ROOT/p).read_text(encoding='utf-8')
def exactly(p,n,c=1):
 a=text(p).count(n)
 if a!=c:errors.append(f'{p}: expected {c} occurrences of {n!r}, found {a}')
exactly('admin/items.php','item_form.php?copy_from=')
exactly('admin/events.php','event_form.php?copy_from=')
exactly('index.php','id="searchPanel"')
exactly('index.php','id="menuSearch"')
exactly('index.php','class="waiter-fab"')
exactly('includes/operator_page.php','id="operatorControls"')
exactly('operator/index.php','render_operator_page();')
exactly('admin/settings.php','image_picker_html(')
if (ROOT/'staff/shift.php').exists():errors.append('Legacy shift responsibility route remains in the project.')
if 'مسئولیت شیفت' in text('waiter/index.php'):errors.append('Shift responsibility remains in daily staff UI.')
if 'waiterTables' in text('waiter/index.php'):errors.append('Ordinary table cards remain duplicated in the action queue.')
if 'public_code LIKE ?' in text('operator/api_orders.php'):errors.append('Technical public-code search remains.')
if errors:raise SystemExit('\n'.join(errors))
print('Consolidated-path audit passed: one active path for search, tables, quick order, café work queue and updater.')
