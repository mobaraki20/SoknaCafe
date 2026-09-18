#!/usr/bin/env python3
from pathlib import Path
import os,sys
ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'tests'))
from lib.visual_quality import assert_min_vertical_gap,assert_no_horizontal_overflow,assert_no_pair_overlap,assert_text_not_clipped,box,maybe_screenshot
try:
    from playwright.sync_api import sync_playwright
except Exception as e:
    if os.getenv('SOKNA_RELEASE_GATE')=='1': print('visual-quality-browser FAILED: Playwright unavailable',e);sys.exit(1)
    print('SKIP: Playwright unavailable');sys.exit(0)

def fail(msg): print('visual-quality-browser FAILED:',msg);sys.exit(1)

HTML='''<!doctype html><html lang="fa" dir="rtl"><meta name="viewport" content="width=device-width,initial-scale=1"><body class="panel-body"><main id="panelContent" class="panel-content"><div class="panel-page-flow push-devices-page" data-visual-quality-page="push_devices"><div class="push-health-strip"><article><span>ثبت‌شده</span><strong>۸</strong></article><article><span>فعال</span><strong>۸</strong></article><article class="has-warning"><span>نیازمند بررسی</span><strong>۴</strong></article></div><details class="push-setup-guide"><summary>راهنمای فعال‌سازی اعلان روی دستگاه</summary></details><section class="card" id="policy"><div class="card-head"><div class="panel-copy-stack"><h2>اعلان‌های عملیات زنده</h2></div></div><div class="card-body panel-card-flow"><form class="push-policy-form"><label class="check-line push-policy-toggle"><input type="checkbox" checked><span>دریافت اعلان‌های سالن و آماده‌سازی برای مدیر</span></label></form><div class="panel-diagnostic-grid push-worker-health has-warning"><div class="panel-diagnostic-item is-primary"><span class="panel-diagnostic-label">ارسال خودکار</span><strong>فعال</strong><small>رویداد جدید پس از ثبت تلاش فوری دارد و صف در صفحات فعال کارکنان دوباره پردازش می‌شود.</small></div><div class="panel-diagnostic-item"><span class="panel-diagnostic-label">صف در انتظار</span><strong>۸۶ اعلان</strong><small>قدیمی‌ترین: ۱۶ مرداد · ۱۷:۰۶</small></div><div class="panel-diagnostic-item"><span class="panel-diagnostic-label">پردازش مستقل</span><strong>اختیاری</strong><small>برای مقیاس فعلی سکنا الزامی نیست.</small></div></div></div></section><section class="card push-device-card" id="devices"><div class="card-head"><div class="panel-copy-stack"><h2>دستگاه‌های کارکنان</h2><small>دستگاه منقضی‌شده به‌صورت خودکار از دریافت اعلان خارج می‌شود.</small></div></div><div class="push-device-list"><article class="push-device-row"><header><div><strong>کیمی</strong><span>گوشی اندرویدی · Kimi</span></div><span class="badge badge-posted">فعال</span></header><div class="push-device-health"><span>آخرین موفق: ۲۵ مرداد · ۰۰:۵۵</span><span class="muted">به‌روزرسانی: ۲۵ مرداد · ۰۰:۵۵</span></div><form class="push-device-action"><button class="btn btn-sm btn-outline">غیرفعال‌کردن اعلان</button></form></article></div></section><section class="card push-log-card" id="logs"><div class="card-head"><div class="panel-copy-stack"><h2>۵۰ ارسال اخیر</h2><small>این بخش برای عیب‌یابی است؛ متن خصوصی اعلان ذخیره نمی‌شود.</small></div></div></section></div></main></body></html>'''

with sync_playwright() as p:
    try: browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    except Exception as e:
        if os.getenv('SOKNA_RELEASE_GATE')=='1': fail('Chromium launch failed: '+str(e))
        print('SKIP: Chromium unavailable');sys.exit(0)
    for width in [320,360,390,412,768,1366]:
        page=browser.new_page(viewport={'width':width,'height':900},has_touch=width<=412)
        page.set_content(HTML)
        for css in ['tokens.css','app.css','panel.css','panel-layout.css','panel-components.css','responsive.css']:
            page.add_style_tag(path=str(ROOT/'assets/css'/css))
        try:
            assert_no_horizontal_overflow(page,f'push devices {width}')
            flow=page.locator('.panel-page-flow')
            children=flow.locator(':scope > *').all()
            expected=16 if width<=520 else 20
            for i in range(len(children)-1):
                assert_min_vertical_gap(children[i],children[i+1],expected-0.75,f'page rhythm {width} child {i}')
            assert_min_vertical_gap(page.locator('.push-policy-toggle'),page.locator('.panel-diagnostic-grid'),11.25,f'policy body rhythm {width}')
            display=page.locator('.push-worker-health').evaluate("el=>getComputedStyle(el).display")
            if display!='grid': raise AssertionError(f'worker diagnostic is {display}, expected grid')
            items=page.locator('.panel-diagnostic-item').all()
            assert_no_pair_overlap(items,f'diagnostic items {width}')
            assert_text_not_clipped(page,'.panel-diagnostic-item',f'diagnostic content {width}')
            toggle=box(page.locator('.push-policy-toggle'))
            if toggle['height']<48: raise AssertionError(f'policy toggle {toggle["height"]:.2f}px < 48px')
            if page.locator('#policy .card-head small').count(): raise AssertionError('policy header repeated explanatory copy')
            if 'ارسال خودکار' not in page.locator('.panel-diagnostic-item.is-primary').inner_text(): raise AssertionError('primary health metric is not automatic delivery')
            if 'اختیاری' not in page.locator('.panel-diagnostic-item').nth(2).inner_text(): raise AssertionError('independent worker is not presented as optional')
            if width<=520:
                first,second,third=[box(x) for x in items]
                if first['width'] < box(page.locator('.panel-diagnostic-grid'))['width']*.9: raise AssertionError('automatic delivery status must span mobile first row')
                if abs(second['y']-third['y'])>2: raise AssertionError('queue and optional-worker metrics must share mobile row')
            else:
                ys=[box(x)['y'] for x in items]
                if max(ys)-min(ys)>2: raise AssertionError('desktop diagnostics must share one row')
            maybe_screenshot(page,f'push-devices-worker-stopped-{width}')
        except AssertionError as e:
            maybe_screenshot(page,f'FAILED-push-devices-worker-stopped-{width}')
            fail(str(e))
        finally:
            page.close()
    browser.close()
print('visual-quality-browser PASS')
