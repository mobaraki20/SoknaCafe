from pathlib import Path
from collections import Counter
import json,re

ROOT=Path(__file__).resolve().parents[1]
BASE=json.loads((ROOT/'docs/ui-design-system/UI_DEBT_BASELINE.json').read_text('utf-8'))['metrics']
css_files=[p for p in (ROOT/'assets/css').rglob('*.css') if not p.name.startswith('scds-')]
css='\n'.join(p.read_text('utf-8',errors='ignore') for p in css_files)
markup_files=[*ROOT.rglob('*.php'),*ROOT.rglob('*.html')]
markup='\n'.join(p.read_text('utf-8',errors='ignore') for p in markup_files if '.git' not in p.parts)
conds=[' '.join(x.split()) for x in re.findall(r'@media\s*([^\{]+)\{',css,re.I)]
metrics={
 'important_count_max':len(re.findall(r'!important\b',css,re.I)),
 'direct_hex_colors_max':len(re.findall(r'#[0-9a-fA-F]{3,8}\b',css)),
 'direct_rgb_functions_max':len(re.findall(r'\brgba?\s*\(',css,re.I)),
 'media_queries_max':len(re.findall(r'@media\b',css,re.I)),
 'unique_media_conditions_max':len(set(conds)),
 'physical_direction_properties_max':len(re.findall(r'\b(?:left|right|margin-left|margin-right|padding-left|padding-right|border-left|border-right)\s*:',css)),
 'inline_style_attributes_max':len(re.findall(r'\sstyle\s*=\s*["\']',markup,re.I)),
 'style_blocks_max':len(re.findall(r'<style\b',markup,re.I)),
}
failed=[]
for key,val in metrics.items():
    ceiling=int(BASE[key])
    if val>ceiling:
        failed.append(f'{key}: {val} > baseline ceiling {ceiling}')
if failed:
    raise SystemExit('FAIL UI debt regression budget:\n- '+'\n- '.join(failed))
print('PASS SCDS regression budget: '+', '.join(f'{k}={v}' for k,v in metrics.items()))
