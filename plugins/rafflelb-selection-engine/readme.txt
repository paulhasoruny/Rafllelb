= 0.2.16 =
* Fixes the Selection Engine virtual-route browser title so it no longer appears as “Untitled”.
* Uses a product-specific browser title on individual Selection Status pages.

= 0.2.13 =
* Adds distinct privacy-safe public handling for early close-to-selection versus early cancellation/refund.
* Cancelled raffles retain their locked eligible-entry record but explicitly do not proceed to Selection or a winner.
* Early close-to-selection remains awaiting Selection with no refund.
* No new REST writes, schema, or private participant/refund metadata exposure.

=== RaffleLB Selection Engine ===
Contributors: rafflelb
Tags: raffle, woocommerce, transparency, results
Requires at least: 6.4
Requires PHP: 7.4
Stable tag: 0.2.19
License: GPLv2 or later

Public raffle transparency pages with Shop-owned native entry access and recorded results.

== Description ==

RaffleLB Selection Engine adds:

* An automatic `/selection-engine/` public hub.
* The `[rafflelb_selection_engine]` shortcode.
* Automatic `/selection/{product-slug}/` status pages for raffle-enabled WooCommerce products.
* The Shop-owned native raffle-entry form on open Selection pages, using the existing WooCommerce/Draw Engine purchase flow.
* Public, GET-only status, live-pool, and privacy-minimal locked-pool endpoints.
* A scrollable live active-entry list while open and finalized snapshot list after lock, both with server-masked participant names.
* Entry-number-only search and safe 60-row chunk loading for large raffles.
* A browser-only interactive demonstration that cannot affect official raffle data.
* A small “VIEW SELECTION STATUS” link on raffle product pages.
* A shared compact recorded-result component for Homepage and Winners.
* A public Winners presentation in which every result links to its permanent Selection page.

The plugin creates no database tables and contains no custom raffle-state mutation endpoint. Entry submissions use WooCommerce's native product form and the existing Shop/Draw Engine handlers.

== Installation ==

1. Upload and activate the plugin ZIP in WordPress.
2. Ensure WooCommerce and RaffleLB Draw Engine 0.34.18.42 or a compatible version are active.
3. Visit `/selection-engine/`.

If WordPress does not recognize the new routes immediately, visit Settings > Permalinks and save once.

== Changelog ==

= 0.2.19 =
* Restores the compact Selection Engine hero layout only; all 0.2.18 behavior remains unchanged.

= 0.2.18 =
* Restores the desktop Selection Engine chamber and keeps Allocation Progress slightly lower without shrinking the engine.

= 0.2.17 =
* Slower staged demo, balanced live Selection panels, and public Selection Status summary.

= 0.2.12 =
* Separates the administrator's public closure reason from a system-controlled Raffle Points refund-status line.
* Maps all non-complete refund states to public processing and includes that projection in the polling revision.
* Preserves all 0.2.11 visual, privacy, locked-pool, polling, and GET-only REST behavior.

= 0.2.10 =
* Stops reading or writing full public snapshot caches for LIVE and AWAITING raffles; COMPLETE snapshots retain the existing short cache.
* Keeps live REST/browser no-store behavior, cache-busting, unexpected-zero verification, immutable locked-pool access, and the complete 0.2.9 interface unchanged.
* Rejects live-pool page-one payloads when zero totals contain rows, positive totals contain no rows, or returned rows exceed the declared total.

= 0.2.9 =
* Reworked Eligible Entries into a compact two-column ledger with a fixed ticket column, left-aligned participant names, one outer border, a muted header, and seven-to-eight visible rows.
* Made live status and live-pool polling explicitly uncached, bypassed mutable live snapshot cache reads, and verified unexpected live zero counts against a fresh live-pool read before updating the interface.
* Preserves an existing register when its pool is unchanged, while legitimate new live entries still replace the register with the newly verified pool.

= 0.2.8 =
* Restored one single bordered register around the complete Eligible Entries list while keeping every row flat, gapless, and separated only by a thin horizontal rule.
* Preserved the compact 36px rows, eight-row viewport, subtle scrollbar, left/right alignment, search, responsive behavior, and all 0.2.7 data/privacy logic.

= 0.2.7 =
* Compacted only the Eligible Entries panel with 36px rows, thin separators, right-aligned participant names, a 304px desktop scroller, and a 40px search input.
* Removed the oversized inner-list box treatment and reduced panel spacing while preserving LIVE/LOCKED status, counts, search, pagination, data sources, and privacy behavior.

= 0.2.6 =
* Added a separately labelled LIVE list of current active entry numbers and server-masked participant names while a raffle remains open.
* Reuses the existing entry-number search and safe 60-row chunk loading for both mutable live data and immutable locked snapshots.
* Rechecks raffle state after each live query and fails closed if closure occurs; locked and completed raffles retain the 0.2.5 authoritative snapshot path unchanged.

= 0.2.5 =
* Added a read-only finalized eligible-entry verification list sourced only from Draw Engine's active immutable locked-pool revision.
* Added server-side `FirstName S***` masking, neutral fallback, entry-number-only search, 60-row GET pagination, winning-row marking, and compact responsive scrolling.
* Kept open raffles entry-private and declines to publish a historical list when no authoritative snapshot exists.

= 0.2.4 =
* Replaced embedded WooCommerce stock availability with a Selection-context `RAFFLE OPEN` badge.
* Suppressed the redundant product-page Selection Status link while the native form is embedded on that same Selection page.
* Preserved the native quantity, cart, capacity, reservation, checkout, and entry-generation flow.

= 0.2.3 =
* Added the Shop-owned native raffle-entry form to open public Selection pages with existing account, quantity, capacity, cart, checkout, reservation, and entry-generation behavior.
* Filtered Current Raffles to official live raffles with available capacity and added a compact empty state.
* Removes the entry panel immediately when public polling reports that the raffle no longer accepts entries.

= 0.2.2 =
* Rebuilt the desktop trust steps as one continuous border with three divided cells.
* Replaced mobile right-side arrows with clean centered vertical connectors between the three compact cards.
* Preserved all public-result data, ordering, masking, routes, and result-grid behavior from 0.2.1.

= 0.2.1 =
* Added safe full-bleed Winners layout, latest-result prize media, compact trust flow, unobstructed product imagery, and count-aware result grids.
* Moved recorded status labels into card bodies and fixed the false filtering empty state.
* Tightened responsive spacing and stacked mobile search/sort controls without changing public result semantics.

= 0.2.0 =
* Added shared public result data and visual helpers for Homepage and Winners.
* Added the recorded-results Winners presentation through the existing shortcode extension point without changing Draw Engine.
* Added compact/strong responsive engine visuals and permanent Selection result links.
* Kept public result output free of internal selection method, selected-by, manual, external, or administrative fields.

= 0.1.9 =
* Moved the product-page Selection Status CTA inside the live raffle panel using WooCommerce's server-side after-form hook.
* Positioned the single secondary CTA below the raffle controls and above the raffle trust row, with full-width desktop and mobile styling.
* Preserved the existing selection URL, raffle-product visibility rule, and all raffle/cart behavior.

= 0.1.8 =
* Reworked Current Raffles into a compact 4/3/2/1-column live-status browser with equal-height cards.
* Reduced desktop and mobile card, media, typography, badge, progress, CTA, and section spacing while preserving all live raffle content.
* Kept product artwork and the branded fallback centered, contained, uncropped, and presented on pure black media backgrounds.

= 0.1.7 =
* Moved all chamber readouts into a flat overlay shared by demo and real raffle engines, separate from transformed cylinder geometry.
* Increased center labels, floating entry tiles, technical state labels, and progress captions to crisp readable sizes.
* Preserved Manrope through `--rl-font` and added explicit font-smoothing and geometric text rendering for engine readouts.
* Removed scaling animation and heavy glyph glow from central and winning-entry numbers while retaining glow on their surrounding panels.

= 0.1.6 =
* Restyled the demonstration actions as a lime primary button and a dark bordered reset button using the RaffleLB Manrope token stack.
* Removed the demo-entry selector and fixed the browser-only demonstration pool at 100 entries.
* Tightened demonstration log spacing for both untimed and timestamped rows.
* Enlarged and standardized Current Raffles product media while preserving contained, uncropped artwork and the branded fallback.
* Kept Current Raffles cards equal-height with aligned content and a single-column mobile layout.

= 0.1.5 =
* Made the RaffleLB Design System `--rl-font` token the canonical Selection Engine typeface with a complete Manrope system fallback.
* Replaced Selection Engine Inter and Woodmart font-family overrides without bundling font files.
* Adopted shared RaffleLB weight, body-line, heading-line, and label-tracking tokens for appropriate UI roles.
* Preserved the dedicated handwritten hero phrase and all existing component dimensions and behavior.

= 0.1.4 =
* Improved individual status-page typography, contrast, and supporting-label readability.
* Clarified the Selection Engine back-context label and refined affected hero spacing.
* Moved the Official Prize caption below the product frame and enforced a pure-black contained image area.
* Corrected the Recorded Process Log empty-state alignment and wrapping.

= 0.1.3 =
* Strengthened the individual raffle hero with premium product framing, technical depth, and clearer public status hierarchy.
* Refined demo controls with a neon-lime primary action, dark bordered reset action, and custom black/lime entry selector.
* Increased final-result emphasis and added subtle chamber edge, ring, and tile animation with reduced-motion support.
* Added intentional technical treatments for empty logs, result panels, and public-raffle states.
* Replaced plain missing-image marks with premium neutral RaffleLB placeholders.
* Standardized Current Raffles card proportions and completed the mobile layout pass.

= 0.1.2 =
* Added route-scoped full-bleed Woodmart wrapper overrides to remove page gutters.
* Enlarged desktop heroes, timelines, statistics, dashboard panels, and supporting content.
* Rebuilt the central chamber with layered CSS geometry, depth, technical details, and state-specific lighting.
* Strengthened product presentation while retaining contained artwork.
* Reworked mobile ordering and the five-stage timeline into a compact horizontal composition.

= 0.1.1 =
* Real chambers now use real public entry samples after closure and neutral markers while open.
* Public trust wording now describes a secure process over the eligible locked entry pool without method claims.
* Product artwork now uses contained, padded presentation in hero and hub cards.
* Public snapshots now use a 20-second WordPress cache.
* Public routes and results now reject non-public, protected, private, and hidden products.

= 0.1.0 =
* Initial read-only public release.
