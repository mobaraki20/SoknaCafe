# Handoff — Settlement Flow Preview V17

Baseline: `1.36.4-dev.6` + V16 Order Context.

## Behavior changes
- Itemized settlement no longer exposes a separate user Review step.
- Server-side `checkout_itemized_review` is preserved and runs automatically before payment; checkout remains a distinct persisted request.
- The primary CTA shows the server-reviewed payable amount, including allocated discount.
- After a partial itemized payment, the itemized modal stays open, refreshes the account, and shows remaining items for the next guest.
- Once itemized settlement has started, the table CTA opens itemized settlement directly instead of showing destination choices again.
- `افزودن قلم جاافتاده` is available inside the active itemized settlement flow.
- Returning from late-accounting preserves `open_table` and resumes the itemized settlement modal.

## Safety kept
- Allocation owner and finance engine unchanged.
- Server review remains authoritative.
- Expected session/remaining/signature checks remain required.
- Idempotent request IDs and reconciliation remain active.
- Late-accounting permission/session/preparation-suppression rules unchanged.
