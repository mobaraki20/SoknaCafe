#!/usr/bin/env python3
from pathlib import Path
R=Path(__file__).resolve().parents[1]
read=lambda p:(R/p).read_text(encoding='utf-8')
runtime=read('includes/runtime.php')
bridge=read('tools/print-runtime-worker.php')
modules=read('includes/modules.php')
push_worker=read('tools/push-worker.php')
push=read('includes/push.php')
reconcile=read('docs/architecture-migration-r2/PHASE7R_PRINTING_RECONCILIATION_FA.md')
setup=read('runtime/windows/setup-sokna.ps1')

checks={
 'printing registered under Local Runtime': "'printing' => [" in runtime and 'tools/print-runtime-worker.php' in runtime,
 'runtime knows canonical internal Print Worker service': 'sokna_runtime_print_worker_service_name' in runtime and 'SoknaPrintWorker' in runtime,
 'bridge is CLI and Windows-SCM scoped': "PHP_SAPI !== 'cli'" in bridge and "PHP_OS_FAMILY !== 'Windows'" in bridge and "'sc.exe'" in bridge,
 'bridge recovers only stopped internal service': "'query'" in bridge and "'start'" in bridge and "state === 'STOPPED'" in bridge,
 'missing worker is internal component state': "'component_missing'" in bridge and '1060' in bridge,
 'bridge does not duplicate Print API/state machine': all(token not in bridge for token in ['print_jobs','print_attempts','print_claim_requests','print_v4_','StartDoc','Winspool','INSERT INTO','UPDATE ','DELETE FROM ']),
 'R2 internal ownership is active': 'محصول جدا نصب نمی‌شود' in reconcile and 'SOKNA Local' in reconcile,
 'setup installs bundled worker rather than external setup': 'Install-PrintWorkerComponent' in setup and 'PrintAgentSetup' not in setup and 'PrintAgentSha256' not in setup,
 'printing registry describes internal runtime ownership': 'سرویس چاپ داخلی سکنا -> Windows Spooler' in modules and 'installed Windows Print Agent service' not in modules,
 'notification processing is Runtime-owned': 'SOKNA Runtime -> tools/push-worker.php' in modules and "'push' => [" in runtime and 'tools/push-worker.php' in runtime,
 'notification outbox remains canonical': 'push_process_queue(10)' in push_worker and 'INSERT INTO push_event_queue' in push,
 'opportunistic notification drain remains accelerator only': 'latency accelerator/fallback' in modules,
}
failed=[name for name,ok in checks.items() if not ok]
for name,ok in checks.items(): print(('PASS' if ok else 'FAIL')+': '+name)
if failed: raise SystemExit('Phase 7 runtime services contract failed: '+', '.join(failed))
print(f'Phase 7 runtime services contract PASS: {len(checks)} checks.')
