# RaffleLB Selection Engine 0.2.13 — Early-closure outcome states

Selection Engine now consumes only the public-safe early-close projection from Draw Engine: closure mode, sanitized public note, and privacy-reduced refund status. `selection` keeps the immutable locked pool in the existing Awaiting Selection lifecycle and states that no Points refund was issued. `cancel_refund` maps to a distinct public `cancelled` state, retaining the locked entry register as a public record while explicitly marking Selection and Winner as not proceeding/not applicable.

All routes remain GET-only. Selection Engine adds no raffle/result writes, does not expose the internal closure reason, administrator identity, order/user IDs, payment details, refund amounts, or complimentary metadata, and preserves the 0.2.12 live polling/cache protections and Eligible Entries presentation.

# RaffleLB Selection Engine 0.2.10 — Implementation Report

## 0.2.10 full-snapshot cache correction

LIVE and AWAITING public status snapshots now bypass both reads and writes in Selection Engine's transient/object cache. COMPLETE snapshots alone retain the existing 20-second cache. This removes redundant live cache writes and prevents an awaiting snapshot from delaying the public transition to a recorded result. Non-complete status responses remain `no-store`; browser no-store requests, cache-busting, and fresh live-pool zero verification are unchanged.

The browser now rejects a page-one live-pool response unless zero totals have zero rows, positive totals have at least one row, and returned rows do not exceed the declared total. The Draw Engine bridge, immutable locked pool, masking, REST fields, GET-only routes, database behavior, and complete 0.2.9 visual design are unchanged.

## 0.2.9 ledger and live-refresh correction

Eligible Entries now renders as one compact two-column table-style register: a muted header, fixed ticket column, immediately adjacent left-aligned participant column, flat rows, and one outer border. Desktop shows eight 38px rows; mobile shows eight 36px rows.

The zero-reset was caused by mutable open-raffle status snapshots sharing the 20-second transient/object-cache path and by the browser treating every changed status revision as authoritative, including rebuilding the register from a temporary zero/incomplete response. Version 0.2.9 never reuses a cached `live` snapshot, marks live status/pool REST responses `no-store`, sends cache-busted no-store browser requests, and verifies an unexpected zero through the fresh live-pool route. Failed or inconsistent polls leave the last verified count, register, and progress unchanged. The renderer compares the incoming pool with the displayed pool and rebuilds the register only when its mode, total, immutable revision, or public rows materially change.

The Draw Engine plugin and `selection_bridge_locked_pool()` contract are unchanged. Closed/completed pages continue to use the same immutable locked revision. All routes remain GET-only and Selection Engine adds no database writes.

## 0.2.8 single-register correction

Version 0.2.8 changes only Eligible Entries CSS and release metadata. The participant list now has one outer border, with flat gapless rows separated by a single 1px horizontal rule and no separator after the final row. All 0.2.7 PHP data adapters, HTML renderer, JavaScript, REST contracts, privacy behavior, and Draw Engine integration are unchanged.

## 0.2.7 compact panel

Version 0.2.7 changes only Eligible Entries presentation and release metadata. It removes the inner list border/radius/background, uses fixed 36px rows with thin separators, aligns ticket numbers left and masked names right, caps the internal scroller at 288px, keeps the search control at 40px, and reduces panel/count/notice spacing. Live reads, locked snapshots, masking, REST routes, search and pagination JavaScript, and every Draw Engine contract are unchanged from 0.2.6.

## Live source

While public raffle status is `live`, Selection Engine reads current `status='active'` rows from the existing `{prefix}rafflelb_entries` table. It selects only `entry_number` and the internal `order_id` needed to obtain billing names. Before returning, `order_id` is discarded and the participant is converted server-side to `FirstName S***`; unusable names become `Participant ***`.

The live route checks raffle state both before and after the query. If closure occurs during the request, it returns no mutable list. Live results are paginated in 60-row UI chunks, support exact entry-number lookup, and may change on the existing public status refresh cycle.

## Locked source

Closed and completed raffles retain the 0.2.5 path unchanged: `RaffleLB_Draw_Engine::selection_bridge_locked_pool()` supplies the active immutable revision captured by Draw Engine 0.34.18.42. Selection Engine never reconstructs a closed historical pool from current entries.

## Public fields and privacy

The GET-only live route returns only the public mode, current active count, formatted entry number, and masked participant name. No internal entry identifier, user identifier, order identifier, username, surname, email, phone, payment data, private status, or metadata is returned.

## Interface states

- Open: `LIVE`, current active count, searchable current entry rows, and a notice that entries may change.
- Locked: authoritative immutable snapshot, `LOCKED`, and the existing ticket search/chunking behavior.
- Complete: the same locked snapshot with the existing restrained winning-entry marker.

Selection Engine creates no schema and contains no raffle-entry, closing, locking, winner-selection, or result mutation. Draw Engine is unchanged.
