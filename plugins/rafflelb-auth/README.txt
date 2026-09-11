=== RaffleLB Auth ===
Contributors: rafflelb
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 0.1.13
License: GPLv2 or later

Standalone RaffleLB customer phone authentication for WordPress and WooCommerce.

0.1.12 adds the Best SMS Bulk / PROXIREACH secured JSON SMS transport while keeping OTP generation, hashing, expiry, verification, rate limits, and canonical phone identity inside RaffleLB Auth. Configure API Key, API Secret, API Secured Key, and Sender ID in Settings > RaffleLB Authentication, or define RAFFLELB_BSB_API_KEY, RAFFLELB_BSB_API_SECRET, RAFFLELB_BSB_SECURED_KEY, and RAFFLELB_BSB_SENDER_ID in wp-config.php.

0.1.3 strictly separates verified-phone and legacy login paths and clears identifier failures after successful authentication. It preserves the approved 0.1.2 phone/email interface and 0.1.1 security fixes.

== Features ==
* Phone-first registration with server-side OTP verification before account creation.
* Verified phone plus password login; legacy username/email login remains available through the form endpoint.
* Phone OTP password recovery without changing WordPress administrator recovery.
* E.164-style Lebanese phone normalization in one reusable service.
* Replaceable SMS provider interface, disabled provider, and administrator-only encrypted development adapter.
* Phone/IP rate limits, resend cooldown, expiry, attempt limits, and one-time verified transactions.
* Race-safe canonical phone registry plus namespaced customer metadata.

== Setup ==
1. Install and activate the plugin ZIP.
2. Open Settings > RaffleLB Authentication.
3. The SMS provider is disabled by default. For non-production QA only, enable test mode and select the development adapter.
4. Visit WooCommerce My Account, or place [rafflelb_auth] on a page.

Test codes are encrypted at rest, expire quickly, and are visible only to an authenticated administrator on the settings page. Test mode cannot run when wp_get_environment_type() is production.

== Security ==
OTP transaction records use WordPress transients. Only WordPress password hashes of OTPs are stored in those records. Codes are never returned to frontend requests, URLs, logs, localStorage, or customer-visible markup. The development adapter keeps a separate short-lived encrypted copy solely for administrator QA.
