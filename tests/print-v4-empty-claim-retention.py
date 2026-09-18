#!/usr/bin/env python3
from pathlib import Path
R=Path(__file__).resolve().parents[1]
v4=(R/'print-agent/v4/api.php').read_text(encoding='utf-8')
preflight=(R/'tools/print-v4-staging-preflight.php').read_text(encoding='utf-8')
checks={
    'retention_is_seven_days': 'PRINT_V4_EMPTY_CLAIM_RETENTION_SECONDS = 7 * 24 * 60 * 60' in v4,
    'cleanup_is_batched': 'PRINT_V4_EMPTY_CLAIM_CLEANUP_BATCH = 2000' in v4 and 'ORDER BY id LIMIT' in v4,
    'only_completed_empty_claims_are_deleted': 'attempt_ids_json IS NOT NULL AND JSON_LENGTH(attempt_ids_json)=0' in v4,
    'incomplete_claims_are_preserved': 'attempt_ids_json IS NOT NULL' in v4,
    'nonempty_claims_are_preserved': 'JSON_LENGTH(attempt_ids_json)=0' in v4,
    'cleanup_is_age_bounded': "created_at<?" in v4 and "time()-PRINT_V4_EMPTY_CLAIM_RETENTION_SECONDS" in v4,
    'cleanup_failure_cannot_block_claim': 'catch(Throwable)' in v4 and 'cleanup failure must never block printing' in v4,
    'cleanup_is_off_claim_fast_path': v4.find('print_v4_cleanup_empty_claim_requests($pdo);', v4.find("if($action==='claim')")) == -1,
    'cleanup_runs_on_heartbeat_before_its_transaction': v4.find('print_v4_cleanup_empty_claim_requests($pdo);', v4.find("if($action==='heartbeat')")) < v4.find("$pdo->beginTransaction();", v4.find("if($action==='heartbeat')")),
    'empty_claim_replay_remains_durable': 'INSERT IGNORE INTO print_claim_requests' in v4 and "json_encode($claimedAttemptIds" in v4,
    'preflight_reports_empty_growth': 'empty_claim_rows_total' in preflight and 'empty_claim_rows_expired_7d' in preflight,
}
failed=[k for k,v in checks.items() if not v]
if failed:
    print('FAIL print_v4_empty_claim_retention: '+','.join(failed))
    raise SystemExit(1)
print('PASS print_v4_empty_claim_retention: '+','.join(checks))
