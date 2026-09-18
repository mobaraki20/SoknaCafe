#!/usr/bin/env python3
from pathlib import Path
import re

root=Path(__file__).resolve().parents[1]
api=(root/'print-agent/v4/api.php').read_text(encoding='utf-8')
schema=(root/'database/schema.sql').read_text(encoding='utf-8')
migration=(root/'release/1.36.4-dev.23-print-claim-reconciliation.sql').read_text(encoding='utf-8')
health=(root/'includes/schema_health.php').read_text(encoding='utf-8')
errors=[]

def require(name, condition):
    if not condition: errors.append(name)

version=(root/'VERSION.txt').read_text().strip()
match=re.fullmatch(r'1\.36\.4-dev\.(\d+)', version)
require('version_at_least_dev23', bool(match) and int(match.group(1))>=23)
for token in ['response_snapshot_json']:
    require('schema_'+token, token in schema)
    require('migration_'+token, token in migration)
require('snapshot_capability', "'durable_claim_snapshot_v1'" in api)
require('rekey_capability', "'claim_conflict_rekey_v1'" in api)
require('snapshot_without_lease', "unset($item['attempt']['lease_token'])" in api)
require('lease_rederived', "$claimRequestId.':'.(string)$jobId" in api)
require('claim_stores_snapshot', 'SET attempt_ids_json=?,response_snapshot_json=?' in api)
require('replay_prefers_snapshot', "$savedRow['response_snapshot_json']" in api and 'print_v4_claim_snapshot_restore' in api)
require('unsafe_rekey_fails_closed', "'code'=>'claim_reconciliation_unsafe'" in api and "'requires_human_resolution'=>true" in api)
require('expired_unaccepted_is_recoverable', "in_array($oldState,['reserved','expired'],true)" in api and "$oldState==='expired'&&$jobState!=='pending'" in api)
require('old_attempt_is_preserved', "SET state='expired'" in api and "outcome='identity_collision_rekey'" in api)
require('replacement_above_local_ceiling', '$replacementId=max($highest,$localMaxAttemptId)+1;' in api)
require('no_payload_in_reconciliation_evidence', "'local_content_sha256_prefix'=>substr($localHash,0,16)" in api)
require('reconciliation_is_idempotent', 'print_claim_reconciliations r' in api and "hash_equals((string)($duplicateRow['request_hash']??''),$resolutionHash)" in api and "'idempotent'=>true" in api)
require('concurrent_retry_serialized_by_claim_lock', api.index('SELECT * FROM print_claim_requests WHERE agent_id=? AND request_id=? FOR UPDATE') < api.index('FROM print_claim_reconciliations r JOIN print_claim_requests'))
require('updater_schema_health', 'response_snapshot_json' in health)
require('per_attempt_reconciliation_ledger', 'CREATE TABLE IF NOT EXISTS print_claim_reconciliations' in schema and 'uq_print_claim_reconciliation_attempt' in schema and 'CREATE TABLE print_claim_reconciliations' in migration)
require('multi_conflict_claim_supported', 'INSERT INTO print_claim_reconciliations' in api and "if(!empty($claim['reconciliation_request_id']))" not in api)
require('job_attempts_locked', 'SELECT id,state FROM print_attempts WHERE job_id=? ORDER BY id FOR UPDATE' in api)
require('newer_or_active_attempt_fails_closed', '$siblingId>$attemptId' in api and "['reserved','claimed','started','unknown','recovery_hold']" in api)
require('expired_requires_pending', "$oldState==='expired'&&$jobState!=='pending'" in api)
require('reserved_requires_reserved', "$oldState==='reserved'&&$jobState!=='reserved'" in api)
require('snapshot_identity_is_preserved', "$item['attempt']['id']=$replacementId" in api and '$replacement=print_v4_attempt_response' not in api)
require('client_id_ceiling_is_bounded', 'PRINT_V4_RECONCILIATION_MAX_ID_GAP' in api and "'code'=>'attempt_id_gap_unsafe'" in api)
require('mismatch_is_server_verified', '$actualMismatch=[]' in api and "'code'=>'claim_mismatch_not_proven'" in api)
require('accept_job_rowcount_checked', '$acceptJob->rowCount()!==1' in api and "'code'=>'job_reservation_conflict'" in api)
require('ledger_schema_health', all(x in health for x in ['print_claim_reconciliation_ledger_table','print_claim_reconciliation_ledger_unique_request','print_claim_reconciliation_ledger_unique_attempt']))

if errors:
    print('FAIL print-dev23 Claim reconciliation contract: '+','.join(errors))
    raise SystemExit(1)
print('PASS print-dev23 Claim reconciliation contract')
