<?php
/**
 * Plugin Name: RaffleLB Design System
 * Description: Shared RaffleLB frontend visual foundation. 0.1.3 scope: Manrope + shared typography tokens + Selection ecosystem typography guard; no layout/size/weight changes.
 * Version: 0.1.3
 * Author: RaffleLB
 * Requires PHP: 7.4
 */
if (!defined('ABSPATH')) { exit; }

final class RaffleLB_Design_System {
    const VERSION = '0.1.3';

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
