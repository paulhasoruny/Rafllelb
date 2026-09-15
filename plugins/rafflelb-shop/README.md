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
