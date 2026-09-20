# Copy/Paste Prompt for the Next Agent

پروژه SOKNA Cafe تا **Phase 8A / 1.36.4-dev.38** کامل شده است.
GitHub و handoffها source of truth هستند.

Valid checkpoint:
- PR #16 merged.
- main `485db60b71b40c475c931a9d6d056ce8a07d0df2`.
- post-merge CI `35442209529` SUCCESS.

Active work: **Phase 8B — Windows New / Recover Setup Orchestration**, branch `phase/8b-windows-setup`, draft PR #18. Continue this branch, not a new branch from main.

Latest verified source `a3435d187717ffdc1d2fc2914ab81a341e7742b3`; CI `35504331920` all three jobs SUCCESS. Native Inno platform preview install/ARP/shortcuts/cached Repair/uninstall/data-preservation passed on a Windows fixture. Artifact `sokna-platform-preview-unsigned` is not a clean-machine installer. No mandatory cost; test use; Inno chosen, WiX superseded. Read CURRENT_STATUS and PHASE8B_IN_PROGRESS_HANDOFF on the active branch for evidence and remaining work. Next: unified diagnostics, then clean-machine prerequisites/web stack/DB and New/Recover, same-version complete app repair, Persian copy and UAT. Preserve existing owners and keep GitHub handoffs updated. Full phase is not complete or merged.

Before coding audit `install.php`, Runtime Windows/HTTPS provisioning, Print Agent Setup contract, backup import/restore, updater recovery, Public pairing/off-server/push setup.
Preserve mature engines; implement one orchestration owner instead of duplicating install/update/backup state machines.

Critical identity rule: portable `app.key` is not the machine installation private key. The Phase 8A private installation key must never be cloned by Recovery Set or machine replacement.

## Verified installer diagnostic snapshot — 2026-09-20
Source `0557e64336d1ac1be958787ebe76f895df4647a2`; CI https://github.com/mobaraki20/SoknaCafe/actions/runs/35504980480 — Windows, Linux/browser and MariaDB all completed SUCCESS.

The existing private support.zip now includes a redacted native installer log snapshot, summary.json and events.jsonl. Tests passed for a held-open log, credential canary redaction, missing-log preservation of the original setup failure, and actual native preflight/install/Repair ZIP contents. Summary exposes bundle path/status and snapshot scope. The snapshot ends at setup-owner completion; early wizard/bridge failures and later Inno finalization/removal failures remain open. Do not call this full installer diagnostic coverage.

Delivery/source map: [DELIVERY_STATUS_FA.md](DELIVERY_STATUS_FA.md). Print Agent's independent repository was located at https://github.com/mobaraki20/Pagent ; published release v6.2.5 provides Setup.exe and source.zip. Release metadata reports target `11708956df922e02ad8067ad281950dae33bab64` and Setup SHA256 `d34241a4b3ed8b3d1cf5106eedee39d97d905766b067013b9402f3f18e8f0929`. These are observed release metadata, not a new binary hash verification or local-installer compatibility test. Do not recreate the Agent or assume old AGENT_DEPENDENCIES notes describe the latest release.

Next: finish diagnostics outside the owner window, then complete the clean-machine prerequisite/web stack/database and New/Recover package, Public deployment bundle, exact Agent integration and same-version application repair. Full Phase 8B remains IN PROGRESS; PR #18 is draft/unmerged. User is not responsible for manually assembling missing packages. Full initial-handoff traceability and real-device UAT remain open.

