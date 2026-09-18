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
functions = text('includes/functions.php')
metric = text('api/metric.php')
help_page = text('help.php')
help_topics = text('includes/help_topics.php')

# Registry + runtime semantics: Marketing is the only real toggle in this pilot. Non-hardened optional
# modules must not accidentally become disabled because their `required` flag is false.
code = (
    "function setting_bool(string $key,bool $default=false): bool { return $key==='module.marketing.enabled' ? false : false; }"
    f"require {str(ROOT / 'includes/modules.php')!r};"
    "echo json_encode(['marketing'=>sokna_module('marketing'),'marketing_enabled'=>sokna_module_enabled('marketing'),"
    "'subscribers_enabled'=>sokna_module_enabled('subscribers'),'platform_enabled'=>sokna_module_enabled('platform')],"
    "JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);"
)
data = json.loads(subprocess.check_output(['php','-r',code], text=True))
marketing = data['marketing']
need(marketing.get('toggleable') is True, 'marketing must be the end-to-end toggle pilot')
need(marketing.get('setting_key') == 'module.marketing.enabled', 'marketing setting key must be stable')
need(marketing.get('default_enabled') is True, 'upgrade/default behavior must preserve current marketing availability')
need(data['marketing_enabled'] is False, 'marketing runtime setting is not enforced')
need(data['subscribers_enabled'] is True, 'non-hardened optional module must remain available')
need(data['platform_enabled'] is True, 'required core module must remain enabled')
need("function sokna_module_set_enabled_locked" in modules, 'transactional module toggle contract missing')
need("INSERT IGNORE INTO settings" in modules and "FOR UPDATE" in modules, 'module toggle must serialize on a real settings row')
need("expectedEnabled" in modules and "وضعیت این بخش در صفحه دیگری تغییر کرده است" in modules, 'stale-tab snapshot guard missing')
need("audit_log_write_strict" in modules and "module.enabled_changed" in modules, 'module toggle must be strictly audited')

# Product language is cross-cutting Platform functionality, not Marketing. Disabling Marketing must
# never make order/waiter/push text administration disappear.
need("'admin/messages.php'" in modules.split("'platform' => [",1)[1].split("'menu' => [",1)[0], 'messages route must belong to Platform')
marketing_block = modules.split("'marketing' => [",1)[1].split("'reporting' => [",1)[0]
need("admin/messages.php" not in marketing_block, 'messages route must not be gated by Marketing')

# All Marketing write/read admin entrypoints fail closed server-side. Navigation hiding alone is not security.
for route in ['admin/marketing.php','admin/campaign_form.php','admin/events.php','admin/event_form.php']:
    need("sokna_module_require('marketing');" in text(route), f'{route}: server-side module guard missing')
need("!sokna_module_enabled('marketing')" in layout and "['منو و مهمان']['events']" in layout and "['منو و مهمان']['marketing']" in layout,
     'admin navigation must remove both marketing entrypoints while disabled')
need("'modules' => [$base . '/admin/modules.php', 'امکانات سامانه']" in layout, 'module manager must be discoverable in system navigation')

# Guest output is fail-closed before querying domain tables, and old cached clients cannot keep writing
# marketing-specific analytics after the module has been disabled.
need("$marketingEnabled=sokna_module_enabled('marketing');" in guest, 'guest page must derive one module state')
need("if($marketingEnabled&&setting_bool('events_enabled',true))" in guest, 'event query must be gated by module state')
need("$campaign=$marketingEnabled?active_campaign():null" in guest, 'campaign guest output must be gated by module state')
need("!sokna_module_enabled('marketing') || !setting_bool('campaigns_enabled', true)" in functions, 'campaign read model must fail closed')
need("return sokna_module_enabled('marketing') && setting_bool('events_enabled', true) ? '#events' : '';" in functions,
     'campaign->events CTA must not point to a disabled event surface')
need("in_array($key,['event_open','campaign_click'],true) && !sokna_module_enabled('marketing')" in metric,
     'marketing metrics must no-op after disable')
need('sokna_module_enabled($module)' in help_page and 'runtime_modules' in help_page, 'help must hide topics for disabled/unready modules')
need("'module'=>'marketing'" in help_topics, 'marketing help topic must declare its module owner')

# The manager is registry-driven: a switch appears only after a module is explicitly toggle-ready.
need("sokna_module_toggleable($key)" in manager, 'manager must trust the hardened registry, not a page-local allow-list')
need("$key !== 'marketing'" not in manager, 'manager must not hard-code the first pilot forever')
need("$manageableModules" in manager and "foreach ($manageableModules as $key => $module)" in manager, 'manager UI must render toggle-ready modules generically')
need("expected_enabled" in manager and "sokna_module_set_enabled_locked" in manager, 'manager must use stale-safe module contract')
need("DELETE FROM campaigns" not in manager and "DELETE FROM events" not in manager, 'module disable must never delete marketing records')
need("این صفحه دسترسی کاربران را تغییر نمی‌دهد" in manager and 'users.php' in manager, 'module state must be clearly separated from user permissions')
need("بخش‌های همیشه فعال" in manager and "قابلیت‌های قابل مدیریت" in manager, 'module manager IA must distinguish optional controls from required platform areas')
need("Audit" not in manager and "مسیرهای سرور" not in manager, 'manager copy must stay human-facing rather than implementation-facing')
need("چیزی حذف نمی‌شود" in manager, 'disable semantics must be explicit without implying destructive cleanup')
need("'manager' => [" in marketing_block and "'links' => [" in marketing_block, 'toggle-ready module must own its management UI metadata')

# Guest sub-feature preferences remain untouched while the whole module is off. Otherwise re-enable would
# silently change the owner's previous event/campaign visibility choices.
need("if (sokna_module_enabled('marketing'))" in settings, 'guest settings must preserve marketing sub-preferences while disabled')
need("$marketingModuleEnabled=sokna_module_enabled('marketing');" in settings, 'settings UI must know module state')
need("modules.php" in settings and "تنظیمات قبلی نمایش حفظ شده‌اند" in settings, 'disabled guest settings need a clear recovery path')

print('PASS marketing module management contract')
