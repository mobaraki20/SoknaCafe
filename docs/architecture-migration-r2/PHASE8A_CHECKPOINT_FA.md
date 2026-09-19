# Phase 8A Checkpoint — Installation Identity + Recovery Set Metadata

Version target: 1.36.4-dev.38
Status: VALIDATION

## Identity boundary
- dedicated Ed25519 installation identity lives under the private SOKNA data root.
- public metadata and private key are separate files.
- incomplete identity fails closed.
- private installation key is not a DB field and is never placed in backup archive.

## Recovery Set enrichment
Existing backup v3 remains the mature archive engine. Its manifest gains recovery_metadata containing:
- public installation identity metadata.
- Public binding ID/host and shared-secret fingerprint only.
- media/theme references.
- Center/Accommodation connection metadata and secret fingerprints only.

Important: system/app.key remains portable because existing encrypted integration secrets depend on it. This is intentionally separate from the new machine installation private key.

## Next
After 8A final-head + post-merge gates, Phase 8B builds Windows new/recover setup orchestration on top of these owners.
