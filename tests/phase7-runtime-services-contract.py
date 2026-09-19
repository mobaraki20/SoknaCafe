#!/usr/bin/env python3
from pathlib import Path
R=Path(__file__).resolve().parents[1]
read=lambda p:(R/p).read_text(encoding='utf-8')
runtime=read('includes/runtime.php')
bridge=read('tools/print-runtime-worker.php')
modules=read('includes/modules.php')
push_worker=read('tools/push-worker.php')
push=read('includes/push.php')
distribution=read('docs/PRINT_AGENT_DISTRIBUTION_CONTRACT_FA.md')

checks={
 'printing registered under Local Runtime': "'printing' => [" in runtime and 'tools/print-runtime-worker.php' in runtime,
 'runtime knows canonical Agent service name': 'sokna_runtime_print_agent_service_name' in runtime and 'Sokna Print Agent 6' in runtime,
 'bridge is CLI and Windows-SCM scoped': "PHP_SAPI !== 'cli'" in bridge and "PHP_OS_FAMILY !== 'Windows'" in bridge and "'sc.exe'" in bridge,
 'bridge can recover only a stopped installed service': "'query'" in bridge and "'start'" in bridge and "state === 'STOPPED'" in bridge,
 'bridge treats missing Agent as install state not business failure': "'not_installed'" in bridge and r'\b1060\b' in bridge,
 'bridge does not duplicate Print API/state machine': all(token not in bridge for token in ['print_jobs','print_attempts','print_claim_requests','print_v4_','StartDoc','Winspool','INSERT INTO','UPDATE ','DELETE FROM ']),
 'stable Agent distribution remains external': 'mobaraki20/Pagent' in distribution and 'Stable GitHub Release Asset' in distribution and 'Cafe binary را mirror نمی‌کند' in distribution,
 'printing registry describes Runtime supervision': 'SOKNA Runtime -> tools/print-runtime-worker.php -> installed Windows Print Agent service' in modules,
 'notification processing is Runtime-owned': 'SOKNA Runtime -> tools/push-worker.php' in modules and "'push' => [" in runtime and 'tools/push-worker.php' in runtime,
 'notification outbox remains canonical': 'push_process_queue(10)' in push_worker and 'INSERT INTO push_event_queue' in push,
 'opportunistic notification drain remains accelerator only': 'latency accelerator/fallback' in modules,
}
failed=[name for name,ok in checks.items() if not ok]
for name,ok in checks.items():
    print(('PASS' if ok else 'FAIL')+': '+name)
if failed:
    raise SystemExit('Phase 7A runtime services contract failed: '+', '.join(failed))
print(f'Phase 7A runtime services contract PASS: {len(checks)} checks.')
