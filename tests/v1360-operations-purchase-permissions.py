from pathlib import Path
R=Path(__file__).resolve().parents[1]
def t(p): return (R/p).read_text(encoding='utf-8')
def need(x,msg):
    if not x: raise AssertionError(msg)

f=t('includes/functions.php'); users=t('admin/users.php'); purchases=t('admin/purchases.php'); schema=t('database/schema.sql'); supply=t('modules/Supply/domain.php'); need_page=t('operator/supply-needs.php')
jalali=t('assets/js/panel-jalali.js'); panelcss=t('assets/css/panel-components.css')
quick=t('assets/js/staff-quick-order.js'); quickphp=t('staff/quick-order.php'); operator=t('assets/js/operator.js'); tables=t('admin/tables.php')
updater=t('includes/updater_engine/1.5.3/console.php'); runtime=t('includes/updater_engine/1.5.3/runtime.php'); layout=t('includes/panel_layout.php')

# Four always-visible responsibilities plus one Inventory-scoped responsibility; technical capabilities remain internal.
for key in ['orders_floor','preparation','cashier_accounts','shift_supervision']:
    need(f"'{key}' =>" in f or f"'{key}' => [" in f, f'missing responsibility {key}')
need("$definitions['inventory_purchase']" in f and "sokna_module_configured_enabled('inventory')" in f, 'inventory responsibility is not module-aware')
need("$responsibilities[] = 'inventory_purchase'" in f, 'stored inventory grants no longer map back to the responsibility')
need('name="responsibilities[]"' in users,'responsibility inputs missing')
need('name="capabilities[]"' not in users,'technical capability grid still exposed')
need('inventory_cost_view' in users and 'مشاهده اطلاعات مالی انبار' in users,'cost privilege not separate')
need("$capabilities[] = 'inventory_view'" in f and "$capabilities[] = 'inventory_operations'" in f,'inventory bundle mapping missing')
need("user_has_capability('inventory_operations', $user) && user_has_capability('shift_supervision', $user)" in f,'sensitive inventory manager derivation missing')

# 1.35 supply model: staff can report needs without purchase authority; purchase/receive is one workflow.
need('user_can_report_supply_needs' in f and 'supply_need_allowed_departments' in f,'supply reporting permission helpers missing')
need('user_can_manage_purchases()' in purchases,'purchase guard missing')
for table in ['inventory_supply_needs','inventory_supply_receipts','inventory_supply_receipt_allocations']:
    need(f'CREATE TABLE IF NOT EXISTS {table}' in schema,f'{table} missing from schema')
for old in ['inventory_purchase_cycles','inventory_purchase_lines']:
    need(f'CREATE TABLE IF NOT EXISTS {old}' not in schema,f'legacy purchase table still current: {old}')
need('ثبت تحویل و ورود انبار' in purchases and 'inventory_record_movement_locked' in supply,'physical receive-to-ledger workflow missing')
need('در حال خرید' in purchases and 'supply_mark_group_preparing_locked' in supply,'buyer commitment workflow missing')
need('id="supplyNeedForm"' in need_page and "$_POST['inventory_item_id']" in need_page and "$_POST['quantity_major']" in need_page,'multi-item supply need form contract missing')
need('id="supplySubmit"' in need_page and 'ثبت درخواست خرید' in need_page,'canonical supply submit action missing')
need('supply_request_upsert_locked($pdo' in need_page and 'function supply_request_upsert_locked' in supply,'supply request domain owner missing')
for forbidden in ['نیازمند تصمیم','این نوبت نه','بستن خرید جاری']:
    need(forbidden not in purchases,f'old purchase workflow remains: {forbidden}')
need("'purchases' => [$base . '/admin/purchases.php', 'خرید']" in layout or '/admin/purchases.php' in layout,'purchase nav missing')

# Inline Jalali picker uses one delegated swipe owner and preserves vertical scroll.
need("closest?.('[data-jalali-days]')" in jalali and "'.jalali-picker-dialog,.jalali-picker-inline'" in jalali,'shared Jalali swipe owner missing')
need('Math.abs(dx) < 42' in jalali and 'Math.abs(dx) <= Math.abs(dy) * 1.2' in jalali,'swipe axis guard missing')
need('touch-action:pan-y' in panelcss,'calendar vertical scroll contract missing')

# Quick Order has one predictable success destination: operational tables.
need('data-success-url=' in quickphp and '?work=tables#tables' in quickphp,'Quick Order success destination missing')
need("searchParams.set('quick_order_table'" in quick and "searchParams.set('quick_order_success'" in quick and "searchParams.set('open_table'" in quick and "searchParams.set('quick_order_order'" in quick,'success context missing')
need('is-quick-order-success' in operator,'table success highlight missing')

# Tables/QR informational row opens management detail, actions remain separate.
need('table-management-row is-navigable' in tables and 'data-row-href="?edit=' in tables,'table informational row is not clickable to edit')
need('data-action-menu-trigger' in tables,'secondary table actions missing')

# Updater close is navigation, never disguised logout.
need('maintenance.php' in updater,'updater return target wrong')
need('name="action" value="logout"' not in updater,'logout still exposed in updater header')
need('content:"×"' not in updater and "content:'×'" not in updater,'fake close icon still present')
need("const SOKNA_UPDATER_RUNTIME_VERSION = '1.5.3';" in runtime,'updater runtime identity not 1.5.3')
print('1.36.0 operations/permissions/modular-supply contracts PASS.')
