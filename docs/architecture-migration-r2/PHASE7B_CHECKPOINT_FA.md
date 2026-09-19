# Phase 7B Checkpoint — Accommodation Transport Adapter

Version target: `1.36.4-dev.36`
Status: COMPLETE

## Goal
Separate Accommodation network transport from the Local-authoritative business contract without changing finance, settlement, ambiguity or recovery semantics.

## Ownership
- `includes/accommodation.php`: business policy, response classification, transfers, settlement/recovery and compatibility API.
- `includes/accommodation_transport.php`: HTTPS request mechanics only.

## Preserved behavior
- same `accommodation_http_request()` compatibility API.
- same Bearer credential and Cafe tracking ID.
- same remote tracking propagation.
- same HTTPS verification and curl/stream fallback.
- same injected test transport hook.
- same deterministic vs ambiguous error classification.
- no new DB tables and no Public business authority.

## Validation
- existing `tests/accommodation-api-contract.php` remains authoritative for wire/business semantics.
- existing HTTP integration and boundary contracts remain unchanged.
- new `tests/phase7b-accommodation-transport-contract.py` prevents HTTP mechanics from leaking back into the business owner.

## Validation evidence
- PR #13 merged.
- merge commit: c83df6bdfd240a29a8c8bcf6653f2b69886d2954.
- post-merge main CI 35440573461: Windows PASS / Public+Local MariaDB PASS / Linux+Browser PASS.
