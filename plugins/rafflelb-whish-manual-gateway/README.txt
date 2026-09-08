RaffleLB Whish Manual Gateway v1.1.4
- Typography cleanup: normalized invalid font-weight values (950) to 900. No payment/verification logic changed.

RaffleLB Whish Manual Gateway v1.0.0

Install:
1. WordPress Dashboard -> Plugins -> Add New Plugin -> Upload Plugin.
2. Upload the ZIP and activate it.
3. WooCommerce -> Settings -> Payments.
4. Open "Whish Checkout – Manual Gateway".
5. Enter the Whish recipient/account name and Whish phone/account number.
6. Save changes and enable the gateway.

Behavior:
- Customer must provide a Whish transaction reference OR a payment screenshot.
- Both are accepted.
- JPG/JPEG, PNG, WEBP, HEIC, HEIF accepted.
- Default max screenshot size: 8 MB.
- Order is placed On hold until manual verification.
- Admin order screen shows the reference and screenshot link.
- No automatic payment confirmation.

Designed for classic WooCommerce checkout.
