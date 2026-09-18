from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
errors=[]
def read(p): return (ROOT/p).read_text(encoding="utf-8")
def need(p,t,m):
    if t not in read(p): errors.append(m)
def forbid(p,t,m):
    if t in read(p): errors.append(m)
need("admin/settings.php","public_waiter_call_enabled","Public waiter-call setting is missing.")
need("menu/index.php","CAFE_PUBLIC_WAITER_TABLES","Public waiter-call state is not exposed safely.")
need('menu/index.php','id="publicWaiterTable"','Public waiter call does not require table selection.')
need("api/waiter_call.php","public_table_id","Public waiter call does not identify the selected table safely.")
forbid("assets/js/operator.js","ثبت حضور بدون سفارش","Manual presence still appears in operator UI.")
forbid("operator/index.php","billDiscountReason","Discount reason field still exists.")
forbid("assets/js/operator.js","billDiscountReason","Discount reason is still sent by the client.")
need("operator/api_bill.php","discount_by_user_id","Discount actor is not audited.")
need("admin/operations_report.php","discount_by","Discount actor is absent from operations export.")
need("includes/font_runtime.php","v33.003","Vazirmatn installer is not pinned to an official version.")
need("includes/functions.php","return '\"' . $family . '\", Tahoma","Vazirmatn is not forced as the first UI font.")
if errors:
    raise SystemExit("\n".join("- "+e for e in errors))
print("Policy simplification checks passed.")
