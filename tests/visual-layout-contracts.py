#!/usr/bin/env python3
from pathlib import Path
import json,re,sys
R=Path(__file__).resolve().parents[1]

def need(ok,msg):
    if not ok:
        print('visual-layout-contract FAILED:',msg);sys.exit(1)

def txt(path): return (R/path).read_text(encoding='utf-8')

tokens=txt('assets/css/tokens.css')
for token in ['--panel-gap-copy','--panel-gap-control','--panel-gap-section','--panel-gap-page']:
    need(token in tokens,'missing semantic spacing token '+token)
css=txt('assets/css/panel-components.css')
for owner in ['.panel-page-flow{','.panel-card-flow{','.panel-copy-stack{','.panel-diagnostic-grid{']:
    need(owner in css,'missing visual composition owner '+owner)
need(len(re.findall(r'(?m)(?:^|\})\s*\.panel-copy-stack\{',css))==1,'panel-copy-stack must have exactly one canonical owner')
need('.push-device-card,.push-log-card{margin-top:' not in css,'push cards still own sibling spacing')
need('.push-health-strip{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px;margin-' not in css,'push summary still owns page spacing')

push=txt('admin/push_devices.php')
for contract in ['data-visual-quality-page="push_devices"','panel-page-flow push-devices-page','card-body panel-card-flow','panel-diagnostic-grid push-worker-health','panel-copy-stack','push-policy-toggle']:
    need(contract in push,'push devices page missing composition contract '+contract)
need('push-worker-health"><span>Worker:' not in push,'legacy inline worker diagnostic returned')
need('<label class="check-line push-policy-toggle">' in push,'policy control must be a fully clickable label')

baseline=json.loads(txt('tests/visual_quality_baseline.json'))
debt={row['path'] for row in baseline['top_level_surface_debt']}
owners=['panel-page-flow','panel-surface-stack','page-grid','settings-page-grid']
found=set()
for p in sorted((R/'admin').glob('*.php')):
    s=p.read_text(encoding='utf-8',errors='ignore')
    count=len(re.findall(r'<section\s+class="[^"]*\bcard\b',s))
    if count<2: continue
    rel=p.relative_to(R).as_posix()
    has_owner=any(owner in s for owner in owners)
    if not has_owner:
        found.add(rel)
        need(rel in debt,f'new/untracked multi-surface page without shared flow owner: {rel}')
for rel in debt:
    need(rel in found,f'stale visual debt baseline entry; page now has a shared flow owner and must be removed: {rel}')

registry=json.loads(txt('tests/visual_quality_pages.json'))
ids=set()
for row in registry['pages']:
    need(row['id'] not in ids,'duplicate visual page id '+row['id']);ids.add(row['id'])
    need((R/row['source']).exists(),'visual page source missing '+row['source'])
    need(row['baseline_status'] in {'pending_uat','approved'},'invalid baseline status '+row['id'])
    need(set(row['widths']).issubset({320,360,390,412,768,1366}),'unexpected width in '+row['id'])
need('push_devices_worker_stopped' in ids,'notification page missing from visual baseline inventory')
print(f'visual-layout-contract PASS ({len(found)} explicit legacy composition debts, {len(ids)} visual states registered)')
