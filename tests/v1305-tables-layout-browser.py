#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
css='\n'.join((ROOT/p).read_text(encoding='utf-8') for p in ['assets/css/tokens.css','assets/css/app.css','assets/css/panel.css','assets/css/panel-layout.css','assets/css/panel-components.css'] if (ROOT/p).exists())
rows=''.join(f'''<div class="panel-list-row table-management-row"><div class="panel-list-primary table-management-primary"><div class="table-management-identity"><span class="table-medallion table-medallion-sm state-free"><small>میز</small><strong>{i}</strong></span><div class="panel-copy-stack"><strong>میز {i}</strong><small class="table-management-mobile-meta">بدون بخش · آزاد</small></div></div></div><div class="panel-list-value table-management-zone"><span>بخش</span><strong>—</strong></div><div class="panel-list-value table-management-status"><span>وضعیت</span><strong>آزاد</strong></div><div class="panel-row-actions"><button class="btn btn-sm btn-light table-management-action"><span>•••</span><span class="table-action-label">مدیریت</span></button></div></div>''' for i in range(1,36))
html=f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>:root{{--line:#ddd5ca;--surface:#fff;--text:#2c2723;--muted:#6f675f;--primary:#365b4c;--font-ui:Tahoma;--panel-top-offset:0px;--panel-line:#ddd5ca;--panel-surface:#fff;--panel-warning:#b67814}}body{{margin:0;background:#f5f1e9}}{css}</style></head><body><main style="padding:16px"><div class="page-grid tables-page-grid"><section class="card table-card tables-list-card panel-list-card"><div class="table-management-list">{rows}</div></section><section class="card tables-editor-card"><div class="card-head"><div class="panel-copy-stack"><h2>میز جدید</h2><small>فرم مدیریت</small></div></div><div class="card-body"><div style="height:320px">فرم</div></div></section></div></main></body></html>'''
with sync_playwright() as p:
    b=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    for width in (390,768,1366,1920):
        pg=b.new_page(viewport={'width':width,'height':800});pg.set_content(html,wait_until='load')
        assert pg.evaluate('document.documentElement.scrollWidth')<=width+1, width
        first=pg.locator('.table-management-row').first
        fb=first.bounding_box(); assert fb and fb['width'] <= width+1
        action=pg.locator('.table-management-action').first.bounding_box(); assert action and action['height']>=44 and action['width']>=44
        if width<=640:
            assert fb['height'] <= 90,(width,fb)
            assert pg.locator('.table-management-mobile-meta').first.evaluate("e=>getComputedStyle(e).display") != 'none'
            assert pg.locator('.table-management-zone').first.evaluate("e=>getComputedStyle(e).display") == 'none'
            assert pg.locator('.table-action-label').first.evaluate("e=>getComputedStyle(e).display") == 'none'
        else:
            editor=pg.locator('.tables-editor-card'); list_card=pg.locator('.tables-list-card')
            eb=editor.bounding_box(); lb=list_card.bounding_box(); assert eb and lb
            assert eb['height'] < 600,(width,eb,lb)
            if width>=901:
                y0=eb['y']; pg.evaluate('window.scrollTo(0,700)');pg.wait_for_timeout(80); y1=editor.bounding_box()['y']
                assert y1 <= 30,(width,y0,y1)
        pg.close()
    b.close()
source=(ROOT/'admin/tables.php').read_text(encoding='utf-8')
functions=(ROOT/'includes/functions.php').read_text(encoding='utf-8')
panel_css=(ROOT/'assets/css/panel-components.css').read_text(encoding='utf-8')
assert 'backfill_unambiguous_table_numbers' not in source and 'repair_legacy_numbers' not in source
assert 'table-number-direct-action' not in source and '?missing=1' not in source
assert 'tables-management-table' not in source and 'tables-management-table' not in panel_css
assert 'table-management-row' in source and 'table-management-row' in panel_css
assert ',false) ?>' in source, 'management medallion must not guess a missing formal table number'
assert 'bool $allowLegacyFallback = true' in functions and "if (!$allowLegacyFallback) return '؟';" in functions
print('1.32.1 tables hardening passed: compact mobile list, sticky desktop editor, mandatory canonical table number, and no legacy repair/table-to-card patch stack.')
