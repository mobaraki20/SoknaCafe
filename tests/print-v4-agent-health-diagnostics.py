#!/usr/bin/env python3
from pathlib import Path
root=Path(__file__).resolve().parents[1]
api=(root/'print-agent/v4/api.php').read_text(encoding='utf-8')
admin=(root/'admin/printing.php').read_text(encoding='utf-8')
css=(root/'assets/css/panel-components.css').read_text(encoding='utf-8')
standards=(root/'docs/STANDARDS_REGISTER_FA.md').read_text(encoding='utf-8')
architecture=(root/'docs/PRINT_V4_ARCHITECTURE_FA.md').read_text(encoding='utf-8')
required=['last_successful_action','last_api_success_at','last_api_error_code','consecutive_api_failures','last_api_latency_ms']
for token in required:
    assert token in api, f'heartbeat missing {token}'
    assert token in admin, f'admin diagnostics missing {token}'
assert 'health_json' in api and 'last_heartbeat_at=NOW()' in api
assert 'printer_discovery_at' in api and 'last_heartbeat_at' in admin
assert 'panel-diagnostic-grid' in admin and '.panel-diagnostic-grid' in css
assert 'printing_agent_health' in admin and "'degraded'" in admin and "'attention'" in admin
assert 'Agent Health Diagnostics' in standards
assert 'Transport health / Diagnostics' in architecture
assert 'lease_token' not in ''.join([line for line in admin.splitlines() if 'panel-diagnostic' in line])
print('PASS print-v4 Agent health diagnostics contract')
