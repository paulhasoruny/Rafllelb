=== RaffleLB Cart Manager ===
Contributors: rafflelb
Requires at least: 6.5
Requires PHP: 7.4
Stable tag: 0.1.2
License: GPLv2 or later

Product-first administration of saved WooCommerce carts and existing RaffleLB holds.

== Installation ==
1. Test on a staging copy of your site first.
2. Upload rafflelb-cart-manager.zip under Plugins > Add New > Upload Plugin.
3. Activate alongside WooCommerce and the supplied RaffleLB Draw Engine 0.34.18.3 package.
4. Open RaffleLB Cart Manager > Cart Manager. Removal is enabled two minutes after activation.

No existing RaffleLB plugin files need replacing. Core and Account remain unchanged.
Requires InnoDB tables and MySQL/MariaDB GET_LOCK support. An unsupported session
handler or changed engine reservation code disables removal.

== Usage ==
Search products, expand a product, and choose Remove Now for its holder.
The action removes all lines for that product and purchase mode from that cart.
Registered users show their name, email and ID. Guests have a hashed anonymous label.
Raffle variations use the draw product's ID; direct-purchase variations remain distinct.
Raffle and direct-purchase lines are separate groups even when their product is the same.
Remaining time follows the engine's original timer. Refresh carts updates the snapshot.
For raffle products, only WooCommerce carts with a matching currently-active Draw Engine
hold are displayed. Saved/stale raffle carts whose 15-minute hold has expired are hidden,
and raffle products with zero active holds do not appear. Active unmatched holds remain
visible as warnings but cannot be manually released.
Last activity means the last observed cart request, recorded only after activation.
It is not inferred from session expiry or hold created_at.

Store/direct-purchase cart quantities do not represent reserved stock. Dormant account
persistent carts without an active Woo session are excluded from live cart counts.
Unmatched holds are shown but cannot be manually released. They expire normally.
Expired/missing timers, shared hold tokens, stale forms and changes to other holds
cause a refusal rather than a potentially inconsistent update. Use the storefront to
edit your own current admin-user cart. This is removal, not a ban: shoppers may add again.

== Integration ==
Uses RaffleLB_Draw_Engine::sync_hold_from_cart() in an isolated in-memory cart/session
context. This invokes the engine's existing release-and-rebuild mechanism. Other hold
quantities and expiry values must match before/after, or the transaction rolls back.
Last raffle removal uses the engine's empty-cart branch and clears its timer.
The manager does not create a second timer, manipulate paid entries, change orders,
change gateway settings, or directly adjust WooCommerce stock.

Target Woo session, account persistent cart and raffle hold changes commit together.
Session and user-meta caches are invalidated after commit. Removed-item undo entries
are cleared, and totals/shipping caches are invalidated for Woo's next recalculation.
Standard cookie session requests use a subclass of the default Woo session handler
with per-customer advisory locks held through shutdown. Known Store API Cart-Token
requests lock before their standard final handler loads. Unknown handlers are retained
and disable admin removal when detected. A detected unknown handler flag is reset on
reactivation, after its integration has been reviewed.

Only manage_woocommerce users can view or remove. Removal requires POST, a WordPress
nonce and a current cart fingerprint. No raw guest session key/token is rendered.

== Validation scope ==
PHP 8.3 syntax; JavaScript syntax; 20 local behavior assertions using the supplied
engine class and WooCommerce 10.1.2 session base, backed by a transactional SQLite
test adapter; 12 method-contract checks; browser preview layout, filtering and collapse.
These are not live WordPress/MySQL/payment tests. Before production, validate classic
and Block checkout, simultaneous requests, guest login migration and persistent object
caching on staging. See the accompanying validation report for details.

== Deactivation ==
Deactivation restores the default session-handler selection and removes the UI hooks.
It does not restore manually removed items or alter remaining holds. No custom tables
or schedules are created. Uninstall removes only this plugin's two option flags.

== Changelog ==
= 0.1.1 =
* Show only active Draw Engine reservation holders for raffle products.
* Hide stale/expired raffle carts and raffle products with zero active holds.
* Clarify raffle summary counts as active carts plus reserved quantity.

= 0.1.0 =
* Initial release.
