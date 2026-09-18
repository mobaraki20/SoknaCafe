#!/usr/bin/env python3
from pathlib import Path
try:
    from playwright.sync_api import sync_playwright
except Exception:
    print('Playwright unavailable; UI conformance browser check skipped.')
    raise SystemExit(0)

ROOT = Path(__file__).resolve().parents[1]
HTML = '''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head><body>
<div class="panel-shell"><main id="panelContent" class="panel-content" style="height:900px;overflow:auto">
  <div id="panelToolsMenu"><button id="panelToolsToggle" class="icon-btn topbar-tools-toggle" type="button" aria-expanded="false">•••</button>
    <div id="panelToolsPopover" class="topbar-tools-menu hidden" aria-hidden="true"><button class="topbar-tool" role="menuitem">ابزار</button></div>
  </div>
  <div id="row" class="panel-list-row is-navigable" data-row-href="#row-open" role="link" tabindex="0">
    <span>میز ۱۲</span>
    <div class="row-action-menu" data-action-menu data-action-menu-label="مدیریت میز ۱۲">
      <button id="rowTrigger" type="button" data-action-menu-trigger aria-expanded="false">•••</button>
      <div id="rowPopover" class="row-action-popover" data-action-menu-popover role="menu">
        <button id="rowAction" role="menuitem" type="button" data-click-confirm="این میز حذف می‌شود." data-confirm-title="حذف میز؟" data-confirm-ok="حذف میز" data-confirm-danger="1">حذف</button>
        <button role="menuitem" type="button">حذف</button>
      </div>
    </div>
  </div>
  <div class="form-group"><label>دسته<select id="choice" class="form-control" data-choice-mode="compact"><option>مواد اولیه</option><option>نوشیدنی</option></select></label></div>
  <div class="operator-work-tabs-v1280 panel-primary-tabs"><button type="button">نیازمند اقدام <span id="countBadge">۱۲</span></button></div>
  <form class="inventory-workspace" style="height:480px;overflow:auto"><input id="lastField" class="form-control" value="گرم"><div id="sticky" class="inventory-sticky-actions"><button class="btn btn-primary">ذخیره</button></div></form>
  <div id="touchTargets">
    <button id="genericIconBtn" class="icon-btn" type="button" aria-label="گزینه">⋮</button>
    <button id="sideGroupToggle" class="side-nav-group-toggle" type="button">گروه</button>
    <nav class="side-nav"><a id="sideNavLink" href="#x">صفحه</a></nav>
    <button id="smallBtn" class="btn btn-sm">ریز</button>
    <button id="stateToggle" class="state-toggle">وضعیت</button>
    <div class="segmented-control compact"><button id="segBtn" type="button">تب</button></div>
    <div class="sidebar-user-actions"><a id="sideAction" href="#x">حساب</a></div>
    <nav class="panel-subnav"><a id="subnavAction" href="#x">زیرمنو</a></nav>
    <button id="supplyRemove" class="supply-row-remove" type="button">×</button>
    <button id="reorderHandle" class="reorder-handle" type="button">↕</button>
    <div class="quick-order-pending-actions"><button id="pendingAction" type="button">تأیید</button></div>
    <button id="tableReviewAction" class="table-card-review" type="button"><span>نیازمند بررسی</span><b>بازکردن</b></button>
    <details class="table-account-disclosure-v1280"><summary id="tableDisclosure">جزئیات حساب</summary></details>
    <div class="table-sort-control-v1306"><button id="tableSortAction" type="button">شماره</button></div>
    <button id="operatorIconAction" class="icon-action-button" type="button">⋮</button>
    <button id="batchActionTrigger" class="bill-batch-action-trigger" type="button">⋮</button>
    <div class="table-account-footer-actions-v1301"><button id="accountFooterAction" class="btn" type="button">اقدام حساب</button></div>
    <details class="device-settings"><summary id="deviceSettingsAction">تنظیم دستگاه</summary></details>
    <nav class="panel-subnav print-template-switch"><a id="printTemplateTab" href="#x">قالب چاپ</a></nav>
    <button id="quickBack" class="quick-order-back" type="button">‹</button>
    <button id="quickChangeTable" class="quick-order-change-table" type="button">تغییر میز</button>
    <button id="quickCategoryBack" class="quick-order-category-back" type="button">بازگشت</button>
    <button id="quickSearchToggle" class="quick-order-search-toggle" type="button">⌕</button>
    <div class="quick-order-search"><input id="quickSearchInput" type="search"></div>
    <div class="quick-order-inline-qty"><button id="quickQtyMinus" type="button">−</button><span>۱</span><button id="quickQtyPlus" type="button">+</button></div>
    <details class="quick-order-current"><summary id="quickCurrentSummary">حساب فعلی</summary></details>
    <button id="quickTakeawayTool" class="quick-order-takeaway-tool" type="button">بیرون‌بر</button>
    <div class="quick-order-takeaway-mode"><div><button id="quickTakeawayMode" class="btn" type="button">ویرایش</button></div></div>
    <div class="quick-order-line-qty quick-order-inline-qty"><button id="quickLineQty" type="button">−</button><span>۱</span><button type="button">+</button></div>
    <button id="quickLineNote" class="quick-order-line-note-button" type="button">یادداشت</button>
    <div class="quick-order-takeaway-stepper"><button id="quickTakeawayMinus" type="button">−</button><span>۰/۱</span><button type="button">+</button></div>
    <button id="quickTakeawayToggle" class="quick-order-takeaway-toggle" type="button">بیرون‌بر</button>
  </div>
  <button id="dangerTrigger" class="btn btn-danger" data-click-confirm="این مورد حذف می‌شود." data-confirm-title="حذف مورد؟" data-confirm-ok="حذف" data-confirm-danger="1">حذف</button>
</main></div>
<div class="pwa-update-banner" id="pwaUpdateBanner"><div><strong>نسخه جدید آماده است</strong></div><div class="pwa-update-actions"><button>به‌روزرسانی</button></div></div>
<div class="panel-confirm-layer hidden" id="panelConfirmLayer" role="dialog" aria-modal="true" aria-labelledby="panelConfirmTitle" aria-describedby="panelConfirmMessage" aria-hidden="true">
  <div class="panel-confirm-backdrop" data-panel-confirm-cancel></div>
  <section class="panel-confirm-card"><div class="panel-confirm-icon"><span data-panel-confirm-icon-info>i</span><span class="hidden" data-panel-confirm-icon-danger>!</span></div><div class="panel-confirm-copy"><h2 id="panelConfirmTitle">تأیید</h2><p id="panelConfirmMessage"></p></div><div class="panel-confirm-actions"><button class="btn btn-primary" type="button" data-panel-confirm-ok>تأیید</button><button class="btn btn-light" type="button" data-panel-confirm-cancel>انصراف</button></div></section>
</div>
<div id="panelToast" class="panel-toast hidden"></div>
<script>window.__rowClicks=0; document.getElementById('row').addEventListener('click',e=>{ if(!e.target.closest('button,a,input,select,textarea')) window.__rowClicks++; });</script>
</body></html>'''

CSS_FILES = [
    'assets/css/app.css',
    'assets/css/panel-components.css',
    'assets/css/panel-layout.css',
    'assets/css/operator-live.css',
    'assets/css/inventory.css',
    'assets/css/items-management.css',
    'assets/css/reorder.css',
    'assets/css/quick-order.css',
    'assets/css/staff-action-queue.css',
]
JS_FILES = [
    'assets/js/panel-core.js',
    'assets/js/panel-menus.js',
    'assets/js/panel-shell.js',
    'assets/js/panel-choice.js',
]

def style(page, selector, prop):
    loc = selector if hasattr(selector, 'evaluate') else page.locator(selector)
    return loc.evaluate('(e,p)=>getComputedStyle(e).getPropertyValue(p)', prop)

def add_app(page):
    page.set_content(HTML)
    for rel in CSS_FILES:
        page.add_style_tag(path=str(ROOT/rel))
    for rel in JS_FILES:
        page.add_script_tag(path=str(ROOT/rel))
    page.wait_for_timeout(100)

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])

    for width in (320, 390, 412):
        page = browser.new_page(viewport={'width':width,'height':844}, has_touch=True, is_mobile=True)
        add_app(page)

        # Shared select affordance: down-chevron stays physical in RTL, not a left navigation arrow.
        trigger = page.locator('.panel-choice-trigger').first
        assert trigger.count() == 1, f'{width}: enhanced choice trigger missing'
        chevron = trigger.locator('i').first
        assert style(page, chevron, 'border-right-width') == '2px', f'{width}: RTL select lacks right stroke'
        assert style(page, chevron, 'border-bottom-width') == '2px', f'{width}: RTL select lacks bottom stroke'
        assert style(page, chevron, 'border-left-width') == '0px', f'{width}: RTL select still mirrors into a left arrow'

        # Context menu becomes a modal mobile action sheet with background containment.
        page.click('#rowTrigger'); page.wait_for_timeout(80)
        assert page.locator('#rowPopover').evaluate("e=>e.classList.contains('is-mobile-action-sheet')"), f'{width}: row action not mobile sheet'
        assert page.locator('.panel-action-menu-backdrop').is_visible(), f'{width}: row action backdrop missing'
        assert page.locator('body').evaluate("e=>e.classList.contains('panel-action-menu-open')")
        assert style(page, '#pwaUpdateBanner', 'visibility') == 'hidden', f'{width}: update banner competes with row sheet'
        r = page.locator('#rowPopover').bounding_box(); assert r
        assert r['x'] >= 8 and r['x'] + r['width'] <= width - 8 + 1.5, f'{width}: action sheet escapes viewport: {r}'
        assert r['y'] + r['height'] <= 844 - 8 + 2, f'{width}: action sheet bottom clipped: {r}'
        body_overflow = style(page, 'body', 'overflow')
        assert body_overflow == 'hidden', f'{width}: background scroll not locked ({body_overflow})'
        page.click('.panel-action-menu-backdrop'); page.wait_for_timeout(40)
        assert page.locator('#rowPopover').evaluate("e=>!e.classList.contains('is-mobile-action-sheet')")
        assert style(page, '#pwaUpdateBanner', 'visibility') != 'hidden', f'{width}: update banner not restored after sheet close'

        # Consequential action handoff: sheet closes into confirm without an overlay/banner/scroll gap.
        page.click('#rowTrigger'); page.wait_for_timeout(40)
        page.click('#rowAction'); page.wait_for_timeout(80)
        assert page.locator('#panelConfirmLayer').is_visible(), f'{width}: menu action did not hand off to confirm'
        assert page.locator('#panelConfirmTitle').inner_text() == 'حذف میز؟'
        assert page.locator('[data-panel-confirm-ok]').inner_text() == 'حذف میز'
        assert page.locator('#rowPopover').evaluate("e=>!e.classList.contains('is-mobile-action-sheet')"), f'{width}: action sheet remained behind confirm'
        assert page.locator('.panel-action-menu-backdrop').is_hidden(), f'{width}: action-menu backdrop remained behind confirm'
        assert style(page, '#pwaUpdateBanner', 'visibility') == 'hidden', f'{width}: update banner flashed between action sheet and confirm'
        assert style(page, 'body', 'overflow') == 'hidden', f'{width}: background scroll unlocked during sheet-to-confirm handoff'
        page.click('.panel-confirm-actions [data-panel-confirm-cancel]'); page.wait_for_timeout(40)
        assert style(page, '#pwaUpdateBanner', 'visibility') != 'hidden', f'{width}: update banner not restored after confirm close'

        # Top three-dot tools follows the same mobile task-sheet contract.
        page.click('#panelToolsToggle'); page.wait_for_timeout(80)
        assert page.locator('#panelToolsPopover').evaluate("e=>e.classList.contains('is-mobile-tools-sheet')"), f'{width}: top tools not a mobile sheet'
        assert page.locator('.panel-tools-backdrop').is_visible(), f'{width}: top tools backdrop missing'
        assert style(page, '#pwaUpdateBanner', 'visibility') == 'hidden', f'{width}: update banner competes with top tools'
        page.click('.panel-tools-backdrop'); page.wait_for_timeout(40)
        assert page.locator('#panelToolsPopover').is_hidden()

        # Two-digit attention badge: actual glyph box center must remain close to the circle center.
        badge = page.locator('#countBadge')
        b = badge.bounding_box(); assert b
        assert abs(b['width'] - 24) <= 0.8 and abs(b['height'] - 24) <= 0.8, f'{width}: badge geometry {b}'
        glyph = badge.evaluate('''e=>{const r=document.createRange(); r.selectNodeContents(e); const b=r.getBoundingClientRect(); const a=e.getBoundingClientRect(); return {dx:(b.left+b.width/2)-(a.left+a.width/2),dy:(b.top+b.height/2)-(a.top+a.height/2)};}''')
        assert abs(glyph['dx']) <= 1.6 and abs(glyph['dy']) <= 2.2, f'{width}: two-digit badge optically off center {glyph}'

        # Inventory actions no longer overlay the last field on mobile.
        assert style(page, '#sticky', 'position') == 'static', f'{width}: inventory actions still sticky on mobile'
        last = page.locator('#lastField').bounding_box(); sticky = page.locator('#sticky').bounding_box(); assert last and sticky
        assert last['y'] + last['height'] <= sticky['y'] + 1, f'{width}: action bar overlaps form control'

        # Common touch actions stay at least 44px without inflating desktop-only density.
        touch_selectors = (
            '#smallBtn','#stateToggle','#segBtn','#sideAction','#subnavAction','#supplyRemove','#reorderHandle',
            '#pendingAction','#tableReviewAction','#tableDisclosure','#tableSortAction','#operatorIconAction','#genericIconBtn','#sideGroupToggle','#sideNavLink','#panelToolsToggle',
            '#batchActionTrigger','#accountFooterAction','#deviceSettingsAction','#printTemplateTab','#quickBack',
            '#quickChangeTable','#quickCategoryBack','#quickSearchToggle','#quickSearchInput','#quickQtyMinus',
            '#quickQtyPlus','#quickCurrentSummary','#quickTakeawayTool','#quickTakeawayMode','#quickLineQty',
            '#quickLineNote','#quickTakeawayMinus','#quickTakeawayToggle'
        )
        square_targets = ('#supplyRemove','#reorderHandle','#operatorIconAction','#batchActionTrigger','#genericIconBtn','#panelToolsToggle','#quickBack','#quickSearchToggle','#quickLineNote','#quickTakeawayMinus')
        for selector in touch_selectors:
            rect = page.locator(selector).bounding_box(); assert rect
            assert rect['height'] >= 43.5, f'{width}: touch target too short {selector} => {rect}'
            if selector in square_targets:
                assert rect['width'] >= 43.5, f'{width}: touch target too narrow {selector} => {rect}'

        # Destructive confirm has semantic icon/tone/CTA and does not show the old generic copy.
        page.click('#dangerTrigger'); page.wait_for_timeout(50)
        assert page.locator('#panelConfirmTitle').inner_text() == 'حذف مورد؟'
        assert page.locator('[data-panel-confirm-ok]').inner_text() == 'حذف'
        assert 'btn-danger' in page.locator('[data-panel-confirm-ok]').get_attribute('class')
        assert page.locator('[data-panel-confirm-icon-danger]').is_visible()
        assert page.locator('[data-panel-confirm-icon-info]').is_hidden()
        assert page.locator('#panelConfirmLayer').get_attribute('data-confirm-tone') == 'danger'
        page.click('.panel-confirm-actions [data-panel-confirm-cancel]'); page.wait_for_timeout(30)
        page.close()

    # Desktop menus remain contextually anchored; mobile treatment must not leak upward.
    page = browser.new_page(viewport={'width':1366,'height':768})
    add_app(page)
    page.click('#rowTrigger'); page.wait_for_timeout(80)
    assert not page.locator('#rowPopover').evaluate("e=>e.classList.contains('is-mobile-action-sheet')")
    assert page.locator('.panel-action-menu-backdrop').is_hidden()
    assert page.locator('#rowPopover').get_attribute('data-menu-placement') in ('above','below','above-clamped','below-clamped')
    page.keyboard.press('Escape'); page.wait_for_timeout(30)
    page.click('#panelToolsToggle'); page.wait_for_timeout(80)
    assert not page.locator('#panelToolsPopover').evaluate("e=>e.classList.contains('is-mobile-tools-sheet')")
    assert page.locator('.panel-tools-backdrop').is_hidden()
    page.close()
    browser.close()

print('UI conformance browser PASS: mobile task sheets, overlay suppression, RTL choice, badge geometry, non-overlapping actions and semantic confirms are verified at 320/390/412 plus desktop.')
