#!/usr/bin/env python3
from pathlib import Path
import re
ROOT=Path(__file__).resolve().parents[1]
def read(rel): return (ROOT/rel).read_text(encoding='utf-8')
layout=read('assets/css/panel-layout.css'); quick=read('assets/css/quick-order.css')
for selector in ['.sidebar{','.panel-topbar{','.panel-main{','.panel-content{','.panel-subnav{']:
    assert selector in layout, selector
for rel in ['assets/css/app.css','assets/css/responsive.css','assets/css/panel.css']:
    text=read(rel)
    for exact in [r'(?:^|})\s*\.sidebar\s*\{',r'(?:^|})\s*\.panel-topbar\s*\{',r'(?:^|})\s*\.panel-main\s*\{',r'(?:^|})\s*\.panel-content\s*\{',r'(?:^|})\s*\.panel-subnav\s*\{']:
        assert not re.search(exact,text), f'{rel} still owns canonical layout selector {exact}'
    assert '.quick-order-' not in text, f'{rel} still owns quick-order selectors'
runtime_files=[ROOT/'includes/panel_layout.php',ROOT/'service-worker.js']+[p for p in (ROOT/'assets').rglob('*') if p.is_file()]
assert 'staff-quick-order-refinement' not in ''.join(p.read_text(errors='ignore') for p in runtime_files)
assert '.panel-body.panel-sidebar-open' in layout
assert '/* Sokna 1.30.2 — dedicated staff ordering workspace.' in quick
print('Panel CSS ownership passed: layout and quick-order have single canonical owners; deleted refinement layer has no references.')
