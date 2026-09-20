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
