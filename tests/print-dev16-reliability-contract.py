from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def read(p): return (ROOT/p).read_text(encoding='utf-8')
printing=read(Path('includes/printing.php')); api=read(Path('print-agent/v4/api.php')); admin=read(Path('admin/printing.php'))
templates=read(Path('admin/print_templates.php')); designer=read(Path('assets/js/print-template-designer.js')); push=read(Path('assets/js/push-runtime.js'))
checks={
'web_version': read(Path('VERSION.txt')).strip() in {'1.36.4-dev.16','1.36.4-dev.17','1.36.4-dev.18','1.36.4-dev.19','1.36.4-dev.20','1.36.4-dev.21','1.36.4-dev.22'},
'retry_cycles': all(x in printing for x in ['retry_cycle_started_at','print_job_safe_retry','retry_cycle_started_by_user_id']),
'api_attempt_status': "if($action==='attempt_status')" in api,
'utc_wire': 'print_v4_wire_time' in api and "new DateTimeZone('UTC')" in api,
'strict_hash': "print_agent_api_string_field($data,'content_sha256',128)" in api,
'retire_agent': 'print_agent_retired' in admin and 'retired_at' in read(Path('database/schema.sql')),
'area_lock_scope': "destination_type='preparation' ORDER BY destination_key FOR UPDATE" in admin,
'problem_owner': 'function print_open_problem_sql' in printing,
'live_snapshot': 'data-print-live-status' in admin and 'print_status_snapshot.php' in read(Path('includes/modules.php')),
'template_origin': all(x in templates for x in ["'imported'","'custom'",'active_guard']),
'legacy_compact_removed': 'quantity-first' in templates and 'compact-list' not in templates,
'exact_preview': all(x in designer for x in ['print.preview','image_base64','پیش‌نمایش دقیق','پیش‌نمایش تقریبی']),
'local_wake': all(x in push for x in ['127.0.0.1','print.wake','targetAddressSpace']),
'capability_endpoint': 'api/print_bridge_capability.php' in read(Path('includes/modules.php')),
'no_server_spooler': 'Winspool' not in printing,
}
failed=[k for k,v in checks.items() if not v]
if failed: raise SystemExit('FAIL '+','.join(failed))
print('PASS print-dev16-reliability-contract: '+','.join(checks))
