#!/usr/bin/env python3
from __future__ import annotations
import json
import subprocess
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
def text(p:str)->str: return (ROOT/p).read_text('utf-8',errors='ignore')
def need(cond:bool,msg:str)->None:
    if not cond: raise AssertionError(msg)

modules=text('includes/modules.php')
inventory=text('includes/inventory.php')
layout=text('includes/panel_layout.php')
item_form=text('admin/item_form.php')
manager=text('admin/modules.php')
help_page=text('help.php')
help_topics=text('includes/help_topics.php')
users=text('admin/users.php')
auth=text('includes/auth.php')
worker=text('tools/inventory-worker.php')
kick=text('api/inventory_kick.php')

# Product contract: Inventory and Supply are optional modules, Supply formally depends on Inventory.
need("'inventory' => [" in modules and "'toggleable' => true" in modules.split("'inventory' => [",1)[1].split("'supply' => [",1)[0], 'Inventory must be toggle-ready')
need("'setting_key' => 'module.inventory.enabled'" in modules, 'Inventory module setting missing')
need("'runtime_ready' => 'inventory_module_runtime_ready'" in modules, 'Inventory runtime readiness callback missing')
need("'supply' => [" in modules and "'setting_key' => 'module.supply.enabled'" in modules, 'Supply module setting missing')
need("'depends_on' => ['platform','inventory']" in modules, 'Supply must formally depend on Inventory')
need("'before_disable'=>'supply_module_before_disable_locked'" in modules and 'function supply_module_before_disable_locked' in text('modules/Supply/domain.php'), 'Supply disable must protect in-flight preparing commitments')
need("preparing_quantity_base>0" in text('modules/Supply/domain.php'), 'Supply disable must reject hidden in-flight buyer commitments')
need('function supply_module_runtime_ready_locked' in text('modules/Supply/domain.php') and 'supply_module_require_runtime_ready_locked($pdo);' in text('modules/Supply/domain.php'), 'Supply mutation contracts must serialize with module toggles')
need('sokna_module_configured_enabled' in modules and 'sokna_module_runtime_ready' in modules, 'configured/effective/readiness states must be distinct')
need('sokna_module_dependents' in modules and 'sokna_module_set_enabled_locked($pdo, $dependent, false' in modules, 'disabling a module must cascade to toggleable dependents')
need("ابتدا ' . implode(' و ', $labels) . ' را فعال و آماده کنید" in modules, 'activation must fail when dependencies are not ready')

# Runtime truth table independent of DB: settings are stubbed, Inventory readiness is injected.
def state(inv_setting:bool,supply_setting:bool,inv_ready:bool)->dict:
    php = f'''
    $settings=['module.inventory.enabled'=>{1 if inv_setting else 0},'module.supply.enabled'=>{1 if supply_setting else 0}];
    $invReady={str(inv_ready).lower()};
    function setting_bool(string $key,bool $default=false): bool {{ global $settings; return array_key_exists($key,$settings)?((int)$settings[$key]===1):$default; }}
    function inventory_module_runtime_ready(): bool {{ global $invReady; return $invReady; }}
    require {str(ROOT/'includes/modules.php')!r};
    echo json_encode([
      'inventory_configured'=>sokna_module_configured_enabled('inventory'),
      'inventory_enabled'=>sokna_module_enabled('inventory'),
      'inventory_ready'=>sokna_module_runtime_ready('inventory'),
      'supply_configured'=>sokna_module_configured_enabled('supply'),
      'supply_enabled'=>sokna_module_enabled('supply'),
      'supply_ready'=>sokna_module_runtime_ready('supply'),
    ]);
    '''
    raw=subprocess.check_output(['php','-r',php],text=True)
    return json.loads(raw)

s=state(True,True,True)
need(s['inventory_enabled'] and s['inventory_ready'] and s['supply_enabled'], 'ON/ON ready state broken')
s=state(True,False,True)
need(s['inventory_ready'] and not s['supply_enabled'], 'Inventory ON + Supply OFF must be valid')
s=state(False,False,False)
need(not s['inventory_enabled'] and not s['supply_enabled'], 'Inventory OFF + Supply OFF state broken')
s=state(False,True,False)
need(not s['supply_enabled'], 'Supply must not become effective while Inventory is off even if stale setting says ON')
s=state(True,True,False)
need(s['inventory_enabled'] and not s['inventory_ready'] and not s['supply_enabled'], 're-enabled Inventory must stay visible but block Supply until reconciliation')

# Disable/re-enable lifecycle: old balances are not trusted after a disabled period.
need('function inventory_module_runtime_ready_locked' in inventory and "sokna_module_setting_state_locked($pdo, 'inventory')" in inventory, 'Inventory order/stock writes must serialize with module toggles')
need('inventory_module_runtime_ready_locked($pdo)' in inventory[inventory.find('function inventory_enqueue_order_event_tx'):inventory.find('function inventory_apply_order_consumption_locked')], 'order outbox enqueue must use locked module readiness')
need('inventory_module_configured_locked($pdo)' in inventory[inventory.find('function inventory_count_start'):inventory.find('function inventory_count_cancel_locked')], 'count start must serialize with disable')
need('function inventory_module_before_disable_locked' in inventory, 'Inventory disable safety lifecycle missing')
need("status<>'done'" in inventory and "status='draft'" in inventory, 'disable must reject unresolved consumption/count work')
need('function inventory_module_after_disable_locked' in inventory and "inventory_reconciliation_required" in inventory, 'disable must mark old balance untrusted')
need('function inventory_reconciliation_required_locked' in inventory, 'reconciliation finalization must read locked DB truth, not stale setting cache')
need("inventory.reconciliation_completed" in inventory and "scope_type'=>'full'" in inventory, 'only full count may clear re-enable reconciliation requirement')

# Orders must remain independent: Inventory event hooks become no-ops instead of rejecting orders.
for fn in [
    'inventory_enqueue_order_event_tx','inventory_process_pending_order_events',
    'inventory_register_after_response_order','inventory_after_response_drain',
    'inventory_process_order_events_for_order','inventory_retry_failed_order_events',
]:
    pos=inventory.find(f'function {fn}')
    need(pos>=0, f'{fn} missing')
    block=inventory[pos:pos+1800]
    need("sokna_module_runtime_ready('inventory')" in block, f'{fn} must honor runtime readiness')
need("return ['processed'=>0,'failed'=>0]" in inventory, 'disabled Inventory background processing must cleanly no-op')
need("'inventory_enabled'=>sokna_module_enabled('inventory')" in kick, 'cached client inventory kick must return safe no-op state')
need("sokna_module_runtime_ready('inventory')" in worker and "'processed'=>0" in worker, 'Inventory worker must stop cleanly while disabled/unreconciled')

# Server-side routes, not just navigation, enforce optionality.
visible_routes=['admin/inventory.php','admin/inventory_categories.php','admin/inventory_count.php','admin/inventory_count_start.php','admin/inventory_item.php','admin/inventory_item_form.php','admin/inventory_items.php','admin/inventory_opening.php','admin/inventory_review.php']
write_routes=['admin/inventory_adjustment.php','admin/inventory_receive.php','admin/inventory_waste.php']
for path in visible_routes:
    need("sokna_module_require('inventory')" in text(path), f'{path} missing Inventory server gate')
for path in write_routes:
    src=text(path)
    need("sokna_module_require('inventory')" in src and "sokna_module_require_runtime_ready('inventory')" in src, f'{path} must block writes until Inventory is ready')
need("sokna_module_require_runtime_ready('inventory')" in text('admin/inventory_report.php'), 'inventory-profit report must not expose stale stock')

# Menu recipe UI becomes optional without deleting preserved recipe data.
need("$inventoryEnabled = sokna_module_enabled('inventory')" in item_form, 'menu item form must know Inventory state')
need("if ($inventoryEnabled)" in item_form and 'inventory_save_recipe_locked' in item_form, 'recipe writes must be gated by Inventory')
need("<?php if($inventoryEnabled): ?>" in item_form, 'recipe UI must disappear when Inventory is disabled')

# Team access UI hides disabled capability without erasing prior grants.
need('inventory_capability_keys()' in users and '$preservedHiddenInventoryCapabilities' in users, 'hidden Inventory grants must be preserved when editing users')
functions=text('includes/functions.php')
need("!sokna_module_enabled('supply')" in functions[functions.find('function user_can_manage_purchases'):functions.find('function supply_need_allowed_departments')], 'purchase/report-need affordances must disappear when Supply is disabled')
need('دسترسی‌های قبلی انبار پنهان می‌مانند' in users, 'admin must understand hidden grants are preserved')
need("sokna_module_enabled('inventory')" in auth, 'home routing must not send staff into a disabled Inventory route')

# Shell/report/help must not display stale stock while Inventory is not ready.
need("sokna_module_runtime_ready('inventory') && user_has_inventory_access" in layout, 'Inventory badge must require trusted runtime state')
need("!sokna_module_runtime_ready('inventory')" in layout and "inventory_report" in layout, 'Inventory report navigation must hide during reconciliation')
need("'module'=>'inventory'" in help_topics, 'Inventory help topics must be module-aware')
need("'runtime_modules'=>['inventory']" in help_topics, 'Inventory profit help must require trusted runtime state')
need("runtime_modules" in help_page and 'sokna_module_runtime_ready' in help_page, 'Help filter must support runtime-ready dependencies')

# Module manager must show dependency consequence before destructive visibility changes.
need('$configuredDependents' in manager and 'قابلیت وابسته' in manager, 'manager disable confirmation must disclose dependent modules')
need("$configured && !$ready" in manager and 'نیازمند آماده‌سازی' in manager, 're-enabled Inventory needs a visible waiting state')
need("!$configured && $unreadyDependencies" in manager, 'Supply activation control must be disabled while Inventory is unavailable')

print('PASS optional Inventory/Supply module contract and state matrix')
