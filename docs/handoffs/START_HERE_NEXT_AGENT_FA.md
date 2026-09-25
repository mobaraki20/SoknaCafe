> **مرجع فعلی تطبیق — 2026-09-25:** شاخه محلی `work/reconcile-dev39`، نسخه سورس dev.39، مبتنی بر تاریخچه گیت‌هاب و پیشرفت‌های بسته. ابتدا `docs/handoffs/DEV39_RECONCILIATION_2026-09-25_FA.md` را بخوانید (مسیر نسبت به ریشه مخزن). ادامه‌ها و دستورهای متعارض زیر سوابق تاریخی‌اند؛ Phase8B کامل نشده، WiX مجوز اجرا نگرفته و Windows CI جدید انجام نشده است.

# START HERE — Next Agent

Current continuation authority is the **2026-09-24 local reconciliation workspace**, not the older Phase8A-only text.

1. Read `LOCAL_WORKSPACE_BASELINE_2026-09-24_FA.md`.
2. Read `CURRENT_STATUS_FA.md`.
3. Read `R2_IMPLEMENTATION_RECONCILIATION_2026-09-24_FA.md`.
4. Read `PHASE7R_PRINTING_RECONCILIATION_FA.md`.
5. Inspect `git status` and `git log` before changing code.

Hard rules:
- Canonical recovery baseline is `75421078d8e944a33f58cbab96723c63480c4f5e`; always inspect current HEAD/status after recovery.
- Working product is dev.38/Phase8B; dev.26 is behavior/UI-DNA reference only.
- All previous SOKNA Design System versions are rejected; `SCDS-CANONICAL-2026-R1` is the sole Design Authority and defects in dev.26/dev.38 must be corrected, not copied.
- Print Worker is internal to SOKNA Local. Never revive a separate Print Agent installer/download/product flow.
- Preserve Print API v4 durable state machine and Winspool semantics; no duplicate PHP implementation.
- `NOT_RUN` is never `PASS`; Windows CI and physical-printer UAT remain explicit blockers until executed.
