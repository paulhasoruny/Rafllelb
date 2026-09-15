RaffleLB Raffle Manager 1.6.15

- Fixed Needs Attention so a successfully cancelled/refunded raffle is no longer flagged after its Points refund reaches complete.
- Added useful raffle sorting: Priority, Closest to Full, Newest, Oldest, and Name A–Z. Priority surfaces real admin work first and then active raffles closest to filling.
- Added remaining-entry count beside capacity progress.
- Removed the Action column from Raffle Operations; the prize name remains the Manage entry point.
- Added a Publishing column with safe one-click Draft/Publish controls. Drafting changes only the WooCommerce product publishing state; raffle entries/history/status are preserved.

RaffleLB Raffle Manager v1.6.13

Fresh Testing Reset:
- Added one consolidated Reset / Cleanup action for starting a clean QA cycle without deleting the catalog, users, referral relationships, or Raffle Points.
- The unified reset permanently deletes all WooCommerce orders through WooCommerce APIs, then clears all RaffleLB entries, entry-history/locked-pool snapshots, holds, winner/result rows, product-linked notifications, and Selection snapshot cache.
- Every raffle product is returned to fresh operational state: winner/Selection/early-close/refund/locked-pool metadata is cleared and managed raffle stock is restored to configured Total Entries.
- Products, images, prices, categories/tags, users/auth data, referral codes/relationships, Raffle Points balances and Points ledgers, plugin settings, coupons, and general/manual notifications are intentionally preserved.
- Referral statistics that are computed directly from order history can no longer count orders that were permanently deleted; stored Points and referral user data are not modified.
- Added strict checkbox + exact RESET TEST DATA confirmation and a fail-safe: if any WooCommerce order cannot be removed, the global raffle-table wipe stops instead of silently creating a partial operational reset.
- Existing targeted order cleanup and completed-raffle reset remain available below the unified tool; their product-state cleanup now also clears the newer public-note/mode/refund and authoritative locked-pool metadata introduced by later Draw Engine versions.

RaffleLB Raffle Manager v1.6.12

Early-closure outcome split:
- Replaces the single early-close action with two explicit choices: Close Early & Proceed to Selection, or Cancel Raffle & Refund Participants.
- Both choices reuse Draw Engine's authoritative locked pool; the Manager does not duplicate selection/refund logic.
- Cancelled/refunded raffles have a separate admin display state, no winner controls, and an idempotent Retry / Verify Points Refunds action only when needed.
- Early-close-to-selection raffles keep the existing secure/manual internal winner controls and explicitly show that no Points refund was issued.
- Existing actual-paid Entry Revenue logic from v1.6.11 is preserved.
- The proceed-to-Selection option is shown only when Draw Engine advertises split-mode capability; during an out-of-order upgrade the legacy cancel/refund path remains the only available action.

RaffleLB Raffle Manager v1.6.11

Winner contact details:
- Winner tab now shows the winner's WordPress username, email, phone number and related order alongside the existing winning-entry details.
- The phone shown prefers the verified RaffleLB Auth `_rafflelb_phone` when `_rafflelb_phone_verified` is `yes`; a WooCommerce billing phone is used only as a clearly labeled fallback for older/non-Auth customers.
- Added admin-only Call Winner and WhatsApp Winner actions. No phone/contact data is exposed on the public storefront.
- No draw, winner-selection, fulfillment, entry, order, or database logic was changed.

RaffleLB Raffle Manager v1.6.8

Correction pass on the v1.6.7 Operations redesign (visual design, status pills, Overview/Entries/Orders layout, and Needs Attention logic are unchanged from 1.6.7):
- Entries tab: search, the All/Active/Void filter, Clear, and pagination now return to #entries instead of dropping back to Overview after navigation.
- Orders tab: the All/Paid+Processing/Pending/Cancelled+Failed filter now returns to #orders the same way.
- Orders tab: order discovery now reads all historical entries for the raffle, not only active ones, so a cancelled/refunded order whose entries were correctly voided still appears (and shows correctly under Cancelled/Failed) instead of disappearing from the list. Active and void entry counts are now calculated separately per order, so an order with zero active entries shows "0 active (N void)" instead of a bogus #000 range. This is read-only discovery - no entry status, void state, or order is changed.
- Overview: removed the v1.6.7 Paid/Pending Revenue split, which implied more precision than the underlying data supports (current entry price multiplied by entry count, not the historical transaction price). Overview now shows a single "Entry Revenue" figure - the same current-price x claimed-count estimate already shown in the stats bar - labeled as an estimate rather than as authoritative paid/pending accounting.
- Export Entries CSV: customer name and email cells are now neutralized against spreadsheet-formula injection (a leading =, +, -, or @ is tab-prefixed) before being written with fputcsv(). Capability check, nonce verification, and the read-only nature of the export are unchanged.

RaffleLB Raffle Manager v1.6.7

Operations UI/UX redesign (presentational only - every function that writes or authoritatively computes raffle state is unchanged from 1.6.6):
- Raffle Operations list: redesigned Prize, Status, Entry/Capacity, Winner, Fulfillment and Actions columns with a clearer visual hierarchy (thumbnail + title + SKU, status/fulfillment pills, price + claimed/total + progress bar, structured winner block, primary/secondary/tertiary actions).
- New "Needs Attention" filter/tab, derived from existing draw status, early-close meta, and fulfillment status - no new database state, no Draw Engine change.
- Overview tab rebuilt into a metric grid plus Raffle/Winner/Fulfillment summary sections and a Recent Activity list read from the existing entry-history table. No draw/winner/fulfillment controls are duplicated here.
- Entries tab: added an Email column, an Active/Void filter, search across entry #/customer/email/order #, and a read-only "Export Entries CSV" action. The existing Void Entry mechanism is unchanged.
- Orders tab: added Payment method, Payment status and Date columns and a status filter, using existing WooCommerce order APIs only. The existing cancel/refund action and its nonce are unchanged.

RaffleLB Raffle Manager v1.6.3

- Completed the Monitor workspace split without changing Draw Engine handlers.
- Winner tab now contains winner details and winner-email controls only.
- Fulfillment tab owns the existing nonce-backed fulfillment form exactly once, including the fulfilled lock and Edit Anyway behavior.
- Audit tab is read-only and contains draw/audit/early-close information only.
- Overview now shows operational status, capacity, active holds, available entries, prices, revenue, and delivery type.
- Existing Entries, Orders, Draw, void, close, winner, email, fulfillment and order-protection actions remain unchanged.

RaffleLB Raffle Manager v1.4.1

Added whole-site test-order cleanup to Reset / Cleanup:
- New "Delete All Test Orders & Related Entries" danger-zone tool.
- Requires a confirmation checkbox, the exact typed phrase DELETE ALL TEST ORDERS, and a browser confirmation dialog.
- Permanently deletes all WooCommerce customer orders for all users (compatible with WooCommerce order APIs/HPOS), restores stock reduced by those test orders first, deletes RaffleLB entries and entry-history rows tied to those orders, and deletes draw-result rows whose winning order was removed.
- Affected raffle products have winner/draw/early-close state cleared and stock recalculated from Total Entries minus any active entries that remain. Winner/draw-complete notifications tied to removed test results are removed; products, customers, coupons, referral points, and general/manual notifications are not deleted.
- Shows the current order count and reports how many orders, entries, history rows, and draw-result rows were deleted.

RaffleLB Raffle Manager v1.4.0

Added Reset / Cleanup (danger zone) for wiping test raffle data:
- New "Reset / Cleanup" submenu under Raffles, listing every raffle that has a completed draw (winner, entry count, selected date, fulfillment status), each with a checkbox.
- Selecting one or more and confirming (a required "I understand" checkbox plus typing the exact word RESET, on top of a JS confirm() dialog) permanently deletes: the draw result/winner record, every paid entry for that raffle, its entry history, any RaffleLB Notifications rows tied to it (winner/draw-completed types), and clears the draw-status/winner/early-close product meta. The product's stock is reset back to its full Total Entries and put back in stock, as a fresh unentered raffle.
- WooCommerce orders are never touched - only the raffle-specific entries/results/notifications tied to them are removed, for cleaning up test data without affecting real order/revenue records.
- Skips anything selected that doesn't actually have a completed draw (e.g. merely sold-out/ready-to-draw), so it can't be used to wipe an in-progress raffle by mistake.

RaffleLB Raffle Manager v1.3.0

Tag picker on the Tags field:
- Add/Edit Raffle: the Tags field now lists every existing product tag as a clickable chip below the input, so you don't have to remember or retype tag names. Click a tag to add it to the field, click it again to remove it - it stays highlighted while active. Typing directly still works the same way (comma-separated).

RaffleLB Raffle Manager v1.2.0

Renamed the two prices for clarity, added a Retail Price column:
- "Ticket Price" is now labeled "Raffle Price" everywhere (Add/Edit form, Monitor stats). Same field (WooCommerce Regular Price / one raffle entry) - label only.
- "Buy It Now price" is now labeled "Retail Price" everywhere (Add/Edit form, Monitor stats) - it already was the price shown on the storefront as "RETAIL PRICE" (product page, shop cards, price filter), so the admin label now matches. Same field/meta (`_rafflelb_buy_now_price`) - no data change.
- All Raffles list: added a "Retail Price" column next to "Raffle Price", showing the direct-purchase price when Buy It Now is enabled, or "—" otherwise.

RaffleLB Raffle Manager v1.1.0

Simplified images, added Tags, added Delivery Type:
- Add/Edit Raffle: removed the separate "Homepage Hero Image" field entirely - it was an admin-only concept the requester didn't want here. There is now a single "Image" field (still the normal WooCommerce product image).
- Add/Edit Raffle: added a "Tags" field (comma-separated, saved to the standard product_tag taxonomy).
- Add/Edit Raffle: added a "Delivery Type" choice - Tangible/physical or Digital (voucher/gift card) - stored on a new `_rafflelb_item_type` meta key. This feeds RaffleLB Custom Checkout's existing Cash on Delivery gate directly per raffle, instead of relying only on its category-slug guess (see RaffleLB Custom Checkout v4.8.6). New raffles default to Tangible. Also shown on the Monitor page's stats row.
- Existing raffles that had a Homepage Hero Image set keep that meta untouched (this screen no longer manages or clears it) - edit it via the standard WooCommerce product screen if needed.

RaffleLB Raffle Manager v1.0.0

PURPOSE
A dedicated "Raffles" admin section, similar in spirit to WooCommerce's own
Products screen, purpose-built for RaffleLB: one place to create, edit, and
monitor every raffle instead of piecing it together across the generic
WooCommerce product editor and the old RaffleLB Entries screen.

This is intentionally a separate plugin from RaffleLB Draw Engine. It does
not duplicate the draw, fulfillment, or entry-voiding logic — it reuses the
Draw Engine's existing admin-post handlers (secure random draw, choose
winner, close raffle early, update fulfillment, send winner email, cancel/
refund order) so there is exactly one implementation of each. Deactivating
this plugin does not touch any raffle data; RaffleLB Draw Engine keeps
running everything on its own.

REQUIRES
- WooCommerce
- RaffleLB Draw Engine (for the entries/draw/fulfillment engine and DB
  tables this screen reads and writes)
- RaffleLB Draw Engine v0.33.62+ (adds the `rafflelb_redirect_to` support
  its admin-post handlers need to return here instead of the old Entries
  screen)
- RaffleLB Custom Checkout v4.8.6+ (reads the Delivery Type meta this
  screen sets; earlier versions just fall back to their own category
  guess, so nothing breaks without it)

WHAT IT DOES
- Adds a top-level "Raffles" admin menu (WooCommerce -> Raffles equivalent,
  its own icon in the sidebar).
- All Raffles: a searchable, filterable, paginated list of every raffle
  product — thumbnail, price, entries sold/total with a progress bar, draw
  status, winner (once selected), and fulfillment status.
- Add/Edit Raffle: a streamlined form (title, description, image, category,
  tags, ticket price, total entries, Buy It Now toggle/price, delivery type,
  publish/draft) instead of the full WooCommerce product-data tabs. Saves
  as a normal virtual/managed-stock simple WooCommerce product with
  RaffleLB Draw enabled, fully compatible with Draw Engine and the
  storefront.
- Monitor (per raffle): live stats (entries, revenue, Buy It Now price,
  delivery type), the same draw controls as the old Entries screen (close
  early / secure random draw / record chosen winner) scoped to just this
  raffle, winner + fulfillment management with the same "fulfilled" lock,
  a paginated entries list, and an orders list with cancel/refund (VOID
  protection).
- Hides the old WooCommerce -> RaffleLB Entries menu item from the sidebar
  (the page and its handlers are untouched and still work if linked to
  directly — nothing here deletes or migrates data).


v1.6.10
- Raffle Manager early-close form now sends both the private internal reason and the separate Public Early Closure Note required by Draw Engine 0.34.18.44.
- Adds customer-safe public-note guidance and surfaces the saved public note on the admin Ready to Draw record.
- Adds Draw Engine early-close/refund notice messages so failures are visible inside Raffle Manager.

v1.6.11
- Corrected admin Entry Revenue so it is calculated from the actual WooCommerce raffle line-item value attached to active entries instead of current raffle price multiplied by claimed count.
- Complimentary participation remains eligible and consumes raffle capacity, but its private `_rafflelb_complimentary=yes` order-item marker now explicitly contributes $0 to Raffle Manager revenue.
- Active-entry revenue respects discounted line totals and excludes shipping/order-level amounts; no checkout, entry allocation, Draw Engine, refund, or database-write logic was changed.



1.6.15
- Removed customer-facing raffle sorting controls from Raffle Manager; Store owns raffle discovery/sorting.
- Removed the Publishing column from the Raffle Operations list. Draft/Published state remains available inside the raffle Manage/Edit form.
