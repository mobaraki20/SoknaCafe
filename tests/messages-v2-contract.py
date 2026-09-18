#!/usr/bin/env python3
from pathlib import Path
R=Path(__file__).resolve().parents[1]
read=lambda p:(R/p).read_text(encoding='utf-8')
page=read('admin/messages.php');js=read('assets/js/messages-admin.js');fn=read('includes/function_domains/messages.php');audit=read('includes/audit_presentation.php')
for token in ['data-message-search','data-message-filter="changed"','data-message-filter="optional"','data-message-group','data-message-preview','data-message-count','data-insert-token']:
 assert token in page, token
assert 'guest_message.reset' in page and 'guest_messages.reset' in page and 'guest_messages.updated' in page
assert 'متغیر ناشناخته' in page and 'باید حفظ شود' in page
assert "($definition['editable'] ?? true) !== true" in page
assert 'lastChanges' in page and 'audit_log' in page
assert "replace(/ي/g,'ی').replace(/ك/g,'ک')" in js and 'filter === \'changed\'' in js
assert 'guest_message.reset' in audit
assert "'editable'=>false" in fn
print('Messages v2 contract PASS: scalable search/group/filter, per-message reset, token guardrails, preview/count and audit evidence coexist with protected noneditable system copy.')
