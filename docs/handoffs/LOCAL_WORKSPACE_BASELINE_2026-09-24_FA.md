# SOKNA Cafe — Local Workspace Baseline Handoff

> **HISTORICAL BASELINE NOTICE — superseded later on 2026-09-24**
> This file records the imported dev.38 workspace baseline. Current continuation authority is `docs/handoffs/CURRENT_STATUS_FA.md`; Phase 8B Closure now uses `1.36.4-dev.39` on `work/phase8b-closure-dev39`.


## 1. هدف
این سند نقطه شروع توسعه محلی پس از تحویل پروژه به Agent جدید است. GitHub در این محیط مستقیماً قابل نوشتن نیست؛ بنابراین کار در Git محلی انجام و در checkpointها به شکل ZIP + git bundle + Handoff + SHA256 تحویل می‌شود.

## 2. منابع و نقش آن‌ها
- **Working source:** snapshot `phase/8b-windows-setup`, `VERSION.txt = 1.36.4-dev.38`.
- **Observed upstream code head در Handoff موجود:** `6a3e183ca0ea544046c09bf3b9c8de7ab038ca5b`.
- **dev.26:** مرجع رفتار تجاری و DNA رابط قدیمی؛ Working Source نیست.
- **R2:** مرجع اهداف معماری؛ در صورت تعارض با تصمیم جدید صریح مالک، تصمیم جدید مالک مقدم است.
- **همه SOKNA Design Systemهای قبلی:** REJECTED؛ هیچ نسخه/R/Freeze آن‌ها Design Authority نیست.

## 3. Git محلی
Snapshot بدون `.git` تحویل شده بود و در Workspace به Git محلی تبدیل شد.
Baseline local commit اولیه در log محلی نگهداری می‌شود. این SHA محلی معادل SHA upstream نیست و نباید به‌عنوان upstream commit گزارش شود.

## 4. Baseline test evidence
در محیط فعلی:
- PHP lint: **278/278 PASS**
- JavaScript syntax: **36/36 PASS**
- `tests/unit.php`: **103/103 PASS**
- اولین Phase 2 PHP contract به دلیل نبود `pdo_sqlite` در PHP workspace با `ENVIRONMENT_BLOCKER` متوقف شد؛ Fail محصول محسوب نمی‌شود.
- مجموعه Static Python contracts اجراشده تا این checkpoint: **60 PASS / 0 FAIL**.
- Browser gates اجراشده تا سقف زمان ابزار: login picker, supply, modules, UI conformance, tab-language, icon-system, guest, Jalali, navigation, touch/focus همگی PASS شدند؛ اجرای batch طولانی توسط timeout ابزار قطع شده و تست‌های اجرا نشده PASS ادعا نمی‌شوند.

Environment فعلی PHP 8.4 دارای PDO core است ولی `pdo_sqlite`/`pdo_mysql` ندارد. Chromium موجود است.

## 5. اصل Completion
`Test not run = Not tested`. Timeout ابزار، فقدان extension یا نبود Windows/MariaDB فقط `BLOCKED_ENVIRONMENT / NOT_RUN` است، نه PASS و نه Fail محصول.

## 6. ترتیب ادامه
1. R2 implementation reconciliation و ثبت gapهای واقعی.
2. ایجاد Design System جدید و Persian/RTL gate به‌صورت همزمان با migration، نه در پایان.
3. بستن gapهای Phase 6 (Batch Purchase, Expenses UI/owner completeness, Tax).
4. reconcile Phase 7 Printing ownership بدون شکستن state machine بالغ.
5. ادامه Phase 8B/8C و سپس Phase 9/UAT/Release.

## 7. UI rule
هر Surface قدیمی یا جدید هنگام لمس شدن باید Audit شود. ظاهر/رفتار خوب dev.26 حفظ می‌شود؛ اشکال‌ها کپی نمی‌شوند. Pattern عمومی ابتدا به Component/Token/Behavior رسمی Design System تبدیل می‌شود و سپس مصرف می‌شود.

## 8. Progress after baseline — local commits / Phase7R
Valid local commits after the imported dev.38 snapshot:
- `cffcc71` — canonical Persian/RTL UI foundation + regression budget.
- `867ba06` — Persian customer/settlement product language standardization.
- `cb62770` — Phase 6D atomic Batch Purchase receipt workflow.
- `4681e1b` — Phase 6E append-only Expenses workflow.

Phase 7 Printing reconciliation is implemented in the working tree and must be committed only after the gates recorded in `PHASE7R_PRINTING_RECONCILIATION_FA.md` remain green. It internalizes audited Print Worker 6.2.5 under SOKNA Local and explicitly leaves Windows CI/physical UAT open. Phase 6F Tax must remain deferred until that checkpoint is durable.
