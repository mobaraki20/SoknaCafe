# Agent 6.2.3 build blocker

Target baseline required by the remediation contract:
- Agent 6.2.2
- exact source commit `5a58a10f6326698f80c3408a7a69229754e5c9d4`

In this execution environment:
- the exact Agent repository could be inspected remotely, but `git clone`/raw materialization was unavailable to the build container;
- no .NET/Windows build/runtime was available;
- Library contained old Pagent 6.1.1 ZIPs only; they were deliberately **not** used because the contract forbids overwriting the 6.2.2 baseline with 6.1.x.

Consequences:
- no 6.2.3 source commit/installer/checksum is claimed;
- G01-G12 are `BLOCKED_EXTERNAL`;
- A53/B53 are `BLOCKED_EXTERNAL` until an acceptance server plus target Agent exists;
- Agent 6.2.3 remains Candidate/UNVERIFIED and must not be advertised as Recommended.
