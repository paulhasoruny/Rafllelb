# RaffleLB Homepage V2

Standalone, isolated plugin that renders an experimental alternate homepage
("Home V2") through a single shortcode:

```
[rafflelb_homepage_v2]
```

Create a WordPress page (e.g. titled "Home V2") and place the shortcode in
it. This plugin does not touch the live homepage, does not change any menu,
theme, or other RaffleLB plugin, and does not alter any database schema,
meta key, or checkout/raffle logic.

## What it does

Renders, in order: Hero, Trust strip, Featured Products (plain ecommerce
cards), Shop by Category, RaffleLB Points, Featured Selections (entry price
+ progress), How Selections Work, Verified Results, Customer Reviews.

All dynamic data is read-only:

- **Hero images** — WooCommerce products tagged `homepage-hero`, using the
  same `RaffleLB\Core\Contracts::META_HERO_IMAGE` meta key the live site
  already curates. No fake/hardcoded product images.
- **Featured Products** — real WooCommerce products (tag `homepage-featured`
  if present, otherwise the most recent purchasable products). Cards show
  image, name, real retail price, and a Buy Now link only. A product is
  marked with a subtle "Selection available" link only when
  `RaffleLB_Selection_Adapter::is_public_raffle_product()` confirms it —
  no progress bar, entry price, or entry count on these cards, by design.
  Retail price is resolved via `RLHV2_Data::retail_price()`: for a product
  that also has an active Selection, `WC_Product::get_price()` actually
  holds the *raffle entry price* (Draw Engine uses it as the cart line-item
  price for an entry) — the real direct-purchase price lives in the
  `_rafflelb_buy_now_price` meta, read through Draw Engine's public
  `homepage_buy_now_price()` bridge. For a plain product with no Selection,
  `get_price()` is untouched and remains correct.
- **Shop by Category** — real `product_cat` taxonomy terms, restricted to
  top-level categories (`parent => 0`, `orderby => menu_order`), matching
  how the live homepage's own category grid sources its categories. This
  avoids surfacing leaf subcategories (e.g. "Men's Perfumes") that usually
  have no thumbnail of their own configured.
- **RaffleLB Points** — reuses the existing `[rafflelb_points_header]`
  shortcode (rafflelb-referral-points) for the balance pill, and links to
  the real `refer-and-earn` My Account endpoint.
- **Featured Selections** — `RaffleLB_Selection_Adapter::raffle_cards()`
  (rafflelb-selection-engine), the same public read API the live winners/
  results pages use. Entry price, percent filled, and claimed/total come
  straight from that API.
- **Verified Results** — `RaffleLB_Selection_Adapter::public_results()` and
  `::public_result_count()`. If there are zero completed selections, the
  real empty state is shown — no invented numbers.
- **Customer Reviews** — the real `rafflelb_review` post type (registered by
  rafflelb-draw-engine, the same data source `[rafflelb_community_sections]`
  renders). If there are no published reviews yet, the honest empty state
  links to `home_url('/#community-reviews')` — the existing review
  submission form already embedded on the live homepage — rather than
  rebuilding that form inside Home V2.

Every integration point is wrapped in `class_exists()` / `function_exists()`
/ `post_type_exists()` guards, so the page degrades gracefully (sections
simply don't render, or show an honest empty state) if any of those plugins
are inactive.

## Technical notes

- CSS/JS is enqueued only when the shortcode is actually present on the
  requested page (`has_shortcode()` check on `wp_enqueue_scripts`).
- All markup/classes/functions are namespaced `rl-hv2-` / `RLHV2_` /
  `rafflelb-homepage-v2` to avoid any collision with the live homepage
  plugin's `rlfp318` / `rlsc316` / `rlp270` / `rl-almost-filled` classes.
- All dynamic output is escaped (`esc_html`, `esc_attr`, `esc_url`).
- No new database tables, no new meta keys, no writes of any kind.

## Files

```
rafflelb-homepage-v2.php              Bootstrap, shortcode + conditional enqueue
includes/class-rlhv2-data.php         Read-only data access (WooCommerce, Selection Engine, Referral Points, reviews CPT)
includes/class-rlhv2-render.php       Markup for each section
assets/css/rafflelb-homepage-v2.css   Isolated stylesheet (dark background, white text, #baff00 lime accent)
```

## Changelog

### 0.1.2 — visual recreation of the approved Home V2 preview
This pass rebuilt composition, spacing, and density to match a
client-approved preview image directly (not a fresh layout). Structural
changes, isolated to this plugin only:

- **Hero** now uses the real `rafflelb-homepage/assets/hero-reference-scene.webp`
  artwork — a read-only reference to that plugin's already-public static
  asset (`RLHV2_Data::hero_scene_url()`, guarded by `file_exists()`), not a
  new composite. It's the same rocky-pedestal / lime-halo product
  photography the approved preview is built around. Falls back to the
  0.1.1 product-collage layout if that file/plugin is unavailable. Added
  the mini benefit row (Premium Brands / RaffleLB Points / Featured
  Selections / Trusted by Thousands) and an italic decorative tagline
  overlay ("Premium Products Bigger Possibilities").
- **Shop by Category** now prefers the six named flagship categories
  (Perfumes, Electronics, Cosmetics, Experiences, Vouchers & Gift Cards,
  Home Appliances) in that order when the store has them, still filling
  remaining slots from real top-level categories otherwise — never
  fabricated categories.
- **RaffleLB Points** rebuilt as a 3-column banner (copy / Shop→Refer→Earn→
  Redeem step flow / art) using the real `rafflelb-referral-points`
  ticket/"R" mark (`RLHV2_Data::points_icon_url()`, same read-only asset
  reference pattern as the hero) for the art column — no invented coin
  illustration.
- **Featured Products** fixed: image area is now a fixed-height box (was
  `aspect-ratio`, which let unusually tall/narrow product photos blow out
  the card height) and the "Selection available" line now always reserves
  its row height, so Buy Now buttons align across every card regardless of
  title length or Selection status.
- **Verified Results** now pairs the one real number (published results
  count) with two static, non-numeric trust statements — deliberately
  omits invented counts like "10,000+ happy customers" that have no
  safely-reusable, read-only data source on this store.
- Added a 768px tablet tier (3-column trust strip/products/stats, single-
  row How Selections Work) so the tablet breakpoint reads as designed
  rather than a stretched phone layout; desktop is capped and centered
  above ~1700px so very wide screens don't stretch awkwardly.
- Card/section CSS rewritten throughout for tighter vertical rhythm,
  matching the preview's denser composition (previous pass was airier).

### 0.1.1
- Fixed Featured Products showing the raffle entry price instead of the
  real retail price (see `RLHV2_Data::retail_price()` above).
- Root wrapper now breaks out of the theme's centered content column
  (`100vw` + negative-margin technique, the same pattern the live
  homepage's own sections use) so the dark background is full width;
  section content still constrains to an internal `max-width: 1400px`.
  Shortcode output is trimmed to reduce stray whitespace from `wpautop`.
- Hero now shows real product imagery: `homepage-hero`-tagged products
  first, falling back to the Featured Products images (never fake/
  hardcoded images) in a staggered collage layout.
- Trust strip, Points, and How Selections Work sections now use inline
  SVG line icons (no external icon library), matching the live site's
  icon style.
- Shop by Category restricted to top-level categories (see above) so real
  category artwork is used instead of the initial-letter fallback.
- Points section rebuilt with icon cards for Shop/Refer/Earn/Redeem and
  explicit `!important` colors to fix low-contrast heading text.
- Featured Selections cards tightened, now read "claimed · remaining"
  instead of "of total entries".
- Verified Results and Customer Reviews empty states redesigned with an
  icon + intentional card styling; reviews empty state adds a "Share Your
  Experience" link into the live homepage's existing review form.
- All color/background/font rules now use `!important` throughout,
  matching the defensive pattern the live homepage's own plugin CSS uses,
  to stop theme/page-builder styles from overriding heading/text colors.
