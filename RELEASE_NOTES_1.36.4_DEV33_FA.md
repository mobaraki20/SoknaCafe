# SOKNA 1.36.4-dev.33 — Phase 6B

## Explicit Sellable Kind
- Added explicit `menu_item | service_item` classification.
- Added admin selector «نوع مورد».
- Added strict write validation and legacy-safe read normalization.
- Added kind projection to shared catalog/admin payload.
- Added `sellable_kind_snapshot` to newly committed order lines.
- Default internal services are explicitly classified.
- No category/station inference is used.
- No automatic takeaway packaging fee was introduced.
- Historical order rows are not rewritten.

## Migration
Apply:
`docs/architecture-migration-r2/PHASE6B_LOCAL_MIGRATION.sql`

## Validation
See:
- `docs/architecture-migration-r2/PHASE6B_CHECKPOINT_FA.md`
- `docs/handoffs/PHASE6B_HANDOFF_FA.md`
