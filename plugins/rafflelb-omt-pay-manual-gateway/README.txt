RaffleLB OMT Pay Manual Gateway v1.0.1
- Typography cleanup: normalized invalid font-weight values (950) to 900. No payment/verification logic changed.

RaffleLB OMT Pay Manual Gateway v1.0.0

Manual WooCommerce payment method for OMT Pay.

Flow:
1. Customer sends the exact order total through OMT Pay.
2. Customer uploads a payment screenshot OR enters a reference phone number / transaction number.
3. Order is placed On hold for manual verification.
4. Admin verifies payment before confirming the order.

The gateway is independent from Whish Money.
