#!/usr/bin/env python3
from pathlib import Path
import hashlib, json, os, re, sys
ROOT=Path(__file__).resolve().parents[1]
EXPECTED_WORKER_ARCHIVE='c9c205fe0efb08c5d3a270b1065b0e320489b0e7f3c8764d2295724fb01c102a'
checks=[]
def check(name,cond,detail=''):
    checks.append((name,bool(cond),detail))
def text(rel): return (ROOT/rel).read_text(encoding='utf-8')

version=text('VERSION.txt').strip(); sw=text('service-worker.js'); schema=text('database/schema.sql'); api=text('print-agent/v4/api.php')
printing=text('includes/printing.php'); agent_api=text('includes/print_agent_api.php'); bridge=text('api/print_bridge_capability.php')
push=text('assets/js/push-runtime.js'); designer=text('assets/js/print-template-designer.js'); templates=text('admin/print_templates.php')
admin=text('admin/printing.php'); snapshot=text('api/print_status_snapshot.php'); migration=text('release/1.36.4-dev.20-print-web.sql')
setup=text('runtime/windows/setup-sokna.ps1'); provenance=json.loads(text('runtime/print-worker/source/PROVENANCE.json'))

version_match=re.fullmatch(r'1\.36\.4-dev\.(\d+)', version)
check('target_version', bool(version_match) and int(version_match.group(1))>=20, version)
check('service_worker_release', f"const RELEASE='{version}'" in sw and f"cafe-staff-v{version}" in sw)
check('internal_worker_provenance', provenance.get('upstream_version')=='6.2.5' and provenance.get('upstream_archive_sha256')==EXPECTED_WORKER_ARCHIVE and provenance.get('component')=='SOKNA Local internal Print Worker', str(provenance.get('upstream_archive_sha256','')))
check('schema_bridge_runtime',all(x in schema for x in ['bridge_protocol_version','bridge_pairing_id','bridge_runtime_seen_at']))
check('schema_request_hashes',all(x in schema for x in ['accept_request_hash','start_request_hash','renew_request_hash','report_request_hash','request_hash CHAR(64) NOT NULL']))
check('schema_admin_action_identity',all(x in schema for x in ['last_admin_action_id','last_admin_action_type','last_admin_action_hash']))
check('migration_bridge_runtime',all(x in migration for x in ['ALTER TABLE print_agents','bridge_runtime_seen_at']))
check('migration_request_hashes',all(x in migration for x in ['ALTER TABLE print_attempts','accept_request_hash','report_request_hash','ALTER TABLE print_claim_requests','request_hash']))
check('request_body_fingerprint',all(x in api for x in ['print_v4_request_hash(','request_body_conflict','accept_request_hash','report_request_hash']))
check('attempt_status_mismatch_fail_closed',"if(!$receiptMatches && $receiptStored!=='')$next='reconcile'" in api)
check('expired_reserved_not_print_authority',"elseif($state==='reserved'&&$leaseExpired)$next='reconcile'" in api and "'terminal'=>$terminal||$leaseExpired" in api)
check('submitted_requires_spooler',"$status==='submitted'&&$spooler===''" in api and 'spooler_job_id_required' in api)
check('terminal_evidence_exact',"$sameEvidence=$existingSpooler===$spooler" in api)
check('transient_db_classification',all(x in api for x in ['print_v4_db_transient','db_transient','Retry-After: 1']))
check('strict_field_types',all(x in agent_api for x in ['print_agent_api_int_field','if(!is_int($data[$key]))','print_agent_api_bool_field','if(!is_bool($data[$key]))']))
check('heartbeat_report_backlog',all(x in api for x in ['pending_report_count','auth_blocked_report_count','reconciliation_report_count']))
check('bridge_secret_not_health_json',"'bridge_pairing_id'" not in re.search(r"\$health=\[(.*?)\];",api,re.S).group(1) if re.search(r"\$health=\[(.*?)\];",api,re.S) else False)
# spell this check without invalid Python expression syntax from PHP token
check('bridge_runtime_fresh', 'bridge_runtime_seen_at' in bridge and 'time()-$seen<=45' in bridge and "'exact_preview_ready'=>false" in bridge)
check('exact_preview_fail_closed',"'render_profile'=>null" in bridge and 'render_profile_unavailable' in bridge and 'dpi:203' not in designer)
check('preview_session_revision',all(x in designer for x in ['const revision=++exactRevision','exactController?.abort()','session_id:exactSessionId','dpi_x:Number(profile.dpi_x)','dpi_y:Number(profile.dpi_y)']))
check('wake_coalescing_deadline',all(x in push for x in ['pendingPrintWake','mergeWake','fetchWithDeadline','queueMicrotask(()=>void drainPrintWake())']))
check('wake_commit_check',all(x in printing for x in ['$pdo->inTransaction()','SELECT id,destination_key,status FROM print_jobs','status=\'pending\'']))
check('admin_action_idempotency',all(x in printing for x in ['print_admin_action_id','print_admin_action_hash','print_admin_action_is_replay','last_admin_action_id']))
check('legacy_worker_takeover_policy', all(x in setup for x in ['Both legacy and internal Print Worker data roots contain state. Automatic merge is unsafe', "Stop-Service $LegacyPrintWorkerServiceName", "Internal Print Worker RUNNING. legacy_data_migrated=", "config',$LegacyPrintWorkerServiceName,'start=','disabled'"]))
check('safe_import_limits',all(x in printing+templates for x in ['1048576','786432','array_key_exists($name,$files)','JSON object']))
check('snapshot_utc', 'print_database_time_to_utc' in snapshot)
check('agent_binary_not_bundled', not any((ROOT/'print-agent').glob('Sokna-Print-Agent-*')))
check('single_print_api_owner', (ROOT/'print-agent/v4/api.php').is_file() and not (ROOT/'print-agent/v3').exists())

# Remove duplicate named check if it arose from the explicit form above.
seen=set(); unique=[]
for row in checks:
    if row[0] in seen: continue
    seen.add(row[0]); unique.append(row)
checks=unique
failed=[x for x in checks if not x[1]]
for name,ok,detail in checks:
    print(('PASS' if ok else 'FAIL'),name,detail)
if failed:
    print(f'FAILURES={len(failed)}')
    sys.exit(1)
print(f'PASS print-dev20-contract assertions={len(checks)}')
