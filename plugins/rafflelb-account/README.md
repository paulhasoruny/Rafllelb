# RaffleLB Account 0.1.6 — typography and readability

- Account-owned typography now uses `var(--rl-font, "Manrope", sans-serif)`.
- Page titles, summary identity, metrics, navigation, raffle/order metadata, addresses, and forms use a clearer responsive hierarchy.
- Layout structure, dimensions, responsive ordering, icons, endpoints, queries, and account/business logic are unchanged.

# RaffleLB Account 0.1.5 — prize fulfillment visibility

- My Raffles now reads the existing Draw Engine fulfillment fields for winning raffles.
- Winners see Pending Fulfillment, Winner Contacted, Prize Claimed, or Prize Fulfilled beneath the existing You Won panel.
- Relevant fulfillment milestone dates are shown when available.
- The admin fulfillment note remains private and is not exposed to customers.
- No new tables/options or fulfillment writes are added; Account remains display-only for this state.

# RaffleLB Account — reversible extraction, version 0.1.0

## Install both packages in this order

1. Keep RaffleLB Core and the other existing plugins active.
2. Upload rafflelb-draw-engine-0.34.18.1.zip through WordPress Plugins > Add New > Upload Plugin. Choose to replace the existing RaffleLB Draw Engine. Keep its existing folder name, rafflelb-draw-engine. Do not delete the installed plugin first or install a second Draw Engine copy.
3. Upload rafflelb-account-0.1.0.zip and activate RaffleLB Account.
4. Clear the site's page/asset cache. Check logged-in and logged-out My Account, My Raffles, Orders, an authorized View Order, account details, addresses, Notifications, Refer & Earn and mobile navigation.

You will have nine plugins. Homepage and Shop remain inside Draw Engine for later stages.

Installing Account with the original Draw Engine is inert: the original Engine has no delegation bridge. With the matching Engine update, Account requires Core >= 0.1.0 and bridge version 1. If either dependency is absent, the original Engine account behavior is used. No activation/deactivation hooks or database migrations are added by Account.

## What changed

29 functions now have active implementations in Account: account menu ordering/body classes, account styles/contrast, My Raffles, winner summary/dismissal, endpoint/query-var handling, account dashboard, orders/view-order presentation, account/login assets and header-account dropdown styling. Four account assets are copied byte-for-byte. Global function names and Engine callbacks remain callable as compatibility wrappers.

Draw Engine retains every original implementation. Each selected function first delegates to Account when ready, otherwise executes its original body. Existing hook registrations, priorities, callback identities and registration order are unchanged. The statistics helper remains in Engine behind a narrow account_stats bridge. Shared table/meta constants come from Core. Native authentication and order access checks remain identical.

The winner-banner dismissal handler retains its existing nonce, login check and _rafflelb_dismissed_wins update. This only changes ownership; it does not introduce a new data write. Entries, holds, capacity, order processing, payment handling, points, referrals, draws, winners and notifications stay with their current owners.

Shared site-wide typography/contrast remains in Engine because it also affects Cart and Checkout. Account is not standalone without Engine yet. Engine does not get smaller in this stage because fallback code is deliberately retained. Later cleanup can remove it after acceptance.

The WordPress plugin header is now 0.34.18.1, but Engine's internal VERSION remains 0.34.18 so its existing schema-version check does not run an unnecessary upgrade solely for this extraction. Core 0.1.0's baseline diagnostic may report this header as version_changed_review_required; that is expected for this reviewed bridge release. No Core replacement is required.

Account asset URLs resolve from the Account directory while active; fallback URLs resolve from Engine. Existing CSS/JS cache-version values are retained for this identical-asset release. Future Account asset changes must also bump their URL cache-version values.

## Rollback

Deactivate only RaffleLB Account and clear caches. Draw Engine immediately uses its original account implementations on the next request. Keep Core and all original feature plugins active. There is no Account-created table or option to delete.

If needed, replace Draw Engine with your backed-up original 0.34.18 ZIP. Do not delete data or reset winners/entries. Account remains inert with the original Engine, so you can deactivate it before restoring Engine.

## Validation performed

- Both changed PHP files pass PHP 8.2.0 lint.
- All 29 extracted function bodies match original bytes after the two documented reference substitutions (Core constants and Engine statistics bridge).
- Removing only the new delegation/bridge insertions and reverting the plugin header reproduces the original Engine PHP byte-for-byte. Transactional functions and existing hook statements are untouched.
- All four Account asset files match originals exactly.
- Isolated tests compare 153 hook operations including order/priorities and selected account snapshots across original Engine, updated Engine alone, Account loaded before Engine, Account loaded after Engine, and Account without Core. All match, allowing the intended asset-directory change. Tests explicitly check active delegation readiness and missing-Core fallback.
- Snapshots cover menus including Notifications/referrals, body/query variables, endpoint registration, cancel-action removal, style/contrast output, empty winner summary, invalid-order rejection and selected footer/body callbacks.

Not tested against a running WordPress site: full populated dashboard/My Raffles/Orders rendering, pagination, real nonce/AJAX behavior, theme/browser visuals, payment/refund flows, HPOS/multisite integration, or production data. The isolated harness uses API stubs. PHP 7.4 is the declared syntax floor but runtime execution was tested on PHP 8.2.0 only.

## Acceptance checks

Compare before/after for populated and empty accounts; mobile and desktop; orders pagination; valid and unauthorized view-order access; login/register/password recovery; winner dismissal; My Raffles active/completed/history states; all menu tabs and header dropdown. Confirm an ordinary purchase and existing raffle workflow still behave as before. Test Account deactivation fallback as well. Keep the backup until these checks pass.
