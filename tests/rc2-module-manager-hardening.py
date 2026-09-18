#!/usr/bin/env python3
from pathlib import Path
root=Path(__file__).resolve().parents[1]
registry=(root/'includes/modules.php').read_text(encoding='utf-8')
manager=(root/'admin/modules.php').read_text(encoding='utf-8')
supply=(root/'modules/Supply/domain.php').read_text(encoding='utf-8')
purchases=(root/'admin/purchases.php').read_text(encoding='utf-8')

assert 'Worker' not in registry and 'Recipe' not in registry, 'manager-facing module copy must not expose implementation jargon'
assert "'disable_preflight'=>'supply_module_disable_preflight'" in registry
assert 'function sokna_module_disable_preflight' in registry and 'sokna_module_dependents($key)' in registry
assert 'function supply_module_disable_preflight' in supply
assert "status='open' AND preparing_quantity_base>0" in supply
assert "مشاهده اقلام در حال خرید" in supply and 'purchases.php#preparingPurchases' in supply
assert 'module-disable-blocker' in manager and 'sokna_module_disable_preflight((string)$key)' in manager
assert '$configured && $disableBlockers' in manager and 'disabled aria-disabled' in manager
assert 'id="preparingPurchases"' in purchases
assert 'supply_module_before_disable_locked' in supply, 'UI preflight must not replace locked backend guard'
print('RC2 module manager hardening PASS: Persian operational copy, proactive preparing blocker, direct remediation, and locked backend guard coexist.')
