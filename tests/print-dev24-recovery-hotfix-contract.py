#!/usr/bin/env python3
from pathlib import Path
root=Path(__file__).resolve().parents[1]
api=(root/'print-agent/v4/api.php').read_text(encoding='utf-8')
printing=(root/'includes/printing.php').read_text(encoding='utf-8')
admin=(root/'admin/printing.php').read_text(encoding='utf-8')
errors=[]
def req(name, cond):
    if not cond: errors.append(name)
req('legacy_snapshot_helper', 'function print_v4_rebuild_legacy_claim_snapshot' in api)
req('reconcile_hydrates_legacy_snapshot', 'Legacy dev.22-and-earlier claims' in api and 'print_v4_rebuild_legacy_claim_snapshot($pdo,$agentId,$attemptIds,$claimRequestId)' in api)
req('legacy_replay_uses_same_owner', 'print_v4_rebuild_legacy_claim_snapshot($pdo,$agentId,$attemptIds,$requestId)' in api)
req('no_live_destination_for_legacy_snapshot', 'print_v4_attempt_response($row,[],$leaseToken)' in api)
req('safe_reserved_cancel_owner', 'function print_job_cancel_unprinted' in printing and "['pending','blocked','failed','reserved']" in printing)
req('reserved_cancel_locks_attempt', "state='cancelled'" in printing and "outcome='cancelled_by_admin'" in printing)
req('ambiguous_no_longer_needed', 'human_no_longer_needed' in printing and 'resolve_unknown_no_longer_needed' in admin)
req('ui_no_longer_needed', 'دیگر نیاز به چاپ نیست' in admin)
if errors:
    print('FAIL print dev24 recovery hotfix contract: '+','.join(errors))
    raise SystemExit(1)
print('PASS print dev24 recovery hotfix contract')
