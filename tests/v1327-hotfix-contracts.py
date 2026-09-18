#!/usr/bin/env python3
from pathlib import Path
import re, subprocess
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')

reporting=read('includes/reporting.php')
acc=read('admin/accommodation.php');acc_core=read('includes/accommodation.php')
inv=read('includes/inventory.php');inv_home=read('admin/inventory.php');inv_count=read('admin/inventory_count.php');inv_start=read('admin/inventory_count_start.php');inv_open=read('admin/inventory_opening.php')
tables=read('admin/tables.php');item=read('admin/item_form.php');inv_core=read('includes/inventory.php')
invoice=read('includes/invoices_page.php');qr=read('admin/qr.php');users=read('admin/users.php');dashboard=read('admin/index.php');messages=read('admin/messages.php');push=read('admin/push_devices.php');events=read('admin/events.php')
layout=read('includes/panel_layout.php');panel_core=read('assets/js/panel-core.js');center=read('includes/sokna_center.php')
pc=read('assets/css/panel-components.css');icss=read('assets/css/inventory.css')

# Shared report renderer: numeric-string PHP array keys must be normalized before strict escaping/comparison.
assert "foreach(report_range_options() as $key=>$label): $key=(string)$key;" in reporting
php_test=ROOT/'tests/v1327-report-render.php'
proc=subprocess.run(['php',str(php_test)],cwd=ROOT,text=True,capture_output=True)
assert proc.returncode==0,(proc.stdout,proc.stderr)
assert 'REPORT_RENDER_OK' in proc.stdout,proc.stdout

# Personnel visibility: staff is fail-closed; only explicit Center allow reveals the launcher. Admin keeps the management entry.
assert "if ($isAdmin)" in layout
assert "elseif (($personnelState['state'] ?? '') === 'unknown')" in layout
unknown_chunk=layout.split("elseif (($personnelState['state'] ?? '') === 'unknown')",1)[1].split('}',1)[0]
assert "'hidden'=>true" in unknown_chunk
assert "'refresh'=>true" in unknown_chunk
assert "data-center-personnel-link" in layout and "data-center-personnel-refresh" in layout
assert 'if (data?.supported !== true)' in panel_core and 'link.hidden = true' in panel_core
assert 'unknown/unsupported/failed refresh never reveals the launcher' in panel_core

# Current domain supports exactly one open count; legacy multi-draft UI/branches are gone.
assert 'function inventory_open_count_session' in inv and 'function inventory_open_count_sessions' not in inv
assert "WHERE s.status='draft'" in inv and "ORDER BY s.id" in inv and 'LIMIT 1' in inv
for src in (inv_home, inv_start, inv_open): assert 'inventory_open_count_session' in src
assert 'شمارش قدیمی هنوز باز هستند' not in inv and 'شمارش باز دیگر باقی مانده' not in inv_count
assert "redirect('inventory.php?tab=counts')" in inv_count
assert 'ذخیره و خروج' in inv_count and 'شمارش کور فعال است' in inv_count
assert 'inventory-count-group' in inv_count and 'inventory-count-actions' in inv_count
assert 'inventory-count-meter' in icss

# Table management: official number is explicit, bulk creation atomic, and state change is desired-state.
assert "preg_match('/^[0-9]{1,4}$/',$numberRaw)" in tables
save_chunk=tables.split("if($action==='save')",1)[1].split("elseif($action==='bulk_create')",1)[0]
assert 'extract_table_number($name)' not in save_chunk
bulk=tables.split("elseif($action==='bulk_create')",1)[1].split("elseif($action==='set_active')",1)[0]
assert '$pdo->beginTransaction()' in bulk and '$pdo->commit()' in bulk
assert "value=\"set_active\"" in tables and 'desired_active' in tables
assert '?missing=1' not in tables and 'table-number-direct-action' not in tables
assert 'panel-balanced-actions tables-primary-actions' in tables

# Recipe cost preview is server-derived every render from active recipe/current projection; JS remains a live enhancer.
assert 'function inventory_recipe_cost_preview' in inv_core
assert 'inventory_recipe_cost_preview(db(),$recipeRows,$recipeSalePrice)' in item
assert 'recipe-cost-preview' in item and 'recipe-cost-ratio' in item
assert 'هزینه کامل قابل محاسبه نیست' in item

# Accommodation normal state is silent; only exceptions occupy attention space, and history uses effective time ordering.
assert 'همه انتقال‌ها تعیین تکلیف شده‌اند' not in acc
assert 'if($attentionTotal>0)' in acc and 'accommodation-issues-card' in acc
assert 'financial-workspace accommodation-page-stack panel-page-flow' in acc
assert 'accommodation_transfer_effective_time_sql' in acc_core
assert "resolved_at" in acc_core.split('function accommodation_transfer_effective_time',1)[1].split('}',1)[0]
assert "ORDER BY '.$eventTimeSql.' DESC" in acc

# Invoice archive/detail share a financial-document language rather than card-per-record.
assert 'function invoice_presented_items' in invoice
assert 'invoice-day-group' in invoice and 'invoice-transaction-row' in invoice
assert 'invoice-receipt-summary' in invoice and 'invoice-receipt-lines' in invoice
assert 'invoice-detail-meta' not in invoice
assert "if((int)$detail['discount']!==0)" in invoice
assert 'چاپ مجدد' in invoice and 'btn btn-light' in invoice

# Shared UI migrations for QR, users, healthy states, guidance and surfaces.
assert 'panel-surface-stack qr-single-stack' in qr and 'panel-surface-stack qr-center-stack' in qr
assert 'HTTPS آماده' not in qr and 'qr-domain-warning' in qr
assert 'panel-balanced-actions qr-page-actions' in qr and 'کپی لینک' in qr
assert 'team-page-grid' in users and 'is-editor-mode' in users and '?new=1' in users
assert "$hadError && $action==='save'" in users
assert 'صف عملیات خالی است' not in dashboard
assert '<h2>وضعیت عملیات</h2>' in dashboard
assert 'panel-helper-note event-lifecycle-note' in events
assert 'panel-helper-note messages-guidance' in messages and 'messages-reset-control' in messages
assert 'push-setup-guide' in push and 'Web Push فعال است.' not in push
assert 'panel-surface-stack inventory-item-sections' in read('admin/inventory_item.php')
assert 'subscriber-profile-stack panel-page-flow' in read('includes/subscribers_page.php')

# Shared surfaces/actions are owned in panel-components, not per-page inline margins.
assert '.panel-balanced-actions{' in pc and '.panel-helper-note{' in pc and '.panel-surface-stack{' in pc
assert 'style="margin-bottom:18px"' not in messages

print('1.32.14 hotfix contracts passed: reports, entitlement compatibility, single-count ownership, table integrity, persistent food cost, financial history, and shared panel UI migrations.')
