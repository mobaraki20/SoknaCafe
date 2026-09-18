# WEB_PRINT_TRACEABILITY — dev.20

- Web baseline ZIP SHA: `9a37c1276f61d8199860f093cb312701f938e7805c79f7b2202bb27fc1b72993`
- Web production-code commit tested: `18a0930106df17190378fa5a43422a993fc5096a`
- Official Agent release: `6.2.0 / 2fb431962542ab59973142a9820eb673ab05d763`
- Remediation candidate observed: `6cf8b794676585eaff1a2a27c4e910667f7d8b46`
- Result policy: PASS فقط برای command اجراشده؛ DB/Windows/UAT جایگزین static ندارد.

| W | وضعیت | owner/symbol | evidence/test |
|---|---|---|---|
| W01 | IMPLEMENTED_UNVERIFIED | `PRINT_SHARED_CONTRACT.md`<br>`tests/fixtures/print-v4-dev20/manifest.json` | B01, B02, B49; `WEB_PRINT_ACCEPTANCE_RESULTS.json` |
| W02 | IMPLEMENTED_UNVERIFIED | `print_v4_request_hash`<br>`print_v4_claim`<br>`print_v4_accept`<br>`print_v4_start` | B03, B04, B05, B06, B07, B08, B09, B16, B49; `WEB_PRINT_ACCEPTANCE_RESULTS.json` |
| W03 | IMPLEMENTED_UNVERIFIED | `print_v4_attempt_status` | B07, B08, B09, B10, B11, B12, B49; `WEB_PRINT_ACCEPTANCE_RESULTS.json` |
| W04 | IMPLEMENTED_UNVERIFIED | `print_v4_report`<br>`print_v4_db_transient` | B05, B11, B13, B14, B15, B16, B45, B47, B49; `WEB_PRINT_ACCEPTANCE_RESULTS.json` |
| W05 | IMPLEMENTED_UNVERIFIED | `print_agent_api_read_json`<br>`print_agent_api_int_field`<br>`print_agent_api_bool_field` | B12, B17, B18, B36, B42; `WEB_PRINT_ACCEPTANCE_RESULTS.json` |
| W06 | IMPLEMENTED_UNVERIFIED | `print_database_time_to_utc` | B19, B20, B50; `WEB_PRINT_ACCEPTANCE_RESULTS.json` |
| W07 | IMPLEMENTED_UNVERIFIED | `print_response_wake_metadata`<br>`print_enqueue_job` | B06, B21, B22, B23, B24, B43, B50; `WEB_PRINT_ACCEPTANCE_RESULTS.json` |
| W08 | IMPLEMENTED_UNVERIFIED | `print_admin_action_id`<br>`print_job_create_reprint`<br>`print_job_reroute_unprinted` | B15, B25, B26, B27, B28; `WEB_PRINT_ACCEPTANCE_RESULTS.json` |
| W09 | IMPLEMENTED_UNVERIFIED | `admin/printing.php destination validation`<br>`print_reconcile_unmapped_preparation_jobs` | B24, B29, B30, B31, B32, B50; `WEB_PRINT_ACCEPTANCE_RESULTS.json` |
| W10 | IMPLEMENTED_UNVERIFIED | `SoknaPushRuntime.printWake`<br>`api/print_bridge_capability.php` | B02, B22, B33, B34, B35, B36, B49, B50; `WEB_PRINT_ACCEPTANCE_RESULTS.json` |
| W11 | BLOCKED_EXTERNAL | `assets/js/print-template-designer.js exactRevision/exactSessionId` | B02, B37, B38, B39, B49, B50; `WEB_PRINT_ACCEPTANCE_RESULTS.json` |
| W12 | IMPLEMENTED_UNVERIFIED | `print_template_read_package`<br>`admin/print_templates.php` | B23, B40, B41, B42, B43; `WEB_PRINT_ACCEPTANCE_RESULTS.json` |
| W13 | IMPLEMENTED_UNVERIFIED | `api/print_status_snapshot.php`<br>`admin/printing.php diagnostics` | B18, B19, B32, B44, B45, B46, B50; `WEB_PRINT_ACCEPTANCE_RESULTS.json` |
| W14 | IMPLEMENTED_UNVERIFIED | `printing_agent_unsettled_attempt_count` | B47, B50; `WEB_PRINT_ACCEPTANCE_RESULTS.json` |
| W15 | IMPLEMENTED_UNVERIFIED | `release/1.36.4-dev.20-print-web.sql`<br>`database/schema.sql` | B01, B20, B48, B50; `WEB_PRINT_ACCEPTANCE_RESULTS.json` |
| W16 | IMPLEMENTED_UNVERIFIED | `tests/print_acceptance.php`<br>`tests/print-dev20-browser.py`<br>`tests/print-dev20-contract.py` | B01, B48, B49, B50; `WEB_PRINT_ACCEPTANCE_RESULTS.json` |

## دسته‌بندی تغییرات

**موجود و حفظ‌شده:** attempt/retry cycle، v4 owner، backup `.skb`/Sodium، template origin/revision/active guard، local wake framework، status snapshot و permissions فعلی dev.19.

**اصلاح‌شده:** replay body fingerprint، receipt/next_action، terminal report evidence، strict validation، UTC snapshot، commit-safe/coalesced wake، preview revision/session/fail-closed DPI، import bounds، human action idempotency، failed-reprint FIFO، drain-before-retirement و migration dev.20.

**وابستهٔ باز:** MySQL/MariaDB concurrent/API tests، A49/B49 Windows integration، RenderProfile/local-device identity واقعی Agent، UAT پرینتر/50-print/soak.

## Evidence واقعی

- `artifacts/web-print-dev20/regression/final-prepackage.log` — PHP lint + JS syntax + contract/browser regressions.
- `artifacts/web-print-dev20/browser/B33.json`, `B34.json`, `B37.json`, `B39.json`, `B46.json` — Chromium assertions.
- `artifacts/web-print-dev20/acceptance-web/summary.json` — 6 PASS / 42 NOT_RUN و exit code 3.
- `artifacts/web-print-dev20/acceptance-integration/summary.json` — B49 NOT_RUN و missing real-integration env.

هیچ W در این تحویل VERIFIED نیست چون ماتریس هر W هنوز testهای DB/Agent/UAT اجرا‌نشده دارد.
