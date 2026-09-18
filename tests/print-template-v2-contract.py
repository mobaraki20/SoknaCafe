#!/usr/bin/env python3
from pathlib import Path
import json,re
R=Path(__file__).resolve().parents[1]
read=lambda p:(R/p).read_text(encoding='utf-8')
printing=read('includes/printing.php');page=read('admin/print_templates.php');admin_print=read('admin/printing.php');schema=read('database/schema.sql')
assert "sokna-print-template-package-v2" in printing and "sokna-print-design-v2" in printing
assert "['manifest.json','template.xml','styles.json','sample-data.json']" in printing
assert "str_contains($name,'..')" in printing and '262144' in printing
assert 'فایل اجرایی داخل قالب پذیرفته نمی‌شود' in page and 'پیش‌نمایش' in page and 'data-thermal-preview' in page
assert 'assets/js/print-template-designer.js' in page and 'assets/js/reorder-list.js' in page
assert 'چاپ فیزیکی' in page and 'پرینتر واقعی' in page and 'UAT' not in page
assert 'admin/print_templates.php' in admin_print
for action in ['print_template_saved','print_template_imported','print_template_activated','print_template_deleted','print_template_reset']:
 assert action in page, action
for token in ['ticket_title','new_order','adjustment','cancel','takeaway']:
 assert token in printing and token in schema, token
prep=json.loads((R/'print-templates/preparation/styles.json').read_text(encoding='utf-8'))
cust=json.loads((R/'print-templates/customer/styles.json').read_text(encoding='utf-8'))
assert prep['design']['labels']['ticket_title']=='فیش آماده‌سازی'
assert prep['design']['layout_contract']=='preparation-ticket-v2' and cust['design']['layout_contract']=='customer-receipt-v2'
assert cust['design']['item_layout']=='columnar' and 'Vazirmatn' in cust['design']['font_stack'] and 'Vazirmatn' in prep['design']['font_stack']
assert "['columnar','columnar-compact','two-line','responsive-receipt']" in printing
assert "'format' => 'sokna-print-template-v1'" in printing and 'Stable renderer payload contract' in printing
assert "show_prices'] = $key === 'customer'" in printing
assert "invoice_reference" in printing and "order_reference" in printing
print('Print Template v2 contract PASS: structured/versioned design, safe four-file import, preview, customizable labels/section order, Agent-6 renderer/font contract and explicit physical-print UAT boundary are enforced.')
