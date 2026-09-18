#!/usr/bin/env python3
from pathlib import Path
import sys
R=Path(__file__).resolve().parents[1]
def t(p): return (R/p).read_text()
def need(v,m):
    if not v: print('1.32.14 schedule FAILED:',m); sys.exit(1)
f=t('includes/function_domains/jalali.php') + t('includes/functions.php'); item=t('admin/item_form.php'); tp=t('assets/js/panel-time-picker.js')
need('function parse_optional_jalali_day_boundary' in f,'day-boundary parser')
need("$endOfDay ? '23:59:59' : '00:00:00'" in f,'deterministic day bounds')
need('schedule_start_time' not in item and 'schedule_end_time' not in item,'duplicate range times removed')
need(item.count('data-minute-step="15"')>=2,'item daily 15-minute steps')
need('DAYOFWEEK(NOW())=1,7,DAYOFWEEK(NOW())-1' in f,'overnight previous-day semantics')
need("return 15;" in tp and 'panel-time-grip' not in tp,'time modal semantic fallback/no sheet drag')
# Every concrete panel time input must opt into a semantic step explicitly.
for folder in ['admin','operator','waiter','staff']:
    for path in (R/folder).glob('*.php'):
        for no,line in enumerate(path.read_text().splitlines(),1):
            if 'type="time"' in line and 'data-minute-step=' not in line:
                need(False,f'{path.relative_to(R)}:{no} missing data-minute-step')
print('1.32.14 schedule contracts PASS')
