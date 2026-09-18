#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
css='\n'.join((ROOT/p).read_text(encoding='utf-8') for p in [
    'assets/css/tokens.css','assets/css/app.css','assets/css/responsive.css','assets/css/panel.css','assets/css/panel-layout.css','assets/css/panel-components.css','assets/css/inventory.css','assets/css/items-management.css'
])
menus=(ROOT/'assets/js/panel-menus.js').read_text(encoding='utf-8')
html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>:root{{--primary:#365b4c;--line:#e5ddd3;--muted:#736b64;--danger:#a33;--success:#16845b;--font-ui:system-ui;--panel-line:#e5ddd3;--panel-surface:#fff;--panel-warning:#b67814}}body{{margin:0}}{css}</style></head><body class="panel-body panel-section-inventory"><main class="panel-content">
<div class="inventory-toolbar"><div class="panel-copy-stack"><strong>کنترل موجودی، ورود، ضایعات و شمارش</strong><small>وضعیت جاری انبار</small></div><div class="inventory-toolbar-actions"><a class="btn btn-light">مدیریت کالاها</a><div class="row-action-menu" data-action-menu data-action-menu-label="ثبت عملیات انبار"><button type="button" class="btn btn-primary" data-action-menu-trigger aria-expanded="false" aria-haspopup="menu">ثبت عملیات</button><div class="row-action-popover" data-action-menu-popover role="menu"><a role="menuitem">ورود کالا</a><a role="menuitem">ثبت ضایعات</a><a role="menuitem">شروع شمارش</a></div></div></div></div>
<div class="inventory-summary"><div class="summary-cell"><small>ارزش تقریبی موجودی</small><strong>۱۲٬۵۰۰٬۰۰۰ تومان</strong><span class="summary-note">۱ قلم قیمت ناقص</span></div><div class="summary-cell"><small>نیازمند توجه</small><strong>۴ مورد</strong><span class="summary-note">۳ کم‌موجودی · ۱ منفی</span></div><div class="summary-cell"><small>آخرین شمارش</small><strong>شمارش پایان مرداد</strong><span class="summary-note">امروز</span></div></div>
<div class="inventory-list"><a class="inventory-row"><div class="inventory-row-main"><strong>سیب‌زمینی کاله</strong><small>مواد اولیه · آشپزخانه · بسته ۲.۵ کیلویی · کارتن ۴ بسته‌ای</small></div><div class="inventory-row-meta"><small>واحد پایه</small><strong>گرم</strong></div><div class="inventory-row-stock">۱۲٫۵ کیلوگرم</div><div><span class="inventory-status">آماده</span></div></a></div>
<section class="card panel-list-card inventory-management-list"><div class="panel-list-row inventory-management-row"><div class="panel-list-primary"><strong>آب معدنی کوچک</strong><small>نوشیدنی آماده · مشترک</small></div><div class="panel-list-value"><span>واحد پایه</span><strong>عدد</strong></div><div class="panel-list-value"><span>موجودی</span><strong>۵</strong></div><div class="inventory-management-status"><span class="panel-status-badge is-warning">نیاز به تأیید</span></div><div class="panel-row-actions"><button class="btn btn-light">مدیریت</button></div></div></section>
<div class="inventory-count-list"><div class="inventory-count-line"><div><strong>بستنی وانیلی کاله</strong><small>مقدار را به کیلوگرم وارد کن</small></div><div><input class="form-control" value="2.7"><input class="form-control inventory-opening-cost" value="2500000"></div></div></div>
<div class="recipe-component-list"><div class="recipe-component-row"><div class="form-group"><label>ماده انبار</label><button class="form-control">شیر دومینو</button></div><div class="form-group"><label>مقدار</label><input class="form-control" value="0.22"></div><button class="btn btn-light recipe-remove">حذف</button></div></div>
</main><script>{menus}</script></body></html>'''
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    for width,height in ((1440,900),(768,900),(390,844),(320,720)):
        page=browser.new_page(viewport={'width':width,'height':height})
        page.set_content(html,wait_until='load')
        assert page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'), width
        row=page.locator('.inventory-row').first.bounding_box(); assert row and row['width'] <= width + 1
        trigger=page.locator('[data-action-menu-trigger]'); trigger.click(); page.wait_for_timeout(30)
        pop=page.locator('[data-action-menu-popover]'); assert pop.is_visible(), width
        assert trigger.get_attribute('aria-expanded')=='true'
        box=pop.bounding_box(); assert box and box['x'] >= -1 and box['x']+box['width'] <= width+1, (width,box)
        if width <= 640:
            # Mobile contextual actions use the shared task-sheet owner. This avoids
            # clipped popovers and competition with banners/viewport edges.
            assert 'is-mobile-action-sheet' in (pop.get_attribute('class') or ''), (width,pop.get_attribute('class'))
            assert page.locator('.panel-action-menu-backdrop').is_visible(), width
            assert page.evaluate("document.body.classList.contains('panel-action-menu-open')"), width
            assert box['width'] >= width - 24 and box['width'] <= width - 16, (width,box)
            assert box['x'] >= 8 and box['x'] <= 14, (width,box)
            assert box['y'] + box['height'] <= height - 6, (width,box)
            summary=page.locator('.inventory-summary')
            assert summary.evaluate("e=>getComputedStyle(e).gridTemplateColumns.split(' ').length") == 1
            sb=summary.bounding_box(); assert sb and sb['height'] < 220, (width,sb)
            count_cols=page.locator('.inventory-count-line').evaluate("e=>getComputedStyle(e).gridTemplateColumns.split(' ').length")
            assert count_cols == (1 if width <= 340 else 2), (width,count_cols)
            mrow=page.locator('.inventory-management-row'); mb=mrow.bounding_box(); assert mb and mb['height'] < 110, (width,mb)
            assert page.locator('.inventory-management-row>.panel-list-value').first.evaluate("e=>getComputedStyle(e).display") == 'none'
            recipe=page.locator('.recipe-component-row'); rb=recipe.bounding_box(); assert rb and rb['width'] <= width+1
            remove=page.locator('.recipe-remove').bounding_box(); assert remove and remove['height'] >= 44 and remove['width'] >= 44
        page.keyboard.press('Escape'); page.wait_for_timeout(20); assert pop.is_hidden()
        page.close()
    browser.close()
print('Inventory browser contract passed: compact mobile summary/catalog/recipe rows, responsive count layout, no horizontal overflow, and desktop-anchored/mobile-task-sheet shared action menus.')
