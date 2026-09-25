from pathlib import Path
import json

ROOT=Path(__file__).resolve().parents[1]

def require(cond,msg):
    if not cond:
        raise SystemExit('FAIL: '+msg)

contract=(ROOT/'docs/ui-design-system/CANONICAL_DESIGN_SYSTEM_CONTRACT_FA.md').read_text('utf-8')
registry=json.loads((ROOT/'docs/ui-design-system/COMPONENT_REGISTRY.json').read_text('utf-8'))
foundation=(ROOT/'assets/css/scds-foundation.css').read_text('utf-8')
legacy=(ROOT/'docs/UI_DESIGN_SYSTEM_FA.md').read_text('utf-8')
ai_handoff=(ROOT/'docs/AI_HANDOFF/README_FA.md').read_text('utf-8')

require('SCDS-CANONICAL-2026-R1' in contract,'canonical design-system id missing')
require('رد شده' in contract and 'RTL-first' in contract and 'Persian-first' in contract,'owner decisions / Persian RTL rules missing')
require(registry.get('design_system_id')=='SCDS-CANONICAL-2026-R1','component registry points to wrong system')
require(registry.get('previous_sokna_design_systems')=='rejected_not_authoritative','rejected previous DS decision not machine-readable')
require('SUPERSEDED / NON-AUTHORITATIVE FOR NEW WORK' in legacy and 'SCDS-CANONICAL-2026-R1' in legacy,'legacy UI standard must declare canonical supersession')
require('docs/ui-design-system/CANONICAL_DESIGN_SYSTEM_CONTRACT_FA.md' in ai_handoff and 'legacy compatibility/reference' in ai_handoff,'AI handoff must point to canonical DS and quarantine legacy DS')
require('--scds-touch-target:44px' in foundation,'44px canonical touch target missing')
require('html[lang="fa"]{direction:rtl' in foundation,'Persian RTL foundation missing')

for rel in ['includes/panel_layout.php','login.php','includes/guest_menu_view.php','staff/quick-order.php']:
    text=(ROOT/rel).read_text('utf-8')
    require("assets/css/scds-foundation.css" in text,f'canonical foundation not loaded by {rel}')

# New migration must not reintroduce artifacts from the rejected historical DS stream.
for p in ROOT.rglob('*'):
    if not p.is_file() or '.git' in p.parts or p.suffix.lower() not in {'.php','.css','.js','.md','.json'}:
        continue
    rel=p.relative_to(ROOT).as_posix()
    if rel.startswith('docs/ui-design-system/') or rel=='WORKSPACE_START_HERE_FA.md':
        continue
    text=p.read_text('utf-8',errors='ignore').lower()
    require('sokna-ds-v1-' not in text and 'sokna-ds-v2-' not in text,f'rejected DS asset reference found in {rel}')

print('PASS SCDS canonical contract')
