from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')
printing=read('includes/printing.php')
layout=read('includes/panel_layout.php')
admin=read('admin/printing.php')
templates=read('admin/print_templates.php')
css=read('assets/css/panel-components.css')

def need(cond,msg):
    if not cond: raise AssertionError(msg)

need('print_worker_component_metadata' in printing and 'mobaraki20/Pagent' not in printing, 'Phase7R supersedes external Agent release resolution with the internal Print Worker component')
need("['items','categories','printing']" in layout and 'assets/css/reorder.css' in layout, 'printing must load the shared reorder component stylesheet')
need('برنامه چاپ 6.1.0 را نصب کنید' not in admin, 'stale Agent 6.1.0 guidance remains in runtime UI')
need("$originLabels=['builtin'=>'استاندارد سکنا','imported'=>'واردشده','custom'=>'سفارشی'];" in templates, 'template origins must be human labels, not raw technical values')
need('<bdi dir="ltr"><?= e((string)$row[\'template_version\']) ?></bdi>' in templates, 'template version must be bidi-isolated on RTL mobile UI')
need('بازبینی <?= fa_digits((int)($row[\'revision\']??1)) ?>' in templates, 'template revision must be shown separately from source version')
need('.print-template-history .panel-list-secondary,.print-template-history .panel-row-actions{grid-column:1;grid-row:auto}' in css, 'mobile history actions must not inherit global row 1 placement')
print('PASS: dev17 phase1 print UI/distribution contract')
