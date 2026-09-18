#!/usr/bin/env python3
from pathlib import Path
R=Path(__file__).resolve().parents[1]
def t(p): return (R/p).read_text(encoding='utf-8')
def need(x,msg):
    if not x: raise AssertionError(msg)

version=t('VERSION.txt').strip(); need(bool(version),'development version identity')
modules=t('includes/modules.php'); bootstrap=t('bootstrap.php'); domain=t('modules/Supply/domain.php'); queries=t('modules/Supply/queries.php'); inventory=t('includes/inventory.php')
schema=t('database/schema.sql')
purchases=t('admin/purchases.php'); pjs=t('assets/js/supply-purchases.js'); needs=t('operator/supply-needs.php'); njs=t('assets/js/supply-needs.js'); css=t('assets/css/inventory.css'); layout=t('includes/panel_layout.php')

# Modular-monolith boundary: one app/db, explicit registry, Supply pilot owns domain, no compatibility copy.
need("'supply' =>" in modules and "'depends_on' => ['platform','inventory']" in modules,'Supply dependency missing')
need('sokna_module_dependency_errors' in modules and 'cycle:' in modules,'dependency verification missing')
need("require_once __DIR__ . '/includes/modules.php';" in bootstrap,'module registry not loaded')
need("require_once __DIR__ . '/modules/Supply/domain.php';" in bootstrap and "modules/Supply/queries.php" in bootstrap,'Supply module owner not loaded')
need(not (R/'includes/supply.php').exists(),'legacy Supply owner still present')
need('sokna_module_require(\'supply\')' in purchases and 'sokna_module_require(\'supply\')' in needs,'routes do not enforce module boundary')
need("sokna_module_enabled('supply')" in layout and "sokna_module_enabled('inventory')" in layout,'navigation is not registry-aware')

# Preparing is a quantity reservation/commitment, not stock and not a mutable badge-only status.
for col in ('preparing_quantity_base BIGINT UNSIGNED NOT NULL DEFAULT 0','preparing_by_user_id INT UNSIGNED NULL','preparing_at DATETIME NULL'):
    need(col in schema,col+' missing')
need('inventory_supply_receipt_allocations' in schema,'receipt allocation audit table missing')
need('supply_need_uncommitted' in domain and 'supply_need_remaining($need) - (int)($need[\'preparing_quantity_base\']' in domain,'uncommitted formula missing')
need('$requestedTotal=$fulfilled+$preparing+$requestedBase;' in domain,'staff update can overwrite preparing snapshot')
need('supply_mark_group_preparing_locked' in domain and 'supply_return_group_from_preparing_locked' in domain,'preparing transitions missing')
need('expectedUncommittedBase' in domain and 'expectedPreparingBase' in domain and 'expected_preparing_quantity_base' in domain,'stale buyer action guards missing')
need('supply_receive_preparing_locked' in domain and "'movement_type'=>'purchase_receive'" in domain,'preparing receipt does not reach ledger')
need('inventory_record_movement_locked' in domain and 'UPDATE inventory_balances' not in domain,'Supply bypasses Inventory owner')
need('inventory_supply_receipt_allocations' in domain and 'allocated_quantity_base' in domain,'department allocation audit missing')
need("$toAllocate=min($received,$preparedTotal);" in domain and '$allocated=min($prepared,$toAllocate);' in domain,'partial/over receive allocation missing')
need("'source_type'=>'inventory_supply_group'" in domain and "'idempotency_key'=>'supply:receive:'" in domain,'group receipt idempotency/source missing')

# Unknown catalog items are not silently trusted.
need('inventory_create_unreviewed_item_locked' in domain and "'needs_review'" in inventory and 'review_note' in inventory and 'inventory.item_created_from_supply' in inventory,'unknown item review contract missing or outside Inventory owner')

# Buyer-facing UI is grouped and state-oriented, with no stock mutation on prepare.
need('supply_purchase_groups($pdo)' in purchases and 'شروع خرید همه' in purchases and 'در حال خرید' in purchases,'grouped purchase flow missing')
need('data-open-purchase-receive' in purchases and 'group_key' in purchases,'receipt is not group-based')
need('expected_quantity_base' in purchases and 'purchaseExpectedPreparing' in purchases,'purchase UI does not send state snapshots')
need('اشتراک فهرست در حال خرید' in purchases and 'data-purchase-share' in purchases,'buyer snapshot share list missing')
need('موجودی انبار' in purchases and "COALESCE(b.quantity_base,0)>=0" in purchases,'negative stock leaked into low-stock suggestion')
need('value="<?= e(numeric_input_display_value' not in purchases,'low-stock quantity still auto-guessed')
need('data-jalali-date' in purchases and 'data-jalali-input' not in purchases,'purchase date drifted from shared Jalali owner')
need('supply-purchases.js' in purchases and not (R/'assets/js/purchases-v1350.js').exists(),'versioned purchase JS owner not consolidated')


# Buyer-facing unknown items with the same normalized name/unit aggregate across departments without merging audit rows.
need("return 'free:'" in domain and "LOWER(TRIM(item_name_snapshot))=?" in domain,'unknown buyer aggregation missing')
need("count($openGroups)>1" in purchases,'bulk prepare should not duplicate the single-row action')
need('format_jalali_human_datetime' in purchases,'purchase timestamps are not human/Jalali')
need('panel-form-dialog-card' in purchases and 'panel-form-dialog-body' in purchases,'receipt still misuses confirmation-card geometry')
need("requestAnimationFrame(()=>unitCount?.focus())" not in pjs,'receipt still auto-opens mobile keyboard')

# Receipt quantity is deliberately blank; unit changes cannot reinterpret a stale number.
need("const clearQuantities=()=>" in pjs and "unit?.addEventListener('change',()=>syncUnit(true))" in pjs,'unit-change clear guard missing')
need("unitCount.value=active?.remaining_major" not in pjs and 'preparing_major' in pjs,'need quantity is still blindly prefilled')
need('purchaseFillPrepared' in purchases and "mode!=='base'" in pjs,'safe base-unit quick-fill missing')

# Staff UI exposes only meaningful states and makes open need editing discoverable.
need('در انتظار خرید' in needs and 'در حال خرید' in needs and 'data-edit-supply-need' in needs,'staff state/edit UI missing')
need('SOKNA_SUPPLY_OPEN' in needs and "[data-edit-supply-need]" in njs,'open need edit flow missing')
need('supply-line-note' in njs and 'توضیح، در صورت نیاز' in njs,'optional line note not exposed')
need('status-chip-success' in css and '.supply-open-row{width:100%' in css,'Supply visual state not integrated')

print(f'{version} modular Supply contract PASS.')
