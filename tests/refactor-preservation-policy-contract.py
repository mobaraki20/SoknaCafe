#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

def read(rel: str) -> str:
    return (ROOT / rel).read_text(encoding='utf-8')

contract = read('docs/UI_BEHAVIOR_PRESERVATION_CONTRACT_FA.md')
root_policy = read('docs/ROOT_CAUSE_REFACTOR_POLICY_FA.md')
developer = read('DEVELOPER_READ_FIRST_FA.md')
audit = read('docs/PREOP_ROOT_CAUSE_AUDIT_1.36.3_FA.md')
handoff = read('docs/AI_HANDOFF/DEVELOPER_HANDOFF_1.36.3_FINAL_FA.md')

checks = {
    'preservation contract exists': 'قرارداد حفظ رابط و رفتار' in contract,
    'refactor is not redesign': 'Refactor با Redesign یکی نیست' in contract,
    'observable contract is protected': 'Contract مشاهده‌پذیر کاربر و عملیات' in contract,
    'one concern rule exists': 'One-Concern Rule' in contract,
    'stop-the-batch rule exists': 'Stop-the-Batch' in contract,
    'schema changes use stronger gate': 'Data Audit → Migration/Schema Change → Fresh Install' in contract,
    'explicit redesign approval required': 'REDESIGN_APPROVED' in contract and 'BEHAVIOR_CHANGE_APPROVED' in contract,
    'batch A is behavior preserving': 'Behavior-preserving cleanup' in contract,
    'root policy links preservation contract': 'UI_BEHAVIOR_PRESERVATION_CONTRACT_FA.md' in root_policy,
    'developer read-first links preservation contract': 'UI_BEHAVIOR_PRESERVATION_CONTRACT_FA.md' in developer,
    'audit freezes UI behavior for batch A': 'UI/Behavior Preservation Gate' in audit,
    'handoff links preservation contract': 'UI_BEHAVIOR_PRESERVATION_CONTRACT_FA.md' in handoff,
}

failed = [name for name, ok in checks.items() if not ok]
for name, ok in checks.items():
    print(('PASS' if ok else 'FAIL') + ': ' + name)
if failed:
    raise SystemExit('Refactor preservation policy contract failed: ' + ', '.join(failed))
print('PASS: refactor UI/behavior preservation policy contract')
