RaffleLB Custom Checkout v4.8.6

Explicit tangible/digital override for Cash on Delivery:
- rlcc_item_is_voucher_or_gift_card() now checks a per-product `_rafflelb_item_type` meta value ('tangible'/'digital') first, before falling back to its existing voucher-category-slug guess.
- Lets RaffleLB Raffle Manager's "Delivery Type" field set this directly per raffle instead of relying on the product being filed under a vouchers/gift-cards category.
- No change for any product that doesn't have this meta set - the original category-based check still applies exactly as before.

RaffleLB Custom Checkout v4.8.5

Typography cleanup:
- Normalized invalid font-weight values (950, 1000) to 900, the heaviest weight actually loaded for Inter.
- No layout, presentation structure, or payment logic changed.

RaffleLB Custom Checkout v4.6.1

Whish layout sync:
- Whish now uses the same stacked visual structure as OMT Pay.
- 3 full-width step rows.
- Screenshot/reference section full width below the steps.
- Verification notice below.
- Removed the old two-column Whish layout.
- Desktop and mobile aligned with OMT Pay presentation.
- Payment logic unchanged.
