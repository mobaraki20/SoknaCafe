# SOKNA 1.36.4-dev.36 — Phase 7B

## Accommodation Transport Adapter
- Extracted HTTPS mechanics from the Accommodation business owner into `includes/accommodation_transport.php`.
- Kept `accommodation_http_request()` as a compatibility wrapper so callers and business semantics do not change.
- Preserved Bearer auth, tracking IDs, TLS verification, curl/stream fallback and test transport injection.
- No Accommodation persistence, settlement, ambiguity classification or recovery logic moved into the transport layer.
- No schema change.

## Validation
See `docs/architecture-migration-r2/PHASE7B_CHECKPOINT_FA.md`.
