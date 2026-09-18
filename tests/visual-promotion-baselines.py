#!/usr/bin/env python3
from pathlib import Path
import json,os,sys
R=Path(__file__).resolve().parents[1]
data=json.loads((R/'tests/visual_quality_pages.json').read_text(encoding='utf-8'))
pending=[row['id'] for row in data['pages'] if row.get('baseline_status')!='approved']
if pending:
    if os.getenv('SOKNA_RELEASE_PROMOTION')=='1':
        print('visual-promotion-baselines FAILED: human-approved visual baselines pending:',', '.join(pending));sys.exit(1)
    print('UAT_REQUIRED: human-approved visual baselines pending:',', '.join(pending));sys.exit(0)
print('visual-promotion-baselines PASS')
