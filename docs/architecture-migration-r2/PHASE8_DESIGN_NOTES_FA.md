# Phase 8 Design Notes — Setup / Recovery / Backup / Takeover

Status: IN PROGRESS
Branch family: phase/8*

## Frozen goal
- Windows install new/recover flows.
- optional Public pairing/printer/offsite/push setup.
- enriched Recovery Set/PITR.
- machine replacement with fresh installation identity and explicit Public takeover.
- final checkpoint requires restore + takeover drill.

## Existing mature owners to preserve
- includes/maintenance.php: backup integrity, secure .skb export/import, restore, emergency recovery point and health check.
- includes/updater_engine/1.5.3/: staging/validation/recovery-point/rollback.
- install.php: existing fresh web install contract.
- Runtime/Windows HTTPS provisioning and worker supervision.
- Relay shared-secret transport remains compatible until takeover migration is proven.

## 8A — Installation identity + enriched Recovery Set
- introduce a separate installation signing identity in private data root.
- app.key remains portable because it decrypts integration secrets; it is NOT the machine installation private key.
- installation private key is never copied into backup/recovery archives.
- backup manifest carries public identity metadata, Public binding reference/fingerprint, media/theme references and integration secret fingerprints only.
- partial/corrupt installation identity fails closed instead of silently regenerating.

## 8B — Windows New / Recover setup flow
- orchestrate existing fresh install, Runtime service/HTTPS, optional Agent/Public/offsite/push steps.
- Recover flow installs current binaries/config first, generates a fresh installation identity, then imports/restores Recovery Set.
- preserve updater/backup engines; setup orchestrates them.

## 8C — Machine replacement + Public takeover
- old machine private identity is never cloned.
- replacement proves restored business state plus fresh identity.
- Public takeover is explicit, atomic, auditable and revokes/retire old binding only after new ownership is proven.
- final drill must demonstrate restore + takeover + old identity rejection.
