# PRINT_SHARED_CONTRACT — Web dev.21 / Print Agent v4

نسخه قرارداد: 1.1 — 2026-09-12

## مبنا و منبع حقیقت

- Web baseline: `1.36.4-dev.19`, ZIP SHA-256 `9a37c1276f61d8199860f093cb312701f938e7805c79f7b2202bb27fc1b72993`.
- Web target: `1.36.4-dev.20`.
- Protocol: Print API v4؛ owner سرور `print-agent/v4/api.php` و owner domain `includes/printing.php`.
- آخرین Release رسمی Agent: `6.2.0`, source SHA `2fb431962542ab59973142a9820eb673ab05d763`.
- Candidate اصلاحات پس از Release: branch `feat/agent-remediation-post-6.2`, observed SHA `6cf8b794676585eaff1a2a27c4e910667f7d8b46`. این SHA Release رسمی/Integration-verified تلقی نمی‌شود.
- Incident baseline فعلی برای remediation: Agent `6.2.2`, exact source SHA `5a58a10f6326698f80c3408a7a69229754e5c9d4`.
- Target پیشنهادی remediation: Agent `6.2.3`؛ تا build/installer/checksum، G01-G12 و A53/B53 واقعی تکمیل نشوند فقط `Candidate / UNVERIFIED` است و Recommended وب تغییر نمی‌کند.

## مدل حالت

Job state و Attempt state یکی نیستند. Agent فقط Attempt پذیرفته‌شدهٔ خودش را اجرا می‌کند. Local outcome Agent (`submitted/failed/unknown/recovery_hold`) با delivery state گزارش (`pending/backoff/auth_blocked/reconciliation_required/delivered`) جداست. `submitted` فقط تحویل به Windows Spooler است و به معنی خروج فیزیکی کاغذ نیست.

## request identity و replay

Mutationهای Claim/Accept/Renew/Start/Report دارای `request_id` ثابت و بدنهٔ canonical هستند. Server برای requestهای نسخه جدید SHA-256 بدنهٔ معنایی را ذخیره می‌کند. همان identity + همان body replay همان اثر است؛ همان identity + body متفاوت `409 request_body_conflict` است. برای ledgerهای قدیمی dev.19 که hash نداشتند migration تاریخچه را جعل نمی‌کند؛ fingerprint از اولین درخواست ثبت‌شده در dev.20 به بعد authoritative است.

Claim replay snapshot همان Job/Attempt/Destination اولیه را برمی‌گرداند؛ empty Claim قبلی با همان request id بعداً Job تازه نمی‌گیرد. Accept به Attempt + lease + local receipt + content hash bind است. Start فقط Attempt claimed همان Agent را ادامه می‌دهد و state terminal مجوز چاپ تازه نیست.

## attempt_status

Response حداقل شامل `success, attempt_id, job_id, attempt_state, job_state, receipt_matches, next_action, terminal, requires_human_resolution, lease_expires_at, server_time` است. همهٔ زمان‌های wire دارای offset صریح UTC هستند.

- claimed + receipt match → `start`
- started + receipt match → `report/continue` مطابق outcome محلی؛ این پاسخ مجوز reprint نیست.
- receipt mismatch → `reconcile` و بدون action چاپ.
- reserved منقضی و هرگز پذیرفته‌نشده → `reconcile`, terminal/effective-expired؛ status خودش mutation انجام نمی‌دهد.
- submitted/failed/unknown/recovery_hold/human-resolved → چاپ جدید خودکار ممنوع؛ outcome/decision حفظ می‌شود.

## Report

`submitted` بدون `spooler_job_id` معتبر `422` است. replay فقط وقتی idempotent است که status/retryable/receipt/spooler evidence همان باشد. evidence متفاوت conflict است. late submitted فقط برای Attempt واقعاً started، receipt یکسان، evidence معتبر و بدون human resolution متعارض قابل پذیرش است. human resolution authoritative است و late report آن را overwrite نمی‌کند.

## خطا

401 credential؛ 403 policy/lease/revocation؛ 409 semantic/state/evidence conflict؛ 422 schema؛ 429 bounded busy؛ transient DB/deadlock/connection loss با 503/retry semantics. unique خطای نامرتبط با receipt به `receipt_conflict` تبدیل نمی‌شود.

## Wake و Bridge

Wake فقط بعد از business commit موفق metadata می‌گیرد. Browser owner چاپ نیست و durable queue ندارد. Wake در حافظه coalesce می‌شود، deadline دارد و شکست آن Job را fail/retry نمی‌کند؛ polling Agent مسیر حقیقت است. bootstrap فقط runtime تازه Listener را برمی‌گرداند، raw Agent bearer/lease را به JS نمی‌دهد و arbitrary host ممنوع است.

`bridge_pairing_id` credential است و در health عمومی نمایش داده نمی‌شود. runtime freshness از config intent جداست. Local device identity در Web dev.20 هنوز به‌صورت قابل اثبات bind نشده؛ بنابراین bootstrap آن را صریحاً unverified اعلام می‌کند.

## Preview

Revision هنگام تغییر فرم فوراً زیاد و request قبلی abort می‌شود؛ debounce فقط ارسال را عقب می‌اندازد. `session_id` مستقل و پاسخ باید revision/session/destination فعلی را match کند. DPI ثابت 203 حذف شده است. exact فقط با RenderProfile واقعی مقصد (`dpi_x/dpi_y` و binding معتبر) مجاز است. چون Agent remediation فعلی profile قابل اتکای queue-bound را در contract وب expose نکرده، Web dev.20 `exact_preview_ready=false` و preview را approximate/unavailable نشان می‌دهد؛ این fail-closed عمدی است.

## Template/Payload

Job شامل payload/template snapshot و hash همان bytes است؛ Agent در print path template/font/HTML اجرایی از اینترنت fetch نمی‌کند. تغییر/حذف template فعال Job تاریخی را عوض نمی‌کند. reprint تاریخی از snapshot قبلی است و «چاپ جدید با قالب فعلی» عملیات جداست.

## Retirement/rotation

Policy منتخب Web dev.20: **drain-before-retirement/rotation**. تا Attempt باز یا report حل‌نشده وجود دارد disable/delete/token rotation رد می‌شود. token revoked برای گزارش عمومی دوباره باز نمی‌شود؛ history حفظ می‌شود و heartbeat دیررس Agent بازنشسته آن را reactivate نمی‌کند.

## Compatibility matrix

| Web | Agent | وضعیت | توضیح |
|---|---|---|---|
| dev.19 | 6.2.0 `2fb431...` | UNVERIFIED | سورس/Release شناخته‌شده است اما pair evidence B49 در این تحویل اجرا نشده. |
| dev.20 | 6.2.0 `2fb431...` | UNVERIFIED | API v4 additive است؛ exact preview fail-closed؛ A49/B49 واقعی لازم است. |
| dev.19 | remediation `6cf8b...` | UNVERIFIED | branch رسمی Release نیست. |
| dev.20 | remediation `6cf8b...` | UNVERIFIED | قرارداد static تطبیق داده شده؛ real Windows/API pair test هنوز لازم است. |
| dev.20 | 6.2.2 `5a58a...` | INCIDENT BASELINE | مبنای قطعی remediation؛ optional-null Heartbeat و Claim conflict در scope سند حاضر است. |
| dev.21 | 6.2.2 `5a58a...` | SERVER-MITIGATED / AGENT-UNFIXED | Web null اختیاری را می‌پذیرد و freshness مستقل دارد؛ defectهای quarantine/health سمت Agent هنوز باقی‌اند. |
| dev.21 | 6.2.3 | CANDIDATE / UNVERIFIED | فقط پس از artifact واقعی، checksum، upgrade gate و A53/B53 می‌تواند Recommended شود. |

هیچ خانه‌ای صرفاً با وجود capability string به SUPPORTED عملیاتی ارتقا نمی‌یابد.
