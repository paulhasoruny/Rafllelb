# RaffleLB Core 0.1.0 — additive foundation

Install rafflelb-core.zip using WordPress Plugins > Add New > Upload Plugin, then activate on a staging copy first. Keep all seven existing plugins installed and active. This adds an eighth plugin; the eventual eleven-plugin architecture is not yet extracted.

Core loads namespaced PHP classes only. It registers no actions, filters, shortcodes, endpoints, scheduled jobs, activation/deactivation migrations, or uninstall cleanup. It performs no database queries until explicitly requested. Existing plugins do not call Core yet; Core is a foundation for later migrations, not a replacement Draw Engine.

## API for future modules

- `RaffleLB\Core\Contracts`: exact inspected Draw Engine table suffixes, metadata constants and hold duration, plus Notifications table and draw event name. Existing class constants remain authoritative for legacy code.
- `RaffleLB\Core\Database::table('entries'|'holds'|'results'|'history'|'notifications')`: current-blog table name; unknown aliases return WP_Error. No cached prefix.
- `Database::exists($alias)`: read-only SHOW TABLES check on demand. Does not validate schema or create tables.
- `RaffleLB\Core\WooCommerce::order($id)`: delegates to wc_get_order, preserving WooCommerce storage abstraction. Missing WooCommerce returns WP_Error; invalid order follows WooCommerce's false return.
- `WooCommerce::cart_item_mode($item)` and `order_item_mode($item)`: Draw Engine's exact item-mode interpretation; buy_now or raffle_entry.
- `WooCommerce::cart_mode('engine'|'checkout')`: delegates to the chosen legacy provider. Missing provider or invalid context returns WP_Error. Engine may return an empty string; Checkout defaults to raffle_entry. Do not interchange these contexts.
- `RaffleLB\Core\Compatibility::report()`: call after plugins_loaded completes. Compares installed plugin header versions against baseline.json, checks active state and the notification listener. This is a diagnostic, not proof of behavioral compatibility or source identity. Renamed plugin directories are reported missing_or_renamed.

Consumers must use is_wp_error() for error-capable APIs and declare/load Core before calling its classes. No global legacy function is replaced. No hard Core dependency is added to legacy plugins.

## Rollback

Deactivate and delete only RaffleLB Core. There is no Core data to remove or restore. Keep the seven legacy plugins active. Once future modules depend on Core, this rollback procedure must be revised.

## Staging acceptance before production

Capture database/table counts, schema, product/order/user metadata and settings before activation. Compare after an otherwise idle activation/deactivation cycle; Core should cause no changes. Test real workflows on staging separately, since those intentionally write data.

1. Existing homepage, shop, product, account, menu, checkout, shortcodes and endpoint URLs render identically.
2. Raffle-only, buy-now, mixed, empty and variation carts retain existing classification and restrictions; holds, capacity and expiry remain correct.
3. Paid order processing and repeated payment callbacks create no duplicate entries or points. Pending/manual OMT payment retains proof/reference and order status behavior.
4. Failed, cancelled and refunded orders retain entry voiding and referral/points reversal behavior.
5. Random/manual winner selection, early closure, winner emails, notifications, result history and fulfillment work once per intended action.
6. My Account menu ordering, Refer & Earn, Notifications, orders, view-order and mobile links match baseline.
7. Compare existing HPOS setting and multisite behavior where used; do not change either setting as part of this release.
8. Deactivate Core and repeat a representative purchase/draw/account flow. Legacy operation must remain independent.

Do not run Raffle Manager reset/test-order deletion actions against production for testing.
