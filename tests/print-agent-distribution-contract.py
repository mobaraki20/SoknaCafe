#!/usr/bin/env python3
from pathlib import Path
import json
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')
printing=read('includes/printing.php')
admin=read('admin/printing.php')
setup=read('runtime/windows/setup-sokna.ps1')
build=read('runtime/windows/build-print-worker.ps1')
reconcile=read('docs/architecture-migration-r2/PHASE7R_PRINTING_RECONCILIATION_FA.md')
source=ROOT/'runtime/print-worker/source'
prov=json.loads((source/'PROVENANCE.json').read_text(encoding='utf-8'))

def need(cond,msg):
    if not cond: raise AssertionError(msg)

need((ROOT/'print-agent/v4/api.php').is_file(),'Print API v4 must remain in Cafe')
need(not (ROOT/'print-agent/api.php').exists(),'legacy v3 Agent API must not return')
need(prov.get('upstream_version')=='6.2.5','internal worker provenance must pin audited 6.2.5 baseline')
need((source/'src/Sokna.PrintAgent.Core').is_dir() and (source/'src/Sokna.PrintAgent.Service').is_dir() and (source/'src/Sokna.PrintAgent.Worker').is_dir(),'operational Print Worker source must ship inside SOKNA source')
need(not (source/'src/Sokna.PrintAgent.Setup').exists() and not (source/'src/Sokna.PrintAgent.Control').exists(),'standalone Setup/Control product must not be bundled as owners')
need("@('Service','Worker')" in build and 'component-manifest.json' in build,'internal build must produce SOKNA-owned Service/Worker bundle')
need('Install-PrintWorkerComponent' in setup and 'PrintWorkerBundle' in setup,'SOKNA Setup must install its internal worker')
need('PrintAgentSetup' not in setup and 'PrintAgentSha256' not in setup,'external Print Agent installer parameters are forbidden')
need('mobaraki20/Pagent' not in printing and '/releases/latest' not in printing,'runtime must not resolve external Agent releases')
need('print_agent_download_url()' not in admin and 'print_agent_release_page_url()' not in admin,'admin must not expose external download/install flow')
need('برنامه جداگانه‌ای برای دانلود ندارد' in admin,'admin must explain integrated ownership in Persian')
need('محصول جدا نصب نمی‌شود' in reconcile,'R2 integrated distribution decision must be explicit')
need((ROOT/'docs/PRINT_AGENT_DISTRIBUTION_CONTRACT_FA.md').is_file(),'historical distribution contract must remain as superseded provenance')
print('PASS print_worker_internal_distribution_contract')
