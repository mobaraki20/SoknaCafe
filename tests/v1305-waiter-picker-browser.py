#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
css='\n'.join((ROOT/p).read_text(encoding='utf-8') for p in ['assets/css/tokens.css','assets/css/app.css','assets/css/responsive.css','assets/css/guest-menu.css'])
js=(ROOT/'assets/js/menu.js').read_text(encoding='utf-8')
# Deliberately shuffled. Internal codes are not part of the browser contract anymore.
tables=[{'id':i,'name':f'میز {i}','table_number':i,'sort_order':i} for i in range(1,36)]
tables=tables[15:22]+tables[:15]+tables[22:]
import json
html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="x"><style>:root{{--primary:#365b4c;--accent:#b85c38;--guest-card:#fff;--guest-line:#ddd5ca;--guest-ink:#2c2723;--guest-soft:#6f675f;--font-ui:Tahoma}}{css}</style></head><body class="guest-menu-page guest-mode-public has-waiter">
<button id="waiterButton"><span id="waiterButtonText">فراخوان گارسون</span></button>
<div class="success-modal hidden" id="waiterModal"><div class="success-box"><div class="success-icon waiter-icon"></div><h2 id="waiterModalTitle">گارسون رو صدا کنیم؟</h2><p id="waiterModalText"></p><div class="public-table-quick hidden" id="publicTableQuick"><span>فراخوان برای <strong id="publicTableQuickName"></strong></span><button type="button" id="changePublicTable">تغییر میز</button></div><section class="public-table-picker" id="publicTableSelectWrap"><div class="public-table-picker-head"><div><strong>میزت را انتخاب کن</strong><small>فراخوان فقط برای همان میز فرستاده می‌شود.</small></div><span class="public-table-selected" id="publicTableSelection">هنوز انتخاب نشده</span></div><input type="hidden" id="publicWaiterTable"><div class="public-table-grid" id="publicTableGrid"></div></section><div class="modal-actions"><button id="confirmWaiter">فراخوان گارسون</button><button id="closeWaiter">نه، فعلاً</button></div></div></div>
<div id="guestToast" class="hidden"></div><section id="eventsSheet" class="hidden"></section><div id="itemDetailModal" class="hidden"></div>
<script>window.CAFE_MENU=[];window.CAFE_CURRENCY='تومان';window.CAFE_WAITER_API_URL='/fake';window.CAFE_PUBLIC_WAITER_TABLES={json.dumps(tables,ensure_ascii=False)};window.CAFE_MESSAGES={{waiter_button:'فراخوان گارسون'}};</script><script>{js}</script></body></html>'''
with sync_playwright() as p:
    b=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    for width in (320,360,390,412):
        page=b.new_page(viewport={'width':width,'height':760})
        errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
        page.set_content(html,wait_until='load')
        page.click('#waiterButton');page.wait_for_timeout(50)
        buttons=page.locator('#publicTableGrid .public-table-option')
        assert buttons.count()==35
        shown=[buttons.nth(i).locator('strong').inner_text() for i in range(6)]
        assert shown==['۱','۲','۳','۴','۵','۶'],(width,shown)
        assert page.locator('#publicTableGrid').evaluate("e=>e.textContent.includes('T-')") is False
        dims=page.evaluate("()=>({root:document.documentElement.scrollWidth,iw:innerWidth,grid:document.querySelector('#publicTableGrid').scrollWidth,gw:document.querySelector('#publicTableGrid').clientWidth})")
        assert dims['root']<=dims['iw']+1,(width,dims)
        assert dims['grid']<=dims['gw']+1,(width,dims)
        footer=page.locator('#waiterModal .modal-actions').bounding_box(); assert footer and footer['y']+footer['height'] <= 760+1,(width,footer)
        # Choose table 5, reopen into quick state, force stale scroll position, then "change table" must reveal selected table 5.
        page.locator('[data-public-table="5"]').click(); page.click('#closeWaiter'); page.click('#waiterButton');
        assert page.locator('#publicTableQuick').is_visible()
        page.evaluate("document.querySelector('#publicTableGrid').scrollTop=document.querySelector('#publicTableGrid').scrollHeight")
        page.click('#changePublicTable');page.wait_for_timeout(50)
        selected=page.locator('.public-table-option.is-selected'); assert selected.locator('strong').inner_text()=='۵'
        visible=selected.evaluate("e=>{const g=e.parentElement,r=e.getBoundingClientRect(),q=g.getBoundingClientRect();return r.top>=q.top-1&&r.bottom<=q.bottom+1}")
        
        assert visible,(width,page.evaluate("document.querySelector('#publicTableGrid').scrollTop"))
        assert not errors,(width,errors)
        page.close()
    b.close()
index=(ROOT/'menu/index.php').read_text(encoding='utf-8')
preview_contract=index[index.find('window.CAFE_PUBLIC_WAITER_TABLES='):index.find('window.CAFE_MESSAGES=',index.find('window.CAFE_PUBLIC_WAITER_TABLES='))]
assert "'code'" not in preview_contract and "'table_number'" in preview_contract
print('1.30.5 public waiter picker passed: numeric order, no internal codes, no horizontal overflow, fixed footer and selected-table reveal at 320/360/390/412.')
