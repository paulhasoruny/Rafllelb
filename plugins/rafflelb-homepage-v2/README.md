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

## Remaining differences from the approved preview / blueprint (as of 0.1.4)

- **Points art**: the preview shows a large stacked-coin illustration; Home
  V2 uses the real `rafflelb-referral-points` ticket/"R" mark instead (no
  coin asset exists anywhere in the codebase to reuse, and inventing one
  would violate "use existing RaffleLB logo/points assets only").
- **Verified Results stat count**: the preview shows 4 stat tiles (500+
  results / 100% / 10,000+ customers / Licensed & Compliant); Home V2 shows
  3 (1 real published-results count + 2 static, non-numeric trust
  statements) — there's no safely-reusable, read-only source for a
  "10,000+ happy customers" count on this store, and "Licensed & Compliant"
  is a legal claim this plugin has no basis to assert.
- **Hero product mix**: the bundled `hero-reference-scene.webp` art
  (headphones/perfume/iPhone/watch/AirPods/cosmetics) is close to but not
  pixel-identical to the preview's exact product mix (which also includes a
  PS5 and a laptop) — it's the closest existing real asset, not a new
  composite.
Everything else — card counts (6 Products / 6 Categories / 4 Selections,
all uniform, no lead/bento tiles), horizontal Selection cards at every
width, copy, section order, the six named categories, Selection wording,
single-banner Points, borderless How-Selections-Work flow, overall page
density, and empty-state honesty — matches the reference screenshot as
closely as the real data allows.

## Changelog

### 0.1.6 — typography/button fidelity pass
A closer, more rigorous re-comparison against the reference screenshot
(re-measuring rendered output rather than eyeballing it) turned up four
remaining fidelity gaps, all CSS-only — no markup, no data-layer change
(`class-rlhv2-data.php` — zero diff, verified with `git diff`):

- **Section headings weren't uppercase.** The reference shows "FEATURED
  PRODUCTS", "SHOP BY CATEGORY", "HOW SELECTIONS WORK", etc. in caps;
  `.rl-hv2-section-head h2` had no `text-transform`, so it rendered in the
  PHP source's Title Case. Added `text-transform: uppercase` (the
  subtitle paragraph directly below explicitly keeps `text-transform:
  none`, since the reference's one-line descriptions stay sentence case).
- **Hero/Points eyebrows weren't uppercase.** Same issue for "Authentic
  products. Real rewards." and "RaffleLB Points" — added
  `text-transform: uppercase` to `.rl-hv2-eyebrow`.
- **"View All X →" / "Learn More →" were bare underlined text**, not the
  bordered pill buttons the reference clearly shows next to every section
  heading. `.rl-hv2-view-all` now has a visible border, background, and
  proper padding, matching the same button language as the rest of the
  page.
- **The secondary button border was nearly invisible** (`rgba(255,255,
  255,.09)`, the same token used for hairline dividers) against the dark
  background — the reference's "Explore Selections" button has a clearly
  visible outline. Strengthened to `rgba(255,255,255,.28)`.

### 0.1.5 — match reference density (compact the whole page)
0.1.4 matched the blueprint's *grid shape* (6/6/4 uniform cards) but ran
far taller than the reference screenshot — roughly 5140px tall at 1920px
width, against a reference whose proportions imply roughly 3700-4000px.
0.1.5 is a pure density pass: no section was added, removed, or
restructured; every change is a spacing/sizing reduction. As with prior
versions, **`class-rlhv2-data.php` was not touched** (verified with
`git diff` — zero changes).

Two real bugs found by comparing against the reference and re-measuring
the rendered page (not just eyeballing it):

- **Featured Selections cards were vertical at desktop** (image on top,
  full width) via a `≥960px` override — the reference shows compact
  *horizontal* cards (image left, content right) throughout. Removed that
  override; selection cards now stay horizontal at every width, which
  alone cut a large chunk of that section's height.
- **The hero image had no upper size limit**, so at wide viewports it
  scaled up to fill an increasingly wide grid column, inflating hero
  height well past the reference's proportions. Added `max-width: 520px`
  on `.rl-hv2-hero-media` so the hero art stays a fixed, compact size
  regardless of viewport width.

Everything else is a straightforward reduction, applied consistently:

- New spacing token `--rl-hv2-space` dropped from 64px to 44px (every
  section's vertical padding); section-head bottom margin 32px → 22px.
- Hero: padding 56/40px → 36/28px; H1 max size 78px → 54px; sub-copy,
  CTA row, and benefits row gaps all tightened.
- Trust strip padding 26px → 18px.
- Featured Products: image band 190px → 155px, card body padding 16px →
  13px, grid gap 16px → 12px.
- Shop by Category: aspect-ratio 0.95 (tall) → 1.05 (flatter/shorter),
  content padding 18px → 14px, grid gap 16px → 12px.
- RaffleLB Points: banner padding 72px → 40px, headline max size 58px →
  42px, step-icon circles 54px → 44px, internal gaps tightened throughout.
- How Selections Work: step gap 36px → 22px, numbered circle 58px → 50px.
- Verified Results / Reviews: stat-row and card margins/padding tightened;
  empty-state padding reduced so honest empty states stay compact, not a
  large empty block.

Net result, measured directly from the rendered output (not estimated):
1920px-wide render dropped from 5141px tall to 3735px tall (-27%), with
hero + trust strip + the start of Featured Products now visible together
in the first ~740px, without a structural rewrite — same sections, same
order, same card counts, just correctly sized.

### 0.1.4 — revert to a uniform-grid layout blueprint
0.1.3's bento/asymmetric composition (lead tiles in Products/Selections,
2-large+4-small Categories) was replaced on client direction with a single
consistent uniform-grid system, following an exact layout blueprint. As
with 0.1.3, **`class-rlhv2-data.php` was not touched** (verified with
`git diff` — zero changes); only `class-rlhv2-render.php` and
`assets/css/rafflelb-homepage-v2.css` changed.

- **Featured Products**: back to 6 equal cards (`featured_products(6)`,
  was `(7)`), single row of 6 at ≥1180px, 3×2 at tablet, 2×3 on mobile. No
  lead tile.
- **Shop by Category**: back to 6 equal tiles, single row of 6 at
  ≥1180px, 3×2 at tablet. No large/small distinction.
- **Featured Selections**: back to 4 equal compact cards
  (`featured_selections(4)`, call unchanged), single row of 4 at ≥960px.
  Each card now also shows an explicit "View Selection" link (the
  blueprint lists it as a required field on every card, not just a lead
  tile). No lead tile.
- **RaffleLB Points** and **How Selections Work**: unchanged from 0.1.3 —
  both already matched this blueprint (single banner with no card grid
  inside; borderless connected-line flow).
- Introduced two global tokens, `--rl-hv2-radius` (14px) and
  `--rl-hv2-space` (64px), and applied them to every card/section so the
  whole page now shares exactly one card radius and one spacing scale, per
  the blueprint's global rules. Section heading scale pulled back slightly
  (was up to 44px, now up to 38px) to read as "balanced" rather than
  oversized.

Verified again with the same local Playwright-against-real-templates
harness, at all six required widths, confirming a single row of 6/6/4 at
large desktop, 3×2/3×2 at tablet, and 2-column grids on mobile, with no
overflow.

### 0.1.3 — presentation-layer redesign (premium ecommerce art direction)
0.1.2 matched the preview's *content* closely but still read as a repeated
bordered-card grid — too many identical panels, admin-UI density, not
enough depth or hierarchy. 0.1.3 rewrites the presentation layer only
(`class-rlhv2-render.php` markup + `assets/css/rafflelb-homepage-v2.css`);
**`class-rlhv2-data.php` was not touched at all** (verified with
`git diff` — zero changes), so every data source, pricing rule, and
integration guard from 0.1.2 is identical.

Per section:
- **Featured Products** — was 6 identical cards; now a bento layout: one
  tall lead product (spans the full row height) beside a 3-row×2-col rail
  of supporting products. `RLHV2_Data::featured_products()` is now called
  with `limit=7` (1 lead + 6 rail) instead of 6 — the only call-site change
  anywhere, same method, same fields.
- **Shop by Category** — was 6 equal tiles; now 2 large campaign-banner
  tiles (first two categories returned) + 4 smaller tiles below, image-led
  with bottom gradient overlay, matching a "campaign banner" feel rather
  than a directory listing.
- **RaffleLB Points** — rebuilt as a hero-scale promo: large gradient
  background with layered radial glow, big headline, step icons as glowing
  circles (no bordered boxes), large glowing ticket-mark art on the right.
- **Featured Selections** — was a uniform row; now one large lead tile
  (bigger image, bigger type, prominent CTA) plus 3 compact supporting
  tiles below it.
- **How Selections Work** — the 3 steps no longer sit in bordered boxes;
  they're numbered glowing circles connected by a soft gradient line
  (hidden below 700px, where the flow reads top-to-bottom instead).
- **Verified Results empty state** — replaced the bordered rectangle with
  a centered icon in a soft glow ring + headline + subtext.
- **Customer Reviews empty state** — replaced the bordered rectangle with
  a two-column glassy panel (headline + CTA button) instead of a large
  empty box.
- Typography scale increased throughout (section headings now
  clamp(28px–44px), was 21–27px; body copy 14–16px, was 12–13px); every
  heading now emphasizes exactly one lime keyword, never a whole phrase.
- Section backgrounds carry per-section radial-glow treatments instead of
  a flat alternating tint, and hairline borders (`rgba(255,255,255,.09)`)
  replace the previous solid-green card borders almost everywhere, so the
  page reads as layered surfaces rather than a stack of bordered rectangles.

Verified again with the same local Playwright-against-real-templates
harness as 0.1.2, at all six required widths — see "Remaining differences
from the preview" below for what I found and didn't fix.

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
