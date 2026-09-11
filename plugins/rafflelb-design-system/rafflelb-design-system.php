<?php
/**
 * Plugin Name: RaffleLB Design System
 * Description: Shared RaffleLB frontend visual foundation. 0.1.2 scope: final safe foundation — Manrope + shared typography tokens, body sets font-family only (no inherited weight/line-height).
 * Version: 0.1.2
 * Author: RaffleLB
 * Requires PHP: 7.4
 */
if (!defined('ABSPATH')) { exit; }

final class RaffleLB_Design_System {
    const VERSION = '0.1.2';

    public static function init() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_typography']);
    }

    /**
     * Frontend only. Never touches wp-admin, the block editor, or WooCommerce admin screens.
     */
    public static function enqueue_typography() {
        if (is_admin()) return;

        wp_enqueue_style(
            'rafflelb-design-system-typography',
            plugins_url('assets/css/typography.css', __FILE__),
            [],
            self::VERSION
        );
    }
}

RaffleLB_Design_System::init();
