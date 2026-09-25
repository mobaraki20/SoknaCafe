# Phase 7R — Reconciliation چاپ با Frozen R2

Status: IN_PROGRESS
Authority: `SOKNA_ARCHITECTURE_HANDOFF_STANDALONE_FINAL_R2_2026-09-18`

## علت بازگشایی Phase 7

پیاده‌سازی قبلی Phase 7، عبارت «internalize Print Worker ownership under Local Runtime» را به معنای supervise کردن محصول نصب‌شونده مستقل Pagent تفسیر کرده بود. این تفسیر با Frozen Decision اصلی R2 تعارض دارد:

- `01_FROZEN_PRODUCT_DECISIONS_FA.md`: «Print Worker داخلی SOKNA است؛ محصول جدا نصب نمی‌شود.»
- `09_PRINTING_NOTIFICATIONS_FA.md`: «Internal Print Worker shipped/managed by SOKNA.»
- `23_DECISION_LOG_FA.md`: `internal Print Worker: accepted`.

بنابراین بخش Deployment/Ownership در `PHASE7_DESIGN_NOTES_FA.md` که binary distribution را به installer مستقل Pagent واگذار می‌کرد، برای ادامه مهاجرت **Superseded** است. state machine چاپ، Protocol v4 و semantics بالغ آن Superseded نیستند و باید حفظ شوند.

## تصمیم اصلاحی

1. SOKNA Local تنها محصول نصب‌شونده سمت کافه است.
2. Print Worker یک component داخلی SOKNA Local است و همراه Build/Setup/Repair/Recovery خود SOKNA مدیریت می‌شود.
3. Setup مستقل، Windows Installed Apps entry مستقل، shortcut مستقل و flow دانلود GitHub برای Print Agent در معماری نهایی وجود ندارد.
4. برای جلوگیری از بازنویسی پرریسک، source عملیاتی Pagent 6.2.5 به‌عنوان baseline فنی internalize می‌شود:
   - Core
   - Runtime
   - Service
   - Worker
   - SQLite durable queue
   - Claim/Accept/Start/Report state machine
   - reconciliation/retry/failover logic
   - renderer و Winspool adapter
5. `Control` و `Setup` مستقل upstream بخشی از محصول نهایی SOKNA نیستند. مدیریت کاربر در Surface فارسی `چاپ و پرینترها` انجام می‌شود.
6. نام‌های فنی `print_agents`, Print API v4 و namespaceهای `Sokna.PrintAgent.*` فعلاً compatibility detail هستند و به‌تنهایی به معنای محصول جدا نیستند.
7. داده durable چاپ تحت SOKNA Data Root قرار می‌گیرد. مهاجرت از Data Root قدیمی PrintAgent باید fail-safe باشد و queue/secret/config را بدون تصمیم صریح حذف نکند.
8. Physical printer/Windows acceptance همچنان Gate مستقل UAT است؛ internal شدن component به معنی PASS خودکار سخت‌افزار نیست.

## Source provenance

Owner فایل `Pagent-release-v6.2.5-final.zip` را به‌عنوان سورس نهایی موجود ارائه کرده است.

- archive SHA-256: `c9c205fe0efb08c5d3a270b1065b0e320489b0e7f3c8764d2295724fb01c102a`
- version: `6.2.5`
- imported source baseline commit in received archive workspace: `65e12c4`
- internal source: `runtime/print-worker/source/`
- provenance: `runtime/print-worker/source/PROVENANCE.json`
- source-code comparison against the supplied 6.2.5 source: **54 `src/` files byte-identical**, exactly **2 code files adapted** for SOKNA ownership (`AgentPaths.cs`, `Service/Program.cs`), and **37 upstream test files byte-identical**. `Setup`/`Control` are omitted intentionally. Renderer/state-machine/SQLite/Winspool code is unchanged at this checkpoint. The bundled font license has only trailing-whitespace normalization for repository hygiene.

## Migration order

1. Freeze/provenance source 6.2.5.
2. Build internal Service+Worker only; no standalone Setup/Control package.
3. Move component install/repair ownership into `runtime/windows/setup-sokna.ps1`.
4. Move component data path under SOKNA Data Root with legacy-preservation path.
5. Replace Runtime «supervise external product» semantics with «health/recovery of internal component».
6. Remove external GitHub download/release-owner behavior from active Local UI.
7. Integrate fresh provisioning/credential handoff without asking user to install/configure a second product.
8. Run Print API/state-machine regression.
9. Run Windows service/install/repair/rollback acceptance.
10. Keep physical printer UAT open until real hardware evidence exists.

## Non-negotiable regression rules

- Do not rewrite Print API v4 state machine in PHP.
- Do not reset SQLite queue to solve migration issues.
- Do not auto-reprint ambiguous physical outcomes.
- Do not weaken idempotency, submission fence, reconciliation or durable report outbox.
- Kitchen/Preparation ticket remains non-financial.
- Customer/financial receipt must expose Tax when Tax is later enabled.
- No separate Print Agent installer/download may return to the active SOKNA setup flow.

## Local implementation checkpoint — 2026-09-24

Status: **LOCAL_IMPLEMENTATION_READY / WINDOWS_CI_REQUIRED / PHYSICAL_UAT_REQUIRED**

پیاده‌سازی workspace تا این checkpoint:
- source عملیاتی 6.2.5 به `runtime/print-worker/source/` internalize شده و provenance SHA/commit ثبت است؛ Setup/Control مستقل upstream وارد مالکیت runtime نشده‌اند.
- `runtime/windows/build-print-worker.ps1` فقط Service+Worker را restore/build/test/publish می‌کند و manifest داخلی می‌سازد.
- `setup-sokna.ps1` مالک install/repair/recover سرویس `SoknaPrintWorker` است؛ پارامتر installer خارجی حذف شده است.
- Fresh/Recover pairing از owner دیتابیس Cafe ساخته و از فایل private به Worker داده می‌شود؛ secret وارد CLI/log/registry/summary نمی‌شود و Worker آن را با DPAPI `LocalMachine` ذخیره می‌کند.
- Repair سالم `config.json`/`secret.dat` موجود را حفظ می‌کند؛ pairing فقط در صورت مفقودبودن واقعی state محلی بازسازی می‌شود.
- DataRoot قدیمی هیچ‌وقت با DataRoot پرشده جدید merge نمی‌شود؛ دو root دارای state باعث fail-closed می‌شوند. migration فقط وقتی مقصد داخلی فاقد state است انجام می‌شود و rollback کپی مهاجرتی را پاک می‌کند، در حالی که منبع قدیمی دست‌نخورده می‌ماند.
- legacy standalone service پس از موفقیت internal worker disable می‌شود تا دو worker هم‌زمان یک Print API queue را claim نکنند.
- UI فعال چاپ download/install مستقل Agent را حذف کرده و lifecycle identity/token را فقط Setup/Repair-owned می‌داند.
- `settings.print_internal_worker_agent_id` هویت canonical Worker داخلی را مشخص می‌کند؛ Setup مسیرهای دارای queue را به همان Worker normalize می‌کند، اما `print_attempts.agent_id` تاریخی برای Audit بازنویسی نمی‌شود.
- UI روزمره دیگر Worker/«رایانه چاپ» را route choice نشان نمی‌دهد؛ مدیر فقط **پرینتر اصلی** و **پرینتر جایگزین** را انتخاب می‌کند. هویت Worker زیرساخت پنهان SOKNA است.
- GitHub external release resolver از owner فعال چاپ حذف شده است؛ compatibility aliasهای فنی `print_agent_*` فقط نام legacy/API هستند.
- Windows CI برای build/test .NET 10.0.302 و Setup runtime به workflow اضافه شده است؛ نتیجه hosted CI هنوز در این workspace اجرا نشده است.

### Evidence اجراشده در workspace
- PHP lint: **281 files PASS**.
- JavaScript syntax: **34 files PASS**.
- Unit: **103/103 PASS**.
- `phase7r-internal-print-worker-contract.py`: **30/30 PASS**.
- `phase7-runtime-services-contract.py`: **12/12 PASS**.
- `phase8b-windows-setup-contract.py`: **18/18 PASS**.
- `rc8-contracts.py`: **56 PASS**.
- `v1360-final-invariants.py`: PASS.
- `v1360-module-ownership-contract.py`: PASS.
- `v1360-ui-language-contract.py`: PASS.
- Print API/state-machine gates PASS: server contract, hardening, agent health diagnostics, required-intent, ambiguity, attempts, empty-claim retention, claim reconciliation, recovery hotfix, nullable report contract, pre-op clean baseline, v4-only baseline, fault/load model, dev14 reliability, Agent 6.1 accept compatibility, v13220 operations.
- Historical external-distribution tests that were still executable were migrated to assert the same protocol invariants under **internal component ownership**, rather than being deleted/ignored.
- `print-dev20-contract.py`: PASS after migrating historical assertions to the frozen R2 internal-worker ownership while retaining v4/idempotency/ambiguity invariants.
- Print acceptance `B01`: **PASS** (base contract + printing settings browser + template browser).
- `git diff --check`: PASS.

### Evidence عمداً ادعانشده
- C# restore/build/test: **NOT_RUN — `dotnet` در workspace موجود نیست**.
- PowerShell runtime/SCM install/repair/rollback: **NOT_RUN — `pwsh` در workspace موجود نیست**.
- DB-backed Phase8B Setup runtime: **BLOCKED_ENVIRONMENT — `PDO MySQL` در PHP workspace موجود نیست**.
- Windows Spooler / physical printer: **UAT_REQUIRED**.
- Hosted GitHub Windows CI: **NOT_RUN in this offline workspace**.

تا بسته‌شدن موارد بالا، Phase7R از نظر source/architecture قابل checkpoint است اما `Production-ready` یا `Operationally accepted` اعلام نمی‌شود.
