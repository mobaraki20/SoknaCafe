#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')
schema=read('database/schema.sql');printing=read('includes/printing.php');api=read('print-agent/v4/api.php');admin=read('admin/printing.php');templates=read('admin/print_templates.php');css=read('assets/css/panel-components.css');js=read('assets/js/printing-settings.js')
for token in ['print_agents','print_destinations','print_jobs','print_templates','fallback_agent_id','preparation_areas_json']: assert token in schema
# Phase7R: Cafe owns an internal Print Worker component; no runtime GitHub release resolver/download flow.
assert 'print_worker_component_metadata' in printing and "'ownership' => 'sokna-local-internal'" in printing
assert 'mobaraki20/Pagent' not in printing and '/releases/latest' not in printing and 'print_agent_release_metadata' not in printing
source=ROOT/'runtime/print-worker/source'
assert (source/'src/Sokna.PrintAgent.Core').is_dir() and (source/'src/Sokna.PrintAgent.Service').is_dir() and (source/'src/Sokna.PrintAgent.Worker').is_dir()
assert not (source/'src/Sokna.PrintAgent.Setup').exists() and not (source/'src/Sokna.PrintAgent.Control').exists()
assert 'sokna-print-document-v2' in printing and 'sokna-print-template-v1' in printing
assert "['kitchen'=>[], 'bar'=>[]]" in printing and 'بار گرم' not in printing and 'بار سرد' not in printing
assert 'print_prep_destination_groups' in printing and 'foreach (print_prep_destination_groups' in printing
assert 'show_prices' in printing and 'Prices on preparation tickets are deliberately forbidden' in printing
assert 'fallback_agent_id' in printing and 'print_any_preparation_destination_ready' in printing
helper=read('includes/print_agent_api.php'); assert 'Bearer' in helper and "hash('sha256',$token)" in helper and "if($action==='probe')" in api and "if($action==='accept')" in api
assert "if($action==='claim')" in api and "if($action==='start')" in api and "if($action==='report')" in api and 'destination_snapshot_json' in api
for label in ['نمای کلی','تنظیمات چاپ','عیب‌یابی','سلامت سرویس چاپ داخلی','تنظیمات پیشرفته چاپ']: assert label in admin
for action in ['create_destination','save_destination','test_print']: assert action in admin
# Identity lifecycle is Setup/Repair-owned; crafted legacy identity actions are rejected rather than exposed as an owner.
assert "in_array($action,['create_agent','rotate_agent','set_agent_active','delete_agent'],true)" in admin
assert 'هویت و کلید سرویس چاپ داخلی توسط Setup/Repair خود SOKNA مدیریت می‌شود' in admin
for action in ['import_template','activate_template','reset_template','delete_template']: assert action in templates
assert 'data-print-printer-select="primary"' in admin and 'printing-printers-data' in admin
assert 'print-settings-tabs' not in admin and '.print-settings-tabs' not in css
assert '.print5-status-strip' in css and '.print5-overview-grid' in css and '.print5-destination-editor' in css and 'let printers = []' in js
assert not list((ROOT/'print-agent').glob('Sokna-Print-Agent-*'))
assert (ROOT/'print-agent/v4/api.php').is_file() and not (ROOT/'print-agent/api.php').exists()
assert 'print_agent_download_url()' not in admin and 'برنامه جداگانه‌ای برای دانلود ندارد' in admin
print('Printing system passed: Cafe owns Print API/mappings/durable attempts and the audited Print Worker is an internal SOKNA Local component.')
