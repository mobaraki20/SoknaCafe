#!/usr/bin/env python3
from pathlib import Path
import re
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')

def need(cond,msg):
    if not cond: raise AssertionError(msg)

pc=read('assets/css/panel-components.css')
pl=read('assets/css/panel-layout.css')
inv=read('assets/css/inventory.css')
choice=read('assets/js/panel-choice.js')
menus=read('assets/js/panel-menus.js')
shell=read('assets/js/panel-shell.js')
core=read('assets/js/panel-core.js')
flow=read('assets/js/inventory-form-flow.js')
tables=read('admin/tables.php')
purchases=read('admin/purchases.php')
itemform=read('admin/inventory_item_form.php')
operator_css=read('assets/css/operator-live.css')

# Select affordance must be a physical down-chevron, independent of RTL mirroring.
need('border-right:2px solid currentColor' in pc and 'border-inline-end:2px solid currentColor' not in pc, 'shared Select chevron must point down in RTL')
# Mobile contextual menus are modal action sheets with backdrop/scroll containment.
need('is-mobile-action-sheet' in menus and 'panel-action-menu-backdrop' in menus, 'row action menus need mobile action-sheet owner')
need('.row-action-popover.is-mobile-action-sheet' in pc, 'mobile action-sheet geometry missing')
need('panel-action-menu-open' in menus and 'overflow:hidden' in pc, 'mobile action sheet must lock background scroll')
need('is-mobile-tools-sheet' in shell and 'panel-tools-backdrop' in shell, 'top three-dot tools need mobile action-sheet behavior')
need('.topbar-tools-menu.is-mobile-tools-sheet' in pl, 'top tools mobile sheet geometry missing')
# Low-priority update banner must not compete with an active overlay.
need('.panel-action-menu-open .pwa-update-banner' in pl and '.panel-tools-open .pwa-update-banner' in pl and '.has-panel-dialog .pwa-update-banner' in pl and '.no-scroll .pwa-update-banner' in pl, 'PWA update banner must yield to active overlays')
# Confirmation must support semantic tone and action-specific labels.
need('data-panel-confirm-icon-danger' in read('includes/panel_layout.php'), 'confirm danger icon missing')
need("confirmLayer.dataset.confirmTone" in core, 'confirm semantic tone owner missing')
need("form.dataset.confirmOk || 'ادامه'" in core, 'generic yes/do-it copy must be retired')
need('بله، انجام شود' not in core and 'بله، ادامه بده' not in core, 'ambiguous confirmation CTA remains')

need("آزادکردن میز بدون فاکتور؟',{okLabel:'آزادکردن میز',danger:true" in read('assets/js/operator.js'), 'table release confirmation must name and style the consequential action')
need('تغییر واحد ثبت موجودی؟' in itemform and 'با تغییر واحد ثبت موجودی' in itemform, 'inventory base-unit confirmation must use the same vocabulary as the form')

# Inventory sticky footer must not overlay form fields on touch/mobile.
need('@media(max-width:720px)' in inv and '.inventory-sticky-actions{position:static' in inv, 'inventory sticky actions must become in-flow on mobile')
# Implicit selection progression must not summon keyboard on touch.
need('if (CafeUI.keyboard?.isTouchContext?.()) return;' in flow, 'inventory implicit choice focus must be suppressed on touch')
# Attention count must be an optically centered fixed circle for two digits.
need('width:24px;height:24px;padding:0' in operator_css and 'line-height:1' in operator_css, 'attention tab count must be centered for two digits')
# Tables use whole-row navigation, not underlined title-only navigation.
need('data-row-href="?edit=' in tables and 'table-management-row is-navigable' in tables, 'table rows need whole-row navigation')
need('table-management-open' not in tables, 'title-only table link should be retired')
# Purchase vocabulary must be operational and confirmations action-specific.
for old in ['نیازهای خرید ·','شروع تهیه همه','برای تهیه','لغو نیاز جدید','نیازهای جدید این قلم لغو شوند؟']:
    need(old not in purchases, f'legacy purchase wording remains: {old}')
need('در انتظار خرید' in purchases and 'شروع خرید' in purchases and 'حذف از فهرست خرید' in purchases, 'purchase operational vocabulary incomplete')
need('data-confirm-title="حذف از فهرست خرید"' in purchases and 'data-confirm-danger="1"' in purchases, 'purchase destructive confirmation must be explicit')
# Inventory item editor must use manager-facing wording.
for old in ['بازبینی کاتالوگ','منتسب به بخش — پیش‌فرض','واحد اصلی موجودی']:
    need(old not in itemform, f'inventory editor technical copy remains: {old}')
need('بخش پیش‌فرض' in itemform and 'واحد ثبت موجودی' in itemform, 'inventory editor operational labels missing')




menu_item_form=read('admin/item_form.php')
need("if(window.CafeUI?.keyboard?.isTouchContext?.())return;requestAnimationFrame" in menu_item_form, 'menu recipe selection must not summon the keyboard implicitly on touch')
need("itemName');input?.focus" not in menu_item_form, 'copy-item navigation must not autofocus on mobile')
need("eventStart')?.focus" not in read('admin/event_form.php'), 'copy-event navigation must not autofocus on mobile')
need('.panel-form-dialog-head .icon-btn{flex:0 0 auto;width:44px;height:44px;min-width:44px;min-height:44px}' in pc, 'shared form-dialog close control must meet 44px touch target')

quick_css=read('assets/css/quick-order.css')
staff_queue_css=read('assets/css/staff-action-queue.css')
need('every recurring Quick Order touch action remains finger-safe' in quick_css and '.quick-order-takeaway-stepper button{height:44px;min-width:44px;min-height:44px}' in quick_css, 'Quick Order compact touch actions must be raised to 44px at the shared mobile owner')
need('account and bill controls that are compact on desktop stay touch-safe' in operator_css and '.table-account-footer-actions-v1301 .btn{min-height:44px}' in operator_css, 'operator account/bill touch actions must keep 44px targets')
need('.device-settings summary{min-height:44px}' in staff_queue_css, 'staff device-settings disclosure must keep a 44px touch target')
need('.panel-subnav a{min-height:44px}' in pl, 'secondary navigation links must keep 44px touch targets on compact layouts')
need('.panel-body .icon-btn{min-width:44px;min-height:44px}' in pc, 'shared panel icon buttons must be touch-safe at the owner level')
need('.side-nav-group-toggle,.side-nav a,.topbar-tool{min-height:44px}' in pl, 'sidebar/tool navigation must keep 44px touch targets')

print_templates=read('admin/print_templates.php')
need('aria-label="گرفتن و جابه‌جایی <?= e($sectionLabel) ?>"' in print_templates, 'print-template drag handle needs an accessible name')
need('aria-label="انتقال <?= e($sectionLabel) ?> به بالا"' in print_templates and 'aria-label="انتقال <?= e($sectionLabel) ?> به پایین"' in print_templates, 'print-template reorder arrows need accessible names')
need('.print-template-section-order .reorder-handle{width:44px;height:44px}' in pc and '.print-template-section-order .reorder-actions .btn{min-width:44px;min-height:44px' in pc, 'print-template reorder controls must keep 44px touch targets')

quick=read('staff/quick-order.php')
need('تأیید عملیات' not in quick, 'standalone Quick Order still ships the old generic confirmation title')
for marker in ['data-panel-confirm-icon-info','data-panel-confirm-icon-danger','id="panelConfirmTitle">تأیید<']:
    need(marker in quick, f'Quick Order confirm does not follow shared semantic markup: {marker}')

print('UI conformance contract PASS: prior escaped defect classes are locked at shared-owner level.')
