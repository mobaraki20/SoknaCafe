#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
css='\n'.join((ROOT/p).read_text(encoding='utf-8') for p in [
 'assets/css/tokens.css','assets/css/app.css','assets/css/responsive.css','assets/css/panel.css','assets/css/panel-layout.css','assets/css/panel-components.css','assets/css/operator-live.css','assets/css/inventory.css'
])
menus=(ROOT/'assets/js/panel-menus.js').read_text(encoding='utf-8')

def no_h_overflow(pg,w):
    vals=pg.evaluate('''()=>({sw:document.documentElement.scrollWidth,cw:document.documentElement.clientWidth,bw:document.body.scrollWidth})''')
    assert vals['sw']<=w+1 and vals['bw']<=w+1,(w,vals)

with sync_playwright() as p:
    b=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])

    # Focus owner: panel form controls have one box-shadow focus ring and no second outline.
    for width in (320,390,768):
        pg=b.new_page(viewport={'width':width,'height':760})
        pg.set_content(f'''<!doctype html><html lang="fa" dir="rtl"><meta name="viewport" content="width=device-width,initial-scale=1"><style>{css}</style><body class="panel-body"><main class="panel-content"><label for="q">قیمت</label><input id="q" class="form-control" type="number" value="220000"><input id="s" class="form-control" type="search" value="آب"></main></body></html>''')
        pg.focus('#q')
        st=pg.locator('#q').evaluate("e=>({outline:getComputedStyle(e).outlineStyle,ow:getComputedStyle(e).outlineWidth,shadow:getComputedStyle(e).boxShadow,border:getComputedStyle(e).borderColor})")
        assert st['outline']=='none' or st['ow']=='0px', (width,st)
        assert st['shadow']!='none', (width,st)
        # search native cancel decoration should not create layout overflow.
        pg.focus('#s'); no_h_overflow(pg,width)
        pg.close()

    # Operator tablist: attention gets more room, all labels stay one line and centered.
    for width in (320,360,390,412):
        pg=b.new_page(viewport={'width':width,'height':760})
        pg.set_content(f'''<!doctype html><html lang="fa" dir="rtl"><meta name="viewport" content="width=device-width,initial-scale=1"><style>{css}</style><body class="panel-body"><main class="panel-content"><section class="operator-live-head-v1280"><div class="operator-work-tabs-v1280 panel-primary-tabs" role="tablist"><button>نیازمند اقدام <span>۳۱</span></button><button>میزها</button><button>جمع اقلام</button></div></section></main></body></html>''')
        tabs=pg.locator('.operator-work-tabs-v1280 button')
        boxes=[tabs.nth(i).bounding_box() for i in range(3)]
        assert all(boxes)
        widths=[round(x['width'],1) for x in boxes]
        assert widths[0] > widths[1] and abs(widths[1]-widths[2])<=1.5,(width,widths)
        aligns=tabs.evaluate_all("els=>els.map(e=>({jc:getComputedStyle(e).justifyContent,ta:getComputedStyle(e).textAlign,ws:getComputedStyle(e).whiteSpace,sh:e.scrollHeight,ch:e.clientHeight}))")
        assert all(v['jc']=='center' and v['ta']=='center' and v['ws']=='nowrap' and v['sh']<=v['ch']+1 for v in aligns),(width,aligns)
        no_h_overflow(pg,width);pg.close()

    # User-facing numeric inputs display Persian digits but submit canonical ASCII values.
    pg=b.new_page(viewport={'width':390,'height':760})
    panel_core=(ROOT/'assets/js/panel-core.js').read_text(encoding='utf-8')
    html='<!doctype html><html lang="fa" dir="rtl"><body><main id="panelContent"><form id="f"><input class="form-control" inputmode="numeric" name="n" value="200"><input class="form-control" inputmode="decimal" name="decimal[]" value="12.5"><input class="form-control" inputmode="decimal" name="decimal[]" value="۴٫۲۵"></form></main><div id="panelToast"></div><script>window.SoknaInteraction={isKeyboard:()=>true};</script><script>'+panel_core+'</script></body></html>'
    pg.set_content(html)
    pg.wait_for_timeout(40)
    assert pg.locator('input[name="n"]').input_value()=='۲۰۰'
    assert pg.locator('input[name="decimal[]"]').nth(0).input_value()=='۱۲٫۵'
    pg.locator('input[name="n"]').fill('3500');pg.locator('input[name="n"]').dispatch_event('input')
    assert pg.locator('input[name="n"]').input_value()=='۳۵۰۰'
    vals=pg.evaluate("()=>{const fd=new FormData(document.getElementById('f'));return {n:fd.get('n'),d:fd.getAll('decimal[]')}}")
    assert vals=={'n':'3500','d':['12.5','4.25']},vals
    pg.close()

    # Report rows stay compact and never degrade into one full-width block per metric.
    for width in (320,390,412,768):
        pg=b.new_page(viewport={'width':width,'height':900})
        metrics=''.join(f'<div class="report-data-metric"><span>شاخص {i}</span><strong>{i*1000}</strong></div>' for i in range(1,5))
        pg.set_content(f'''<!doctype html><html lang="fa" dir="rtl"><meta name="viewport" content="width=device-width,initial-scale=1"><style>{css}</style><body class="panel-body"><main class="panel-content"><section class="card"><div class="report-data-list"><article class="report-data-row" style="--report-metric-count:4"><div class="report-data-primary"><strong>برگر گوشت</strong><small>۴۵ عدد</small></div>{metrics}</article></div></section></main></body></html>''')
        row=pg.locator('.report-data-row').bounding_box(); assert row
        if width<=412:
            assert row['height']<280,(width,row)
            m1=pg.locator('.report-data-metric').nth(0).bounding_box();m2=pg.locator('.report-data-metric').nth(1).bounding_box();assert m1 and m2
            assert abs(m1['y']-m2['y'])<=2,(width,m1,m2)
        no_h_overflow(pg,width);pg.close()

    # Shared 3-column list variant and category management does not create wide-table overflow.
    for width in (320,390,768):
        pg=b.new_page(viewport={'width':width,'height':760})
        pg.set_content(f'''<!doctype html><html lang="fa" dir="rtl"><meta name="viewport" content="width=device-width,initial-scale=1"><style>{css}</style><body class="panel-body"><main class="panel-content"><div class="panel-surface-stack"><section class="card panel-list-card"><div class="panel-list-row panel-list-row-3"><div class="panel-list-primary"><strong>مواد اولیه</strong><small>۱۲ کالا</small></div><div class="panel-list-secondary"><span class="panel-status-badge is-ok">فعال</span></div><div class="panel-row-actions"><div class="row-action-menu" data-action-menu><button class="btn btn-light" data-action-menu-trigger>مدیریت</button><div class="row-action-popover" data-action-menu-popover><button>تغییر نام</button><button>غیرفعال‌کردن</button></div></div></div></div></section><section class="card" style="height:80px"></section></div></main><script>{menus}</script></body></html>''')
        no_h_overflow(pg,width)
        pg.click('[data-action-menu-trigger]');pg.wait_for_timeout(50)
        pop=pg.locator('[data-action-menu-popover]').bounding_box();assert pop
        assert pop['x']>=7 and pop['x']+pop['width']<=width-7,(width,pop)
        pg.close()

    # Sidebar navigation has stable single-group visual behavior and reachable 44px-ish targets.
    pg=b.new_page(viewport={'width':390,'height':844})
    pg.set_content(f'''<!doctype html><html lang="fa" dir="rtl"><meta name="viewport" content="width=device-width,initial-scale=1"><style>{css}</style><body class="panel-body"><aside class="sidebar is-open" style="position:relative;transform:none"><div class="side-nav-groups"><section class="side-nav-group is-open is-current"><button class="side-nav-group-toggle"><span>گزارش‌ها</span></button><nav class="side-nav"><a class="active"><span class="side-nav-icon"></span><span class="side-nav-label">فروش و عملکرد</span></a><a><span class="side-nav-icon"></span><span class="side-nav-label">عملیات</span></a><a><span class="side-nav-icon"></span><span class="side-nav-label">انبار و سود</span></a><a><span class="side-nav-icon"></span><span class="side-nav-label">فعالیت کاربران</span></a></nav></section></div></aside></body></html>''')
    heights=pg.locator('.side-nav a').evaluate_all('els=>els.map(e=>e.getBoundingClientRect().height)')
    assert all(h>=42 for h in heights),heights
    pg.close();b.close()
print('1.32.14 browser checks passed: single-ring form focus, equal operator tabs, compact report rows, category/action-menu geometry and sidebar navigation.')
