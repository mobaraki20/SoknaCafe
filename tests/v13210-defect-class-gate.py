#!/usr/bin/env python3
from pathlib import Path
import json, re, sys
R=Path(__file__).resolve().parents[1]

def txt(rel): return (R/rel).read_text()
def need(ok,msg):
    if not ok:
        print('Current defect-class FAILED:',msg); sys.exit(1)

version=(R/'VERSION.txt').read_text().strip(); need(bool(re.fullmatch(r'\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?',version)),'release identity')
sw=txt('service-worker.js'); need(f"const RELEASE='{version}'" in sw and f"cafe-staff-v{version}" in sw,'service worker identity')

# Escaped subscriber-detail route defect: settlement metadata is secondary and cannot take down the profile.
sub=txt('includes/subscribers_page.php')
need('function subscriber_ledger_enrich_settlements' in sub,'subscriber settlement enrichment owner missing')
base=re.search(r'\$sql="SELECT l\.\*,u\.display_name actor_name.*?ORDER BY l\.created_at DESC,l\.id DESC LIMIT \$ledgerPerPage OFFSET \$ledgerOffset";',sub,re.S)
need(base is not None and 'settlement_records' not in base.group(0),'subscriber primary ledger query must not depend on settlement joins')
need('subscriber settlement enrichment:' in sub and 'catch (Throwable $e)' in sub,'subscriber settlement enrichment must fail soft and log')
need('SELECT MIN(sr0.id)' not in sub,'subscriber detail must not restore correlated settlement scalar join')
need('ORDER BY l.created_at DESC,l.id DESC' in sub,'subscriber ledger temporal ordering')

# Accommodation: exception-first, no silent count failure, simple filter.
acc=txt('admin/accommodation.php')
need('catch(Throwable){}' not in acc.replace(' ',''),'accommodation unresolved count must not fail silently')
need('data-auto-submit' in acc and '>اعمال<' not in acc,'accommodation status auto-apply')
need("if($display['needs_action']||(string)$row['status']!=='posted')" in acc,'healthy posted accommodation status must stay quiet while recovery mismatches stay visible')

# Quick Edit: real flags, shared swipe owner, compact schedule, no bespoke overlay close path.
items=txt('admin/items.php'); itemjs=txt('assets/js/items-management.js'); core=txt('assets/js/panel-core.js')
need('quick-item-flags' in items and items.count('quickItemAvailable')>=1 and items.count('quickItemActive')>=1 and items.count('quickItemFeatured')>=1,'quick edit flags real markup')
need('data-item-editor-swipe-handle' in items and 'CafeUI.bindSwipeDismiss' in itemjs and 'bindSwipeDismiss' in core,'quick edit shared swipe-down contract')
need('closeDrawerSafely' in itemjs,'quick edit dirty close must protect swipe dismissal')

# Time semantics: every admin time input declares semantic step; time modal has no dead sheet grip.
timepicker=txt('assets/js/panel-time-picker.js')
need('panel-time-grip' not in timepicker and 'data-panel-time-drag' not in timepicker,'time modal dead drag hooks')
need("falling back to 15 minutes" in timepicker,'safe semantic fallback')
for f in list((R/'admin').glob('*.php'))+list((R/'waiter').glob('*.php'))+list((R/'operator').glob('*.php'))+list((R/'staff').glob('*.php')):
    for n,line in enumerate(f.read_text().splitlines(),1):
        if 'type="time"' in line:
            need('data-minute-step=' in line, f'time input without semantic step: {f.relative_to(R)}:{n}')

# Item schedule: range date-only, daily time owns hours, overnight previous-day rule.
itemform=txt('admin/item_form.php'); funcs=txt('includes/functions.php') + txt('includes/function_domains/jalali.php')
need('schedule_start_time' not in itemform and 'schedule_end_time' not in itemform,'item date range must not require duplicate time')
need('parse_optional_jalali_day_boundary' in funcs and "'23:59:59'" in funcs and "'00:00:00'" in funcs,'date-only day boundary parser')
need('DAYOFWEEK(NOW())=1,7,DAYOFWEEK(NOW())-1' in funcs,'overnight schedule previous weekday handling')
need(itemform.count('data-minute-step="15"')>=2,'item daily schedule 15-minute step')

# Composite date/tile geometry owners.
pcss=txt('assets/css/panel-components.css'); appcss=txt('assets/css/app.css')
need('.jalali-date-control{display:grid' in pcss and '.jalali-date-button{position:static' in pcss and 'overflow:hidden' in pcss[pcss.find('.jalali-date-control{'):pcss.find('.jalali-date-control{')+500],'jalali composite control owner')
need('.panel-content :is(.tag-check-grid,.day-check-grid)>label{display:flex;align-items:center;justify-content:space-between' in pcss and 'min-height:48px' in pcss[pcss.find('.panel-content :is(.tag-check-grid,.day-check-grid)>label{'):pcss.find('.panel-content :is(.tag-check-grid,.day-check-grid)>label{')+240],'selection tile alignment owner')
need(pcss.count('.invoice-receipt-line{')<=2 and pcss.count('.invoice-receipt-meta dl{')<=2,'receipt CSS owner must not stack duplicate non-responsive overrides')
need('.invoice-receipt-meta dl>div:last-child:nth-child(odd){grid-column:1/-1}' in pcss,'odd receipt metadata full-width')

# Notification routing/one-tap claim integrity.
push=txt('includes/push.php'); call=tx(t('api/waiter_call.php') + '\n' + t('includes/waiter_call_service.php')); action=txt('api/push_action.php'); shell=txt('includes/panel_layout.php'); pushapi=txt('waiter/api_push.php'); worker=txt('tools/push-worker.php')
need('attention_filter=calls' in call and 'operator/index.php' in call,'waiter-call push URL must open floor/operator workflow')
need("push.admin_live_operations" in push and "role']==='admin'" in push,'admin live push opt-in policy')
need("$requestId!==''" in push and "$eventType==='order'" in push,'event idempotency key separated from display tag')
need('ORDER BY CASE event_type' in push,'push queue priority')
need('ORDER BY updated_at DESC LIMIT 24' not in push,'eligible push destinations must not be silently truncated')
need("'urgency'" in push and "'ttl'" in push,'notification urgency/ttl policy')
need('accept_call' in action and 'FOR UPDATE' in action and 'orders_floor' in action,'atomic one-tap waiter-call claim')
need("['accept_call','approve_order'].includes(event.action)" in sw and 'action_token' in sw,'service-worker action handler')
need('id="waiterNotify"' in shell and 'device-notifications.js' in shell,'shared push device controls')
need('user_home_path($user)' in pushapi,'test notification target must match user home')
need('push.worker_last_seen_at' in worker,'push worker heartbeat')

reg=json.loads(txt('tests/defect_class_registry.json'))
ids={row['id'] for row in reg['classes']}
for expected in ['route_db_runtime','sheet_dismiss_contract','time_semantic_step','composite_control_geometry','selection_tile_alignment','schedule_boundary_overnight','notification_routing_integrity','notification_pipeline_parity','real_markup_geometry','required_print_intent','print_submission_fence','print_attempt_idempotency','print_agent_recovery','print_fifo_integrity']:
    need(expected in ids,'missing defect registry class '+expected)
print('Current broad defect-class PASS')
