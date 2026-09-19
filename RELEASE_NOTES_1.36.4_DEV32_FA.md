# Release Notes — SOKNA 1.36.4-dev.32

## Scope
Phase 6A of Handover R2: Preparation visibility/action permission split.

## What changed
- Added canonical Preparation permission owner.
- Supervisor/Admin can monitor Kitchen and Bar globally without receiving Preparation mutation authority.
- Preparation workers can act only in assigned areas.
- Supervisor+Preparation users can see all areas but action only their assigned areas.
- Preparation feed is now read-only; refresh no longer changes adjustment delivery state.
- Preparation action buttons are gated per task/area using server-authored actionable areas.
- Mutation endpoint revalidates area authority server-side.

## Not changed
- Order confirmation semantics.
- Preparation claim business state.
- Printing.
- Settlement/Finance.
- Public Deferred or Realtime state machines.
- Existing user/capability schema.

## Validation
Required gates:
- Windows runtime/lint/contracts
- Local MariaDB permission matrix
- Linux full regression
- Browser per-area actionability test

## Upgrade
Accepted predecessor: `1.36.4-dev.31`.
Target: `1.36.4-dev.32`.
No database migration is required for this checkpoint.
