# START HERE — Next Agent

اگر این پروژه را بدون هیچ زمینه قبلی تحویل گرفته‌ای، **هیچ کدی را تغییر نده** تا این ترتیب را کامل طی کنی:

1. `docs/handoffs/MASTER_HANDOFF_FA.md`
2. `docs/handoffs/CURRENT_STATUS_FA.md`
3. `docs/handoffs/PHASE6C_HANDOFF_FA.md`
4. `DEVELOPER_READ_FIRST_FA.md`
5. `docs/architecture-migration-r2/IMPLEMENTATION_PLAN_FA.md`
6. `docs/architecture-migration-r2/API_CONTRACTS.md`
7. `docs/architecture-migration-r2/SCHEMA_CHANGE_PLAN.md`
8. `docs/architecture-migration-r2/RISK_REGISTER.md`

## Current continuation
- completed through **Phase 6C / 1.36.4-dev.34**.
- Phase 6C product merge: `ccf0656655702a0b175a7cb9d7521fcb808745b1`.
- product post-merge CI `35438494232`: SUCCESS.
- next work: **Phase 7 — Printing / Notifications / Integrations**.

Start Phase 7 from current `main`. Do not checkout or reset to the old Phase 6C branch.

## Never assume
- هرگز از تاریخچه ChatGPT به‌عنوان source of truth استفاده نکن.
- هرگز Business owner موازی نساز.
- هرگز Realtime و Deferred را merge نکن.
- هرگز Public را Business Authority نکن.
- printing state machine بالغ را هنگام internalize کردن worker بازنویسی نکن.
- هرگز یک Phase را بدون final-head gates و post-merge product validation کامل اعلام نکن.
