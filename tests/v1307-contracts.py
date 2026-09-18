#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')

bt=read('includes/business_time.php')
settings=read('admin/settings.php')
ops=read('admin/operations_report.php')
analytics=read('admin/analytics.php')
funcs=read('includes/functions.php')
move=read('operator/api_table_session.php')
orders=read('operator/api_orders.php')
operator=read('assets/js/operator.js')
layout=read('includes/panel_layout.php')
choice=read('assets/js/panel-choice.js')
validation=read('assets/js/panel-validation.js')
timepicker=read('assets/js/panel-time-picker.js')
login=read('login.php')
sw=read('service-worker.js')

# Dynamic shift UX + stable identity.
assert 'business_new_shift_key' in bt
assert 'business_used_shift_keys' in bt
assert 'business_report_shift_options' in bt and 'business_report_shift_identities' in bt
assert "if (count($shifts) < 2) return $options" in bt
assert "business_shift_key<>'outside'" in bt
assert 'این شیفت با یک شیفت قدیمی تداخل دارد' in settings
assert 'businessShiftTemplate' in settings and 'افزودن شیفت' in settings
assert "rows.length>=3" in settings and "rows.length<=1" in settings
assert 'shift_key[]' in settings and 'data-shift-key' in settings
assert 'business-time-row' not in settings  # old hour/minute paired selects are gone from markup

# Dedicated time owner; generic select/validation label extraction must not consume option text.
assert "input.form-control[type=\"time\"]" in timepicker
assert 'panelTimeLayer' in timepicker and 'panel:enhance-time' in timepicker
assert './assets/js/panel-time-picker.js' in sw and './assets/js/panel-choice.js' in sw
assert "input.tabIndex = -1" in timepicker and "input.setAttribute('aria-hidden', 'true')" in timepicker
assert "select.labels[0].textContent" not in choice
assert "control.labels?.[0]?.textContent" not in validation
assert 'cleanLabelText' in choice and 'cleanLabelText' in validation

# Login picker approved centered modal; no native select, no role labels, no swipe/grip.
assert '<select' not in login[login.index('<form method="post" class="login-form"'):login.index('</form>')]
assert 'login-account-modal' in login and 'login-account-list' in login
assert 'data-login-account-close' in login
assert 'loginAccountGrip' not in login and 'touchstart' not in login
assert 'favicon_url(192)' in login

# Carryover sessions are exception-only and never auto-closed.
assert 'business_session_is_carryover' in bt
assert "business_date_display" in orders and "carryover" in orders
assert 'نشست‌های بازمانده' in operator and 'خودکار نمی‌بندد' in operator
assert "status='closed'" not in bt

# Change-table preserves original business snapshot.
for token in ('business_date','business_shift_key','business_shift_label','business_cutoff_snapshot'):
    assert token in move, token
assert '?array $businessSnapshot = null' in funcs
assert '$business ??= business_assignment($effectiveStartedAt)' in funcs

# Reports use range-aware shift identities, hide redundant single shift and keep outside as exception.
assert 'business_report_shift_options($fromDate,$toDate,true)' in ops
assert 'business_report_shift_options($fromBusiness,$toBusiness,true)' in analytics
assert 'business_report_shift_identities($shiftOptions)' in ops and 'business_report_shift_identities($shiftOptions)' in analytics
assert 'رخداد خارج از ساعت شیفت' in ops and 'رخداد خارج از ساعت شیفت' in analytics
assert "['outside'" not in ops and "['outside'" not in analytics
assert 'format_jalali_datetime($fromDate)' not in ops

# Panel header eyebrow is centrally mapped to navigation parent groups.
for group in ('خلاصه','عملیات','منو و مهمان','مالی','گزارش‌ها','سامانه و زیرساخت'):
    assert group in layout
assert '$pageMeta[0] = $pageGroupMap[$section]' in layout

# Business day drives fiscal period and daily guest metrics.
assert "business_assignment($issuedAt)['business_date']" in read('includes/settlement.php')
assert 'business_current_date()' in read('admin/financial_periods.php')
assert 'business_current_date()' in read('includes/subscribers.php')
assert '$metricBusinessDate=business_current_date()' in read('api/metric.php')

print('1.31.7 static contracts passed: centered login modal, shared time owner, dynamic/stable shifts, carryover exception, business-day reporting and header IA.')
