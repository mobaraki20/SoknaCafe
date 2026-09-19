# Copy/Paste Prompt for the Next Agent

پروژه SOKNA Cafe تا **Phase 8A / 1.36.4-dev.38** کامل شده است.
GitHub و handoffها source of truth هستند.

Valid checkpoint:
- PR #16 merged.
- main `485db60b71b40c475c931a9d6d056ce8a07d0df2`.
- post-merge CI `35442209529` SUCCESS.

Next work: **Phase 8B — Windows New / Recover Setup Orchestration**.

Before coding audit `install.php`, Runtime Windows/HTTPS provisioning, Print Agent Setup contract, backup import/restore, updater recovery, Public pairing/off-server/push setup.
Preserve mature engines; implement one orchestration owner instead of duplicating install/update/backup state machines.

Critical identity rule: portable `app.key` is not the machine installation private key. The Phase 8A private installation key must never be cloned by Recovery Set or machine replacement.
