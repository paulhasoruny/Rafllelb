# RaffleLB Auth 0.1.3 QA

Use a non-production WordPress staging site with WooCommerce active.

1. Activate the plugin and confirm the `wp_rafflelb_auth_phones` table (prefix may differ) exists with a primary key on `phone`.
2. In **Settings → RaffleLB Authentication**, enable **NOT FOR PRODUCTION** test mode and select the development adapter.
3. Open My Account logged out at 360 px, 390 px, 430 px, tablet, and desktop widths. Confirm no horizontal scrolling and complete each keyboard-only flow.
4. Registration: request a code, read it only from the administrator settings page, verify, then create an account with an email and matching 8+ character passwords.
5. Confirm the user has the WooCommerce `customer` role and `_rafflelb_phone`, `_rafflelb_phone_verified`, `_rafflelb_phone_verified_at`, and `billing_phone` metadata.
6. Confirm the stored canonical phone starts with `+961`; retry registration with spaces/dashes and the same digits and confirm it is rejected.
7. Confirm account creation fails when the OTP transaction is absent, unverified, expired, for another purpose, or already consumed.
8. Enter five wrong codes. Confirm the transaction is invalidated and a new code is required.
9. Confirm a resend within 60 seconds is blocked, the sixth phone send in an hour is blocked, and the configured IP limit is enforced.
10. Confirm the OTP transient contains a WordPress password hash rather than the six-digit code. Confirm frontend responses, HTML, URLs, browser storage, and WordPress logs contain no OTP.
11. Confirm phone plus password login succeeds, a wrong password fails generically, and login never asks for an OTP.
12. Reset the password by phone. Confirm the verified transaction can update only its server-bound user and is consumed afterward.
13. Try password reset for an unknown but valid phone. Confirm the initial response is identical in substance and does not disclose account existence.
14. Confirm a legacy WooCommerce customer can still submit username/email plus password through the Auth form.
15. Confirm `/wp-login.php` and WordPress administrator password recovery are unchanged and never require an OTP.
16. Disable test mode and confirm the test adapter is unavailable. Set `WP_ENVIRONMENT_TYPE=production` and confirm test mode cannot be enabled.
17. Replace the provider only through the `rafflelb_auth_sms_providers` filter and the provider interface; do not add provider calls to flow classes.

## 0.1.1 security regression cases

1. Compare known- and unknown-phone reset-send calls with the disabled provider, development provider, and a filtered provider that returns `WP_Error`. Confirm each syntactically valid phone receives HTTP success with only `transaction`, `masked_phone`, and the same generic `message`; no provider error or account data may appear.
2. Advance a five-minute OTP transaction to 4m50s, verify it, then complete registration/reset more than ten seconds later but within the ten-minute verified window.
3. Repeat a successful verify request. Confirm it returns the same successful phone state without warnings, attempt changes, or a hash lookup.
4. Confirm unverified OTPs still fail after `expires`, verified transactions fail after `verified_expires`, and completed transactions fail after consumption.
5. With Account 0.1.6 active, inspect logged-out My Account markup: only `[data-rl-auth]` is customer-visible, the native `#customer_login` is absent, and Account's `rl-glass-login`, `rl-glass-stage`, and `rl-glass-shell` classes are not created. Log in and confirm the authenticated Account dashboard/navigation still work.

## 0.1.2 login regression cases

1. Confirm phone mode is the default, displays `+961`, uses `type="tel"`, `inputmode="tel"`, and authenticates a verified canonical phone with the correct password.
2. Submit the same phone with a wrong password and confirm the response is the generic “The login details are incorrect.” message.
3. Choose **Use email instead** and confirm the prefix disappears, the control becomes `type="text"`, `inputmode` is absent, and `autocomplete="username"` is active. Test both a legacy email and username with correct passwords.
4. At 360 px, confirm email/username mode invokes a normal text-capable keyboard and does not overflow.
5. Toggle Email → Phone → Email repeatedly. Confirm the label, prefix, type, input mode, autocomplete, placeholder, help, and each mode’s remembered value remain correct and keyboard focus returns to the identifier.
6. Fail the same canonical phone or lowercase/sanitized legacy identifier ten times within 15 minutes. Confirm the next attempt is temporarily blocked by its hashed identifier bucket. Repeat from multiple IPs to establish identifier-level protection.
7. Confirm failed variants of the same E.164 phone share a limiter bucket, raw passwords and public limiter keys are never stored, and a successful sign-on does not increment the identifier failure counter.
8. Confirm the existing 40-attempt hourly IP limit still applies independently.
9. Confirm `/wp-login.php` remains unchanged and Account 0.1.6 authenticated pages remain available.

## 0.1.3 strict-mode regression cases

1. In Phone mode, confirm only a successfully normalized canonical phone resolving to a user with verified RaffleLB phone metadata reaches `wp_signon()` under that user’s actual `user_login`.
2. Confirm invalid, unknown, and unverified phones fail with exactly the same generic login error.
3. Enter an email, username, and phone-looking legacy username while `login_mode=phone`; confirm none can fall back to raw WordPress authentication.
4. Submit the same legacy email and username with `login_mode=legacy`; confirm normal WordPress/WooCommerce compatibility still succeeds.
5. Confirm every failed identifier increments its hashed transient bucket, including invalid Phone-mode input, while a successful login deletes that identifier bucket.
6. Confirm the independent IP limiter remains unchanged and successful login does not clear it.
7. Confirm `/wp-login.php`, `/wp-admin/`, Account 0.1.6 logged-in pages, and the approved 0.1.2 toggle UI remain unchanged.

Before release, run `php -l` on every PHP file, `node --check assets/auth.js`, a CSS validator, and unzip-test the final archive on a machine with PHP available.

## 0.1.12 Best SMS Bulk / PROXIREACH regression cases

1. Confirm the provider list contains Disabled, Development test adapter, and Best SMS Bulk / PROXIREACH in that order.
2. On production, confirm the Development test adapter remains unavailable and cannot stay selected.
3. Select Best SMS Bulk and confirm it remains unavailable until API Key, API Secret, API Secured Key, and Sender ID are all configured.
4. Save each credential and inspect the returned HTML. Confirm no stored credential value appears and each populated field says `Stored — leave blank to keep existing value`.
5. Submit blank credential fields and confirm the stored values remain unchanged; submit replacements and confirm the new values take effect.
6. Define each `RAFFLELB_BSB_*` constant and confirm it takes precedence over the corresponding database value while wp-admin says `Configured via wp-config.php` without exposing its value.
7. With a stubbed WordPress HTTP transport, confirm canonical `+961XXXXXXXX` is sent as `961XXXXXXXX`, `route` is exactly `sms`, Sender ID comes from configuration, both secured headers are present, and the OTP service's supplied message is unchanged.
8. Confirm only an HTTP 2xx response containing a JSON array whose first item has integer-equivalent status `200` and a non-empty `confirmationid` succeeds.
9. Confirm transport failures, invalid JSON, authentication errors, unauthorized Sender ID, insufficient credits, and other rejections return only safe `bsb_*` error codes/messages.
10. Confirm no OTP, message body, API credential, complete payload, or full provider response is logged or included in delivery-failure telemetry.
11. Re-run registration, reset, login, `/wp-admin/`, frontend visual, and all prior OTP/security regression cases unchanged.
