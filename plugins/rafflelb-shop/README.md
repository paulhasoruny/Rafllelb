# RaffleLB Shop 0.1.89 — shared product shell

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
