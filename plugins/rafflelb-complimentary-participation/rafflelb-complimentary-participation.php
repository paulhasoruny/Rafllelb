<?php
/**
 * Plugin Name: RaffleLB Complimentary Participation
 * Description: Private, administrator-controlled complimentary raffle participation using the normal WooCommerce and RaffleLB Draw Engine flows.
 * Version: 0.1.2
 * Author: RaffleLB
 * Text Domain: rafflelb-complimentary-participation
 * Requires Plugins: woocommerce, rafflelb-core, rafflelb-draw-engine
 */

if (!defined('ABSPATH')) {
    exit;
}

final class RaffleLB_Complimentary_Participation {
    const VERSION = '0.1.2';

    const USER_META_ENABLED = '_rafflelb_complimentary_enabled';
    const SECURITY_RESET_ID           = '0.1.2-strict-whitelist-reset';
    const OPTION_SECURITY_RESET_VERSION = '_rafflelb_cp_security_reset_version';
    const OPTION_SECURITY_NOTICE        = '_rafflelb_cp_security_reset_notice';

    const ORDER_META_MARKER       = '_rafflelb_complimentary';
    const ORDER_META_NOMINAL      = '_rafflelb_nominal_entry_value';
    const ORDER_META_ACTUAL       = '_rafflelb_actual_paid_value';
    const ORDER_META_CREATED_AT   = '_rafflelb_complimentary_created_at';

    const ITEM_META_MARKER        = '_rafflelb_complimentary';
    const ITEM_META_NOMINAL_UNIT  = '_rafflelb_nominal_unit_value';
    const ITEM_META_NOMINAL_LINE  = '_rafflelb_nominal_line_value';
    const ITEM_META_ACTUAL        = '_rafflelb_actual_paid_value';

    const PURCHASE_MODE_META = '_rafflelb_purchase_mode';
    const DRAW_ENABLED_META  = '_rafflelb_draw_enabled';
    const GATEWAY_ID         = 'rafflelb_complimentary';

    private static $applying_prices = false;

    public static function boot() {
        // Security hardening for 0.1.2: complimentary access is a strict,
        // administrator-managed per-user whitelist. Existing accidental "yes"
        // flags are reset once on upgrade, and every newly-created account is
        // explicitly initialized to "no".
        self::maybe_run_security_reset();

        add_action('admin_init', array(__CLASS__, 'maybe_run_security_reset'), 1);
        add_action('user_register', array(__CLASS__, 'initialize_new_user_disabled'), 1, 1);
        add_action('show_user_profile', array(__CLASS__, 'render_user_profile'));
        add_action('edit_user_profile', array(__CLASS__, 'render_user_profile'));
        add_action('personal_options_update', array(__CLASS__, 'save_user_profile'));
        add_action('edit_user_profile_update', array(__CLASS__, 'save_user_profile'));
        add_action('admin_notices', array(__CLASS__, 'dependency_notice'));
        add_action('admin_notices', array(__CLASS__, 'security_reset_notice'));
        add_filter('manage_users_columns', array(__CLASS__, 'add_users_list_column'));
        add_filter('manage_users_custom_column', array(__CLASS__, 'render_users_list_column'), 10, 3);

        add_filter('is_protected_meta', array(__CLASS__, 'protect_private_meta'), 10, 3);
        add_filter('woocommerce_hidden_order_itemmeta', array(__CLASS__, 'hide_private_item_meta'));
        add_filter('woocommerce_order_item_get_formatted_meta_data', array(__CLASS__, 'remove_private_formatted_item_meta'), 10, 2);
        add_filter('woocommerce_rest_prepare_shop_order_object', array(__CLASS__, 'strip_private_rest_order_meta'), 10, 3);
        add_filter('woocommerce_rest_prepare_shop_order', array(__CLASS__, 'strip_private_rest_order_meta'), 10, 3);

        if (!class_exists('WooCommerce') || !class_exists('WC_Payment_Gateway')) {
            return;
        }

        add_action('woocommerce_before_calculate_totals', array(__CLASS__, 'apply_complimentary_prices'), 1000);
        add_action('woocommerce_checkout_process', array(__CLASS__, 'validate_checkout'), 1000);
        add_action('woocommerce_checkout_create_order_line_item', array(__CLASS__, 'save_order_item_metadata'), 100, 4);
        add_action('woocommerce_checkout_create_order', array(__CLASS__, 'save_order_metadata'), 100, 2);

        add_filter('woocommerce_payment_gateways', array(__CLASS__, 'register_gateway'));
        add_filter('woocommerce_available_payment_gateways', array(__CLASS__, 'restrict_available_gateways'), 10001);

        add_action('woocommerce_checkout_order_processed', array(__CLASS__, 'complete_zero_cost_order'), 5, 3);
        add_action('woocommerce_store_api_checkout_order_processed', array(__CLASS__, 'complete_store_api_zero_cost_order'), 5, 1);

        add_action('woocommerce_admin_order_data_after_order_details', array(__CLASS__, 'render_admin_order_audit'));

        // RaffleLB Referral & Points determines "qualified" customers from
        // processing/completed order queries. Exclude complimentary orders only
        // inside its own account/admin screens while leaving normal order history,
        // My Account, and the AI Assistant's allowlisted order lookup unchanged.
        add_filter('woocommerce_order_query_args', array(__CLASS__, 'exclude_from_referral_qualification_queries'), 1000);
    }

    /**
     * Strict one-time whitelist reset for v0.1.2.
     *
     * This intentionally uses a new migration id so it runs even if the v0.1.1
     * migration option was already present. Any existing explicit value other
     * than "no" is normalized to "no". Missing metadata is already disabled.
     * The method is called during plugin boot and again on admin_init; the option
     * guard makes repeated calls harmless.
     */
    public static function maybe_run_security_reset() {
        $done_version = (string) get_option(self::OPTION_SECURITY_RESET_VERSION, '');
        if ($done_version === self::SECURITY_RESET_ID) {
            return;
        }

        global $wpdb;
        $user_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT user_id
             FROM {$wpdb->usermeta}
             WHERE meta_key = %s
               AND (meta_value IS NULL OR meta_value <> %s)",
            self::USER_META_ENABLED,
            'no'
        ));

        $reset_count = 0;
        foreach ((array) $user_ids as $user_id) {
            $user_id = absint($user_id);
            if ($user_id < 1) {
                continue;
            }

            update_user_meta($user_id, self::USER_META_ENABLED, 'no');
            clean_user_cache($user_id);
            $reset_count++;
        }

        update_option(self::OPTION_SECURITY_RESET_VERSION, self::SECURITY_RESET_ID, false);
        update_option(self::OPTION_SECURITY_NOTICE, (string) $reset_count, false);
    }

    /**
     * New accounts must always start with complimentary participation disabled.
     */
    public static function initialize_new_user_disabled($user_id) {
        $user_id = absint($user_id);
        if ($user_id > 0) {
            update_user_meta($user_id, self::USER_META_ENABLED, 'no');
        }
    }

    public static function security_reset_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $notice = get_option(self::OPTION_SECURITY_NOTICE, null);
        if ($notice === null || $notice === false || $notice === '') {
            return;
        }

        delete_option(self::OPTION_SECURITY_NOTICE);
        $count = absint($notice);

        echo '<div class="notice notice-warning is-dismissible"><p><strong>RaffleLB Complimentary Participation:</strong> ';
        echo esc_html(sprintf(
            'Strict whitelist reset completed. %d existing account(s) were set to Disabled. All new accounts also default to Disabled. Manually enable only the intended account(s).',
            $count
        ));
        echo '</p></div>';
    }

    public static function add_users_list_column($columns) {
        if (!current_user_can('manage_options')) {
            return $columns;
        }

        $columns['rafflelb_complimentary'] = __('Complimentary', 'rafflelb-complimentary-participation');
        return $columns;
    }

    public static function render_users_list_column($output, $column_name, $user_id) {
        if ($column_name !== 'rafflelb_complimentary' || !current_user_can('manage_options')) {
            return $output;
        }

        return self::is_user_enabled($user_id)
            ? '<strong style="color:#008a20">' . esc_html__('Enabled', 'rafflelb-complimentary-participation') . '</strong>'
            : '<span style="color:#646970">' . esc_html__('Disabled', 'rafflelb-complimentary-participation') . '</span>';
    }

    public static function declare_hpos_compatibility() {
        if (class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
            Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                __FILE__,
                true
            );
        }
    }

    public static function dependency_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $missing = array();
        if (!class_exists('WooCommerce')) {
            $missing[] = 'WooCommerce';
        }
        if (!class_exists('RaffleLB_Draw_Engine')) {
            $missing[] = 'RaffleLB Draw Engine';
        }

        if (!$missing) {
            return;
        }

        echo '<div class="notice notice-warning"><p><strong>RaffleLB Complimentary Participation:</strong> ';
        echo esc_html('Inactive until the following dependency is available: ' . implode(', ', $missing) . '.');
        echo '</p></div>';
    }

    private static function can_manage_user($user_id) {
        $user_id = absint($user_id);
        return $user_id > 0
            && get_userdata($user_id)
            && current_user_can('manage_options')
            && current_user_can('edit_user', $user_id);
    }

    public static function render_user_profile($user) {
        if (!$user instanceof WP_User || !self::can_manage_user($user->ID)) {
            return;
        }

        $enabled = get_user_meta($user->ID, self::USER_META_ENABLED, true) === 'yes';
        wp_nonce_field('rafflelb_cp_update_user_' . absint($user->ID), 'rafflelb_cp_nonce');
        ?>
        <h2><?php echo esc_html__('RaffleLB Complimentary Participation', 'rafflelb-complimentary-participation'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php echo esc_html__('Enable Complimentary Participation', 'rafflelb-complimentary-participation'); ?></th>
                <td>
                    <label for="rafflelb_complimentary_enabled">
                        <input
                            type="checkbox"
                            id="rafflelb_complimentary_enabled"
                            name="rafflelb_complimentary_enabled"
                            value="yes"
                            <?php checked($enabled); ?>
                        >
                        <?php echo esc_html__('Allow this user to place eligible raffle-entry orders at $0.', 'rafflelb-complimentary-participation'); ?>
                    </label>
                    <p class="description">
                        <?php echo esc_html__('Applies to future raffle-entry orders only. Existing orders are not changed.', 'rafflelb-complimentary-participation'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    public static function save_user_profile($user_id) {
        $user_id = absint($user_id);
        if (!self::can_manage_user($user_id)) {
            return;
        }

        if (!isset($_POST['rafflelb_cp_nonce'])) {
            return;
        }

        $nonce = sanitize_text_field(wp_unslash($_POST['rafflelb_cp_nonce']));
        if (!wp_verify_nonce($nonce, 'rafflelb_cp_update_user_' . $user_id)) {
            return;
        }

        $enabled = isset($_POST['rafflelb_complimentary_enabled'])
            && sanitize_key(wp_unslash($_POST['rafflelb_complimentary_enabled'])) === 'yes';

        update_user_meta($user_id, self::USER_META_ENABLED, $enabled ? 'yes' : 'no');
    }

    public static function is_user_enabled($user_id) {
        $user_id = absint($user_id);
        return $user_id > 0 && get_user_meta($user_id, self::USER_META_ENABLED, true) === 'yes';
    }

    private static function current_user_is_enabled() {
        return is_user_logged_in() && self::is_user_enabled(get_current_user_id());
    }

    private static function item_purchase_mode($item) {
        if (!is_array($item)) {
            return 'raffle_entry';
        }

        if (class_exists('RaffleLB\\Core\\WooCommerce')) {
            return RaffleLB\Core\WooCommerce::cart_item_mode($item);
        }

        return isset($item[self::PURCHASE_MODE_META]) && $item[self::PURCHASE_MODE_META] === 'buy_now'
            ? 'buy_now'
            : 'raffle_entry';
    }

    private static function order_item_purchase_mode($item) {
        if (!$item instanceof WC_Order_Item_Product) {
            return 'buy_now';
        }

        if (class_exists('RaffleLB\\Core\\WooCommerce')) {
            return RaffleLB\Core\WooCommerce::order_item_mode($item);
        }

        return (string) $item->get_meta(self::PURCHASE_MODE_META, true) === 'buy_now'
            ? 'buy_now'
            : 'raffle_entry';
    }

    private static function raffle_product_id($product) {
        if (!$product instanceof WC_Product) {
            return 0;
        }

        if (is_callable(array('RaffleLB_Draw_Engine', 'shop_bridge_draw_id'))) {
            return absint(RaffleLB_Draw_Engine::shop_bridge_draw_id($product));
        }

        $product_id = absint($product->get_id());
        if ($product_id && get_post_meta($product_id, self::DRAW_ENABLED_META, true) === 'yes') {
            return $product_id;
        }

        $parent_id = absint($product->get_parent_id());
        if ($parent_id && get_post_meta($parent_id, self::DRAW_ENABLED_META, true) === 'yes') {
            return $parent_id;
        }

        return 0;
    }

    /**
     * A complimentary checkout is deliberately limited to a pure raffle-entry
     * cart. Mixed/direct/shop carts retain their normal paid behavior.
     */
    public static function cart_qualifies($cart = null) {
        if (!self::current_user_is_enabled() || !class_exists('RaffleLB_Draw_Engine')) {
            return false;
        }

        if (!$cart && function_exists('WC') && WC()) {
            $cart = WC()->cart;
        }
        if (!$cart instanceof WC_Cart || $cart->is_empty()) {
            return false;
        }

        $found = false;
        foreach ($cart->get_cart() as $cart_item) {
            if (empty($cart_item['data']) || !$cart_item['data'] instanceof WC_Product) {
                return false;
            }
            if (self::item_purchase_mode($cart_item) !== 'raffle_entry') {
                return false;
            }
            if (!self::raffle_product_id($cart_item['data'])) {
                return false;
            }
            if (empty($cart_item['quantity']) || absint($cart_item['quantity']) < 1) {
                return false;
            }
            $found = true;
        }

        return $found;
    }

    private static function nominal_unit_value($product_id, $variation_id = 0) {
        $lookup_id = absint($variation_id) ?: absint($product_id);
        $product = $lookup_id && function_exists('wc_get_product') ? wc_get_product($lookup_id) : false;
        if (!$product instanceof WC_Product) {
            return 0.0;
        }
        return max(0.0, (float) $product->get_price('edit'));
    }

    public static function apply_complimentary_prices($cart) {
        if (self::$applying_prices || (is_admin() && !wp_doing_ajax())) {
            return;
        }
        if (!$cart instanceof WC_Cart || !self::cart_qualifies($cart)) {
            return;
        }

        self::$applying_prices = true;
        try {
            foreach ($cart->get_cart() as $cart_item) {
                if (!empty($cart_item['data']) && $cart_item['data'] instanceof WC_Product) {
                    $cart_item['data']->set_price(0);
                }
            }
        } finally {
            self::$applying_prices = false;
        }
    }

    public static function validate_checkout() {
        if (!function_exists('WC') || !WC()->cart || !self::cart_qualifies(WC()->cart)) {
            return;
        }

        if (abs((float) WC()->cart->get_total('edit')) > 0.00001) {
            wc_add_notice(
                __('This complimentary raffle checkout could not be validated at $0. Please contact an administrator.', 'rafflelb-complimentary-participation'),
                'error'
            );
        }
    }

    public static function save_order_item_metadata($item, $cart_item_key, $values, $order) {
        if (!$item instanceof WC_Order_Item_Product || !$order instanceof WC_Order) {
            return;
        }
        if (!function_exists('WC') || !WC()->cart || !self::cart_qualifies(WC()->cart)) {
            return;
        }
        if (self::item_purchase_mode($values) !== 'raffle_entry') {
            return;
        }

        $product = isset($values['data']) && $values['data'] instanceof WC_Product ? $values['data'] : $item->get_product();
        if (!self::raffle_product_id($product)) {
            return;
        }

        $product_id = isset($values['product_id']) ? absint($values['product_id']) : absint($item->get_product_id());
        $variation_id = isset($values['variation_id']) ? absint($values['variation_id']) : absint($item->get_variation_id());
        $quantity = max(1, absint($item->get_quantity()));
        $unit = self::nominal_unit_value($product_id, $variation_id);
        $line = $unit * $quantity;

        $item->add_meta_data(self::ITEM_META_MARKER, 'yes', true);
        $item->add_meta_data(self::ITEM_META_NOMINAL_UNIT, self::decimal($unit), true);
        $item->add_meta_data(self::ITEM_META_NOMINAL_LINE, self::decimal($line), true);
        $item->add_meta_data(self::ITEM_META_ACTUAL, self::decimal(0), true);
    }

    public static function save_order_metadata($order, $data) {
        if (!$order instanceof WC_Order || !function_exists('WC') || !WC()->cart) {
            return;
        }
        if (!self::cart_qualifies(WC()->cart)) {
            return;
        }

        // Do not silently absorb shipping, taxes, or third-party fees. A valid
        // pure raffle checkout must already total zero after server-side prices.
        if (abs((float) $order->get_total()) > 0.00001) {
            throw new Exception(
                __('Complimentary raffle checkout validation failed because the final order total was not $0.', 'rafflelb-complimentary-participation')
            );
        }

        $nominal = 0.0;
        foreach ($order->get_items('line_item') as $item) {
            if ($item instanceof WC_Order_Item_Product && self::order_item_purchase_mode($item) === 'raffle_entry') {
                $nominal += max(0.0, (float) $item->get_meta(self::ITEM_META_NOMINAL_LINE, true));
            }
        }

        $order->update_meta_data(self::ORDER_META_MARKER, 'yes');
        $order->update_meta_data(self::ORDER_META_NOMINAL, self::decimal($nominal));
        $order->update_meta_data(self::ORDER_META_ACTUAL, self::decimal(0));
        $order->update_meta_data(self::ORDER_META_CREATED_AT, current_time('mysql', true));

        // The private ID supplies an auditable internal payment path. The title
        // remains generic because WooCommerce can show it on customer screens.
        $order->set_payment_method(self::GATEWAY_ID);
        $order->set_payment_method_title(__('No payment required', 'rafflelb-complimentary-participation'));
    }

    private static function decimal($value) {
        $decimals = function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2;
        return function_exists('wc_format_decimal')
            ? wc_format_decimal((float) $value, $decimals)
            : number_format((float) $value, $decimals, '.', '');
    }

    public static function register_gateway($gateways) {
        if (class_exists('RaffleLB_Complimentary_Gateway')) {
            $gateways[] = 'RaffleLB_Complimentary_Gateway';
        }
        return $gateways;
    }

    public static function restrict_available_gateways($gateways) {
        if (is_admin() && !wp_doing_ajax()) {
            return $gateways;
        }
        if (!self::cart_qualifies()) {
            return $gateways;
        }

        return isset($gateways[self::GATEWAY_ID])
            ? array(self::GATEWAY_ID => $gateways[self::GATEWAY_ID])
            : array();
    }

    public static function order_is_complimentary($order) {
        return $order instanceof WC_Order
            && $order->get_meta(self::ORDER_META_MARKER, true) === 'yes';
    }

    public static function complete_zero_cost_order($order_id, $posted_data = array(), $order = null) {
        if (!$order instanceof WC_Order && function_exists('wc_get_order')) {
            $order = wc_get_order(absint($order_id));
        }
        self::maybe_complete_order($order);
    }

    public static function complete_store_api_zero_cost_order($order) {
        self::maybe_complete_order($order);
    }

    private static function maybe_complete_order($order) {
        if (!self::order_is_complimentary($order) || abs((float) $order->get_total()) > 0.00001) {
            return;
        }

        if ($order->has_status(array('pending', 'on-hold'))) {
            // payment_complete() is WooCommerce's normal zero-cost transition.
            // Draw Engine already listens to the resulting processing/completed
            // status and remains the sole owner of entry generation.
            $order->payment_complete();
        }
    }

    public static function render_admin_order_audit($order) {
        if (!current_user_can('manage_woocommerce') || !self::order_is_complimentary($order)) {
            return;
        }

        $quantity = 0;
        $lines = array();
        foreach ($order->get_items('line_item') as $item) {
            if ($item instanceof WC_Order_Item_Product && $item->get_meta(self::ITEM_META_MARKER, true) === 'yes') {
                $line_quantity = max(0, absint($item->get_quantity()));
                $quantity += $line_quantity;
                $lines[] = array(
                    'name'     => $item->get_name(),
                    'quantity' => $line_quantity,
                    'unit'     => max(0.0, (float) $item->get_meta(self::ITEM_META_NOMINAL_UNIT, true)),
                    'nominal'  => max(0.0, (float) $item->get_meta(self::ITEM_META_NOMINAL_LINE, true)),
                    'actual'   => max(0.0, (float) $item->get_meta(self::ITEM_META_ACTUAL, true)),
                );
            }
        }

        $nominal = (float) $order->get_meta(self::ORDER_META_NOMINAL, true);
        $actual = (float) $order->get_meta(self::ORDER_META_ACTUAL, true);
        $created_at = (string) $order->get_meta(self::ORDER_META_CREATED_AT, true);
        $currency = $order->get_currency();
        ?>
        <div class="order_data_column" style="clear:both;width:100%;padding-top:14px">
            <div style="border-left:4px solid #2271b1;background:#f0f6fc;padding:12px 14px;max-width:680px">
                <strong style="display:block;font-size:14px;margin-bottom:8px">
                    <?php echo esc_html__('COMPLIMENTARY ENTRY', 'rafflelb-complimentary-participation'); ?>
                </strong>
                <?php if ($lines) : ?>
                    <table style="width:100%;border-collapse:collapse;margin:7px 0 10px">
                        <thead>
                            <tr>
                                <th style="text-align:left;padding:5px;border-bottom:1px solid #c3c4c7"><?php echo esc_html__('Raffle', 'rafflelb-complimentary-participation'); ?></th>
                                <th style="text-align:right;padding:5px;border-bottom:1px solid #c3c4c7"><?php echo esc_html__('Normal price', 'rafflelb-complimentary-participation'); ?></th>
                                <th style="text-align:right;padding:5px;border-bottom:1px solid #c3c4c7"><?php echo esc_html__('Quantity', 'rafflelb-complimentary-participation'); ?></th>
                                <th style="text-align:right;padding:5px;border-bottom:1px solid #c3c4c7"><?php echo esc_html__('Nominal total', 'rafflelb-complimentary-participation'); ?></th>
                                <th style="text-align:right;padding:5px;border-bottom:1px solid #c3c4c7"><?php echo esc_html__('Paid', 'rafflelb-complimentary-participation'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lines as $line) : ?>
                                <tr>
                                    <td style="text-align:left;padding:5px"><?php echo esc_html($line['name']); ?></td>
                                    <td style="text-align:right;padding:5px"><?php echo wp_kses_post(wc_price($line['unit'], array('currency' => $currency))); ?></td>
                                    <td style="text-align:right;padding:5px"><?php echo esc_html($line['quantity']); ?></td>
                                    <td style="text-align:right;padding:5px"><?php echo wp_kses_post(wc_price($line['nominal'], array('currency' => $currency))); ?></td>
                                    <td style="text-align:right;padding:5px"><?php echo wp_kses_post(wc_price($line['actual'], array('currency' => $currency))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                <p style="margin:3px 0">
                    <?php echo esc_html__('Order nominal total:', 'rafflelb-complimentary-participation'); ?>
                    <strong><?php echo wp_kses_post(wc_price($nominal, array('currency' => $currency))); ?></strong>
                </p>
                <p style="margin:3px 0">
                    <?php echo esc_html__('Quantity:', 'rafflelb-complimentary-participation'); ?>
                    <strong><?php echo esc_html($quantity); ?></strong>
                </p>
                <p style="margin:3px 0">
                    <?php echo esc_html__('Actually paid:', 'rafflelb-complimentary-participation'); ?>
                    <strong><?php echo wp_kses_post(wc_price($actual, array('currency' => $currency))); ?></strong>
                </p>
                <?php if ($created_at !== '') : ?>
                    <p style="margin:3px 0">
                        <?php echo esc_html__('Created at (UTC):', 'rafflelb-complimentary-participation'); ?>
                        <strong><?php echo esc_html($created_at); ?></strong>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private static function private_meta_keys() {
        return array(
            self::USER_META_ENABLED,
            self::ORDER_META_MARKER,
            self::ORDER_META_NOMINAL,
            self::ORDER_META_ACTUAL,
            self::ORDER_META_CREATED_AT,
            self::ITEM_META_NOMINAL_UNIT,
            self::ITEM_META_NOMINAL_LINE,
        );
    }

    public static function protect_private_meta($protected, $meta_key, $meta_type) {
        return in_array((string) $meta_key, self::private_meta_keys(), true) ? true : $protected;
    }

    public static function hide_private_item_meta($hidden) {
        return array_values(array_unique(array_merge((array) $hidden, self::private_meta_keys())));
    }

    public static function remove_private_formatted_item_meta($formatted_meta, $item) {
        foreach ((array) $formatted_meta as $meta_id => $meta) {
            $key = is_object($meta) && isset($meta->key) ? (string) $meta->key : '';
            if (in_array($key, self::private_meta_keys(), true)) {
                unset($formatted_meta[$meta_id]);
            }
        }
        return $formatted_meta;
    }

    public static function strip_private_rest_order_meta($response, $order, $request) {
        if (!is_object($response) || !is_callable(array($response, 'get_data')) || !is_callable(array($response, 'set_data'))) {
            return $response;
        }

        $data = $response->get_data();
        if (isset($data['meta_data']) && is_array($data['meta_data'])) {
            $private_keys = self::private_meta_keys();
            $data['meta_data'] = array_values(array_filter($data['meta_data'], static function($meta) use ($private_keys) {
                return !is_array($meta) || !isset($meta['key']) || !in_array((string) $meta['key'], $private_keys, true);
            }));
            $response->set_data($data);
        }
        return $response;
    }

    private static function is_referral_reporting_context() {
        if (is_admin()) {
            $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
            return in_array($page, array('rafflelb-referrals', 'rafflelb-user-points'), true);
        }

        return function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('refer-and-earn');
    }

    public static function exclude_from_referral_qualification_queries($args) {
        if (!is_array($args) || !self::is_referral_reporting_context() || empty($args['customer_id'])) {
            return $args;
        }

        $statuses = isset($args['status']) ? (array) $args['status'] : array();
        $statuses = array_map(static function($status) {
            return preg_replace('/^wc-/', '', sanitize_key((string) $status));
        }, $statuses);

        if (!in_array('processing', $statuses, true) || !in_array('completed', $statuses, true)) {
            return $args;
        }

        $not_complimentary = array(
            'relation' => 'OR',
            array(
                'key'     => self::ORDER_META_MARKER,
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key'     => self::ORDER_META_MARKER,
                'value'   => 'yes',
                'compare' => '!=',
            ),
        );

        if (empty($args['meta_query']) || !is_array($args['meta_query'])) {
            $args['meta_query'] = array($not_complimentary);
        } else {
            $args['meta_query'] = array(
                'relation' => 'AND',
                $args['meta_query'],
                $not_complimentary,
            );
        }

        return $args;
    }
}

function rafflelb_complimentary_participation_bootstrap() {
    if (class_exists('WC_Payment_Gateway') && !class_exists('RaffleLB_Complimentary_Gateway')) {
        class RaffleLB_Complimentary_Gateway extends WC_Payment_Gateway {
            public function __construct() {
                $this->id = RaffleLB_Complimentary_Participation::GATEWAY_ID;
                $this->method_title = __('Complimentary Raffle Entry', 'rafflelb-complimentary-participation');
                $this->method_description = '';
                $this->title = __('No payment required', 'rafflelb-complimentary-participation');
                $this->description = '';
                $this->has_fields = false;
                $this->enabled = 'yes';
                $this->supports = array('products');
            }

            public function is_available() {
                return RaffleLB_Complimentary_Participation::cart_qualifies();
            }

            public function process_payment($order_id) {
                $order = function_exists('wc_get_order') ? wc_get_order(absint($order_id)) : false;
                if (!RaffleLB_Complimentary_Participation::order_is_complimentary($order)
                    || abs((float) $order->get_total()) > 0.00001) {
                    wc_add_notice(
                        __('This no-payment checkout is not available for this order.', 'rafflelb-complimentary-participation'),
                        'error'
                    );
                    return array('result' => 'failure');
                }

                if ($order->has_status(array('pending', 'on-hold'))) {
                    $order->payment_complete();
                }

                if (function_exists('WC') && WC()->cart) {
                    WC()->cart->empty_cart();
                }

                return array(
                    'result'   => 'success',
                    'redirect' => $this->get_return_url($order),
                );
            }
        }
    }

    RaffleLB_Complimentary_Participation::boot();
}

add_action('before_woocommerce_init', array('RaffleLB_Complimentary_Participation', 'declare_hpos_compatibility'));
add_action('plugins_loaded', 'rafflelb_complimentary_participation_bootstrap', 30);
