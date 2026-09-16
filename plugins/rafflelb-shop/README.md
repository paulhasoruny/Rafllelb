## 0.2.63
- Targeted fixes only — no card redesign, no rewrite of the card renderer, pricing, Search, or AJAX filtering. All five issues below were mobile-only (`max-width:767px`); desktop and tablet are unchanged.
- **Shopping Mode label centering**: `.rl-shop-view-mode` never got its own `justify-content`/`align-items` from `assets/mobile-store-card.css`, so a dead-code mobile rule in `raffle_archive_styles()` (`body.rafflelb-raffle-archive .rl-shop-view-mode{justify-content:flex-start!important;...}`, left over from an old single-column toolbar design removed in 0.2.59) was the only rule left standing for those two properties, left-aligning the label text inside an otherwise-centered button. Fixed by adding explicit `display:flex;align-items:center;justify-content:center;text-align:center` to the existing `.rl-store-reference .rl-shop-view-mode` rule in `mobile-store-card.css` (already at higher specificity than the old rule on every property they share) — no width, no markup, and no functionality change; the legacy rule itself is left as historical dead code rather than touched, per this file's established pattern.
- **Sort control hidden beside Filters**: `#rl-shop-controls .rl-shop-filter-toggle{width:100%!important}` in `rafflelb-shop.php` was written for an older `display:grid` toolbar layout, where `100%` meant "fill this one ~112px grid cell." `assets/mobile-store-card.css` later switched `.rl-shop-toolbar-actions` to `display:flex`, and in a flex row `width:100%` on a flex item becomes its flex-basis — so the real Filters button (a direct flex child) tried to claim the entire row's width, and with `flex-shrink:0` (`.rl-shop-filter-toggle{flex:0 0 auto}` in `mobile-store-card.css`) it never gave any of that back, squeezing its flex sibling `.rl-shop-sort` to zero width. Fixed by removing that one `width:100%!important` declaration from `rafflelb-shop.php` (replaced with an explanatory comment); the Sort trigger button built by the same file's script keeps its own `width:100%` from `.rl-shop-sort-trigger` (a different selector, scoped to its own parent), so that styling is untouched. No markup, JS, or Sort control replacement.
- **Product image not using available height**: `.product-element-top` used `align-self:start!important` plus `aspect-ratio:1/1!important`, pinning the image to a fixed square sized only by its own column width — leaving empty space below it whenever the information column (badge/category/title/prices/actions) was taller than that square. Changed to `align-self:stretch!important` (aspect-ratio removed) in both the main card rule and the `.rl-shop-view-retail` (Store Only) defensive override, so the image column now fills the full height of the rows it spans; width is unchanged (still the same `--rl-mcard-img` column, roughly 30% of the card) and the `<img>` keeps `height:100%`/`object-fit:contain`, so it grows into the extra height without cropping or distortion.
- **Raffle progress bar missing on mobile Store cards**: a 0.2.58-era rule intentionally hid `.rl-shop-raffle-progress` together with the old full "RAFFLE AVAILABLE" block and the bold claimed/left text. That rule now only hides `.rl-shop-raffle-live` and `.rl-shop-raffle-line strong`; `.rl-shop-raffle-progress` is unhidden and given compact mobile sizing (`height:5px;margin:3px 0`) instead. No new markup and no new progress logic — it's the exact same element/data `raffle_archive_progress()` already outputs, using its existing lime-on-dark track/fill styling; Store Only cards render no raffle box at all (existing mode logic), so no fake progress ever shows there.
- **Search results appearing as only one product**: `store_search_catalog_filter()` (the actual `/shop/?rl_search=…` query filter) was re-checked and confirmed to use a 1000-item cap and never touch `posts_per_page`, ruling out a PHP/AJAX query limit. Without live-site DOM access a specific clipping selector could not be pinpointed with certainty, so `assets/mobile-store-card.css` now defensively forces `height:auto;max-height:none;overflow:visible` on both `.products` and `.rl-raffle-card` on mobile, guaranteeing the result list is always exactly as tall as its real content and can never be height- or overflow-clipped to fewer cards than it contains. This is a hardening fix, not a confirmed root-cause fix — flagged as such in this version's report.
- Preserves everything the brief called out: horizontal card design, Retail/Raffle price display and the Store+Raffle two-price layout, Store Only price layout, product badges/colors, category/title layout, Search placement/clear ×/All-Store mobile hiding, Filters/Sort/category/subcategory/brand/price filtering, login redirects, Buy Now/Enter Raffle behavior, AJAX refresh, desktop Store design, the single product page, and all raffle-availability/product-mode logic.
- Presentation only, mobile-only, Shop/category archive only. Desktop, the single product page, AJAX search/filter/sort logic, and all Store/Raffle mode, brand, cart, checkout, reservation, points, referral and Selection logic are unchanged. The existing one-time cache purge and WP Rocket exclusions/filemtime cache-busting are unchanged and still run automatically on this version bump.

## 0.2.62
- Closes the last mobile CSS-ownership duplication: a second, later-printed copy of the same typography block existed at the end of `legacy_callback_18600` ("Keep the 0.1.95 type layer authoritative"), and `legacy_callback_18334` itself still had an **unconditional** (not inside any media query at all) copy of `#rl-shop-controls .rl-shop-view-mode` / `.rl-shop-toolbar-actions>.rl-shop-filter-toggle` / `.rl-shop-sort-trigger` (ID selectors, always outranking any number of classes) and `.rl-shop-prices:not(.is-dual-price) small`/`strong` (tied specificity with `assets/mobile-store-card.css`, decided by print order) — 0.2.61 only removed the mobile-only duplicate of the ID rule, not this always-on one. This is what let Store Only / Raffle Only's solo price type revert to 10px/18-20px on a phone instead of the compact mobile values.
- Fixed by wrapping both copies in `@media(min-width:768px)` in `rafflelb-shop.php` — no rule was deleted and no desktop value changed, only the viewport each block applies at is now explicit. The now-unreachable `max-width:767px` sub-blocks inside them (which only ever existed to retune those same rules for phones) are left in place for history rather than deleted.
- `assets/mobile-store-card.css` now genuinely owns mode-button font size, Filters/Sort font size, and Retail/Raffle Entry price type at `<=767px`, alongside the card geometry it already owned.
- No card redesign: 0.2.59 card geometry, the 0.2.60 search fix (Mode → Search → Filters/Sort order, Search All Store hidden on mobile, clear × position, `MutationObserver`/`matchMedia` behavior), the 0.2.61 Store Only `.rl-shop-view-retail` protection, and `raffle_product_price_html()` are all unchanged.
- Presentation only. Desktop, the single product page, AJAX search/filter/sort, and all Store/Raffle mode, brand, cart, checkout, reservation, points, referral and Selection logic are unchanged. The existing one-time cache purge and WP Rocket exclusions/filemtime cache-busting are unchanged and still run automatically on this version bump.

## 0.2.61
- Closes a CSS-ownership gap in `legacy_callback_18334`: its mobile `@media(max-width:767px)` block sized `#rl-shop-controls .rl-shop-view-mode` / `.rl-shop-toolbar-actions>.rl-shop-filter-toggle` / `.rl-shop-sort-trigger` using ID-prefixed selectors, which always outrank any number of classes — `assets/mobile-store-card.css`'s mobile toolbar sizing could never actually win against it. Removed from `legacy_callback_18334` (its unrelated desktop typography above that media query, and its Draw Engine proxy hook, are untouched).
- Adds a defensive `.rl-shop-view-retail` (dedicated STORE ONLY shop view) override to `assets/mobile-store-card.css`. `legacy_callback_18600`'s old Store Only desktop geometry (43%/57% split, fixed 26/58/54/54px rows, 54px price/Buy Now boxes) already lives inside its own `@media(min-width:768px)` block and should not reach phones through the CSS source — but that inline `<style>` tag isn't excluded from WP Rocket's optimization the way `mobile-store-card.css` is, and a page-optimizer mangling a media boundary is exactly what caused the 0.2.56/0.2.57 regressions. The new rules force the same compact geometry Store + Raffle already uses, at higher specificity than the desktop rule, so mobile Store Only can't revert to the tall old layout even if that rule ever leaked through.
- No card redesign: all 0.2.59 card geometry (`.price` wrapper grid placement, prices, badge position, image spanning from row 1, compact actions for all three modes) and the 0.2.60 search fix (Mode → Search → Filters/Sort order, Search All Store hidden on mobile, clear × position, `MutationObserver`/`matchMedia` behavior) are unchanged.
- Presentation only. Desktop, the single product page, `raffle_product_price_html()`, AJAX search/filter/sort, and all Store/Raffle mode, brand, cart, checkout, reservation, points, referral and Selection logic are unchanged. The existing one-time cache purge and WP Rocket exclusions/filemtime cache-busting are unchanged and still run automatically on this version bump.

## 0.2.60
- Fixes the mobile control order (Shopping Mode → Search → Filters/Sort): `.rl-shop-toolbar-actions` (Filters + Sort) lives inside `#rl-shop-controls`, while `#rl-store-search` is a separate section that `store_search_ui()`'s own `place()` script inserts right after the whole toolbar — no CSS could put that sibling between two of the toolbar's own children. `place()` (in `rafflelb-shop.php`) now checks the viewport: on `<=767px` it inserts `#rl-store-search` inside `#rl-shop-controls`, immediately before `.rl-shop-toolbar-actions`; desktop keeps its original "after the whole toolbar" placement. The existing `MutationObserver` re-applies this placement after AJAX/DOM updates, and a new `matchMedia` listener re-applies it if the viewport crosses the 767px breakpoint.
- Fixes the mobile search clear (×) button position: the base `.rl-store-search-clear{right:210px}` assumes the Search All Store toggle still occupies the space to its right, which is no longer true on mobile now that the toggle is hidden there (below). Mobile now gets its own `right:6px` position and the input a matching `padding-right:40px` so typed text never runs under the ×.
- Hides the Search All Store toggle on mobile only (`display:none`, mobile breakpoint) so the compact search field is exactly one row (icon, input, ×) matching the approved reference; its checkbox, JS, URL/query handling ("respect the selected Shopping Mode unless All Store is checked"), and desktop appearance are all unchanged — nothing was removed, only hidden on phones.
- Does not touch any of the 0.2.59 card fixes (`.price` wrapper grid placement, Retail/Raffle prices, badge in the information column, image spanning from row 1, compact Store Only/Raffle Only/Store+Raffle actions, the removed legacy vertical-card CSS, the `shop-reference.css` dependency, WP Rocket/RUCSS protections, or the one-column mobile `.products` grid).
- Presentation/behavior only, mobile-only (`max-width:767px` plus the `place()` viewport check), Shop/category archive only. Desktop, the single product page, and all Store/Raffle mode, filter, sort, brand, cart, checkout, reservation, points, referral and Selection logic are unchanged. The existing one-time cache purge and WP Rocket exclusions/filemtime cache-busting are unchanged and still run automatically on this version bump.

## 0.2.59
- Fixes the actual "missing prices" bug after live-DOM inspection: WoodMart's loop template wraps `raffle_product_price_html()`'s `.rl-shop-prices` output in its own `<span class="price">`, which never became `display:contents` — only `.product-information` and `.product-element-bottom` did. `.rl-shop-prices` was therefore never a direct grid child of `.product-wrapper`, so grid-placing it did nothing; the real (unplaced) grid item was `.price`, which fell back to implicit auto-placement. `assets/mobile-store-card.css` now grid-places `.price` itself and lets `.rl-shop-prices` fill it — Retail Price / Raffle Entry are visible again for all three modes, still entirely from the existing `raffle_product_price_html()` output.
- Corrects the badge/image/text geometry to match the approved reference: the product-type pill now sits in the information column next to the image (row 1, column 2) instead of a full-width row above the whole card, and the image starts at row 1 and spans every information row instead of starting below the badge.
- Store Only / Raffle Only no longer get a giant full-width action footer: Buy Now / Enter Raffle now render inside the information column at a compact size, matching Store + Raffle's compact action row instead of stretching under the image.
- Neutralizes the old vertical/equal-height mobile card CSS that was still fighting the new grid card: `legacy_callback_18430`'s entire mobile block (flex-column `.product-wrapper`/`.product-information`, fixed title/category heights, `margin-top:auto` actions) is now a no-op, and `legacy_callback_18334` keeps only its unrelated desktop typography rules. Both functions and their RaffleLB Draw Engine proxy hooks are left in place — only their old mobile card geometry was removed, not the compatibility bridge. `legacy_callback_18600`'s unrelated fixes (square product image, raffle price/suffix overlap) are untouched.
- Removes the old `@media(max-width:767px)` block inside `store_search_ui()`'s own `wp_footer`-printed `<style>` (it re-styled `.rl-store-search-shell`/`.rl-store-search-all` and, being printed after `assets/mobile-store-card.css`, was a second, later-loading source of mobile search styling); the search-results-dropdown-only mobile tweaks in that same block are kept. `assets/mobile-store-card.css` is now the only source of mobile `.rl-store-search` presentation.
- Removes the non-functional "SHOPPING MODE" pill/eyebrow on mobile entirely (previously just shrunk); the three mode buttons underneath are unaffected and fully functional.
- Presentation only, mobile-only (`max-width:767px`), Shop/category archive only. Desktop, the single product page, and all Store/Raffle mode, filter, sort, brand, cart, checkout, reservation, points, referral and Selection logic are unchanged. The existing one-time cache purge (`mobile_store_assets_version_bump_purge()`) and WP Rocket exclusions/filemtime cache-busting for `assets/mobile-store-card.css` are unchanged and still run automatically on this version bump.

## 0.2.58
- Root-causes why the compact mobile Store cards (0.2.56/0.2.57) still rendered broken/missing prices on the live site: `assets/shop-reference.css` is unconditionally enqueued on every Shop/category archive with the `rl-store-reference` body class always present alongside `rafflelb-raffle-archive` (`legacy_callback_18802` / `legacy_callback_18806`, proxied from RaffleLB Draw Engine) — a fact hidden by a truncated `grep` during the original investigation. Its selectors are always `body.rafflelb-raffle-archive.rl-store-reference ...`, one class more specific than `mobile-store-card.css`'s `body.rafflelb-raffle-archive ...`, so shop-reference.css's own (desktop) 3-column `.products` grid and 42%/58% card split were unconditionally winning the cascade on phones too, including forcing the desktop product grid onto phone widths — which both hid/squeezed the price boxes and produced the reported "search overlapping the first product" (a full-width search panel sitting above a cluster of cards crushed to roughly a third of the screen width).
- Fixes this properly instead of another blind patch: moves every `@media(max-width:...)` rule that used to live in `shop-reference.css` (the real, working, previously-tuned mobile narrowing for `.products`, `.rl-shop-hero`, `.rl-shop-toolbar`, etc.) into `assets/mobile-store-card.css`, so `shop-reference.css` is now desktop/base-only, and gives every selector in `mobile-store-card.css` the matching `.rl-store-reference` specificity plus an explicit WordPress style dependency on the `rafflelb-store-reference` handle, so it always loads after shop-reference.css and always wins. `mobile-store-card.css` is now the single, authoritative mobile Store implementation (hero, toolbar, search, product grid and cards all in one file).
- All prices continue to come from the existing `raffle_product_price_html()` output (no hard-coded prices): Store + Raffle shows both the Retail Price and Raffle Entry boxes side by side, Store Only shows Retail Price only, Raffle Only shows Raffle Entry only.
- Still no heart/favorite/wishlist icon. Presentation only, mobile-only (`max-width:767px`), Shop/category archive only; desktop, the single product page, and all Store/Raffle mode, filter, sort, brand, cart, checkout, reservation, points, referral and Selection logic are unchanged.

## 0.2.57
- Root-causes why the 0.2.56 mobile Store card redesign never appeared on the live site: it was an inline `<style>` block printed on `wp_head`, and WP Rocket's Remove Unused CSS (RUCSS) rewrites/strips inline blocks it doesn't recognize — this plugin already had to safelist three other inline blocks for the same reason, and 0.2.56's block was simply never added to that list. On top of that, a plain file update does not purge WP Rocket's existing page cache by itself, so anonymous/mobile visitors could keep receiving HTML generated before 0.2.56 existed.
- Moves the mobile card/toolbar/search CSS out of an inline `<style>` block and into an enqueued file, `assets/mobile-store-card.css`, protected from WP Rocket's CSS optimization the same proven way `assets/single-product.css` already is (`rocket_exclude_css` / `rocket_minify_excluded_external_css` / `rocket_exclude_defer_css` / `rocket_rucss_safelist`).
- Adds a one-time, idempotent cache purge that fires the moment this plugin's version changes (works for common cache plugins: WP Rocket, W3 Total Cache, WP Super Cache, LiteSpeed Cache, plus an opcache reset), so a plugin update is enough on its own — no more relying on someone remembering to clear cache.
- Trims the large `RAFFLE AVAILABLE` / progress bar / claimed-left block on the MOBILE STORE ARCHIVE cards down to a single compact "left" line (the already-closed "RAFFLE CLOSED" state was one line already and is untouched); the full progress block is unchanged everywhere else, including the product detail page.
- Further compacts the mobile Shopping Mode row, the search panel (`#rl-store-search`), and Filters/Sort so more of the product list is visible without scrolling.
- Still no heart/favorite/wishlist icon. Presentation only, mobile-only (`max-width:767px`), Shop/category archive only; desktop, the single product page, and all Store/Raffle mode, filter, sort, brand, cart, checkout, reservation, points, referral and Selection logic are unchanged.

## 0.2.56
- Redesigns the mobile (phone-width) `/shop/` and category archive product cards as compact single-column horizontal cards: image on the left, product-type badge/category/name/price on the right, one full-width Buy Now / Enter Raffle row underneath.
- Adds a small "BUY + RAFFLE" / "STORE ONLY" / "RAFFLE ONLY" pill per card, replacing the old full-width "STORE ONLY" status note on mobile only.
- Compacts the mobile Shopping Mode row, search area, and Filters/Sort row so the toolbar takes noticeably less vertical space.
- No heart/favorite/wishlist icon is added; the existing wishlist-hiding rule is preserved.
- Presentation only, mobile-only (max-width:767px), Shop/category archive only. Desktop layout, the single product page, Store/Raffle/Both mode logic, search, filters, sort, brands, cart, checkout, Selection, reservations, points, referral, and SEO are all unchanged.

## 0.2.52
- Restores the Filters discovery hint to its original dismissible behavior while removing persistent browser memory. The hint hides when Filters is opened or after 12 seconds, but it appears again on every new Shop visit or refresh.
- Treats a browser Back/Forward cache return to the Shop as a new visit so the hint can reappear without using localStorage.
- No Store, raffle, category, brand, price, checkout, reservation, points, or Selection logic changed.

## 0.2.41
- Stabilizes the Filters discovery hint by anchoring it in document space instead of continuously recalculating its fixed position while the page scrolls. The hint now scrolls naturally with the Filters button without visible jumping.
- Replaces the simple down-arrow badge with a cleaner animated Filters/sliders icon while keeping the pointer aimed at the real Filters control.
- Keeps viewport clamping and responsive behavior for narrow screens, and only recalculates on resize/orientation changes.
- No Store, raffle, category, brand, price, checkout, reservation, points, or Selection logic changed.

## 0.2.40
- Fixes the one-time Filters discovery hint positioning on mobile so the pill is clamped fully inside the viewport instead of being cut off at the left edge.
- Repositions the hint using its measured width/height and keeps the pointer aimed at the real Filters button on desktop and mobile.
- Uses a shorter mobile label while retaining the full desktop message, plus safer resize/orientation handling.
- No Store, raffle, category, brand, price, checkout, reservation, points, or Selection logic changed.

## 0.2.33
- Fixes the Brands filter disappearing when WoodMart maintains separate desktop/mobile or logged-in filter DOM copies. Brands now mount in every active category filter area instead of only the first matching DOM node.
- Brand discovery now reads persisted product/category/brand relationships directly and avoids session-sensitive WooCommerce visibility checks.
- Uses a fresh brand-cache generation and a no-cache AJAX fallback so newly assigned brands appear consistently for logged-in and logged-out shoppers.

# RaffleLB Shop 0.2.30
- Finalizes the dynamic Brands filter for Store category archives. Brands remain hidden on the main Shop until a category is selected.
- After category selection, the filter shows only brands represented by visible products in that category and the active Shopping Mode (STORE & RAFFLE / STORE ONLY / RAFFLE ONLY).
- Uses the detected product brand taxonomy and a RaffleLB-owned `rl_brand` catalogue query, so filtering is independent of WoodMart/WooCommerce layered-navigation URL formats.
- Adds **All Brands**, resets stale/incompatible brand choices automatically after category/mode changes, and keeps the filter responsive with horizontal scrolling on mobile.
- Preserves existing category, subcategory, price, sort, Store/Raffle mode, checkout, reservation, points, referral, and Selection logic.

# RaffleLB Shop 0.2.28
- Rebalances desktop Store + Raffle cards to a 38.5/61.5 image-to-information split while retaining a large, centered product image.
- Gives the dual-price row a clearly differentiated 38/62 Retail-to-Raffle split, with equal box geometry and aligned labels and values.
- Keeps large Retail values such as `1,450.00 $` intact and Raffle values such as `15.00 $ / entry` on one comfortable baseline without shrinking the dominant entry amount.
- Adds explicit presentation classes for combined products and their dual-price row so Store Only, Raffle Only, tablet, and mobile layouts keep their dedicated behavior.
- Reconciles the remaining generic inline and asset rules so the responsive dual-price implementation has one authoritative source.
- Presentation-only change; retail/entry values, WooCommerce pricing, schema/SEO, checkout, reservations, Selection, points, referral, and product-card behavior are unchanged.

# RaffleLB Shop 0.2.25
- Replaces the accumulated 0.2.21–0.2.24 Store + Raffle pricing overrides with one responsive dual-price component.
- Keeps Retail Price slightly narrower while guaranteeing room for large values such as `1,450.00 $`; Raffle Entry receives the wider column and keeps values such as `15.00 $ / entry` on one baseline.
- Normalizes label alignment, box height, radius, padding, currency containment, and narrow-screen type scaling without stacking the boxes.
- Presentation-only change; retail/entry values, WooCommerce pricing, schema/SEO, checkout, reservations, Selection, points, referral, and product-card behavior are unchanged.

# RaffleLB Shop 0.2.20
- Fixes Merchant shipping/return schema for RaffleLB physical Store + Raffle products that WooCommerce marks virtual for entry-flow behaviour.
- The WooCommerce virtual flag no longer suppresses Lebanon shipping and 7-day return schema for physical RaffleLB prizes.
- Downloadable products and voucher/gift-card category products remain excluded from physical shipping/return schema.
- Adds Google Merchant shipping details to physical direct-purchase offers: Lebanon (`LB`), USD 4.50 delivery, and a typical 1–3 day delivery window.
- Adds a 7-day finite merchant return window to physical direct-purchase offers, matching RaffleLB's published Shipping & Returns policy.
- Existing `shippingDetails` or `hasMerchantReturnPolicy` schema from another integration is preserved and never overwritten.
- Virtual/downloadable items are intentionally excluded because vouchers/digital codes can have different fulfilment and return rules.
- Keeps the 0.2.18 automatic product meta-description generation and the 0.2.17 retail-price schema correction.
- No checkout, Selection, payment, reservation, points, referral, order, or pricing calculations are changed.

- Adds automatic, mode-aware product meta-description generation through The SEO Framework when a product has no manually saved SEO description.
- **Store + Raffle:** generated description promotes both direct purchase and the live Selection without using internal Draw wording.
- **Raffle Only:** generated description mentions only the Selection route.
- **Store Only:** generated description mentions only direct purchase.
- Manual per-product The SEO Framework descriptions remain untouched and take priority.
- Product titles remain under The SEO Framework's normal branded title generation because it is already producing clean `Product Name - RaffleLB` titles.
- No cart, checkout, pricing, payment, reservation, points, referral, or Selection transactional logic is changed.

# RaffleLB Shop 0.2.17
- Corrects WooCommerce Product/Merchant structured data for Google without changing checkout, Selection, pricing, reservations, orders, or payment logic.
- **Store + Raffle:** schema now exports the configured Buy Now/retail price instead of the Selection entry price.
- **Raffle Only:** remains indexable as a normal web page but is removed from WooCommerce Product/Merchant rich-result output, so an entry fee cannot be advertised as the prize's selling price.
- **Store Only:** native WooCommerce schema remains unchanged.
- Existing seller, availability, shipping/return fields and other schema additions are preserved when a retail Offer is present.

# RaffleLB Shop 0.2.16
- Fixed **View All Results** so it always opens the Store with the active search term applied instead of falling back to the full catalogue.
- The View All link now bypasses WoodMart archive AJAX interception and performs a full filtered navigation.
- Added a late WooCommerce product-query safety pass so `rl_search` remains authoritative even if the theme rebuilds the catalogue query.
- Existing instant search, typo correction, Store-mode scope, banners and transactional logic are unchanged.

# RaffleLB Shop 0.2.15
- Store search now auto-corrects high-confidence customer misspellings using a catalogue-derived spelling lexicon.
- Example: `airfrier` can automatically become `Air Fryer` and search the correct products.
- Corrections learn from product titles, categories and brands, with conservative fuzzy matching to avoid changing valid searches.
- Instant results visibly show the correction, and View All uses the corrected search term.
- Existing Store mode filtering, banners and search features are preserved.

# RaffleLB Shop 0.2.14
- Added a dedicated RaffleLB Store search directly below the Shopping Mode controls.
- Instant search matches product names, parent/variation SKUs, product categories, and registered product brand taxonomies.
- Search respects STORE & RAFFLE / STORE ONLY / RAFFLE ONLY by default, with a separate SEARCH ALL STORE toggle.
- Results show product image, product type, category, price context and SKU, plus a View All Results route that filters the Store page.
- Search is read-only and does not alter cart, reservation, checkout, entry, capacity, payment or Selection logic.

# RaffleLB Shop 0.2.13
- Added independent per-banner controls for font size (10–24 px), font weight, banner height (38–64 px), and moving speed (5–60 seconds).
- Moving speed is now honored on desktop and mobile; lower seconds means a faster ticker.
- Existing banner settings remain backward compatible and automatically receive sensible defaults.

- Redesigned public promotion banners as a premium full-width announcement strip directly below the site header.
- Animated mode now uses a real right-to-left ticker path and no longer duplicates short messages side-by-side.
- Removed the reduced-motion override for this explicit admin-selected moving mode, which could previously make Animated appear static.
- Hovering a moving banner pauses it for readability; it resumes when the pointer leaves.

## 0.2.8
- Fixed Store Banners admin routing and placed it inside the active RaffleLB / Raffle Manager admin menu. Use WordPress admin.php?page=rafflelb-shop-banners.

# RaffleLB Shop 0.2.6

- Store & Raffle and Raffle Only archive media now use the same full black image well as Store Only.
- Added independent Store & Raffle, Store Only, and Raffle Only promotion/update banners. Each can be enabled separately and set to animated/moving or static.

# RaffleLB Shop 0.2.5 — Raffle Only customer sorting

- In the dedicated RAFFLE ONLY shop mode, every visible raffle card now shows **View Selection Status** directly beneath its primary Enter Raffle / Raffle Details action.
- The link uses the Selection Engine adapter when available and falls back to the existing `/selection/{product-slug}/` route.
- The status link is public/read-only and does not change entry, cart, checkout, or Selection logic.

# RaffleLB Shop 0.2.2 — embedded open-state availability

- Suppresses WooCommerce's visible stock-availability text only while the Selection-page native entry form is rendering.
- Exposes the existing temporary render context through a read-only helper so Selection can omit its redundant product-page status link.
- Adds the explicit `RAFFLE OPEN` state badge to the embedded panel without modifying WooCommerce stock data or transactional hooks.

# RaffleLB Shop 0.2.1 — scoped embedded entry presentation

- Wraps the Selection-page native WooCommerce form render in a temporary Shop context flag.
- Suppresses only the product-page `SELECT NUMBER OF ENTRIES` label and `.rl-entry-trust` row while that embedded form renders; all WooCommerce and Draw Engine transactional hooks remain active.
- Simplifies the embedded panel to its title, entry price, native quantity input, and native Enter Raffle button.
- Updates visible Shop wording from `Verified draw`, `draw process`, and `draw terms` to equivalent Selection terminology without renaming internal identifiers or APIs.

# RaffleLB Shop 0.2.0 — shared Selection entry form and raffle-state badge

- Adds a narrow `RaffleLB_Shop::selection_entry_form()` extension point for the Selection Engine. It renders WooCommerce's native single-product add-to-cart form, preserving Draw Engine quantity limits, capacity validation, cart metadata, reservations, checkout, and paid-entry generation.
- Reuses Shop's existing account policy and login URL for guests; it creates no alternate entry endpoint or direct table write.
- Makes the raffle product header state-aware: open raffles retain the normal availability badge, locked raffles show `RAFFLE CLOSED`, and recorded winners show `SELECTION COMPLETE`.
- Does not alter WooCommerce stock quantities or stock-management settings. Direct-purchase availability remains in the Buy Direct panel.

# RaffleLB Shop 0.1.94 — Store Only default-view status row text

0.1.94 changes only the two labels in the default ALL PRODUCTS view's Store
Only status row: `DIRECT PURCHASE` / `SHIPS FROM STOCK` (or `Currently
unavailable`) becomes `STORE ONLY` / `DIRECT PURCHASE`. Same markup, same
CSS, same row height/spacing/typography. No other archive-card content,
query, or template changed.

# RaffleLB Shop 0.1.93 — fix Raffle Only price box markup in the default view

0.1.93 fixes one thing left over from 0.1.92: a genuine Raffle Only product in
the default ALL PRODUCTS view was rendering its price in a generic
`.rl-shop-prices` container (`.rl-shop-price-main`), which the CSS treats as
the Store + Raffle dual-price grid. It now uses the same solo
`.rl-shop-prices.is-raffle-only` / `.rl-shop-price-raffle` markup already used
by the dedicated RAFFLE ONLY view, carrying the authoritative WooCommerce
price. No other logic, CSS, query or template changed.

# RaffleLB Shop 0.1.92 — default Shopping Mode becomes the full catalogue

0.1.92 changes only Shop/archive Shopping Mode behavior and mode-aware card
contents. Single-product pages are untouched.

The default STORE & RAFFLE Shopping Mode is now the complete catalogue: it
shows Store Only, Raffle Only and Store + Raffle products, so a homepage
category link never lands on a falsely empty view because its products
happen to be a type the old default excluded. STORE ONLY and RAFFLE ONLY
modes are unchanged (Store Only + Store & Raffle, and Raffle Only + Store &
Raffle, respectively).

In the default view each card now reflects the product's real classification
instead of always showing both a Buy Now and an Enter Raffle control:
- Genuine Store Only: real WooCommerce price, BUY NOW only.
- Genuine Raffle Only: raffle entry price and availability, ENTER RAFFLE only.
- Store + Raffle: unchanged, both routes.

All three continue to share the existing card shell (image, title, price row,
status row, bottom action row), so outer card width/height and the bottom
action row stay identical across a row regardless of a product's type.

The default view's catalogue query has no classification restriction at all
(previously it wrongly required Store + Raffle's own meta), so it is simpler
than either dedicated mode's query, not more expensive. The Store Only query
optimization and Shopping Mode navigation capture are untouched.

---

# RaffleLB Shop 0.1.92 — shared product shell

0.1.90 trims the Store Only desktop left column to a fixed 580px (gallery and
Product Information together), above the 901px breakpoint only. The shared
architecture below is unchanged.

0.1.89 removes the second copy of the single-product layout. Raffle, Store &
Raffle and Store Only now render one shared visual shell:

    RaffleLB_Shop::render_product_shell()
      div.product
        div.product-image-summary-wrap
          div.product-image-summary.rl-product-layout-ready
            div.product-image-summary-inner
              div.rl-product-breadcrumbs
              div.rl-product-layout
                div.rl-product-left    gallery + Product Information
                div.rl-product-right   summary (title + stock/category) + purchase area

Shop adds the mode-neutral body class `rafflelb-product-page` to every product
page. `assets/single-product.css` scopes the whole shell to that class and is
the only single-product stylesheet. Mode classes scope purchase components
only: `rafflelb-raffle-product` for the raffle card, live panel, entry form and
Raffle Details; `rafflelb-store-product` for the compact Buy Direct card.
Store Only does not receive `rafflelb-raffle-product` and its metadata,
purchase mode and Draw Engine routing are unchanged.

`assets/store-product.css` is deleted. It was a 710-line duplicate of the shell
whose desktop block forced `grid-template-columns:444px minmax(0,1fr)` and
`max-width:444px` on the Store left column, which is what made Store Only look
different from the approved product pages.

Store Only purchasing stays native WooCommerce: `woocommerce_template_single_add_to_cart()`
supplies price, stock, quantity validation and Add to Cart, and no RaffleLB
purchase mode is submitted.

---

# RaffleLB Shop 0.1.43 — installation and rollback

## Install in order

1. Keep Core, Account, Homepage and all seven original feature plugins active.
2. Upload rafflelb-draw-engine-0.34.18.3.zip through WordPress Plugins > Add New > Upload Plugin. Replace the currently installed Draw Engine in the existing rafflelb-draw-engine folder. Do not delete it first or create a second Draw Engine folder. This is cumulative: Account and Homepage bridges are included.
3. Upload rafflelb-shop-0.1.43.zip and activate RaffleLB Shop.
4. Clear page/asset caches. Check Store, category archives and product pages on desktop and mobile, including the view selector, filters, sorting, prices, Buy Direct and Enter Raffle controls.

The setup now has eleven plugins. No Core, Account or Homepage replacement is needed.

## Scope and ownership

Shop supplies 47 existing functions covering catalog scope, Store/Raffle/Both view selection, retail sorting and price filtering, archive and product presentation, product information panels, category redirect, raffle marketplace shortcode, shop filters UI, loading-overlay presentation, footer widget repair and mobile Store fixes. Existing shop-reference.css and site-icon assets are copied byte-for-byte.

The exact extracted function list and original source locations are in extraction-map.json. This is a controlled code-ownership transfer, not a redesign or new shopping rule.

Preserved semantics include rl_view=both/retail/raffle; unknown modes default to both; retail and combined browsing use raffle-enabled products with enabled positive retail prices; winner-selected products are excluded from catalog browsing; sorting uses _rafflelb_buy_now_price for retail views; raffle price behavior is retained. Existing filters/actions, query argument handling and limits remain identical.

The rafflelb_raffles_marketplace shortcode keeps its name, attributes and behavior. Homepage's category/featured-product shortcodes remain owned by Homepage; Shop does not duplicate them.

Cart/order purchase-mode processing, cart prices, payment gateways, reservation/capacity management, order callbacks, entry creation/voiding, draw selection and winner persistence remain in Draw Engine and the existing feature plugins. Shop's product controls still submit the same fields to those existing handlers. Product settings/admin metadata handling remains in Engine.

Statistics, product/draw identification, retail price, direct-purchase eligibility and closed-draw checks delegate to five narrow Engine helper bridges. Statistics retain their existing effects, including expired-hold cleanup and draw-status handling; they have not been replaced by an independent read-only calculation.

Shared site-wide header, typography, cart/checkout styling and global loading overlay remain in Engine. A mixed legacy CSS callback affecting both Homepage and Shop remains there as well. Theme and saved WordPress/Elementor layouts are unchanged. The new modules still require Engine; this stage does not remove its fallback code or make it smaller.

## Compatibility

The updated Engine retains every original implementation and every existing hook registration. Each selected callback delegates at execution time only when Shop is loaded, Core >= 0.1.1 exists and SHOP_BRIDGE_VERSION equals 1. Hook names, identities, priorities, argument counts and registration order stay unchanged. Shop registers no duplicate callbacks or activation/deactivation migrations.

The new plugin header is 0.34.18.3. Internal Engine schema VERSION remains 0.34.18 so this extraction alone does not trigger an unnecessary schema upgrade. Core's original version-baseline diagnostic may flag this new header for review; that is expected.

Missing Shop or Core selects retained Engine implementations. Shop with an earlier Engine is inert. Account and Homepage bridge versions and code are retained in this update.

## Rollback

Deactivate only RaffleLB Shop and clear caches. The retained Engine implementations resume on the next request. Keep Account, Homepage, Core and other plugins active. This switch needs no database reset or data deletion.

If a full code rollback is needed, deactivate Shop and replace Engine with the previously installed 0.34.18.2 Homepage-stage ZIP from your backup. Earlier pre-Homepage versions would also disable Homepage delegation.

## Validation performed

- Both changed PHP files pass PHP 8.2.0 lint.
- All 47 extracted function bodies match their originals after only the documented Core-constant/Engine-helper substitutions.
- Removing only the new delegation/bridge insertions and reverting the plugin header reproduces the full Homepage-stage Engine PHP byte-for-byte. All earlier Account/Homepage work and transactional function bodies are retained.
- Both Shop assets match their original bytes; other Engine files/assets are unchanged.
- Six isolated scenarios pass: prior Engine, new Engine without Shop, Shop loaded before Engine, Shop loaded after Engine, missing Core and Shop with the prior Engine. Delegation readiness and fallback are explicitly checked.
- In every scenario, 48 catalog/filter/price cases match across Shop/category/product/other contexts, both/retail/raffle/invalid view modes and three price-filter inputs. Additional checks confirm non-main queries are left alone and repeated price-filter application does not duplicate the filter.
- 36 product-render snapshots per scenario match across live/ready-to-draw/winner-selected states, with direct purchase enabled and disabled. Product panels and styles are included.
- Account and sample-product Homepage snapshots remain identical. All 153 Engine hook operations match, including registration order, callback identity, priority and argument count. Selected Shop styles, mobile callbacks and asset enqueue output match after normalizing the intended asset-directory change.
- ZIP contents verified against delivered source.

These tests use small WordPress/WooCommerce API stubs and compare code outputs; generated SQL was not executed against a real database. Live theme/browser rendering, real pagination/filter queries, variable products, inventory edge cases, gateways, multiple-user holds, HPOS/multisite and production data were not exercised. PHP 7.4 is the declared syntax floor; runtime tests used PHP 8.2.0.

## Live acceptance

Check each Store/Raffle/Both mode, category links, ascending/descending sorting, price limits and filter reset. Verify retail prices versus entry fees, open/completed products, product information, Buy Direct and Enter Raffle controls on desktop/mobile. Confirm unchanged cart/checkout behavior and a representative payment/entry workflow. Recheck Homepage and My Account. Test Shop deactivation fallback and keep the backup until acceptance is complete.

Eleven plugins completes the planned additive module set. Removing legacy fallback code is a separate later cleanup requiring full site-level acceptance; it is not part of these packages.


## 0.1.3
Fixes mobile raffle-entry unit containment, centered Enter Raffle CTA, and custom Sort width by targeting the late inline legacy CSS/JS layer.

## 0.1.5
- Fixed mobile raffle-entry amount/suffix ordering and spacing.
- Made Shop product media square/frameless on desktop and mobile to match the Featured Products image treatment.
- Shop stylesheet now uses the Shop plugin version for cache-busting.


## 0.1.7

## 0.1.17

- Consolidates the active desktop Store Only card treatment around a 43/57 media/content split with compact retail pricing and a full-width Buy Now action.
- Keeps price markup inline so the currency symbol remains with the amount.
- Uses the two stable toolbar group separators (after Points and before Filters/Sort) while leaving mobile rules unchanged.
- Desktop-only Shop polish; mobile presentation is unchanged.
- Aligns Points, Shopping Mode buttons, Filters and Sort on one desktop row.

## 0.1.27

- Redesigns only the raffle-enabled WooCommerce single-product presentation to match the approved RaffleLB reference: gallery and product details on the left; title, Buy Direct and raffle-entry cards on the right.
- Keeps the native WooCommerce gallery, quantity/add-to-cart form, product data, account gating, Draw Engine progress, and Raffle Details markup intact. No purchase, reservation, entry-allocation, checkout, draw or database code changes are included.
- Adds responsive ordering so the mobile experience is gallery, title, Buy Direct, raffle entry, product details, then Raffle Details.

## 0.1.28

- Fixes the desktop column structure introduced in 0.1.27. The product-details mounting script now creates explicit left/right sibling columns around WoodMart's actual gallery wrapper and WooCommerce summary, instead of relying on theme-dependent nesting under `.product-image-summary-inner`.
- Breadcrumbs and the summary are mounted in the right column; the gallery and Product Details panel are mounted in the left column. At tablet/mobile widths the columns collapse to the required reading order.
- Uses the Homepage Featured Products Inter font stack on desktop.
- Keeps desktop raffle-entry `/ entry` inside its price box.
- Reworks Store Only retail pricing into a cleaner Featured Products-style price strip.

## 0.1.29

- Replaces resize-time product-details relocation with a single deterministic mount; responsive CSS now controls the gallery, summary and details reading order.
- Rebuilds the desktop Buy Direct action area as a compact vertical price-and-button rail, preserving the existing form and guest account gate while restoring readable copy width.
- Keeps the raffle card wide and compact: quantity and CTA share one row on desktop, while live progress, reservation and Draw Engine markup remain untouched.
- Aligns the gallery/Product Details left column and title/purchase/raffle right column on a balanced two-column grid.

## 0.1.30

- Consolidates raffle single-product presentation into `assets/single-product.css`; the historic inline v0.29/v0.30/0.1.28/0.1.29 rules are no longer rendered.
- Mounts native breadcrumbs above the two product columns and keeps product details beneath the native gallery.
- Adds semantic Buy Direct, raffle-header and trust-row icon markup while retaining the original WooCommerce and Draw Engine forms, fields and values.

## 0.1.31

- Restores the Raffle Details presentation in the authoritative single-product stylesheet: bordered four-column card, centered heading, lime accents and numbered markers.
- Removes the empty theme tabs/accordion wrapper and wrapper sizing that produced the large gap before Raffle Details, without negative-margin compensation.
- Hides only WoodMart raffle-product sticky add-to-cart controls and the duplicate live-panel status label; the primary Buy Direct and raffle forms remain intact.

## 0.1.32

- Restores the existing Raffle Details renderer by keeping its WoodMart wrapper visible while leaving only native Woo tabs hidden.
- Refines the single gallery border system, native quantity control, typography, card alignment and Product Details specification rows in `single-product.css`.

## 0.1.33

- Parses recognised generic specification labels from existing product descriptions into aligned label/value rows; unmatched source text remains visible.
- Adds data-backed Product Details feature items and inline action-button icons without changing forms or business logic.
- Aligns the Product Details and raffle-card bottoms through the existing desktop grid, and removes inherited text effects within the scoped product layout.

## 0.1.34

- Uses one shared two-row product grid: gallery/top content occupy row one; Product Details and raffle card occupy the shared second row and stretch together.
- Establishes a scoped system-sans typography stack across the product page and Raffle Details.
- Selects up to three icon-bearing feature cards from real parsed Processor, Camera, Connectivity, Battery, Size, or Warranty rows.

## 0.1.35

- Returns the desktop product composition to two stretched vertical column wrappers: Product Details and the raffle card are each the final flexible child of their own equally tall column. This keeps their bottom borders aligned while keeping the raffle card directly beneath Buy Direct rather than below the gallery.
- Refines the scoped system-sans product typography for readable labels, specification values, feature cards, live metrics and trust items. No external font or text effect is introduced.

## 0.1.36

- Refines only the authoritative single-product typography: brighter readable body copy, 11–12px uppercase labels, clearer Draw Engine metric labels, and larger trust-row supporting text.
- Increases Product Details specification and real-data feature-card readability while preserving their parsed source values, layout, and existing three icon mappings.
- Improves Raffle Details text hierarchy without changing its four-column structure or placement.

## 0.1.37

- Final desktop typography polish only: improves off-white contrast, normalises readable label tracking, and clarifies Buy Direct, Product Details, raffle metrics, trust-row, breadcrumb, and Raffle Details text.
- Leaves the approved grid, card geometry, native controls, feature-card positions, and all product/raffle functionality unchanged.

## 0.1.38

- Rebuilds only the <=767px presentation as a natural single-column reading flow: gallery, title/meta, Buy Direct, raffle entry, Product Details, then Raffle Details.
- Resets desktop inner grids and flexible equal-height behavior only on mobile, preserving the native gallery, forms, Draw Engine values, parsed specs, and desktop presentation.
- Adds a mobile-only final-section bottom buffer so the separately owned floating referral control does not permanently cover the last raffle content.


## 0.1.7
Desktop-only refinement: toolbar grouping/dividers, contained raffle-entry pricing, Featured Products typography, and redesigned Store Only retail-price card. Mobile rules unchanged.

## 0.1.15

- Desktop-only continuation from the 0.1.14 baseline.
- Replaces standalone desktop toolbar divider rendering with group-level borders: Raffle Points / Shopping Mode and Shopping Mode / Filters + Sort.
- Consolidates clear Inter typography and compact Store Only price/image alignment while retaining the approved mobile cascade and Store & Raffle price boxes.


## 0.1.18

- Replaces accumulated desktop test overrides with one authoritative desktop layer.
- Store Only cards now match the approved 43/57 reference layout, with the Buy Now action under the information column and one-line retail currency.
- Uses the two existing toolbar divider nodes in their semantic positions and applies crisp Inter toolbar/product typography.
- Mobile rules are intentionally preserved.


## 0.1.22
- Store Only desktop Retail Price box now shrink-wraps its content instead of stretching across the full information column.
- Retail Price label and amount are centered inside a tighter 46px minimum-height border, removing the unused blank area while keeping WooCommerce currency inline.
- Mobile rules and Store & Raffle pricing geometry are unchanged.

## 0.1.23
- Store Only desktop Retail Price border now spans the same information-column width as the Buy Now button.
- Retail Price label and amount are centered both horizontally and vertically inside the full-width 54px price box.
- Store & Raffle and mobile layouts remain unchanged.

## 0.1.24
- Store Only desktop product details no longer stretch vertically inside equal-height cards, so Retail Price and Buy Now sit closer beneath the product title.
- Store Only card height now follows the compact four-row content grid; the product image spans the same rows as the information stack so its bottom edge aligns with the Buy Now button.
- Retail Price full-width centered treatment from 0.1.23 is preserved; Store & Raffle and mobile layouts are unchanged.



## 0.1.25
- Desktop toolbar action widths rebalanced: Filters receives more space while Sort is reduced from its oversized width.
- Filters and Sort remain on the same 54px row and keep the existing toolbar alignment and functionality.
- Store cards and all mobile rules are unchanged.


## 0.1.42
- Fixes the WoodMart theme flash seen for a frame when opening a raffle product from the Store.
- assets/single-product.css is now printed render-blocking from wp_head, ahead of every theme stylesheet, instead of relying only on the wp_enqueue_scripts copy that WP Rocket could move behind an async loader. The enqueued duplicate is dropped.
- The first-paint guard covers the whole product region (summary wrap, summary, tabs wrapper, raffle details section and the product container) rather than the summary alone, and is only released once the layout has been mounted AND the layout stylesheet is confirmed applied.
- The guard is released by a CSS keyframe after 2.5s and on window load, so the product region can never stay hidden if the script or the stylesheet fails. With JavaScript disabled the guard is never applied at all.
- WP Rocket exclusions added for the layout stylesheet (async CSS, minification, Remove Unused CSS) and for the inline first-paint style, alongside the existing delay/defer JavaScript exclusions.
- Product markup, layout, mobile rules and every other presentation rule are unchanged.


## 0.1.43
- Desktop only: approximately 14px of space between the bottom of the gallery and the top of the Product Details card, up from 12px.
- Desktop only: the Product Details bottom border and the raffle card bottom border now sit on the same horizontal line. The two columns remain independent vertical stacks; the grid stretches both to equal height and the final card in each stack absorbs the remaining height. No shared grid rows, no fixed card heights, no JavaScript measurement, no negative margins.
- The final card is addressed as the stack's last child and its trailing margin is zeroed there, so the base 22px card margin cannot reintroduce the offset if stylesheet order is rewritten by an optimiser.
- Scoped to @media (min-width:901px). Mobile geometry is byte-identical to 0.1.42, verified element by element at 390px, 767px and 900px.
- The 0.1.42 first-paint/flicker fix is untouched: rafflelb-shop.php changes only the version string.


## 0.1.47
- Refined desktop geometry for completed/ready-to-draw raffle products.
- When the live raffle entry card is absent, the product gallery column now caps at 500px and the result/title column gets the remaining width.
- Mobile behavior remains unchanged and continues to use the natural full-width stack.
- No raffle, checkout, order, capacity, winner, or Draw Engine logic changed.

## 0.1.46
- Fixes closed/completed raffle single-product pages falling back to WoodMart's full-width gallery after the live raffle-entry card is intentionally removed.
- The product layout bootstrap now treats `.rl-raffle-option-card` as optional while still requiring the native gallery, WooCommerce summary and Product Details panel. Winner-selected and ready-to-draw states therefore keep the same desktop/mobile product composition as live raffles.
- Adds a scoped gallery containment fallback so an unmounted closed/completed state cannot expand the native product image/placeholder across the full product area.
- No Draw Engine, winner, order, reservation, capacity, checkout, schema or database logic is changed.


## 0.2.5
- Moves raffle-discovery sorting to the public Store where shoppers need it.
- RAFFLE ONLY sort choices now include **Closest to full**, **Newest raffles**, and entry-price ordering.
- **Closest to full** uses confirmed active raffle entries and configured capacity; it does not change raffle state or Selection logic.

- v0.2.8: Store Banners now detects and attaches to the actual visible RaffleLB Operations top-level menu regardless of its internal WordPress slug.
## 0.2.23
- Rebalanced the dual price cards on desktop: Retail Price is slightly narrower and Raffle Entry is wider.
- Kept the full Retail Price label and large retail amounts readable.
- Kept raffle amount, currency, and `/ entry` on one baseline inside the Raffle Entry border.
- Presentation-only change; no pricing, checkout, raffle/Selection, or purchase logic changed.

## 0.2.24
- Slightly increased and brightened the `/ entry` unit label in Store + Raffle price cards for clearer readability.
- Presentation-only change; no pricing, checkout, raffle/Selection, reservation, points, referral, or order logic changed.


## 0.2.49 — Centered price boxes
- Centers Retail Price and Raffle Entry labels/values inside their bordered price cards across Store & Raffle, Store Only, and Raffle Only modes on desktop and mobile.
- Keeps the compact raffle price + `/ entry` grouping unchanged; this is presentation-only.
