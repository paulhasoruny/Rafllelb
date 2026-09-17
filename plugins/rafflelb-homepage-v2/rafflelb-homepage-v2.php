<?php
/**
 * Plugin Name: RaffleLB Homepage V2
 * Description: Isolated, standalone alternate homepage for RaffleLB via the [rafflelb_homepage_v2] shortcode. Reads existing RaffleLB/WooCommerce data read-only; does not modify the live homepage or any other plugin.
 * Version: 0.1.5
 * Author: RaffleLB
 * Requires PHP: 7.4
 * Text Domain: rafflelb-homepage-v2
 */

if (!defined('ABSPATH')) {
    exit;
}

define('RLHV2_VERSION', '0.1.5');
define('RLHV2_FILE', __FILE__);
define('RLHV2_DIR', plugin_dir_path(__FILE__));
define('RLHV2_URL', plugin_dir_url(__FILE__));
define('RLHV2_SHORTCODE', 'rafflelb_homepage_v2');

final class RaffleLB_Homepage_V2 {

    public static function init() {
        require_once RLHV2_DIR . 'includes/class-rlhv2-data.php';
        require_once RLHV2_DIR . 'includes/class-rlhv2-render.php';

        add_action('init', [__CLASS__, 'register_shortcode']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'maybe_enqueue_assets']);
    }

    public static function register_shortcode() {
        add_shortcode(RLHV2_SHORTCODE, [__CLASS__, 'render_shortcode']);
    }

    /**
     * Only enqueue on pages that actually contain the shortcode, so this
     * plugin never adds weight to the rest of the site (including the live
     * homepage, which does not use this shortcode).
     */
    public static function maybe_enqueue_assets() {
        if (!self::current_request_has_shortcode()) {
            return;
        }

        wp_enqueue_style(
            'rafflelb-homepage-v2',
            RLHV2_URL . 'assets/css/rafflelb-homepage-v2.css',
            [],
            RLHV2_VERSION
        );
    }

    private static function current_request_has_shortcode() {
        if (is_admin()) {
            return false;
        }
        $post = get_post();
        if (!$post instanceof WP_Post) {
            return false;
        }
        return has_shortcode((string) $post->post_content, RLHV2_SHORTCODE);
    }

    public static function render_shortcode($atts = [], $content = '') {
        // Belt-and-braces: guarantee the stylesheet is present even if this
        // shortcode is rendered from somewhere the wp_enqueue_scripts check
        // above did not catch (widget, template part, do_shortcode() call).
        if (!wp_style_is('rafflelb-homepage-v2', 'enqueued') && !wp_style_is('rafflelb-homepage-v2', 'done')) {
            wp_enqueue_style(
                'rafflelb-homepage-v2',
                RLHV2_URL . 'assets/css/rafflelb-homepage-v2.css',
                [],
                RLHV2_VERSION
            );
        }

        return RLHV2_Render::page();
    }
}

RaffleLB_Homepage_V2::init();
