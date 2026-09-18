#!/usr/bin/env python3
from __future__ import annotations
import json
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

def need(condition: bool, message: str) -> None:
    if not condition:
        raise AssertionError(message)

def text(path: str) -> str:
    return (ROOT / path).read_text('utf-8', errors='ignore')

modules = text('includes/modules.php')
center = text('includes/sokna_center.php')
layout = text('includes/panel_layout.php')
settings = text('admin/settings.php')
manager = text('admin/modules.php')
personnel_page = text('admin/personnel.php')
center_settings = text('admin/center_settings.php')
user_directory = text('api/sokna_center_users.php')
entitlement_api = text('api/sokna_center_personnel_access.php')
payroll_api = text('api/sokna_center_payroll_reminder_count.php')
center_return = text('center_return.php')
help_page = text('help.php')
help_topics = text('includes/help_topics.php')

# Runtime registry state: Personnel is the third hardened toggle and remains independent of the
# two existing managed modules and all required operational modules.
code = (
    "function setting_bool(string $key,bool $default=false): bool { "
    "if ($key==='module.personnel.enabled') return false; "
    "if ($key==='module.reporting.enabled') return true; "
    "if ($key==='module.marketing.enabled') return true; return $default; }"
    f"require {str(ROOT / 'includes/modules.php')!r};"
    "echo json_encode(['personnel'=>sokna_module('personnel'),'personnel_enabled'=>sokna_module_enabled('personnel'),"
    "'reporting_enabled'=>sokna_module_enabled('reporting'),'marketing_enabled'=>sokna_module_enabled('marketing'),"
    "'orders_enabled'=>sokna_module_enabled('orders')],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);"
)
data = json.loads(subprocess.check_output(['php','-r',code], text=True))
personnel = data['personnel']
need(personnel.get('toggleable') is True, 'personnel must be end-to-end toggle-ready')
need(personnel.get('setting_key') == 'module.personnel.enabled', 'personnel setting key must be stable')
need(personnel.get('default_enabled') is True, 'personnel must preserve current availability by default')
need(data['personnel_enabled'] is False, 'personnel runtime setting is not enforced')
need(data['reporting_enabled'] is True and data['marketing_enabled'] is True, 'personnel toggle must not affect other optional modules')
need(data['orders_enabled'] is True, 'personnel toggle must not affect core ordering')

block = modules.split("'personnel' => [",1)[1].split("'printing' => [",1)[0]
need("'manager' => [" in block and "'links' => [" in block, 'personnel manager metadata missing')
need('اطلاعات اتصال ذخیره‌شده پاک نمی‌شود' in block, 'disable semantics must preserve pairing configuration')
need('عملیات سفارش، صندوق، انبار و چاپ مستقل باقی می‌مانند' in block, 'manager copy must state operational independence')
need('center_return.php remains a safe recovery redirect while disabled' in block, 'stale remote return needs a safe recovery exception')

# Module state is the outer feature gate; a stored/overridden pairing cannot bypass a disabled module.
need("sokna_module_enabled('personnel')" in center and 'sokna_center_personnel_module_enabled' in center, 'Sokna Center connection must be gated by Personnel module state')
pos_gate = center.index('if (!sokna_center_personnel_module_enabled()) return false;')
pos_override = center.index("$GLOBALS['SOKNA_CENTER_CONFIG_OVERRIDE']", pos_gate)
need(pos_gate < pos_override, 'module disable must win over connection overrides/configuration')
need(center.count("if (!sokna_center_personnel_module_enabled())") >= 3, 'pair/probe entrypoints must also reject a disabled Personnel module')

# Human entrypoints fail closed server-side; navigation/Settings must not leave dead Personnel links.
for route, source in [('admin/personnel.php', personnel_page), ('admin/center_settings.php', center_settings)]:
    need("sokna_module_require('personnel');" in source, f'{route}: server-side module guard missing')
need("sokna_center_connection_enabled()" in layout, 'personnel navigation must remain connection-aware')
need("sokna_module_enabled('personnel')" in settings and 'center_settings.php' in settings,
     'technical connection link must disappear from Settings while Personnel is disabled')


# Probe/entitlement are visibility/diagnostic hints only; launcher availability must not depend on them.
need('sokna_center_test_connection(' not in personnel_page, 'Personnel launcher must not probe Center before handoff')
need('sokna_center_handoff_target()' in personnel_page and 'sokna_center_handoff_token(' in personnel_page,
     'Personnel launcher must construct the signed handoff locally')
need('$personnelAccess' not in personnel_page, 'local entitlement cache must not become handoff authorization')
need('تلاش دوباره' in personnel_page and 'بررسی تنظیم اتصال' in personnel_page,
     'Personnel failure UI must distinguish retry from connection configuration')

# S2S/user-facing APIs inherit the same outer module gate through the single connection predicate.
need('sokna_center_verify_user_directory_request' in user_directory and 'sokna_center_connection_enabled()' in center,
     'Center user directory must use the gated connection contract')
need("!sokna_center_connection_enabled()" in entitlement_api and "allowed'=>false" in entitlement_api,
     'personnel entitlement refresh must fail closed without generating permission')
need("!sokna_center_connection_enabled()" in payroll_api and "count'=>null" in payroll_api,
     'payroll badge must fail silent while module is disabled')

# A user who disabled the module while another tab is at Center must still be able to return safely.
need("sokna_module_require('personnel')" not in center_return, 'Center return must remain a safe authenticated redirect')
need('require_login();' in center_return and 'redirect(user_home_path());' in center_return,
     'Center return must not expose data while remaining available')

# Help follows runtime availability and the generic module manager remains free of Personnel-specific branching.
center_topic_pos = help_topics.find("'id'=>'center-personnel'")
need(center_topic_pos >= 0 and "'module'=>'personnel'" in help_topics[center_topic_pos:center_topic_pos+900],
     'Personnel help topic must declare module ownership')
need('sokna_module_enabled($module)' in help_page and 'runtime_modules' in help_page, 'help module visibility guard missing')
need("foreach ($manageableModules as $key => $module)" in manager, 'module manager must remain registry-driven')
need("$key === 'personnel'" not in manager and "$key !== 'personnel'" not in manager, 'manager must not hard-code Personnel')
need('DELETE FROM settings' not in manager and 'sokna_center_handoff_secret_encrypted' not in manager,
     'module disable must not delete Center pairing configuration')

print('PASS personnel module management contract')
