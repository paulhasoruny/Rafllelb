v0.34.18.45 — Split early-close outcomes

- Adds a durable early-close mode while preserving the existing authoritative locked-pool snapshot and internal ready-to-draw compatibility state.
- `Close Early & Proceed to Selection` locks the current eligible pool, issues no Raffle Points refund, and permits the existing Selection flow on that locked pool.
- `Cancel Raffle & Refund Participants` locks the record, keeps winner selection blocked, and runs the existing idempotent Points-refund workflow.
- The public closure bridge exposes only `selection` or `cancel_refund`, the sanitized public note, and a privacy-safe refund status; internal reason/admin/refund details remain private.
- A raffle's early-close outcome cannot be changed after closure.
- Adds `EARLY_CLOSE_MODE_VERSION = 1` so admin clients can safely detect the split-mode capability during upgrades.

v0.34.18.44 — Privacy-safe public refund status

- Adds only `complete` or `processing` to the public early-closure bridge; private failure and attention states remain internal.
- Marks new or retried refunds as processing before the existing idempotent refund pass and complete only after success.
- Keeps the approved locked-pool calculations, complimentary exclusion, and idempotency behavior unchanged.

v0.34.18.43 — Public early-close record and idempotent Points refunds

- Requires separate internal and public notes for new explicit early closures.
- Exposes only a sanitized public closure note through the read-only Selection bridge.
- Refunds each authoritative locked paid-entry value through Referral & Points; zero-value and complimentary lines are excluded.
- Replays and admin retries are safe because each raffle/order refund uses a durable idempotency key.

v0.34.18.42 — Immutable locked-pool revisions and read-only Selection bridge

- Captures every new full or early closure as an immutable `locked_pool` revision in the existing entry-history table while holding the established per-raffle advisory lock.
- Random and manual selection use the active validated revision; legacy raffles with no snapshot retain the 0.34.18.41 selection fallback.
- Post-lock voids create a new active revision when the raffle remains early-closed; full raffles preserve their existing reopen behavior and create a fresh revision on their next closure.
- Adds one privacy-minimal read-only bridge returning only formatted entry number, server-masked participant name, locked count, revision/timestamp, and winning boolean.
- No schema migration; entry creation, capacity, checkout, result rows, hooks, and internal compatibility VERSION 0.34.18 remain unchanged.

v0.34.18.40 — Per-raffle allocation/early-close mutex

- Paid entry allocation and administrative early close now share a bounded, per-raffle MySQL/MariaDB advisory lock, making the frozen early-close snapshot deterministic under concurrent requests.
- Allocation rechecks closed state, order-item idempotency, and active capacity while holding the lock; lock failure creates no entry and leaves a private manual-review order note.
- No schema migration; internal compatibility VERSION remains 0.34.18 and winner selection, hooks, frontend assets, holds, and result contracts are unchanged.

v0.34.18.39 — Early-close paid-entry integrity guard

- Paid-order entry generation now rejects early-closed, ready-to-draw, winner-selected, and permanently resulted raffles before allocation, with a second guard immediately before each authoritative entry insert.
- Late-paid orders are preserved and receive a private WooCommerce order note; no entry is created and the frozen eligible pool is not expanded.
- Existing early close, full-capacity closure, entry idempotency/capacity, random/manual winner selection, hooks, tables, and internal schema compatibility VERSION 0.34.18 are unchanged.

v0.34.18.37 — Fulfillment status event bridge

- Prize fulfillment storage remains owned exclusively by Draw Engine; existing pending/contacted/claimed/fulfilled states, notes, timestamps, order notes, and result-table columns are unchanged.
- Added the read-only `rafflelb_fulfillment_status_changed` action after a successful actual status transition so Notifications/Account can react without duplicating fulfillment writes.
- The event is not emitted for note-only saves or re-saving the same status, preventing duplicate customer notifications.
- Internal Draw Engine schema compatibility VERSION remains 0.34.18 and protected table/meta contracts are unchanged.

v0.34.18.29 — Winner notification ownership handoff

- Keeps draw state/result ownership in Draw Engine while moving automatic customer delivery to RaffleLB Notifications.
- rafflelb_draw_completed keeps its original first five arguments unchanged and now adds a backward-compatible sixth read-only context argument containing entry number, order id, selected timestamp, and method.
- Removed Draw Engine's automatic winner-email call after both Secure Random Draw and Record Chosen Winner, preventing duplicate email delivery when Notifications is active.
- Existing admin Send/Resend Winner Email remains available but delegates through rafflelb_winner_email_delivery when Notifications is active; the legacy Draw Engine sender remains only as a compatibility fallback if Notifications is disabled.
- Added rafflelb_winner_email_sent listener so Draw Engine remains the sole writer of rafflelb_draw_results.winner_email_sent_at while Notifications owns delivery.
- Internal Draw Engine schema compatibility VERSION remains 0.34.18; rafflelb_entries, rafflelb_draw_results, rafflelb_holds, rafflelb_draw_status, rafflelb_winner_entry_id, rafflelb_purchase_mode and _rafflelb_purchase_mode contracts are unchanged.

v0.34.18.28 — Individual-entry void permanence / no replacement tickets

- Keeps the existing per-entry admin void owned by Draw Engine: one active entry can be changed to Void without changing its WooCommerce order or sibling entries.
- Fixed order-entry idempotency so a voided ticket remains permanently void even if the same paid order later re-enters Processing/Completed hooks. Historical Void rows now count as already-issued tickets for that order line, preventing a silent replacement entry.
- Capacity still counts only active entries, winner pools still include only active entries, and new ticket numbers remain monotonically increasing from the highest number ever issued, so voided numbers are never reused or renumbered.
- Internal Draw Engine schema compatibility VERSION remains 0.34.18; table/meta contracts are unchanged.

v0.34.18.16 — Desktop sticky navigation breathing room

- On desktop sticky headers only, raises the visible `.whb-row.whb-sticky-row` and its inner container from 65px to 82px, preserving centered controls and the bottom-attached lime divider.
- Normal desktop, mobile, mobile sticky, logged-in/logged-out mobile-logo sizing, Draw Engine schema VERSION (0.34.18), and all raffle/cart/account/bridge/database logic are unchanged.

v0.34.18.15 — Logged-out mobile logo expansion

- On mobile only, `body:not(.logged-in)` expands `.whb-mobile-center .wd-logo` from 170px to 204px and its proportional image cap from 60px to 68px.
- Logged-in header geometry, prepared/sticky header sizing, divider behavior, Draw Engine schema VERSION (0.34.18), and all raffle/cart/account/bridge/database logic are unchanged.

v0.34.18.14 — Mobile prepared-header reserve correction
- Live measurements at 390px, 412px, and 430px show a 76px visible mobile row. After a sticky cycle, WoodMart retains `.whb-sticky-prepared` and computes the non-sticky outer header as 87px high with 87px top padding, leaving an 11px dark reserve beneath that row.
- On mobile only, `.whb-sticky-prepared:not(.whb-sticked)` is restored to the measured 76px height/padding-top. The sticky state remains excluded, preserving the existing mobile sticky divider and behavior.
- Desktop prepared-header correction, normal/sticky divider selectors, Draw Engine schema VERSION (0.34.18), and all raffle/cart/account/bridge/database logic are unchanged.

v0.34.18.13 — Desktop prepared-header reserve correction
- Live inspection identified no `.whb-header-bottom` or other empty WoodMart row. The only normal header row is the 78px `.whb-general-header`.
- After a sticky cycle, WoodMart retains `.whb-sticky-prepared` on the normal outer header and computes its height/padding-top as 102px while the visible child row remains 78px, leaving a 24px dark reserve below the row.
- On desktop only, `.whb-sticky-prepared:not(.whb-sticked)` is restored to the same 78px height/padding-top as the normal header. The real sticky state deliberately keeps WoodMart's 102px reserve unchanged.
- Sticky divider selectors, mobile sticky behavior, Draw Engine schema VERSION (0.34.18), and all raffle/cart/account/bridge/database logic are unchanged.

v0.34.18.12 — Desktop sticky divider and header-height consolidation
- Live inspection confirms the normal desktop header hierarchy is consistently 78px high with zero vertical padding/margins; visible desktop columns are vertically centered within that row. The inactive 94px inner-height declaration has therefore been removed rather than relying on cascade order to override it.
- WoodMart moves `.whb-row.whb-sticky-row` to the viewport when `.whb-header.whb-header_231291.whb-sticked` activates, while the original outer header remains as an off-screen 102px reserve. The divider is now moved from that off-screen outer wrapper to `.whb-sticked .whb-row:last-child::after` on every breakpoint.
- Mobile sticky behavior is preserved. Normal headers continue to use the outer-header divider; sticky headers use exactly one in-boundary row divider.
- Presentation only; raffle, cart, checkout, account, bridge, database logic, and internal schema VERSION (0.34.18) are unchanged.

v0.34.18.11 — Header alignment and mobile sticky divider
- Desktop: explicitly centers the existing WoodMart header columns within the fixed 78px `.whb-general-header-inner`; no header dimensions, logo/menu/control sizes, or horizontal spacing are changed.
- Mobile sticky: moves the existing 2px lime divider from the outer header pseudo-element (which sits outside the sticky clipping boundary at `bottom:-2px`) to `.whb-sticked .whb-row:last-child::after` at `bottom:0`.
- The outer pseudo-element is disabled only during mobile sticky state, so there is one divider in normal and sticky desktop/mobile states.
- Presentation only; raffle, cart, checkout, account, bridge, and database logic are unchanged. Internal schema VERSION remains 0.34.18.

v0.34.18.10 — Global header divider placement
- Moved the RaffleLB lime divider from the inner `.whb-general-header` / sticky-row border to `body .whb-header.whb-header_231291::after`.
- The 2px existing lime rule is now anchored at the outer header boundary (`bottom:-2px`), so it separates the header from page content without changing desktop/mobile header dimensions, control positions, or sticky behavior.
- Presentation only; raffle, cart, checkout, account, and Draw Engine logic are unchanged.

v0.33.95 Winners page: fixed inconsistent RAFFLE NOW button spacing:
- The button was the only icon+arrow CTA on this page using justify-content:space-between instead of a fixed gap, so the space between "RAFFLE NOW" and the arrow only stayed tight when the button happened to shrink-wrap exactly to its text - any extra width pushed the arrow out toward the far edge instead, which is why it looked loose on mobile and tight on desktop.
- Switched to the same fixed-gap pattern already used by every other button on this page, and locked the button to its content width so nothing else can stretch it. Consistent on both desktop and mobile now.
- Presentation only; no raffle or checkout logic changed.

v0.33.94 Winners page: fixed the off-center intro paragraph in the empty state:
- "The full record of every completed draw..." had a max-width and zero left/right margin, so the paragraph's own box stayed pinned to the left of its (centered) container - text-align:center only centered the wrapped lines inside that already-left-shifted box, not the box itself. Now centers correctly under "Our Winners" when there are no draws recorded yet.
- Presentation only; no draw, payment, or result-recording logic changed.

v0.33.93 Winners page: fixed the off-center "Meet our winners" heading with zero winners:
- With no draws recorded yet, the search/sort toolbar that normally sits next to that heading doesn't render, leaving it alone in a space-between row - which pins a lone flex item to the left edge instead of centering it. It now centers to match the hero above it when the page is empty.
- Added a defensive overflow guard on the page's full-width section in case the same layout technique was also causing a slight horizontal scroll on some screens.
- Presentation only; no draw, payment, or result-recording logic changed.

v0.33.92 Winners page redesign: a real winner in the hero, a display typeface, honest stats:
- Replaced the hero's four-panel stat block with a "Most Recent Win" spotlight card featuring the actual latest winner's prize photo, name, and winning ticket - three of those four old stats were the same count repeated or a constant (100%, "Verified") dressed up as data.
- Added a dedicated display typeface (Fraunces) for the page's headlines, the spotlight winner's name, and the empty-state heading - previously the entire page, from the hero down to 8px labels, ran on one UI font.
- Tightened the intro copy (dropped a generic "Real people. Real prizes. Real stories." tagline and a redundant paragraph repeating the same point) into one sentence plus a live tally of completed draws.
- The winner directory below (search, filters, sort, card grid) is unchanged - same data, same behavior, just a stronger hero above it.
- Presentation only; no draw, payment, or result-recording logic changed.

v0.33.91 My Account Dashboard: simplified copy, replaced the meaningless Account Status stat:
- Hid WooCommerce's own default dashboard paragraph ("From your account dashboard you can view...") - it duplicated what the Welcome back card above it already says, just as clutter.
- Shortened "Only live raffles entered by this account are shown here." to "Live raffles you've entered."
- Replaced the "ACCOUNT STATUS: ACTIVE" stat tile, which always read ACTIVE for any logged-in member and told them nothing, with "RAFFLES WON": a real, motivating lifetime count, computed from the same draw-results record used everywhere else on the account pages.
- Presentation only; no order, payment, or draw logic changed.

v0.33.90 Homepage Featured Products: fixed the barely-visible OR divider:
- The "OR" between BUY NOW and Enter Raffle on the homepage's Featured Products cards was 7px and low-contrast against the dark card background - easy to miss entirely.
- Now larger and higher-contrast, with even spacing above and below instead of sitting flush against the Enter Raffle button.
- Presentation only; no pricing, raffle, or checkout logic changed.

v0.33.89 Order details redesigned; closed the gap left by hiding the duplicate Orders table:
- My Account > Order details is now a premium receipt: product thumbnail, name, purchase type and quantity per item, a totals card (subtotal/shipping/tax/discount/payment method/grand total, computed by WooCommerce itself), and a restyled contact-details card - replacing the bare WooCommerce table + address columns.
- Fixed blank space left below My Orders (and now Order details) after hiding the theme's leftover duplicate table: collapses the now-empty wrapper element(s) instead of just hiding the table itself.
- Presentation only; no order, payment, or draw logic changed.

v0.33.88 My Orders: fix unreadable View button and hide a leftover duplicate table:
- Fixed the "View" button on a completed order rendering white text on a lime background (unreadable); now dark text, matching every other filled lime button on the account pages.
- The v0.33.87 card list still had WooCommerce's raw default orders table rendering a second time below it, coming from outside this plugin (the active theme's own account template). Hidden outright, since our own markup never produces that table.
- Presentation only; no order, payment, or draw logic changed.

v0.33.87 My Orders: single premium card list, no more duplicate/garbled rows:
- My Account > Orders now fully replaces WooCommerce's default orders table with one clean card per order (thumbnail, order number, status pill, product name, date, item count, total, and a View/Pay button).
- Fixes the order list rendering twice on mobile (once as the intended card, once as WooCommerce's own default responsive table stacking Date/Status/Total/Actions beneath it).
- Fixes long product names collapsing to one word per line inside a too-narrow table column.
- Presentation only; no order, payment, or draw logic changed.

v0.33.73 Product image fit consistency:
- Featured Products images now use object-fit: contain with no forced zoom.
- Shop product-card images now use object-fit: contain with no hover zoom.
- Prevents square/product-safe images from being cropped inside wide card media frames.
- Presentation only; raffle, pricing, checkout, reservation and draw logic unchanged.

v0.33.72 Mobile ENTER RAFFLE price visibility:
- Keeps the full FROM amount visible in narrow two-column Store cards.
- Allows the ENTER RAFFLE label to wrap instead of truncating the price.
- Presentation only; no raffle, pricing, checkout, reservation, payment, or draw logic changed.

v0.33.71 Mobile Store card equal-height + CTA containment:
- Equalizes two-column mobile Shop cards when product names wrap differently.
- Reserves a consistent two-line title slot and compact one-line category slot.
- Removes large blank gaps before retail price.
- Keeps BUY NOW / ENTER RAFFLE fully inside each card border.
- Presentation-only; no raffle, checkout, payment, reservation, or draw logic changed.

v0.33.70 Mobile Store card alignment:
- On the two-column mobile Shop grid, product-title and category areas now reserve equal heights so cards in the same row stay vertically aligned even when one product name wraps to two lines.
- Product names are limited to two visible lines on mobile and lower card actions remain pinned consistently.
- Presentation-only change; raffle, retail, checkout, reservation, payment and draw logic are untouched.

v0.33.69 Hide completed-draw raffles from the Shop; hardened dashboard live-entry count:
- Shop/category archives (raffle, retail, and combined view modes) now exclude any product whose draw has been permanently recorded (_rafflelb_draw_status = winner_selected), across the board - it no longer stays listed for direct/Buy Now purchase either. It's still reachable directly by its product URL and from the Winners page; this only removes it from catalogue browsing.
- Dashboard "Your active raffle entries" widget (rafflelb_premium_account_dashboard()) now also cross-checks the permanent draw-results table, not only the product's _rafflelb_draw_status meta, before counting a raffle as still live. The meta-only check was already correct in the code, but a completed draw should never be able to reappear as "live" here even in an edge case where that meta and the results table disagree (e.g. a stale object cache read).

v0.33.68 Corrected the account tab order: Logout last again, clean mobile pairs:
- Desktop: swapped Refer & Earn and Logout back so Logout reads last again, left to right: Dashboard, Notifications, My Raffles, Orders, Addresses, Account Details, Refer & Earn, Logout.
- That exact order also happens to pair perfectly on the mobile 2-column grid with no leftover odd item: (Dashboard, Notifications) (My Raffles, Orders) (Addresses, Account Details) (Refer & Earn, Logout). Removed the previous version's "Addresses full width" and "last item full width" CSS rules, since nothing needs to be forced onto its own row anymore - every tab now sits beside another one, as requested.

v0.33.67 Fixed desktop line-wrap and mobile pairing for the account tabs:
- Desktop: the tab row's grid was hardcoded to 7 columns (`repeat(7,...)`) - clearly written before the 8th tab (Notifications) existed - so the 8th tab always wrapped to its own line no matter the order. Changed to 8 columns; all tabs now sit on one line.
- Mobile (2-column grid): reordered so Account Details and Logout land on the same row together, with Refer & Earn as its own full-width row below them - final order is now Dashboard, Notifications, My Raffles, Orders, Addresses, Account Details, Logout, Refer & Earn. Addresses also spans full width so the Account Details/Logout row starts cleanly on a fresh row instead of pairing oddly with whatever came before it.
- The tablet 4-column layout already had exactly 2 clean rows of 4 with 8 items, so it needed no change.

v0.33.66 Made the My Account tab order self-correcting:
- The 0.33.65 fix for the Notifications tab's position lived in RaffleLB Referral & Points, a separate plugin - if that plugin's update wasn't uploaded (or is ever deactivated), the tab still landed after Logout, alone on its own row, both in the full My Account nav and the header's account dropdown.
- Added a second, definitive pass on woocommerce_account_menu_items at priority 999 (i.e. after every other RaffleLB plugin's own filter has already run) that always produces the same final order: Dashboard, Notifications, My Raffles, Orders, Addresses, Refer & Earn, Account Details, then Logout - and Logout is now structurally guaranteed to render last, even if a future plugin adds a brand-new tab nobody has coded a position for yet.
- No separate upload needed for this to keep working going forward; it no longer depends on RaffleLB Referral & Points being current.

v0.33.65 Notifications tab: icon, correct order, dark header dropdown:
- Added a bell icon for the new "Notifications" My Account tab (was showing a blank/generic square) - added to both icon-definition blocks in account-premium.css.
- Fixed the account-premium.css cache-busting version, which was hardcoded to the string "0.33.61" regardless of the actual plugin version, so a CSS change like this one wasn't guaranteed to reach a browser with the old file cached. Now uses the real plugin version.
- Fixed the WoodMart header's "My Account" dropdown (visible on every page, separate from the full My Account page nav) rendering with the theme's default white background / black text instead of the site's dark theme - added scoped styling under the existing .wd-header-my-account wrapper.
- The actual tab ordering bug ("Notifications lands after Logout, alone on its own row") was in RaffleLB Referral & Points, not here - see that plugin's 1.2.1 -> 1.2.2 changelog.

v0.33.64 Added a `rafflelb_draw_completed` action hook:
- Fired right after a winner is permanently recorded (both Secure Random Draw and Record Chosen Winner), with (product_id, winner_user_id, winner_entry_id, result_id, method).
- No behavior change on its own - lets other plugins (RaffleLB Notifications) react to a completed draw without this plugin knowing anything about them, the same pattern already used for the admin redirect-target support in 0.33.62.

v0.33.63 Dismiss button on each "My Raffles" Winning Entry card:
- The Winning Entry cards at the top of My Account -> My RaffleLB Entries were purely additive - every past win stayed stacked there forever with no way to clear them out.
- Added a small × dismiss button (top-right of each card, matching the existing dashboard winner-banner style) that hides that one card. Reuses the same acknowledgement list as the dashboard "You have a winning entry" banner (admin_ajax action rafflelb_dismiss_winner_banner, usermeta _rafflelb_dismissed_wins) - dismissing from either place hides it in both.
- Only hides the summary card. The underlying draw result, the full entry lower on the page (order, status, etc.), and order history are never touched or deleted.

v0.33.62 Added an opt-in redirect target for admin actions:
- The close-early / draw-winner / choose-winner / fulfillment / winner-email / order cancel-refund handlers now return to a `rafflelb_redirect_to` URL when one is posted, instead of always landing back on the built-in RaffleLB Entries screen.
- Restricted to admin.php URLs on this install (validated with esc_url_raw + a same-origin prefix check), so it can never be turned into an open redirect. Falls back to the old behavior when nothing is posted.
- Lets the new RaffleLB Raffle Manager plugin reuse these existing handlers and send the admin back to its own per-raffle screen after an action, instead of duplicating the draw/fulfillment logic. No other behavior changed.

v0.33.61 Clarified the raw entries table's confusing "Active" status:
- "Active" here always meant "a valid, non-voided entry" - it never changes once a winner is picked, since every entry that was in the pool (winning or not) stays a permanent record of legitimate participation. It only flips to "Void" if the order was cancelled/refunded. The word "Active" reads as "the raffle is still open," which isn't what it means.
- Relabeled it "Confirmed" (Void stays Void), and added a separate "Draw" column showing the raffle's actual progress (Live / Ready to Draw / Winner Selected), so the two ideas - "is this entry legitimate" vs. "has this raffle finished" - are no longer conflated in one ambiguous word.

v0.33.60 Admin: search, filter, pagination for RaffleLB Entries; fulfillment lock:
- "Winner Selected" table: search box (reward, customer, order #, entry #), a fulfillment-status filter dropdown, and pagination (20 per page).
- "Entry Order Management" table: search box (customer, order #, reward) and pagination (20 per page).
- Once a prize's fulfillment status is "Prize Fulfilled," the status dropdown/note/save button now lock automatically to prevent accidental changes, with an "Edit anyway" checkbox to intentionally unlock and correct a mistake.
- Both tables were previously unbounded (every completed draw / every order with active entries rendered on one page, no way to search) - this was the main practical gap as usage grows.
- Admin-only screen; no change to the customer-facing site.

v0.33.59 Fixed ENTRY # / status badges wrapping to two lines on mobile:
- On narrow phones, the "ENTRY #002" and "DRAW COMPLETE" (or WINNING ENTRY / LIVE / AWAITING DRAW) badges sat beside the thumbnail image, leaving them only the leftover width to share - not enough to fit both on one line.
- Stacked the thumbnail above the entry details instead of beside them below 480px, so the badges get the card's full width and sit side by side as intended.

v0.33.58 Improved legibility of "VIEW RAFFLE" / "VIEW ORDER" buttons on My Raffles:
- These had shrunk to 10px, heavily letter-spaced, all-caps bold text across several past versions - hard to read at that size. Bumped to 13px, reduced letter-spacing, and added explicit Inter so it can't fall back silently. Buttons are slightly taller/wider to match.

v0.33.57 Numbered badges on all four Raffle Details columns:
- WINNER and IMPORTANT now use the same circular lime-outlined number badges as HOW IT WORKS, instead of plain paragraphs.
- PRIZE keeps its bold product name as a headline, with its two facts (Condition, Authenticity) numbered the same way below it.
- Each column numbers independently (every list still starts at 1).

v0.33.56 Redesigned the Raffle Details section for a more premium feel:
- Column headings (PRIZE, HOW IT WORKS, WINNER, IMPORTANT) now left-align with the body text below them instead of floating centered above left-aligned content - the mismatch was the main reason it still looked off after the text-align fix.
- Added a small lime accent bar above each heading instead of relying only on plain text.
- "HOW IT WORKS" numbered steps now use small circular lime-outlined number badges instead of default browser "1. 2. 3." numerals.
- Slightly more breathing room between columns.

v0.33.55 Fixed ragged centered text in the Raffle Details section:
- The WINNER and IMPORTANT column paragraphs were center-aligned, which reads fine for a single short line but looks ragged and hard to scan once a sentence wraps to 2-3 lines (visible on desktop; mobile already had this fixed). Switched paragraph text to left-aligned; column headings (PRIZE, HOW IT WORKS, WINNER, IMPORTANT) stay centered.

v0.33.54 Made Inter the sitewide default font (the missing piece):
- Every earlier font fix in this series made Inter available and applied it to RaffleLB's own custom blocks (shop, checkout, account, winner panels, etc.), but native WooCommerce/WoodMart elements we never explicitly styled - product title, breadcrumbs, tabs, buttons - kept inheriting the theme's own default font instead. That's what made a product page look inconsistent: our components in Inter, everything else in a different font, side by side.
- Added one sitewide, deliberately low-priority rule (body{font-family:Inter,...}, no !important) so anything that doesn't set its own font now defaults to Inter, while every existing more-specific rule (including icon fonts) still overrides it exactly as before.
- Loaded on every front-end page (admin untouched), so this should be the last font-consistency fix needed across the site.

v0.33.53 Fixed the product-page winner panel's mismatched design:
- The "WINNER SELECTED" panel that appears on a raffle's own product page once drawn had cream/pale-yellow boxes with black text, clashing badly with the dark theme used everywhere else on the site - a leftover from before the dark redesign. Restyled to dark, lime-bordered boxes matching the rest of the site (white winner name, lime winning-entry number).
- Added an explicit Inter font-family to the panel so it can't silently fall back to a plain system font.
- Removed the "Manual / External Result" / "Secure Random Draw" line - unnecessary internal process detail for a public-facing panel. The result date is now formatted readably (e.g. "Sep 2, 2026 at 3:24 PM") instead of a raw database timestamp.
- The homepage "Latest Winners" section already uses Inter and dark styling correctly - no change needed there.

v0.33.52 Fixed winner name privacy exposure sitewide:
- The public /winners/ page and the "winner selected" panel on a product's own page were printing each winner's real full legal/billing name to any visitor, logged in or not.
- All public winner name display now shows the full first name plus only the family name's first letter, e.g. "Paul H***" - the family name itself is never shown.
- The homepage "Latest Winners" section already masked names but used a different, inconsistent format (first name reduced to an initial, e.g. "P*** H."); brought it in line with the new format above via one shared helper, so every public winner display now reads consistently.
- The winner's own notification email is unaffected - it still addresses them by their real name, since it's private and sent only to them.
- No draw, entry, reservation, payment, or winner-selection logic changed - this only changes how a name is displayed.

v0.33.51 Desktop toolbar: bottom-aligned zones + lime dividers:
- Raffle Points, the mode buttons, and Filters/Sort now share the same bottom edge instead of all sitting on the toolbar's vertical center (which put Raffle Points/Filters visibly higher than the buttons, since the SHOPPING MODE label made that middle section taller).
- Added a small lime vertical divider between each of the three zones for a cleaner, more deliberate separation. The divider before Raffle Points only shows for logged-in users, so guests don't see a line with nothing next to it.
- Desktop only (min-width:1181px); mobile/tablet unaffected.

v0.33.50 Cleaner, aligned category filter chips on mobile:
- The collapsed category chip list sized each chip to fit its own label (a deliberate flowing-tag look for desktop), which read as a ragged, uneven grid on a narrow phone screen. Forced a clean two-column grid with equal-width chips instead, so every row lines up.
- Scoped to max-width:767px only.

v0.33.49 Removed the Raffle Points badge from mobile again:
- Reverted the mobile-specific sizing/unhiding added in v0.33.46. The badge is desktop-only again (hidden below 1181px), same as before that pass.

v0.33.48 Added a small "OR" between Buy Now and Enter Raffle on mobile:
- Shown only when both a purchase button and a raffle button appear on the same card (Store & Raffle mode), and only on mobile where the two buttons stack vertically. Desktop's side-by-side layout is unchanged.

v0.33.47 Fixed inconsistent product card layout on mobile:
- The "RAFFLE AVAILABLE" label and entry price sat side-by-side on the same row, which collided/wrapped awkwardly at two-column card width. Stacked the label above the price instead.
- "X claimed" / "Y left of Z" was wrapping onto separate full-width lines rather than staying side-by-side; forced them to a single line each with ellipsis as a safety net.
- Brought the closed-raffle card variant ("RAFFLE CLOSED / DIRECT SHOPPING VIEW") to the same stacked, sized treatment as the live-raffle box, so every card in the grid reads consistently regardless of its raffle status.
- Still scoped to max-width:767px only.

v0.33.46 Mobile-only Store polish (desktop/tablet unchanged):
- Raffle Points badge now also shows on mobile (was hidden below 1181px), sized to match the mobile toolbar.
- Sort dropdown rebuilt with forced contrast/sizing and a proper custom arrow icon, so it reliably shows readable text instead of a near-empty box next to Filters.
- Product grid switched from one huge full-width card per row to a compact two-column grid, with proportionally smaller card padding, title, price, and button sizing.
- Every change in this release is scoped to max-width:767px only; nothing above that width (tablet, desktop) is touched.

v0.33.45 Raffle Points badge text balance fix:
- "Raffle Points" label was too small/muted next to the balance number. Increased its size and weight (12px muted gray -> 15px brighter, bolder) so the label and the number read as a balanced pair instead of the label looking like an afterthought.

v0.33.44 Redesigned the Raffle Points badge as a quiet card, not a lime button:
- Removed the solid lime fill/border - a personal account stat reads as more premium as a restrained dark card than a shouted CTA button.
- Reordered to icon, then "Raffle Points" label, then the balance number, instead of icon/number/label.
- Icon now sits in its own small rounded square instead of floating loose.

v0.33.43 Replaced the live raffle count badge with a Raffle Points balance:
- The Store toolbar's left badge now shows the shopper's own Raffle Points balance (using the RaffleLB logo mark as its icon) instead of a live raffle count, and links to the account dashboard.
- Logged-in only, since guests have no points balance - the zone is simply empty for them, same as before this badge existed.
- Removed the now-unused live-raffle-count helper and its transient cache.
- Reads the same _rafflelb_ref_points user meta the Referral & Points plugin and account dashboard already use; does not modify balances.

v0.33.42 Bolder "live" treatment for the raffle count badge:
- Switched from a thin lime-outline pill (same quiet treatment as the SHOPPING MODE label) to a solid lime fill matching the active mode button's visual weight, a larger bold count number, and a pulsing dot - reads as an energetic live indicator instead of a faint secondary label.

v0.33.41 Added a live raffle count badge to the Store toolbar:
- The toolbar-spacer zone that balances/centers the SHOPPING MODE badge and buttons was previously empty space. Now shows a small "X LIVE RAFFLES" badge (matching the SHOPPING MODE badge style) counting currently-live raffles with entries remaining. Cached for 3 minutes since it's a full raffle scan on every Store page view.
- Hidden below 1180px, where the toolbar stacks into a single column and there's no empty zone to fill.
- No raffle, reservation, payment, or draw logic changed - this only reads existing draw status, it never writes to it.

v0.33.40 Fixed the Sort dropdown clipping "high"/"low" in RAFFLE ONLY mode:
- The Raffle Only sort labels ("Entry price: low to high" / "high to low") are longer than the Store labels and were being clipped by two narrower fixed-width overrides at desktop breakpoints. Widened them to match the default width.
- No sorting/filtering logic changed.

v0.33.39 Rebuilt the Store toolbar layout and hardened the loading overlay:
- The badge was still splitting away from the mode buttons at some desktop widths (e.g. with Filters open). Root cause: the toolbar had three separate, historical CSS Grid layouts (from three earlier versions) each using their own "display:contents" trick to place the old two-piece control, and patching them one at a time kept surfacing a different width where it broke again. Replaced all three with one unified flexbox layout (a balanced invisible-spacer arrangement) so the badge+buttons group is genuinely centered at every desktop width, independent of how wide Filters/Sort is.
- Fixed the loading overlay getting stuck after clicking a category/price filter specifically (while working fine for Store/Raffle mode buttons). Some filter controls are handled by the theme's own AJAX filtering rather than a full page reload, so the overlay's reload-time hide logic never ran for those clicks. The overlay now also clears itself independently 2.5 seconds after being shown, regardless of what happens afterward.

v0.33.38 Fixed the wide-desktop badge layout and a stuck loading overlay:
- On wide desktop (1450px+) a pre-existing CSS Grid layout used "display:contents" on the mode-block wrapper to place its two former pieces ("Choose how you shop" text and the mode buttons) into separate grid columns. Since v0.33.37 replaced the text with a single stacked label+buttons unit, that old grid rule was silently splitting them apart again - hence "SHOPPING MODE" sitting alone on the far left with an empty gap before the buttons. Fixed by keeping the label and buttons together as one real, centered grid item at this breakpoint too.
- Fixed the navigation loading overlay getting stuck on screen indefinitely after clicking a filter. The overlay's hide logic depended on a separate, much larger script finishing without error; it now also carries its own fully independent 2.5-second safety timer, so it can never be left blocking the page regardless of what else happens on load.

v0.33.37 Shopping-mode badge fix + smooth filter/category navigation:
- The v0.33.36 centered label change didn't actually take effect on the live site: it was added to a CSS block that renders before the Store toolbar's own styles in the page, so the older left-aligned text kept winning. Fixed by adding the corrected styling directly into the toolbar's own style block, using a more specific selector so this class of bug can't silently recur.
- Redesigned the label itself as a small lime pill badge ("• SHOPPING MODE") centered above the mode buttons, instead of plain text, matching the WINNER/RaffleLB Account kicker badges used elsewhere on the site.
- Fixed the "flash to top of page then jump back down" that happened every time a Store/Raffle mode, category, subcategory, price filter, or sort option was changed. These are real page reloads; the page previously rendered at the top and only scrolled down to the toolbar ~60-360ms later. Added a loading overlay (RaffleLB icon on a spinner) that appears immediately, before the page has a chance to render at the wrong scroll position, and only fades away once the toolbar position has already been restored behind it.
- No Store mode, filter, pricing, raffle, payment, reservation, winner, or draw logic changed.

v0.33.36 Redesigned the Store toolbar's shopping-mode control:
- Replaced the two-line "Choose how you shop / Store, raffle, or both." paragraph with a small uppercase "SHOPPING MODE" label centered directly above the STORE & RAFFLE / STORE ONLY / RAFFLE ONLY buttons.
- The label and buttons now read as one centered unit instead of text sitting beside the buttons off to the left.
- No Store mode, filter, pricing, raffle, payment, reservation, winner, or draw logic changed.

v0.33.35 Fixed the winner-banner dismiss button doing nothing on My Account:
- The dismiss (x) button's JavaScript contained blank lines that WordPress's automatic content formatting (wpautop) was splitting into stray <p> tags, breaking the script's syntax entirely on the My Account dashboard. This silently prevented the click handler from ever attaching, so clicking dismiss did nothing.
- Collapsed the script to a single unbroken block so it can no longer be mangled by that formatting pass.
- No change to what dismiss does: it still hides the winner banner for that user only, permanently, via AJAX + user meta. The win itself remains visible under My Raffles/order history.

v0.33.34 Typography cleanup:
- Normalized invalid font-weight values (950, 1000) to the standard 900 (the heaviest weight actually loaded for Inter); browsers were already silently clamping these, so this is a no-op visually but removes non-standard values.
- Added Inter as the primary typeface everywhere it was missing on real site pages (Shop/category archive labels, homepage Live Raffles legacy block, Latest Winners section, cart, account page, header points badge) so they match the rest of the premium UI now that Inter loads sitewide.
- Left the three transactional email templates (order confirmation, winner email, voided-order email) on Arial/Helvetica intentionally — email clients do not reliably load Google Fonts, so a safe system-font stack is correct there.
- No raffle, reservation, payment, entry, early-close, manual-winner, or draw logic changed.

v0.33.33 Sitewide typography fix:
- Inter is now loaded on every page, not only the Shop/product-category catalogue.
- Dozens of premium UI blocks (homepage Live Raffles, account pages, checkout, winners) already declared font-family:Inter but the font file was never fetched outside the Shop, so they silently rendered in the browser's plain fallback font (Arial/system UI), which read as weak/thin typography.
- No raffle, reservation, payment, entry, early-close, manual-winner, or draw logic changed.

v0.33.31 Store card presentation polish:
- Fixed encoded category labels such as “Vouchers & Gift Cards” rendering as “Vouchers &amp; Gift Cards” in the scalable category filter.
- Centered archive product names and product category labels.
- Product names and category labels now use Inter for a cleaner, consistent catalogue presentation.
- Replaced the Buy Now arrow with the same shopping-bag SVG glyph used by the homepage Featured Products section.
- No Store mode, category, price, raffle, reservation, payment, winner, early-close, or draw logic changed.

RaffleLB Draw Engine v0.33.29

- Fixed the Store toolbar so STORE & RAFFLE / STORE ONLY / RAFFLE ONLY are truly centered on wide desktop.
- Prevented CLEAR FILTERS, FILTERS and SORT from colliding with or covering the RAFFLE ONLY button.
- Added a safe two-row toolbar layout for medium desktop/tablet widths where all controls cannot fit cleanly on one line.
- No Store/Raffle pricing, filtering, purchase, reservation, payment, draw, early-close or winner logic changed.


- Fixed Store navigation jumping back to the top of the page after selecting a parent category, subcategory, price filter, sort option, Clear Filters, or Store/Raffle view mode.
- Added a stable Store-controls anchor plus short-lived session restoration so full-page WooCommerce/WoodMart requests return to the filter toolbar instead of the hero/header.
- Category/subcategory and price-filter navigation reopens the Filters panel after reload when the action started inside Filters.
- Desktop and mobile use separate sticky-header offsets so the toolbar is not hidden underneath the header after navigation.
- No catalogue eligibility, retail/raffle pricing, reservation, payment, entry, early-close, manual-winner, or draw-result logic changed.

RaffleLB Draw Engine v0.33.21

- Added a three-mode Store toolbar switcher on the left:
  • SHOW PRICES WITH RAFFLES — current combined retail + raffle presentation.
  • SHOW PRICES WITHOUT RAFFLES — retail price / Buy Now presentation only.
  • SHOW RAFFLES ONLY — raffle entry price, progress and raffle CTA only; also includes raffle-enabled products that do not have Buy It Now enabled.
- Raffle-only mode uses the WooCommerce raffle-entry price for sorting and MIN/MAX price filtering; retail/combined modes continue using the Buy It Now retail value.
- Category and subcategory navigation preserves the selected Store mode, sorting and price filters.
- Parent/subcategory visibility is mode-aware, so Raffles Only can expose categories containing raffle-only products.
- Clear Filters preserves the selected Store mode.
- No reservation, payment, entry assignment, early-close, manual-winner, or draw-result logic changed.

RaffleLB Draw Engine v0.33.20

- Fixed retail Price filter disappearing on leaf subcategories such as Men's Perfumes / Women's Perfumes.
- The MIN / MAX retail-price filter is now a persistent RaffleLB control and no longer depends on WoodMart keeping its native price widget in the DOM.
- Native WoodMart price presentations are hidden; WooCommerce min_price/max_price query arguments are still used and remain mapped to the Buy It Now retail value.
- Filter grid order remains Category + Price, followed by the separate Subcategory section.
- Existing Store parent/subcategory filtering, Clear Filters, sorting, raffle, reservation, payment, early-close, and winner logic remain unchanged.

RaffleLB Draw Engine v0.33.19
- Fixed WoodMart archive refresh timing so the premium parent-category and exact MIN/MAX retail-price controls are automatically restored after category navigation/AJAX updates; no close/reopen workaround required.
- Added an always-visible CLEAR FILTERS control whenever a category or retail-price filter is active. It returns to the complete Store catalogue while preserving the selected retail-price sort order.
- Category archives now count as an active filter in the toolbar.

RaffleLB Draw Engine v0.33.15

- Reworked the STORE hero with a clearer Segoe UI retail typography system and removed the decorative underline treatment.
- Replaced the tiny outlined assurance pills with larger readable Direct purchase / Secure checkout / Raffle option feature labels.
- Constrained category search to its own filter column so it cannot overlap the retail Price filter.
- Added contextual nested WooCommerce category filtering: selecting a parent category reveals its real child categories as a second Subcategory filter.
- When a leaf subcategory is selected, sibling subcategories remain available for quick switching.
- Native WooCommerce category URLs and WoodMart/WooCommerce catalogue filtering remain the source of truth.
- No raffle, reservation, payment, retail-price, early-close, manual-winner or draw logic changed.

RaffleLB Draw Engine v0.33.12

- Renamed the main WooCommerce Shop archive heading to STORE.
- Removed the small RAFFLELB / STORE eyebrow from the Shop hero.
- Reworked the Store heading into a larger Inter editorial title with a clean lime underline.
- Increased small Shop UI labels, assurances, filters, metadata and CTA text for clearer professional readability.
- No raffle, reservation, payment, retail-price, early-close, manual-winner or draw logic changed.

RaffleLB Draw Engine v0.33.11

- Removed the product-result count from the public Shop toolbar.
- Redesigned the Shop/category hero heading as a calmer Inter editorial lockup with a slim RaffleLB lime rule.
- Reduced heading weight/size, removed forced uppercase, and refined eyebrow/assurance typography for a more professional retail presentation.
- Shop filter, sorting, retail-price, raffle-entry, early-close, manual-winner, reservation, payment, and draw logic are unchanged.

- Premium Shop presentation refined around native WoodMart/WooCommerce filtering.
- Moved the RaffleLB Store hero outside WoodMart's archive toolbar container.
- Removed duplicate Show Sidebar and products-per-page controls from the public Shop presentation.
- Added a compact RaffleLB toolbar with product count, native WooCommerce sorting, and a Filters toggle.
- Native WoodMart Shop filters are collapsed by default and retain their original AJAX/query behavior.
- Shop filter panel is optimized for Product Category + Price widgets; WoodMart's duplicate Sort By widget is hidden.
- Retail-price sorting and retail-price filter mapping remain tied to the Buy It Now retail value, not raffle entry price.
- No raffle entry, reservation, payment, early-close, draw, manual-winner, or result logic changed.

v0.25.9
- Removed legacy checkout layout/background/payment/order-table/input CSS from Draw Engine.
- Draw Engine no longer forces the WooCommerce order review table white.
- Draw Engine no longer controls checkout columns, payment boxes, totals, or field styling.
- RaffleLB Dark Checkout is now the single source of truth for checkout presentation.
- Raffle logic, reservation logic, entry assignment, checkout wording, intro block, coupon shell, winner/review/request logic, and side-drawer layering remain unchanged.

RaffleLB Draw Engine v0.25.7

- Split the Live Raffles showcase into individual background panels per raffle card.
- Dynamic responsive grid: automatically centers 1–2 raffles, fits 3–4 when space allows, and wraps additional raffles cleanly.
- Mobile remains one card per row.
- No raffle, reservation, review, request, winner, or payment logic changed.

RaffleLB Draw Engine v0.25.4

- Improved contrast for the “RAFFLE NEXT?” homepage heading with a dark highlight block.
- No raffle, reservation, review, or request logic changed.

RaffleLB Draw Engine v0.25.0

v0.25.0
- Added homepage Community Reviews section.
- Added admin-managed RaffleLB Reviews under WooCommerce; only published reviews display publicly.
- Added functional "What Should We Raffle Next?" request form.
- Requests are stored privately under WooCommerce > Raffle Requests and emailed to info@rafflelb.com.
- Added shortcode [rafflelb_community_sections].
- Community sections are appended automatically to the homepage unless the shortcode is already placed manually.
- No raffle entry, reservation, draw, winner-selection, or payment logic changed.

RaffleLB Draw Engine v0.22.7

CLEANUP: STOCK EMAIL LOGIC REMOVED FROM PLUGIN

WooCommerce stock notification emails are now controlled entirely from:
WooCommerce > Settings > Products > Inventory

The RaffleLB plugin no longer:
- filters low-stock notification sending
- alters stock-email recipients
- interferes with WooCommerce stock email settings

This avoids conflicts and keeps stock-notification control in WooCommerce.

All other v0.22.1 functionality remains unchanged:
- Paid display label
- Dark branded paid-entry confirmation email
- Dark branded cancellation/refund emails
- Winner email
- SMTP sender identity
- Reservation system
- Entry allocation
- VOID/audit history
- Admin Entry Order Management
- Winner selection and fulfillment


0.22.7
- Improved [rafflelb_recent_winners] typography and contrast for clearer homepage readability.
- No draw, entry, reservation, winner-selection, email, or order logic changes.

0.22.3
- Added dynamic [rafflelb_recent_winners] homepage shortcode using real draw-result records only.
- Shows latest 3 completed draws, privacy-shortened winner names, real winning entry numbers, and live result counts.


v0.22.7
- Customer-facing terminology updated from Reward/Rewards to Raffle/Raffles where applicable.
- No draw logic, database schema, reservation, order, email delivery, or winner-selection behavior changed.


0.25.6
- Live raffle showcase now sizes itself to the current number of live raffle cards (up to three columns).
- Cards use a centered wrapping layout, so additional raffles fill and wrap cleanly without large empty side areas.
- Mobile remains single-column and responsive.


v0.26.2
- Premium dark Live Raffles shortcode panel.
- Centered LIVE NOW heading.
- Dark raffle cards with lime accents.
- Dynamic centered 1-4 card grid.
- Added View All Raffles button.
- Responsive mobile layout.


v0.27.0
- Complete rebuild of the homepage [rafflelb_live_raffles] visual output.
- Removed all legacy live-card CSS from the shortcode output.
- New unique rlp270 classes prevent old WoodMart/plugin CSS conflicts.
- Full-width premium dark section.
- Larger centered dark raffle cards with lime accents.
- Dynamic 1–4 column layout.
- Responsive mobile layout.
- Raffle logic unchanged.


v0.33.4 Shop readability update:
- Increased Shop typography across hero, toolbar, filters and cards.
- Added exact FROM / TO retail price inputs while keeping WooCommerce min_price/max_price filtering.
- Price filter continues to use Buy It Now retail value rather than raffle entry price.


v0.33.7 Shop cleanup:
- Removed the duplicate quick-category pills from directly under the Shop hero.
- Product categories now appear only inside the Filters panel, alongside the retail price filter.
- No raffle, reservation, payment, draw, early-close, or winner-selection logic changed.


v0.33.5 Shop polish:
- Shop sort menu now shows only Retail price low-to-high and high-to-low.
- Increased category typography in both quick-category pills and native category filter.
- Rebuilt exact retail price filter as compact MIN / MAX numeric fields with proper currency symbol rendering.
- Fixed HTML entity display in the currency prefix (for example &#36; showing instead of $).


0.33.7
- Shop filter and sort controls now use the Inter font family.
- Inter is loaded only on Shop/product-category catalogue pages to avoid changing global typography.


v0.33.8 scalable Shop category filter:
- Keeps the native WoodMart/WooCommerce category links and filtering behavior.
- Shows only the first 4 categories in compact mode.
- Automatically adds a + N MORE control when additional categories exist.
- Expanded mode shows all categories in a searchable 3-column grid on desktop, 2 columns on tablet and 1 column on small mobile.
- Keeps the active category visible among the first four when possible.
- Does not change raffle/draw/payment logic.

- Parent categories stay visible after selecting a category, with an All Categories reset link back to the complete Store list.


v0.33.26
- Added a clear "Choose your way to shop" heading and supporting copy above the Store/Raffle mode buttons.
- Kept mode controls readable and responsive on desktop, tablet, and mobile.

v0.33.25
- Store mode selector buttons now use high-visibility RaffleLB lime styling.
- Active mode is solid lime with dark text; inactive modes keep a lime outline/tint so all three choices remain obvious.
- Increased selector typography for clearer desktop and mobile readability.


v0.33.27
- Improved “Choose your way to shop” typography with a larger, clearer professional system font stack and stronger contrast.
- No Store mode, filter, pricing, raffle, payment, reservation, winner, or draw logic changes.


v0.33.28
- Centered the STORE & RAFFLE / STORE ONLY / RAFFLE ONLY mode controls in the desktop toolbar using a balanced three-zone layout.
- Kept the shopping guidance copy anchored left and Filters/Sort anchored right.
- Tablet and mobile stacking behavior remains unchanged.


v0.33.30
- Refined the Store toolbar shopping guidance into a shorter, clearer premium callout: “Choose how you shop” / “Store, raffle, or both.”
- Added a subtle RaffleLB lime accent and improved readable typography while preserving true-centered Store/Raffle mode buttons.
- No Store, filter, pricing, raffle, payment, reservation, winner, or draw logic changes.
0.33.74
- Rebuilt My Raffles as compact, responsive raffle-group cards matching the approved preview.
- Groups all of a customer's ticket numbers under their raffle instead of duplicating one card per ticket.
- Adds Active, Won, and Past tabs with live counts, entry price, total spend, progress, remaining entries, and winner state.
- Presentation-only update; raffle entry, draw, order, payment, referral, and reservation logic is unchanged.
0.33.75
- Enforced Inter throughout the My Raffles section.
- Increased Entries, Entry Price, and Total Spent label/value sizes.
- Moved the Active badge below the product image and corrected its text to black on lime.
0.33.76
- Added a final high-priority My Raffles correction layer after all account styles.
- Prevents Woodmart/account CSS and stale external stylesheets from overriding Inter, statistic sizing, or Active badge placement.
0.33.77
- Removed the unavailable My Raffles page-body class from every correction selector.
- Added critical inline styles to the status badge and statistic labels/values so theme CSS cannot override them.
0.33.78
- Rebuilt the public Winners page as a premium dynamic winners directory.
- Added truthful live statistics, category filters, winner/prize search, sorting, verified result cards, responsive layouts, and a professional empty state.
- Winner cards continue to use the existing completed-draw records and privacy-masked names; raffle and winner-selection logic is unchanged.
0.33.79
- Updated the Winners page "Explore Live Raffles" button to open the Shop directly in raffle view at the raffle controls.
0.33.80
- Renamed the Winners hero button from "Play Now" to "Raffle Now" and linked it directly to the Shop raffle view controls.
0.33.81
- Restricted the Welcome back / Raffle Points / Orders summary banner to the main My Account Dashboard only.
- The banner no longer appears inside My Raffles, Orders, Refer & Earn, Addresses, Account Details, or Notifications.
0.33.82
- Moved the Welcome back summary from the global account-content hook to WooCommerce's Dashboard-only hook.
- This definitive hook-level correction prevents WoodMart and custom endpoints from rendering the banner on other account sections.
0.33.83
- Added subtle lime-gradient vertical dividers between Entries, Entry Price, and Total Spent on mobile My Raffles cards.
- Desktop My Raffles cards remain unchanged.
0.33.84
- Added balanced internal padding on both sides of the mobile My Raffles statistic dividers.
- Keeps the three statistic columns equal while separating the dividers clearly from their labels and values.
0.33.85
- Rebuilt the mobile My Raffles card instead of squeezing statistics beside the product image.
- Image and product name now form a clean top row; the three statistics use a separate full-width panel underneath.
- Added centered values, balanced spacing, short subtle dividers, and full-width ticket/progress rows.
0.33.86
- Moved the mobile Active/Completed badge from beneath the product image to directly beneath the product name.
- Product name is now top-aligned beside the image, creating a clearer mobile card header.
- Desktop badge placement remains unchanged.

v0.34.18.5 — Account duplicate cleanup
- RaffleLB Account 0.1.0 is now the source of truth for My Account presentation.
- Removed duplicated Account fallback implementations from Draw Engine while retaining callback/function identities as thin compatibility delegates.
- Removed duplicated account-premium.css, account-login.css, and account-login.js from Draw Engine; identical source-of-truth copies remain in RaffleLB Account.
- Preserved ACCOUNT_BRIDGE_VERSION=1, account_stats(), WooCommerce My Account hook names/priorities, endpoint contracts, and all raffle transaction/data contracts.
- Internal schema VERSION remains 0.34.18; no database migration is introduced.
v0.34.18.30 — Winners page frontend refinement

- Removed the Winners page's decorative hero text and its external Fraunces font request.
- Applied the established RaffleLB Inter UI stack to Winners headings, card content, controls, filters, and buttons.
- Rebuilt the Winners search/sort toolbar as a bounded grid: a larger fluid search field alongside a dedicated sort column on desktop/tablet, stacking into full-width controls on small screens.
- Presentation only; raffle selection, results, entries, reservations, checkout, notifications, fulfillment, audit data, table/meta contracts, and internal schema compatibility VERSION (0.34.18) are unchanged.
v0.34.18.31 — Winners page final visual correction

- Set direct border-box sizing and full-width constraints on the Winners search input and sort select, with a fixed 16px desktop gap and dedicated 360–420px / 180–200px columns.
- The toolbar now stacks before its available width is constrained, while all Winners typography meets the increased desktop and mobile readability minimums.
- Presentation only; raffle selection, results, entries, reservations, checkout, notifications, fulfillment, audit data, table/meta contracts, and internal schema compatibility VERSION (0.34.18) are unchanged.
v0.34.18.32 — Winners page product framing and header join

- Updated Most Recent Win and winner-card product media to centered contain framing on a near-black surface, with no image hover zoom/cropping.
- Removed the measured WoodMart content-wrapper top padding only on the Winners page so the hero joins the header border.
- Set the native Winners sort select and its options to Chromium/Windows dark color-scheme styling.
- Presentation only; raffle selection, results, entries, reservations, checkout, notifications, fulfillment, audit data, table/meta contracts, and internal schema compatibility VERSION (0.34.18) are unchanged.
v0.34.18.33 — Winners phone layout refinement

- On phones, Most Recent Win is a compact 190px horizontal card with a 125px media rail and a tightly balanced result column.
- On phones, the Winners grid is one full-width card per row with 200px imagery and larger readable product, winner, metadata, and button text.
- Presentation only; raffle selection, results, entries, reservations, checkout, notifications, fulfillment, audit data, table/meta contracts, search/sort behavior, and internal schema compatibility VERSION (0.34.18) are unchanged.
v0.34.18.34 — Winners phone card compaction

- Kept the existing mobile Most Recent Win layout untouched.
- Reduced only regular phone winner-card media to 155px with a 145px contained product maximum, tightened the content stack, and placed draw metadata in two compact columns.
- Presentation only; raffle selection, results, entries, reservations, checkout, notifications, fulfillment, audit data, table/meta contracts, search/sort behavior, and internal schema compatibility VERSION (0.34.18) are unchanged.
v0.34.18.35 — Winners phone horizontal result cards

- Redesigned only regular mobile Winners cards as 120px media-rail result cards with compact right-side details and a dedicated bottom result-link row.
- The featured Most Recent Win section, desktop/tablet winner cards, data, filtering, searching, and sorting remain unchanged.
- Presentation only; internal schema compatibility VERSION remains 0.34.18.


v0.34.18.36 — Winners mobile product titles
- Mobile All Winners cards now show the complete product name instead of truncating it with an ellipsis.
- Long names wrap naturally; the compact horizontal card grows only as much as needed to preserve the full title.
- No draw, entry, order, notification, schema, or winner-selection logic changed.
v0.34.18.38 — Winners hero presentation refinement
- Rebalanced the public Winners hero, replaced the single tally line with a compact summary using the existing result count, and preserved the established raffle CTA destination.
- Reworked only the Most Recent Win presentation hierarchy while retaining the same latest result, masked winner name, prize, draw date, ticket, and product link.
- Set the featured product image stage to pure black (#000000) with centered contain sizing and protected safe space; no product media is cropped.
- Adopted the active RaffleLB Design System Manrope token for Winners-owned typography.
- Draw selection, entries, holds, fulfillment, database schema, AJAX filters, and schema compatibility remain unchanged.
