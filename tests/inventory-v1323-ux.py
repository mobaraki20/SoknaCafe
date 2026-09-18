#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')

receive=read('admin/inventory_receive.php')
waste=read('admin/inventory_waste.php')
item_form=read('admin/inventory_item_form.php')
count=read('admin/inventory_count.php')
recipe=read('admin/item_form.php')
inv=read('admin/inventory.php')
item=read('admin/inventory_item.php')
choice=read('assets/js/panel-choice.js')
flow=read('assets/js/inventory-form-flow.js')
css=read('assets/css/inventory.css')

# Structural task-flow contracts.
for text in (receive,waste):
    assert 'data-inventory-item-picker' in text
    assert 'data-choice-search-focus="true"' in text
    assert 'data-inventory-item-select' in text
assert '<noscript>' in receive and '<noscript>' in waste
assert 'data-inventory-operation-form' in receive and 'data-inventory-operation-form' in waste
assert 'inputmode="numeric"' in receive and 'data-money-input' in receive and 'data-inventory-cost-preview' not in receive
assert 'data-inventory-step data-inventory-next-target="[data-inventory-submit]"' in waste
assert 'data-choice-search-focus="true"' in recipe
assert 'data-inventory-autofocus' in item_form
assert 'inventory-purchase-unit-editor' in item_form
assert 'حذف این واحد' in item_form
assert 'review_status"' not in item_form.split('<section class="card">',1)[1].split('<script>',1)[0]
assert 'کالا فعال باشد' not in item_form
assert 'id="inventoryCountForm"' in count
assert 'id="inventoryCountForm"' in count and 'value="review"' in count and 'value="save"' in count
assert 'data-inventory-count-quantity' in count
assert 'inventory-count-group' in count
assert 'movement-extra' not in css
assert '.movement-action' in css and '.movement-cost' in css
assert 'movement-action' in inv and 'movement-action' in item
assert 'explicitSearchFocus' in choice
assert 'actualInput.required' in flow
assert 'form.requestSubmit()' in flow
assert 'Master Data' not in read('admin/inventory_items.php')
assert 'Cost Adjustment' not in read('admin/inventory_adjustment.php')
assert 'برای Audit' not in read('admin/inventory_adjustment.php')

# Browser behavior: touch browse search can opt-in to keyboard focus; explicit selection
# advances without a second Continue tap; Enter follows logical numeric flow; movement
# action/cost remain available on mobile.
base_css='\n'.join(read(p) for p in ['assets/css/tokens.css','assets/css/app.css','assets/css/panel.css','assets/css/panel-layout.css','assets/css/panel-components.css','assets/css/inventory.css'])
html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>:root{{--primary:#365b4c;--line:#ddd5ca;--muted:#6f675f;--font-ui:Tahoma;--panel-line:#ddd5ca;--panel-surface:#fff;--danger:#a33}}{base_css}</style></head><body class="panel-body panel-section-inventory"><main class="panel-content">
<form id="pick" data-inventory-item-picker><select id="item" class="form-control" data-choice-mode="browse" data-choice-search="true" data-choice-search-focus="true" data-inventory-item-select required><option value="" disabled hidden data-choice-placeholder="true" selected>انتخاب کالا</option><option value="1">سیب‌زمینی کاله</option><option value="2">شیر دومینو</option></select></form>
<form id="flow" data-inventory-flow><input id="qty" class="form-control" inputmode="decimal" enterkeyhint="next" data-inventory-step><input id="cost" class="form-control" inputmode="numeric" enterkeyhint="done" data-inventory-step></form>
<article class="inventory-movement"><div class="movement-main"><strong>سیب‌زمینی</strong><small>ورود کالا</small></div><div class="movement-qty"><strong>۱۰ کیلو</strong></div><div class="movement-cost"><strong>۱٬۰۰۰٬۰۰۰ تومان</strong></div><div class="movement-action"><button class="btn btn-light">اصلاح</button></div></article>
</main><script>window.SoknaInteraction={{isKeyboard:()=>false,current:()=> 'touch'}};</script><script>{choice}</script><script>{flow}</script><script>window.picked=false;document.getElementById('pick').addEventListener('submit',e=>{{e.preventDefault();window.picked=true;}});</script></body></html>'''

with sync_playwright() as p:
    b=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    pg=b.new_page(viewport={'width':390,'height':844})
    pg.set_content(html,wait_until='load')
    trigger=pg.locator('#item + .panel-choice-trigger')
    trigger.click(); pg.wait_for_timeout(80)
    search=pg.locator('[data-panel-choice-search] input')
    assert search.is_visible()
    assert pg.evaluate('document.activeElement === document.querySelector("[data-panel-choice-search] input")')
    search.fill('سیب')
    pg.locator('.panel-choice-option:visible',has_text='سیب‌زمینی کاله').click(); pg.wait_for_timeout(80)
    assert pg.evaluate('window.picked === true')
    pg.locator('#qty').focus(); pg.keyboard.press('Enter'); pg.wait_for_timeout(30)
    assert pg.evaluate('document.activeElement && document.activeElement.id === "cost"')
    action=pg.locator('.movement-action'); cost=pg.locator('.movement-cost')
    assert action.is_visible() and cost.is_visible()
    ab=action.bounding_box(); assert ab and ab['height'] >= 44
    assert pg.evaluate('document.documentElement.scrollWidth <= innerWidth + 1')
    b.close()

print('Inventory 1.32.3 UX contracts passed: direct item selection, opt-in touch search focus, guided keyboard flow, compact master-data controls, safe count review, and mobile movement actions/costs.')
