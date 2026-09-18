from pathlib import Path

root = Path(__file__).resolve().parents[1]
sql = (root / 'release' / '1.36.4-dev.26-print-preop-clean-baseline.sql').read_text(encoding='utf-8')
notes = (root / 'RELEASE_NOTES_1.36.4_DEV26_FA.md').read_text(encoding='utf-8')

ordered = [
    'DELETE FROM print_claim_reconciliations;',
    'DELETE FROM print_claim_requests;',
    'DELETE FROM print_attempts;',
    'UPDATE print_jobs SET reprint_of_id = NULL WHERE reprint_of_id IS NOT NULL;',
    'DELETE FROM print_jobs;',
]
pos = -1
for token in ordered:
    nxt = sql.find(token)
    assert nxt > pos, f'missing/out-of-order token: {token}'
    pos = nxt

# Updater 1.5.3 executes migration statements split by this exact delimiter.
assert sql.count('-- CAFE-STMT --') == len(ordered) - 1
parts = [x.strip() for x in sql.split('-- CAFE-STMT --')]
assert len(parts) == len(ordered)
for part, token in zip(parts, ordered):
    assert token in part

upper = sql.upper()
assert 'AUTO_INCREMENT' in upper  # policy comment must stay visible
assert 'ALTER TABLE' not in upper
assert 'TRUNCATE' not in upper
assert 'DELETE FROM PRINT_AGENTS' not in upper
assert 'DELETE FROM PRINT_DESTINATIONS' not in upper
assert 'DELETE FROM PRINT_TEMPLATES' not in upper
assert 'AUTO_INCREMENT=' not in upper.replace(' ', '')
assert 'Production' in notes or 'PRODUCTION' in notes
print('print-dev26-preop-clean-baseline-contract: PASS')
