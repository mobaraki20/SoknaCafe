#!/usr/bin/env python3
from pathlib import Path
import re,sys
R=Path(__file__).resolve().parents[1]
def t(p): return (R/p).read_text()
def need(v,m):
    if not v: print('1.32.14 UAT-fix audit FAILED:',m);sys.exit(1)

sub=t('includes/subscribers_page.php'); items=t('admin/items.php'); itemjs=t('assets/js/items-management.js'); pcss=t('assets/css/panel-components.css')
need('subscriber_ledger_enrich_settlements' in sub and 'subscriber settlement enrichment:' in sub,'subscriber route fail-soft owner')
need('quick-item-flags' in items and 'CafeUI.bindSwipeDismiss' in itemjs,'Quick Edit flags/swipe')
need('CafeUI.bindSwipeDismiss' in t('assets/js/panel-choice.js') and 'CafeUI.bindSwipeDismiss' in t('assets/js/panel-media.js'),'Choice/Media shared swipe owner')
need('falling back to 15 minutes' in t('assets/js/panel-time-picker.js'),'Time Picker semantic fallback')
itemform=t('admin/item_form.php'); funcs=t('includes/functions.php')
need('schedule_start_time' not in itemform and 'schedule_end_time' not in itemform and itemform.count('data-minute-step="15"')>=2,'item schedule date-only + daily semantic time')
need('DAYOFWEEK(NOW())=1,7,DAYOFWEEK(NOW())-1' in funcs,'overnight previous weekday')
need('.panel-content :is(.tag-check-grid,.day-check-grid)>label{display:flex;align-items:center' in pcss and 'min-height:48px' in pcss,'selection tile alignment')
need('.jalali-date-control{display:grid' in pcss and '.jalali-date-button{position:static' in pcss,'Jalali composite containment')
need('.invoice-receipt-meta dl>div:last-child:nth-child(odd){grid-column:1/-1}' in pcss,'invoice odd metadata full row')
need(pcss.count('.invoice-receipt-meta{border-top:1px')==0,'invoice receipt non-responsive override stack not retired')
acc=t('admin/accommodation.php')
need('data-auto-submit' in acc and '>اعمال<' not in acc,'accommodation auto-apply/no big Apply')
need("if($display['needs_action']||(string)$row['status']!=='posted')" in acc and "'posted'=>['label'=>'منتقل‌شده','tone'=>'success','needs_action'=>false]" in t('includes/accommodation.php'),'accommodation hides normal posted success badge while preserving exception state')
push=t('includes/push.php'); call=(t('api/waiter_call.php') + '\n' + t('includes/waiter_call_service.php')); action=t('api/push_action.php')
need('attention_filter=calls' in call and 'operator/index.php' in call,'waiter call target')
need('push.admin_live_operations' in push and 'user_preparation_areas' in push,'notification responsibility routing')
need('accept_call' in action and 'FOR UPDATE' in action,'notification one-tap atomic claim')
notify=t('assets/js/device-notifications.js')
need("confirm?.('اعلان‌های پس‌زمینه" in notify and not re.search(r'confirm\??\.?(?:\s*)\(\s*\{',notify),'notification shared confirm signature')
print('1.32.14 UAT fix audit PASS')
