> **مرجع فعلی تطبیق — 2026-09-25:** شاخه محلی `work/reconcile-dev39`، نسخه سورس dev.39، مبتنی بر تاریخچه گیت‌هاب و پیشرفت‌های بسته. ابتدا `docs/handoffs/DEV39_RECONCILIATION_2026-09-25_FA.md` را بخوانید (مسیر نسبت به ریشه مخزن). ادامه‌ها و دستورهای متعارض زیر سوابق تاریخی‌اند؛ Phase8B کامل نشده، WiX مجوز اجرا نگرفته و Windows CI جدید انجام نشده است.

# Copy/Paste Prompt for the Next Agent

Continue the current **local SOKNA Cafe reconciliation workspace**; do not restart from dev.26 and do not assume old GitHub-only handoffs are newer than this file.

Working source:
- current engineering version: `1.36.4-dev.39`
- active local branch: `work/phase8b-closure-dev39`
- committed predecessor/baseline entering closure: `a579fcf21878413998deb7158eb9a5a29fd8d32a`
- source-hardening implementation checkpoint: `268957d42d281b0f677de5a32be6c41e36b108f5` (packaged head may contain later handoff-only docs; always run `git rev-parse HEAD`)
- immediate objective: Phase 8B Closure — provider freeze → frozen release lock → Windows RC build/acceptance
- `dev.38` and earlier GitHub/local snapshots are provenance/predecessors, not the current installable identity
- `dev.26`: Business Behavior + UI DNA/provenance only
- R2: frozen architecture authority, subject only to explicit later owner decisions documented in current handoffs

Active work: **Phase 8B — Windows New / Recover Setup Orchestration**, branch `phase/8b-windows-setup`, draft PR #18. Continue this branch, not a new branch from main.

Latest verified source `a3435d187717ffdc1d2fc2914ab81a341e7742b3`; CI `35504331920` all three jobs SUCCESS. Native Inno platform preview install/ARP/shortcuts/cached Repair/uninstall/data-preservation passed on a Windows fixture. Artifact `sokna-platform-preview-unsigned` is not a clean-machine installer. No mandatory cost; test use; Inno chosen, WiX superseded. Read CURRENT_STATUS and PHASE8B_IN_PROGRESS_HANDOFF on the active branch for evidence and remaining work. Next: unified diagnostics, then clean-machine prerequisites/web stack/DB and New/Recover, same-version complete app repair, Persian copy and UAT. Preserve existing owners and keep GitHub handoffs updated. Full phase is not complete or merged.

Locked owner decisions:
- every previous **SOKNA Design System** is REJECTED; build a new Persian-first/RTL-first DS from good dev.26 DNA, fix defects, standardize and enforce it.
- Print Worker is **internal SOKNA Local**, not a separately installed Pagent product. Read `docs/architecture-migration-r2/PHASE7R_PRINTING_RECONCILIATION_FA.md`. Preserve the audited 6.2.5 Print API/SQLite/reconciliation/Winspool semantics; do not reimplement them in PHP.
- no Windows/.NET/physical-printer result may be called PASS unless actually executed.

Read first:
1. `docs/handoffs/LOCAL_WORKSPACE_BASELINE_2026-09-24_FA.md`
2. `docs/handoffs/CURRENT_STATUS_FA.md`
3. `docs/architecture-migration-r2/R2_IMPLEMENTATION_RECONCILIATION_2026-09-24_FA.md`
4. `docs/architecture-migration-r2/PHASE7R_PRINTING_RECONCILIATION_FA.md`
5. `docs/handoffs/MASTER_HANDOFF_FA.md`

Critical identity rule: portable `app.key` is not the machine installation private key. The Phase 8A private installation key must never be cloned by Recovery Set or machine replacement.

## Verified installer diagnostic snapshot — 2026-09-20
Source `0557e64336d1ac1be958787ebe76f895df4647a2`; CI https://github.com/mobaraki20/SoknaCafe/actions/runs/35504980480 — Windows, Linux/browser and MariaDB all completed SUCCESS.

The existing private support.zip now includes a redacted native installer log snapshot, summary.json and events.jsonl. Tests passed for a held-open log, credential canary redaction, missing-log preservation of the original setup failure, and actual native preflight/install/Repair ZIP contents. Summary exposes bundle path/status and snapshot scope. The snapshot ends at setup-owner completion; early wizard/bridge failures and later Inno finalization/removal failures remain open. Do not call this full installer diagnostic coverage.

Delivery/source map: [DELIVERY_STATUS_FA.md](DELIVERY_STATUS_FA.md). Print Agent's independent repository was located at https://github.com/mobaraki20/Pagent ; published release v6.2.5 provides Setup.exe and source.zip. Release metadata reports target `11708956df922e02ad8067ad281950dae33bab64` and Setup SHA256 `d34241a4b3ed8b3d1cf5106eedee39d97d905766b067013b9402f3f18e8f0929`. These are observed release metadata, not a new binary hash verification or local-installer compatibility test. Do not recreate the Agent or assume old AGENT_DEPENDENCIES notes describe the latest release.

Next: finish diagnostics outside the owner window, then complete the clean-machine prerequisite/web stack/database and New/Recover package, Public deployment bundle, exact Agent integration and same-version application repair. Full Phase 8B remains IN PROGRESS; PR #18 is draft/unmerged. User is not responsible for manually assembling missing packages. Full initial-handoff traceability and real-device UAT remain open.


Continue from the newest packaged Git commit. **Do not redo Phase7R, Tax, Batch Purchase, Expense, SCDS foundation or WIN-06 source hardening; those are already closed in source.**

Immediate next evidence sequence:
1. On the exact dev.39 head, run the explicit `windows-prerequisite-freeze` Windows workflow. Do not hand-author `release-lock.json`.
2. Review the generated hash/size/version/Authenticode evidence and commit the frozen `installer/windows/prerequisites/release-lock.json` only if it matches the candidate and exact VERSION.
3. On that exact lock-bearing head, run `windows-rc-full-stack` and record the New → external Apache reload → Repair/HTTPS → Recovery Set → Recover → Repair/HTTPS evidence.
4. Run the MSI/Burn package job including synthetic dev.38→dev.39 major-upgrade acceptance, then signing/timestamp evidence.
5. Keep cashier/printer/reboot/network field UAT and Phase 8C Takeover explicitly separate.

Never convert `NOT_RUN`, environment blocker, source-authored test, or missing Windows evidence into PASS.
