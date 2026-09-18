#!/usr/bin/env python3
from pathlib import Path
import re
try:
    from playwright.sync_api import sync_playwright
except Exception:
    print('Playwright unavailable; icon browser skipped.'); raise SystemExit(0)
ROOT=Path(__file__).resolve().parents[1]
fn=(ROOT/'includes/functions.php').read_text(encoding='utf-8')
part=fn[fn.index('function category_icon_registry'):fn.index('function category_icon_library')]
keys=[]
for key in re.findall(r"'key'=>'([^']+)'", part):
    if key not in keys: keys.append(key)
sprite=(ROOT/'assets/icons/ui-sprite.svg').read_text(encoding='utf-8')
inner=sprite[sprite.index('>')+1:sprite.rfind('</svg>')]
icons=''.join(f'<div class="cell"><svg class="ui-icon" viewBox="0 0 24 24" data-key="{k}"><use href="#icon-{k}"></use></svg><span>{k}</span></div>' for k in keys)
HTML=f'''<!doctype html><html dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>*{{box-sizing:border-box}}body{{margin:0;font-family:Arial}}.defs{{position:absolute;width:0;height:0;overflow:hidden}}.grid{{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:6px;padding:8px}}.cell{{min-width:0;height:74px;border:1px solid #ddd;border-radius:12px;display:grid;place-items:center;align-content:center;gap:5px;overflow:hidden}}.cell span{{font-size:8px;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;direction:ltr}}.ui-icon{{width:28px;height:28px;display:block;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}}@media(min-width:700px){{.grid{{grid-template-columns:repeat(8,minmax(0,1fr))}}}}</style><body><svg class="defs" xmlns="http://www.w3.org/2000/svg">{inner}</svg><div class="grid">{icons}</div></body></html>'''
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    for width in (320,390,412,1366):
        page=browser.new_page(viewport={'width':width,'height':900});page.set_content(HTML)
        assert page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1'), f'{width}: icon gallery root overflow'
        for item in page.locator('.ui-icon').all():
            key=item.get_attribute('data-key')
            box=item.evaluate('''e=>{const u=e.querySelector('use');let b;try{b=u.getBBox()}catch(_){b={x:0,y:0,width:0,height:0}};return {x:b.x,y:b.y,w:b.width,h:b.height,cx:b.x+b.width/2,cy:b.y+b.height/2}}''')
            assert box['w']>=5 and box['h']>=5, f'{key}: visually empty {box}'
            assert box['x']>=-1.2 and box['y']>=-1.2 and box['x']+box['w']<=25.2 and box['y']+box['h']<=25.2, f'{key}: drawing clips 24x24 canvas {box}'
            assert abs(box['cx']-12)<=3.2 and abs(box['cy']-12)<=3.2, f'{key}: optical center unusually off {box}'
        page.close()
    browser.close()
print(f'Icon system browser PASS: {len(keys)} category/future icons render non-empty, centered and unclipped at 320/390/412/1366.')
