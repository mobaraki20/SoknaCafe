# Handoff — Sokna Cafe 1.36.4-dev.15 / Print Agent Accept Compatibility

## Baseline
- From: `1.36.4-dev.14`
- To: `1.36.4-dev.15`
- Status: Pilot-ready / Pre-Operational
- Updater Engine: `1.5.3`

## Root Cause proven from real Agent Support Package
Windows Agent `6.1.0` was Running, API/heartbeat successful, printer `Cash / POS-80C / USB002` healthy, but `accept` repeatedly failed with `accept_validation_failed`.

Supported Agent source (`6.0.0` and `6.1.0`) creates local receipts as:
`r-` + `Guid.NewGuid().ToString("N")`.

Cafe `dev.14` accepted only `/^[A-Fa-f0-9._:-]{8,96}$/`, which rejects the literal `r` prefix. The job therefore remained in the reservation cycle and never crossed the durable accept boundary.

## Owner-based fix
Owner: `Print API v4 accept contract`.

Changes:
- Shared receipt validator in `includes/print_agent_api.php`.
- Contract: `^r-[A-Fa-f0-9]{32}$`.
- SHA-256 format validator remains exact 64 hex characters.
- Accept errors are deterministic and separated:
  - invalid receipt syntax → `invalid_local_receipt_id`
  - invalid hash syntax → `invalid_content_sha256`
  - valid hash but different from reserved Job → `content_hash_mismatch`
- Existing receipt conflict/idempotency semantics are unchanged.

Do not patch Agent, printer driver, Spooler or route mapping for this defect unless new independent evidence proves a second fault.

## Migration
Update package migration: `migrations/1.36.4-dev.15-print-accept-hotfix-reset.sql`.

Explicit Product-approved pre-operational cleanup only:
- clears `print_attempts`
- clears `print_claim_requests`
- clears `print_jobs`
- resets AUTO_INCREMENT for those three tables

Preserves agents/destinations/templates and all non-print data.

Before running update:
1. Stop Windows Print Agent.
2. Clear Windows test print queue.
3. Install update.
4. Restart Agent.
5. Send exactly one test Job first.

## Required UAT
- Real MySQL updater migration.
- Agent 6.1.0 real flow: `reserved → claimed → started → submitted`.
- Physical output from actual printer.
- Customer receipt and preparation destination separately.

`submitted` is not physical-print confirmation.

## Escaped defect gate
- `tests/print-v4-agent61-accept-contract.py`
- `tests/print-agent-receipt-validator.php`
- `print_agent_receipt_contract_parity` in defect registry.

## Locked rules
- Modular Monolith.
- Owner-based root fixes only.
- No patch stacking.
- Test not run = Not tested.
- House/Finance/Menu unaffected by this hotfix.
