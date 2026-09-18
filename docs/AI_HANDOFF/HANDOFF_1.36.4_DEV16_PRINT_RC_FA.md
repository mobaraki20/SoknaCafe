# Handoff — Sokna Web 1.36.4-dev.16 Print Reliability RC

تاریخ: 2026-09-10
Baseline: Sokna Web 1.36.4-dev.15
Agent counterpart: Pagent 6.2.0 RC (`release/v6.2.0-rc1`)

## وضعیت F01 تا F21

| ID | وضعیت RC | شاهد/توضیح |
|---|---|---|
| F01 | FIXED_AGENT / UAT_REQUIRED | حذف انتظار اضافه و Local Wake در Agent 6.2.0؛ اندازه‌گیری صندوق واقعی لازم است. |
| F02 | FIXED | `attempt_status` و replay/reconciliation قرارداد Web/Agent. |
| F03 | FIXED_AGENT | recovery محافظت‌شده Worker در Agent 6.2.0. |
| F04 | FIXED_AGENT | Outbox مستقل/backoff/quarantine در Agent 6.2.0. |
| F05 | FIXED | `retry_cycle` و `cycle_attempt_no` بدون reset تاریخچه. |
| F06 | FIXED | `print_v4_wire_time()` و UTC صریح. |
| F07 | FIXED | predicate مشترک `print_open_problem_sql()` + count مستقل + pagination. |
| F08 | FIXED | سلامت بر مقصدهای active/required و route عملیاتی. |
| F09 | FIXED | `MAX(submitted_at)`؛ submitted همچنان فقط پذیرش Spooler است. |
| F10 | FIXED | Agent دارای history بازنشسته می‌شود؛ حذف فیزیکی فقط بدون reference. |
| F11 | FIXED | validation عدد/queue و lock scope تراکنشی mapping. |
| F12 | FIXED | سازگاری destination/document و reconcile `preparation_area_unmapped`. |
| F13 | FIXED | test print دارای `document_kind` واقعی و area key. |
| F14 | FIXED / UAT_REQUIRED | Exact preview از Local Renderer؛ fallback تقریبی صریح. |
| F15 | FIXED_AGENT / UAT_REQUIRED | Measure/Draw و overflow در Agent 6.2.0؛ کاغذ واقعی لازم است. |
| F16 | FIXED | origin/revision/hash و replacement اتمیک active template. |
| F17 | FIXED | PDO conflict فقط برای duplicate receipt constraint. |
| F18 | FIXED | JSON/method/content-type/type validation، hash بدون truncate، status واقعی Accept. |
| F19 | FIXED | readiness owner مشترک و printer discovery age. |
| F20 | FIXED | snapshot read-only بدون overwrite فرم/focus/scroll. |
| F21 | REVIEWED | موارد restore/epoch و recovery سخت‌افزاری در UAT/Recovery Lab باقی مانده‌اند. |

## وضعیت T01 تا T38

`PASS` فقط برای چیزی ثبت شده که در این محیط واقعاً اجرا شده است.

| ID | وضعیت | توضیح |
|---|---|---|
| T01 | NOT_RUN | MySQL/MariaDB واقعی در runtime فعلی موجود نیست. |
| T02 | PASS | قرارداد durable claim/replay در تست‌های v4 موجود. |
| T03 | PASS | empty claim replay/retention contracts. |
| T04 | PASS_AGENT | CI/Agent recovery contract؛ چاپ فیزیکی جداگانه UAT است. |
| T05 | PASS | reservation expiry/retry cycle contract. |
| T06 | PASS_AGENT | Agent recovery tests. |
| T07 | PASS_AGENT | stable Start identity/recovery در Agent. |
| T08 | PASS_AGENT | Worker launch/guard tests در Agent CI. |
| T09 | PASS_AGENT | pre-fence recovery tests. |
| T10 | PASS_AGENT | post-fence ambiguity hold. |
| T11 | UAT_REQUIRED | Spooler/پرینتر واقعی. |
| T12 | PASS_AGENT | Outbox isolation contract. |
| T13 | NOT_RUN | نیازمند DB واقعی و UI double-click concurrency. |
| T14 | UAT_REQUIRED | چند tab + Wake/Poll روی صندوق واقعی. |
| T15 | UAT_REQUIRED | permission/Bridge/browser lifecycle. |
| T16 | UAT_REQUIRED | موبایل + صف صندوق end-to-end. |
| T17 | PASS_CONTRACT | UTC wire contract؛ skew واقعی محیط UAT. |
| T18 | NOT_RUN | DB/API integration واقعی. |
| T19 | NOT_RUN | DB fixture واقعی با 25 مشکل. |
| T20 | NOT_RUN | DB/UI integration واقعی. |
| T21 | NOT_RUN | FK/retire روی DB واقعی. |
| T22 | NOT_RUN | concurrent MariaDB لازم است. |
| T23 | PASS_STATIC | validation code + syntax؛ browser submit واقعی UAT. |
| T24 | NOT_RUN | DB migration/reconcile واقعی. |
| T25 | UAT_REQUIRED | Renderer + کاغذ. |
| T26 | UAT_REQUIRED | تمام layout/font روی Renderer/پرینتر. |
| T27 | UAT_REQUIRED | long RTL روی bitmap و کاغذ. |
| T28 | UAT_REQUIRED | Driver واقعی 58/80 و 203/300 DPI. |
| T29 | UAT_REQUIRED | exact bitmap equality با Agent محلی. |
| T30 | NOT_RUN | DB/UI integration واقعی. |
| T31 | NOT_RUN | historical snapshot end-to-end. |
| T32 | UAT_REQUIRED | Bridge security روی browser/Windows. |
| T33 | UAT_REQUIRED | token rotation/open work end-to-end. |
| T34 | PARTIAL_PASS | Clean package و update manifest ساخته شدند؛ migration install روی DB واقعی NOT_RUN. |
| T35 | UAT_REQUIRED | rollback با DB/Windows واقعی. |
| T36 | UAT_REQUIRED | Recovery Lab با queue محلی و DB restore. |
| T37 | UAT_REQUIRED | Paper out/Offline/Spooler restart. |
| T38 | UAT_REQUIRED | 50 چاپ فیزیکی و soak. |

## تست‌های اجراشده

- PHP lint: تمام فایل‌های PHP PASS.
- Node syntax: تمام `assets/js/*.js` و service-worker PASS.
- 15 قرارداد چاپ Python شامل `print-dev16-reliability-contract.py`: PASS.
- `tests/updater-canonical.php`: PASS.
- `tests/print-agent-receipt-validator.php`: PASS.
- `tests/print-template-v2-runtime.php`: PASS.
- نصب آزمایشی Update Package تا مرحله نیاز به PDO MySQL پیش رفت؛ runtime فعلی `pdo_mysql` ندارد، بنابراین migration/install واقعی PASS اعلام نشده است.

## Rollback

Updater v1.5.3 restore point را قبل از live changes می‌سازد. پیش از نصب RC روی محیط تست، DB backup بگیرید. در rollback/restore با Agent یا Spooler جلوتر از DB، هیچ reprint خودکار انجام نشود و ambiguity دستی reconcile شود.
