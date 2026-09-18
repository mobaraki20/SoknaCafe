#!/usr/bin/env python3
from pathlib import Path
import sys
R=Path(__file__).resolve().parents[1]
def t(p): return (R/p).read_text()
def need(v,m):
    if not v: print('1.32.14 notification FAILED:',m); sys.exit(1)
p=t('includes/push.php'); sw=t('service-worker.js'); endpoint=t('api/push_action.php'); call=t('api/waiter_call.php'); shell=t('includes/panel_layout.php')
need("WHEN 'waiter_call' THEN 30" in p and "WHEN 'pending_order' THEN 20" in p and "WHEN 'order' THEN 10" in p,'queue priority matrix')
need("$eventType === 'waiter_call' || $eventType === 'pending_order' ? 'high' : 'normal'" in p,'urgency matrix')
need("if((string)$row['role']==='admin' && !$adminLive) return false" in p,'admin default silence')
need("in_array('orders_floor', user_capabilities($userId), true)" in p,'floor recipient capability')
need("array_intersect($areas, user_preparation_areas($userId))" in p,'preparation area routing')
need('order-added-' in t('operator/api_bill.php') and '],$requestId)' in t('operator/api_bill.php'),'order addition has request id distinct from display tag')
need("if($tag===''||$eventType==='order')return null" in p,'order display tag cannot become idempotency key')
need('attention_filter=calls' in call,'waiter call notification opens calls filter')
need("['action'=>'accept_call','title'=>'رسیدگی شد']" in p and "['action'=>'open','title'=>'مشاهده']" in p,'one-tap call completion actions')
need('push_action_token' in p and 'push_action_token_decode' in p,'signed action token')
need("UPDATE waiter_calls SET status='done',active_table_guard=NULL" in endpoint and 'FOR UPDATE' in endpoint,'atomic notification completion mutation')
need("$call['status']==='done'" in endpoint and "'idempotent'=>true" in endpoint,'completion retry idempotency')
need("audit_log_write('waiter_call.completed'" in endpoint and "'source'=>'notification_action'" in endpoint,'notification completion audit')
need("['accept_call','approve_order'].includes(event.action)" in sw and "method:'POST'" in sw,'service worker background action')
need('waiterNotify' in shell and 'waiterNotifyTest' in shell,'shared device notification controls')
print('1.32.14 notification contracts PASS')
