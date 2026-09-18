from pathlib import Path
api=Path(__file__).resolve().parents[1]/'print-agent/v4/api.php'
s=api.read_text(encoding='utf-8')
checks={
 'spooler_nullable': "$spooler=print_agent_api_optional_string_field($data,'spooler_job_id',96)??'';" in s,
 'error_code_nullable': "$errorCode=print_agent_api_optional_string_field($data,'error_code',80)??'';" in s,
 'error_message_nullable': "$errorMessage=print_agent_api_optional_string_field($data,'error_message',500)??'';" in s,
 'submitted_still_requires_spooler': "$status==='submitted'&&$spooler===''" in s and 'spooler_job_id_required' in s,
}
failed=[k for k,v in checks.items() if not v]
if failed:
    raise SystemExit('FAIL: '+','.join(failed))
print('PASS', ','.join(checks))
