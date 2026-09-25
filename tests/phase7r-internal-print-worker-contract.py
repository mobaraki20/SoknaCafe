#!/usr/bin/env python3
import json
from pathlib import Path

R=Path(__file__).resolve().parents[1]
read=lambda p:(R/p).read_text(encoding='utf-8')
source=R/'runtime/print-worker/source'
prov=json.loads((source/'PROVENANCE.json').read_text(encoding='utf-8'))
runtime=read('includes/runtime.php')
bridge=read('tools/print-runtime-worker.php')
setup=read('runtime/windows/setup-sokna.ps1')
build=read('runtime/windows/build-print-worker.ps1')
modules=read('includes/modules.php')
admin=read('admin/printing.php')
settings_js=read('assets/js/printing-settings.js')
printing=read('includes/printing.php')
decisions=read('docs/DECISIONS_FA.md')
reconcile=read('docs/architecture-migration-r2/PHASE7R_PRINTING_RECONCILIATION_FA.md')
service_program=read('runtime/print-worker/source/src/Sokna.PrintAgent.Service/Program.cs')
paths=read('runtime/print-worker/source/src/Sokna.PrintAgent.Core/AgentPaths.cs')
secret_store=read('runtime/print-worker/source/src/Sokna.PrintAgent.Core/SecretStore.cs')
setup_install=read('includes/setup_install.php')
setup_machine=read('tools/setup-machine.php')
repair_pairing=read('tools/provision-print-worker.php')

checks={
 'R2 contradiction is explicitly superseded': 'Superseded' in reconcile and 'محصول جدا نصب نمی‌شود' in reconcile,
 'user supplied 6.2.5 provenance is pinned': prov.get('upstream_version')=='6.2.5' and prov.get('upstream_archive_sha256')=='c9c205fe0efb08c5d3a270b1065b0e320489b0e7f3c8764d2295724fb01c102a',
 'operational source is internal': all((source/'src'/p).is_dir() for p in ['Sokna.PrintAgent.Core','Sokna.PrintAgent.Runtime','Sokna.PrintAgent.Service','Sokna.PrintAgent.Worker']),
 'standalone setup and control are not internal source owners': not (source/'src/Sokna.PrintAgent.Setup').exists() and not (source/'src/Sokna.PrintAgent.Control').exists() and not (source/'installer').exists(),
 'internal build produces only service and worker': "@('Service','Worker')" in build and 'Sokna.PrintAgent.Control' not in build and 'Setup.exe' not in build,
 'internal worker publish remains self-contained': '--self-contained true' in build and '--self-contained false' not in build,
 'internal service SCM name matches Setup exactly': 'ServiceName="SoknaPrintWorker"' in service_program and "$PrintWorkerServiceName = 'SoknaPrintWorker'" in setup,
 'internal data root belongs to SOKNA': 'SOFTWARE\\Sokna\\Local\\PrintWorker' in paths and '"SOKNA","print-worker"' in paths,
 'runtime owns canonical print worker service': 'sokna_runtime_print_worker_service_name' in runtime and 'SoknaPrintWorker' in runtime,
 'runtime bridge no longer describes external product installation': 'installed Print Agent' not in bridge and 'Print Agent service' not in bridge,
 'setup has no external Print Agent installer parameters': 'PrintAgentSetup' not in setup and 'PrintAgentSha256' not in setup,
 'setup installs bundled internal print worker': 'Install-PrintWorkerComponent' in setup and 'PrintWorkerBundle' in setup,
 'setup verifies exact Print Worker manifest hashes and rejects extra payload': 'Print Worker payload SHA256 mismatch' in setup and 'payload/manifest file count mismatch' in setup and 'Unexpected Print Worker payload file' in setup,
 'setup preserves service recovery and fresh health evidence': "@('failureflag',$PrintWorkerServiceName,'1')" in setup and 'fresh health.json within 20 seconds' in setup and 'health.json is incomplete' in setup,
 'pairing secret travels only through private provision file': 'print-worker-provision.private' in setup and "@('--provision-file',$ProvisionFile)" in setup and '--token' not in setup,
 'worker provisions machine-scoped DPAPI secret': '--provision-file' in service_program and 'SecretStore.Save' in service_program and 'DataProtectionScope.LocalMachine' in secret_store,
 'fresh and recovery owners both provision canonical DB identity': 'sokna_setup_write_internal_print_worker_provision($pdo,$input)' in setup_install and 'sokna_setup_write_internal_print_worker_provision($pdo, $input)' in setup_machine,
 'private provision file is cleanup-owned': '@($privateConfig,$privatePassphrase,$printWorkerProvisionFile)' in setup,
 'healthy Repair preserves pairing and regenerates only when missing': "$Mode -eq 'Repair' -and $needsRepairProvision" in setup and "config.json" in setup and "secret.dat" in setup and 'tools\\provision-print-worker.php' in setup,
 'legacy data migration fails closed instead of merging populated roots': 'Both legacy and internal Print Worker data roots contain state' in setup and '$legacyHasData -and $newHasData' in setup,
 'legacy migration copies hidden state and rollback removes migrated copy': 'Get-ChildItem -LiteralPath $legacyDataRoot -Force | Copy-Item' in setup and '$migratedLegacyData -and (Test-Path -LiteralPath $componentData)' in setup,
 'binary rollback recreates component root before restoring backup': 'New-Item $componentRoot -ItemType Directory -Force | Out-Null' in setup and 'Get-ChildItem -LiteralPath $backup -Force | Copy-Item -Destination $componentRoot -Recurse -Force' in setup,
 'repair pairing tool uses private output file not token arguments': '--output-file=' in repair_pairing and '--token=' not in repair_pairing and 'sokna_setup_write_internal_print_worker_provision' in repair_pairing,
 'active module registry names internal worker': 'سرویس چاپ داخلی سکنا' in modules and 'installed Windows Print Agent service' not in modules,
 'active printing UI has no external download flow': 'print_agent_download_url()' not in admin and 'print_agent_release_page_url()' not in admin,
 'active printing UI selects printers, never worker identities': 'name="agent_id"' not in admin and 'name="fallback_agent_id"' not in admin and 'data-print-agent-select' not in admin and 'data-print-agent-select' not in settings_js,
 'destination save is pinned to the canonical internal worker': 'print_internal_worker_agent($pdo,true)' in admin and 'print_internal_worker_agent_id' in printing,
 'setup normalizes active route ownership to the internal worker': 'print_internal_worker_agent_id' in setup_install and 'UPDATE print_destinations SET agent_id=CASE' in setup_install,
 'active printing owner does not resolve GitHub Agent releases': 'api.github.com/repos/' not in printing and 'mobaraki20/Pagent' not in printing,
 'active decisions match R2 internal worker': 'Print Worker داخلی' in decisions and 'پذیرش Windows جداگانه' not in decisions,
}
failed=[name for name,ok in checks.items() if not ok]
for name,ok in checks.items(): print(('PASS' if ok else 'FAIL')+': '+name)
if failed: raise SystemExit('Phase 7R internal Print Worker contract failed: '+', '.join(failed))
print(f'Phase 7R internal Print Worker contract PASS: {len(checks)} checks.')
