#!/usr/bin/env python3
from pathlib import Path
import subprocess, tempfile, base64
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
markup=subprocess.check_output(['php','-r',f"require '{ROOT}/includes/functions.php'; echo image_picker_html(null,'تصویر کمپین','', 'image/png,image/jpeg,image/webp', true, null);"],text=True)
css='\n'.join((ROOT/p).read_text(encoding='utf-8') for p in ['assets/css/tokens.css','assets/css/app.css','assets/css/panel.css','assets/css/panel-layout.css','assets/css/panel-components.css'])
js='\n'.join((ROOT/f).read_text(encoding='utf-8') for f in ['assets/js/panel-core.js','assets/js/panel-media.js'])
html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><style>.hidden{{display:none!important}}{css}</style></head><body class="panel-body"><main id="panelContent"><form method="post">{markup}<button>ذخیره</button></form></main><div id="panelToast" class="panel-toast hidden"></div></body></html>'''
# 1x1 transparent PNG
png=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')
with tempfile.TemporaryDirectory() as tmp:
    image=Path(tmp)/'campaign.png';image.write_bytes(png)
    with sync_playwright() as p:
        browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
        page=browser.new_page(viewport={'width':390,'height':844});errors=[];page.on('pageerror',lambda err:errors.append(str(err)))
        page.set_content(html);page.add_script_tag(content=js);page.wait_for_timeout(60)
        upload=page.locator('[data-image-mode-trigger="upload"]')
        with page.expect_file_chooser() as chooser_info: upload.click()
        chooser_info.value.set_files(str(image));page.wait_for_timeout(80)
        assert page.locator('[data-image-panel="upload"]').evaluate('(e)=>e.classList.contains("is-active")')
        assert page.locator('[data-image-upload-preview]').is_visible()
        assert page.locator('[data-image-upload-name]').inner_text()=='campaign.png'
        page.click('[data-image-mode-trigger="library"]');page.wait_for_timeout(60)
        assert page.locator('[data-image-panel="library"]').evaluate('(e)=>e.classList.contains("is-active")')
        if page.locator('[data-image-library-search]').count():
            assert page.locator('[data-image-library-search]').evaluate('(e)=>document.activeElement===e')
            page.fill('[data-image-library-search]','عبارت ناموجود قطعی')
            assert page.locator('[data-image-library-card]:not(.hidden)').count()==0
        head=page.locator('.image-library-dialog-head')
        def swipe(y0,y1,pid,hold=0):
            head.dispatch_event('pointerdown',{'pointerId':pid,'pointerType':'touch','button':0,'clientX':150,'clientY':y0,'isPrimary':True})
            head.dispatch_event('pointermove',{'pointerId':pid,'pointerType':'touch','button':0,'clientX':150,'clientY':y1,'isPrimary':True})
            if hold: page.wait_for_timeout(hold)
            head.dispatch_event('pointerup',{'pointerId':pid,'pointerType':'touch','button':0,'clientX':150,'clientY':y1,'isPrimary':True})
        swipe(90,122,21,120);page.wait_for_timeout(240)
        assert page.locator('[data-image-panel="library"]').evaluate('(e)=>e.classList.contains("is-active")')
        swipe(90,230,22);page.wait_for_timeout(220)
        assert not page.locator('[data-image-panel="library"]').evaluate('(e)=>e.classList.contains("is-active")')
        assert page.locator('[data-image-panel="upload"]').evaluate('(e)=>e.classList.contains("is-active")')
        page.click('[data-image-mode-trigger="keep"]')
        assert not errors,errors
        browser.close()
print('Media picker browser checks passed: device chooser opens directly, upload preview works, site album opens/searches, and modes switch predictably.')
