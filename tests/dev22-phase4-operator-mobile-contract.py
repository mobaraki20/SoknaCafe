#!/usr/bin/env python3
from pathlib import Path
R=Path(__file__).resolve().parents[1]
js=(R/'assets/js/operator.js').read_text(encoding='utf-8')
api=(R/'operator/api_orders.php').read_text(encoding='utf-8')
for token in ['last_order_at','last_order_ago']:
    assert token in api
assert "آخرین سفارش ${esc(table.last_order_ago)}" in js
assert "table.last_order_ago?`آخرین سفارش ${table.last_order_ago}`" in js
for token in ['function tableHistoryUrl','function writeTableHistory','operatorTableId','window.addEventListener(\'popstate\'','closeTableView','history.back()','tableListScrollY']:
    assert token in js,token
assert "history.replaceState(history.state,'',url)" in js
print('dev22 phase4 operator mobile contract PASS')
