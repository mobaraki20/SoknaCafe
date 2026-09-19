# SOKNA 1.36.4-dev.38 — Phase 8A

## Installation identity + Recovery Set metadata
- Added a dedicated Ed25519 installation identity under the private SOKNA data root.
- Installation public metadata and private key are stored separately; partial identity fails closed.
- Backup v3 now carries recovery metadata for Public binding, public installation identity, media/theme references and integration fingerprints.
- Relay shared secret and installation private key are not exported in recovery metadata.
- Existing portable app.key remains in the secure backup because it is required to decrypt restored integration secrets; it is distinct from the machine installation identity.
- No schema change.

See docs/architecture-migration-r2/PHASE8A_CHECKPOINT_FA.md.
