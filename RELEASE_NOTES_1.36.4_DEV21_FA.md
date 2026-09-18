# Sokna 1.36.4-dev.21 — Print Failover/Heartbeat Remediation

- Heartbeat optional null compatibility without weakening required validation.
- Authoritative `last_heartbeat_at`; Claim traffic cannot make printer readiness fresh.
- Canonical Agent/queue readiness with reason-specific failures.
- Transactional Promote Fallback action and safer Agent retirement lifecycle.
- Retired Agent archive separated from operational selectors.
- Agent 6.2.3 remains candidate until its installer/pair tests are verified.

Hardware printing and Real API pair acceptance remain UAT/acceptance-server gates.
