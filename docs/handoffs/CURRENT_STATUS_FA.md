# SOKNA — Current Project Status

Updated: 2026-09-19
Repository: `mobaraki20/SoknaCafe`
Current completed release checkpoint: `1.36.4-dev.38`

## Completed through
- Phase 0–7 complete.
- Phase 8A — Installation Identity + Recovery Set Metadata. PR #16.

## Latest verified checkpoint
- merge: `485db60b71b40c475c931a9d6d056ce8a07d0df2`
- post-merge CI: `35442209529` — SUCCESS
- Windows PASS / Public+Local MariaDB PASS / Linux+Browser PASS.

Latest handoff: `docs/handoffs/PHASE8A_HANDOFF_FA.md`

## Frozen Phase 8A behavior
- machine installation identity is separate from portable `app.key`.
- installation private key lives only under private data root and is never copied into backups.
- Backup v3 remains canonical and gains safe recovery metadata only.
- mature restore/rollback/encryption engines were not rewritten.

## Active next work
Phase 8B — Windows New / Recover Setup Orchestration.

First audit/compose:
- existing `install.php` fresh install contract.
- Runtime Windows service + `provision-local-https.ps1`.
- Print Agent stable Setup contract.
- backup import/restore and updater recovery owners.
- optional Public pairing, off-server backup and Push setup.

8B must create an orchestration layer, not duplicate installer/updater/backup state machines.
After 8B, Phase 8C handles machine replacement + Public takeover and the restore/takeover drill.
