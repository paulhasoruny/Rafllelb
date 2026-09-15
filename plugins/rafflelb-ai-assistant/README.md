## 0.1.23 signed-in Selection Engine answer reliability

- Fixes a login-state inconsistency where a general question such as `do u have a draw engine?` could succeed while signed out but fall through to the generic provider error while signed in.
- Adds a deterministic public Selection Engine answer before authenticated personal-tool/provider routing, so the same public question returns the same answer regardless of login state.
- Uses the public terminology **Selection Engine** and links customers to `/selection-engine/`; conversational references to a "draw engine" are gently translated to the public RaffleLB name.
- Adds the dynamic Selection Engine route to the assistant's public page links and system knowledge because it is not a normal WordPress page that live-page discovery can automatically read.
- No changes to personal-data permissions, account tools, product/raffle data access, rate limits, frontend design, or mutation boundaries.

## 0.1.22 deterministic personal-order routing fix

- Fixes an order-number parser bug where the plural word `orders` could be misread as a specific order number `s`.
- Broadens signed-in personal order recognition so phrases such as `what orders i am registered to ?`, `do I have orders?`, and other clearly first-person order questions are answered directly from that customer's WooCommerce orders.
- Specific order lookup now requires a real identifier such as `order #1234`, `order 1234`, or `order number 1234`.
- Personal order questions continue to bypass OpenAI and remain scoped to the authenticated WordPress user.
- No changes to public catalogue behavior, UI, pricing, raffle logic, points, or privacy boundaries.

# RaffleLB AI Assistant

## 0.1.21 conversational personal-order follow-up fix

- Fixes signed-in order follow-ups with natural spacing/punctuation such as `and orders ?`, `orders?`, and `what about my orders?`.
- These follow-ups continue to inherit the established personal-account context and are answered directly from the logged-in customer's own WooCommerce orders.
- No changes to privacy boundaries, public catalogue behavior, pricing, UI, or other personal tools.

# RaffleLB AI Assistant 0.1.18

Read-only customer-facing AI chat assistant for RaffleLB.



## 0.1.18 personal-account response reliability

- Adds a safe deterministic fallback for signed-in personal tools so customer questions about their own raffles, recent orders, or Raffle Points do not collapse to the generic assistant error if the AI provider fails after a successful read-only lookup.
- The fallback is generated only from the same customer-scoped tool result already returned by the server; it never broadens access or exposes phone, email, address, payment details, another customer, or backend identifiers.
- My Raffles now remains useful even if winner-result metadata is temporarily unavailable: confirmed entries can still be returned, while winner details are omitted until available.
- Strengthens the system instruction to avoid repeatedly retrying a personal tool after it has already returned a usable result or a customer-safe availability error.
- Public catalogue, pricing, raffle logic, live knowledge, frontend design, rate limits, and OpenAI configuration are unchanged.

## 0.1.17 administrator QA rate-limit isolation

- WordPress administrators with `manage_options` use separate rate-limit buckets from visitors/customers, preventing admin QA traffic from exhausting public limits.
- Administrator QA allowance is at least 100 requests per IP and 100 requests per browser session within the configured rate window (300 seconds by default).
- Visitor/customer configured limits remain unchanged.
- The customer widget now displays the safe server-provided error message for rate limits, daily ceilings, invalid requests, and temporary availability failures instead of replacing every error with the same generic sentence.
- No changes to personal-data permissions, tools, catalogue/raffle logic, knowledge, pricing, or the approved visual design.

## 0.1.16 secure signed-in personal assistance

- Adds optional read-only personal help for the currently authenticated WordPress/WooCommerce customer.
- The browser now sends a WordPress REST nonce for signed-in sessions; without a valid authenticated session, personal tools are not exposed to the model.
- Adds three customer-scoped tools: **My Raffles** (own entries/ticket numbers and own win status), **My Orders** (own recent/specific order status only), and **My Raffle Points** (own balance and current redemption value/rate).
- Every personal lookup is server-scoped to `get_current_user_id()`; tools accept no user ID/email/phone and cannot query another customer.
- Personal tools never return address, phone, email, payment credentials/details, or another winner/customer identity. Order lookup reports WooCommerce status only and does not pretend to provide live courier/GPS tracking.
- Adds an administrator toggle under **Signed-in personal assistance**. It is enabled by default on upgrade and can be disabled independently of the public assistant.
- All personal functionality remains read-only: no cart/order/account/points/raffle/winner mutation was added.
- Public catalogue, raffle, trust, policy, live-page knowledge, and the approved frontend remain unchanged.


## 0.1.15 live official-page knowledge + finalized customer policies

- Added live knowledge from selected official WordPress pages. Published edits are read on the next assistant request; no manual AI resync is required.
- If no exact pages are selected, the plugin automatically detects FAQ, raffle/selection/draw rules, Contact/Support, Delivery/Returns, Terms, Privacy, and Refer & Earn pages. Administrators can instead select exact published pages in settings.
- Dynamic shortcodes and scripts are never executed while reading page knowledge, so account-only/dynamic customer data is not pulled into the public assistant. Static Elementor text is supported.
- Live official page facts take precedence over older manual knowledge when the two conflict, while page content remains untrusted data and cannot override security/privacy/tool restrictions.
- Added the approved RaffleLB policies: Lebanon delivery at $4.50, typically 1–3 business days, prize delivery covered within Lebanon, 7-day eligible direct-purchase returns/exchanges, 48-hour damaged/wrong-item reporting, raffle-entry refund exceptions, full-payment-only Raffle Points, and the current Refer & Earn 10% reward rules.
- Payment methods remain intentionally generic until RaffleLB chooses/finalizes them; the assistant should refer to the methods currently shown at checkout.
- Upgrade migration replaces only untouched pre-0.1.15 generic policy defaults; administrator-customized knowledge is preserved.


## 0.1.14

- Added a safe predefined icon selector for each of the three opening quick questions.
- Icon choices now follow the configured quick question instead of being permanently tied to row position.
- Existing defaults remain Question, Ticket, and Package, so upgrades preserve the current appearance unless changed by an administrator.

## 0.1.13 raffle trust-answer refinement

- Trust questions about winner selection now answer clearly at the public customer-facing level instead of defaulting to an evasive internal-controls disclaimer.
- Questions such as whether admins choose winners or staff can pick whoever they want now state that admins/staff do not choose or arbitrarily pick winners through the normal customer-facing raffle process; winners are handled according to RaffleLB's official selection process and published rules.
- Backend implementation details, source code, audit hashes, locks, and internal admin mechanisms remain non-customer-facing and are not disclosed.
- No product, pricing, raffle-state, OpenAI provider, REST, frontend, privacy, or tool behavior changed.

## 0.1.12 compound catalogue filtering

- Fixes multi-constraint recommendations such as “perfume under $150 that I can buy directly and that also has a raffle”.
- Extracts the core catalogue search term and applies direct-purchase min/max price plus direct-buy / active-raffle constraints server-side before returning products to the model.
- Distinguishes raffle-enabled from currently live/enterable raffles in public product data.
- Keeps direct-purchase budget comparisons tied to the RaffleLB retail/buy-now price, never the raffle-entry fee.


## Configuration

Open the top-level **RaffleLB AI Assistant** menu, configure an OpenAI API key, review the starter knowledge, and enable the assistant. The database API key can be overridden in `wp-config.php`:

```php
define('RAFFLELB_AI_OPENAI_API_KEY', 'your-key');
define('RAFFLELB_AI_OPENAI_MODEL', 'gpt-5.6-luna'); // Optional.
```

The saved key is never rendered back into an input. A blank API-key submission preserves the existing saved value.



## 0.1.11 first-party trust answers

- Adds an editable **Trust & Authenticity** knowledge section with safe defaults for the official RaffleLB website and authentic/original products.
- Instructs the official assistant to answer administrator-approved first-party business facts directly instead of adding inappropriate third-party-review caveats.
- Keeps independent-verification caveats only for customers who explicitly ask for external proof, reviews, certification, or third-party evidence.

## 0.1.10 answer readability

- Adds safe structured rendering for assistant replies without using `innerHTML`: same-site Markdown-style links become real links and `**bold**` markers render as bold text through DOM nodes.
- Adds visible spacing between product/bullet blocks so catalogue answers do not run together.
- Instructs catalogue answers to keep each product and its link together with a blank line between products.
- Keeps all product pricing semantics, tools, REST behavior, security controls, and the approved widget layout unchanged.


## 0.1.9 raffle availability wording

- Products with `purchase_mode = store_and_raffle` are presented with the direct-purchase price first and a separate note that they are also available through a raffle.
- General catalogue answers do not present `raffle_entry_price` as the product price and do not automatically quote the entry fee unless raffle pricing is requested or directly useful.
- Raffle-only products are described as raffle-only without inventing a retail/direct-buy price.

## 0.1.8 direct-purchase price semantics

- Product tools now distinguish RaffleLB direct-buy retail pricing from raffle entry pricing.
- Raffle-enabled products use `_rafflelb_buy_now_enabled` + `_rafflelb_buy_now_price` for direct purchase availability/price.
- WooCommerce native product price is exposed only as `raffle_entry_price` for raffle-enabled products.
- General catalogue answers are instructed to prefer direct-purchase pricing and never label an entry fee as a retail/product price.

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
- `includes/class-tools.php`: four public read-only tools plus three authenticated current-customer-only read tools.
- `includes/class-rest-controller.php`: public read-only chat endpoint, validation, and abuse controls.
- `includes/class-frontend.php` and `assets/`: accessible vanilla JavaScript/CSS widget.

## Privacy and boundaries

The plugin does not persist chat transcripts. For signed-in customers, it may read only that same customer’s raffle entry numbers, own order status, and own Raffle Points balance through server-scoped tools. It never returns another customer’s data, addresses, phone numbers, email addresses, or payment credentials/details. It does not add to cart, reserve or allocate entries, create/change/cancel/refund orders, adjust points, mutate draws, select winners, or call private Draw Engine mutation methods. Live raffle progress remains an aggregate count of active rows for a public product through the RaffleLB Core table contract.


## 0.1.20
- Common signed-in personal questions about the customer's own raffles/tickets, own orders, and own Raffle Points are now resolved directly by authenticated server-side read-only lookups before OpenAI is called.
- This prevents an OpenAI/tool follow-up failure from producing a generic error for simple account questions.
- These deterministic personal lookups do not consume OpenAI request quota.


## 0.1.20
- Personal account follow-ups now use recent conversation context. For example, after asking about your registered raffles, `What about orders?` is answered directly from the signed-in customer's own orders instead of falling through to the AI provider.
