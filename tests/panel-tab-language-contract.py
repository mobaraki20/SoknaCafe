#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')
css=read('assets/css/panel-components.css')
layout=read('assets/css/panel-layout.css')
operator=read('includes/operator_page.php')
printing=read('admin/printing.php')
settings=read('admin/settings.php')
messages=read('admin/messages.php')
templates=read('admin/print_templates.php')
primary=css[css.index('.panel-primary-tabs'):css.index('/* Operator live error')]
secondary=layout[layout.index('.panel-subnav'):layout.index('@media(max-width:520px)')]
checks={
 'canonical primary component exists':'.panel-primary-tabs' in css,
 'primary touch target is 44px':'min-height:44px' in primary,
 'primary active fill uses brand primary':'background:var(--primary)' in primary,
 'primary active text uses on-primary':'color:var(--on-primary,#fff)' in primary,
 'operator uses canonical primary tabs':'operator-work-tabs-v1280 panel-primary-tabs' in operator,
 'printing uses canonical primary tabs':'panel-primary-tabs print5-tabs' in printing,
 'settings sections use canonical primary tabs':'panel-primary-tabs settings-section-nav' in settings,
 'print template switch is secondary navigation':'panel-subnav print-template-switch' in templates,
 'message categories are explicitly filters':'role="group" aria-label="فیلتر دسته متن‌ها"' in messages,
 'message categories are not mislabeled as tabs':'messages-v2-groups" role="tablist"' not in messages,
 'secondary nav remains separate':'.panel-subnav' in layout,
 'secondary active state is soft, not full primary fill':'background:var(--primary' not in secondary,
 'segmented filters remain available':'.segmented-control' in css,
 'legacy tab style owners are removed': all(x not in css for x in ['.panel-tabs','.page-tabs','.settings-tabs']),
}
failed=[k for k,v in checks.items() if not v]
for k,v in checks.items(): print(('PASS' if v else 'FAIL'),k)
if failed: raise SystemExit('Panel tab language contract failed: '+', '.join(failed))
print(f'Panel tab language contract PASS: {len(checks)}/{len(checks)} checks.')
