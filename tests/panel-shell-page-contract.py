#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
layout=(ROOT/'includes/panel_layout.php').read_text(encoding='utf-8')
assert layout.count('id="panelNavToggle"')==1
assert layout.count('id="panelToolsToggle"')==1
assert layout.count("asset('assets/js/panel-shell.js')")==1
assert layout.find("asset('assets/js/panel-shell.js')") < layout.find('id="panelContent"')
assert "asset('assets/js/panel-navigation.js')" not in layout
pages=[]
for p in ROOT.rglob('*.php'):
    if p.name=='panel_layout.php': continue
    text=p.read_text(encoding='utf-8',errors='ignore')
    if 'panel_header(' not in text: continue
    pages.append(p.relative_to(ROOT).as_posix())
    assert 'id="panelNavToggle"' not in text, f'duplicate nav owner in {p}'
    assert 'id="panelToolsToggle"' not in text, f'duplicate tools owner in {p}'
    assert 'panel-shell.js' not in text, f'page-local shell controller in {p}'
    assert 'panel-navigation.js' not in text, f'legacy page-local nav controller in {p}'
    assert 'panel_footer(' in text, f'panel page has no footer contract: {p}'
assert len(pages)>=20, pages
print(f'Panel shell page contract passed across {len(pages)} real panel PHP sources: one shared header owner, no page-local navigation/tools controllers, and footer contract retained.')
