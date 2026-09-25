> **Historical audit — Superseded for deployment/ownership by `docs/architecture-migration-r2/PHASE7R_PRINTING_RECONCILIATION_FA.md`.**
> Protocol-v4/state-machine findings remain useful evidence. Claims that Agent source/binary must live only outside Cafe are not active authority after R2 reconciliation.

# Audit جاری Integration چاپ — Working Copy بدون Release

> Baseline اجرایی: `Sokna 1.33.0-rc1`
> Agent Source of Truth: `mobaraki20/Pagent` / branch `main`
> وضعیت محصول: هنوز عملیاتی نشده؛ baseline پیش از اولین انتشار به v4-only clean install تبدیل شده است.
> این سند Release/Production approval نیست.

| الزام | Owner | وضعیت فعلی | شواهد/تست |
|---|---|---|---|
| Durable print intent همراه عملیات سفارش | Server/DB | VERIFIED در Source | `print-v4-reproduction-required-intent.py` PASS |
| rollback بدون orphan intent | Server/DB | VERIFIED در Source؛ DB runtime PENDING | enqueue داخل همان transaction؛ MariaDB واقعی هنوز اجرا نشده |
| عدم network call به Agent داخل Order transaction | Server | VERIFIED | Print enqueue فقط DB است |
| Claim request idempotency حتی برای نتیجه خالی | Server/DB | VERIFIED | `print_claim_requests`; hardening test PASS |
| Claim replay attempt جدید نسازد | Server/DB | VERIFIED | Attempt IDs در claim ledger پایدار می‌شوند |
| Destination snapshot claim پایدار باشد | Server/DB | VERIFIED | `destination_snapshot_json` در `print_attempts` |
| Snapshot خراب/گمشده reroute حدسی نسازد | Server | VERIFIED fail-closed | 409 `destination_snapshot_invalid` |
| Accept/Start/Renew/Report idempotent | Server | VERIFIED | request_id guardها و attempt binding |
| reserved expiry امن | Server | VERIFIED | فقط `reserved` expiry/retry می‌شود |
| claimed/started با heartbeat loss reassign نشوند | Server | VERIFIED | Claim فقط pending؛ ambiguity انسانی جدا |
| submitted فقط Spooler acceptance | Server | VERIFIED | `physical_print_confirmed=false` |
| unknown/recovery_hold Auto-Retry نشوند | Server | VERIFIED | human resolution only |
| Late submitted evidence کنترل‌شده | Server | VERIFIED | started واقعی + unresolved + same receipt + spooler evidence |
| stale ownership direct-POST guard | Server/Admin | VERIFIED | threshold داخل Shared Owner enforce می‌شود |
| Reprint Job جدید و Audit | Server/Admin | VERIFIED | `reprint_of_id` + reason/audit |
| FIFO destination fence | Server | VERIFIED در Source/Model | fault/load model PASS |
| invalid token / auth fail-closed | Server | VERIFIED | SHA-256 token lookup + active=1 + HTTPS trust boundary |
| Print API v3 | Runtime | REMOVED | baseline پیش از بهره‌برداری فقط `print-agent/v4/api.php` دارد |
| protocol discriminator در DB/Admin | Server/DB/Admin | REMOVED | همه credentialها v4 هستند؛ `protocol_version` وجود ندارد |
| Agent source/binary داخل Cafe | Deployment | REMOVED | Agent فقط در `mobaraki20/Pagent` نگهداری می‌شود |
| Agent download یک Source of Truth | Deployment/Admin | VERIFIED | URL pinned به GitHub Release `v6.2.0`؛ Setup و SHA-256 رسمی مستقیم Verify شده‌اند |
| Upgrade-only v3→v4 migrations | DB | REMOVED FROM PRELAUNCH BASELINE | نصب تازه فقط `database/schema.sql` را اجرا می‌کند |
| MariaDB clean install | DB/Test System | PENDING | سیستم موجود قابل reset است ولی دسترسی DB هنوز ارائه نشده |
| Authenticated HTTP flow روی سیستم تست | Test System | PENDING | URL/session/Agent credential تست هنوز لازم است |
| Current `Pagent/main` source compatibility | Agent↔Server | VERIFIED CODE/CI | Agent 6.1 PR روی `main` merge شده؛ Protocol v4 حفظ شده و CI رسمی Build/Test/Windows install gate PASS است؛ physical printer UAT مستقل باقی است |
| Claim ledger growth / idle cadence | Agent↔Server/DB | PENDING MEASUREMENT | روی سیستم تست با Agent واقعی اندازه‌گیری خواهد شد |
| Physical printer/Spooler | Windows/Printer | UAT_REQUIRED | سیستم تست غیرعملیاتی برای destructive recovery tests مناسب است |

## Baseline نهایی پیش از اولین انتشار

- Server: فقط Print API v4.
- DB clean install: schema نهایی، بدون مسیر upgrade v3→v4.
- Agent: فقط GitHub `mobaraki20/Pagent`.
- Cafe package: بدون Agent source/binary.
- هر failure مبهم بعد از Start: بدون Auto-Reprint.

## نکته مهم تست
چون سامانه هنوز عملیاتی نیست، تست‌های destructive مثل پاک‌سازی print tables، reset mapping، قطع Agent، stop/start Spooler و reinstall قابل انجام‌اند؛ با این حال قبل از هر reset DB یک backup تستی می‌گیریم تا خود مسیر restore نیز بررسی شود.
