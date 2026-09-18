# RaffleLB Store Hub

Version 0.1.16

A separate, uninstall-safe premium Store Hub preview for RaffleLB. It does not replace the existing WooCommerce Shop or RaffleLB Homepage.

## Preview page
On activation the plugin creates a published WordPress page at:

`/store-preview/`

The plugin also creates a brand directory preview at:

`/brands-preview/`

with shortcode:

`[rafflelb_store_hub]`

## Sections
- Search
- Live Raffles
- Shop by Category
- Shop by Brand
- Shop by Budget
- Almost Filled

Category, brand and budget links continue into the existing WooCommerce Shop/catalog and reuse RaffleLB's current query/filter conventions.

## Changelog

## 0.1.16
- Rebuilt Live Raffles as a compact luxury campaign banner (~470px desktop height, true 48/52 split) instead of the 0.1.15 dashboard-card layout: a single standalone entry-price block, one horizontal "claimed • left • total • filled" info row with small icons in place of four stat cards, a thinner 9px progress bar, and two side-by-side CTAs sized to content instead of stretched full width.
- Redesigned the right-side product stage: a broader restrained lime halo, a subtle abstract two-panel gradient for depth, a thin elliptical platform ring with floor reflection under the product, and a corner vignette. Removed `mix-blend-mode:screen` (it could wash out dark/black product photos); dark-background photos now rely on stage tone-matching and the vignette instead. No PHP image processing, no cropping — `object-fit:contain` throughout.
- Consolidated three stacked layers of Live-Raffles CSS overrides (base + 0.1.13 refinement + 0.1.14 + 0.1.15) into one clean, scoped ruleset; removed every superseded/dead Live-Raffles rule. No non-raffle selector (search, categories, brands, budget, Almost Filled) was touched — verified against a full diff.
- Product titles are now hard-clamped to 2 lines with an ellipsis so unusually long names can't break the layout.
- No raffle logic, queries, data sources, URLs, entry/progress calculations, or Selection Status behavior changed.
- Added a dedicated mobile layout: stage first, badges, title, entry price, a wrapping info row, progress, then full-width stacked CTAs.

## 0.1.15
- Visual-only rebuild of the Live Raffles banner into a true 50/50 split: refined stat cards with icons, a standalone entry-price row, a thicker premium progress bar, and better-proportioned CTAs. No raffle logic, queries, data sources, URLs, entry/progress calculations, Selection Status behavior, or section order changed.
- Reduced the product-title dominance and tightened vertical rhythm so the section stays a compact ~480px on desktop instead of a full hero.
- Redesigned the right-side product stage: a restrained neon-lime halo ring, a soft rim/platform light, layered dark-green gradients for depth, and a vignette, replacing the previous bright glow and disconnected platform ellipse. Product images keep their real proportions and are never cropped.
- Fixed the carousel's "next" arrow and dot indicators, which were being mis-positioned by an unconstrained containing box and could render off the visible edge; both controls are now reliably aligned at the stage's left/right edges as intended.
- Added a dedicated mobile layout: stage first, badges, title, entry price, a clean 2x2 stat grid (all four stats visible), progress, then full-width stacked CTAs.

## 0.1.14
- Rebuilt the Live Raffles showcase into a cinematic premium spotlight while preserving the existing live-raffle query and carousel behavior.
- Added dynamic Live/availability badges, entry-price and raffle-stat cards, a premium progress treatment, stronger CTAs, a generic neon-lime product stage, and edge carousel navigation.
- Product-specific logos or text are not added to the visual stage; each slide uses the real raffle product image and live RaffleLB data.
- Added responsive tablet/mobile layouts without changing the rest of Store Hub.

## 0.1.13
- Premium UI and typography alignment pass across the Store Hub using the same Manrope/Design System rhythm as the main RaffleLB website.
- Unified section kickers, headings, subtitles and CTA buttons with the homepage visual language.
- Refined Search, Live Raffles, Category, Brand, Budget and Almost Filled surfaces with subtler gradients, borders, radii, shadows and hover states.
- No query, filtering, raffle, category, brand, budget, link or mobile functionality changed.


## 0.1.12
- Refined the visual hierarchy for Shop by Brand, Shop by Budget, and Almost Filled with compact lime kickers, smaller section headlines, muted supporting copy, and tighter spacing.
- Brand cards now use available taxonomy/logo metadata when present and retain clean text fallback when a logo is unavailable.
- Added subtle dollar-mark visual cues to budget cards without changing their filtering behavior.
- Added a distinct LIVE STATUS kicker to Almost Filled while preserving its raffle ranking, progress, actions, and layout.
### 0.1.4
- Removed the Store Hub hero so the preview begins directly with the Store search and browsing sections.
- Removed theme/page wrapper spacing and background bleed on the Store Hub page so the header, Store Hub content and footer connect with one continuous dark background.

### 0.1.3
- Fixed Store-only Perfume Picks prices so normal WooCommerce retail prices are used whenever there is no Draw Engine Buy Now override.
- Added a compact premium Store search below the Store Hub hero, reusing the existing RaffleLB Shop instant-search backend when available and sending full results into the existing Shop search.

### 0.1.2
- Fixed Perfume Picks so it samples all eligible in-stock Perfumes and child-category products and no longer excludes products that also have raffle options.
- Added a separate `/brands-preview/` brand directory with logo cards.
- Changed Store Hub `VIEW ALL BRANDS` to open the new brand directory; individual brand cards still open the existing Shop filtered to that brand.

### 0.1.1
- Reworked the hero into a compact, image-free Shop Hub header with concise Store and Raffles entry buttons.

### 0.1.0
- Initial standalone Store Hub preview plugin.


## 0.1.5
- Reworked Live Raffles into a compact premium single-raffle showcase carousel with product imagery, progress, actions and controls.
- Removed the remaining white gap above the site footer on the Store Hub page.


## 0.1.6
- Moved Live Raffles to the first content section directly below the Store search.
- No design, query, card, filter, or mobile behavior changes.


## 0.1.7
- Removed Featured Store Products from the pre-store discovery page.
- Removed Perfume Picks from the pre-store discovery page.
- Store Hub now focuses on Search, Live Raffles, Categories, Brands and Budget before the footer.


## 0.1.8
- Added a subtle neon-lime separator line between the Store Hub content and the site footer.
- No changes to Store Hub sections, links, logic or mobile behavior.


## 0.1.9
- Reduced the footer separator to the same subtle 1px lime divider strength used between Store Hub sections.
- Removed the stronger glow/bright footer-divider treatment.


## 0.1.10
- Moved the existing Almost Filled raffle section from the Homepage into Store Hub immediately below Live Raffles.
- Preserves the existing Almost Filled ranking, progress, actions, Selection Status link, desktop/mobile design, and raffle-state cache freshness.
- Homepage 0.1.37 no longer renders Almost Filled.


## 0.1.11
- Moved Almost Filled from directly below Live Raffles to the bottom of Store Hub, after Shop by Budget and immediately before the footer.
- No changes to Almost Filled design, ranking, progress, actions, cache freshness, or mobile behavior.
