#!/usr/bin/env python3
from pathlib import Path
import re
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')
func=read('includes/functions.php'); inv=read('includes/invoices_page.php'); subs=read('includes/subscribers_page.php'); item=read('admin/item_form.php'); acc=read('admin/accommodation.php'); pc=read('assets/css/panel-components.css'); core=read('assets/js/panel-core.js')

# Financial human references: canonical IDs remain available for audit/search, but UI is human-first.
assert 'function financial_document_human_label' in func and "'فاکتور'" in func and "'سند برگشت'" in func
assert "financial_document_human_label((string)$row['invoice_number'])" in inv
assert "financial_document_human_label((string)$detail['invoice_number'])" in inv
assert 'canonical_identifier_html((string)$detail[\'invoice_number\'])' in inv
assert 'invoice-receipt-amount' not in inv
assert "if($isReversal || (int)$detail['discount']!==0)" in inv
assert 'panel-icon-action' in inv and 'aria-label="چاپ مجدد فاکتور"' in inv
assert 'panel-disclosure invoice-receipt-meta' in inv
assert '$timeLabel' in inv and 'format_jalali_datetime((string)$detail[\'settled_at\']' not in inv

# Subscriber profile/list follows the same financial language and safe payment contract.
assert 'parse_toman_amount_text' in subs
assert "form_old($paymentState,'amount','')" in subs
assert 'money_input_display_value' in subs and 'data-fill-money-target="subscriberPaymentAmount"' in subs
assert 'subscriber-profile-name-row' in subs and 'panel-icon-action' in subs
assert 'panel-disclosure subscriber-payment-card' in subs
assert 'financial_document_human_label' in subs
assert re.search(r'<a class="[^"]*subscriber-list-card[^"]*financial-row[^"]*"', subs) and '>ویرایش</a>' not in subs.split('subscriber-card-list',1)[1]
assert "REPLACE(REPLACE(s.name,'ي','ی'),'ك','ک') LIKE ?" in subs

# Recipe: disclosure owns the section, PHP owns initial cost, JS only becomes the live editor after user changes.
assert 'form-disclosure full recipe-disclosure' in item
assert 'recipeDisclosureSummary' in item and 'inventory_recipe_cost_preview(db(),$recipeRows,$recipeSalePrice)' in item
assert 'rows().forEach(row=>bind(row,true))' in item
assert 'syncDuplicateOptions();syncCost();\n})();' not in item
assert 'if(disclosure)disclosure.open=true' in item

# Numeric form values are localized by PHP as well as JS; no user-facing numeric value relies only on JS.
assert 'function numeric_input_display_value' in func and 'function money_input_display_value' in func
assert 'data-fill-money-target' in core
bad=[]
for path in list((ROOT/'admin').rglob('*.php'))+list((ROOT/'includes').rglob('*.php')):
    text=path.read_text(encoding='utf-8',errors='ignore')
    for match in re.finditer(r'<input[^>]*inputmode="(?:numeric|decimal)"[^>]*>',text):
        tag=match.group(0)
        if 'value=' not in tag or 'value=""' in tag: continue
        if any(token in tag for token in ('fa_digits','numeric_input_display_value','money_input_display_value','data-latin-digits','ltr-input')): continue
        bad.append((str(path.relative_to(ROOT)), text[:match.start()].count('\n')+1, tag[:180]))
assert not bad,bad

# Surface/utility contracts are shared, and accommodation uses the same human invoice reference.
assert '.panel-surface-stack{display:grid;gap:18px' in pc.replace(' ','')
assert '.panel-content>:is(.card' not in pc
assert '.panel-icon-action{' in pc and '.panel-disclosure{' in pc
assert "financial_document_human_label((string)$row['invoice_number'])" in acc
print('1.32.14 panel experience contracts PASS: financial list/detail continuity, Persian numeric SSR, recipe persistence, subscriber workflow, utility actions, and parent-owned surface spacing.')
