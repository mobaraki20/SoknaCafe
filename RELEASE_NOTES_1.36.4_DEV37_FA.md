# SOKNA 1.36.4-dev.37 — Phase 7C

## Center outbound adaptation
- Added capability-negotiated Local→Center Cafe user projection under Runtime.
- Projection uses the existing paired HMAC trust relation and SOKNA-S2S purpose user_projection.
- Snapshot contains only local user ID, display name, role, active state and updated timestamp.
- Credentials, sessions and HR data are never projected.
- Existing Center→Cafe directory endpoint remains as compatibility fallback until Center-side migration is confirmed.
- Public remains outside the personnel data path.

See docs/architecture-migration-r2/PHASE7C_CHECKPOINT_FA.md.
