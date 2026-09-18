from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
def read(path): return (ROOT/path).read_text(encoding='utf-8')
def require(cond,msg):
    if not cond: raise AssertionError(msg)

items=read('admin/items.php')
items_js=read('assets/js/items-management.js')
item_form=read('admin/item_form.php')
marketing=read('admin/marketing.php')
events=read('admin/events.php')
tags=read('admin/tags.php')
printing=read('admin/printing.php')
print_templates=read('admin/print_templates.php')
messages=read('admin/messages.php')
transfer=read('admin/menu_transfer.php')
menu_js=read('assets/js/menu.js')
operator_js=read('assets/js/operator.js')
quick_js=read('assets/js/staff-quick-order.js')
waiter_js=read('assets/js/waiter.js')
device_js=read('assets/js/device-notifications.js')
push=read('admin/push_devices.php')
users=read('admin/users.php')
audit=read('includes/audit_presentation.php')

# Explicit desired state instead of blind toggle in reviewed admin domains.
for label,source in [('items',items),('marketing',marketing),('events',events),('tags',tags),('printing',printing),('users',users)]:
    require('=1-active' not in source and '=1-featured' not in source and '=1-available' not in source,
            f'{label}: blind state flip remains')
require("desired_state" in items and "desired_active" in marketing and "desired_featured" in events,
        'explicit desired state contracts missing')
require("desired_active" in tags and "desired_active" in printing and "desired_active" in users,
        'explicit active state contract missing')

# Selective audit: important business changes, not generic click tracking.
for action in [
    'menu.item_orderability_changed','menu.item_publication_changed','menu.item_important_updated','menu.items_bulk_updated',
    'menu.csv_import_applied','guest_messages.updated','guest_messages.reset','guest_message.reset','marketing.campaign_active_changed',
    'event.publication_changed','menu.tag_active_changed','print_agent_active_changed','print_job_safe_retry_scheduled','push.device_disabled'
]: require(action in '\n'.join([items,item_form,transfer,messages,marketing,events,tags,printing,print_templates,push]), f'audit action missing: {action}')
require('click.' not in '\n'.join([items,marketing,events,tags,printing,messages,transfer]), 'click-level audit should not be added')

# Admin orderability language is distinct from physical inventory availability.
require('قابل سفارش' in items and 'غیرقابل سفارش' in items_js, 'orderability terminology not applied')
require('قیمت و سفارش‌پذیری' in read('admin/index.php'), 'dashboard menu terminology still conflates inventory')
require('غیرقابل سفارش' in items and 'قابل سفارش' in items, 'menu arrange terminology not updated')

# CSV import validates asset existence and records one summarized import with file hash.
require("is_file(dirname(__DIR__) . '/' . $image)" in transfer, 'CSV image file existence validation missing')
require('file_sha256' in transfer and 'hash_file' in transfer, 'CSV import hash missing')
require('menu.csv_import_applied' in transfer, 'CSV import summary audit missing')

# Guest search is token based, capped results are explicit, and guest request errors use shared owner.
require('searchTokens.every' in menu_js, 'guest search is not all-token matching')
require('۳۰ مورد اول از' in menu_js, 'guest search cap is still silent')
require('SoknaGuestUI' in menu_js and 'requestErrorMessage' in menu_js, 'guest menu request error owner not used')
require('SoknaGuestUI' in menu_js and 'requestErrorMessage' in menu_js, 'guest shared request error owner not used')

# Staff/admin user-facing network errors use CafeUI owner.
for label,source in [('operator',operator_js),('quick order',quick_js),('waiter',waiter_js),('device notifications',device_js)]:
    require('requestErrorMessage' in source, f'{label}: shared request error owner missing')
require("error.textContent=err.message" not in operator_js, 'operator still displays raw request error')

# Printing keeps bounded recent queue explicit and adds meaningful mutation audits.
require('نیازمند رسیدگی' in printing and 'فعالیت اخیر' in printing and 'LIMIT 20' in printing, 'printing bounded exception-first queue is not disclosed')
require('print_template_reset' in print_templates and 'print_template_deleted' in print_templates and 'print_job_cancelled' in printing,
        'printing management audit coverage incomplete')

# Users preserve failed form values without storing password and use desired state.
require("form_state_store('user_form_'" in users and "form_state_pull('user_form_'" in users,
        'user form recovery is missing')
require("unset($preserved['password'],$preserved['csrf_token'])" in users, 'user form recovery must not persist password or CSRF token')
require("value=\"set_active\"" in users, 'user active action is not explicit desired state')

# Activity presentation recognizes the new important actions.
for action in ['menu.item_orderability_changed','menu.csv_import_applied','guest_messages.updated','event.cancelled','print_job_cancelled','push.device_disabled']:
    require(action in audit, f'audit presentation missing: {action}')

print('v1.32.14 menu/admin hardening contracts PASS')
