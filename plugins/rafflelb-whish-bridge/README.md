# RaffleLB Whish Bridge 0.1.14

- Adds a customer-facing **Cancel payment** action on the Whish waiting screen. Cancellation is blocked once a genuine signed Whish event has been detected or the order is paid.
- Safe cancellation restores the order line items (and coupons where possible) to the WooCommerce cart, cancels/trashes the unpaid Whish order, and keeps raffle reservations for **5 minutes** by default. A new Whish checkout starts the normal full payment window again.
- Uses the same per-order lock key as payment confirmation to avoid racing a cancellation against `payment_complete()`.
- Adds the Whish Money logo beside the checkout payment method. The default uses the high-resolution public Whish logo sourced from Whish Money via Wikimedia Commons and can be replaced with a Media Library URL in settings.
- Adds **Checkout Whish Logo URL** and **Cancelled Payment Cart Grace (minutes)** settings.
- Existing QR, Android bridge signing, matching rules, duplicate/ambiguity protection, WooCommerce payment completion, paid-order hooks, and email behavior are unchanged.

# RaffleLB Whish Bridge v0.1.10

Private pilot integration for RaffleLB. It adds a WooCommerce **Pay with Whish Money** method and accepts signed incoming-payment notification events from the companion Android bridge app.

## Safety model

- Android uses Android's official `NotificationListenerService`; no Accessibility, screen control, Whish credentials, or Whish API reverse engineering.
- The bridge accepts events only from the exact official package `money.whish.android`.
- Every phone-to-site request is HMAC-SHA256 signed with Bridge ID, timestamp, nonce, HTTP method, REST route and body.
- Request timestamps and nonces are checked to reduce replay risk.
- Event IDs are unique/idempotent.
- A separate payment-content fingerprint stops a rapidly re-posted notification from auto-confirming a second order; suspicious repeats are marked `possible_duplicate` for review.
- Exact order amount, exact currency and a narrow order/payment time window are required.
- The payer phone field at checkout is optional. Customers may pay from their own Whish account or another person's account. If a payer phone was entered and Whish also provides a sender phone, a mismatch is a hard rejection; it never falls back to amount-only matching.
- If multiple pending orders match, the event becomes `ambiguous`; no order is paid.
- An order already claimed by one detected payment event cannot be silently claimed by another event.
- **Automatic Confirmation is OFF by default** and is additionally blocked until the administrator explicitly marks a genuine incoming Whish notification as parser-verified.
- Sender phone can be used as an extra matching signal when available. The verified incoming Whish notification format captured during the pilot exposed sender name but not sender phone, so sender-phone-required auto confirmation should remain OFF for that format.
- In manual pilot mode, a detected payment prevents the scheduled expiry job from cancelling the order while verification is pending.
- Manual confirmation re-checks amount, currency, payment window and available sender phone immediately before calling WooCommerce `payment_complete()`.
- A small order-level lock prevents concurrent bridge events from completing the same order at the same time.

## RaffleLB integration

The plugin does **not** create raffle entries itself. After a verified payment it calls:

```php
$order->payment_complete( $transaction_id );
```

The current RaffleLB Draw Engine listens to WooCommerce `processing` / `completed` status changes, so its existing paid-entry allocation path remains authoritative. RaffleLB Referral & Points also listens to normal WooCommerce paid-order hooks. The bridge therefore acts only as a payment gateway / payment-verification layer.

It emits these optional integration hooks after detection / verification:

```php
do_action( 'rafflelb_whish_payment_detected', $order_id, $event );
do_action( 'rafflelb_whish_payment_verified', $order_id, $event_id, $event );
```

## Pilot setup

1. Install and activate `rafflelb-whish-bridge-0.1.10.zip`.
2. WooCommerce → Settings → Payments → **Whish Money (RaffleLB Bridge)**.
3. Enter the dedicated RaffleLB Whish receiving number. The verified official QR is bundled; the optional QR URL setting can override it.
4. Leave **Incoming Notification Verified OFF**.
5. Leave **Automatic Confirmation OFF**.
6. WooCommerce → **Whish Bridge**: copy Bridge ID and Bridge secret into the Android app.
7. Install the Android bridge on the dedicated phone and grant Notification Access.
8. Test the signed connection.
9. Create one small Whish test order and make one genuine Whish-to-Whish incoming payment.
10. Verify the exact captured notification wording and parsed amount/currency on the phone.
11. Verify the signed event in WordPress, including amount, sender if available, and raw captured notification text.
12. Manually confirm that event from the Whish Bridge admin page.
13. Confirm WooCommerce transitions to paid and the existing RaffleLB paid-order logic creates the expected raffle entry / purchase state.
14. Only after repeated successful tests should the parser-verified gate and optional Automatic Confirmation be considered.

## Important limitation

This is an unofficial notification-based bridge, not a Whish merchant API or provider-signed settlement webhook. A phone notification is not equivalent to authoritative settlement confirmation. Keep the pilot conservative and use manual confirmation until the real Whish incoming-notification format has been validated on the dedicated phone.


## 0.1.4
- Added a Custom Checkout-safe order-received fallback so Whish transfer instructions always appear even when the custom RaffleLB confirmation template does not fire WooCommerce's gateway-specific thank-you hook.
- Unpaid Whish raffle orders no longer show misleading 'Entry confirmed' hero wording on the custom confirmation page.


## 0.1.4
- Redirects unpaid Whish orders to a dedicated Whish payment waiting screen so RaffleLB Custom Checkout cannot replace/hide the payment instructions.
- Existing unpaid Whish order-received URLs are also redirected to the waiting screen.
- Paid orders return to the normal RaffleLB order-received confirmation page.


## 0.1.4 routing fix
The Whish waiting screen is now routed from the main plugin at `/whish-payment/` instead of depending on WooCommerce instantiating the payment gateway on the next request. This prevents the post-checkout redirect from falling through to the site homepage.


### 0.1.6 payment-page presentation
The virtual `/whish-payment/` route now returns a proper `Whish Payment - RAFFLELB` browser title, suppresses 404 semantics, and uses a full-bleed dark RaffleLB canvas instead of inheriting WoodMart's white/error-page background.


### 0.1.7 payment amount formatting
- The dedicated Whish payment page now forces a clear symbol-first amount such as `$1.00` in both the instruction sentence and the Amount row, independent of the WooCommerce global currency-position setting.

### 0.1.8 payment-page polish

- Highlights the exact Whish payment amount in RaffleLB lime on the waiting screen.
- Adds a compact "If you have any issue, contact support" footer below the payment instructions.
- Adds an optional Support URL gateway setting; when blank, the plugin auto-detects Contact / Contact Us / Support pages.

### 0.1.9 waiting-page redesign

- Redesigns only the dedicated `/whish-payment/` waiting page with a compact, responsive RaffleLB payment-card treatment.
- Improves amount emphasis, payment-detail rows, waiting status, customer-name note, and support-link presentation without changing payment behavior.


### 0.1.10 QR payment + unpaid-order cleanup

- Bundles the verified official Whish QR for the configured RaffleLB receiving account and shows it on `/whish-payment/` by default. An admin QR URL may still override it.
- Shows the Whish QR for scanning from another device. The unreliable same-device `Open in Whish` action has been removed.
- Clearly reminds customers to enter the exact dynamic WooCommerce order amount in Whish; the QR does not claim to prefill the amount.
- New Whish checkouts remain `Pending payment` instead of `On hold` while waiting for the bridge.
- Standard WooCommerce order emails are suppressed while a Whish order is unpaid. Once a verified bridge event is stored and `payment_complete()` runs, normal paid-order emails are allowed.
- When the configured payment window expires with no detected bridge event, the unpaid order is marked expired, cancelled, then moved to Trash so it disappears from the normal Orders list while remaining recoverable.
- A signed payment event detected before expiry still blocks automatic cleanup while verification is pending.
- Uses WooCommerce Action Scheduler for the expiry job when available, with WP-Cron as a fallback.


## 0.1.14

- Replaced the checkout icon with the supplied Whish logo, tightly cropped to remove excessive white edges while keeping the original white background.
- Enlarged the checkout logo badge slightly so the full Whish logo remains clear and readable.
- Removed the `Open in Whish` button and Android intent/deep-link handling because it was not reliably opening the Whish app.
- Kept the Whish QR and manual receiving-number fallback unchanged.

## 0.1.13
- Bundled compact Whish checkout icon from the supplied Whish logo.
- Moved the Whish icon before the payment title and styled it as a compact badge to match the Raffle Points checkout row.
- Existing custom checkout-logo URLs remain supported; the old v0.1.11 default wordmark automatically falls back to the new bundled badge.

## 0.1.15
- Replaced the checkout badge with the exact horizontal Whish Money logo supplied by the user.
- Cropped the excessive white margins while preserving the original white logo background.
- Renamed the bundled logo asset to avoid stale browser/CDN caching of the previous incorrect icon.
- Refined checkout badge proportions for clearer wordmark visibility beside the payment title.
- No payment, matching, bridge, order, QR, cancellation, or security logic changed.

## 0.1.16
- Checkout-only visual update: moved the Raffle Points and Whish Money logos to the far-right edge of their payment rows while keeping the radio button and method title on the left.
- No payment, matching, cancellation, expiry, QR, Android bridge, order, or security logic changed.
