# Phase 8A Handoff — Installation Identity + Recovery Set Metadata

Status: COMPLETE
Release: `1.36.4-dev.38`
PR: #16
Merge commit: `485db60b71b40c475c931a9d6d056ce8a07d0df2`
Post-merge CI: `35442209529` — SUCCESS

## Implemented
- Dedicated Ed25519 installation identity under private SOKNA data root.
- Public identity metadata is separate from `installation.key`.
- Partial identity fails closed instead of silently regenerating.
- Backup v3 manifest now contains safe `recovery_metadata`.
- Recovery metadata includes Public binding reference/fingerprint, public installation identity, media/theme references and Center/Accommodation secret fingerprints.
- Installation private key is never archived.
- `system/app.key` intentionally remains portable because restored encrypted integration secrets depend on it; it is not the machine installation identity.
- CI explicitly installs Sodium, matching the existing installer requirement.

## Canonical owners
- `includes/installation_identity.php`
- `includes/maintenance.php`
- `tests/phase8a-recovery-identity-contract.py`
- `tests/phase8a-installation-identity-runtime.php`
- `tests/phase8a-recovery-metadata-runtime.php`

## Next
Phase 8B — Windows New / Recover Setup Orchestration.
Preserve existing `install.php`, Runtime/HTTPS provisioning, Print Agent installer contract, updater and maintenance engines. 8B should orchestrate them rather than rewrite them.
