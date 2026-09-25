from pathlib import Path
import re
ROOT=Path(__file__).resolve().parents[1]
def read(p): return (ROOT/p).read_text(encoding='utf-8')
printing=read(Path('includes/printing.php')); api=read(Path('print-agent/v4/api.php')); admin=read(Path('admin/printing.php'))
templates=read(Path('admin/print_templates.php')); designer=read(Path('assets/js/print-template-designer.js')); push=read(Path('assets/js/push-runtime.js'))
version=read(Path('VERSION.txt')).strip()
version_match=re.fullmatch(r'1\.36\.4-dev\.(\d+)', version)
checks={
'web_version': bool(version_match) and int(version_match.group(1)) >= 16,
'retry_cycles': all(x in printing for x in ['retry_cycle_started_at','print_job_safe_retry','retry_cycle_started_by_user_id']),
'api_attempt_status': "if($action==='attempt_status')" in api,
'utc_wire': 'print_v4_wire_time' in api and "new DateTimeZone('UTC')" in api,
'strict_hash': "print_agent_api_string_field($data,'content_sha256',128)" in api,
'retired_identity_guard': 'retiredAgents' in admin and 'retired_at' in read(Path('database/schema.sql')) and 'قابل فعال‌سازی، تعویض کلید یا انتخاب در مسیر نیستند' in admin,
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
