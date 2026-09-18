from pathlib import Path
root=Path(__file__).resolve().parents[1]
schema=(root/'database/schema.sql').read_text(encoding='utf-8')
errors=[]
if 'CREATE TABLE IF NOT EXISTS print_attempts' not in schema:
    errors.append('durable print_attempts history is missing')
if 'content_sha256' not in schema:
    errors.append('print payload content hash is missing')
if 'local_receipt_id' not in schema:
    errors.append('durable local receipt identity is missing')
if errors:
    print('FAIL print_attempt_model')
    [print(' - '+e) for e in errors]
    raise SystemExit(1)
print('PASS print_attempt_model')
