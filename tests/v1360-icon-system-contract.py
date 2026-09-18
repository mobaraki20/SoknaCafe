#!/usr/bin/env python3
from pathlib import Path
import re, xml.etree.ElementTree as ET
ROOT=Path(__file__).resolve().parents[1]
functions=(ROOT/'includes/functions.php').read_text(encoding='utf-8')
sprite=ROOT/'assets/icons/ui-sprite.svg'
notifications=(ROOT/'assets/js/device-notifications.js').read_text(encoding='utf-8')

def need(cond,msg):
    if not cond: raise AssertionError(msg)

need('function category_icon_registry(): array' in functions, 'category icon registry missing')
need('preg_match($regex' not in functions[functions.index('function category_visual_icon'):functions.index('function campaign_selection_mode')], 'legacy regex chain still owns category inference')
for key in ['bean','brew','tea','herbal','cup-hot','iced-coffee','mocktail','juice','sharbat','shake','smoothie','healthy-drink','protein','energy-drink','water','sugar-free','cold-drink','snowflake','brunch','breakfast','bakery','pastry','waffle','donut','iranian-food','grill','fried','seafood','sushi','noodles','soup','burger','hot-dog','pizza','pasta','sandwich','salad','appetizer','fries','vegan','diet','kids-menu','sharing','combo','sauce','food','cake','dessert','ice-cream','chocolate','service','seasonal','retail','gift','sparkles','list']:
    need(f"'key'=>'{key}'" in functions, f'icon registry missing {key}')

root=ET.parse(sprite).getroot(); ns={'s':'http://www.w3.org/2000/svg'}
need(root.attrib.get('data-icon-family')=='tabler-outline', 'sprite must have one declared icon family')
need(root.attrib.get('data-icon-version')=='3.46.0', 'sprite source version must stay pinned')
ids=[s.attrib.get('id','') for s in root.findall('s:symbol',ns)]
need(len(ids)==len(set(ids)), 'duplicate SVG symbol ids')
need(len(ids)>=105, f'icon sprite unexpectedly small: {len(ids)}')
for key in re.findall(r"'key'=>'([^']+)'", functions[functions.index('function category_icon_registry'):functions.index('function category_icon_library')]):
    need('icon-'+key in ids, f'registry icon has no SVG symbol: {key}')
for legacy in ['icon-menu','icon-plus','icon-add','icon-chevron-left','icon-arrow-left']:
    need(legacy in ids, f'functional compatibility symbol missing: {legacy}')

# Icon controls must not fall back to font-dependent glyphs. Quantity math in
# receipts remains text; this only guards button markup.
for path in [*ROOT.rglob('*.php'), *ROOT.rglob('*.js')]:
    if any(part in {'tests','vendor','node_modules'} for part in path.parts): continue
    raw=path.read_text(encoding='utf-8',errors='ignore')
    for glyph in ['×','+','−','↻','‹','›','✓','⌄']:
        need(f'>{glyph}</button>' not in raw, f'{path.relative_to(ROOT)}: font glyph used as button icon: {glyph}')
need("button.querySelector('span')" in notifications and 'button.textContent' not in notifications, 'notification state update removes the icon-bearing button structure')

# Visual grammar: every category symbol stays on the canonical canvas and does not inject its own stroke width/color.
byid={s.attrib['id']:s for s in root.findall('s:symbol',ns)}
for icon_id,sym in byid.items():
    need(sym.attrib.get('viewBox')=='0 0 24 24', f'{icon_id}: non-canonical viewBox')
    need(sym.attrib.get('data-source','').startswith('tabler:'), f'{icon_id}: source is outside the unified family')
    raw=ET.tostring(sym,encoding='unicode')
    need('stroke-width=' not in raw and 'stroke="#' not in raw and 'fill="#' not in raw, f'{icon_id}: symbol overrides shared visual grammar')
for key in re.findall(r"'key'=>'([^']+)'", functions[functions.index('function category_icon_registry'):functions.index('function category_icon_library')]):
    sym=byid['icon-'+key]
    need(sym.attrib.get('viewBox')=='0 0 24 24', f'{key}: non-canonical viewBox')

print(f'Icon system contract PASS: {len(ids)} unique Tabler symbols; current + future hospitality taxonomy is registry-driven on one 24x24/currentColor grammar.')
