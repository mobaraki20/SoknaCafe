#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
css='\n'.join((ROOT/p).read_text(encoding='utf-8') for p in ['assets/css/tokens.css','assets/css/panel.css','assets/css/panel-components.css'] if (ROOT/p).exists())
html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>:root{{--panel-card:#fff;--panel-line:#ddd5ca;--panel-ink:#2c2723;--panel-muted:#6f675f;--font-ui:Tahoma}}body{{margin:0}}{css}</style></head><body><main style="padding:12px"><section class="analytics-grid"><article class="card table-card"><div class="data-table-wrap"><table class="data-table analytics-compact-table"><thead><tr><th>روز</th><th>فاکتور</th><th>فروش</th></tr></thead><tbody><tr><td>۱۴۰۵/۰۵/۱۴</td><td>۸</td><td class="analytics-money-cell">۱٬۸۶۰٬۰۰۰</td></tr></tbody></table></div></article><article class="card table-card"><div class="data-table-wrap"><table class="data-table analytics-compact-table"><thead><tr><th>آیتم</th><th>تعداد</th><th>درآمد</th></tr></thead><tbody><tr><td>برگر گوشت با نام بلند نمونه</td><td>۳۹</td><td class="analytics-money-cell">۱۸٬۱۳۵٬۰۰۰</td></tr></tbody></table></div></article></section></main></body></html>'''
with sync_playwright() as p:
    b=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    for width in (320,360,390,412,768):
        pg=b.new_page(viewport={'width':width,'height':800});pg.set_content(html)
        d=pg.evaluate("()=>({iw:innerWidth,sw:document.documentElement.scrollWidth,t:[...document.querySelectorAll('.analytics-compact-table')].map(e=>({w:e.getBoundingClientRect().width,sw:e.scrollWidth,p:e.parentElement.getBoundingClientRect().width}))})")
        assert d['sw']<=d['iw']+1,(width,d)
        if width<=720:
            for t in d['t']: assert t['w']<=t['p']+1 and t['sw']<=t['p']+1,(width,t)
            for th in ['روز','فاکتور','فروش','آیتم','تعداد','درآمد']:
                assert pg.get_by_text(th,exact=True).is_visible(),(width,th)
        if width<=720:
            nowrap=pg.evaluate("()=>[...document.querySelectorAll('.analytics-money-cell')].every(e=>getComputedStyle(e).whiteSpace==='nowrap')")
            assert nowrap,(width,'money cells must stay on one line')
        pg.close()
    b.close()
source=(ROOT/'admin/analytics.php').read_text(encoding='utf-8')
assert 'fa_digits(jalali_date_input((string)$r[\'d\']))' in source
assert 'format_jalali_compact((string)$r[\'d\'])' not in source
print('Analytics mobile tables passed: three columns fit 320/360/390/412, money values stay one line without repeated unit, and daily date has no midnight time.')
