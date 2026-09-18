#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')
messages=read('admin/messages.php'); css=read('assets/css/panel-components.css'); ops=read('admin/operations_report.php'); reporting=read('includes/reporting.php'); choice=read('assets/js/panel-choice.js'); layout=read('includes/panel_layout.php')
assert 'class="panel-copy-stack"' in messages
assert '.panel-copy-stack' in css
assert 'panel:choice-change' in choice
assert "previousValue," in choice and "value: select.value," in choice and "interaction: interactionModality()," in choice
interaction=read('assets/js/interaction-modality.js'); assert "pointerdown" in interaction and "keydown" in interaction
assert "let interactionModality = 'keyboard'" not in choice
assert 'panel-conditions.js' in layout and 'interaction-modality.js' in layout
assert "report_render_range_fields($range,'operationsReport')" in ops
assert 'data-panel-condition-source="<?= e($selectId) ?>" data-panel-condition-value="custom"' in reporting
for forbidden in ('queueMicrotask','MutationObserver','.panel-choice-option'):
    assert forbidden not in ops, f'page-level choice owner leaked back: {forbidden}'
assert '-webkit-tap-highlight-color:transparent' in css
# Exact legacy pattern that caused title/subtitle collision must not reappear.
assert '<div class="toolbar"><div><strong>متن‌ها و تجربه مهمان</strong><small' not in messages
print('1.31.7 shared UI structural contracts passed: one choice owner, declarative conditions, touch/keyboard modality contract and canonical title-copy stack.')
