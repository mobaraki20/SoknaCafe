from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
js=(ROOT/'assets/js/operator.js').read_text(encoding='utf-8')
css=(ROOT/'assets/css/operator-live.css').read_text(encoding='utf-8')
# Success feedback is built from structured state, not backend prose duplicated into toast + notice.
required=[
    'function settlementPrintWarning(result)',
    'function settlementClosedMessage(tableId)',
    'async function finalizeSettlementUi(tableId,result={}',
    'CafeUI.toast(completionMessage',
    'const notice=`پرداخت ${money(paidNow)} ثبت شد`',
    'مانده ${digits(row.remaining_quantity)} عدد',
    '${digits(row.paid_quantity)} پرداخت‌شده',
    'itemizedNoticeTimer:null',
    "window.setTimeout(()=>{state.itemizedNoticeTimer=null",
]
missing=[x for x in required if x not in js]
if missing: raise SystemExit('FAIL settlement feedback contract: '+', '.join(missing))
if 'finalizeSettlementUi(tableId,data.message' in js:
    raise SystemExit('FAIL: final settlement UI still consumes backend prose directly')
if 'رسید در صف چاپ' in js[js.find('async function continueItemizedSettlement'):js.find('async function submitItemizedSettlement')]:
    raise SystemExit('FAIL: queued print status still duplicates normal partial-payment success')
if '.itemized-selection-meta' not in css:
    raise SystemExit('FAIL: compact itemized metadata owner missing')
if 'scrollbar-width:none' not in css or '.itemized-settlement-box .settlement-content::-webkit-scrollbar' not in css:
    raise SystemExit('FAIL: mobile itemized scroll owner still exposes a heavy scrollbar')
if 'افزودن قلم جاافتاده' not in (ROOT/'includes/operator_page.php').read_text(encoding='utf-8'):
    raise SystemExit('FAIL: late-accounting action is not explicit enough')
print('PASS: settlement feedback is structured, concise, and non-duplicative')
