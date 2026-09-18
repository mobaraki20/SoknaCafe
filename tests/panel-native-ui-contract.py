#!/usr/bin/env python3
from pathlib import Path
import re
ROOT=Path(__file__).resolve().parents[1]
roots=['admin','operator','waiter','staff','includes','assets/js']
violations=[]
call=re.compile(r'(?<![.\w])(alert|confirm|prompt|reportValidity)\s*\(')
for base in roots:
  for p in (ROOT/base).rglob('*'):
    if not p.is_file() or p.suffix not in {'.php','.js'} or 'admin/update/' in p.as_posix(): continue
    text=p.read_text(encoding='utf-8',errors='ignore')
    for m in call.finditer(text):
      # Native PWA installation prompt is a browser API method (.prompt()), not matched by the regex.
      violations.append(f'{p.relative_to(ROOT)}:{text.count(chr(10),0,m.start())+1}:{m.group(1)}')
assert not violations,'Native browser message calls remain: '+', '.join(violations)
layout=(ROOT/'includes/panel_layout.php').read_text(encoding='utf-8')
assert 'panel-validation.js' in layout and layout.index('panel-validation.js') < layout.index('id="panelContent"')
validator=(ROOT/'assets/js/panel-validation.js').read_text(encoding='utf-8')
assert "document.addEventListener('invalid'" in validator and 'event.preventDefault()' in validator and 'panel-field-error' in validator
print('Panel native-UI contract passed: no raw alert/confirm/prompt/reportValidity in normal panel flows, and validation interception loads before panel forms.')
