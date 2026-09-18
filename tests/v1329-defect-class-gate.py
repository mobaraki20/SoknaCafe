#!/usr/bin/env python3
from pathlib import Path
import re, json, sys
ROOT=Path(__file__).resolve().parents[1]
errors=[]
def need(cond,msg):
    if not cond: errors.append(msg)

def text(rel): return (ROOT/rel).read_text(errors='ignore')
php='\n'.join(p.read_text(errors='ignore') for p in list((ROOT/'admin').glob('*.php'))+list((ROOT/'includes').glob('*.php')))
sprite=text('assets/icons/ui-sprite.svg')
icons=set(re.findall(r"ui_icon\(['\"]([^'\"]+)",php))
symbols=set(re.findall(r'id=["\']icon-([^"\']+)',sprite))
missing=sorted(icons-symbols)
need(not missing, f'icon registry missing: {missing}')

# Known escaped raw error class: updater console is diagnostic and excluded.
normal_js='\n'.join(p.read_text(errors='ignore') for p in list((ROOT/'assets/js').glob('*.js'))+list((ROOT/'admin').glob('*.php')))
need('status.textContent=error?.message' not in normal_js, 'raw error.message still reaches normal panel UI')

need('inventory-money-preview' not in text('admin/inventory_receive.php'), 'duplicate inventory money preview markup remains')
need('inventory-money-preview' not in text('assets/js/inventory-form-flow.js'), 'duplicate inventory money preview JS remains')
need('data-money-input' in text('assets/js/panel-core.js') and 'CafeUI.money' in text('assets/js/panel-core.js'), 'shared money owner missing')
for rel, token in [
    ('admin/inventory_receive.php','name="total_cost"'),('admin/item_form.php','name="price"'),('admin/items.php','id="quickItemPrice"'),
    ('admin/event_form.php','name="fee_amount"'),('includes/subscribers_page.php','id="subscriberPaymentAmount"')]:
    src=text(rel); pos=src.find(token); need(pos>=0 and 'data-money-input' in src[pos:pos+500], f'{rel}: money field not owned by data-money-input')

jalali=text('assets/js/panel-jalali.js'); css=text('assets/css/panel-components.css')
need('while (days.length < 42)' in jalali and 'days.slice(0, 42)' in jalali, 'Jalali fixed 42-cell contract missing')
need('shiftMonth(dx < 0 ? 1 : -1)' in jalali, 'Jalali swipe contract missing')
need('grid-template-rows:repeat(6,1fr)' in css.replace(' ',''), 'Jalali fixed six-row CSS missing')

need('.panel-surface-stack{display:grid;gap:18px' in css.replace(' ',''), 'parent-owned surface stack missing')
need('.panel-content>:is(.card' not in css, 'legacy global sibling-card margin patch remains')
for rel in ['admin/qr.php','admin/inventory_item.php']:
    need('panel-surface-stack' in text(rel), f'{rel}: reviewed multi-surface page not migrated to stack')
need('financial-workspace accommodation-page-stack panel-page-flow' in text('admin/accommodation.php'), 'admin/accommodation.php: financial page lacks shared page-flow owner')
need('subscriber-profile-stack panel-page-flow' in text('includes/subscribers_page.php'), 'subscriber profile lacks shared page-flow owner')
need('financial-page-shell financial-index-surface' in text('includes/subscribers_page.php'), 'subscriber directory lacks financial page-shell composition owner')

subs=text('includes/subscribers_page.php')
need('LEFT JOIN settlement_records sr ON sr.subscriber_ledger_entry_id=l.id' not in subs, 'ambiguous free settlement join can duplicate subscriber ledger')
need(('subscriber_ledger_enrich_settlements' in subs and "=== 'invoice_reversal'" in subs and 'reverses_settlement_id IS NULL' in subs) or ('orig_map.subscriber_ledger_entry_id=CASE WHEN l.entry_type' in subs), 'original/reversal ledger settlement mapping missing')
need('ORDER BY l.created_at DESC,l.id DESC' in subs, 'subscriber ledger timeline not explicitly time ordered')
need('financial_receipt_presented_items' in subs, 'subscriber ledger does not use shared receipt presenter')
need('financial_receipt_presented_items' in text('includes/invoices_page.php'), 'invoice detail does not use shared receipt presenter')
need('financial_document_search_token' in text('includes/invoices_page.php'), 'invoice search does not accept human reference')

items=text('assets/js/items-management.js')
need('initialDrawerSnapshot' in items and 'تغییرات ذخیره نشده‌اند' in items, 'quick edit dirty-close protection missing')
need("overflow:hidden" in text('assets/css/items-management.css').replace(' ',''), 'quick edit drawer scroll ownership missing')
need('data-inventory-add-field' in text('admin/inventory_receive.php') and 'data-inventory-add-field' in text('admin/inventory_waste.php'), 'progressive inventory metadata fields missing')
need("inventory_occurrence_precision" in text('includes/inventory.php'), 'date-first/time-optional inventory precision contract missing')

need('هر تغییر در یک استاندارد UI' in text('docs/UI_DESIGN_SYSTEM_FA.md'), 'living UI standards change rule missing')
need('Defect-class Gate' in text('docs/TESTING_FA.md'), 'QA defect-class process missing')
need((ROOT/'tests/defect_class_registry.json').exists() and (ROOT/'tests/uat_matrix.json').exists(), 'test registry/matrix missing')

# obvious blind toggle forms in sensitive PHP. Exclude presentation ternaries.
for p in (ROOT/'admin').glob('*.php'):
    src=p.read_text(errors='ignore')
    if re.search(r"\bnext\s*=\s*[^;]*(?:\?\s*0\s*:\s*1|1\s*-)",src): errors.append(f'{p.name}: blind toggle pattern')

if errors:
    print('1.32.15 defect-class gate FAILED')
    for e in errors: print(' -',e)
    sys.exit(1)
print('1.32.15 defect-class gate PASS')
