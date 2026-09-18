from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def need(condition: bool, message: str) -> None:
    if not condition:
        raise AssertionError(message)


functions = read("includes/functions.php")
shell = read("assets/js/panel-shell.js")
notifications = read("assets/js/device-notifications.js")
menus = read("assets/js/panel-menus.js")
components = read("assets/css/panel-components.css")
quick = read("assets/css/quick-order.css")
quick_js = read("assets/js/staff-quick-order.js")
quick_php = read("staff/quick-order.php")
guest_css = read("assets/css/guest-menu.css")
purchases_php = read("admin/purchases.php")
supply = read("assets/js/supply-needs.js")
inventory = read("assets/css/inventory.css")
index = read("menu/index.php")
version = read("VERSION.txt").strip()
worker = read("service-worker.js")
push = read("includes/push.php")
push_action = read("api/push_action.php")

# Release identity is unique and synchronized across the install and PWA cache.
need(bool(version), "release identity missing")
need(f"const RELEASE='{version}'" in worker, "service-worker release identity is stale")
need(f"const CACHE='cafe-staff-v{version}'" in worker, "service-worker cache identity is stale")
need("['action'=>'accept_call','title'=>'رسیدگی شد']" in push, "waiter notification still exposes an intermediate accept action")
need("UPDATE waiter_calls SET status='done',active_table_guard=NULL" in push_action, "waiter notification action does not close the active call")
need("audit_log_write('waiter_call.completed'" in push_action, "waiter notification completion is not audited as completion")

# Cache identity belongs to the asset content, not only to the release label.
need("hash_file('sha256', $file)" in functions, "assets do not have a content fingerprint")
need("window.SOKNA_ICON_SPRITE" in index, "guest icon sprite is not injected through asset()")
need('data-sokna-revision="category-optical-2"' in read("assets/icons/ui-sprite.svg"), "category optical sprite revision is not explicit")
for script in ("assets/js/menu.js",):
    body = read(script)
    need("window.SOKNA_ICON_SPRITE" in body, f"{script} bypasses the sprite owner")
    need("assets/icons/ui-sprite.svg#" not in body, f"{script} hardcodes an unversioned sprite")

# Fixed sheets are body portals, outside sticky/backdrop-filter containing blocks.
need("document.body.appendChild(toolsPopover)" in shell, "global tools sheet is not portalled")
need("restoreToolsHome" in shell, "global tools sheet has no deterministic restore path")
need("CafeUI.bindSwipeDismiss" in shell and "maxStartY: 48" in shell, "global tools sheet is not bound to the shared swipe-down owner")
need("canStart: () => toolsSheetMedia.matches && toolsIsOpen()" in shell, "global tools swipe is not limited to the open mobile sheet")
need("const buttonLabel = button.querySelector('span')" in notifications, "device notification state has no stable label owner")
need("button.textContent" not in notifications, "device notification state still replaces the icon-bearing button contents")
need("document.body.appendChild(popover)" in menus, "row action sheet is not portalled")
need("restorePopoverHome" in menus, "row action sheet has no deterministic restore path")
need("[data-action-menu-popover].is-open{display:grid}" in components, "portal visibility is still owned by a detached ancestor")

# The cart stepper has one geometry owner; obsolete 122/106/102px variants stay deleted.
need(quick.count(".quick-order-line-qty{") == 1, "quick-order line stepper has multiple geometry owners")
need(".quick-order-line-qty{width:132px;height:44px;grid-template-columns:44px 44px 44px}" in quick, "stepper geometry is not deterministic")
need(".quick-order-line-qty button .ui-icon{display:block;width:20px;height:20px}" in quick, "stepper icons are not optically fixed")
for obsolete in ("width:122px", "width:106px", "width:102px"):
    need(obsolete not in quick, f"obsolete stepper width remains: {obsolete}")
need("hasTakeawayStepper=state.fulfillmentEditing&&eligible&&quantity>1" in quick_js, "takeaway multi-quantity rows have no semantic layout state")
need('quick-order-cart-line${state.fulfillmentEditing?\' is-takeaway-editing\':\'\'}${hasTakeawayStepper?\' has-takeaway-stepper\':\'\'}' in quick_js, "takeaway editing state is not rendered on the cart row")
need('const quantityUi=state.fulfillmentEditing' in quick_js and 'quick-order-line-qty-readonly' in quick_js, "takeaway edit mode still exposes total quantity as an editable stepper")
need('@media(max-width:1023px){.quick-order-line-actions{gap:5px}.quick-order-cart-line.is-takeaway-editing{grid-template-columns:1fr;grid-template-areas:"copy" "actions" "message" "input"' in quick, "takeaway editing rows still squeeze at tablet widths")
need('.quick-order-cart-line.is-takeaway-editing .quick-order-line-actions{width:100%;flex-wrap:wrap' in quick, "takeaway actions cannot wrap through the sheet breakpoint")
need('id="quickOrderTakeawayDone" type="button">تأیید</button><button class="btn btn-light btn-sm" id="quickOrderTakeawayAll"' in quick_php, "takeaway primary action is not first in RTL or still uses the old label")

# Guest review owns one compact order-note definition and a visibly distinct takeaway disclosure.
need(guest_css.count('.guest-menu-page .order-note-field{') == 1, "guest order-note field still has stacked CSS owners")
need('.order-note-field summary{list-style:none;min-height:44px' in guest_css, "guest order-note disclosure is still unnecessarily tall")
need('background:color-mix(in srgb,var(--primary) 5%,var(--guest-card))' in guest_css, "guest takeaway disclosure has no subtle visual separation")

# Purchase-request language and purchase rows use one compact root instead of card-in-card overrides.
need("درخواست خرید" in supply and "ثبت ${fa(n)} درخواست خرید" in supply, "request-purchase terminology is incomplete")
need(".supply-submit-bar{position:static" in inventory, "mobile submit bar still overlays form fields")
need('.purchase-waiting-card .purchase-need-list{grid-template-columns:1fr}' in inventory, "waiting purchase list is not compact at the root")
need('min-height:154px' not in inventory, "obsolete waiting-card forced height remains")
need('.purchase-waiting-card .purchase-need-row{' not in inventory, "waiting purchases still have a nested-card layout owner")
need('اگر چند بخش یک کالا را بخواهند' not in purchases_php, "redundant waiting-purchase explainer still consumes vertical space")
need('.purchase-waiting-card .purchase-need-actions .btn-primary{flex:0 0 auto;min-width:88px}' in inventory, "mobile primary purchase action still stretches across the row")
need('.purchase-preparing-card .purchase-need-actions .btn-primary{flex:0 1 220px;max-width:72vw}' in inventory, "preparing purchase primary action still occupies the full row")
need('@media(max-width:360px){.purchase-need-row{grid-template-columns:1fr;grid-template-areas:"main" "actions" "more"}' in inventory, "very narrow purchase rows no longer stack for readability")


need('.quick-order-cart-line.has-takeaway-stepper .quick-order-takeaway-stepper{flex:0 0 auto}' in quick, "takeaway stepper still grows across the action row")
need('purchase-share-btn' in purchases_php and "ui_icon('share')" in purchases_php and '<span>اشتراک</span>' in purchases_php, "purchase share action is not compact icon+label")
need('.inventory-toolbar-actions .purchase-share-btn{flex:0 0 auto' in inventory, "purchase share action still stretches like a primary CTA")

# Printing/template workspaces are constrained on desktop.
need(".panel-section-printing .panel-content{width:100%;max-width:1180px" in components, "printing workspace is not constrained to the operational console width")

print("UI root-fix contracts passed.")
