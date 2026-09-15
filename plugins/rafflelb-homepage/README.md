# RaffleLB Homepage 0.1.10

## 0.1.10 — Latest Winner Media Centering

- Makes every one-to-four-result Latest Winners media stage a flex-centered, pure-black container.
- Keeps prize artwork contained, uncropped, and centered within the media half using `object-position: center center`.
- Preserves the existing wide one-result layout, public result data, masking, links, and count-aware grids.

## 0.1.9 — Public Winner Count and Four-Result Layout

- Uses one newest-first `public_results(0)` snapshot for both Homepage cards and public statistics, preventing raw/internal result rows from inflating Completed Selections or Winners Recorded.
- Shows at most the latest four publicly eligible results and links card media, titles, and shared result actions to each raffle's permanent Selection Result page.
- Applies explicit layouts for one wide card, two equal cards, three equal cards, and four cards on sufficiently wide desktops with a 2×2 fallback.
- Keeps tablet layouts at two columns where appropriate and mobile layouts at one unobstructed card per row.
- Derives Recorded Results as 100% only when at least one eligible public result exists; the empty state reports 0%.

## 0.1.8 — Winner Media and Single-Result Refinement

- Removes the Selection Recorded overlay from prize imagery and renders the shared status row inside each card body.
- Enforces pure-black, centered, uncropped product stages with square-product safe margins.
- Gives a single desktop winner a wider compact horizontal layout while preserving the normal stacked mobile card.
- Keeps the shared Selection Engine recorded-result component, public masking, URLs, ordering, and read-only data flow unchanged.

## 0.1.7 — Selection Engine Result Records

- Recent Winners now consumes the Selection Engine's read-only public result adapter and shared recorded-result visual.
- Each completed card retains the prize, public masked winner, winning entry, and recorded time, while its result CTA opens `/selection/{product-slug}/`.
- Replaced public Draw-era wording in this section with Selection Recorded, Completed Selections, and Recorded Results terminology.
- Added only the compact CSS chamber treatment; no per-card animation or polling is started, preserving Homepage performance.
- Raffle records, entries, winner generation, ordering, masking, and all database behavior remain unchanged.

## 0.1.6 — Compact Mobile Latest Winners

Mobile-only presentation refinement for the Homepage Latest Winners cards.

- At 767px and below, reduces the product stage to 184px (176px at 370px and below), uses a pure-black background, and retains centered `object-fit: contain` product imagery.
- Reduces the no-image `R` fallback to 42px and removes the decorative media glow/overlay on mobile.
- Tightens card/body/result/footer spacing while retaining the winner badge, date, complete prize title, masked winner, winning entry, verified status, and View action.
- Keeps Winner and Winning Entry side-by-side at 360px, 390px, and 430px widths.
- Desktop Latest Winners, queries, result count, privacy masking, links, Draw Engine delegation, and every other Homepage section remain unchanged.

## 0.1.5 — Featured Products Alignment Fix

Tiny, scoped change: centers two text elements inside the Featured
Products cards (`.rlfp318-*`) — nothing else.

- `.rlfp318-price` (the main retail price, e.g. `$49.99`): added
  `justify-content:center` (it's a flex row, so `text-align` alone
  wouldn't move it) plus `text-align:center`.
- `.rlfp318-availability` (the bottom raffle text, e.g. `10 of 10 entries
  available`): added `text-align:center`.

Both rules are base (non-media-query) declarations, so the centering
applies identically on desktop and mobile without touching the
`@media(max-width:480px)` block, which only adjusts font-size/margins.

Untouched: card/image/card-height dimensions, the Buy/Enter buttons, the
`OR` divider, the raffle price/button, product queries, pricing logic,
raffle logic, and all other spacing. Currency formatting
(`get_woocommerce_currency_symbol()` + `number_format()`) is unchanged.

## 0.1.4 — Typography Consistency Pass

Follows 0.1.3's typography upgrade with a consistency and readability pass:
unify Manrope usage, tighten the hero and section-heading hierarchy, and
raise every remaining sub-10px piece of customer-facing text on the live
homepage to a readable floor. No layout, structural, or business-logic
changes — every edit is inside the plugin's own inline `<style>` blocks.

### What changed

- **Hero** (`.rl-home-reference h1`, scoped, additive, no `!important`):
  line-height tightened to `1.02` (within the 0.98–1.04 target) and
  letter-spacing set to `-0.035em`, weight stays 800. The clamp size range
  (42–72px desktop, 40–46px mobile) is unchanged — visual scale preserved,
  nothing shrunk to fix wrapping.
- **Section headings** (Featured Products, Shop Categories): unchanged
  from 0.1.3's 26–38px / weight 800 / `var(--rl-tracking-tight)` hierarchy,
  now confirmed consistent with Recent Winners (32–36px / 800 / `-.03em`),
  which only needed its title's weight and letter-spacing made explicit
  on the winning rule for robustness.
- **Live Raffles panel** (`.rlp275-*`, the actual rendered raffle-card
  skin — the older `.rlp270-title/-price/-stats/-category` rules are dead
  CSS not matched by current markup and were left untouched): title 17px
  → 14px desktop / 15px → 13px mobile (weight 800 → 700); entry price
  24px → 15px desktop / 22px → 14px mobile (weight 900 → 800); the
  `/ Entry`-style unit label 9px → 11px; claimed/capacity and entries-left
  metadata 9px → 11px on desktop and (previously 7–8px on the two mobile
  breakpoints) → 10–11px. Panel padding, card size, grid columns and the
  number of visible raffles are all unchanged.
- **Featured Product cards**: already at 18px/700 title, 24px/800 price,
  13px/700 CTA and 11px category label from 0.1.3 — confirmed in-range,
  no further change needed.
- **Category cards**: title weight unified to 700 (was 750) on the
  desktop-winning rule; `SHOP NOW` / product-count metadata raised from
  8.5–10px to 11px across the 767px/640px/480px breakpoints; the
  decorative `PREMIUM BRANDS • BIGGER POSSIBILITIES` sign-off line moved
  off `Arial` onto `var(--rl-font)` and up from 7–9px to 10px.
- **Hero product carousel labels** (small name tags under the 3 hero
  product images): 9px → 11px desktop, 8px → 10px mobile.
- **Recent Winners cards**: winner badge, date, `claimed`/`entries`
  stat labels and the footer verified-result row were 8px on the base
  (effectively mobile) rules — raised to 10px; the 767px/480px trust-strip
  labels (8–8.5px) and the `VIEW ALL WINNERS` link (8–9px) raised to 10px.
  Desktop already had its own `@media(min-width:768px)` override bumping
  these to 10–12px from a prior release, so desktop was already compliant
  and is unchanged.

### What was intentionally left alone

- **`.rlp270-title`, `.rlp270-category`, `.rlp270-stats`,
  `.rlp270-progress*`, `.rlp270-button`, `.rlp270-body h3`**: dead CSS —
  no element in the current Live Raffles markup carries these classes
  (the markup uses `.rlp275-*` instead). Left as-is; touching unused
  rules would add risk for zero visible effect.
- **Community Reviews / "What Should We Raffle Next?" section**
  (`community_sections_shortcode()`, including its `Inter`/`Arial`/
  `Georgia` declarations and several sub-10px labels): the function's own
  code comment confirms it is "no longer injected automatically on the
  WordPress front page." Not live on the homepage, so left untouched to
  keep this release scoped to what's actually rendered.
- **"How It Works", "One platform. Two ways to shop.", and
  "REFER. EARN. REPEAT."**: none of this copy exists anywhere in the
  RaffleLB Homepage plugin. "How It Works" is only referenced as a nav
  link (`#how-it-works`) inside the Premium Mobile Menu plugin, and
  "REFER. EARN. REPEAT." doesn't match any string in RaffleLB Homepage —
  the Referral & Points plugin's own "Refer & Earn" widget uses different
  wording. Per the architecture rule against reaching into markup this
  plugin doesn't own, and since this task's own "STRICT SAFETY" rule bars
  broad `h1`/`h2`/`h3`/`p` selectors that would be needed to guess at
  unknown markup, these three sections were not touched here. If they're
  meant to be Homepage-owned, they most likely live as raw Elementor page
  content (like the hero) — flag with class hooks similar to
  `.rl-home-reference` so a future release can target them safely, or
  confirm which plugin/page actually renders them.
- **Header/navigation**: RaffleLB Homepage contains no header or nav
  markup/CSS at all, so there was nothing to change here.

### Font-family sweep

`var(--rl-font)` now covers every live homepage selector: hero product
labels, Featured Products, Shop Categories, Live Raffles and Recent
Winners (including the mobile-only `@media(min-width:768px)` and
mobile-first base rules). The only remaining `Inter`/`Arial`/`Georgia`
declarations are inside the dead Community Reviews section noted above.

Upload the ZIP and replace the existing RaffleLB Homepage plugin in its
current folder, then clear site/page caches. Keep Core, Account, Shop,
Draw Engine and RaffleLB Design System (0.1.2+) active.

Rollback: replace Homepage with the previous 0.1.3 ZIP.
