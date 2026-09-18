from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
page_php=(ROOT/'includes/operator_page.php').read_text(encoding='utf-8')
op=(ROOT/'assets/js/operator.js').read_text(encoding='utf-8')
# First-paint contract: server owns startup intent, renders tables selected, and opens detail shell immediately.
checks={
 'startup body class': "['operator-startup-table-detail']" in page_php,
 'tables first tab': "$firstTab=$startupTableIntent?'tables'" in page_php,
 'tables tab selected': "aria-selected=\"<?= $firstTab==='tables'?'true':'false' ?>\"" in page_php,
 'detail layout server-open': "<?= $startupTableIntent?' has-detail':'' ?>" in page_php,
 'detail shell server-open': "<?= $startupTableIntent?' is-open':'' ?>" in page_php,
 'startup placeholder': 'در حال به‌روزرسانی حساب میز…' in page_php,
 'client initial tables': 'const initial=startupOpenTable' in op,
}
missing=[k for k,v in checks.items() if not v]
if missing: raise SystemExit('FAIL startup structural contract: '+', '.join(missing))
branch=op.split('}else if(startupTable){',1)[1].split('}else{',1)[0]
if branch.find('openTable(startupOpenTable)') > branch.find('renderTableCards()'):
    raise SystemExit('FAIL: overview renders before startup table detail')
# Geometry contract: the exact startup class must use the same account-detail owner as the steady state.
HTML='''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body class="operator-startup-table-detail"><section id="tablesPanel" class="operator-work-panel-v1280"><div class="live-tables-layout-v1280 has-detail"><aside class="table-account-panel-v1280 is-open" id="tableDetailShell"><header class="table-account-head-v1280">حساب میز</header><div class="table-detail-body-v1190">در حال به‌روزرسانی حساب میز…</div></aside><div class="table-overview-pane-v1280"><div class="table-overview-toolbar">overview</div><div class="tables-board-v1190">tables</div></div></div></section></body></html>'''
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True, executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    for width in (390,1366):
        pg=browser.new_page(viewport={'width':width,'height':800}, is_mobile=width<700, has_touch=width<700)
        pg.set_content(HTML)
        for css in ('assets/css/tokens.css','assets/css/app.css','assets/css/responsive.css','assets/css/panel.css','assets/css/panel-layout.css','assets/css/panel-components.css','assets/css/operator-live.css'):
            pg.add_style_tag(path=str(ROOT/css))
        detail=pg.locator('#tableDetailShell')
        box=detail.bounding_box()
        assert box and box['width']>0 and box['height']>0, (width,box)
        if width==390:
            # Startup detail is the visible foreground from the first paint, not an overview frame first.
            pos=detail.evaluate("el=>getComputedStyle(el).position")
            assert pos=='fixed', (width,pos)
            assert box['x'] <= 1 and box['width'] >= width-2, (width,box)
        else:
            assert box['width'] >= 580, (width,box)
        pg.close()
    browser.close()
print('PASS: startup open_table first-paint context is detail-first')
