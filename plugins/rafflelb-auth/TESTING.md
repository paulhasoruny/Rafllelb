## 0.1.22 unlisted username/email login regression

1. Confirm normal logged-out My Account remains phone + password only, with no username/email toggle or public special link.
2. Open `/email-login/` (and separately the fallback My Account URL with `?rafflelb_login=legacy`); confirm Username or email + Password appears, +961 is absent, Create account is hidden, and the form contains the route-specific legacy nonce/token.
3. Create a WooCommerce/WordPress customer manually without `_rafflelb_phone_verified=yes`; confirm its username and email each work with the correct existing password.
4. Confirm wrong password, unknown username, unknown email, and a forged `login_mode=legacy` request without the route-specific token all return the same generic error and stay rate-limited.
5. Confirm a normal RaffleLB phone-registered account is refused by the legacy route even with the correct username/email/password, while verified-phone login still works.
6. Re-test 0.1.21 registration: the chosen Username remains the real WordPress `user_login`; OTP, PROXIREACH, phone reset, and deleted-phone reuse remain unchanged.
7. Confirm Settings → RaffleLB Authentication shows the copyable short `/email-login/` URL to administrators.

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
14. Confirm the Auth login form exposes phone + password only and contains no email/username login toggle.
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
3. Confirm there is no **Use email instead** control and the login identifier remains `type="tel"`, `inputmode="tel"`, and `autocomplete="tel"`.
4. At 360 px, confirm the phone login remains aligned and does not overflow.
5. Confirm the login label, +961 prefix, placeholder, helper text, and password controls remain unchanged across desktop/mobile.
6. Fail the same canonical phone ten times within 15 minutes. Confirm the next attempt is temporarily blocked by its identifier bucket. Repeat from multiple IPs to establish identifier-level protection.
7. Confirm failed variants of the same E.164 phone share a limiter bucket, raw passwords and public limiter keys are never stored, and a successful sign-on does not increment the identifier failure counter.
8. Confirm the existing 40-attempt hourly IP limit still applies independently.
9. Confirm `/wp-login.php` remains unchanged and Account 0.1.6 authenticated pages remain available.

## 0.1.3 strict-mode regression cases

1. In Phone mode, confirm only a successfully normalized canonical phone resolving to a user with verified RaffleLB phone metadata reaches `wp_signon()` under that user’s actual `user_login`.
2. Confirm invalid, unknown, and unverified phones fail with exactly the same generic login error.
3. Submit an email or username in the phone field; confirm normalization/authentication fails and there is no raw WordPress fallback.
4. On the normal My Account route, confirm customer authentication resolves only through the verified phone registry; the 0.1.22 `login_mode=legacy` branch must only be reachable from the unlisted `?rafflelb_login=legacy` form and must reject verified-phone accounts.
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


## 0.1.14 Best SMS Bulk / PROXIREACH Text HTTP regression cases

1. Select Best SMS Bulk / PROXIREACH and confirm availability requires API Key, API Secret, and Sender ID only; API Secured Key is no longer required or shown.
2. Confirm the endpoint is exactly `https://www.bestsmsbulk.com/bestsmsbulkapi/sendSmsAPI.php`.
3. With a stubbed WordPress HTTP transport, confirm canonical `+961XXXXXXXX` is sent as `961XXXXXXXX` in `destination`.
4. Confirm the POST body contains only the provider-required SMS fields: `api_key`, `api_secret`, `senderid`, `destination`, and `message`; no secured headers or `route` field are sent.
5. Confirm Sender ID is passed unchanged and remains case-sensitive (production value: `Rafflelb`).
6. Confirm a documented plain-text success response like `110099887;96170123456;1` returns success only when the recipient matches the requested destination.
7. Confirm invalid credentials, unauthorized Sender ID, no credits, and the provider's API-mode mismatch string map to safe WordPress errors without exposing provider credentials or payloads.
8. Re-run registration OTP, password-reset OTP, phone+password login, rate-limit, expiry, and anti-enumeration regression cases unchanged.

## 0.1.16 phone-only launch regression cases

1. Confirm the logged-out My Account/RaffleLB Auth login shows only **Phone number**, **Password**, **Remember me**, **Forgot password?**, and **Create account**; there is no **Use email instead** control.
2. Register a fresh customer through phone OTP, then log out and log back in with the verified phone + password. Confirm the same user ID is used.
3. Submit the registration email or WordPress username in the phone field with the correct password. Confirm login fails generically and does not fall back to WordPress email/username authentication.
4. Confirm an unverified `billing_phone` cannot authenticate unless the canonical number exists in the verified RaffleLB phone registry/metadata.
5. Run Forgot password: phone → OTP → new password; then confirm phone + new password logs in successfully.
6. Confirm `/wp-login.php` / normal WordPress administrator authentication is not modified by this plugin change.
7. Confirm registration still requires the existing email field for the WooCommerce/WordPress account record; this email is not exposed as a customer login option in the RaffleLB Auth UI.


## 0.1.17 provider-response compatibility regression cases
1. Confirm the documented plain-text response `110099887;96170123456;1` succeeds.
2. Confirm the same tuple wrapped in whitespace or simple HTML succeeds.
3. Confirm a decimal part count such as `110099887;96170123456;1.00` succeeds.
4. Confirm a 200/201 JSON success envelope succeeds.
5. Confirm a non-empty HTTP 2xx provider acknowledgement with no failure markers succeeds.
6. Confirm known failures such as invalid credentials, unauthorized sender, no credits, empty fields, API-mode mismatch, rejected, denied, or blocked still fail safely.
7. Confirm no API credential, OTP, message body, or full provider response is exposed to the customer.

## 0.1.18 registration profile fields
- Verify registration requires first name and family name after OTP verification.
- Verify display name is auto-suggested as `First Family` and remains editable.
- Verify created WordPress user has first_name, last_name, display_name and nickname set to the supplied profile values.
- Verify WooCommerce billing_first_name and billing_last_name are populated.
- Verify phone verification, email uniqueness, password validation, login and password-reset behavior are unchanged.


## 0.1.19 verified-phone login regression

1. Register a fresh customer through SMS OTP and complete account creation.
2. Log out, then sign in with the same verified Lebanese phone and password; confirm success.
3. Confirm a wrong password returns only the generic login error.
4. Confirm an unknown or unverified phone returns the same generic login error.
5. Confirm a successful sign-in establishes the normal WordPress session and fires `wp_login`.
6. Confirm the normal customer-facing form still has no username/email toggle/fallback and `/wp-login.php` administrator authentication is untouched. The only exception is the unlisted 0.1.22 route for manually-created accounts without a verified phone.

## 0.1.20 deleted-user phone reuse regression

1. Register and verify a fresh customer phone, complete account creation, and record the WordPress user ID.
2. Delete that customer from WordPress/WooCommerce. Confirm the registry row for that user ID is removed by the delete hook.
3. Register again with the same phone number. Confirm OTP issuance and account creation are allowed.
4. Simulate an orphaned pre-0.1.20 registry row whose user_id no longer exists. Confirm a lookup self-heals the row and the phone can be reserved again.
5. Confirm a phone whose registry user_id still belongs to a live user remains blocked from duplicate registration.


## 0.1.21 real username registration regression

1. Complete phone OTP verification and confirm the account-details step asks for First name, Family name, Username, Email, Password, and Confirm password; there is no Display name field.
2. Create an account with a unique username such as `paul.test_21`. Confirm WordPress `user_login` is exactly that username and is not an `rl_...` generated value.
3. Confirm WordPress `display_name` and `nickname` are automatically set to `First name + Family name` and WooCommerce billing first/last name remain populated.
4. Try an already-used username and confirm registration is blocked with a safe “username already taken” message without consuming the verified phone transaction.
5. Try usernames containing spaces or unsupported characters and confirm they are rejected. Confirm 3–60 character usernames containing letters, numbers, dots, underscores, and hyphens are accepted.
6. Confirm customer login remains verified phone + password only; entering the chosen username in the phone field does not create a username-login fallback.
7. Re-run OTP registration, phone login, forgot-password OTP, deleted-user phone reuse, PROXIREACH sending, and normal `/wp-login.php` administrator login unchanged.
