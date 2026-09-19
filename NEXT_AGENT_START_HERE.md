# SOKNA — NEXT AGENT START HERE

این ریپو باید بدون تاریخچه ChatGPT قابل ادامه باشد.

## Source of truth
1. current `main`
2. `docs/handoffs/MASTER_HANDOFF_FA.md`
3. `docs/handoffs/CURRENT_STATUS_FA.md`
4. latest active/completed phase handoff
5. R2 contracts in `docs/architecture-migration-r2/`

## Current exact state — 2026-09-19
- verified `main`: `3b1c35c8bf7e06a512194559736fbba0303fec46`
- completed through: **Phase 6B / 1.36.4-dev.33**
- active in-progress work: **Phase 6C — Server-persistent Table Draft**
- active branch: `phase/6c-table-draft`
- active branch head: `756d805912351d6dd539f9922e9bd97144369b63`
- branch is 30 commits ahead of main and 0 behind.
- latest branch CI: `35426707212`
  - Windows runtime/TLS: PASS
  - Public + Local MariaDB: PASS
  - Linux full regression: FAIL

DO NOT recreate Phase 6C from main. Continue the existing branch.

Read in this order:
1. `docs/handoffs/NEXT_AGENT_PROMPT_FA.md`
2. `docs/handoffs/MASTER_HANDOFF_FA.md`
3. `docs/handoffs/CURRENT_STATUS_FA.md`
4. `docs/handoffs/PHASE6C_IN_PROGRESS_HANDOFF_FA.md`
5. `docs/architecture-migration-r2/PHASE6C_DESIGN_NOTES_FA.md`
6. `DEVELOPER_READ_FIRST_FA.md`
7. R2 API/Schema/Risk/Implementation contracts.

Then:
```bash
git clone https://github.com/mobaraki20/SoknaCafe.git
cd SoknaCafe
git fetch --all
git checkout phase/6c-table-draft
git reset --hard 756d805912351d6dd539f9922e9bd97144369b63
```

Do not ask the user to repeat architecture/product decisions already frozen in the handoff. Only escalate a genuine product choice not decided by current source + R2 + phase handoffs.
