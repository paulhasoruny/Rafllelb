<?php
/**
 * Store Only product renderer.
 *
 * The outer page shell - container, columns, gallery, Product Information,
 * title and meta row - belongs to RaffleLB_Shop::render_product_shell(), which
 * is shared with raffle products. This class contributes the Store Only
 * purchase area only: the compact Buy Direct card wrapped around WooCommerce's
 * own price and add-to-cart template, so native price, stock, quantity
 * validation, cart, checkout, orders and stock reduction are untouched. No
 * RaffleLB purchase mode is submitted.
 */
defined('ABSPATH') || exit;

final class RaffleLB_Store_Only_Renderer {
    public static function render($product) {
        if (!$product instanceof WC_Product) return;
        RaffleLB_Shop::render_product_shell($product, [__CLASS__, 'purchase_area']);
    }

    public static function purchase_area($product) {
        echo '<section class="rl-buy-now-panel rl-store-buy-direct" aria-label="Buy this product directly">';
        echo '<span class="rl-buy-now-icon rl-store-buy-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 3h2l2.1 10.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.6L19 8H7M9 21a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm8 0a1 1 0 1 0 0-2 1 1 0 1 0 0 2Z"/></svg></span>';
        echo '<div class="rl-buy-now-copy rl-store-buy-copy"><strong>Buy Direct</strong><p>Purchase this item directly at the listed retail price. This option is a standard product purchase and does not include a raffle entry.</p></div>';
        echo '<div class="rl-buy-now-action rl-store-buy-action"><span>RETAIL PRICE</span><p class="price">' . wp_kses_post($product->get_price_html()) . '</p>';
        woocommerce_template_single_add_to_cart();
        echo '</div></section>';
    }
}
