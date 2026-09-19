# Phase 7C Checkpoint — Center Outbound User Projection

Version target: 1.36.4-dev.37
Status: COMPLETE

Goal: move routine Cafe user discovery toward outbound Local→Center transport without breaking older Center installations.

Compatibility:
- legacy GET /api/sokna_center_users.php remains a fallback.
- outbound projection activates only when strict Probe reports capabilities.user_projection_v1=true.
- no capability means no outbound call and no disruption.

Runtime worker: tools/center-projection-worker.php
Target endpoint: POST /api/s2s/cafe_users_sync.php
Authentication: existing paired secret with SOKNA-S2S, issuer Cafe, audience Center, purpose user_projection.
Snapshot is SHA-256 content-versioned.

Allowed fields only: local_user_id, display_name, role, active, updated_at.
No username/password/session/CSRF/API credential or HR data is projected.

Cafe remains Local user authority. Center remains HR/payroll authority. Public is not involved.

Validation:
- tests/phase7c-center-outbound-contract.py
- tests/phase7c-center-projection-local.php
- all existing Center handoff/entitlement/payroll/inbound compatibility tests remain active.

Validation evidence:
- PR #14 merged.
- merge commit: b29cf18aea52228fc44e08aac5e2a7c521295f98.
- post-merge main CI 35441174227: Windows PASS / Public+Local MariaDB PASS / Linux+Browser PASS.
