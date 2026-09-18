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
layout = text('includes/panel_layout.php')
settings = text('admin/settings.php')
manager = text('admin/modules.php')
guest = text('menu/index.php')
metric = text('api/metric.php')
help_page = text('help.php')
help_topics = text('includes/help_topics.php')

# Runtime state: Reporting is a real optional toggle, while required/core modules remain available.
code = (
    "function setting_bool(string $key,bool $default=false): bool { if ($key==='module.reporting.enabled') return false; if ($key==='module.marketing.enabled') return true; return $default; }"
    f"require {str(ROOT / 'includes/modules.php')!r};"
    "echo json_encode(['reporting'=>sokna_module('reporting'),'reporting_enabled'=>sokna_module_enabled('reporting'),"
    "'marketing_enabled'=>sokna_module_enabled('marketing'),'subscribers_enabled'=>sokna_module_enabled('subscribers'),'platform_enabled'=>sokna_module_enabled('platform')],"
    "JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);"
)
data = json.loads(subprocess.check_output(['php','-r',code], text=True))
reporting = data['reporting']
need(reporting.get('toggleable') is True, 'reporting must be end-to-end toggle-ready')
need(reporting.get('setting_key') == 'module.reporting.enabled', 'reporting setting key must be stable')
need(reporting.get('default_enabled') is True, 'reporting must preserve current availability by default')
need(data['reporting_enabled'] is False, 'reporting runtime setting is not enforced')
need(data['marketing_enabled'] is True, 'reporting toggle must not disable marketing')
need(data['subscribers_enabled'] is True, 'non-toggle-ready optional module must remain available')
need(data['platform_enabled'] is True, 'required platform must remain enabled')

block = modules.split("'reporting' => [",1)[1].split("'accommodation' => [",1)[0]
need("'manager' => [" in block and "'links' => [" in block, 'reporting manager metadata missing')
need("'menu_metrics_daily','menu_search_terms_daily'" in block, 'reporting must retain ownership of guest aggregate metric tables')
need('سفارش، مالی، انبار و سابقه ثبت اقدامات اصلی همچنان فعال می‌مانند' in block,
     'manager copy must distinguish reports from core operational/audit truth')

# All human reporting pages fail closed server-side. Hiding the navigation alone is insufficient.
for route in ['admin/analytics.php','admin/operations_report.php','admin/inventory_report.php','admin/activity_report.php']:
    need("sokna_module_require('reporting');" in text(route), f'{route}: reporting route guard missing')
need("!sokna_module_enabled('reporting')" in layout and "unset($navGroups['گزارش‌ها'])" in layout,
     'reporting navigation group must disappear while disabled')

# Guest telemetry is optional support behavior, not a dependency of ordering. Disable it at both ends:
# current clients make no request; stale clients get a successful no-op and cannot keep writing metrics.
need("sokna_module_enabled('reporting')&&setting_bool('analytics_enabled',true)" in guest,
     'guest must stop metric requests when Reporting is disabled')
need("!sokna_module_enabled('reporting')" in metric and "json_response(['success'=>true])" in metric,
     'metric ingestion must become a silent no-op while Reporting is disabled')
need(metric.index("!sokna_module_enabled('reporting')") < metric.index('request_json()'),
     'disabled reporting must no-op before parsing/writing stale guest metric requests')

# The PWA option is Platform-owned and stays editable; the analytics sub-setting disappears while the
# module is off and its old preference is preserved instead of being reset by an absent checkbox.
need("$reportingModuleEnabled=sokna_module_enabled('reporting');" in settings, 'settings must derive reporting state')
need("if (sokna_module_enabled('reporting'))" in settings and "$systemOptions['analytics_enabled']" in settings,
     'settings POST must preserve analytics preference while Reporting is disabled')
need("<?php if($reportingModuleEnabled): ?>" in settings and 'آمار منوی مهمان متوقف است.' in settings and 'modules.php' in settings,
     'settings UI must explain disabled analytics without hiding the unrelated PWA control')

# Help follows the module boundary; when disabled there are no dead links into report routes.
for topic in ['analytics','operations-report','activity-report']:
    pos=help_topics.find(f"'id'=>'{topic}'")
    need(pos >= 0 and "'module'=>'reporting'" in help_topics[pos:pos+300], f'{topic}: help module owner missing')
pos=help_topics.find("'id'=>'inventory-report'")
need(pos >= 0 and "'modules'=>['reporting','inventory']" in help_topics[pos:pos+340] and "'runtime_modules'=>['inventory']" in help_topics[pos:pos+340], 'inventory-report help must require Reporting plus trusted Inventory')
need('sokna_module_enabled($module)' in help_page and 'sokna_module_runtime_ready($module)' in help_page, 'help module visibility/readiness guard missing')

# Manager remains registry-driven. Reporting becoming the second toggle must require zero page-local branching.
need("foreach ($manageableModules as $key => $module)" in manager, 'module manager must remain generic')
need("$key !== 'reporting'" not in manager and "$key === 'reporting'" not in manager, 'manager must not hard-code Reporting')
need("DELETE FROM menu_metrics_daily" not in manager and "DELETE FROM audit_log" not in manager,
     'module disable must not become a destructive cleanup operation')

print('PASS reporting module management contract')
