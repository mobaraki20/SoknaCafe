from pathlib import Path
root=Path(__file__).resolve().parents[1]
v4=(root/'print-agent/v4/api.php').read_text(encoding='utf-8') if (root/'print-agent/v4/api.php').exists() else ''
errors=[]
for token in ["recovery_hold","unknown","print_attempts","local_receipt_id","spooler_job_id","physical_print_confirmed","resolved_at"]:
    if token not in v4: errors.append(f'v4 missing {token}')
if "status IN('failed','unknown','recovery_hold') AND resolved_at IS NOT NULL" not in v4:
    errors.append('resolved ambiguity does not release FIFO fence')
if errors:
    print('FAIL submission_ambiguity'); [print(' - '+e) for e in errors]; raise SystemExit(1)
print('PASS submission_ambiguity')
