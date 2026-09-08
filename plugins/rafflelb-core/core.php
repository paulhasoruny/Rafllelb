<?php
namespace RaffleLB\Core;
if (!defined('ABSPATH')) { exit; }

/** Exact legacy storage identifiers. Legacy plugins retain schema ownership. */
final class Contracts {
    const VERSION = '0.1.2';
    const ENTRY_TABLE = 'rafflelb_entries';
    const HOLD_TABLE = 'rafflelb_holds';
    const RESULT_TABLE = 'rafflelb_draw_results';
    const HISTORY_TABLE = 'rafflelb_entry_history';
    const META_DRAW_STATUS = '_rafflelb_draw_status';
    const META_CLOSED_AT = '_rafflelb_draw_closed_at';
    const META_WINNER_ENTRY_ID = '_rafflelb_winner_entry_id';
    const META_WINNER_SELECTED_AT = '_rafflelb_winner_selected_at';
    const META_EARLY_CLOSED = '_rafflelb_early_closed';
    const META_EARLY_CLOSE_REASON = '_rafflelb_early_close_reason';
    const META_EARLY_CLOSED_BY = '_rafflelb_early_closed_by';
    const META_EARLY_CLOSE_CLAIMED = '_rafflelb_early_close_claimed';
    const META_ENABLED = '_rafflelb_draw_enabled';
    const META_TOTAL = '_rafflelb_total_entries';
    const META_BUY_NOW_ENABLED = '_rafflelb_buy_now_enabled';
    const META_BUY_NOW_PRICE = '_rafflelb_buy_now_price';
    const META_HERO_IMAGE = '_rafflelb_homepage_hero_image_id';
    const ITEM_MODE_META = '_rafflelb_purchase_mode';
    const HOLD_MINUTES = 15;
    const NOTIFICATIONS_TABLE = 'rafflelb_notifications';
    const DRAW_COMPLETED_HOOK = 'rafflelb_draw_completed';
}

/** Resolve the current blog prefix on every call, including after switch_to_blog. */
final class Database {
    public static function table($name) {
        global $wpdb;
        $tables = array(
            'entries' => Contracts::ENTRY_TABLE,
            'holds' => Contracts::HOLD_TABLE,
            'results' => Contracts::RESULT_TABLE,
            'history' => Contracts::HISTORY_TABLE,
            'notifications' => Contracts::NOTIFICATIONS_TABLE,
        );
        if (!isset($tables[$name])) {
            return new \WP_Error('rafflelb_core_unknown_table', 'Unknown RaffleLB table alias.');
        }
        return $wpdb->prefix . $tables[$name];
    }

    /** Read-only, on-demand inspection; never creates or upgrades a table. */
    public static function exists($name) {
        global $wpdb;
        $table = self::table($name);
        if (is_wp_error($table)) { return $table; }
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;
    }
}

final class WooCommerce {
    public static function available() {
        return class_exists('WooCommerce') && function_exists('WC');
    }
    public static function order($id) {
        if (!function_exists('wc_get_order')) {
            return new \WP_Error('rafflelb_core_woocommerce_missing', 'WooCommerce is unavailable.');
        }
        return wc_get_order($id);
    }
    /** Matches Draw Engine item semantics, including missing/unknown modes. */
    public static function cart_item_mode($item) {
        return is_array($item) && isset($item[Contracts::ITEM_MODE_META]) && $item[Contracts::ITEM_MODE_META] === 'buy_now'
            ? 'buy_now' : 'raffle_entry';
    }
    public static function order_item_mode($item) {
        if (!$item instanceof \WC_Order_Item_Product) { return 'raffle_entry'; }
        return (string) $item->get_meta(Contracts::ITEM_MODE_META, true) === 'buy_now' ? 'buy_now' : 'raffle_entry';
    }
    /** Engine and Checkout have different semantics: callers must select explicitly. */
    public static function cart_mode($context) {
        if ($context === 'engine' && is_callable(array('RaffleLB_Draw_Engine', 'current_cart_purchase_mode'))) {
            return \RaffleLB_Draw_Engine::current_cart_purchase_mode();
        }
        if ($context === 'checkout' && function_exists('rlcc_get_checkout_mode')) {
            return \rlcc_get_checkout_mode();
        }
        return new \WP_Error('rafflelb_core_mode_provider_unavailable', 'Requested cart mode provider is unavailable or context is invalid.');
    }
}


/**
 * Site-wide purchasing access policy.
 * Guests may browse, but adding to cart / starting a raffle reservation requires an account.
 */
final class Access {
    const RETURN_SESSION_KEY = 'rafflelb_auth_return_to';
    private static $notice_added = false;

    public static function boot() {
        if (!class_exists('WooCommerce')) return;

        // Server-side enforcement for every WooCommerce add-to-cart route,
        // including forms, AJAX and direct add-to-cart URLs.
        add_filter('woocommerce_add_to_cart_validation', [__CLASS__, 'validate_add_to_cart'], 1, 6);

        // Defense-in-depth for a guest session that already had cart items
        // before this policy was enabled.
        add_action('woocommerce_checkout_process', [__CLASS__, 'block_guest_checkout'], 1);

        // Preserve the requested product/shop page across WooCommerce login/registration.
        add_action('template_redirect', [__CLASS__, 'capture_return_target'], 1);
        add_filter('woocommerce_login_redirect', [__CLASS__, 'login_redirect'], 10, 2);
        add_filter('woocommerce_registration_redirect', [__CLASS__, 'registration_redirect'], 10, 1);

        // Friendly single-product UX: the visible raffle/cart button goes to
        // My Account immediately for guests; server validation remains the
        // non-bypassable enforcement layer.
        add_action('wp_footer', [__CLASS__, 'guest_product_gate_script'], 40);
    }

    public static function purchase_requires_account() {
        return (bool) apply_filters('rafflelb_purchase_requires_account', true);
    }

    public static function can_purchase() {
        return !self::purchase_requires_account() || is_user_logged_in();
    }

    public static function notice_message() {
        return (string) apply_filters(
            'rafflelb_account_required_message',
            'Please log in or create an account before adding items to your cart or entering a raffle.'
        );
    }

    private static function add_notice() {
        if (self::$notice_added || !function_exists('wc_add_notice')) return;
        self::$notice_added = true;
        wc_add_notice(self::notice_message(), 'error');
    }

    public static function validate_add_to_cart($passed, $product_id = 0, $quantity = 1, $variation_id = 0, $variations = [], $cart_item_data = []) {
        if (!$passed || self::can_purchase()) return $passed;
        self::add_notice();
        return false;
    }

    public static function block_guest_checkout() {
        if (self::can_purchase()) return;
        if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) return;
        self::add_notice();
    }

    public static function login_url($return_url = '') {
        $return_url = $return_url ?: (function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/'));
        $return_url = wp_validate_redirect($return_url, home_url('/'));

        if (function_exists('wc_get_page_permalink')) {
            $account = wc_get_page_permalink('myaccount');
            if ($account) {
                return add_query_arg('rafflelb_return_to', $return_url, $account);
            }
        }
        return wp_login_url($return_url);
    }

    public static function capture_return_target() {
        if (is_user_logged_in() || empty($_GET['rafflelb_return_to'])) return;
        if (!function_exists('is_account_page') || !is_account_page()) return;
        if (!function_exists('WC') || !WC()->session) return;

        $fallback = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/');
        $requested = esc_url_raw(wp_unslash($_GET['rafflelb_return_to']));
        $target = wp_validate_redirect($requested, $fallback);
        $session = WC()->session;
        $session->set(self::RETURN_SESSION_KEY, $target);

        // This flow can be the first WooCommerce interaction for a guest. A
        // session value alone is not durable unless WooCommerce also issues
        // its guest session cookie before the login POST begins.
        if (is_callable(array($session, 'set_customer_session_cookie'))) {
            $session->set_customer_session_cookie(true);
        }
    }

    private static function consume_return_target($fallback) {
        if (!function_exists('WC') || !WC()->session) return $fallback;
        $target = (string) WC()->session->get(self::RETURN_SESSION_KEY);
        if (!$target) return $fallback;
        WC()->session->__unset(self::RETURN_SESSION_KEY);
        return wp_validate_redirect($target, $fallback);
    }

    public static function login_redirect($redirect, $user = null) {
        return self::consume_return_target($redirect);
    }

    public static function registration_redirect($redirect) {
        return self::consume_return_target($redirect);
    }

    public static function guest_product_gate_script() {
        if (is_admin() || self::can_purchase()) return;
        if (!function_exists('is_product') || !is_product()) return;

        $product_id = function_exists('get_queried_object_id') ? absint(get_queried_object_id()) : 0;
        $return_url = $product_id ? get_permalink($product_id) : home_url('/');
        $login_url = self::login_url($return_url);
        ?>
        <script id="rafflelb-account-required-product-gate">
        (function(){
            var loginUrl = <?php echo wp_json_encode($login_url); ?>;
            document.addEventListener('submit', function(e){
                var form = e.target && e.target.closest ? e.target.closest('form.cart') : null;
                if (!form) return;
                e.preventDefault();
                e.stopPropagation();
                window.location.href = loginUrl;
            }, true);
        })();
        </script>
        <?php
    }
}

/** On-demand report. Call after plugins_loaded; no automatic notices or frontend output. */
final class Compatibility {
    public static function report() {
        if (!did_action('plugins_loaded')) {
            return new \WP_Error('rafflelb_core_too_early', 'Run compatibility checks after plugins_loaded.');
        }
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $installed = get_plugins();
        $baseline = json_decode(file_get_contents(__DIR__ . '/baseline.json'), true);
        $result = array('core_version' => Contracts::VERSION, 'woocommerce_available' => WooCommerce::available(), 'plugins' => array());
        foreach ($baseline as $plugin) {
            $file = $plugin['file'];
            $version = isset($installed[$file]) ? $installed[$file]['Version'] : null;
            $active = is_plugin_active($file);
            $result['plugins'][] = array(
                'name' => $plugin['name'], 'file' => $file,
                'baseline_version' => $plugin['version'], 'installed_version' => $version,
                'active' => $active,
                'matches_inspected_version' => $version === $plugin['version'],
                'status' => $version === null ? 'missing_or_renamed' : (!$active ? 'inactive' : ($version === $plugin['version'] ? 'baseline_match' : 'version_changed_review_required')),
            );
        }
        $result['engine_loaded'] = class_exists('RaffleLB_Draw_Engine');
        $result['draw_notification_listener'] = has_action('rafflelb_draw_completed', array('RaffleLB_Notifications', 'on_draw_completed'));
        return $result;
    }
}
