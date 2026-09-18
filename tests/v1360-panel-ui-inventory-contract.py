#!/usr/bin/env python3
from pathlib import Path
import json,re
ROOT=Path(__file__).resolve().parents[1]
registry=json.loads((ROOT/'tests/ui-conformance-pages.json').read_text(encoding='utf-8'))

def is_ui_page(p:Path)->bool:
    t=p.read_text(encoding='utf-8',errors='ignore')
    rel=p.relative_to(ROOT).as_posix()
    if '/api_' in rel or rel.endswith('/api.php') or p.name.startswith('api_'): return False
    if rel=='admin/update/index.php': return True
    return ('panel_header(' in t or 'render_operator_page(' in t or 'render_invoices_page(' in t or 'render_subscribers_page(' in t or '<!doctype html>' in t)

actual={'login.php'}
for base in ('admin','operator','staff','waiter'):
    for p in (ROOT/base).rglob('*.php'):
        if is_ui_page(p): actual.add(p.relative_to(ROOT).as_posix())
registered=set(registry)
missing=sorted(actual-registered)
extra=sorted(registered-actual)
assert not missing, f'UI pages missing from conformance registry: {missing}'
assert not extra, f'stale/non-UI registry entries: {extra}'
allowed_families={'auth','dashboard','tables','operations','supply','inventory','menu','finance','reporting','marketing','settings','printing','maintenance'}
for page,meta in registry.items():
    assert meta.get('family') in allowed_families,(page,meta)
    tests=meta.get('tests')
    assert isinstance(tests,list) and tests,(page,'missing browser family tests')
    for test in tests:
        assert (ROOT/'tests'/test).is_file(),(page,'missing test',test)
print(f'Panel UI inventory contract PASS: {len(actual)} UI entrypoints are registered across {len(set(m["family"] for m in registry.values()))} families; no page is silently outside the audit.')
