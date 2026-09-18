#!/usr/bin/env python3
from pathlib import Path
import re
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
source=(ROOT/'includes/updater_engine/1.5.3/console.php').read_text(encoding='utf-8')
style=re.search(r'<style>(.*?)</style>',source,re.S).group(1)
script=re.search(r'<script>\n\(\(\)=>\{\n  const digits=.*?</script>',source,re.S)
common_script=script.group(0).replace('<script>','').replace('</script>','') if script else ''

def page(body):
    return f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><style>{style}</style></head><body><main class="shell">{body}</main><div class="modal-layer hidden" id="rollbackDialog" role="dialog" aria-modal="true"><section class="modal"><h2 id="rollbackTitle">بازگشت؟</h2><p id="rollbackCopy"></p><div class="notice warn hidden" id="rollbackDbWarning">هشدار دیتابیس</div><form id="rollbackForm"><input id="rollbackRestoreId"><label class="field hidden" id="rollbackConfirmField"><span>بازگشت</span><input id="rollbackConfirmText"></label><div class="modal-actions"><button id="rollbackSubmit">شروع</button><button type="button" id="rollbackCancel">انصراف</button></div></form></section></div><script>{common_script}</script></body></html>'''
login='''<header class="top"><div class="brand"><span class="logo">↻</span><div><b>به‌روزرسانی و بازیابی</b></div></div></header><section class="card"><div class="pad"><div class="section-head"><h1>ورود مدیر</h1></div><label class="field"><span>نام کاربری مدیر</span><input autocomplete="username"></label><label class="field"><span>رمز عبور</span><input type="password"></label><button class="btn primary">ورود</button></div></section>'''
healthy='''<header class="top"><div class="brand"><span class="logo">↻</span><div><b>به‌روزرسانی و بازیابی</b></div></div><div class="top-actions"><a class="btn outline small">مدیریت</a><button class="btn subtle small">خروج</button></div></header><section class="health-strip"><div class="health-copy"><div class="health">سامانه سالم است</div><small class="muted">مرکز بازیابی آماده است.</small></div><div class="version-now"><small>نسخه نصب‌شده</small><b dir="ltr">1.36.0-rc.3</b></div></section><section class="card"><div class="pad"><div class="section-head"><div><h2>بازگشت امن</h2></div></div><div class="restore-list"><article class="restore-row"><div class="restore-copy"><b>1.32.16 → 1.32.15</b><div class="restore-meta"><time class="human-time" datetime="2026-08-16T08:00:00+00:00">raw</time><span class="scope-pill">فقط فایل‌ها</span></div></div><button class="btn outline small" type="button" data-rollback-open data-restore-id="abc" data-from="1.32.16" data-to="1.32.15" data-db="0">بازگشت</button></article><article class="restore-row"><div class="restore-copy"><b>1.32.15 → 1.32.14</b><div class="restore-meta"><time class="human-time" datetime="2026-08-15T08:00:00+00:00">raw</time><span class="scope-pill danger">فایل‌ها + دیتابیس</span></div></div><button class="btn outline small" type="button" data-rollback-open data-restore-id="dbx" data-from="1.32.15" data-to="1.32.14" data-db="1">بازگشت</button></article></div></div></section><section class="card"><div class="pad"><h2>آخرین فعالیت‌ها</h2><div class="history-list"><article class="history-row"><div class="history-copy"><b class="history-result ok">به‌روزرسانی موفق</b><div class="history-meta"><span>1.32.15 → 1.32.16</span><time class="human-time" datetime="2026-08-16T08:00:00+00:00">raw</time></div></div></article></div></div></section>'''
running='''<section class="card job-focus"><div class="pad"><div class="job-status"><h2>1.32.15 به 1.32.16</h2><span class="badge">در حال اجرا</span></div><div class="progress"><i style="width:68%"></i></div><div class="job-progress-meta"><b>۶۸٪</b><span class="muted">نصب فایل‌ها</span></div></div></section>'''
with sync_playwright() as p:
    b=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    for width,height in [(320,720),(360,800),(390,844),(412,915),(768,900),(1366,900)]:
        for body in (login,healthy,running):
            pg=b.new_page(viewport={'width':width,'height':height});pg.set_content(page(body));pg.wait_for_timeout(20)
            assert pg.evaluate('document.documentElement.scrollWidth<=innerWidth+1'), (width,body[:20])
            pg.close()
    pg=b.new_page(viewport={'width':320,'height':720});pg.set_content(page(healthy));pg.wait_for_timeout(30)
    ver=pg.locator('.version-now b');box=ver.bounding_box();line=float(pg.evaluate("parseFloat(getComputedStyle(document.querySelector('.version-now b')).lineHeight)"))
    assert box and box['height'] <= line*1.25, 'installed dev version must stay on one line at 320px'
    pg.close()
    pg=b.new_page(viewport={'width':390,'height':844});pg.set_content(page(healthy));pg.wait_for_timeout(30)
    assert 'raw' not in pg.locator('.human-time').first.inner_text(), 'human date should replace ISO/raw time'
    pg.locator('[data-restore-id="abc"]').click();assert pg.locator('#rollbackDialog').is_visible();assert pg.locator('#rollbackConfirmField').is_hidden();pg.locator('#rollbackCancel').click();assert pg.locator('#rollbackDialog').is_hidden()
    pg.locator('[data-restore-id="dbx"]').click();assert pg.locator('#rollbackDbWarning').is_visible();assert pg.locator('#rollbackConfirmField').is_visible();assert pg.locator('#rollbackConfirmText').get_attribute('required') is not None
    pg.keyboard.press('Escape');assert pg.locator('#rollbackDialog').is_hidden()
    pg.close();b.close()
print('Current Updater Engine 1.5.3 browser passed at 320/360/390/412/tablet/desktop: compact shell, human dates, no overflow, contextual rollback confirmation and stronger DB restore warning.')
