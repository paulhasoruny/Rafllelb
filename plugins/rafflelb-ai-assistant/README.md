# RaffleLB AI Assistant 0.1.7

Read-only customer-facing AI chat assistant for RaffleLB.

## Configuration

Open the top-level **RaffleLB AI Assistant** menu, configure an OpenAI API key, review the starter knowledge, and enable the assistant. The database API key can be overridden in `wp-config.php`:

```php
define('RAFFLELB_AI_OPENAI_API_KEY', 'your-key');
define('RAFFLELB_AI_OPENAI_MODEL', 'gpt-5.6-luna'); // Optional.
```

The saved key is never rendered back into an input. A blank API-key submission preserves the existing saved value.

## 0.1.7 live catalogue category discovery

- Keeps WooCommerce's existing direct product data-store search first, then fills remaining result capacity from safely matched `product_cat` categories.
- Matches customer catalogue wording against category names and slugs with bounded normalization for common possessives and singular/plural forms.
- Returns at most eight deduplicated, published, WooCommerce-visible non-variation products, with direct matches first and category-derived matches second.
- Adds customer-safe matched category names to the tool result without exposing term IDs or category metadata.
- Clarifies that product-type and broad catalogue questions require live search, and that zero matches mean “not found in the current catalogue,” not a permanent claim that RaffleLB never sells the item.
- Remains read-only and does not alter the approved 0.1.6 frontend, REST route, provider architecture, privacy controls, raffle tools, or request limits.

## 0.1.6 usability and configuration update

- Removed the visual-only attachment control and the visible AI disclaimer. The composer now contains only the message field and the existing circular lime send button.
- Added plain-text admin fields for the welcome heading and description, plus exactly three configurable opening questions with separate visible labels and submitted prompts. A row with both values blank is omitted without leaving a spacer.
- Localizes sanitized quick-question data through the existing `RaffleLBAssistant` configuration; the frontend no longer hard-codes prompt text.
- Reconciles the legacy `welcome_message` value into the new description when appropriate, then removes the obsolete setting.
- Extends the once-per-browser-session launcher tooltip to about six seconds and permits its temporary, viewport-safe display on mobile while retaining hover and keyboard-focus discovery on desktop.
- Preserves the approved 0.1.5 panel, launcher, messages, typography, colors, quick-action styling, and backend AI behavior.

## 0.1.5 theme-isolation hardening

- Hardened the customer widget against WoodMart and browser-default control styles with an ID-scoped CSS boundary, an inherited-style reset, explicit control appearances, and high-priority definitions for the close button, quick actions, composer, textarea, attachment affordance, send button, launcher, tooltip, images, and icons.
- Preserved the approved 396 × 720px premium black/lime layout while preventing white button/input backgrounds, square send controls, injected button pseudo-elements, uppercase theme text, theme shadows, and form padding from leaking into the assistant.
- Added an explicit namespaced textarea class so its transparent dark composer treatment remains stable on desktop and at 320px mobile widths.
- No provider, REST, settings, knowledge-base, tools, privacy, rate-limit, or request-contract behavior changed.

## 0.1.4 visual fidelity update

- Rebuilt the launcher and open assistant shell against the approved RaffleLB reference: 70px squircle launcher, white callout tooltip, 396 × 720px desktop panel, official local ticket/R artwork, lime edge glow, dedicated welcome card, three outlined quick actions, timestamped bubbles, and a 64px pill composer.
- The attachment control is visual-only and clearly announces `Attachments coming soon`; no upload behavior was added. The `My entries` shortcut sends a safe general support question through the existing chat flow and does not retrieve entry numbers or personal data.
- Mobile uses the same shell at `calc(100vw - 20px)` and up to `88dvh`, with 10px viewport gutters, safe-area-aware placement, independently scrolling content, and 320px overflow protection.
- The provider, Responses API request shape, REST route, settings, knowledge base, read-only tools, privacy rules, rate limits, and history contract are unchanged.

## 0.1.3 visual redesign

- The customer-facing assistant now uses a premium RaffleLB-styled black and neon-lime interface with a compact header, refined message cards, safe suggested-question shortcuts, a restrained typing state, a compact auto-growing composer, and a branded launcher.
- Desktop uses a responsive 412 × 624px floating panel. Mobile uses a safe-area-aware bottom sheet that remains usable from 320px wide, with independently scrolling messages and a composer that stays visible.
- All shortcuts feed predefined questions through the existing chat submission flow. The REST endpoint, request format, provider, tools, settings, security, rate limits, and WooCommerce/RaffleLB backend contracts are unchanged.

## 0.1.2 correction

- The settings page now owns a unique top-level **RaffleLB AI Assistant** admin menu at `admin.php?page=rafflelb-ai-assistant` and no longer inspects or attaches to the generic `rafflelb` parent.

## 0.1.1 corrections

- Product search now uses WooCommerce's product data-store `search_products()` method, preserves its result order, excludes variations/non-public products, and returns at most eight results.
- Active raffles are inspected in bounded 40-product pages, up to an 800-product safety cap, so older live raffles are not hidden behind newer closed raffles.
- Newly submitted API keys are stored as opaque credentials after trimming surrounding whitespace; their contents are not HTML-sanitized or reformatted.

## Architecture

- `rafflelb-ai-assistant.php`: bootstrap only.
- `includes/class-settings.php`: plugin-owned settings, knowledge fields, and safe connection test.
- `includes/class-knowledge-base.php`: server-authored instructions, untrusted knowledge data, and public page lookup.
- `includes/class-openai-provider.php`: isolated server-side OpenAI Responses API client.
- `includes/class-tools.php`: four explicitly allowlisted, read-only WooCommerce/RaffleLB tools.
- `includes/class-rest-controller.php`: public read-only chat endpoint, validation, and abuse controls.
- `includes/class-frontend.php` and `assets/`: accessible vanilla JavaScript/CSS widget.

## Privacy and boundaries

The plugin does not persist chat transcripts and does not expose account, order, payment, entry-owner, or other customer PII. It does not add to cart, reserve or allocate entries, mutate draws, select winners, or call private Draw Engine methods. Live raffle progress is an aggregate count of active rows for a public product through the RaffleLB Core table contract.
