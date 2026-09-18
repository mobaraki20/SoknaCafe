#!/usr/bin/env python3
from pathlib import Path
import re
ROOT=Path(__file__).resolve().parents[1]
def read(p): return (ROOT/p).read_text(encoding='utf-8')
version=read('VERSION.txt').strip(); assert version
sw=read('service-worker.js'); assert f"const RELEASE='{version}'" in sw and f"const CACHE='cafe-staff-v{version}'" in sw
choice=read('assets/js/panel-choice.js')
assert "panel:choice-change" in choice and "interaction: interactionModality()" in choice
interaction=read('assets/js/interaction-modality.js')
assert "pointerdown" in interaction and "touchstart" in interaction and "keydown" in interaction
assert "let interactionModality = 'keyboard'" not in choice
conditions=read('assets/js/panel-conditions.js')
assert 'data-panel-condition-source' in conditions and "panel:choice-change" in conditions
layout=read('includes/panel_layout.php'); assert 'assets/js/panel-conditions.js' in layout and 'assets/js/interaction-modality.js' in layout
assert layout.index('interaction-modality.js') < layout.index('panel-shell.js')
ops=read('admin/operations_report.php'); reporting=read('includes/reporting.php')
assert "report_render_range_fields($range,'operationsReport')" in ops
assert reporting.count('data-panel-condition-source="<?= e($selectId) ?>" data-panel-condition-value="custom"')==2
for residue in ('queueMicrotask(', 'observer.observe(period', ".closest?.('.panel-choice-option')"):
    assert residue not in ops, residue
assert "<?= $customPeriod?'':'disabled' ?>" not in ops
css=read('assets/css/panel-components.css')
assert '-webkit-tap-highlight-color:transparent' in css
assert 'html[data-input-modality="pointer"] .panel-choice-trigger[aria-expanded="true"]' in css
messages=read('admin/messages.php'); assert 'class="panel-copy-stack"' in messages
assert '.panel-copy-stack{display:grid' in css and '.panel-page-actions>div:first-child{display:grid' in css
# Application pages must not couple themselves to panel-choice's internal option DOM.
for folder in ('admin','operator','staff','waiter','includes'):
    for p in (ROOT/folder).rglob('*'):
        if p.suffix not in ('.php','.js'): continue
        assert '.panel-choice-option' not in p.read_text(encoding='utf-8',errors='ignore'), p
print('1.31.7 root contracts passed: one Choice owner, declarative dependent-field owner, touch/keyboard focus contract, no page-level option coupling, and shared copy-stack header contract.')
