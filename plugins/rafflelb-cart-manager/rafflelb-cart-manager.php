<?php
/**
 * Plugin Name: RaffleLB Cart Manager
 * Description: Product-first cart inspection and removal using RaffleLB Draw Engine reservations.
 * Version: 0.1.2
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
register_activation_hook(__FILE__, function () {
    update_option('rlcm_activated_at', time(), false);
    delete_option('rlcm_custom_session_seen');
});
add_action('woocommerce_cart_loaded_from_session', function () {
    if (function_exists('WC') && WC()->session && WC()->cart && !WC()->cart->is_empty()) WC()->session->set('rlcm_last_activity', time());
}, 99);
