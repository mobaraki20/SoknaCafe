#!/usr/bin/env python3
from pathlib import Path
R=Path(__file__).resolve().parents[1]
read=lambda p:(R/p).read_text(encoding='utf-8')
admin=read('admin/printing.php'); v4=read('print-agent/v4/api.php'); printing=read('includes/printing.php')
assert "true, 303" in admin and "printing_redirect($tab);" in admin
assert "printing_issued_token" in admin and "$_SESSION" in admin
assert 'print_job_safe_retry' in admin and 'تلاش دوباره' in admin and 'لغو درخواست' in admin
assert 'resolve_unknown_printed' in admin and 'resolve_unknown_reprint' in admin and 'چاپ مجدد مستقل' in admin
assert 'PRINT_V4_MAX_ATTEMPTS = 5' in v4 and 'reservation_retry_exhausted' in v4
assert "status==='unknown'||$status==='recovery_hold'" in v4 and 'requires_human_resolution' in v4
assert 'نیازمند رسیدگی' in admin and 'فعالیت اخیر' in admin and 'جزئیات و سابقه چاپ' in admin
assert 'print_job_error_human' in printing and 'print_job_create_reprint' in printing
assert 'print_job_hold_stale_ownership' in printing and 'hold_stale_ownership' in admin and 'mark_claim_unknown' not in admin
assert not (R/'print-agent/api.php').exists()
print('Print operations contract PASS: PRG, bounded safe retry, ambiguity resolution, independent reprint and exception-first UI.')
