#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
read=lambda rel:(ROOT/rel).read_text(encoding='utf-8')
css=read('assets/css/panel-components.css')
panel=read('assets/css/panel.css')
inv=read('includes/invoices_page.php')
sub=read('includes/subscribers_page.php')
acc=read('admin/accommodation.php')
period=read('admin/financial_periods.php')
design=read('docs/UI_DESIGN_SYSTEM_FA.md')
checks=[]
def ok(cond,msg):
    checks.append((bool(cond),msg))
    if not cond: print('FAIL:',msg)

for token in ['--financial-primary-soft','--financial-primary-soft-strong','--financial-accent-soft','--financial-line','--financial-value','--financial-shadow']:
    ok(token in css, f'financial visual token exists: {token}')
ok('.financial-page-head{' in css and 'linear-gradient(105deg,var(--financial-primary-soft)' in css, 'financial page shell has a restrained token-backed context surface')
ok('.financial-invoice-workspace .invoice-day-group>header' in css and 'var(--financial-accent-soft)' in css, 'invoice date grouping uses restrained accent/primary hierarchy')
ok('.invoice-related-document{' in css and 'grid-template-areas:"title action" "copy action"' in css, 'related documents have a dedicated readable layout owner')
ok('invoice-related-document is-reversal' in inv and 'مشاهده فاکتور اصلی' in inv and 'مشاهده سند برگشت' in inv, 'invoice reversal relation uses explicit related-document actions')
ok('financial-info-disclosure' in period and '<details class="financial-context-strip' in period, 'financial-period help is progressive disclosure')
ok('$historyPeriods=' in period and 'foreach($historyPeriods as $period)' in period, 'current financial period is not duplicated in history')
ok('accommodation_reservation_human_label' in acc and 'format_jalali_human_datetime($eventTime)' in acc, 'accommodation prioritizes human reservation/time references')
history_start=acc.index('<section class="card accommodation-history-card')
history_end=acc.index('<?php if($attentionTotal>0): ?>', history_start)
normal_history=acc[history_start:history_end]
ok('financial-canonical-meta' not in normal_history, 'canonical reservation id is removed from normal accommodation rows')
ok('subscriber-ledger-metadata' not in sub and '<div class="financial-receipt-total">' not in sub[sub.index('<section class="card subscriber-ledger-card'):sub.index('</div>\n        <?php if($ledgerPages>1):')], 'subscriber ledger removes duplicated metadata/final-total noise')
ok('.panel-section-invoices,.panel-section-subscribers,.panel-section-financial_periods,.panel-section-accommodation' in panel, 'financial pages share one panel section accent contract')
ok('Financial visual hierarchy' in design, 'design system documents the financial visual hierarchy contract')
ok('No duplicate information' in design, 'design system documents the no-duplicate-information rule')
if any(not good for good,_ in checks): raise SystemExit(1)
print(f'financial visual hierarchy contract passed: {len(checks)} checks')
