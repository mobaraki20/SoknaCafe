#!/usr/bin/env python3
from pathlib import Path
import re

ROOT=Path(__file__).resolve().parents[1]
checks=0

def read(path):
    return (ROOT/path).read_text(encoding='utf-8')

def need(cond,msg):
    global checks
    checks+=1
    if not cond:
        raise AssertionError(msg)

schema=read('database/schema.sql')
migration=read('docs/architecture-migration-r2/PHASE6F_LOCAL_MIGRATION.sql')
builder=read('tools/build-release.php')
updater=read('includes/updater_engine/1.5.3/runtime.php')
modules=read('includes/modules.php')
setup=read('includes/setup_install.php')
tax=read('includes/tax.php')
admin=read('admin/tax.php')
settlement=read('includes/settlement.php')
alloc=read('includes/settlement_allocations.php')
printing=read('includes/printing.php')
renderer=read('runtime/print-worker/source/src/Sokna.PrintAgent.Worker/ReceiptRenderer.cs')
accommodation=read('includes/accommodation.php')
guest_view=read('includes/guest_menu_view.php')
guest_js=read('assets/js/menu.js')
remote=read('includes/remote_read_models.php')
reporting=read('includes/reporting.php')
guest_publish=read('includes/guest_publish.php')
public_guest=read('public_edge/guest/index.php')
quote_service=read('includes/guest_order_quote_service.php')
quote_api=read('api/order_quote.php')
public_quote=read('public_edge/api/v1/guest/compat/order_quote.php')
relay_dispatch=read('includes/relay_dispatch.php')

need("'tax' => [" in modules and "'setting_key' => 'module.tax.enabled'" in modules, 'Tax module registry missing.')
need("'module.tax.enabled'=>'0'" in setup, 'Tax must default disabled on install.')
need('tax_module_runtime_ready' in modules and 'tax_module_before_enable_locked' in modules and 'tax_module_before_disable_locked' in modules, 'Tax lifecycle/runtime readiness is not registered.')
need('tax_rate_versions' in tax and 'tax_item_policy_versions' in tax and 'effective_from' in tax, 'Tax owner must use effective-dated rate/policy history.')
need('SELECT COUNT(*) FROM tax_rate_versions' in tax and 'tax_has_live_account_rows' in tax, 'First-rate activation guard is missing.')

required_schema_tokens=[
    'CREATE TABLE IF NOT EXISTS tax_rate_versions',
    'CREATE TABLE IF NOT EXISTS tax_item_policy_versions',
    'tax_policy_snapshot VARCHAR(24)',
    'tax_rate_bps_snapshot SMALLINT UNSIGNED',
    'checkout_taxable BIGINT UNSIGNED',
    'checkout_tax BIGINT UNSIGNED',
    'remaining_tax BIGINT UNSIGNED',
    'taxable_amount BIGINT UNSIGNED',
    'tax_amount BIGINT UNSIGNED',
    'final_amount BIGINT UNSIGNED NOT NULL',
]
for token in required_schema_tokens:
    need(token in schema, f'Clean schema missing: {token}')
for token in ['tax_rate_versions','tax_item_policy_versions','tax_policy_snapshot','tax_rate_bps_snapshot','checkout_taxable','checkout_tax','remaining_tax','taxable_amount','tax_amount','final_amount']:
    need(token in migration, f'Upgrade migration missing: {token}')
need('UPDATE settlement_record_lines SET final_amount=net_amount WHERE final_amount IS NULL' in migration, 'Historical settlement lines must backfill final_amount=net_amount.')
need('module.tax.enabled' in migration, 'Tax module default must be represented in upgrade migration.')
need("arg_value($argv, 'migration')" in builder and "'migrations/' . basename($real)" in builder and "'migration'=>$migration" in builder, 'Canonical release builder must package the Phase 6F migration through the manifest.')
need("case 'migration':" in updater and "-- CAFE-STMT --" in updater and "sru_db_dump_file" in updater and "sru_db_restore" in updater, 'Updater must snapshot, execute and rollback database migrations through the canonical engine.')

insert_files=['includes/guest_order_manage_service.php','includes/guest_order_service.php','includes/staff_order_service.php','operator/api_bill.php']
for path in insert_files:
    text=read(path)
    inserts=re.findall(r"INSERT INTO order_items\([^'\"]+", text)
    need(bool(inserts), f'No order_items insert found in {path}')
    for ins in inserts:
        need('tax_policy_snapshot' in ins and 'tax_rate_bps_snapshot' in ins and 'tax_rate_version_id' in ins and 'tax_item_policy_version_id' in ins, f'Order insert without immutable Tax snapshot in {path}')

need('tax_calculate_invoice_lines' in settlement and 'taxable_amount' in settlement and 'tax_amount' in settlement, 'Settlement is not Tax-aware.')
need('allocation_version' in settlement and 'remaining_tax' in settlement, 'Settlement tax allocation state is incomplete.')
need('settlement_review_selection_tax_v2' in alloc and 'invoice_tax_amount' in alloc and 'tax_amount' in alloc and 'tax_proportional_target' in alloc, 'Itemized allocation v2 is incomplete.')
need('tax_amount' in settlement and 'reversal' in settlement.lower(), 'Tax reversal path is not represented in settlement owner.')

need('id="taxItemPolicy"' in admin and 'data-panel-condition-source="taxItemPolicy"' in admin and 'data-panel-condition-value="custom_rate"' in admin, 'Custom item tax rate must use progressive disclosure.')
need('نرخ پیش‌فرض' in admin and 'معاف از مالیات' in admin and 'نرخ اختصاصی' in admin, 'Persian semantic Tax policy labels are incomplete.')

need('cartTaxBreakdown' in guest_view and 'جمع اقلام' in guest_view and 'مالیات' in guest_view, 'Guest pre-submit tax breakdown missing.')
need('taxProfileForCartPortion' in guest_js and 'existing.tax_rate_bps' in guest_js, 'Guest append preview must honor existing order Tax snapshot.')
need('tax_policy' in guest_js and 'tax_rate_bps' in guest_js and 'مبلغ قابل پرداخت' in guest_js, 'Guest Tax state/rendering incomplete.')

need('tax_policy' in guest_publish and 'tax_rate_bps' in guest_publish and 'tax_item_profile_map' in guest_publish, 'Published Guest snapshot must carry a Tax profile per item.')
need("'tax_policy'=>(string)($taxProfile['policy']" in guest_publish and "'tax_rate_bps'=>(int)($taxProfile['rate_bps']" in guest_publish, 'Guest availability must carry the current Tax profile.')
need("array_key_exists('tax_policy',$live)" in public_guest and "array_key_exists('tax_rate_bps',$live)" in public_guest, 'Public Guest must prefer live availability Tax profiles over published fallback.')
need("'tax_policy'=>(string)($item['tax_policy']" in public_guest and "'tax_rate_bps'=>(int)($item['tax_rate_bps']" in public_guest, 'Public Guest client item model must expose Tax profile.')
need('guest_order_quote_tx' in quote_service and "'subtotal'" in quote_service and "'discount'" in quote_service and "'taxable'" in quote_service and "'tax'" in quote_service and "'total'" in quote_service, 'Authoritative guest financial quote must expose the R2 breakdown.')
need('csrf_valid' in quote_api and 'guest_order_quote(db(),$data)' in quote_api, 'Local Guest quote endpoint must remain CSRF-protected and canonical.')
need("!$state['enabled']||!$state['local_fresh']" in public_quote and "public_guest_relay_call('guest_order.quote'" in public_quote, 'Public Guest quote must fail closed without fresh Local authority and otherwise relay to Local.')
need("'guest_order.quote' => static function" in relay_dispatch and 'guest_order_quote_tx' in relay_dispatch, 'Relay dispatcher must route Guest quote to the Local quote owner.')

staff_api=read('staff/api_quick_order.php')
staff_js=read('assets/js/staff-quick-order.js')
need('allocation_version IN(1,2)' in staff_api and 'current_tax_lines' in staff_api and 'tax_calculate_invoice_lines' in staff_api, 'Staff Quick Order must understand legacy and Tax-aware account state.')
need("'tax_policy'" in staff_api and "'tax_rate_bps'" in staff_api, 'Staff catalog must expose read-only Tax profiles for preview.')
need('calculateTaxFinancials' in staff_js and 'projectedFinancials' in staff_js and 'quickOrderNewTaxHint' in read('staff/quick-order.php'), 'Staff Quick Order preview must include Tax.')

need('tax_rates_bps' in printing and "'tax' =>" in printing, 'Customer print payload must carry Tax amount/rates.')
need('TaxRateLabel' in renderer and 'tax_rates_bps' in renderer and 'مالیات' in renderer, 'Internal Print Worker must render Tax amount/rate.')
prep_start=printing.index('function print_prep_payload')
prep_end=printing.find('\nfunction ',prep_start+10)
prep_block=printing[prep_start:prep_end if prep_end!=-1 else len(printing)]
need('tax_amount' not in prep_block and 'tax_rates_bps' not in prep_block and 'taxable_amount' not in prep_block, 'Preparation ticket must not expose financial Tax fields.')

need('accommodation_validate_tax_snapshot' in accommodation, 'House tax snapshot must validate line and invoice amounts.')
need('accommodation_tax_capability_gate' in accommodation, 'House tax requires explicit destination capability.')
need("unset($snapshot['net'],$snapshot['taxable'],$snapshot['tax'])" not in accommodation, 'House snapshot must preserve tax fields.')

need('tax_amount' in reporting and 'final_amount' in reporting, 'Canonical reporting line source must expose Tax/final fields.')
need("'tax'" in remote and "'collected'" in remote and 'tax_amount' in remote, 'Remote reports must separate Tax and collected total.')
for path in ['admin/analytics.php','admin/inventory_report.php','admin/operations_report.php','admin/financial_periods.php']:
    text=read(path)
    need('tax' in text.lower(), f'{path} is not Tax-aware.')

print(f'PASS r2-phase6f-tax-integration-contract ({checks} checks)')
