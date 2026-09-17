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
  image, name, price, and a Buy Now link only. A product is marked with a
  subtle "Selection available" link only when
  `RaffleLB_Selection_Adapter::is_public_raffle_product()` confirms it —
  no progress bar, entry price, or entry count on these cards, by design.
- **Shop by Category** — the real `product_cat` taxonomy.
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
  renders). Empty state shown if there are no published reviews yet.

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
