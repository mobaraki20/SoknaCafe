# Handoff — Sokna Cafe 1.36.4-dev.8 Test RC

## Baseline
- Source: 1.36.4-dev.8
- Update path: 1.36.4-dev.7 → 1.36.4-dev.8
- Lifecycle: PRE-OPERATIONAL

## قواعد قفل‌شده
- Root-Cause/Owner-first؛ patch stacking و مسیر موازی ممنوع.
- Mobile Quick Order عادی Freeze است.
- Desktop Tables/Quick Order redesign حفظ شود؛ Mobile account Presentation مستقل Responsive و همان Owner مشترک را استفاده می‌کند.
- Settlement Review/Signature/Idempotency سمت سرور حذف یا دور زده نشود.

## Accommodation
- House 2.6.11 API 2.0 Source of Truth است.
- Deterministic: schema_not_ready, temporary_failure, auth/module/validation/contract failures.
- Ambiguous: transport/timeout/invalid response/internal 5xx ناشناخته؛ فقط این حالت‌ها Pending/needs reconciliation می‌سازند.
- tracking_id باید تا Audit/UI/API اپراتور حفظ شود؛ Secret/Token لاگ نشود.

## UAT_REQUIRED
House real charge/void, subscriber real settlement, device responsive, Print Agent, DB/backup/restore.
