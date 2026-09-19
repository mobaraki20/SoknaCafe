# START HERE — Next Agent

اگر این پروژه را بدون زمینه قبلی تحویل گرفته‌ای، قبل از تغییر کد بخوان:
1. `docs/handoffs/MASTER_HANDOFF_FA.md`
2. `docs/handoffs/CURRENT_STATUS_FA.md`
3. `docs/handoffs/PHASE7_HANDOFF_FA.md`
4. `DEVELOPER_READ_FIRST_FA.md`
5. `docs/architecture-migration-r2/IMPLEMENTATION_PLAN_FA.md`
6. `docs/architecture-migration-r2/API_CONTRACTS.md`
7. `docs/architecture-migration-r2/SCHEMA_CHANGE_PLAN.md`
8. `docs/architecture-migration-r2/RISK_REGISTER.md`

## Current continuation
- completed through **Phase 7 / 1.36.4-dev.37**.
- final product merge: `b29cf18aea52228fc44e08aac5e2a7c521295f98`.
- post-merge CI `35441174227`: SUCCESS.
- next: **Phase 8 — Setup / Recovery / Backup / Takeover**.

Start Phase 8 from current main. Audit proven updater/backup/recovery owners first; extend, do not rewrite.

## Never assume
- ChatGPT history is not source of truth.
- Local remains Business Authority.
- Realtime and Deferred remain separate.
- Public is not a business/personnel clone.
- mature print/updater/backup state machines must not be casually rewritten.
- no phase is COMPLETE without final-head and post-merge gates.
