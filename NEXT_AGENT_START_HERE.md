# SOKNA — NEXT AGENT START HERE

Completed product checkpoint: Phase 8A / 1.36.4-dev.38.
Active work: Phase 8B on `phase/8b-windows-setup`, draft PR #18.

Do not restart 8B from main. Fetch the existing branch and check the newest head/CI:
https://github.com/mobaraki20/SoknaCafe/pull/18

Read in order:
1. `DEVELOPER_READ_FIRST_FA.md`
2. `docs/handoffs/MASTER_HANDOFF_FA.md`
3. `docs/handoffs/CURRENT_STATUS_FA.md`
4. `docs/handoffs/PHASE8B_IN_PROGRESS_HANDOFF_FA.md` on the active branch
5. `docs/architecture-migration-r2/WINDOWS_INSTALLER_ACCEPTANCE_FA.md`
6. `docs/architecture-migration-r2/WINDOWS_PACKAGING_OWNERSHIP_FA.md` — Inno Setup selected; owner requires no mandatory cost and current test use.
7. Phase 8 design notes and frozen R2 contracts.

Verified hardening source: `2fdb500d3240a1c2adde30b291fe4377d78fca44`; CI `35477813459` SUCCESS on all three gates. This batch implements setup preflight, service/TLS-safe repair, diagnostics and real Windows fault tests. Continue with Inno Setup packaging and explicit Repair after reading the acceptance contract; recheck CI if the branch has advanced. The service-host artifact is not a complete installer; shortcuts, Installed apps lifecycle, prerequisites and updater ownership remain acceptance gates.
Persist every next step in GitHub handoffs, with tested SHA/run and the precise next action.

Latest implementation: Inno platform preview, source `67b32f767bd254bd26bb845302c8490223698e71`, CI `35491459618` pending at checkpoint. Inspect/fix that run before starting further work. Preview requires an already configured app; clean-machine installation remains open.
