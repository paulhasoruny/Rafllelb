<?php
/** Store Only uses the shared RaffleLB product shell; WooCommerce owns purchase. */
defined('ABSPATH') || exit;

final class RaffleLB_Store_Only_Renderer {
    public static function render($product) {
        if (!$product instanceof WC_Product) return;
        $classes = function_exists('wc_get_product_class') ? wc_get_product_class('', $product) : ['product'];
        echo '<div id="product-' . esc_attr($product->get_id()) . '" class="' . esc_attr(implode(' ', $classes)) . '"><div class="product-image-summary"><div class="rl-product-layout"><div class="rl-product-left">';
        woocommerce_show_product_images();
        RaffleLB_Shop::product_details_panel();
        echo '</div><div class="rl-product-right"><div class="summary entry-summary">';
        echo '<h1 class="product_title entry-title">' . esc_html($product->get_name()) . '</h1>';
        echo '<div class="rl-product-meta-row"><span class="rl-stock-badge' . ($product->is_in_stock() ? ' is-in-stock' : ' is-out-of-stock') . '"><i aria-hidden="true"></i>' . esc_html($product->is_in_stock() ? 'IN STOCK' : 'OUT OF STOCK') . '</span><span class="rl-product-classification">' . wp_kses_post(wc_get_product_category_list($product->get_id(), ', ')) . '</span></div>';
        echo '<section class="rl-buy-now-panel rl-store-buy-direct" aria-label="Buy this product directly"><span class="rl-buy-now-icon rl-store-buy-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 3h2l2.1 10.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.6L19 8H7M9 21a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm8 0a1 1 0 1 0 0-2 1 1 0 1 0 0 2Z"/></svg></span><div class="rl-buy-now-copy rl-store-buy-copy"><strong>Buy Direct</strong><p>Purchase this item directly at the listed retail price. This option is a standard product purchase and does not include a raffle entry.</p></div><div class="rl-buy-now-action rl-store-buy-action"><span>RETAIL PRICE</span><p class="price">' . wp_kses_post($product->get_price_html()) . '</p>';
        woocommerce_template_single_add_to_cart();
        echo '</div></section></div></div></div></div></div>';
    }
}
