# Release Notes — Sokna Cafe 1.36.4-dev.15

تاریخ: 2026-09-06

## Scope
این checkpoint یک Hotfix ریشه‌ای برای سازگاری Print API v4 با Windows Print Agent 6.0/6.1 است.

Root Cause از Support Package واقعی Agent مشخص شد:
- Agent سالم و متصل بود.
- Printer ویندوز سالم/Online بود.
- Job تا `reserved` می‌رسید.
- `accept` با `accept_validation_failed` رد می‌شد.
- Windows Agent از `local_receipt_id` با Contract رسمی `r-` + GUID(N) استفاده می‌کند.
- Validator سرور `r` را نمی‌پذیرفت؛ بنابراین Job هیچ‌وقت به `claimed/start/submitted` نمی‌رسید.

## Print API v4 compatibility hotfix
- Validator `local_receipt_id` با Contract واقعی Agent 6.0/6.1 همگام شد: `r-<32 hex>`.
- Validation شناسه Receipt از Validation Hash جدا شد.
- Error Codeهای مبهم حذف و به خطاهای تفکیک‌شده تبدیل شدند:
  - `invalid_local_receipt_id`
  - `invalid_content_sha256`
  - `content_hash_mismatch`
- `local_receipt_conflict` برای Conflict واقعی Receipt حفظ شد.
- هیچ تغییری در Windows Agent یا Printer Driver لازم نیست.

## Escaped-defect gate
Regression جدید اضافه شد تا Contract واقعی Agent دوباره از سرور جدا نشود:
- `tests/print-v4-agent61-accept-contract.py`
- `tests/print-agent-receipt-validator.php`
- defect class: `print_agent_receipt_contract_parity`

## پاکسازی یک‌باره داده‌های چاپ تستی
چون محیط هنوز Pre-Operational/Test است و Jobهای جدید در اثر Bug فوق ساخته شده‌اند، Update `dev.14 → dev.15` یک Migration یک‌باره دارد که فقط این داده‌ها را پاک می‌کند:
- `print_attempts`
- `print_claim_requests`
- `print_jobs`

Migration به این موارد دست نمی‌زند:
- `print_agents`
- `print_destinations`
- `print_templates`
- Route/Printer mapping
- سفارش، مالی، موجودی، کاربران یا سایر Domainها

**قبل از Update:** Print Agent ویندوز را Stop کنید و Windows Spooler را از Jobهای تستی خالی کنید.
Migration سرور `queue.db` محلی Agent یا Windows Spooler را پاک نمی‌کند.

## پس از نصب
1. Mapping مقصدها را تغییر ندهید مگر Diagnostics آن را ناسالم نشان دهد.
2. Agent 6.1.0 را Start کنید.
3. Diagnostics باید Agent/Printer را Ready نشان دهد.
4. فقط یک Print Test بفرستید.
5. انتظار State flow: `pending → reserved → claimed → started → submitted`.
6. خروج فیزیکی کاغذ را جداگانه تأیید کنید؛ `submitted` فقط تحویل به Windows Spooler است.

## Out of scope
- Redesign جدید Modal اصلاح تعداد.
- تغییر Print Agent 6.1.0.
- تغییر Finance/Settlement/Menu/House.

## Test discipline
`Test not run = Not tested`.
