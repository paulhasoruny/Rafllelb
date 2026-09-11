<?php
/**
 * Plugin Name: RaffleLB Cart Manager
 * Description: Product-first cart inspection and removal using RaffleLB Draw Engine reservations.
 * Version: 0.1.4
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Author: RaffleLB
 */
defined('ABSPATH') || exit;
require_once __DIR__ . '/includes.php';
add_filter('woocommerce_session_handler', array('RLCM', 'session_handler'), 999);
add_action('admin_menu', array('RLCM', 'menu'), 999);
add_action('admin_post_rlcm_remove', array('RLCM', 'handle_remove'));
add_action('admin_enqueue_scripts', array('RLCM', 'assets'));
add_action('wp_head', function () {
    if (is_admin()) return;
    ?>
    <style id="rafflelb-cart-manager-mini-cart-v014">
    /* RaffleLB Cart Manager 0.1.4: WoodMart off-canvas mini-cart presentation only. */
    body .cart-widget-side,
    body .cart-widget-side .widget-heading,
    body .cart-widget-side .widget_shopping_cart,
    body .cart-widget-side .shopping-cart-widget-footer {
        background:#090c09!important;
        color:#f5f7f2!important;
        font-family:var(--rl-font, "Manrope", sans-serif)!important;
    }
    body .cart-widget-side .widget-heading {
        display:flex!important;
        align-items:center!important;
        justify-content:space-between!important;
    }
    body .cart-widget-side :is(.widget-title,.widget-heading .widget-title,.widget-heading>h3) {
        color:#f5f7f2!important;
        -webkit-text-fill-color:#f5f7f2!important;
        font-family:var(--rl-font, "Manrope", sans-serif)!important;
        font-weight:750!important;
        opacity:1!important;
    }
    body .cart-widget-side :is(.close-side-widget,.close-side-widget span,.wd-action-btn.wd-style-text>a) {
        color:#d9dfd5!important;
        -webkit-text-fill-color:#d9dfd5!important;
        font-family:var(--rl-font, "Manrope", sans-serif)!important;
        opacity:1!important;
    }
    body .cart-widget-side :is(.close-side-widget,.wd-action-btn.wd-style-text>a):hover,
    body .cart-widget-side :is(.close-side-widget,.wd-action-btn.wd-style-text>a):focus-visible {
        color:#baff00!important;
        -webkit-text-fill-color:#baff00!important;
    }
    body .cart-widget-side :is(.close-side-widget,.close-side-widget>a,.wd-action-btn.wd-style-text>a)::before,
    body .cart-widget-side :is(.close-side-widget,.close-side-widget>a,.wd-action-btn.wd-style-text>a)::after,
    body .cart-widget-side .close-side-widget .wd-tools-icon {
        color:currentColor!important;
        border-color:currentColor!important;
        opacity:1!important;
    }
    body .cart-widget-side .woocommerce-mini-cart-item,
    body .cart-widget-side .mini_cart_item {
        color:#d9dfd5!important;
        font-family:var(--rl-font, "Manrope", sans-serif)!important;
    }
    body .cart-widget-side :is(.cart-item-link,.wd-entities-title,.woocommerce-mini-cart-item>a:not(.remove),.mini_cart_item>a:not(.remove)) {
        color:#f7f8f5!important;
        -webkit-text-fill-color:#f7f8f5!important;
        font-weight:700!important;
        line-height:1.35!important;
    }
    body .cart-widget-side :is(.variation,.variation dt,.variation dd,.variation p,.product_meta,.sku_wrapper) {
        color:#aeb7ab!important;
        -webkit-text-fill-color:#aeb7ab!important;
        font-size:12px!important;
        line-height:1.5!important;
    }
    body .cart-widget-side :is(.quantity,.quantity .amount,.woocommerce-mini-cart-item .amount,.mini_cart_item .amount) {
        color:#f7f8f5!important;
        -webkit-text-fill-color:#f7f8f5!important;
        font-weight:650!important;
    }
    body .cart-widget-side :is(.quantity .amount,.woocommerce-mini-cart-item .amount,.mini_cart_item .amount) {
        color:#baff00!important;
        -webkit-text-fill-color:#baff00!important;
    }
    body .cart-widget-side :is(.remove,.remove_from_cart_button) {
        color:#e8ece5!important;
        -webkit-text-fill-color:#e8ece5!important;
        border-color:rgba(255,255,255,.18)!important;
        opacity:1!important;
    }
    body .cart-widget-side :is(.remove,.remove_from_cart_button):hover,
    body .cart-widget-side :is(.remove,.remove_from_cart_button):focus-visible {
        color:#baff00!important;
        -webkit-text-fill-color:#baff00!important;
        border-color:rgba(186,255,0,.5)!important;
    }
    body .cart-widget-side :is(.woocommerce-mini-cart__total,.shopping-cart-widget-footer .total),
    body .cart-widget-side :is(.woocommerce-mini-cart__total,.shopping-cart-widget-footer .total) :is(strong,.amount) {
        color:#f7f8f5!important;
        -webkit-text-fill-color:#f7f8f5!important;
        font-family:var(--rl-font, "Manrope", sans-serif)!important;
        font-weight:700!important;
    }
    body .cart-widget-side .woocommerce-mini-cart__buttons {
        display:grid!important;
        gap:10px!important;
    }
    body .cart-widget-side .woocommerce-mini-cart__buttons :is(.button,.btn-cart,.checkout) {
        display:flex!important;
        align-items:center!important;
        justify-content:center!important;
        width:100%!important;
        min-height:44px!important;
        padding:11px 20px!important;
        border-radius:999px!important;
        font-family:var(--rl-font, "Manrope", sans-serif)!important;
        font-size:13px!important;
        font-weight:750!important;
        line-height:1.25!important;
        text-align:center!important;
    }
    body .cart-widget-side .woocommerce-mini-cart__buttons :is(.btn-cart,.button:first-child) {
        border:1px solid rgba(186,255,0,.7)!important;
        background:#10150e!important;
        color:#f7f8f5!important;
        -webkit-text-fill-color:#f7f8f5!important;
    }
    body .cart-widget-side .woocommerce-mini-cart__buttons :is(.btn-cart,.button:first-child):hover,
    body .cart-widget-side .woocommerce-mini-cart__buttons :is(.btn-cart,.button:first-child):focus-visible {
        border-color:#baff00!important;
        background:#18210f!important;
        color:#baff00!important;
        -webkit-text-fill-color:#baff00!important;
    }
    body .cart-widget-side .woocommerce-mini-cart__buttons :is(.checkout,.button:last-child) {
        border-color:#baff00!important;
        background:#baff00!important;
        color:#071000!important;
        -webkit-text-fill-color:#071000!important;
    }
    </style>
    <?php
}, 2000);
register_activation_hook(__FILE__, function () {
    update_option('rlcm_activated_at', time(), false);
    delete_option('rlcm_custom_session_seen');
});
add_action('woocommerce_cart_loaded_from_session', function () {
    if (function_exists('WC') && WC()->session && WC()->cart && !WC()->cart->is_empty()) WC()->session->set('rlcm_last_activity', time());
}, 99);
