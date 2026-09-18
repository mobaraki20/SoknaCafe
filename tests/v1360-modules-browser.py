#!/usr/bin/env python3
from pathlib import Path
from playwright.sync_api import sync_playwright

ROOT=Path(__file__).resolve().parents[1]
css='\n'.join((ROOT/p).read_text(encoding='utf-8') for p in [
    'assets/css/tokens.css','assets/css/app.css','assets/css/panel.css','assets/css/panel-layout.css','assets/css/panel-components.css'
])
CORE=['هسته سامانه','منو و مهمان','سفارش و سرویس','مالی و تسویه','چاپ','اعلان‌ها']
MODULES=[
    dict(key='inventory',label='انبار',context='عملیات و موجودی',description='موجودی، ورود و ضایعات، شمارش و مصرف مواد از فروش را کنترل می‌کند.',links=['انبار'],impacts=['صفحه‌ها، عملیات پس‌زمینه و گزارش‌های وابسته به انبار از کار روزانه کنار گذاشته می‌شوند.','خرید و تأمین نیز چون به انبار وابسته است به‌صورت امن غیرفعال می‌شود.','داده‌ها و دستور مصرف مواد قبلی حذف نمی‌شوند؛ پس از فعال‌سازی دوباره یک شمارش کامل لازم است.']),
    dict(key='supply',label='خرید و تأمین',context='خرید روزانه',description='اعلام نیاز، در حال خرید و تحویل را مدیریت می‌کند و برای ثبت تحویل به انبار وابسته است.',links=['خرید'],impacts=['اعلام نیاز و صفحه خرید از منوی تیم و مدیریت حذف می‌شوند.','هیچ نیاز یا تحویل تازه‌ای ثبت نمی‌شود و اطلاعات قبلی حذف نمی‌شوند.','انبار می‌تواند بدون خرید فعال بماند؛ اما خرید بدون انبار قابل فعال‌سازی نیست.']),
    dict(key='marketing',label='کمپین‌ها و رویدادها',context='منوی مهمان',description='کمپین‌های منوی مهمان و رویدادهای قابل نمایش را یکجا کنترل می‌کند.',links=['کمپین‌ها','رویدادها'],impacts=['صفحات کمپین و رویداد از منوی مدیریت خارج می‌شوند.','در منوی مهمان کمپین یا رویدادی نمایش داده نمی‌شود.','خاموش‌کردن این قابلیت چیزی را حذف نمی‌کند.']),
    dict(key='reporting',label='گزارش‌ها و تحلیل',context='مدیریت و تحلیل',description='گزارش‌های فروش، عملیات، انبار و فعالیت کاربران را همراه با آمار تجمیعی منوی مهمان کنترل می‌کند.',links=['فروش و عملکرد','عملیات','انبار و سود','فعالیت کاربران'],impacts=['چهار صفحه گزارش از منوی مدیریت و دسترسی مستقیم خارج می‌شوند.','ثبت آمار تجمیعی تازه از رفتار منوی مهمان متوقف می‌شود.','سفارش، مالی، انبار و سابقه ثبت اقدامات اصلی همچنان فعال می‌مانند و حذف نمی‌شوند.']),
    dict(key='personnel',label='پرسنل و حقوق',context='اتصال مرکز سکنا',description='ورود امن به پرسنل و حقوق مرکز سکنا و یادآورهای حقوق را از داخل کافه کنترل می‌کند.',links=['پرسنل و حقوق','تنظیم اتصال'],impacts=['پرسنل و حقوق و یادآور عددی حقوق از منوی تیم حذف می‌شوند.','ورود امن و APIهای پرسنلی کافه به مرکز سکنا متوقف می‌شوند.','اطلاعات اتصال ذخیره‌شده پاک نمی‌شود و عملیات سفارش، صندوق، انبار و چاپ مستقل باقی می‌مانند.']),
]

def module_card(m,state):
    configured=state.get('configured',False); ready=state.get('ready',False); blocked=state.get('blocked',False); disable_blocked=state.get('disable_blocked',False)
    status='فعال' if ready else ('نیازمند آماده‌سازی' if configured else 'غیرفعال')
    badge_cls='is-success' if ready else ('is-warning' if configured else 'is-muted')
    classes=('is-enabled' if configured else 'is-disabled') + (' is-waiting' if configured and not ready else '')
    if configured and not ready:
        note='قابلیت روشن است اما برای بازگشت به عملیات روزانه هنوز نیاز به آماده‌سازی دارد.'
    elif not configured and blocked:
        note='قابلیت خاموش است. برای فعال‌کردن ابتدا وابستگی عملیاتی آن را آماده کنید.'
    else:
        note='قابلیت فعال است و تنظیمات داخلی همان بخش همچنان مستقل‌اند.' if ready else 'قابلیت خاموش است و از کار روزانه کنار گذاشته شده است.'
    links=''.join(f'<a class="btn btn-light">{x}</a>' for x in m['links']) if configured and m['key']!='supply' or (ready and configured) else ''
    impacts=''.join(f'<li>{x}</li>' for x in m['impacts'])
    disabled=' disabled aria-disabled="true"' if ((not configured and blocked) or (configured and disable_blocked)) else ''
    blocker='<div class="settings-inline-note module-disable-blocker"><strong>برای خاموش‌کردن، ابتدا اقلام «در حال خرید» را تعیین تکلیف کنید.</strong> <a href="#">مشاهده اقلام در حال خرید</a></div>' if disable_blocked else ''
    return f'''<article class="card module-control-card {classes}" data-module-key="{m['key']}">
<div class="module-control-head"><div class="module-control-identity"><span class="module-control-icon">◆</span><div class="panel-copy-stack"><div class="module-control-title-row"><h3>{m['label']}</h3><span class="module-control-context">{m['context']}</span></div><p>{m['description']}</p></div></div><span class="panel-status-badge {badge_cls}">{status}</span></div>
<div class="module-control-body"><div class="settings-inline-note module-state-note">{note}</div>{blocker}<ul class="module-impact-list">{impacts}</ul><div class="module-control-links">{links}</div><form class="panel-action-bar module-control-action"><button class="btn {'btn-outline' if configured else 'btn-primary'}" type="button"{disabled}>{'غیرفعال کردن' if configured else 'فعال کردن'}</button></form></div></article>'''

def markup(states):
    enabled_count=sum(1 for x in states.values() if x.get('ready'))
    cards=''.join(module_card(m,states[m['key']]) for m in MODULES)
    chips=''.join(f'<span class="modules-core-chip">{x}</span>' for x in CORE)
    return f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>{css}</style></head><body class="panel-body"><main class="panel-main"><div class="panel-content"><div class="panel-page-flow modules-workspace">
<div class="toolbar modules-toolbar"><div class="panel-copy-stack"><strong>امکانات سامانه</strong><small class="muted">قابلیت‌های اختیاری را از یک محل فعال یا غیرفعال کنید؛ فقط بخش‌هایی که خاموش‌شدنشان در کل سامانه کنترل شده است اینجا کلید دارند.</small></div></div>
<div class="modules-overview"><div class="modules-overview-metric"><span>قابل مدیریت</span><strong>۵</strong></div><div class="modules-overview-metric"><span>فعال</span><strong>{enabled_count}</strong></div></div>
<div class="panel-helper-note modules-permission-note"><strong>این صفحه دسترسی کاربران را تغییر نمی‌دهد.</strong><span>فعال بودن یک قابلیت با مجوز اعضای تیم فرق دارد. دسترسی هر شخص از <a href="#">اعضای تیم</a> مدیریت می‌شود.</span></div>
<section class="modules-section"><div class="modules-section-head"><div class="panel-copy-stack"><h2>قابلیت‌های قابل مدیریت</h2><small class="muted">خاموش‌کردن، قابلیت را از کار روزانه کنار می‌گذارد؛ حذف اطلاعات یک عملیات جداگانه است.</small></div></div><div class="modules-control-list">{cards}</div></section>
<section class="card modules-core-card"><div class="card-head"><div class="panel-copy-stack"><h2>بخش‌های همیشه فعال</h2><small class="muted">این بخش‌ها برای عملیات پایه سکنا لازم‌اند و عمداً کلید خاموش/روشن ندارند.</small></div><span class="panel-status-badge is-success">پایه</span></div><div class="card-body"><div class="modules-core-list">{chips}</div></div></section>
</div></div></main></body></html>'''

ALL_READY={k:dict(configured=True,ready=True,blocked=False) for k in [m['key'] for m in MODULES]}
CASES=[
    ALL_READY,
    {**ALL_READY,'inventory':dict(configured=False,ready=False,blocked=False),'supply':dict(configured=False,ready=False,blocked=True)},
    {**ALL_READY,'inventory':dict(configured=True,ready=False,blocked=False),'supply':dict(configured=False,ready=False,blocked=True)},
    {**ALL_READY,'supply':dict(configured=False,ready=False,blocked=False)},
    {**ALL_READY,'supply':dict(configured=True,ready=True,blocked=False,disable_blocked=True)},
    {k:dict(configured=False,ready=False,blocked=False) for k in [m['key'] for m in MODULES]},
]

with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    for states in CASES:
        for width,height in ((320,720),(390,844),(412,915),(768,1024),(1366,900)):
            page=browser.new_page(viewport={'width':width,'height':height}); page.set_content(markup(states)); page.wait_for_timeout(25)
            assert page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'), (states,width,'overflow')
            assert page.locator('.module-control-card').count()==5
            assert page.locator('.modules-overview-metric').count()==2
            assert page.locator('.modules-core-chip').count()==len(CORE)
            assert page.locator('.modules-permission-note a').inner_text()=='اعضای تیم'
            for key,state in states.items():
                card=page.locator(f'[data-module-key="{key}"]'); box=card.bounding_box(); btn=card.locator('.module-control-action .btn').bounding_box()
                assert box and btn and box['x']>=-1 and box['x']+box['width']<=width+1
                assert btn['height']>=44
                assert card.locator('.module-impact-list li').count()==3
                if state.get('blocked') and not state.get('configured'):
                    assert card.locator('.module-control-action .btn').is_disabled(), (key,width,'blocked enable must be disabled')
                if state.get('disable_blocked') and state.get('configured'):
                    assert card.locator('.module-control-action .btn').is_disabled(), (key,width,'blocked disable must be disabled')
                    assert card.locator('.module-disable-blocker a').inner_text()=='مشاهده اقلام در حال خرید'
                if state.get('configured') and not state.get('ready'):
                    assert card.locator('.panel-status-badge').inner_text()=='نیازمند آماده‌سازی'
                if width<=640: assert btn['width']>=box['width']-40, (states,width,key,btn,box)
            page.close()
    browser.close()
print('PASS modules management browser: five optional modules, Inventory/Supply dependency waiting state, mobile actions at 320/390/412/768/1366')
