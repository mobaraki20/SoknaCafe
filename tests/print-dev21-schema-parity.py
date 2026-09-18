#!/usr/bin/env python3
from pathlib import Path
r=Path(__file__).resolve().parents[1]
schema=(r/'database/schema.sql').read_text(encoding='utf-8')
mig=(r/'release/1.36.4-dev.21-print-failover.sql').read_text(encoding='utf-8')
checks={
 'schema_column':'last_heartbeat_at DATETIME NULL' in schema,
 'migration_column':'ADD COLUMN last_heartbeat_at DATETIME NULL' in mig,
 'schema_index':'idx_print_agents_active_heartbeat (active,retired_at,last_heartbeat_at)' in schema,
 'migration_index':'idx_print_agents_active_heartbeat (active,retired_at,last_heartbeat_at)' in mig,
 'no_backfill':not any(x in mig.lower() for x in ['update print_agents set last_heartbeat_at','last_heartbeat_at=last_seen_at','last_heartbeat_at = last_seen_at']),
}
bad=[k for k,v in checks.items() if not v]
if bad: raise SystemExit('FAIL '+','.join(bad))
print('PASS print-dev21 schema/migration parity: '+','.join(checks))
