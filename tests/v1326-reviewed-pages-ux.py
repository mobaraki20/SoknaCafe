from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')
def require(cond,msg):
    if not cond: raise AssertionError(msg)

inventory=read('admin/inventory.php')
receive=read('admin/inventory_receive.php')
waste=read('admin/inventory_waste.php')
item_form=read('admin/inventory_item_form.php')
recipe=read('admin/item_form.php')
choice=read('assets/js/panel-choice.js')
panel_css=read('assets/css/panel-components.css')
inv_css=read('assets/css/inventory.css')
item_css=read('assets/css/items-management.css')
accommodation=read('admin/accommodation.php')
invoices=read('includes/invoices_page.php')

# Inventory workspace is task-first and exceptions are operational, not vanity KPIs.
require('href="inventory_receive.php"' in inventory and '> ورود کالا<' in inventory, 'inventory receive must be a direct primary task')
require('href="inventory_waste.php"' in inventory and '> ضایعات<' in inventory, 'inventory waste must be a direct task')
require('inventory-exception-links' in inventory and 'status=negative' in inventory and 'status=missing_cost' in inventory, 'real inventory exceptions must be directly reachable')
require("COALESCE(b.quantity_base,0)>=0" in inventory, 'low stock filter must not mix negative stock')
require('نیازمند بازبینی</option>' not in inventory, 'catalog review should not live in daily stock-status filter')
require('ارزش ثبت‌شده موجودی' in inventory and 'time_ago' in inventory, 'inventory summary labels/freshness must be operational')
require('inventory-active-count' in inventory and 'ادامه شمارش' in inventory, 'open count must be surfaced as an active task')

# Receive/waste use the operation unit consistently and keep advanced data secondary.
for label,source in [('receive',receive),('waste',waste)]:
    require('inventory_format_major_quantity' in source, f'{label}: current stock must use operation major unit')
    require('افزودن اطلاعات بیشتر' in source and 'data-inventory-add-field' in source, f'{label}: backdate/notes should be progressive optional metadata')
    require('inventory-sticky-actions' not in source, f'{label}: submit action should stay at actual form end, not sticky mid-flow')

# Purchase-unit and recipe editors are grouped surfaces with shared chevron/icon ownership.
require("ui_icon('chevron-down')" in item_form and 'inventory-unit-chevron' in item_form, 'purchase unit accordion must use shared icon')
require('.inventory-purchase-unit-list{display:grid;gap:0;border:1px solid var(--line)' in inv_css, 'purchase units must be one grouped surface')
require('.recipe-component-list{display:grid;gap:0' in item_css and '.recipe-component-row:last-child{border-bottom:0}' in item_css, 'recipe components must be grouped with dividers')

# Shared choice picker: token search, content-adaptive mobile height and accessible clear action.
require('tokens.every' in choice, 'panel choice search must match all query tokens')
require('data-panel-choice-empty-clear' in choice and 'برای «${String(query).trim()}»' in choice, 'empty state must explain the current query and offer clear')
require('width:44px;height:44px' in panel_css, 'choice clear target must meet touch target')
require('mode-browse.has-search .panel-choice-sheet{height:auto;' in panel_css, 'browse sheet must be content-adaptive instead of forced full height')

# Already-reviewed financial surfaces remain focused/compact rather than duplicating reports.
require('accommodation-issues-card' in accommodation and 'نیازمند رسیدگی' in accommodation and 'همه انتقال‌ها تعیین تکلیف شده‌اند' not in accommodation, 'accommodation must show only actionable exception state')
require('financial-filter-layer' in invoices and 'invoice-day-group' in invoices and 'invoice-transaction-row' in invoices, 'invoice archive must remain compact, date-grouped and move advanced filters out of normal mobile flow')
require('panel_footer();\n            return;' in invoices, 'invoice detail must be a focused state without re-rendering the archive below')

print('v1.32.15 reviewed-pages UX contracts PASS')
