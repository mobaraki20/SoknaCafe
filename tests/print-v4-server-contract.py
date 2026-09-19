from pathlib import Path
root=Path(__file__).resolve().parents[1]
printing=(root/'includes/printing.php').read_text(encoding='utf-8')
v4=(root/'print-agent/v4/api.php').read_text(encoding='utf-8')
schema=(root/'database/schema.sql').read_text(encoding='utf-8')
errors=[]
for token in ['required','blocked_reason','content_sha256','local_receipt_id','resolution_state','print_attempts']:
 if token not in schema: errors.append('schema missing '+token)
for token in ['print_claim_requests','destination_snapshot_json']:
 if token not in schema: errors.append('schema missing '+token)
for token in ['print_enqueue_prep_order','required = false','print_destination_block_reason','print_job_create_reprint','print_job_resolve_ambiguous']:
 if token not in printing: errors.append('printing owner missing '+token)
for token in ["action==='claim'","action==='accept'","action==='start'","action==='report'","action==='renew'",'PRINT_V4_MAX_ATTEMPTS','content_sha256','physical_print_confirmed','recovery_hold']:
 if token not in v4: errors.append('v4 api missing '+token)
# Required preparation paths must not use best-effort wrapper.
for f in ['includes/functions.php','staff/api_quick_order.php','operator/api_bill.php']:
 txt=(root/f).read_text(encoding='utf-8')
 if f=='staff/api_quick_order.php': txt += '\n' + (root/'includes/staff_order_service.php').read_text(encoding='utf-8')
 if 'print_enqueue_prep_' not in txt: errors.append(f+' missing prep enqueue')
# Pre-launch clean baseline is v4-only.
if (root/'print-agent/api.php').exists(): errors.append('legacy v3 agent api still present')
if (root/'migrations/1.33.0-print-agent-v4.sql').exists() or (root/'migrations/20260818-print-v4-contract-hardening.sql').exists(): errors.append('pre-release v4 upgrade migrations still present')
if errors:
 print('FAIL print_v4_server_contract'); [print(' - '+x) for x in errors]; raise SystemExit(1)
print('PASS print_v4_server_contract')
