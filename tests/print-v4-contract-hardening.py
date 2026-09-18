#!/usr/bin/env python3
from pathlib import Path
import re
R=Path(__file__).resolve().parents[1]
read=lambda p:(R/p).read_text(encoding='utf-8')
v4=read('print-agent/v4/api.php')
printing=read('includes/printing.php')
admin=read('admin/printing.php')
helpers=read('includes/print_agent_api.php')
schema=read('database/schema.sql')
checks={
 'durable_claim_request_ledger': all(x in schema+v4 for x in ['print_claim_requests','attempt_ids_json','INSERT IGNORE INTO print_claim_requests']),
 'empty_claim_replay_is_stable': "json_encode($claimedAttemptIds" in v4 and "attempt_ids_json IS NULL" in v4,
 'empty_claim_retention_bounded': 'PRINT_V4_EMPTY_CLAIM_RETENTION_SECONDS = 7 * 24 * 60 * 60' in v4 and 'print_v4_cleanup_empty_claim_requests' in v4,
 'empty_claim_cleanup_preserves_incomplete_and_nonempty': 'attempt_ids_json IS NOT NULL AND JSON_LENGTH(attempt_ids_json)=0' in v4,
 'claim_replay_not_reserved_only': "claim_request_id=? AND a.state='reserved'" not in v4,
 'destination_snapshot_persisted': 'destination_snapshot_json' in schema+v4 and 'print_v4_destination_snapshot' in v4,
 'destination_snapshot_missing_fails_closed': "throw new RuntimeException('Print Attempt destination snapshot is missing or invalid.');" in v4 and 'return print_v4_destination_snapshot($fallback);' not in v4,
 'destination_snapshot_failure_is_explicit': "'code'=>'destination_snapshot_invalid'" in v4 and 'ادامه خودکار برای جلوگیری از چاپ اشتباه متوقف شد' in v4,
 'start_terminal_metadata': all(x in v4 for x in ["'current_state'=>$currentState","'terminal'=>$terminal","'requires_human_resolution'=>$requiresHuman"]),
 'late_submitted_requires_started': "$lateSubmitted=$status==='submitted'" in v4 and "!empty($attempt['started_at'])" in v4,
 'late_submitted_requires_unresolved': "empty($attempt['job_resolved_at'])" in v4,
 'late_submitted_same_receipt': "hash_equals((string)$attempt['local_receipt_id'],$localReceipt)" in v4,
 'late_submitted_spooler_required': "$spooler!==''" in v4,
 'reserved_expiry_only': "WHERE a.state='reserved'" in v4 and "print_v4_expire_reservations($pdo);" in v4,
 'claimed_started_not_reassigned': "if((string)$job['status']!=='pending')break;" in v4,
 'admin_stale_claimed_hold': 'print_job_hold_stale_ownership' in printing and "$target=$attemptState==='started'?'unknown':'recovery_hold';" in printing,
 'admin_stale_age_enforced_server_side': 'TIMESTAMPDIFF(SECOND,claimed_at,NOW()) claimed_age_seconds' in printing and 'print_job_stale_ownership_threshold_seconds()' in printing and 'این درخواست هنوز در بازه طبیعی دریافت است؛ برای جلوگیری از قطع چاپ سالم کمی صبر کنید.' in printing and 'time()-print_job_stale_ownership_threshold_seconds()' in admin,
 'reserved_not_admin_ambiguity': 'mark_claim_unknown' not in admin and 'hold_stale_ownership' in admin and "$status==='claimed'" in admin,
 'request_id_cross_attempt_conflict': 'print_v4_assert_request_id_not_reused' in v4 and 'request_id_conflict' in v4,
 'unknown_no_auto_retry': "if($status==='unknown'||$status==='recovery_hold')" in v4 and "next_attempt_at=NULL" in v4,
 'submitted_not_physical_confirmation': "'physical_print_confirmed'=>false" in v4,
 'https_forwarded_proto_requires_trusted_proxy': "$trustProxy=(bool)($config['app']['trust_proxy_headers']??false);" in helpers and "if($trustProxy && isset($_SERVER['HTTP_X_FORWARDED_PROTO']))" in helpers,
}
failed=[k for k,v in checks.items() if not v]
if failed:
    print('FAIL print_v4_contract_hardening: '+','.join(failed))
    raise SystemExit(1)
print('PASS print_v4_contract_hardening: '+','.join(checks))
