#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')

def need(cond,msg):
    if not cond: raise AssertionError(msg)

# Daily manager/staff UI must not expose implementation vocabulary when an operational phrase exists.
for rel, forbidden in {
    'admin/settings.php':['Service Worker'],
    'admin/inventory_report.php':['Recipe','Migration'],
    'admin/index.php':['چاپ و Agent'],
    'assets/js/operator.js':['Print Agent','کامپیوتر صندوق و Agent'],
    'admin/print_templates.php':['Agent/Driver','UAT','Machine-wide','Print v2'],
    'includes/panel_layout.php':['Agent صندوق'],
    'includes/help_topics.php':['Recipe','Print Agent','Queue ویندوز','Migration','Duplicate','Token قدیمی','Rotate کردن','Backup را باز یا Hash','Runtime تلقی'],
}.items():
    src=read(rel)
    for term in forbidden: need(term not in src, f'{rel}: technical UI wording remains: {term}')

printing=read('admin/printing.php')
for old in ['کلید اتصال Agent','دانلود Agent','جزئیات نسخه Agent','Agent آنلاین','مدیریت Agent','حذف Agent','Agent انتخاب نشده','Agent آفلاین','پرینتر Windows','هنوز به Windows متصل نشده','Preview ۵۸/۸۰ میلی‌متر','Import/Export']:
    need(old not in printing, f'printing manager UI still exposes implementation term: {old}')
need('سرویس چاپ داخلی' in printing and 'پرینتر' in printing, 'printing operational vocabulary missing')
printing_domain=read('includes/printing.php')
need('Attempt فعال برای این Job' not in printing_domain, 'printing recovery error still exposes implementation vocabulary')

for old in ['Attempt','Recovery Hold','Lease','Accept','claimed/unknown','صف Windows','ارسال به سیستم چاپ ویندوز']:
    need(old not in printing, f'printing manager UI still exposes state-machine vocabulary: {old}')
for old in ['Resolution معتبر نیست','قابل Resolution نیست','Job claimed','قابل Hold است']:
    need(old not in printing_domain, f'printing recovery error still exposes state-machine vocabulary: {old}')
for rel in ['includes/updater_engine/1.5.3/runtime.php']:
    src=read(rel)
    for old in ['Migration معتبر','Checksum Migration','فایل Migration','پوشه Migration','مرحله‌بندی Migration','Migration مرحله‌بندی','Checksum دیتابیس','Checksum پشتیبان']:
        need(old not in src, f'{rel}: updater manager-facing diagnostics expose implementation vocabulary: {old}')

center=read('includes/sokna_center.php')
for old in ['Purpose اتصال مرکز سکنا','Probe/Handoff فقط در حالت Strict']:
    need(old not in center, f'Center manager-facing error still exposes protocol vocabulary: {old}')

# Help must cover the newly hardened optional-module workflows, not only old feature descriptions.
help_src=read('includes/help_topics.php')
for marker in ["'id'=>'system-capabilities'","'id'=>'supply-purchase'",'خرید و تأمین به انبار وابسته است','یک شمارش کامل انجام شود']:
    need(marker in help_src, f'Help Center missing current module workflow: {marker}')

# Action-sensitive confirms must name the exact action; generic affirmative CTA is never the product language.
core=read('assets/js/panel-core.js')
need('بله، انجام شود' not in core and 'بله، ادامه بده' not in core, 'generic affirmative confirm CTA returned')
checks={
    'admin/purchases.php':['data-confirm-title="حذف از فهرست خرید"','data-confirm-ok="حذف از فهرست"','data-confirm-danger="1"'],
    'admin/categories.php':['data-confirm-title="حذف دسته‌بندی؟"','data-confirm-ok="حذف دسته"','data-confirm-danger="1"'],
    'admin/marketing.php':['data-confirm-title="حذف کمپین؟"','data-confirm-ok="حذف کمپین"','data-confirm-danger="1"'],
    'admin/events.php':['data-confirm-title="لغو رویداد؟"','data-confirm-title="حذف رویداد؟"','data-confirm-danger="1"'],
    'admin/tables.php':['data-confirm-title="حذف میز؟"','data-confirm-ok="حذف میز"','data-confirm-danger="1"'],
    'admin/maintenance.php':['data-confirm-title="حذف نسخه بازیابی؟"','data-confirm-ok="حذف نسخه"','data-confirm-danger="1"'],
    'admin/qr.php':['data-confirm-title="ابطال QR فعلی؟"','data-confirm-ok="ابطال و ساخت QR جدید"','data-confirm-danger="1"'],
    'admin/inventory_count.php':['data-confirm-title="لغو شمارش؟"','data-confirm-ok="لغو شمارش"','data-confirm-danger="1"'],
}
for rel, markers in checks.items():
    src=read(rel)
    for marker in markers: need(marker in src, f'{rel}: action-specific confirm marker missing: {marker}')


# Raw HTML autofocus is forbidden on ordinary panel forms: on touch devices it can summon the keyboard on navigation.
for rel in [
    'admin/item_form.php','admin/tag_form.php','admin/campaign_form.php',
    'admin/inventory_categories.php','admin/category_form.php','admin/event_form.php',
]:
    src=read(rel)
    need(' autofocus' not in src, f'{rel}: raw HTML autofocus can summon the mobile keyboard')

# Inventory review/history vocabulary must be consistent across the whole workspace, not only the editor.
for rel in ['admin/inventory.php','admin/inventory_item.php','admin/inventory_items.php','admin/inventory_review.php','admin/inventory_receive.php','admin/inventory_adjustment.php','includes/inventory.php']:
    src=read(rel)
    for old in ['بازبینی کاتالوگ','نیازمند بازبینی','یادداشت بازبینی','نوع گردش','همه گردش‌ها','اصلاح گردش انبار']:
        need(old not in src, f'{rel}: old inventory UI vocabulary remains: {old}')
need("panel_header('تأیید کالاها','inventory')" in read('admin/inventory_review.php'), 'inventory approval page title drifted')
need("'movements'=>['inventory.php?tab=movements','سابقه']" in read('admin/inventory.php'), 'inventory history tab must use manager-facing label')

# Connection settings should describe the task, not protocol vocabulary.
acc_settings=read('admin/accommodation_settings.php')
for old in ['آدرس پایه API','کلید Bearer API','API 2.0']:
    need(old not in acc_settings, f'accommodation settings exposes protocol vocabulary: {old}')



# Supply language and focus behavior must stay consistent across buyer and staff surfaces.
for rel in ['operator/supply-needs.php','assets/js/supply-needs.js','assets/js/supply-purchases.js','includes/modules.php']:
    src=read(rel)
    for old in ['در حال تهیه','نیاز جدید']:
        need(old not in src, f'{rel}: old supply state wording remains: {old}')
need('در حال خرید' in read('operator/supply-needs.php') and 'درخواست اضافه' in read('operator/supply-needs.php'), 'staff supply states must match buyer vocabulary')
need("panel_header('درخواست خرید'" in read('operator/supply-needs.php'), 'staff entrypoint must use request-purchase terminology')
need("const touchContext=()=>window.CafeUI?.keyboard?.isTouchContext?.()" in read('assets/js/supply-needs.js'), 'supply need selection must detect touch before implicit focus')
need("if(!(window.CafeUI?.keyboard?.isTouchContext?.() ?? window.matchMedia('(pointer: coarse)').matches))" in read('assets/js/supply-purchases.js'), 'purchase same-amount shortcut must not summon keyboard on touch')
for rel in ['admin/inventory_receive.php','admin/inventory_waste.php']:
    src=read(rel)
    need('منتسب به بخش' not in src and '<label>بخش مربوط</label>' in src, f'{rel}: inventory department label remains system-facing')

print('UI language contract PASS: manager/staff copy is operational, Help covers current modules, and sensitive confirms name their action.')
