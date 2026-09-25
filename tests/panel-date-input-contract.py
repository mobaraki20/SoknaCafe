#!/usr/bin/env python3
from pathlib import Path
import re
ROOT=Path(__file__).resolve().parents[1]
roots=['admin','operator','waiter','staff','includes']
violations=[]
pat=re.compile(r'type\s*=\s*["\'](?:date|datetime-local|month)["\']',re.I)
for base in roots:
    for p in (ROOT/base).rglob('*.php'):
        if 'admin/update/' in p.as_posix():
            continue
        text=p.read_text(encoding='utf-8',errors='ignore')
        if pat.search(text): violations.append(str(p.relative_to(ROOT)))
assert not violations, 'Native Gregorian date controls remain: '+', '.join(violations)
required={
 'admin/event_form.php':['eventStartDateJ','eventEndDateJ'],
 'admin/campaign_form.php':['campaignStartDateJ','campaignEndDateJ'],
 'admin/item_form.php':['itemScheduleStartDateJ','itemScheduleEndDateJ'],
 'admin/tag_form.php':['tagStartDateJ','tagEndDateJ'],
 'includes/invoices_page.php':['invoiceFromDateJ','invoiceToDateJ'],
 'admin/inventory_receive.php':['inventoryReceiveDate'],
 'admin/inventory_waste.php':['inventoryWasteDate'],
 'admin/purchases.php':['purchaseReceiveDate'],
 'admin/purchases_batch.php':['batchReceiveDate'],
 'admin/expenses.php':['expenseOccurredDate'],
 'admin/tax.php':['taxEffectiveDate','taxPolicyEffectiveDate'],
}
seen=[]
for rel,ids in required.items():
    text=(ROOT/rel).read_text(encoding='utf-8')
    for id_ in ids:
        assert f'id="{id_}"' in text and f'data-open-jalali="{id_}"' in text, (rel,id_)
        seen.append(id_)
# Guard against silently adding a date field outside the shared owner.
actual=[]
for base in roots:
    for p in (ROOT/base).rglob('*.php'):
        text=p.read_text(encoding='utf-8',errors='ignore')
        actual += [x for x in re.findall(r'<input\b[^>]*\bid=["\']([^"\']+)["\'][^>]*\bdata-jalali-date\b[^>]*>',text,re.I|re.S) if not x.startswith('<?=')]
# Report range date controls are emitted dynamically by the shared reporting owner; static controls remain exact.
assert sorted(actual)==sorted(seen),(sorted(actual),sorted(seen))
js=(ROOT/'assets/js/panel-jalali.js').read_text(encoding='utf-8')
assert "const SELECTOR = '[data-jalali-date]'" in js
assert 'enhanceWithin(document)' in js and 'MutationObserver' in js
assert "event.target.closest(SELECTOR)" in js
assert "input.setAttribute('inputmode', 'none')" in js
assert 'input.readOnly = true' not in js, 'Readonly would disable required/constraint validation on required date fields.'
assert "event.target === dialog" in js and "outside" in js
assert 'panel-jalali.js' in (ROOT/'includes/panel_layout.php').read_text(encoding='utf-8')
ops=(ROOT/'admin/operations_report.php').read_text(encoding='utf-8')
reporting=(ROOT/'includes/reporting.php').read_text(encoding='utf-8')
assert "report_render_range_fields($range,'operationsReport')" in ops
assert "$fromId = $idPrefix . 'FromDateJ'" in reporting and "$toId = $idPrefix . 'ToDateJ'" in reporting
assert reporting.count('data-panel-condition-source="<?= e($selectId) ?>" data-panel-condition-value="custom"') == 2
assert 'data-open-jalali="<?= e($fromId) ?>"' in reporting and 'data-open-jalali="<?= e($toId) ?>"' in reporting
assert 'assets/js/panel-conditions.js' in (ROOT/'includes/panel_layout.php').read_text(encoding='utf-8')
assert 'queueMicrotask(' not in ops and 'observer.observe(period' not in ops
assert 'name="action" value="export"' in ops and 'http_build_query(array_merge($queryBase' not in ops
print(f'Panel date-input contract passed: {len(seen)} current panel date fields all use the shared Jalali owner; no native Gregorian controls; mobile picker-only/backdrop-close contract and operations-report custom-range/export contract are present.')
