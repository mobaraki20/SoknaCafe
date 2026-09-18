# Baseline Test Report

Source: `1.36.4-dev.26`

- PHP lint: PASS (175)
- JS syntax: PASS (35)
- Unit: PASS (103)
- Supply modular: PASS
- UI conformance: PASS
- UI language: FAIL (`Attempt` leaked in `admin/printing.php`)
- MariaDB-dependent acceptance: BLOCKED_ENVIRONMENT (`pdo_mysql/mysqli` unavailable in audit environment)

See `raw/baseline-dev-gate.txt` and `raw/environment.txt`.
