#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')
schema=read('database/schema.sql');printing=read('includes/printing.php');api=read('print-agent/v4/api.php');admin=read('admin/printing.php');templates=read('admin/print_templates.php');css=read('assets/css/panel-components.css');js=read('assets/js/printing-settings.js')
for token in ['print_agents','print_destinations','print_jobs','print_templates','fallback_agent_id','preparation_areas_json']: assert token in schema
assert 'mobaraki20/Pagent' in printing and 'Sokna-Print-Agent-' in printing and '/releases/latest' in printing and 'print_agent_release_metadata' in printing and "$version = '6.2.2';" in printing
assert 'sokna-print-document-v2' in printing and 'sokna-print-template-v1' in printing
assert "['kitchen'=>[], 'bar'=>[]]" in printing and 'بار گرم' not in printing and 'بار سرد' not in printing
assert 'print_prep_destination_groups' in printing and 'foreach (print_prep_destination_groups' in printing
assert 'show_prices' in printing and 'Prices on preparation tickets are deliberately forbidden' in printing
assert 'fallback_agent_id' in printing and 'print_any_preparation_destination_ready' in printing
helper=read('includes/print_agent_api.php'); assert 'Bearer' in helper and "hash('sha256',$token)" in helper and "if($action==='probe')" in api and "if($action==='accept')" in api
assert "if($action==='claim')" in api and "if($action==='start')" in api and "if($action==='report')" in api and 'destination_snapshot_json' in api
for label in ['نمای کلی','تنظیمات چاپ','عیب‌یابی','رایانه‌های چاپ','مسیرهای چاپ','تنظیمات پیشرفته چاپ']: assert label in admin
for action in ['delete_agent','create_destination','save_destination','test_print']: assert action in admin
for action in ['import_template','activate_template','reset_template','delete_template']: assert action in templates
assert 'data-print-printer-select="primary"' in admin and 'printing-printers-data' in admin
assert 'print-settings-tabs' not in admin and '.print-settings-tabs' not in css
assert '.print5-status-strip' in css and '.print5-overview-grid' in css and '.print5-destination-editor' in css and 'printersByAgent' in js
assert not (ROOT/'print-agent-v6').exists()
assert not list((ROOT/'print-agent').glob('Sokna-Print-Agent-*'))
assert (ROOT/'print-agent/v4/api.php').is_file() and not (ROOT/'print-agent/api.php').exists()
assert 'print_agent_download_url()' in admin and 'latest/download' not in printing
print('Printing system passed: Cafe owns Print APIs/mappings/durable attempts; Agent binary/source distribution resolves stable Pagent GitHub Releases with LKG fallback.')
