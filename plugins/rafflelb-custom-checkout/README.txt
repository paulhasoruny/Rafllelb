RaffleLB Custom Checkout v4.8.13

Country / Region SelectWoo dark styling:
- Closed Country / Region control now matches the 46px dark checkout inputs with vertically centered selected text and aligned arrow.
- The body-appended SelectWoo dropdown, search field, results, selected option, highlight state, borders, and scrollbar use checkout-scoped dark styling.
- Country data, field definitions, SelectWoo initialization, validation, checkout logic, and payment/order behavior are unchanged.

RaffleLB Custom Checkout v4.8.12

Final Contact Details UX:
- Contact Details now renders before Payment inside the existing checkout left column on every screen size.
- The disclosure starts open for all customers and is never auto-closed after prefill, timeout, or updated_checkout.
- Contact-field validation errors force the disclosure open; order-summary positioning and checkout processing are unchanged.

RaffleLB Custom Checkout v4.8.11

Mobile Contact Details UX:
- At 767px and below, Contact Details and its fields appear before Payment and the single WooCommerce Place Order action.
- The existing Contact Details disclosure starts open on mobile and reopens when checkout validation reports an error.
- Desktop order, field definitions, validation, gateways, totals, checkout AJAX, and order processing are unchanged.

RaffleLB Custom Checkout v4.8.10

Typography/readability upgrade:
- Checkout-owned typography now uses var(--rl-font, "Manrope", sans-serif).
- Improved responsive title, section, form, payment, order-summary, total, coupon, notice, and button hierarchy.
- Checkout structure, fields, calculations, gateway/COD rules, validation, AJAX, metadata, and processing logic are unchanged.

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
