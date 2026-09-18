#!/usr/bin/env python3
from pathlib import Path
import json
from playwright.sync_api import sync_playwright
R=Path(__file__).resolve().parents[1]
css='\n'.join((R/p).read_text(encoding='utf-8') for p in ['assets/css/tokens.css','assets/css/app.css','assets/css/responsive.css','assets/css/panel.css','assets/css/panel-layout.css','assets/css/panel-components.css','assets/css/inventory.css'])
supply_js=(R/'assets/js/supply-needs.js').read_text(encoding='utf-8')
purchase_js=(R/'assets/js/supply-purchases.js').read_text(encoding='utf-8')

catalog=[
 {'id':1,'name':'شیر','base_unit':'ml','unit_label':'لیتر','current':'۳ لیتر','open_need_id':9,'open_quantity':'2','open_quantity_label':'۲ لیتر','preparing_quantity_label':'۱۰ لیتر','open_note':'برای بار و آشپزخانه'},
 {'id':2,'name':'خامه','base_unit':'count','unit_label':'عدد','current':'۲ عدد','open_need_id':10,'open_quantity':'','open_quantity_label':'','preparing_quantity_label':'۶ عدد','open_note':''},
]
open_needs=[
 {'id':9,'item_id':1,'name':'شیر','base_unit':'ml','unit_label':'لیتر','uncommitted_major':'2','uncommitted_label':'۲ لیتر','preparing_label':'۱۰ لیتر','note':'برای بار و آشپزخانه'},
 {'id':10,'item_id':2,'name':'خامه','base_unit':'count','unit_label':'عدد','uncommitted_major':'','uncommitted_label':'','preparing_label':'۶ عدد','note':''},
]
supply_markup='''<main class="panel-content"><div class="panel-surface-stack supply-needs-page"><section class="card"><div class="card-body supply-builder">
<label class="supply-search"><span>جست‌وجوی ماده یا کالا</span><input class="form-control" id="supplyNeedSearch" type="search"></label><div class="supply-search-results" id="supplyNeedResults"></div><button class="supply-free-add hidden" id="supplyFreeAdd" type="button">+ اعلام نیاز برای «<span></span>»</button>
<form id="supplyNeedForm" class="supply-draft-form"><div class="supply-draft-head"><div><strong>نیازهای این ثبت</strong><small id="supplyDraftCount">هنوز موردی اضافه نشده</small></div><button id="supplyDraftClear" class="btn btn-light btn-sm hidden" type="button">پاک‌کردن</button></div><div id="supplyDraftList" class="supply-draft-list"><div id="supplyDraftEmpty" class="inventory-empty">خالی</div></div><div class="supply-submit-bar"><button id="supplySubmit" class="btn btn-primary" disabled>ثبت نیازها</button></div></form>
</div></section><section class="card"><div class="supply-open-list"><button class="supply-open-row" data-edit-supply-need="9"><div><strong>شیر</strong><small>در حال خرید ۱۰ لیتر · نیاز اضافه ۲ لیتر</small></div><span>ویرایش</span></button><button class="supply-open-row" data-edit-supply-need="10"><div><strong>خامه</strong><small>در حال خرید ۶ عدد</small></div><span>افزودن نیاز</span></button></div></section></div></main>'''
supply_html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>.hidden{{display:none!important}}{css}</style></head><body class="panel-body">{supply_markup}<script>window.SOKNA_SUPPLY_CATALOG={json.dumps(catalog,ensure_ascii=False)};window.SOKNA_SUPPLY_OPEN={json.dumps(open_needs,ensure_ascii=False)};</script><script>{supply_js}</script></body></html>'''

needs=[
 {'group_key':'item:1','item_id':1,'name':'شیر','base_unit':'ml','unit_label':'لیتر','preparing_label':'۱۰ لیتر','preparing_quantity_base':10000,'preparing_major':'10','unknown':False,'units':[{'id':21,'name':'کارتن ۶ لیتری','mode':'fixed','base_quantity':6000},{'id':22,'name':'بسته وزن متغیر','mode':'actual_quantity','base_quantity':None}]},
 {'group_key':'free:g:2YHZhNmB2YQg2YfYp9mE2YjZvtuM2YbZiA','item_id':0,'name':'فلفل هالوپینو','base_unit':'g','unit_label':'کیلوگرم','preparing_label':'۲ کیلوگرم','preparing_quantity_base':2000,'preparing_major':'2','unknown':True,'units':[]},
]
items=[
 {'id':1,'name':'شیر','base_unit':'ml','unit_label':'لیتر','units':[{'id':21,'name':'کارتن ۶ لیتری','mode':'fixed','base_quantity':6000},{'id':22,'name':'بسته وزن متغیر','mode':'actual_quantity','base_quantity':None}]},
 {'id':4,'name':'هالوپینو سبز','base_unit':'g','unit_label':'کیلوگرم','units':[]},
 {'id':5,'name':'دستکش','base_unit':'count','unit_label':'عدد','units':[]},
]
purchase_markup='''<main class="panel-content"><div class="panel-surface-stack purchase-page"><section class="card purchase-preparing-card"><div class="purchase-need-list"><article class="purchase-need-row is-preparing" data-purchase-share="شیر — ۱۰ لیتر"><div class="purchase-need-main"><strong>شیر</strong><small>در حال خرید ۱۰ لیتر</small></div><div class="purchase-need-actions"><button class="btn btn-primary btn-sm" data-open-purchase-receive="item:1">ثبت تحویل</button><button class="btn btn-light btn-sm" data-open-purchase-more="prep-item:1">بیشتر</button></div><div class="purchase-need-more hidden" data-purchase-more="prep-item:1">بازگرداندن</div></article><article class="purchase-need-row"><div class="purchase-need-main"><strong>فلفل هالوپینو</strong></div><div class="purchase-need-actions"><button class="btn btn-primary btn-sm" data-open-purchase-receive="free:g:2YHZhNmB2YQg2YfYp9mE2YjZvtuM2YbZiA">ثبت تحویل و اتصال به انبار</button></div></article></div></section></div></main>
<div class="panel-confirm-layer panel-form-dialog-layer hidden purchase-receive-layer" id="purchaseReceiveLayer" aria-hidden="true"><button class="panel-confirm-backdrop"></button><div class="panel-form-dialog-card purchase-receive-card"><header class="panel-form-dialog-head"><div><h2 id="purchaseReceiveTitle"></h2><p id="purchaseReceiveSummary"></p></div><button type="button">×</button></header><form id="purchaseReceiveForm" class="panel-form-dialog-form purchase-receive-form"><div class="panel-form-dialog-body"><input id="purchaseGroupKey" name="group_key"><input id="purchaseExpectedPreparing" name="expected_preparing_quantity_base"><input id="purchaseRequestToken" name="request_token"><div id="purchaseTargetGroup" class="form-group hidden"><select id="purchaseTargetItem" name="target_item_id"></select></div><div class="form-group"><select id="purchaseUnit" name="purchase_unit_id"></select></div><div class="form-group"><label id="purchaseUnitCountLabel"></label><div class="purchase-receive-qty"><input id="purchaseUnitCount" name="unit_count"><em id="purchaseUnitLabel"></em></div><button id="purchaseFillPrepared" type="button">همان مقدار در حال خرید</button></div><div id="purchaseActualGroup" class="form-group hidden"><input id="purchaseActual" name="actual_major_quantity"><em id="purchaseActualLabel"></em></div><input name="total_cost"><input name="supplier"><input name="occurred_date_j"><input name="occurred_time"><textarea name="note"></textarea></div><div class="panel-form-dialog-actions"><button id="purchaseReceiveSubmit">ثبت تحویل و ورود انبار</button><button type="button">انصراف</button></div></form></div></div><button id="sharePurchaseList"></button>'''
purchase_html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>.hidden{{display:none!important}}{css}</style></head><body class="panel-body">{purchase_markup}<script>window.SOKNA_PURCHASE_NEEDS={json.dumps(needs,ensure_ascii=False)};window.SOKNA_PURCHASE_ITEMS={json.dumps(items,ensure_ascii=False)};window.CafeUI={{dialog:{{open:(layer)=>{{layer.classList.remove('hidden');layer.setAttribute('aria-hidden','false')}}}},toast:()=>{{}}}};</script><script>{purchase_js}</script></body></html>'''

with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    for width in (320,390,412,768,1366):
        page=browser.new_page(viewport={'width':width,'height':900},has_touch=width<=768,is_mobile=width<=412);page.set_content(supply_html);page.wait_for_timeout(30)
        assert not page.locator('.supply-submit-bar').is_visible(),'empty draft must not show a disabled sticky submit'
        page.locator('[data-edit-supply-need="9"]').click();page.wait_for_timeout(20)
        assert page.locator('.supply-draft-row').count()==1
        assert page.locator('.supply-submit-bar').is_visible(),'submit action should appear only after a need exists'
        assert page.locator('.supply-need-qty').input_value()=='2','preparing quantity leaked into editable need'
        assert 'در حال خرید' in page.locator('.supply-draft-copy small').inner_text()
        if width<=768:
            assert page.evaluate("document.activeElement?.classList?.contains('supply-need-qty') !== true"),'selecting a supply item must not summon quantity keyboard on touch'
        assert page.locator('.supply-line-note').count()==1
        page.locator('[data-edit-supply-need="10"]').click();page.wait_for_timeout(20)
        assert page.locator('.supply-draft-row').count()==2
        assert page.locator('.supply-draft-row').nth(1).locator('.supply-need-qty').input_value()==''
        assert page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1'),('supply',width)
        page.close()

        page=browser.new_page(viewport={'width':width,'height':900},has_touch=width<=768,is_mobile=width<=412);page.set_content(purchase_html);page.wait_for_timeout(30)
        page.locator('[data-open-purchase-receive="item:1"]').click();page.wait_for_timeout(20)
        assert page.locator('#purchaseReceiveLayer').is_visible()
        assert '۱۰ لیتر' in page.locator('#purchaseReceiveSummary').inner_text()
        assert page.locator('#purchaseUnitCount').input_value()=='' ,'receipt quantity must start blank'
        assert page.locator('#purchaseExpectedPreparing').input_value()=='10000','preparing snapshot guard missing'
        assert page.evaluate("document.activeElement?.id !== 'purchaseUnitCount'"),'receipt dialog must not auto-open the mobile keyboard'
        head=page.locator('.panel-form-dialog-head').bounding_box(); body=page.locator('.panel-form-dialog-body').bounding_box(); title_box=page.locator('#purchaseReceiveTitle').bounding_box()
        assert head and body and title_box and title_box['width']>100,(width,head,body,title_box)
        assert body['y'] >= head['y'] + head['height'] - 2,(width,head,body)
        page.locator('#purchaseFillPrepared').click();assert page.locator('#purchaseUnitCount').input_value()=='10'
        if width<=768: assert page.evaluate("document.activeElement?.id !== 'purchaseUnitCount'"),'same-amount shortcut must not summon keyboard on touch'
        page.locator('#purchaseUnit').select_option('21');page.wait_for_timeout(10)
        assert page.locator('#purchaseUnitCount').input_value()=='' ,'unit change reused old meaning'
        assert not page.locator('#purchaseFillPrepared').is_visible()
        page.locator('#purchaseUnit').select_option('0');page.wait_for_timeout(10)
        assert page.locator('#purchaseUnitCount').input_value()==''
        assert page.locator('#purchaseFillPrepared').is_visible()
        assert page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1'),('purchase-known',width)
        page.locator('#purchaseReceiveLayer').evaluate("e=>{e.classList.add('hidden');e.setAttribute('aria-hidden','true')}")
        page.locator('[data-open-purchase-receive="free:g:2YHZhNmB2YQg2YfYp9mE2YjZvtuM2YbZiA"]').click();page.wait_for_timeout(20)
        assert page.locator('#purchaseTargetGroup').is_visible()
        opts=page.locator('#purchaseTargetItem option').all_inner_texts()
        assert any('هالوپینو سبز' in x for x in opts) and not any('دستکش' in x for x in opts),opts
        assert page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1'),('purchase-unknown',width)
        page.close()
    browser.close()
print('1.36 Supply browser PASS at 320/390/412/768/1366: purchase-state language, touch focus containment, editable added demand, blank receipt quantity, safe unit change, unknown linkage and no overflow.')
