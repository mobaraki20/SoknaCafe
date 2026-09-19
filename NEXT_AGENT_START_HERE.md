# SOKNA — NEXT AGENT START HERE

این ریپو باید بدون تاریخچه ChatGPT قابل ادامه باشد.

## Current exact state — 2026-09-19
- completed through: **Phase 7 / 1.36.4-dev.37**
- final Phase 7 product merge: `b29cf18aea52228fc44e08aac5e2a7c521295f98`
- post-merge CI: `35441174227` — SUCCESS
- required gates: Windows PASS / Public+Local MariaDB PASS / Linux+Browser PASS
- next work: **Phase 8 — Setup / Recovery / Backup / Takeover**

Read in this order:
1. `docs/handoffs/MASTER_HANDOFF_FA.md`
2. `docs/handoffs/CURRENT_STATUS_FA.md`
3. `docs/handoffs/PHASE7_HANDOFF_FA.md`
4. `DEVELOPER_READ_FIRST_FA.md`
5. R2 Implementation/API/Schema/Risk contracts.

Then fetch current `main` and create a new Phase 8 branch.
Do not reuse Phase 7 branches.

Phase 8 begins by auditing existing installer/updater/backup/recovery and identity/pairing owners. Preserve mature engines and extend them for Windows new/recover install, enriched Recovery Set/PITR and machine/Public takeover.
