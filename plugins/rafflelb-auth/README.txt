0.1.23 keeps the complete 0.1.22 authentication behavior and adds the memorable private login URL `/email-login/` for manually-created accounts without verified phone registration. The older `/my-account/?rafflelb_login=legacy` route remains supported as a fallback. Normal verified-phone customer login remains unchanged.

0.1.22 is based on the complete 0.1.21 release. It preserves the real-username registration, hardened verified-phone login, OTP, PROXIREACH, recovery, and deleted-phone reuse behavior. It adds one unlisted My Account route for manually-created accounts without a verified RaffleLB phone: `?rafflelb_login=legacy`. That route accepts username or email plus the existing WordPress password, remains rate-limited, and refuses accounts that already have a verified RaffleLB phone so normal customer login stays phone-only. The copyable URL is shown only to administrators under Settings → RaffleLB Authentication.

0.1.21 replaces the registration Display name field with a real Username field. The chosen unique username is now stored as WordPress user_login instead of generating an rl_... internal username. The public display_name/nickname are set automatically to First name + Family name. RaffleLB customer login remains verified phone + password only; OTP, PROXIREACH, password recovery, and admin login are unchanged.

0.1.20 fixes phone reuse after a customer account is deleted. RaffleLB Auth now removes that user's phone-registry ownership during WordPress/WooCommerce user deletion, and it also self-heals any orphaned registry row left by older versions when the referenced user no longer exists. A deleted test/customer account therefore no longer permanently blocks its former verified phone number from registering again.

RaffleLB Auth 0.1.19

0.1.19 fixes verified-phone login on sites where an unrelated WordPress/WooCommerce authentication filter rejects the hidden internal RaffleLB user_login. After the verified phone resolves the exact customer account, RaffleLB Auth now validates that account's WordPress password directly with wp_check_password(), then establishes the normal WordPress auth cookie/session and fires wp_login. Customer login remains phone + password only; registration, OTP, password reset, rate limits, admin login, and PROXIREACH SMS are unchanged.

=== RaffleLB Auth ===
0.1.16 makes the customer-facing RaffleLB login phone-only for launch. The “Use email instead” toggle and legacy username/email fallback were removed from the Auth form. Verified phone + password remains the only customer login path; registration and phone-OTP password recovery are unchanged. Normal WordPress administrator login is not modified.
Contributors: rafflelb
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 0.1.22
License: GPLv2 or later

Standalone RaffleLB customer phone authentication for WordPress and WooCommerce.

0.1.15 hardens legacy customer compatibility. In “Use email instead” mode, the Auth form now resolves an existing WordPress/WooCommerce account by exact email or username first, then signs in through that account’s actual WordPress user_login. Existing passwords are unchanged; no migration or duplicate account is created. Legacy billing phone numbers remain ineligible for phone login until verified through RaffleLB Auth.

0.1.14 switches Best SMS Bulk / PROXIREACH to the provider-directed Send SMS (Text HTTP) transport at sendSmsAPI.php while keeping OTP generation, hashing, expiry, verification, rate limits, and canonical phone identity inside RaffleLB Auth. Configure API Key, API Secret, and the exact case-sensitive Sender ID in Settings > RaffleLB Authentication, or define RAFFLELB_BSB_API_KEY, RAFFLELB_BSB_API_SECRET, and RAFFLELB_BSB_SENDER_ID in wp-config.php. The previous API Secured Key setting is retained only for rollback compatibility and is not used by this transport.

0.1.3 strictly separates verified-phone and legacy login paths and clears identifier failures after successful authentication. It preserves the approved 0.1.2 phone/email interface and 0.1.1 security fixes.

== Features ==
* Phone-first registration with server-side OTP verification before account creation.
* Normal customer login remains verified phone + password only; 0.1.22 adds only an unlisted username/email route for manually-created accounts without a verified RaffleLB phone.
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

0.1.18
- Registration profile completion added first name, family name, display name, email and password after phone OTP verification.
- Superseded by 0.1.21: registration now asks for a real Username instead of an editable Display name.
