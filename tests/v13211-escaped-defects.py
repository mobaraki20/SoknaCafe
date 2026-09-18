#!/usr/bin/env python3
from pathlib import Path
import re, sys
R=Path(__file__).resolve().parents[1]

def t(p): return (R/p).read_text()
def need(v,m):
    if not v:
        print('1.32.14 escaped-defects FAILED:',m)
        sys.exit(1)

sub=t('includes/subscribers_page.php')
# Subscriber route: primary ledger rendering must not depend on settlement schema.
need('function subscriber_ledger_enrich_settlements' in sub,'subscriber settlement enrichment helper missing')
need('subscriber_ledger_enrich_settlements($pdo,$ledger)' in sub,'subscriber ledger is not enriched after base query')
base=re.search(r'\$sql="SELECT l\.\*,u\.display_name actor_name.*?ORDER BY l\.created_at DESC,l\.id DESC LIMIT \$ledgerPerPage OFFSET \$ledgerOffset";',sub,re.S)
need(base is not None,'subscriber base ledger query not found')
need('settlement_records' not in base.group(0),'subscriber primary ledger query still depends on settlement_records')
need("catch (Throwable $e)" in sub and "subscriber settlement enrichment:" in sub,'subscriber settlement enrichment must fail soft with logging')
need('NULL actor_name,NULL table_id,NULL table_name,NULL reversal_id' in sub,'subscriber base metadata fallback missing')
need("foreach (['settlement_id','invoice_number','settled_at','destination','settlement_status','reversal_settlement_id','reversal_invoice_number']" in sub,'subscriber enrichment fields not initialized for safe rendering')

# Shared dismiss owner: panel sheets must not reimplement gesture math.
core=t('assets/js/panel-core.js'); shell=t('assets/js/panel-shell.js'); choice=t('assets/js/panel-choice.js'); media=t('assets/js/panel-media.js'); item=t('assets/js/items-management.js')
need(all('CafeUI.bindSwipeDismiss' in src for src in [shell,choice,media,item]),'dismissible panel sheets are not bound to shared swipe owner')
for name,src in [('panel-choice.js',choice),('panel-media.js',media)]:
    need('touchstart' not in src and 'pointerdown' not in src, f'{name} restored a bespoke swipe implementation')
need('bindSwipeDismiss' in core and 'canStart' in core,'shared swipe owner incomplete')

# Shared confirm API must use the project signature, not object-style calls.
notify=t('assets/js/device-notifications.js')
need("confirm?.('اعلان‌های پس‌زمینه" in notify and "'خاموش‌کردن اعلان دستگاه'" in notify,'device notification disable confirm does not use shared signature')
need(not re.search(r'confirm\??\.?(?:\s*)\(\s*\{', notify),'object-style CafeUI.confirm call is invalid')
need("button.querySelector('span')" in notify and 'button.textContent' not in notify,'dynamic notification label must preserve the icon-bearing button structure')

# Release discipline: a promotion run must require real DB + authenticated HTTP route probes.
gate=t('tests/run-release-gate.sh')
need('SOKNA_RELEASE_PROMOTION' in gate,'promotion mode missing from release gate')
need('SOKNA_REQUIRE_DB_ROUTE_GATE=1' in gate and 'SOKNA_REQUIRE_HTTP_ROUTE_GATE=1' in gate,'promotion does not force environment route gates')
need('v13211-critical-route-db.php' in gate and 'v13211-critical-route-http.py' in gate,'new critical route probes not wired into release gate')

print('1.32.14 escaped-defect contracts PASS')
