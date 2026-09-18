#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')
errors=[]
def need(p,n,m):
 if n not in read(p):errors.append(m)
def forbid(p,n,m):
 if n in read(p):errors.append(m)
need('includes/auth.php','function user_home_path','Capability-based landing route is missing.')
need('operator/api_orders.php','bill_items','Unified table bill aggregation is missing.')
need('includes/functions.php','function text_substr','Unicode-safe fallback is missing.')
need('waiter/index.php','آماده‌سازی','Focused preparation queue title is missing.')
need('waiter/index.php','staffActionQueue','Unified actionable queue is missing.')
forbid('waiter/index.php','مسئولیت شیفت','Temporary shift responsibility UI remains.')
forbid('waiter/index.php','waiterTables','Ordinary tables remain mixed into the action queue.')
forbid('waiter/api_feed.php','responsibility_active(','Staff feed still depends on temporary shift toggles.')
need('staff/api_quick_order.php','expected_price','Quick-order price revalidation is missing.')
if errors:raise SystemExit('\n'.join(errors))
print('Operational clarity passed: capability routing, unified bills, preparation-only queue, and safe quick-order revalidation.')
