# SOKNA — NEXT AGENT START HERE

این ریپو باید بدون تاریخچه ChatGPT قابل ادامه باشد.

## Current exact state — 2026-09-19
- completed through: **Phase 6C / 1.36.4-dev.34**
- Phase 6C merged PR: #10
- product merge commit: `ccf0656655702a0b175a7cb9d7521fcb808745b1`
- product post-merge CI: `35438494232` — SUCCESS
- required gates: Windows PASS / Public+Local MariaDB PASS / Linux+Browser PASS
- next work: **Phase 7 — Printing / Notifications / Integrations**

Read in this order:
1. `docs/handoffs/MASTER_HANDOFF_FA.md`
2. `docs/handoffs/CURRENT_STATUS_FA.md`
3. `docs/handoffs/PHASE6C_HANDOFF_FA.md`
4. `DEVELOPER_READ_FIRST_FA.md`
5. R2 Implementation/API/Schema/Risk contracts.

Then fetch current `main` and create a new Phase 7 branch. Do not reuse `phase/6c-table-draft` and do not follow the superseded in-progress Phase 6C handoff.

Phase 7 begins with an audit of printing, notification, Accommodation and Center owners. Preserve the mature printing queue/state machine while internalizing worker ownership incrementally.
