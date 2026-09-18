#!/usr/bin/env python3
from pathlib import Path
R=Path(__file__).resolve().parents[1]

def t(p): return (R/p).read_text(encoding='utf-8')
api=t('print-agent/v4/api.php'); helpers=t('includes/print_agent_api.php'); printing=t('includes/printing.php'); admin=t('admin/printing.php'); schema=t('database/schema.sql'); migration=t('release/1.36.4-dev.21-print-failover.sql'); js=t('assets/js/printing-settings.js')
checks={
 'version': t('VERSION.txt').strip() in {'1.36.4-dev.21','1.36.4-dev.22'},
 'nullable_helpers': all(x in helpers for x in ['print_agent_api_optional_string_field','print_agent_api_optional_int_field','print_agent_api_optional_bool_field']),
 'heartbeat_nullable_use': all(x in api for x in ["print_agent_api_optional_string_field($data,'bridge_origin'", "print_agent_api_optional_int_field($data,'last_api_latency_ms'", "print_agent_api_optional_string_field($data,'bridge_pairing_id'"]),
 'heartbeat_authoritative': 'last_heartbeat_at=NOW()' in api and 'last_heartbeat_at DATETIME NULL' in schema,
 'claim_cannot_touch_heartbeat': api.count('last_heartbeat_at=NOW()')==1,
 'migration_no_backfill': 'last_seen_at' not in migration.lower() or 'do not backfill' in migration.lower(),
 'canonical_readiness': all(x in printing for x in ['function print_agent_runtime_readiness','heartbeat_missing','heartbeat_stale','discovery_missing','discovery_stale','queue_offline','queue_not_found']),
 'no_discovery_last_seen_fallback': "health['printer_discovery_at'] ?? $agent['last_seen_at']" not in printing,
 'full_route_runtime': all(x in printing for x in ['primary_last_heartbeat_at','primary_health_json','fallback_last_heartbeat_at','fallback_health_json']),
 'save_actionable': 'printing_queue_readiness_error' in admin and "if(!$qh['ready'])" in admin,
 'promote_transactional': True,
 'promote_ui': 'تبدیل مسیر جایگزین به اصلی' in admin and 'data-print-mutation' in admin,
 'retirement_live_route_block': 'هنوز در مسیر زنده استفاده می‌شود' in admin and 'SELECT destination_key,label FROM print_destinations' in admin,
 'retired_archive': 'آرشیو رایانه‌های چاپ' in admin and '$retiredAgents' in admin and '$routeEligibleAgents' in admin,
 'double_submit_guard': 'form[data-print-mutation]' in js,
 'runtime_job_readiness': all(x in printing for x in ["print_destination_ready($pdo, $destinationKey)", "print_destination_ready($pdo, $targetKey)", "print_destination_ready($pdo,$destinationKey)?null:'destination_unavailable'"]),
 'deterministic_lock_order': all(x in admin for x in ["SELECT destination_key FROM print_destinations WHERE active=1 ORDER BY destination_key FOR UPDATE", "ksort($agentSpecs,SORT_NUMERIC)", "sort($ids,SORT_NUMERIC)"]),
}
# repair the intentionally simple promote check above without syntax tricks
checks['promote_transactional']=all(x in admin for x in ["$action === 'promote_fallback'",'SELECT * FROM print_destinations WHERE destination_key=? FOR UPDATE','print_destination_fallback_promoted',"'before'=>$before", "'after'=>$after"])
failed=[k for k,v in checks.items() if not v]
if failed: raise SystemExit('FAIL '+','.join(failed))
print('PASS print-dev21-remediation-contract: '+','.join(checks))
