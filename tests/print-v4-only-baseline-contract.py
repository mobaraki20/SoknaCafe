#!/usr/bin/env python3
from pathlib import Path
R=Path(__file__).resolve().parents[1]
read=lambda p:(R/p).read_text(encoding='utf-8')
schema=read('database/schema.sql')
v4=read('print-agent/v4/api.php')
admin=read('admin/printing.php')
printing=read('includes/printing.php')
setup_install=read('includes/setup_install.php')
preflight=read('tools/print-v4-staging-preflight.php')
checks={
 'legacy_v3_runtime_removed': not (R/'print-agent/api.php').exists(),
 'agent_source_not_bundled': not (R/'print-agent-v6').exists(),
 'agent_binary_not_bundled': not list((R/'print-agent').glob('Sokna-Print-Agent-*')),
 'single_v4_server_endpoint': (R/'print-agent/v4/api.php').is_file(),
 'no_legacy_agent_protocol_column': '\n    protocol_version ' not in schema,
 'admin_has_no_legacy_protocol_ui': all(x not in admin for x in ['v3 legacy','Protocol قدیمی','legacyAssignedDestinations','protocol_version']),
 'new_internal_worker_identity_has_no_protocol_discriminator': 'INSERT INTO print_agents(name,token_hash,token_hint,active)' in setup_install and 'protocol_version' not in setup_install,
 'v4_heartbeat_does_not_write_protocol_column': 'protocol_version=4' not in v4,
 'single_minimum_agent_version': "function print_agent_minimum_version(): string" in printing and "print_worker_component_metadata()['version']" in printing and "$version = '6.2.5';" in printing and 'print_agent_v4_minimum_version' not in printing+v4,
 'full_v4_table_gate': "'print_agents','print_destinations','print_jobs','print_attempts','print_claim_requests'" in printing,
 'preflight_is_v4_baseline_not_cutover': 'ready_for_v4_baseline' in preflight and 'active_legacy_' not in preflight and 'protocol_version' not in preflight,
 'preflight_read_only': "'mode' => 'read_only'" in preflight and not any(x in preflight.upper() for x in ['UPDATE PRINT_','DELETE FROM PRINT_','INSERT INTO PRINT_','ALTER TABLE','CREATE TABLE']),
 'pre_release_v4_upgrade_migrations_removed': not (R/'migrations/1.33.0-print-agent-v4.sql').exists() and not (R/'migrations/20260818-print-v4-contract-hardening.sql').exists(),
}
failed=[k for k,v in checks.items() if not v]
if failed:
 print('FAIL print_v4_only_baseline_contract: '+','.join(failed)); raise SystemExit(1)
print('PASS print_v4_only_baseline_contract: '+','.join(checks))
