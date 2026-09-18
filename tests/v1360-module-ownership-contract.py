#!/usr/bin/env python3
from __future__ import annotations

import json
import re
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def need(condition: bool, message: str) -> None:
    if not condition:
        raise AssertionError(message)


def php_registry() -> dict:
    code = (
        "require " + repr(str(ROOT / 'includes/modules.php')) + ";"
        "echo json_encode(['registry'=>sokna_module_registry(),'errors'=>sokna_module_dependency_errors()],"
        "JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);"
    )
    raw = subprocess.check_output(['php', '-r', code], text=True)
    return json.loads(raw)


def schema_tables() -> set[str]:
    pattern = re.compile(r'CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+`?([A-Za-z0-9_]+)`?', re.I)
    text = (ROOT / 'database/schema.sql').read_text('utf-8', errors='ignore')
    return set(pattern.findall(text))



data = php_registry()
registry: dict[str, dict] = data['registry']
need(data['errors'] == [], f"module dependency/ownership metadata errors: {data['errors']}")
need(len(registry) == 13, f"unexpected module count: {len(registry)}")
need([key for key,module in registry.items() if module.get('toggleable')] == ['inventory','supply','marketing','reporting','personnel'], 'only end-to-end hardened optional modules may expose runtime toggles')

required_fields = {
    'label', 'type', 'required', 'depends_on', 'reads_from', 'owner', 'owns_tables',
    'entrypoints', 'capabilities', 'background_jobs', 'public_contracts',
}
for key, module in registry.items():
    missing = required_fields - set(module)
    need(not missing, f"{key}: missing registry fields {sorted(missing)}")
    need(isinstance(module['owns_tables'], list), f"{key}: owns_tables must be list")
    if module.get('toggleable'):
        need(module.get('required') is False, f"{key}: required module cannot be toggleable")
        need(bool(str(module.get('setting_key','')).strip()), f"{key}: toggleable module needs setting_key")
        need(isinstance(module.get('default_enabled'), bool), f"{key}: toggleable module needs boolean default_enabled")
    for dep in [*module['depends_on'], *module['reads_from']]:
        need(dep in registry, f"{key}: unknown module edge {dep}")
    for entry in module['entrypoints']:
        entry = str(entry)
        # Descriptive non-path entries are not used in entrypoints; every entry must resolve.
        target = ROOT / entry.rstrip('/')
        need(target.exists(), f"{key}: entrypoint missing: {entry}")

owners: dict[str, list[str]] = {}
for key, module in registry.items():
    for table in module['owns_tables']:
        owners.setdefault(table, []).append(key)

duplicates = {table: mods for table, mods in owners.items() if len(mods) != 1}
need(not duplicates, f"duplicate table owners: {duplicates}")

discovered = schema_tables()
owned = set(owners)
need(discovered == owned, f"table ownership coverage mismatch missing={sorted(discovered-owned)} extra={sorted(owned-discovered)}")
need(len(discovered) == 61, f"expected 61 current schema tables, found {len(discovered)}")

# Every user/agent-facing PHP route has one module owner. This keeps HTTP ownership explicit
# without forcing entrypoint files to move folders.
route_owners: dict[str, list[str]] = {}
for key, module in registry.items():
    for entry in module['entrypoints']:
        entry = str(entry)
        if entry.endswith('.php'):
            route_owners.setdefault(entry, []).append(key)

route_roots = ['menu','admin','api','operator','staff','waiter']
discovered_routes: set[str] = set()
for root_name in route_roots:
    for path in (ROOT / root_name).rglob('*.php'):
        discovered_routes.add(str(path.relative_to(ROOT)))
for entry in ['index.php','about.php','install.php','login.php','logout.php','help.php','favicon.php','manifest.php','robots.php','sitemap.php','notification_preferences.php','center_return.php']:
    if (ROOT / entry).exists():
        discovered_routes.add(entry)
if (ROOT / 'print-agent/v4/api.php').exists():
    discovered_routes.add('print-agent/v4/api.php')

route_duplicates = {route: mods for route, mods in route_owners.items() if len(mods) != 1}
need(not route_duplicates, f"duplicate route owners: {route_duplicates}")
need(discovered_routes == set(route_owners), f"route ownership coverage mismatch missing={sorted(discovered_routes-set(route_owners))} extra={sorted(set(route_owners)-discovered_routes)}")

# Pre-launch cleanup: release-history schema artifacts live in Git, not in the active tree.
need(not (ROOT / 'database/migrations').exists(), 'historical database/migrations archive must not remain in active source')
need(not (ROOT / 'migrations').exists(), 'historical updater migration archive must not remain in active source')
for module in registry.values():
    need('retired_tables' not in module, 'retired table metadata must not remain after pre-launch cleanup')

# Supply is the pilot boundary: it may read Inventory, but Inventory writes must go through Inventory contracts.
supply_domain = (ROOT / 'modules/Supply/domain.php').read_text('utf-8')
for table in registry['inventory']['owns_tables']:
    mutation = re.compile(rf'\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+`?{re.escape(table)}`?\b', re.I)
    need(not mutation.search(supply_domain), f"Supply directly mutates Inventory-owned table: {table}")
need('inventory_create_unreviewed_item_locked(' in supply_domain, 'Supply unknown-item creation must use Inventory owner contract')
need('inventory_record_movement_locked(' in supply_domain, 'Supply receipt must use Inventory movement contract')

panel = (ROOT / 'includes/panel_layout.php').read_text('utf-8')
need('supply_purchase_attention_count(db())' in panel, 'panel shell must use Supply attention read contract')
need('FROM inventory_supply_needs' not in panel, 'panel shell must not query Supply-owned table directly')

inventory = (ROOT / 'includes/inventory.php').read_text('utf-8')
need('function inventory_create_unreviewed_item_locked' in inventory, 'Inventory owner contract for cross-domain item creation missing')

print('PASS module ownership contract')
print(f'Modules: {len(registry)} | Current owned tables: {len(owned)} | Historical migration archives: 0 | Owned PHP routes: {len(route_owners)}')
