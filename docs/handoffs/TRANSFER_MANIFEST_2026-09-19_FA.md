# SOKNA Transfer Manifest — 2026-09-19

هدف این Manifest: انتقال پروژه به یک ایجنت جدید بدون نیاز به تاریخچه ChatGPT.

## Repository
`mobaraki20/SoknaCafe`
Visibility: Public
Default branch: `main`

## Safe completed baseline
Main SHA at transfer time:
`3b1c35c8bf7e06a512194559736fbba0303fec46`

Release:
`1.36.4-dev.33`

Completed:
- Phase 0
- Phase 1
- Phase 2 / PR #1 / merge `f47bde53...`
- Phase 3 / PR #2 / merge `25e01c0d...`
- Phase 4 / PR #3 / merge `124b569b...`
- Phase 5 / PR #4 / merge `652a950e...`
- Phase 6A / PR #5 / merge `32e9b3b2...`
- Phase 6B / PR #7 / merge `afa84a33...`
- Master zero-context GitHub handoff / PR #8 / merge `3b1c35c8...`

Latest main CI:
- run `35426024958`
- SUCCESS

## Active unmerged work
Phase 6C — Table Draft
Branch:
`phase/6c-table-draft`

Head:
`756d805912351d6dd539f9922e9bd97144369b63`

Position:
- ahead of main: 30
- behind main: 0

Latest CI:
`35426707212`

Jobs:
- Windows: SUCCESS
- Public + Local MariaDB: SUCCESS
- Linux full regression: FAILURE

First failing contract:
`tests/itemized-settlement-contract.py`

See:
`docs/handoffs/PHASE6C_IN_PROGRESS_HANDOFF_FA.md`

## Source recovery
Baseline:
```bash
git clone https://github.com/mobaraki20/SoknaCafe.git
cd SoknaCafe
git checkout 3b1c35c8bf7e06a512194559736fbba0303fec46
```

Continue in-progress Phase 6C:
```bash
git fetch --all
git checkout phase/6c-table-draft
git reset --hard 756d805912351d6dd539f9922e9bd97144369b63
```

## Required reading
- `NEXT_AGENT_START_HERE.md`
- `docs/handoffs/NEXT_AGENT_PROMPT_FA.md`
- `docs/handoffs/MASTER_HANDOFF_FA.md`
- `docs/handoffs/CURRENT_STATUS_FA.md`
- `docs/handoffs/PHASE6C_IN_PROGRESS_HANDOFF_FA.md`
- R2 contracts.

## Handoff update rule
Every next subphase must leave GitHub independently sufficient for another agent:
- exact source/branch/SHA
- what was completed
- what was not
- PR/CI evidence
- current failure if any
- frozen decisions
- owner map
- exact next action
- UAT limitations
