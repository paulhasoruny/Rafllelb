## 0.2.19
- Restored only the compact Selection Engine demo-page hero proportions.
- Keeps 0.2.18 chamber restoration, slower staged demo, and Winning Entry status summary unchanged.
- No live Selection, entry, winner, payment, reservation, or transactional logic changed.

## 0.2.18
- Restored the desktop Selection Engine chamber after the 0.2.17 panel-balancing CSS caused the fixed-height chamber to collapse on some desktop widths.
- Allocation Progress now sits modestly lower without turning the Selection Engine panel into a flex column.
- Keeps the slower staged demo and Winning Entry Selection Status summary from 0.2.17.
- No live Selection, entry allocation, winner, payment, reservation, or Draw Engine transactional logic changed.

## 0.2.17
- Slowed the browser-only demonstration into a deliberate multi-stage sequence so the Selection process can be followed visually instead of finishing almost instantly.
- Reduced-motion preferences now suppress motion without collapsing the demonstration timeline into a fraction of a second.
- Added a public-safe Selection Status summary beneath Winning Entry to use the available panel space and clarify Entry Pool, Selection Process, and Public Record states.
- Moved Allocation Progress lower in the desktop Selection Engine panel for better vertical balance.
- No live Selection, entry allocation, winner, payment, reservation, or Draw Engine transactional logic changed.

## 0.2.16
- Improved readability of the Entry Pool status note on both demo and live Selection pages.
- Increased the size, contrast, spacing, and line-height of “Entry pool prepared” / “Live Entry Pool” and their supporting copy.
- No Selection logic, entry logic, result logic, or data behavior changed.

## 0.2.15
- Hardened the Selection Engine browser title across classic themes and common SEO title filters, with a route-scoped browser fallback.

# 0.2.14
- Fixed virtual Selection Engine browser titles so `/selection-engine/` no longer falls back to “Untitled”.
- Added explicit product-specific titles for public Selection Status routes.

# 0.2.13 — split early-closure public states

- Reads the privacy-safe Draw Engine early-close mode and distinguishes proceed-to-Selection from cancellation/refund.
- Proceed-to-Selection keeps the authoritative locked pool in Awaiting Selection and clearly states that no Raffle Points refund was issued.
- Cancellation/refund becomes a public `Raffle Cancelled` state: locked entries remain visible as the record, Selection/Winner steps are marked not proceeding/not applicable, and no winner is implied.
- Refund status remains only `processing` or `complete`; private failure/admin/order/refund metadata is not exposed.
- Existing Eligible Entries visuals, polling protections, GET-only REST routes, masking, and locked-pool bridge are unchanged.

# Changelog

## 0.2.12

- Displays a system-controlled Raffle Points refund line separately from the administrator's public closure reason.
- Treats every public refund state except `complete` as `processing`.
- Includes refund status in the public snapshot revision so polling updates the notice after a successful retry.
- Preserves all 0.2.11 Eligible Entries styling and interaction improvements unchanged.

## 0.2.11

- Equal-height desktop status panels with a contained, independently scrolling Eligible Entries ledger.
- Improved entry-count, search, register-header, ticket, and participant typography.
- Inset search focus treatment that cannot cross the panel boundary.
- Public-safe early-closure notice sourced only through the Draw Engine read-only bridge.

## 0.2.10

- Full public snapshots are now read from and written to the Selection Engine transient/object cache only when status is `complete`.
- LIVE and AWAITING snapshots are calculated fresh; non-complete status responses retain `no-store`, allowing awaiting pages to detect recorded completion promptly.
- Tightened page-one live-pool validation so row count must agree with the declared total bounds.
- Preserved all 0.2.9 visual, live-refresh, privacy, REST-field, and immutable locked-pool behavior.

## 0.2.9

- Reframed Eligible Entries as a compact two-column register with a muted header, a 94px desktop/76px mobile ticket column, left-aligned masked names, and one outer border.
- Prevented mutable live snapshots from being served from the 20-second Selection Engine cache; locked/completed snapshot reuse and Draw Engine's authoritative locked-pool bridge are unchanged.
- Added no-store/cache-busting live polling, fresh live-pool verification before accepting an unexpected zero, incomplete-response retention, and pool-diff rendering so unchanged registers are preserved.

## 0.2.8

- Reframed the participant list as one compact bordered ledger with flat, gapless 36px rows and only 1px horizontal separators.
- Kept the last row separator-free, names right-aligned, tickets left-aligned, and the full register internally scrollable on desktop and mobile.
- UI and release metadata only; HTML, JavaScript, REST/data, masking, locked snapshots, database behavior, and Draw Engine are unchanged.

## 0.2.7

- Redesigned only the Eligible Entries panel as a compact technical register with 36px rows, thin separators, left/right alignment, and an internally scrollable 304px list.
- Reduced panel/search spacing and removed the oversized inner bordered-box treatment without changing live data, locked snapshots, search behavior, REST contracts, masking, or Draw Engine.

## 0.2.6

- Added a LIVE Eligible Entries list using current active Draw Engine entry data while the raffle remains open.
- Masks names server-side as `FirstName S***`, returns no ownership/contact/payment fields, and retains one public row per ticket.
- Added GET-only live pagination and entry-number lookup while preserving the 0.2.5 locked-snapshot and winning-entry behavior unchanged.

## 0.2.5

- Added a public verification list only for the active immutable locked-pool revision supplied by Draw Engine 0.34.18.42.
- Added server-masked participant names, entry-number-only search, GET-only 60-row chunks, a restrained winning indicator, and scroll-contained mobile layouts.
- Open raffles remain count-only. Historical raffles without an authoritative revision do not fabricate or publish an entry list.

## 0.2.4

- Replaced visible native stock availability in the embedded entry panel with a lime/black `RAFFLE OPEN` state badge without changing WooCommerce stock data.
- Made the product-page `VIEW SELECTION STATUS` callback skip only the Shop-owned embedded-form render context, since the user is already on that page.
- Kept the native WooCommerce quantity input and Enter Raffle submission path, including every existing Draw Engine transactional hook.

## 0.2.3

- Added an open-state-only Enter This Raffle panel to permanent Selection pages by calling the Shop plugin's shared renderer, which invokes WooCommerce's native add-to-cart template.
- Kept Draw Engine as the sole owner of quantity limits, capacity validation, cart metadata, reservations, checkout, and paid-entry generation; Selection adds no AJAX mutation route or table write.
- Filtered Current Raffles to official `live` raffles with available capacity and added a compact Shop-linked empty state.
- Removes the rendered entry controls when the existing status poll reports the raffle is no longer accepting entries.

## 0.2.2

- Rebuilt the desktop Winners trust steps as one continuous bordered three-cell container with shared corner radius and internal dividers.
- Replaced the incorrect right-side mobile step arrows with centered 16px vertical connectors between independently bordered cards.
- Preserved the latest-result duplication in Recorded Results so the hero and complete newest-first directory remain consistent.
- Kept public-result eligibility, Selection routes, REST behavior, winner masking, result records, and Draw Engine behavior unchanged.

## 0.2.1

- Broke the Winners canvas safely out of the Woodmart content container while retaining the existing site header and footer.
- Added the latest result's public WooCommerce product image in a compact contained black media stage.
- Moved Selection Recorded status rows below all result imagery and kept product media unobstructed.
- Rebuilt the trust content as one compact panel and tightened desktop/mobile vertical rhythm.
- Added count-aware one/two/many-result grids and a wide horizontal desktop treatment for a single result.
- Fixed the filtering empty state with an explicit `[hidden]` rule and initial JavaScript render pass.

## 0.2.0

- Added the shared read-only recorded-result visual and public result collection used by Homepage and Winners.
- Took over the existing `[rafflelb_winners]` presentation through the WordPress shortcode extension point, leaving Draw Engine unchanged.
- Added permanent `/selection/{product-slug}/` result CTAs, the Winners trust strip, responsive result cards, and accessible search/category/sort controls.
- Added a lightweight result-only stylesheet for Homepage performance; compact cards do not run the full Selection Engine animation or polling.
- Kept the public data contract limited to product, masked winner, winning entry, recorded time, public category, product URL, and Selection URL.

## 0.1.9

- Moved the product-page `VIEW SELECTION STATUS` CTA from the standalone product-summary position into the live raffle panel via the server-side `woocommerce_after_add_to_cart_form` hook at priority 5.
- Placed the single full-width secondary CTA directly after the raffle quantity/entry controls and before the existing trust row.
- Added restrained black/lime desktop and mobile styling while preserving the selection URL, raffle-enabled-product guard, Enter Raffle behavior, and quantity/cart behavior.

## 0.1.8

- Reworked only the Current Raffles presentation into a compact live-status browser with four wide-desktop columns, three medium columns, two narrow-tablet columns, and one mobile column.
- Reduced card height, product-media height, heading spacing, internal padding, status badges, progress bars, and supporting type while keeping titles and calls to action aligned.
- Preserved pure-black contained product imagery, the premium branded fallback, all live status data, raffle ordering, and selection-page links.

## 0.1.7

- Separated every demo and real chamber readout from the transformed cylinder, glass, rings, and core geometry using a shared flat overlay layer.
- Increased center labels, floating entry tiles, POOL/state labels, and progress captions to readable desktop and mobile sizes with restrained tracking.
- Applied the RaffleLB Manrope stack, font smoothing, and geometric text rendering directly to engine readouts.
- Removed scale animation and heavy text-shadow from central and winning-entry glyphs while preserving lime depth and glow on surrounding machinery and panels.

## 0.1.6

- Restyled the browser demonstration actions with RaffleLB’s lime primary and dark bordered secondary button treatments while retaining Manrope through `--rl-font`.
- Removed the demo-entry selector and fixed the browser-only demonstration at 100 entries.
- Corrected Demo Process Log row spacing for compact untimed and consistently aligned timestamped rows.
- Increased Current Raffles media to a consistent 280px desktop presentation with pure-black, contained, uncropped product artwork and the existing branded fallback.
- Preserved equal-height card content, bottom-aligned calls to action, and a 240px single-column mobile media treatment.

## 0.1.5

- Made RaffleLB Design System 0.1.2’s `--rl-font` token the canonical Selection Engine typeface, with Manrope and system fallbacks when the token is unavailable.
- Replaced Selection Engine-specific Inter and Woodmart font-family declarations without bundling or duplicating Manrope.
- Applied shared RaffleLB weight, body-line, heading-line, and label-tracking tokens to appropriate typography roles while preserving layout and sizing.
- Kept the decorative handwritten hero phrase on its existing handwriting stack.

## 0.1.4

- Improved individual status-page typography, contrast, weights, line-height, and supporting-label readability while retaining the approved black/lime design.
- Increased the visibility and spacing of the Selection Engine back-context label.
- Moved the Official Prize caption into its own row below the hero product frame and enforced a pure-black contained image presentation.
- Fixed the Recorded Process Log empty state so its label and message align and wrap cleanly.

## 0.1.3

- Strengthened the individual raffle hero with a larger product presentation, clearer live status hierarchy, technical grid depth, and more prominent raffle metrics.
- Refined demo controls with a neon-lime primary action, dark bordered reset action, and fully styled black/lime entry selector.
- Increased winning-number focus and added subtle chamber edge, ring, glass, and tile motion while preserving reduced-motion support.
- Added technical empty-state treatments without inventing raffle data and replaced plain missing-image marks with premium RaffleLB placeholders.
- Standardized Current Raffles card heights, media areas, title space, progress placement, and CTA alignment.
- Completed the mobile pass for containment, timeline legibility, stacked controls, single-column cards, and footer clearance.

## 0.1.2

- Removed Woodmart page-canvas gutters on Selection Engine routes with route-scoped full-bleed wrapper overrides.
- Increased desktop width, hero scale, panel heights, statistics, timeline legibility, and supporting-panel typography.
- Rebuilt the central engine as a layered cylindrical CSS chamber with glass/metal depth, technical rails, luminous rings, floating tiles, and truthful open/locked/completed visual states.
- Enlarged the contained product hero and hub-card artwork presentation without cropping.
- Added a deliberate mobile composition with product-before-stat ordering, a compact horizontal five-stage timeline, stacked dashboard panels, and no page-level horizontal overflow.

## 0.1.1

- Replaced fixed numbers in real Selection Engine chambers with neutral open-state markers and real public entry samples after closure.
- Reworded public trust messaging to describe a secure process over the eligible locked entry pool without revealing or implying a selection method.
- Changed individual hero and hub-card product artwork to padded `object-fit: contain` presentation on a near-black background.
- Added a 20-second WordPress cache for public status snapshots while keeping frontend polling at 25 seconds.
- Restricted public REST, dynamic selection pages, raffle cards, and related results to published, unprotected, publicly visible, non-hidden raffle products.
