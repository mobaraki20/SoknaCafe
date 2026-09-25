from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

def need(cond,msg):
    if not cond: raise SystemExit('FAIL: '+msg)

customer_files=[
 'includes/panel_layout.php','admin/index.php','includes/settlement.php','includes/operator_page.php',
 'assets/js/operator.js','includes/subscribers_page.php','includes/invoices_page.php',
 'includes/audit_presentation.php','includes/deferred.php','operator/api_settlements.php',
 'operator/api_subscribers.php','includes/subscribers.php','includes/modules.php','includes/printing.php'
]
for rel in customer_files:
    src=(ROOT/rel).read_text('utf-8')
    for old in ['مشترکین','حساب مشترک','تسویه مستقیم']:
        need(old not in src,f'{rel}: legacy user-facing term remains: {old}')


for rel in ['includes/operator_page.php','includes/panel_layout.php']:
    src=(ROOT/rel).read_text('utf-8')
    need('موبایل مشترک' not in src and 'هر مشترک' not in src and 'مشترک را جست' not in src,f'{rel}: subscriber-facing copy still uses مشترک')

settlement=(ROOT/'includes/settlement.php').read_text('utf-8')
need("'direct' => 'تسویه'" in settlement,'direct settlement canonical label missing')
need("'subscriber' => 'حساب مشتری'" in settlement,'customer settlement label missing')
need("panel_header('مشتریان','subscribers')" in (ROOT/'includes/subscribers_page.php').read_text('utf-8'),'customer directory title missing')

# Shared/common is a distinct Persian meaning and must not be globally rewritten.
need("'shared'=>'مشترک'" in (ROOT/'includes/inventory.php').read_text('utf-8'),'inventory shared department vocabulary was incorrectly changed')
need('پیش‌نویس مشترک' in (ROOT/'staff/quick-order.php').read_text('utf-8'),'team-shared draft vocabulary was incorrectly changed')

print('PASS SCDS Persian product language contract')
