<?php
defined('ABSPATH') || exit;

final class RaffleLB_Admin_Navigation {
    const META_KEY = '_rafflelb_admin_advanced_mode';
    const CAPABILITY = 'manage_options';
    const ROOT_SLUG = 'rafflelb-admin-dashboard';

    public static function init() {
        // Run after normal WordPress/WooCommerce/RaffleLB menus are registered so
        // we can reuse their real destinations before simplifying the sidebar.
        add_action('admin_menu', array(__CLASS__, 'build_menu'), PHP_INT_MAX);
        add_action('admin_post_rafflelb_admin_mode', array(__CLASS__, 'toggle_mode'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
        add_filter('admin_body_class', array(__CLASS__, 'body_class'));
        add_filter('tiny_mce_before_init', array(__CLASS__, 'tiny_mce_product_editor_dark'));
    }

    public static function is_rafflelb_mode($user_id = 0) {
        $user_id = $user_id ?: get_current_user_id();
        return $user_id && get_user_meta($user_id, self::META_KEY, true) !== '1';
    }

    public static function toggle_mode() {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to change admin mode.', 'rafflelb-admin'), '', array('response' => 403));
        }
        check_admin_referer('rafflelb_admin_mode');
        $advanced = !empty($_POST['advanced']);
        update_user_meta(get_current_user_id(), self::META_KEY, $advanced ? '1' : '0');
        wp_safe_redirect($advanced ? admin_url() : admin_url('index.php?page=' . self::ROOT_SLUG));
        exit;
    }

    public static function assets() {
        if (!current_user_can('manage_woocommerce')) return;
        wp_enqueue_style('rafflelb-admin-navigation', RAFFLELB_ADMIN_URL . 'assets/css/navigation.css', array(), RAFFLELB_ADMIN_VERSION);
    }

    public static function body_class($classes) {
        if (!current_user_can('manage_woocommerce') || !self::is_rafflelb_mode()) {
            return $classes;
        }

        $classes .= ' rafflelb-admin-mode';

        // Add a stable screen class for custom/admin.php pages. This lets the
        // RaffleLB skin reliably theme existing plugin screens without changing
        // those plugins or relying on their menu parent.
        if (!empty($_GET['page'])) {
            $page = sanitize_key(wp_unslash($_GET['page']));
            if ($page !== '') {
                $classes .= ' rafflelb-screen-' . sanitize_html_class($page);
            }
        }

        if (self::is_product_edit_screen()) {
            $classes .= ' rafflelb-product-edit';
        }

        return $classes;
    }

    /** True only on the classic WooCommerce product edit / add-new screens. */
    private static function is_product_edit_screen() {
        if (!is_admin()) return false;
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== 'product') return false;
        return in_array($screen->base, array('post', 'post-new'), true);
    }

    /**
     * TinyMCE renders the Visual editor inside an iframe, so normal wp-admin CSS
     * cannot reach the white editing canvas. Add a product-editor-only content
     * style while RaffleLB Mode is active.
     */
    public static function tiny_mce_product_editor_dark($init) {
        if (!current_user_can('manage_woocommerce') || !self::is_rafflelb_mode() || !self::is_product_edit_screen()) {
            return $init;
        }

        $dark = 'html,body,body#tinymce,body.mce-content-body{background:#0f120e!important;color:#f3f6ef!important;}' .
            'body#tinymce p,body#tinymce div,body#tinymce span,body#tinymce li,body#tinymce td,body#tinymce th{color:#f3f6ef;}' .
            'body#tinymce a{color:#d7ff66;}' .
            'body#tinymce hr{border-color:#2a3027;}';
        $existing = isset($init['content_style']) ? trim((string) $init['content_style']) : '';
        $init['content_style'] = ($existing !== '' ? rtrim($existing, ';') . ';' : '') . $dark;
        return $init;
    }

    /**
     * Convert an existing menu slug to a safe wp-admin destination.
     * WordPress prints unregistered submenu slugs literally, which caused paths
     * such as /wp-admin/wc-orders and /wp-admin/?rafflelb-referrals. We store
     * complete admin-relative URLs instead.
     */
    private static function admin_destination($slug) {
        $slug = trim((string) $slug);
        if ($slug === '') return '';

        if (preg_match('#^https?://#i', $slug)) return $slug;
        if (strpos($slug, '/') === 0) return $slug;

        // Already a real admin PHP route.
        if (preg_match('/^[a-z0-9_-]+\.php(?:\?.*)?$/i', $slug)) return $slug;

        // Some custom plugins register a menu slug with a leading question mark.
        $slug = ltrim($slug, '?');
        if ($slug === '') return '';

        // A bare plugin page slug must go through admin.php?page=... .
        return 'admin.php?page=' . rawurlencode($slug);
    }

    private static function clean_label($label) {
        $label = wp_strip_all_tags((string) $label);
        // Strip common count bubbles from menu labels.
        $label = preg_replace('/\s+\d+\s*$/', '', $label);
        return strtolower(trim($label));
    }

    /** Find the real destination already registered by another plugin. */
    private static function find_destination($labels, $preferred_parents = array()) {
        global $menu, $submenu;
        $wanted = array_map(array(__CLASS__, 'clean_label'), (array) $labels);

        // Prefer the known parent (WooCommerce for most RaffleLB operational pages).
        foreach ((array) $preferred_parents as $parent) {
            if (empty($submenu[$parent])) continue;
            foreach ((array) $submenu[$parent] as $entry) {
                if (!isset($entry[0], $entry[2])) continue;
                if (in_array(self::clean_label($entry[0]), $wanted, true)) {
                    return self::admin_destination($entry[2]);
                }
            }
        }

        // Then scan every submenu.
        foreach ((array) $submenu as $entries) {
            foreach ((array) $entries as $entry) {
                if (!isset($entry[0], $entry[2])) continue;
                if (in_array(self::clean_label($entry[0]), $wanted, true)) {
                    return self::admin_destination($entry[2]);
                }
            }
        }

        // Finally scan top-level entries.
        foreach ((array) $menu as $entry) {
            if (!isset($entry[0], $entry[2])) continue;
            if (in_array(self::clean_label($entry[0]), $wanted, true)) {
                return self::admin_destination($entry[2]);
            }
        }
        return '';
    }

    private static function add_link($label, $destination, $capability = 'manage_woocommerce') {
        global $submenu;
        if (!$destination) return;
        if (!isset($submenu[self::ROOT_SLUG])) $submenu[self::ROOT_SLUG] = array();
        $submenu[self::ROOT_SLUG][] = array($label, $capability, $destination);
    }

    private static function registered_destinations() {
        $woo = array('woocommerce');
        return array(
            'raffles' => self::find_destination(array('Raffles', 'All Raffles')) ?: 'admin.php?page=rafflelb-raffles',
            'orders' => self::find_destination(array('Orders'), $woo) ?: 'admin.php?page=wc-orders',
            // Product Studio is optional. Keep the established WooCommerce route
            // when its owning plugin is inactive so navigation never fatals.
            'products' => class_exists('RaffleLB_Products') ? 'admin.php?page=rafflelb-products' : 'edit.php?post_type=product',
            'cart_manager' => self::find_destination(array('Cart Manager', 'RaffleLB Cart Manager')) ?: 'admin.php?page=rafflelb-cart-manager',
            'customers' => 'users.php',
            'reviews' => self::find_destination(array('RaffleLB Reviews', 'Product Reviews', 'Reviews'), $woo) ?: 'edit.php?post_type=product&page=product-reviews',
            'requests' => self::find_destination(array('Raffle Requests'), $woo),
            'points_referrals' => self::find_destination(array('RaffleLB Referrals', 'Raffle Points', 'RaffleLB User Points', 'Points & Referrals'), $woo),
            'notifications' => self::find_destination(array('Notifications'), $woo),
            'coupons' => self::find_destination(array('Coupons'), $woo) ?: 'edit.php?post_type=shop_coupon',
            'settings' => self::find_destination(array('Settings'), $woo) ?: 'admin.php?page=wc-settings',
        );
    }

    public static function build_menu() {
        if (!current_user_can('manage_woocommerce')) return;

        if (!self::is_rafflelb_mode()) {
            if (current_user_can(self::CAPABILITY)) {
                add_menu_page(__('RaffleLB Mode', 'rafflelb-admin'), __('RaffleLB Mode', 'rafflelb-admin'), self::CAPABILITY, 'rafflelb-admin-advanced-mode', array(__CLASS__, 'advanced_page'), 'dashicons-admin-generic', 3);
            }
            return;
        }

        global $menu, $submenu;

        // Capture actual plugin destinations before hiding the original menus.
        $dest = self::registered_destinations();

        // Create one operational RaffleLB root.
        add_menu_page(
            __('RaffleLB Operations Dashboard', 'rafflelb-admin'),
            'RaffleLB',
            'manage_woocommerce',
            self::ROOT_SLUG,
            array('RaffleLB_Admin_Dashboard', 'render'),
            'dashicons-star-filled',
            2
        );

        // Replace the automatically-created first submenu with our exact layout.
        $submenu[self::ROOT_SLUG] = array();
        self::add_link(__('Dashboard', 'rafflelb-admin'), 'index.php?page=' . self::ROOT_SLUG);
        self::add_link(__('Raffles', 'rafflelb-admin'), $dest['raffles']);
        self::add_link(__('Orders', 'rafflelb-admin'), $dest['orders']);
        self::add_link(__('Products', 'rafflelb-admin'), $dest['products']);
        self::add_link(__('Cart Manager', 'rafflelb-admin'), $dest['cart_manager']);
        self::add_link(__('Customers', 'rafflelb-admin'), $dest['customers']);
        self::add_link(__('Reviews', 'rafflelb-admin'), $dest['reviews']);
        self::add_link(__('Raffle Requests', 'rafflelb-admin'), $dest['requests']);
        self::add_link(__('Points & Referrals', 'rafflelb-admin'), $dest['points_referrals']);
        self::add_link(__('Notifications', 'rafflelb-admin'), $dest['notifications']);
        self::add_link(__('Coupons', 'rafflelb-admin'), $dest['coupons']);
        self::add_link(__('Settings', 'rafflelb-admin'), $dest['settings']);

        if (current_user_can(self::CAPABILITY)) {
            // This one is a real page owned by this plugin, so register a callback.
            add_submenu_page(self::ROOT_SLUG, __('Advanced Mode', 'rafflelb-admin'), __('Advanced Mode', 'rafflelb-admin'), self::CAPABILITY, 'rafflelb-admin-advanced-mode', array(__CLASS__, 'advanced_page'));
        }

        // In RaffleLB Mode we intentionally show ONLY the operational RaffleLB
        // root. This is a visual/admin-navigation simplification; no plugin page,
        // capability, route or functionality is disabled.
        foreach ((array) $menu as $key => $entry) {
            $slug = isset($entry[2]) ? (string) $entry[2] : '';
            if ($slug !== self::ROOT_SLUG) unset($menu[$key]);
        }
    }

    public static function advanced_page() {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to change admin mode.', 'rafflelb-admin'), '', array('response' => 403));
        }
        $in_raffle_mode = self::is_rafflelb_mode();
        echo '<div class="wrap rlad-mode"><h1>' . esc_html($in_raffle_mode ? __('Advanced Mode', 'rafflelb-admin') : __('RaffleLB Mode', 'rafflelb-admin')) . '</h1><p>' . esc_html($in_raffle_mode ? __('Restore the complete WordPress and WooCommerce menu for your administrator account.', 'rafflelb-admin') : __('Return to the simplified RaffleLB operations navigation for your administrator account.', 'rafflelb-admin')) . '</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="rafflelb_admin_mode"><input type="hidden" name="advanced" value="' . ($in_raffle_mode ? '1' : '0') . '">';
        wp_nonce_field('rafflelb_admin_mode');
        submit_button($in_raffle_mode ? __('Switch to Advanced Mode', 'rafflelb-admin') : __('Return to RaffleLB Mode', 'rafflelb-admin'), 'primary');
        echo '</form></div>';
    }
}
