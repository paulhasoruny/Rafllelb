# RaffleLB Design System

Shared RaffleLB frontend visual foundation.

## 0.1.2 — Final Safe Foundation

Scope: typography only, applied conservatively. No layout changes, no
component dimension changes, no business logic, no changes to any other
RaffleLB plugin.

0.1.0 applied global sizing (heading sizes, `li`/`p` line-height, label
sizes, bold weights on titles/buttons) too aggressively for a first
release. 0.1.1 pulled that back to font-family only, but the global `body`
rule still set `font-weight` and `line-height` — both inherited properties,
so they still reached every descendant (nav, WooCommerce components, list
elements) with no selector naming them directly. 0.1.2 closes that gap:
`body` now sets `font-family` (plus font smoothing) only. Existing
WoodMart/RaffleLB components keep their current geometry until each is
migrated intentionally, page by page, in a later release.

### What it does

- Self-hosts Manrope (variable font, weights 200–800) and serves it as the
  RaffleLB frontend font via `--rl-font`.
- Publishes the full set of shared CSS custom properties — type scale,
  weight, line-height, letter-spacing, and responsive heading sizes
  (`--rl-text-*`, `--rl-weight-*`, `--rl-line-*`, `--rl-tracking-*`,
  `--rl-size-*`) — for other RaffleLB plugins to read and opt into.
- Applies Manrope to frontend body text and normal form controls
  (`button`, `input`, `select`, `textarea`). The global `body` rule sets
  only `font-family` and font-smoothing — no `font-weight` or
  `line-height`, since both are inherited and would otherwise reach
  every descendant.
- Applies Manrope to bare headings, product titles and prices —
  typeface only, no size/weight/line-height change.
- Adds opt-in utility classes (`.rl-type-hero`, `.rl-type-h1`,
  `.rl-type-h2`, `.rl-type-h3`, `.rl-type-body`, `.rl-type-small`,
  `.rl-type-label`, plus `.rl-label` / `.rl-eyebrow`) that carry full
  size/weight/line-height/tracking from the tokens above. Nothing existing
  carries these classes automatically — Homepage, Shop, Account, Checkout,
  etc. adopt them deliberately when a component is migrated.
- `.rl-hero-title` remains available as a pre-existing, intentional
  opt-in hero treatment.
- Loads only on the frontend (`wp_enqueue_scripts`, gated by `!is_admin()`).
  wp-admin, the block editor and WooCommerce admin screens are never
  touched.

### What it deliberately does not do (as of 0.1.2)

- No `font-weight` or `line-height` on the global `body` rule — both are
  inherited properties, so setting them there would still change every
  descendant's geometry even without a selector naming it directly.
- No global `font-size` on bare `h1`/`h2`/`h3` — they keep whatever size
  the active theme/component gives them.
- No global `line-height` on `p`/`li`/`dd`/`dt` — list-heavy components
  (nav, account menus, product UI, WoodMart widgets) keep their own
  line-height.
- No global `font-size` on `label` — Checkout/Account/plugin-specific form
  layouts keep their existing label size.
- No global weight/letter-spacing change on buttons or prices — button
  and price dimensions are unaffected.
- No colors, backgrounds, layout, spacing, or component sizing.
- No `!important` anywhere.
- No targeting of icon fonts, SVG icons, or their pseudo-elements — those
  already declare their own `font-family` closer to the element, which
  wins over inheritance regardless of load order.

### Font loading

One self-hosted variable woff2 file
(`assets/fonts/manrope-variable.woff2`, ~24 KB) covers the full 200–800
weight range via a single `@font-face` rule with `font-weight: 200 800` and
`font-display: swap`. No external request to Google Fonts at runtime.

### License

Manrope is licensed under the SIL Open Font License 1.1. The unmodified
license text ships at `assets/fonts/OFL.txt`.

### Files

```
rafflelb-design-system/
├── rafflelb-design-system.php
├── assets/
│   ├── css/
│   │   └── typography.css
│   └── fonts/
│       ├── manrope-variable.woff2
│       └── OFL.txt
└── README.md
```
