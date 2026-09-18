#!/usr/bin/env python3
from pathlib import Path
R=Path(__file__).resolve().parents[1]
api=(R/'print-agent/v4/api.php').read_text(encoding='utf-8')
helper=(R/'includes/print_agent_api.php').read_text(encoding='utf-8')
registry=(R/'tests/defect_class_registry.json').read_text(encoding='utf-8')
checks={
    'supported_agent_receipt_contract': "preg_match('/^r-[A-Fa-f0-9]{32}$/', $receipt) === 1" in helper,
    'accept_uses_shared_receipt_validator': 'print_agent_api_local_receipt_id_valid($localReceipt)' in api,
    'sha256_shape_has_owner': "preg_match('/^[A-Fa-f0-9]{64}$/', $hash) === 1" in helper,
    'receipt_error_is_specific': "'code'=>'invalid_local_receipt_id'" in api,
    'hash_shape_error_is_specific': "'code'=>'invalid_content_sha256'" in api,
    'hash_mismatch_error_is_specific': "'code'=>'content_hash_mismatch'" in api,
    'ambiguous_accept_error_removed': "'code'=>'accept_validation_failed'" not in api,
    'escaped_defect_registered': 'print_agent_receipt_contract_parity' in registry,
}
failed=[k for k,v in checks.items() if not v]
if failed:
    raise SystemExit('Print Agent 6.1 accept contract FAIL: '+', '.join(failed))
print('Print Agent 6.1 accept contract PASS:', ', '.join(checks))
