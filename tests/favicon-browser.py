#!/usr/bin/env python3
from pathlib import Path
import re
from playwright.sync_api import sync_playwright

ROOT=Path(__file__).resolve().parents[1]
settings=(ROOT/'admin/settings.php').read_text(encoding='utf-8')
blocks=re.findall(r'<script>\s*(.*?)\s*</script>',settings,flags=re.S)
script=next((b for b in blocks if 'faviconForm' in b),None)
assert script,'Favicon settings script is missing.'
html='''<!doctype html><html lang="fa" dir="rtl"><body>
<form id="faviconForm"><input id="faviconSource" type="file" accept="image/png,image/jpeg,image/webp"><input type="hidden" name="favicon_payload" id="faviconPayload"><button type="submit" name="action" value="favicon" id="faviconSave">save</button><button type="submit" name="action" value="favicon_reset">reset</button><p id="faviconStatus"></p></form>
<script>document.getElementById('faviconForm').addEventListener('submit',e=>{if(document.getElementById('faviconPayload').value)e.preventDefault()},true);</script>
</body></html>'''
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    page=browser.new_page()
    page.set_content(html)
    page.add_script_tag(content=script)
    page.locator('#faviconSource').set_input_files(str(ROOT/'assets/icons/favicon-512.png'))
    page.locator('#faviconSave').click()
    page.wait_for_function("document.getElementById('faviconPayload').value.length > 100")
    data=page.locator('#faviconPayload').input_value()
    keys=page.evaluate("p=>Object.keys(JSON.parse(p)).sort()",data)
    assert keys==['180','192','32','512'],keys
    dimensions=page.evaluate('''async p=>{const o=JSON.parse(p),r={};for(const [k,v] of Object.entries(o)){const i=new Image();await new Promise((ok,no)=>{i.onload=ok;i.onerror=no;i.src=v});r[k]=[i.width,i.height]}return r}''',data)
    assert dimensions=={'32':[32,32],'180':[180,180],'192':[192,192],'512':[512,512]},dimensions
    assert 'ذخیره' in page.locator('#faviconStatus').inner_text()
    browser.close()
print('Favicon browser processing passed: four padded PNG sizes are prepared before submit.')
