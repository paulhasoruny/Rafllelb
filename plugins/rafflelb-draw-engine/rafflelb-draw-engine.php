<?php
/**
 * Plugin Name: RaffleLB Draw Engine
 * Description: Raffle entry engine with cart-level reservation locking, unique paid entries, live progress, and WooCommerce integration.
 * Version: 0.34.18.16
 * Author: RaffleLB
 */

if ( ! defined( 'ABSPATH' ) ) exit;

final class RaffleLB_Draw_Engine {
    const SHOP_BRIDGE_VERSION = '1';
    public static function shop_bridge_stats($pid, $exclude_own = false) {
        return self::stats($pid, $exclude_own);
    }
    public static function shop_bridge_draw_id($product) {
        return self::draw_id($product);
    }
    public static function shop_bridge_buy_now_price($product) {
        return self::buy_now_price($product);
    }
    public static function shop_bridge_buy_now_enabled($product) {
        return self::buy_now_enabled($product);
    }
    public static function shop_bridge_is_draw_closed($product) {
        return self::is_draw_closed($product);
    }

    const HOMEPAGE_BRIDGE_VERSION = '1';
    public static function homepage_stats($pid, $exclude_own = false) {
        return self::stats($pid, $exclude_own);
    }
    public static function homepage_draw_id($product) {
        return self::draw_id($product);
    }
    public static function homepage_buy_now_price($product) {
        return self::buy_now_price($product);
    }
    public static function homepage_get_draw_result($pid) {
        return self::get_draw_result($pid);
    }
    public static function homepage_winner_masked_name($result) {
        return self::winner_masked_name($result);
    }

    // Read-only bridge: transactional statistics remain owned by Draw Engine.
    const ACCOUNT_BRIDGE_VERSION = '1';
    public static function account_stats($pid, $exclude_own = false) {
        return self::stats($pid, $exclude_own);
    }

    const VERSION = '0.34.18';
    const ENTRY_TABLE = 'rafflelb_entries';
    const HOLD_TABLE  = 'rafflelb_holds';
    const RESULT_TABLE = 'rafflelb_draw_results';
    const HISTORY_TABLE = 'rafflelb_entry_history';
    const META_DRAW_STATUS = '_rafflelb_draw_status';
    const META_CLOSED_AT  = '_rafflelb_draw_closed_at';
    const META_WINNER_ENTRY_ID = '_rafflelb_winner_entry_id';
    const META_WINNER_SELECTED_AT = '_rafflelb_winner_selected_at';
    const META_EARLY_CLOSED = '_rafflelb_early_closed';
    const META_EARLY_CLOSE_REASON = '_rafflelb_early_close_reason';
    const META_EARLY_CLOSED_BY = '_rafflelb_early_closed_by';
    const META_EARLY_CLOSE_CLAIMED = '_rafflelb_early_close_claimed';
    const META_ENABLED = '_rafflelb_draw_enabled';
    const META_TOTAL   = '_rafflelb_total_entries';
    const META_BUY_NOW_ENABLED = '_rafflelb_buy_now_enabled';
    const META_BUY_NOW_PRICE   = '_rafflelb_buy_now_price';
    const META_HERO_IMAGE      = '_rafflelb_homepage_hero_image_id';
    const ITEM_MODE_META       = '_rafflelb_purchase_mode';
    const HOLD_MINUTES = 15;

    public static function init() {
        register_activation_hook(__FILE__, [__CLASS__, 'activate']);
        register_deactivation_hook(__FILE__, [__CLASS__, 'deactivate']);
        add_action('plugins_loaded', [__CLASS__, 'boot']);
    }


    public static function side_drawer_layer_fix() {
        if ( is_admin() ) return;
        echo '<style id="rafflelb-side-drawer-layer-fix">
            .cart-widget-side,
            .wd-side-hidden,
            .wd-side-hidden.wd-opened,
            .mobile-nav,
            .mobile-nav.wd-opened {
                z-index: 9999999 !important;
            }
            .whb-header::before,
            .whb-header::after,
            .whb-row::before,
            .whb-row::after {
                z-index: 1 !important;
            }
        </style>';
    }

    public static function ensure_schema() {
        $installed = get_option('rafflelb_draw_engine_version', '');

        if ($installed === self::VERSION) {
            return;
        }

        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $cc = $wpdb->get_charset_collate();

        $entries = $wpdb->prefix . self::ENTRY_TABLE;
        $holds   = $wpdb->prefix . self::HOLD_TABLE;

        dbDelta("CREATE TABLE {$entries} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT UNSIGNED NOT NULL,
            order_item_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            entry_number INT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY product_entry (product_id, entry_number),
            KEY order_id (order_id),
            KEY order_item_id (order_item_id),
            KEY product_id (product_id),
            KEY user_id (user_id),
            KEY status (status)
        ) {$cc};");

        dbDelta("CREATE TABLE {$holds} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            hold_token VARCHAR(64) NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            quantity INT UNSIGNED NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY token_product (hold_token, product_id),
            KEY product_id (product_id),
            KEY expires_at (expires_at)
        ) {$cc};");



        $history = $wpdb->prefix . self::HISTORY_TABLE;
        dbDelta("CREATE TABLE {$history} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            entry_id BIGINT UNSIGNED NOT NULL,
            order_id BIGINT UNSIGNED NOT NULL,
            order_item_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            entry_number INT UNSIGNED NOT NULL,
            event_type VARCHAR(32) NOT NULL,
            reason VARCHAR(255) NULL,
            event_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY entry_id (entry_id),
            KEY order_id (order_id),
            KEY product_id (product_id),
            KEY user_id (user_id),
            KEY entry_number (entry_number),
            KEY event_type (event_type)
        ) {$cc};");

        $results = $wpdb->prefix . self::RESULT_TABLE;

        dbDelta("CREATE TABLE {$results} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id BIGINT UNSIGNED NOT NULL,
            entry_id BIGINT UNSIGNED NOT NULL,
            entry_number INT UNSIGNED NOT NULL,
            order_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            selected_at DATETIME NOT NULL,
            selection_method VARCHAR(32) NOT NULL DEFAULT 'random',
            selected_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            selection_note TEXT NULL,
            rng_token VARCHAR(64) NOT NULL DEFAULT '',
            audit_hash VARCHAR(64) NOT NULL,
            fulfillment_status VARCHAR(24) NOT NULL DEFAULT 'pending',
            fulfillment_note TEXT NULL,
            contacted_at DATETIME NULL,
            claimed_at DATETIME NULL,
            fulfilled_at DATETIME NULL,
            winner_email_sent_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY product_id (product_id),
            KEY entry_id (entry_id),
            KEY order_id (order_id),
            KEY user_id (user_id),
            KEY fulfillment_status (fulfillment_status)
        ) {$cc};");

        update_option('rafflelb_draw_engine_version', self::VERSION);
    }

    public static function activate() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $cc = $wpdb->get_charset_collate();

        $entries = $wpdb->prefix . self::ENTRY_TABLE;
        $holds   = $wpdb->prefix . self::HOLD_TABLE;

        dbDelta("CREATE TABLE {$entries} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT UNSIGNED NOT NULL,
            order_item_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            entry_number INT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY product_entry (product_id, entry_number),
            KEY order_id (order_id),
            KEY order_item_id (order_item_id),
            KEY product_id (product_id),
            KEY user_id (user_id),
            KEY status (status)
        ) {$cc};");

        dbDelta("CREATE TABLE {$holds} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            hold_token VARCHAR(64) NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            quantity INT UNSIGNED NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY token_product (hold_token, product_id),
            KEY product_id (product_id),
            KEY expires_at (expires_at)
        ) {$cc};");

        add_rewrite_endpoint('rafflelb-entries', EP_ROOT | EP_PAGES);
        flush_rewrite_rules();


        $history = $wpdb->prefix . self::HISTORY_TABLE;
        dbDelta("CREATE TABLE {$history} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            entry_id BIGINT UNSIGNED NOT NULL,
            order_id BIGINT UNSIGNED NOT NULL,
            order_item_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            entry_number INT UNSIGNED NOT NULL,
            event_type VARCHAR(32) NOT NULL,
            reason VARCHAR(255) NULL,
            event_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY entry_id (entry_id),
            KEY order_id (order_id),
            KEY product_id (product_id),
            KEY user_id (user_id),
            KEY entry_number (entry_number),
            KEY event_type (event_type)
        ) {$cc};");

        $results = $wpdb->prefix . self::RESULT_TABLE;

        dbDelta("CREATE TABLE {$results} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id BIGINT UNSIGNED NOT NULL,
            entry_id BIGINT UNSIGNED NOT NULL,
            entry_number INT UNSIGNED NOT NULL,
            order_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            selected_at DATETIME NOT NULL,
            selection_method VARCHAR(32) NOT NULL DEFAULT 'random',
            selected_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            selection_note TEXT NULL,
            rng_token VARCHAR(64) NOT NULL DEFAULT '',
            audit_hash VARCHAR(64) NOT NULL,
            fulfillment_status VARCHAR(24) NOT NULL DEFAULT 'pending',
            fulfillment_note TEXT NULL,
            contacted_at DATETIME NULL,
            claimed_at DATETIME NULL,
            fulfilled_at DATETIME NULL,
            winner_email_sent_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY product_id (product_id),
            KEY entry_id (entry_id),
            KEY order_id (order_id),
            KEY user_id (user_id),
            KEY fulfillment_status (fulfillment_status)
        ) {$cc};");

        update_option('rafflelb_draw_engine_version', self::VERSION);
    }

    public static function deactivate() { flush_rewrite_rules(); }

    public static function boot() {
        // IMPORTANT: WordPress does not reliably re-run activation hooks when an
        // already-active plugin is replaced by an uploaded ZIP. Ensure/migrate
        // the database schema on every version change.
        self::ensure_schema();

        if ( ! class_exists('WooCommerce') ) return;

        add_action('woocommerce_product_options_general_product_data', [__CLASS__, 'product_fields'], 30);
        add_action('woocommerce_process_product_meta', [__CLASS__, 'save_product_fields']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'admin_hero_image_media']);

        add_action('woocommerce_order_status_processing', [__CLASS__, 'generate_entries_for_order']);
        add_action('woocommerce_order_status_completed', [__CLASS__, 'generate_entries_for_order']);
        add_action('woocommerce_order_status_cancelled', [__CLASS__, 'void_entries_for_order']);
        add_action('woocommerce_order_status_refunded', [__CLASS__, 'void_entries_for_order']);
        add_action('woocommerce_order_status_failed', [__CLASS__, 'void_entries_for_order']);

        add_filter('woocommerce_is_purchasable', [__CLASS__, 'is_purchasable'], 20, 2);
        add_filter('woocommerce_quantity_input_args', [__CLASS__, 'quantity_args'], 20, 2);
        add_filter('woocommerce_add_to_cart_validation', [__CLASS__, 'add_to_cart_validation'], 20, 5);
        add_filter('woocommerce_add_cart_item_data', [__CLASS__, 'purchase_mode_cart_item_data'], 20, 4);
        add_action('woocommerce_before_calculate_totals', [__CLASS__, 'apply_purchase_mode_prices'], 20);
        add_filter('woocommerce_get_item_data', [__CLASS__, 'display_purchase_mode_cart_data'], 20, 2);
        add_action('woocommerce_checkout_create_order_line_item', [__CLASS__, 'save_purchase_mode_order_item'], 20, 4);
        add_action('woocommerce_checkout_create_order', [__CLASS__, 'save_order_purchase_type'], 20, 2);
        add_action('woocommerce_check_cart_items', [__CLASS__, 'check_cart']);

        // v0.6: lock at CART level, independent of classic/block checkout.
        add_action('woocommerce_add_to_cart', [__CLASS__, 'sync_hold_from_cart'], 30);
        add_action('woocommerce_after_cart_item_quantity_update', [__CLASS__, 'sync_hold_from_cart'], 30);
        add_action('woocommerce_cart_item_removed', [__CLASS__, 'sync_hold_from_cart'], 30);
        add_action('woocommerce_cart_item_restored', [__CLASS__, 'sync_hold_from_cart'], 30);

        // Expire abandoned raffle carts BEFORE rebuilding their reservation.
        add_action('woocommerce_cart_loaded_from_session', [__CLASS__, 'expire_raffle_cart_if_needed'], 5);
        add_action('woocommerce_cart_loaded_from_session', [__CLASS__, 'sync_hold_from_cart'], 30);
        add_action('woocommerce_cart_emptied', [__CLASS__, 'release_current_hold'], 30);
        add_action('wp_logout', [__CLASS__, 'release_current_hold']);

        // Release only when the order has actually been created from this cart.
        add_action('woocommerce_checkout_order_created', [__CLASS__, 'release_current_hold'], 30);
        add_action('woocommerce_store_api_checkout_order_processed', [__CLASS__, 'release_current_hold'], 30);

        add_action('init', [__CLASS__, 'cleanup_expired_holds']);

        // v0.25.8: keep WoodMart side drawers above the decorative header lime line.
        add_action('wp_head', [__CLASS__, 'side_drawer_layer_fix'], 99);

        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        add_action('admin_post_rafflelb_close_raffle_early', [__CLASS__, 'handle_close_raffle_early']);
        add_action('admin_post_rafflelb_select_winner', [__CLASS__, 'handle_select_winner']);
        add_action('admin_post_rafflelb_choose_winner', [__CLASS__, 'handle_choose_winner']);
        add_action('admin_post_rafflelb_update_fulfillment', [__CLASS__, 'handle_update_fulfillment']);
        add_action('admin_post_rafflelb_send_winner_email', [__CLASS__, 'handle_send_winner_email']);
        add_action('admin_post_rafflelb_admin_order_action', [__CLASS__, 'handle_admin_order_action']);
        add_action('init', [__CLASS__, 'register_endpoint']);
        add_action('init', [__CLASS__, 'ensure_winners_page'], 20);
        add_shortcode('rafflelb_winners', [__CLASS__, 'winners_shortcode']);
        add_shortcode('rafflelb_recent_winners', [__CLASS__, 'recent_winners_shortcode']);
        add_shortcode('rafflelb_latest_winners', [__CLASS__, 'recent_winners_shortcode']);
        add_shortcode('rafflelb_live_raffles', [__CLASS__, 'live_raffles_shortcode']);
        add_shortcode('rafflelb_featured_products', [__CLASS__, 'featured_products_shortcode']);
        add_shortcode('rafflelb_shop_categories', [__CLASS__, 'shop_categories_shortcode']);
        add_shortcode('rafflelb_raffles_marketplace', [__CLASS__, 'raffles_marketplace_shortcode']);
        add_action('wp_footer', [__CLASS__, 'homepage_hero_products_inject'], 60);
        add_action('wp_footer', [__CLASS__, 'homepage_live_raffles_inject'], 61);
        add_shortcode('rafflelb_community_sections', [__CLASS__, 'community_sections_shortcode']);
        add_action('init', [__CLASS__, 'register_community_content_types'], 12);
        add_action('add_meta_boxes', [__CLASS__, 'community_meta_boxes']);
        add_action('save_post_rafflelb_review', [__CLASS__, 'save_review_meta']);
        add_action('admin_post_rafflelb_submit_review', [__CLASS__, 'handle_review_submission']);
        add_action('admin_post_nopriv_rafflelb_submit_review', [__CLASS__, 'handle_review_submission']);
        add_action('admin_post_rafflelb_submit_raffle_request', [__CLASS__, 'handle_raffle_request']);
        add_action('admin_post_nopriv_rafflelb_submit_raffle_request', [__CLASS__, 'handle_raffle_request']);
        add_filter('the_content', [__CLASS__, 'append_home_community_sections'], 40);
        add_filter('body_class', [__CLASS__, 'winners_body_class']);
        add_filter('query_vars', [__CLASS__, 'query_vars']);
        add_filter('woocommerce_account_menu_items', [__CLASS__, 'account_menu']);
        // Other RaffleLB plugins (Referral & Points) also filter this hook and
        // fully rebuild the array from their own hardcoded order, which can
        // silently drop or displace a tab this plugin (or a future plugin)
        // added. Run one definitive pass last, so the final order is correct
        // no matter what ran before it or whether every plugin is up to date.
        add_filter('woocommerce_account_menu_items', [__CLASS__, 'final_account_menu_order'], 999);
        add_action('woocommerce_account_rafflelb-entries_endpoint', [__CLASS__, 'account_entries']);
        add_action('woocommerce_account_dashboard', [__CLASS__, 'account_winner_summary'], 5);
        add_action('wp_ajax_rafflelb_dismiss_winner_banner', [__CLASS__, 'ajax_dismiss_winner_banner']);
        add_filter('woocommerce_my_account_my_orders_actions', [__CLASS__, 'remove_customer_cancel_action'], 50, 2);
        add_filter('wc_order_statuses', [__CLASS__, 'rafflelb_order_status_labels'], 999);

        // RaffleLB customer email wording.
        add_filter('woocommerce_email_subject_customer_processing_order', [__CLASS__, 'email_subject_paid_order'], 999, 2);
        add_filter('woocommerce_email_heading_customer_processing_order', [__CLASS__, 'email_heading_paid_order'], 999, 2);
        add_filter('woocommerce_email_subject_customer_completed_order', [__CLASS__, 'email_subject_completed_order'], 999, 2);
        add_filter('woocommerce_email_heading_customer_completed_order', [__CLASS__, 'email_heading_completed_order'], 999, 2);

        // Replace WooCommerce's standard customer order emails for RaffleLB
        // orders with our branded dark RaffleLB confirmation email.
        add_filter('woocommerce_email_enabled_customer_processing_order', [__CLASS__, 'disable_default_raffle_processing_email'], 999, 3);
        add_filter('woocommerce_email_enabled_customer_completed_order', [__CLASS__, 'disable_default_raffle_completed_email'], 999, 3);
        add_action('woocommerce_order_status_processing', [__CLASS__, 'send_raffle_order_confirmation_email'], 80);



        add_shortcode('rafflelb_progress', [__CLASS__, 'progress_shortcode']);
        add_action('woocommerce_single_product_summary', [__CLASS__, 'raffle_option_open'], 27);
        add_action('woocommerce_single_product_summary', [__CLASS__, 'auto_progress'], 28);
        add_action('woocommerce_single_product_summary', [__CLASS__, 'raffle_option_close'], 31);
        add_action('wp_footer', [__CLASS__, 'product_details_relocator']);

        // RaffleLB checkout experience.
        add_filter('body_class', [__CLASS__, 'checkout_body_class']);
        add_filter('woocommerce_checkout_fields', [__CLASS__, 'simplify_raffle_checkout_fields'], 30);
        add_filter('woocommerce_order_button_text', [__CLASS__, 'checkout_button_text']);
        add_filter('gettext', [__CLASS__, 'checkout_text_replacements'], 20, 3);
        add_action('woocommerce_before_checkout_form', [__CLASS__, 'checkout_intro'], 8);
        add_action('wp_head', [__CLASS__, 'checkout_styles']);

        // RaffleLB My Account experience.
        add_filter('body_class', [__CLASS__, 'account_body_class']);
        add_action('wp_head', [__CLASS__, 'account_styles']);

        // RaffleLB Cart experience.
        add_filter('body_class', [__CLASS__, 'cart_body_class']);
        add_action('wp_head', [__CLASS__, 'cart_styles']);
        add_filter('woocommerce_product_single_add_to_cart_text', [__CLASS__, 'cart_button_text']);

        // RaffleLB single raffle product experience.
        add_filter('body_class', [__CLASS__, 'raffle_product_body_class']);
        add_action('wp_head', [__CLASS__, 'raffle_product_styles']);
        add_filter('woocommerce_get_price_html', [__CLASS__, 'raffle_product_price_html'], 40, 2);
        add_filter('woocommerce_get_availability_text', [__CLASS__, 'raffle_product_availability_text'], 40, 2);
        add_action('woocommerce_before_add_to_cart_quantity', [__CLASS__, 'raffle_quantity_label']);
        add_action('woocommerce_single_product_summary', [__CLASS__, 'product_details_panel'], 24);
        add_action('woocommerce_single_product_summary', [__CLASS__, 'buy_now_panel'], 25);
        add_action('woocommerce_single_product_summary', [__CLASS__, 'raffle_secondary_intro'], 29);
        add_action('woocommerce_after_add_to_cart_form', [__CLASS__, 'raffle_entry_trust']);
        add_action('woocommerce_single_product_summary', [__CLASS__, 'raffle_short_description'], 12);
        add_filter('woocommerce_product_tabs', [__CLASS__, 'raffle_product_tabs'], 99);
        add_action('woocommerce_after_single_product_summary', [__CLASS__, 'raffle_details_section'], 6);

        // RaffleLB premium retail Shop / product-category archive experience.
        // WoodMart + WooCommerce remain responsible for the actual filter UI/AJAX behavior.
        add_filter('body_class', [__CLASS__, 'raffle_archive_body_class']);
        add_filter('post_class', [__CLASS__, 'raffle_archive_product_class'], 20, 3);
        // Render the premium retail hero and toolbar before WoodMart's own loop tools,
        // so the store header is not trapped inside the theme toolbar container.
        add_action('woocommerce_archive_description', [__CLASS__, 'raffle_archive_hero'], 1);
        add_action('woocommerce_archive_description', [__CLASS__, 'shop_premium_toolbar'], 2);
        add_action('woocommerce_after_shop_loop_item_title', [__CLASS__, 'raffle_archive_progress'], 20);
        add_action('woocommerce_after_shop_loop_item', [__CLASS__, 'raffle_archive_view_button'], 40);
        add_action('wp_head', [__CLASS__, 'raffle_archive_styles']);
        add_action('wp_body_open', [__CLASS__, 'shop_navigation_loading_overlay'], 1);

        /* Site-wide version of the same loading overlay used on the shop
           archive, so every page transition (account, checkout, any other
           page) gets the same smooth spinner instead of a raw browser
           reload flash. Shares the overlay's markup/CSS/element id with
           the shop-specific version above so there's exactly one on any
           given page - the shop keeps its own extra "return to filter
           position" behavior, everywhere else just shows the spinner on
           click and hides it once the destination page has loaded. */
        add_action('wp_head', [__CLASS__, 'site_wide_loading_overlay_styles'], 5);
        add_action('wp_body_open', [__CLASS__, 'site_wide_loading_overlay_markup'], 1);
        add_action('wp_footer', [__CLASS__, 'site_wide_loading_overlay_script'], 20005);
        add_action('wp_enqueue_scripts', [__CLASS__, 'shop_enqueue_inter_font'], 20);
        add_action('wp_head', [__CLASS__, 'global_inter_font_default'], 25);

        // Keep the Shop as a real direct-purchase catalogue while preserving native WoodMart/WooCommerce filters.
        add_action('pre_get_posts', [__CLASS__, 'shop_catalog_scope'], 30);
        add_filter('woocommerce_catalog_orderby', [__CLASS__, 'shop_orderby_options'], 40);
        add_filter('woocommerce_default_catalog_orderby_options', [__CLASS__, 'shop_orderby_options'], 40);

        // WooCommerce stores the raffle entry fee in its normal price field. On Shop archives only,
        // make the native price filter operate on RaffleLB's real retail / Buy It Now value instead.
        add_filter('woocommerce_price_filter_sql', [__CLASS__, 'shop_native_price_filter_sql'], 40, 3);
        add_filter('woocommerce_enable_post_clause_filtering', [__CLASS__, 'shop_disable_core_price_filtering'], 40, 2);
        add_filter('posts_clauses', [__CLASS__, 'shop_native_price_filter_clauses'], 40, 2);

        // Keep legacy/incorrect category links working.
        add_action('template_redirect', [__CLASS__, 'raffle_category_legacy_redirect'], 1);

        // Shared customer-area typography for Cart / Checkout / My Account.
        add_action('wp_head', [__CLASS__, 'customer_area_typography']);

        // Load the final contrast guard after theme styles so WoodMart cannot
        // turn customer-area text dark again.
        add_action('wp_footer', [__CLASS__, 'customer_area_contrast'], 999);
        add_action('wp_footer', [__CLASS__, 'account_hard_contrast_fix'], 10000);
        add_action('wp_footer', [__CLASS__, 'checkout_hard_contrast_fix'], 10001);
        add_action('wp_footer', [__CLASS__, 'shop_footer_widget_repair'], 10003);
        add_action('wp_footer', [__CLASS__, 'shop_native_filters_ui'], 10004);
        add_action('wp_footer', [__CLASS__, 'shop_sort_select_force_fix'], 10005);
        add_action('wp_head', [__CLASS__, 'global_ui_typography'], 999);
        add_action('wp', [__CLASS__, 'raffle_remove_native_short_description'], 40);
    }


    public static function shop_footer_widget_repair() {
        // Shop presentation now lives exclusively in the RaffleLB Shop plugin.
        // Keep this callback as a compatibility delegate so existing Draw Engine
        // hook registrations and third-party callers remain stable.
        if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
            return RaffleLB_Shop::shop_footer_widget_repair(...func_get_args());
        }

        return;
    }


    public static function global_ui_typography() {
        if (is_admin()) return;
        ?>
        <style id="rafflelb-global-ui-typography-v0285">
        /* =========================================================
           RaffleLB v0.28.5 — GLOBAL SMALL UI TEXT READABILITY
           Only small controls/labels are normalized.
           Main headings/body typography are intentionally untouched.
           ========================================================= */

        /* Header navigation */
        .wd-nav-main > li > a,
        .whb-header .wd-nav-main > li > a{
            font-size:12px!important;
            font-weight:800!important;
            letter-spacing:.02em!important;
            line-height:1.25!important;
            -webkit-font-smoothing:antialiased!important;
            text-rendering:optimizeLegibility!important;
        }

        /* Mobile navigation */
        .mobile-nav .wd-nav-mobile > li > a{
            -webkit-font-smoothing:antialiased!important;
            text-rendering:optimizeLegibility!important;
        }

        /* Marketplace category chips */
        .rl-market-filter-chip{
            font-size:11px!important;
            font-weight:800!important;
            letter-spacing:.055em!important;
            line-height:1.15!important;
            -webkit-font-smoothing:antialiased!important;
            text-rendering:optimizeLegibility!important;
        }

        /* Live raffle / archive cards */
        .rlp275-category,
        .rlp275-price small,
        .rlp275-progress-head,
        .rlp275-progress-foot,
        .rlp275-button,
        .rlp270-live,
        .rlp270-category,
        .rlp270-viewall,
        .rl-view-raffle,
        .rl-archive-card .product-category,
        .rafflelb-raffle-archive .product-grid-item .product-category{
            font-size:10px!important;
            font-weight:800!important;
            letter-spacing:.045em!important;
            line-height:1.2!important;
            -webkit-font-smoothing:antialiased!important;
            text-rendering:optimizeLegibility!important;
        }

        /* Small raffle stats */
        .rlp275-progress-head,
        .rlp275-progress-foot,
        .rlp270-stats small,
        .rlp270-progress-label,
        .rafflelb-raffle-archive .rl-archive-progress,
        .rafflelb-raffle-archive .rl-archive-progress *{
            -webkit-font-smoothing:antialiased!important;
            text-rendering:optimizeLegibility!important;
        }

        /* Generic buttons in customer-facing WooCommerce areas */
        body.woocommerce-page .button,
        body.woocommerce-page button,
        body.woocommerce-page input[type="submit"],
        body.woocommerce-account .button,
        body.woocommerce-checkout .button{
            font-weight:800!important;
            letter-spacing:.025em!important;
            -webkit-font-smoothing:antialiased!important;
            text-rendering:optimizeLegibility!important;
        }

        /* Form labels and helper text — keep them readable without enlarging
           normal paragraph text across the site. */
        body.woocommerce-page form label,
        body.woocommerce-account form label,
        body.woocommerce-checkout form label,
        .woocommerce-form__label,
        .form-row label{
            font-size:max(11px, .72rem)!important;
            font-weight:700!important;
            letter-spacing:.01em!important;
            -webkit-font-smoothing:antialiased!important;
            text-rendering:optimizeLegibility!important;
        }

        /* Footer small navigation / meta text */
        .wd-footer a,
        .wd-footer .widget-title,
        .footer-container a,
        .footer-container .widget-title{
            -webkit-font-smoothing:antialiased!important;
            text-rendering:optimizeLegibility!important;
        }

        /* Very small screens: never push category filters back down to tiny text. */
        @media(max-width:767px){
            .rl-market-filter-chip{
                font-size:10px!important;
                letter-spacing:.035em!important;
            }

            .rlp275-progress-head,
            .rlp275-progress-foot{
                font-size:8.5px!important;
                letter-spacing:.02em!important;
            }

            .rlp275-button,
            .rlp270-viewall{
                font-size:10px!important;
            }
        }
        </style>
        <?php
    }

    public static function raffle_category_legacy_redirect() {
        // Shop presentation now lives exclusively in the RaffleLB Shop plugin.
        // Keep this callback as a compatibility delegate so existing Draw Engine
        // hook registrations and third-party callers remain stable.
        if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
            return RaffleLB_Shop::raffle_category_legacy_redirect(...func_get_args());
        }

        return;
    }

    public static function admin_hero_image_media($hook) {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) return;
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== 'product') return;
        wp_enqueue_media();
    }

    public static function product_fields() {
        echo '<div class="options_group rafflelb-purchase-options">';
        echo '<p style="margin:12px 12px 6px;padding-top:12px;border-top:1px solid #ddd"><strong>RaffleLB Purchase Options</strong></p>';
        echo '<p class="form-field" style="margin-top:0;color:#666">The WooCommerce <strong>Regular price</strong> above is the raffle <strong>entry price</strong>.</p>';

        woocommerce_wp_checkbox([
            'id'=>self::META_ENABLED,
            'label'=>'Enable Enter Raffle',
            'description'=>'Enable the paid raffle-entry option and generate one unique entry per paid quantity.'
        ]);

        woocommerce_wp_text_input([
            'id'=>self::META_TOTAL,
            'label'=>'Total raffle entries',
            'type'=>'number',
            'custom_attributes'=>['min'=>'1','step'=>'1']
        ]);

        echo '<p style="margin:14px 12px 5px;padding-top:12px;border-top:1px solid #ddd"><strong>Buy It Now — Direct E-commerce Sale</strong></p>';

        woocommerce_wp_checkbox([
            'id'=>self::META_BUY_NOW_ENABLED,
            'label'=>'Enable Buy It Now',
            'description'=>'Show a separate direct-purchase option. This is a normal e-commerce purchase and does not generate raffle entries.'
        ]);

        woocommerce_wp_text_input([
            'id'=>self::META_BUY_NOW_PRICE,
            'label'=>'Buy It Now price ($)',
            'type'=>'number',
            'data_type'=>'price',
            'desc_tip'=>true,
            'description'=>'Full selling price for direct purchase.',
            'custom_attributes'=>['min'=>'0','step'=>'0.01']
        ]);

        echo '<p class="form-field" style="color:#666"><em>Buy It Now and Enter Raffle remain separate in the cart, checkout, order metadata, and raffle-entry generation.</em></p>';

        $hero_image_id = absint(get_post_meta(get_the_ID(), self::META_HERO_IMAGE, true));
        $hero_image_url = $hero_image_id ? wp_get_attachment_image_url($hero_image_id, 'medium') : '';
        echo '<p style="margin:14px 12px 5px;padding-top:12px;border-top:1px solid #ddd"><strong>Homepage Hero</strong></p>';
        echo '<p class="form-field rafflelb-hero-image-field">';
        echo '<label>Homepage Hero Image</label>';
        echo '<span class="description" style="display:block;margin:0 0 9px 150px;max-width:540px">Upload a dedicated transparent PNG/WebP cutout for products tagged <strong>homepage-hero</strong>. This image is used only in the homepage hero and does not replace the normal WooCommerce product image.</span>';
        echo '<input type="hidden" id="'.esc_attr(self::META_HERO_IMAGE).'" name="'.esc_attr(self::META_HERO_IMAGE).'" value="'.esc_attr($hero_image_id).'">';
        echo '<span id="rafflelb-hero-image-preview" style="display:inline-block;margin-left:150px;vertical-align:middle">';
        if ($hero_image_url) echo '<img src="'.esc_url($hero_image_url).'" style="display:block;max-width:150px;max-height:150px;padding:8px;border:1px solid #dcdcde;background:#fff;margin-bottom:8px">';
        echo '</span>';
        echo '<span style="display:inline-block;margin-left:150px">';
        echo '<button type="button" class="button" id="rafflelb-hero-image-upload">'.($hero_image_id ? 'Change Hero Image' : 'Upload Hero Image').'</button> ';
        echo '<button type="button" class="button" id="rafflelb-hero-image-remove" '.($hero_image_id ? '' : 'style="display:none"').'>Remove</button>';
        echo '</span>';
        echo '</p>';
        ?>
        <script>
        jQuery(function($){
            var frame;
            $('#rafflelb-hero-image-upload').on('click', function(e){
                e.preventDefault();
                if(frame){ frame.open(); return; }
                frame = wp.media({
                    title: 'Choose Homepage Hero Image',
                    button: { text: 'Use as Hero Image' },
                    library: { type: 'image' },
                    multiple: false
                });
                frame.on('select', function(){
                    var a = frame.state().get('selection').first().toJSON();
                    $('#<?php echo esc_js(self::META_HERO_IMAGE); ?>').val(a.id);
                    var url = (a.sizes && a.sizes.medium) ? a.sizes.medium.url : a.url;
                    $('#rafflelb-hero-image-preview').html('<img src="'+url+'" style="display:block;max-width:150px;max-height:150px;padding:8px;border:1px solid #dcdcde;background:#fff;margin-bottom:8px">');
                    $('#rafflelb-hero-image-upload').text('Change Hero Image');
                    $('#rafflelb-hero-image-remove').show();
                });
                frame.open();
            });
            $('#rafflelb-hero-image-remove').on('click', function(e){
                e.preventDefault();
                $('#<?php echo esc_js(self::META_HERO_IMAGE); ?>').val('');
                $('#rafflelb-hero-image-preview').empty();
                $('#rafflelb-hero-image-upload').text('Upload Hero Image');
                $(this).hide();
            });
        });
        </script>
        <?php
        echo '</div>';
    }

    public static function save_product_fields($id) {
        update_post_meta($id, self::META_ENABLED, isset($_POST[self::META_ENABLED]) ? 'yes':'no');
        if (isset($_POST[self::META_TOTAL])) {
            $n = absint(wp_unslash($_POST[self::META_TOTAL]));
            $n ? update_post_meta($id,self::META_TOTAL,$n) : delete_post_meta($id,self::META_TOTAL);
        }

        update_post_meta(
            $id,
            self::META_BUY_NOW_ENABLED,
            isset($_POST[self::META_BUY_NOW_ENABLED]) ? 'yes' : 'no'
        );

        if (isset($_POST[self::META_BUY_NOW_PRICE])) {
            $price = wc_format_decimal(wp_unslash($_POST[self::META_BUY_NOW_PRICE]));
            if ($price !== '' && (float)$price > 0) {
                update_post_meta($id, self::META_BUY_NOW_PRICE, $price);
            } else {
                delete_post_meta($id, self::META_BUY_NOW_PRICE);
            }
        }

        if (isset($_POST[self::META_HERO_IMAGE])) {
            $hero_image_id = absint(wp_unslash($_POST[self::META_HERO_IMAGE]));
            if ($hero_image_id && wp_attachment_is_image($hero_image_id)) {
                update_post_meta($id, self::META_HERO_IMAGE, $hero_image_id);
            } else {
                delete_post_meta($id, self::META_HERO_IMAGE);
            }
        } else {
            delete_post_meta($id, self::META_HERO_IMAGE);
        }
    }

    private static function draw_id($product) {
        if (!$product instanceof WC_Product) return 0;
        $id = $product->get_id();
        if ('yes' === get_post_meta($id,self::META_ENABLED,true)) return $id;
        if ($product->is_type('variation')) {
            $p = $product->get_parent_id();
            if ('yes' === get_post_meta($p,self::META_ENABLED,true)) return $p;
        }
        return 0;
    }

    public static function cleanup_expired_holds() {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}" . self::HOLD_TABLE . " WHERE expires_at < %s",
            current_time('mysql')
        ));
    }

    private static function token() {
        if (!function_exists('WC') || !WC()->session) return '';
        $t = WC()->session->get('rafflelb_hold_token');
        if (!$t) {
            $t = wp_generate_uuid4();
            WC()->session->set('rafflelb_hold_token', $t);
        }
        return sanitize_text_field($t);
    }

    private static function release_token($token) {
        if (!$token) return;
        global $wpdb;
        $wpdb->delete($wpdb->prefix.self::HOLD_TABLE, ['hold_token'=>$token], ['%s']);
    }

    public static function release_current_hold($unused = null) {
        if (!function_exists('WC') || !WC()->session) return;
        $t = WC()->session->get('rafflelb_hold_token');
        if ($t) self::release_token($t);
        WC()->session->__unset('rafflelb_hold_started_at');
    }

    private static function claimed($pid) {
        global $wpdb;
        return (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}" . self::ENTRY_TABLE . " WHERE product_id=%d AND status='active'",
            $pid
        ));
    }

    private static function held($pid, $exclude='') {
        self::cleanup_expired_holds();
        global $wpdb;
        $table = $wpdb->prefix.self::HOLD_TABLE;
        if ($exclude) {
            return (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(quantity),0) FROM {$table}
                 WHERE product_id=%d AND hold_token<>%s AND expires_at>=%s",
                $pid,$exclude,current_time('mysql')
            ));
        }
        return (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(quantity),0) FROM {$table}
             WHERE product_id=%d AND expires_at>=%s",
            $pid,current_time('mysql')
        ));
    }


    private static function draw_status($pid) {
        $status = (string) get_post_meta($pid, self::META_DRAW_STATUS, true);
        if (in_array($status, ['ready_to_draw','winner_selected'], true)) return $status;

        $total = absint(get_post_meta($pid, self::META_TOTAL, true));
        if ($total > 0 && self::claimed($pid) >= $total) {
            self::mark_ready_to_draw($pid);
            return 'ready_to_draw';
        }
        return 'live';
    }

    private static function mark_ready_to_draw($pid) {
        $total = absint(get_post_meta($pid, self::META_TOTAL, true));
        if ($total < 1 || self::claimed($pid) < $total) return false;

        $current_status = (string) get_post_meta($pid, self::META_DRAW_STATUS, true);
        if ($current_status === 'winner_selected') return true;

        if ($current_status !== 'ready_to_draw') {
            update_post_meta($pid, self::META_DRAW_STATUS, 'ready_to_draw');
            update_post_meta($pid, self::META_CLOSED_AT, current_time('mysql'));
        }

        // Once full, no temporary reservation should remain for this reward.
        global $wpdb;
        $wpdb->delete(
            $wpdb->prefix . self::HOLD_TABLE,
            ['product_id' => $pid],
            ['%d']
        );

        return true;
    }

    private static function stats($pid, $exclude_own=false) {
        $total = absint(get_post_meta($pid,self::META_TOTAL,true));
        if ($total < 1) return false;
        $claimed = self::claimed($pid);
        $token = $exclude_own ? self::token() : '';
        $held = self::held($pid,$token);
        $left = max(0,$total-$claimed);
        $available = max(0,$left-$held);
        $percent = min(100,round(($claimed/$total)*100));
        $status = self::draw_status($pid);
        if (in_array($status, ['ready_to_draw','winner_selected'], true)) {
            $left = 0;
            $available = 0;
        }
        $product_id = $pid;
        return compact('total','claimed','held','left','available','percent','status','product_id');
    }


    private static function buy_now_enabled($product) {
        if (!$product instanceof WC_Product) return false;
        $pid = self::draw_id($product);
        if (!$pid) return false;

        $enabled = get_post_meta($pid, self::META_BUY_NOW_ENABLED, true) === 'yes';
        $price   = (float) get_post_meta($pid, self::META_BUY_NOW_PRICE, true);

        return $enabled && $price > 0;
    }

    /**
     * True once this product's raffle has a permanently recorded winner.
     * Used to hide both the "Buy this product" panel and the raffle entry
     * form/CTA on the single product page, leaving only the winner result.
     */
    private static function is_draw_closed($product) {
        if (!$product instanceof WC_Product) return false;
        $pid = self::draw_id($product);
        if (!$pid) return false;
        return self::draw_status($pid) === 'winner_selected';
    }

    private static function buy_now_price($product) {
        if (!$product instanceof WC_Product) return 0.0;
        $pid = self::draw_id($product);
        if (!$pid) return 0.0;
        return max(0, (float) get_post_meta($pid, self::META_BUY_NOW_PRICE, true));
    }

    private static function requested_purchase_mode() {
        $mode = isset($_REQUEST['rafflelb_purchase_mode'])
            ? sanitize_key(wp_unslash($_REQUEST['rafflelb_purchase_mode']))
            : 'raffle_entry';

        return $mode === 'buy_now' ? 'buy_now' : 'raffle_entry';
    }

    private static function cart_item_purchase_mode($item) {
        if (is_array($item) && !empty($item[self::ITEM_MODE_META])) {
            return $item[self::ITEM_MODE_META] === 'buy_now' ? 'buy_now' : 'raffle_entry';
        }
        return 'raffle_entry';
    }

    private static function order_item_purchase_mode($item) {
        if (!$item instanceof WC_Order_Item_Product) return 'raffle_entry';
        $mode = (string) $item->get_meta(self::ITEM_MODE_META, true);
        return $mode === 'buy_now' ? 'buy_now' : 'raffle_entry';
    }

    public static function current_cart_purchase_mode() {
        if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) return '';

        $mode = '';
        foreach (WC()->cart->get_cart() as $item) {
            if (empty($item['data']) || !$item['data'] instanceof WC_Product) continue;
            if (!self::draw_id($item['data'])) continue;

            $item_mode = self::cart_item_purchase_mode($item);
            if ($mode === '') $mode = $item_mode;
            if ($mode !== $item_mode) return 'mixed';
        }
        return $mode;
    }

    public static function purchase_mode_cart_item_data($cart_item_data, $product_id, $variation_id, $quantity) {
        $product = wc_get_product($variation_id ?: $product_id);
        if (!$product || !self::draw_id($product)) return $cart_item_data;

        $mode = self::requested_purchase_mode();
        if ($mode === 'buy_now' && self::buy_now_enabled($product)) {
            $cart_item_data[self::ITEM_MODE_META] = 'buy_now';
            $cart_item_data['_rafflelb_direct_price'] = self::buy_now_price($product);
            // Keeps direct-purchase and raffle-entry lines distinct if the same product is used.
            $cart_item_data['_rafflelb_mode_key'] = 'buy_now';
        } else {
            $cart_item_data[self::ITEM_MODE_META] = 'raffle_entry';
            $cart_item_data['_rafflelb_mode_key'] = 'raffle_entry';
        }

        return $cart_item_data;
    }

    public static function apply_purchase_mode_prices($cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;
        if (!$cart instanceof WC_Cart) return;

        foreach ($cart->get_cart() as $cart_item) {
            if (empty($cart_item['data']) || !$cart_item['data'] instanceof WC_Product) continue;
            if (!self::draw_id($cart_item['data'])) continue;
            if (self::cart_item_purchase_mode($cart_item) !== 'buy_now') continue;

            $price = isset($cart_item['_rafflelb_direct_price'])
                ? (float) $cart_item['_rafflelb_direct_price']
                : self::buy_now_price($cart_item['data']);

            if ($price > 0) {
                $cart_item['data']->set_price($price);
            }
        }
    }

    public static function display_purchase_mode_cart_data($item_data, $cart_item) {
        if (empty($cart_item['data']) || !$cart_item['data'] instanceof WC_Product) return $item_data;
        if (!self::draw_id($cart_item['data'])) return $item_data;

        $mode = self::cart_item_purchase_mode($cart_item);

        $item_data[] = [
            'key'   => 'Purchase Type',
            'value' => $mode === 'buy_now' ? 'BUY IT NOW — DIRECT PURCHASE' : 'ENTER RAFFLE — PAID ENTRY',
        ];

        return $item_data;
    }

    public static function save_purchase_mode_order_item($item, $cart_item_key, $values, $order) {
        if (!$item instanceof WC_Order_Item_Product) return;
        if (empty($values['data']) || !$values['data'] instanceof WC_Product) return;
        if (!self::draw_id($values['data'])) return;

        $mode = self::cart_item_purchase_mode($values);
        $item->add_meta_data(self::ITEM_MODE_META, $mode, true);
        $item->add_meta_data(
            'Purchase Type',
            $mode === 'buy_now' ? 'Buy It Now — Direct Purchase' : 'Enter Raffle — Paid Entry',
            true
        );
    }

    public static function save_order_purchase_type($order, $data) {
        if (!$order instanceof WC_Order) return;
        $mode = self::current_cart_purchase_mode();

        if ($mode === 'buy_now') {
            $order->update_meta_data('_rafflelb_order_type', 'direct_ecommerce');
            $order->update_meta_data('RaffleLB Order Type', 'Direct E-commerce Purchase');
        } elseif ($mode === 'raffle_entry') {
            $order->update_meta_data('_rafflelb_order_type', 'raffle_entry');
            $order->update_meta_data('RaffleLB Order Type', 'Paid Raffle Entry');
        }
    }

    private static function cart_draw_qty() {
        $out=[];
        if (!function_exists('WC') || !WC()->cart) return $out;
        foreach (WC()->cart->get_cart() as $item) {
            if (empty($item['data']) || !$item['data'] instanceof WC_Product) continue;
            if (self::cart_item_purchase_mode($item) === 'buy_now') continue;
            $pid=self::draw_id($item['data']);
            if (!$pid) continue;
            if (!isset($out[$pid])) $out[$pid]=0;
            $out[$pid]+=max(0,absint($item['quantity']));
        }
        return $out;
    }

    public static function sync_hold_from_cart($unused = null) {
        if (!function_exists('WC') || !WC()->session || !WC()->cart) return;

        // Account-required purchasing policy: a guest must never own or refresh
        // a raffle reservation. Core blocks add-to-cart before this point; this
        // guard also cleans up any legacy guest hold from an older session.
        if (class_exists('\\RaffleLB\\Core\\Access') && !\RaffleLB\Core\Access::can_purchase()) {
            self::release_current_hold();
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . self::HOLD_TABLE;
        $token = self::token();
        $cart  = self::cart_draw_qty();

        // No raffle items = no reservation timer.
        if (empty($cart)) {
            self::release_token($token);
            WC()->session->__unset('rafflelb_hold_started_at');
            return;
        }

        $now = current_time('timestamp');
        $started = absint(WC()->session->get('rafflelb_hold_started_at'));

        // Start the 15-minute clock only once. Refreshing/revisiting does NOT extend it.
        if (!$started) {
            $started = $now;
            WC()->session->set('rafflelb_hold_started_at', $started);
        }

        $expires_ts = $started + (self::HOLD_MINUTES * MINUTE_IN_SECONDS);

        // If already expired, let the expiration handler clear the cart.
        if ($now >= $expires_ts) {
            self::expire_raffle_cart_if_needed();
            return;
        }

        // Remove previous quantities for this session, then rebuild using the SAME expiry.
        self::release_token($token);

        foreach ($cart as $pid => $qty) {
            $s = self::stats($pid, true);
            if (!$s) continue;

            $reserve = min($qty, $s['available']);
            if ($reserve < 1) continue;

            $wpdb->insert($table, [
                'hold_token' => $token,
                'product_id' => $pid,
                'quantity'   => $reserve,
                'expires_at' => date('Y-m-d H:i:s', $expires_ts),
                'created_at' => current_time('mysql'),
            ], ['%s','%d','%d','%s','%s']);
        }
    }

    public static function expire_raffle_cart_if_needed($unused = null) {
        if (!function_exists('WC') || !WC()->session || !WC()->cart) return;

        $started = absint(WC()->session->get('rafflelb_hold_started_at'));
        if (!$started) return;

        $now = current_time('timestamp');
        $expires_ts = $started + (self::HOLD_MINUTES * MINUTE_IN_SECONDS);

        if ($now < $expires_ts) return;

        $removed = false;

        foreach (WC()->cart->get_cart() as $cart_key => $item) {
            if (empty($item['data']) || !$item['data'] instanceof WC_Product) continue;

            if (self::draw_id($item['data']) && self::cart_item_purchase_mode($item) !== 'buy_now') {
                WC()->cart->remove_cart_item($cart_key);
                $removed = true;
            }
        }

        self::release_current_hold();
        WC()->session->__unset('rafflelb_hold_started_at');

        if ($removed) {
            WC()->cart->set_session();

            if (function_exists('wc_add_notice')) {
                wc_add_notice(
                    sprintf(
                        'Your RaffleLB reservation expired after %d minutes. Please add the entries again to start a new reservation.',
                        self::HOLD_MINUTES
                    ),
                    'notice'
                );
            }
        }
    }

    public static function is_purchasable($yes,$product) {
        $pid=self::draw_id($product);
        if (!$pid) return $yes;

        // Once a winner is permanently recorded, this product is no longer
        // purchasable by any route — neither "Buy Direct" nor a raffle entry.
        if (self::draw_status($pid) === 'winner_selected') {
            return false;
        }

        if (self::buy_now_enabled($product) && $product->is_in_stock()) {
            return true;
        }

        $s=self::stats($pid,true); // own cart reservation shouldn't block itself
        return $s && $s['available']>0 ? $yes : false;
    }

    public static function quantity_args($args,$product) {
        $pid=self::draw_id($product);
        if (!$pid) return $args;

        $s=self::stats($pid,true);
        if (!$s) return $args;

        // Product page quantity should include this session's current cart qty capacity.
        $args['min_value']=1;
        $args['step']=1;
        $args['max_value']=max(0,$s['available']);
        return $args;
    }

    public static function add_to_cart_validation($passed,$product_id,$quantity,$variation_id=0,$variations=[]) {
        $product=wc_get_product($variation_id ?: $product_id);
        if (!$product) return $passed;
        $pid=self::draw_id($product);
        if (!$pid) return $passed;

        $requested = self::requested_purchase_mode();
        $cart_mode = self::current_cart_purchase_mode();

        if ($cart_mode && $cart_mode !== 'mixed' && $cart_mode !== $requested) {
            wc_add_notice(
                $requested === 'buy_now'
                    ? 'Buy It Now purchases must be checked out separately from raffle entries.'
                    : 'Raffle entries must be checked out separately from Buy It Now purchases.',
                'error'
            );
            return false;
        }

        if ($requested === 'buy_now') {
            if (!self::buy_now_enabled($product)) {
                wc_add_notice('Buy It Now is not available for this product.', 'error');
                return false;
            }
            if (!$product->is_in_stock()) {
                wc_add_notice('This product is currently unavailable for direct purchase.', 'error');
                return false;
            }
            return $passed;
        }

        $s=self::stats($pid,true);
        if (!$s) return false;

        $existing=0;
        foreach(self::cart_draw_qty() as $id=>$q) if((int)$id===(int)$pid) $existing=$q;

        if ($existing+absint($quantity)>$s['available']) {
            wc_add_notice(sprintf('Only %d RaffleLB entries are available right now.',$s['available']),'error');
            return false;
        }
        return $passed;
    }

    public static function check_cart() {
        foreach(self::cart_draw_qty() as $pid=>$qty) {
            $s=self::stats($pid,true);
            if(!$s || $qty>$s['available']) {
                wc_add_notice(sprintf(
                    'Availability changed for %s. Only %d entries are available.',
                    get_the_title($pid), $s?$s['available']:0
                ),'error');
            }
        }
    }

    public static function generate_entries_for_order($order_id) {
        $order=wc_get_order($order_id);
        if(!$order) return;

        foreach($order->get_items() as $item_id=>$item) {
            $p=$item->get_product();
            if(!$p) continue;
            if (self::order_item_purchase_mode($item) === 'buy_now') continue;
            $pid=self::draw_id($p);
            if(!$pid) continue;

            $total=absint(get_post_meta($pid,self::META_TOTAL,true));
            $qty=max(0,absint($item->get_quantity()));
            $existing=self::count_item_entries($item_id);
            $needed=max(0,$qty-$existing);
            if(!$total || !$needed) continue;

            $claimed=self::claimed($pid);
            if($claimed+$needed>$total) {
                $order->add_order_note('RaffleLB: entry limit protection blocked entry generation.');
                continue;
            }

            $created=self::allocate($order_id,$item_id,$pid,absint($order->get_user_id()),$needed,$total);
            if($created) {
                $order->add_order_note('RaffleLB entries generated: '.implode(', ',array_map(
                    fn($n)=>'#'.str_pad((string)$n,3,'0',STR_PAD_LEFT),$created
                )));

                if (self::mark_ready_to_draw($pid)) {
                    $order->add_order_note('RaffleLB: draw is now CLOSED and Ready to Draw. Final paid entry list is full.');
                    self::complete_paid_orders_for_draw($pid);
                }
            }
        }
    }


    private static function archive_entry_event($entry, $event_type, $reason='') {
        if (!$entry) return false;
        global $wpdb;

        return (bool) $wpdb->insert(
            $wpdb->prefix . self::HISTORY_TABLE,
            [
                'entry_id'      => absint($entry->id),
                'order_id'      => absint($entry->order_id),
                'order_item_id' => absint($entry->order_item_id),
                'product_id'    => absint($entry->product_id),
                'user_id'       => absint($entry->user_id),
                'entry_number'  => absint($entry->entry_number),
                'event_type'    => sanitize_key($event_type),
                'reason'        => sanitize_text_field($reason),
                'event_at'      => current_time('mysql'),
            ],
            ['%d','%d','%d','%d','%d','%d','%s','%s','%s']
        );
    }

    private static function maybe_reopen_draw($pid) {
        $status = (string) get_post_meta($pid, self::META_DRAW_STATUS, true);
        if ($status === 'winner_selected') return false;
        // An administrator's explicit early-close decision is sticky. Voiding
        // an entry after closure must not silently reopen the raffle.
        if (get_post_meta($pid, self::META_EARLY_CLOSED, true) === 'yes') return false;

        $total = absint(get_post_meta($pid, self::META_TOTAL, true));
        if ($total > 0 && self::claimed($pid) < $total) {
            update_post_meta($pid, self::META_DRAW_STATUS, 'live');
            delete_post_meta($pid, self::META_CLOSED_AT);
            return true;
        }
        return false;
    }

    private static function order_is_raffle_only($order) {
        if (!$order instanceof WC_Order) return false;
        $has_raffle = false;

        foreach ($order->get_items('line_item') as $item) {
            $product = $item->get_product();
            if (!$product) return false;
            $pid = self::draw_id($product);
            if (!$pid) return false;
            if (self::order_item_purchase_mode($item) === 'buy_now') return false;
            $has_raffle = true;
        }

        return $has_raffle;
    }

    private static function complete_paid_orders_for_draw($pid) {
        global $wpdb;

        $order_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT order_id
             FROM {$wpdb->prefix}" . self::ENTRY_TABLE . "
             WHERE product_id=%d AND status='active' AND order_id>0",
            $pid
        ));

        foreach ($order_ids as $order_id) {
            $order = wc_get_order(absint($order_id));
            if (!$order) continue;

            // Only paid orders in WooCommerce's internal processing state, and only if every line item is a RaffleLB draw product.
            if ($order->has_status('processing') && $order->is_paid() && self::order_is_raffle_only($order)) {
                $order->update_status(
                    'completed',
                    'RaffleLB: draw reached its full paid-entry limit. Paid raffle order automatically completed.'
                );
            }
        }
    }


    private static function send_voided_order_email($order, $entries, $reason) {
        if (!$order instanceof WC_Order || !$entries) return false;

        $status = $order->get_status();
        if (!in_array($status, ['cancelled', 'refunded'], true)) return false;

        // Prevent accidental duplicate notices for the same terminal action.
        $meta_key = '_rafflelb_' . $status . '_email_sent_at';
        if ($order->get_meta($meta_key, true)) return true;

        $to = sanitize_email($order->get_billing_email());

        if (!$to && $order->get_user_id()) {
            $user = get_user_by('id', $order->get_user_id());
            if ($user) $to = sanitize_email($user->user_email);
        }

        if (!$to || !is_email($to)) {
            $order->add_order_note('RaffleLB: cancellation/refund email was not sent because no valid customer email address was available.');
            return false;
        }

        $customer_name = trim($order->get_formatted_billing_full_name());
        if ($customer_name === '') $customer_name = 'RaffleLB Member';

        // Group affected entry numbers by reward.
        $grouped = [];
        foreach ($entries as $entry) {
            $pid = absint($entry->product_id);
            if (!isset($grouped[$pid])) $grouped[$pid] = [];
            $grouped[$pid][] = absint($entry->entry_number);
        }

        $entry_blocks = '';
        foreach ($grouped as $pid => $numbers) {
            sort($numbers, SORT_NUMERIC);
            $formatted = array_map(static function($n){
                return '#' . str_pad((string)absint($n), 3, '0', STR_PAD_LEFT);
            }, $numbers);

            $entry_blocks .=
                '<div style="margin:0 0 12px;padding:14px 16px;border:1px solid #333b2b;border-radius:10px;background:#171b14">' .
                    '<div style="font-size:11px;color:#8f9887;letter-spacing:.8px;text-transform:uppercase">Raffle</div>' .
                    '<div style="margin-top:3px;color:#ffffff;font-size:15px;font-weight:700">' . esc_html(get_the_title($pid)) . '</div>' .
                    '<div style="margin-top:9px;font-size:11px;color:#8f9887;letter-spacing:.8px;text-transform:uppercase">Affected Entries</div>' .
                    '<div style="margin-top:3px;color:#caff16;font-size:16px;font-weight:800">' . esc_html(implode(', ', $formatted)) . '</div>' .
                '</div>';
        }

        $is_refund = ($status === 'refunded');
        $status_label = $is_refund ? 'REFUNDED' : 'CANCELLED';
        $subject = 'RaffleLB Entry Order #' . $order->get_order_number() . ' ' . ucfirst($status);

        $account_url = function_exists('wc_get_account_endpoint_url')
            ? wc_get_account_endpoint_url('orders')
            : home_url('/my-account/orders/');

        $reason_text = trim((string)$reason);
        if (strpos($reason_text, 'Admin reason: ') === 0) {
            $reason_text = substr($reason_text, strlen('Admin reason: '));
        }
        if ($reason_text === '') {
            $reason_text = 'Order status changed to ' . $status . '.';
        }

        $message =
        '<div style="margin:0;padding:28px 12px;background:#090b08;font-family:Arial,Helvetica,sans-serif;color:#ffffff">' .
          '<div style="max-width:620px;margin:0 auto;border:1px solid #293021;border-radius:16px;background:#10130d;overflow:hidden">' .
            '<div style="padding:22px 24px;border-bottom:1px solid #293021">' .
              '<div style="font-size:20px;font-weight:800;letter-spacing:.5px">RAFFLE<span style="color:#caff16">LB</span></div>' .
            '</div>' .
            '<div style="padding:26px 24px">' .
              '<div style="display:inline-block;padding:7px 10px;border-radius:999px;background:#252b20;color:#caff16;font-size:11px;font-weight:800;letter-spacing:1px">' .
                esc_html($status_label) .
              '</div>' .
              '<h2 style="margin:16px 0 10px;color:#ffffff;font-size:23px">Entry order update</h2>' .
              '<p style="margin:0 0 18px;color:#c7cec0;font-size:14px;line-height:1.65">Hello ' . esc_html($customer_name) . ', your RaffleLB entry order <strong style="color:#ffffff">#' . esc_html($order->get_order_number()) . '</strong> has been ' . esc_html($status) . '.</p>' .
              $entry_blocks .
              '<div style="margin:18px 0;padding:15px 16px;border-left:3px solid #caff16;background:#151912">' .
                '<div style="font-size:11px;color:#8f9887;letter-spacing:.8px;text-transform:uppercase">Reason</div>' .
                '<div style="margin-top:5px;color:#ffffff;font-size:14px;line-height:1.55">' . nl2br(esc_html($reason_text)) . '</div>' .
              '</div>' .
              '<p style="margin:0 0 20px;color:#c7cec0;font-size:14px;line-height:1.65">The affected entry numbers are no longer valid for this draw and have been removed from the active entry pool.</p>' .
              ($is_refund
                ? '<p style="margin:0 0 20px;color:#c7cec0;font-size:13px;line-height:1.6"><strong style="color:#ffffff">Refund note:</strong> This message confirms the RaffleLB order status. Any actual payment return depends on the payment method and refund processing used for the order.</p>'
                : '') .
              '<a href="' . esc_url($account_url) . '" style="display:inline-block;padding:13px 18px;border-radius:9px;background:#caff16;color:#080908;text-decoration:none;font-weight:800;font-size:13px">VIEW ENTRY ORDERS</a>' .
            '</div>' .
          '</div>' .
        '</div>';

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: RaffleLB <notifications@rafflelb.com>',
        ];
        $sent = wp_mail($to, $subject, $message, $headers);

        if ($sent) {
            $order->update_meta_data($meta_key, current_time('mysql'));
            $order->save();
            $order->add_order_note(
                'RaffleLB: customer ' . strtolower($status_label) .
                ' notification email sent to ' . $to . '.'
            );
        } else {
            $order->add_order_note(
                'RaffleLB: customer ' . strtolower($status_label) .
                ' notification email could not be sent.'
            );
        }

        return (bool)$sent;
    }


    public static function void_entries_for_order($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;

        global $wpdb;
        $table = $wpdb->prefix . self::ENTRY_TABLE;

        $entries = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE order_id=%d AND status='active'
             ORDER BY id ASC",
            $order_id
        ));

        if (!$entries) return;

        $affected_products = [];
        $blocked_products = [];

        // Admin actions from RaffleLB Entries store the exact reason here
        // before WooCommerce changes the order status. Direct/manual status
        // changes in WooCommerce still use the safe generic fallback.
        $admin_void_reason = trim((string) $order->get_meta('_rafflelb_pending_void_reason', true));
        $admin_void_action = trim((string) $order->get_meta('_rafflelb_pending_void_action', true));

        $audit_reason = $admin_void_reason !== ''
            ? 'Admin reason: ' . $admin_void_reason
            : 'Order #' . absint($order_id) . ' changed to ' . $order->get_status();

        foreach ($entries as $entry) {
            $pid = absint($entry->product_id);
            $draw_status = (string) get_post_meta($pid, self::META_DRAW_STATUS, true);

            // Never rewrite a completed historical draw automatically.
            if ($draw_status === 'winner_selected' || self::get_draw_result($pid)) {
                $blocked_products[$pid] = true;
                continue;
            }

            self::archive_entry_event(
                $entry,
                'void',
                $audit_reason
            );

            $wpdb->update(
                $table,
                ['status' => 'void'],
                ['id' => absint($entry->id)],
                ['%s'],
                ['%d']
            );

            $affected_products[$pid] = true;
        }

        if ($affected_products) {
            $numbers = [];
            foreach ($entries as $entry) {
                if (isset($affected_products[absint($entry->product_id)])) {
                    $numbers[] = '#' . str_pad((string)absint($entry->entry_number), 3, '0', STR_PAD_LEFT);
                }
            }

            $order->add_order_note(
                'RaffleLB: entries voided because order status changed to ' .
                strtoupper($order->get_status()) . ': ' . implode(', ', $numbers)
            );

            foreach (array_keys($affected_products) as $pid) {
                if (self::maybe_reopen_draw($pid)) {
                    $order->add_order_note(
                        'RaffleLB: ' . get_the_title($pid) .
                        ' reopened because voided entries made slots available again.'
                    );
                }
            }
        }

        if ($blocked_products) {
            foreach (array_keys($blocked_products) as $pid) {
                $order->add_order_note(
                    'RaffleLB: automatic entry voiding was BLOCKED for ' . get_the_title($pid) .
                    ' because a winner has already been permanently selected. Manual review required.'
                );
            }
        }

        // Notify the customer only when entries were actually voided and the
        // order ended as Cancelled or Refunded. Failed orders remain silent.
        if ($affected_products && in_array($order->get_status(), ['cancelled','refunded'], true)) {
            self::send_voided_order_email($order, $entries, $audit_reason);
        }

        // One-time handoff data: remove after the status hook has archived it.
        if ($admin_void_reason !== '' || $admin_void_action !== '') {
            $order->delete_meta_data('_rafflelb_pending_void_reason');
            $order->delete_meta_data('_rafflelb_pending_void_action');
            $order->save();
        }
    }

    private static function count_item_entries($item_id) {
        global $wpdb;
        return (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}".self::ENTRY_TABLE." WHERE order_item_id=%d AND status='active'",
            $item_id
        ));
    }

    private static function allocate($order_id,$item_id,$pid,$uid,$needed,$total) {
        global $wpdb;
        $table=$wpdb->prefix.self::ENTRY_TABLE;
        $made=[];

        for($n=1;$n<=$total && count($made)<$needed;$n++) {
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE product_id=%d AND entry_number=%d LIMIT 1",
                $pid, $n
            ));

            if ($existing) {
                if ($existing->status !== 'void') continue;

                // Preserve the old ownership/status permanently before this slot is reused.
                self::archive_entry_event(
                    $existing,
                    'reassigned',
                    'Void slot reassigned to paid order #' . absint($order_id)
                );

                $updated = $wpdb->update(
                    $table,
                    [
                        'order_id'      => absint($order_id),
                        'order_item_id' => absint($item_id),
                        'user_id'       => absint($uid),
                        'status'        => 'active',
                        'created_at'    => current_time('mysql'),
                    ],
                    ['id' => absint($existing->id)],
                    ['%d','%d','%d','%s','%s'],
                    ['%d']
                );

                if ($updated !== false) $made[]=$n;
                continue;
            }

            $ok=$wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$table}
                (order_id,order_item_id,product_id,user_id,entry_number,status,created_at)
                VALUES(%d,%d,%d,%d,%d,%s,%s)",
                $order_id,$item_id,$pid,$uid,$n,'active',current_time('mysql')
            ));
            if((int)$ok===1) $made[]=$n;
        }
        return $made;
    }

    private static function cart_is_raffle_only() {
        if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) {
            return false;
        }

        foreach (WC()->cart->get_cart() as $item) {
            if (empty($item['data']) || !$item['data'] instanceof WC_Product) {
                return false;
            }
            if (!self::draw_id($item['data'])) {
                return false;
            }
        }

        return true;
    }

    public static function checkout_body_class($classes) {
        if (function_exists('is_checkout') && is_checkout() && !is_order_received_page()) {
            $classes[] = 'rafflelb-checkout-page';
        }
        return $classes;
    }

    public static function simplify_raffle_checkout_fields($fields) {
        if (!self::cart_is_raffle_only()) {
            return $fields;
        }

        $remove = [
            'billing_company',
            'billing_address_1',
            'billing_address_2',
            'billing_city',
            'billing_state',
            'billing_postcode',
        ];

        foreach ($remove as $key) {
            if (isset($fields['billing'][$key])) {
                unset($fields['billing'][$key]);
            }
        }

        if (isset($fields['billing']['billing_first_name'])) {
            $fields['billing']['billing_first_name']['label'] = 'First name';
            $fields['billing']['billing_first_name']['priority'] = 10;
        }
        if (isset($fields['billing']['billing_last_name'])) {
            $fields['billing']['billing_last_name']['label'] = 'Last name';
            $fields['billing']['billing_last_name']['priority'] = 20;
        }
        if (isset($fields['billing']['billing_country'])) {
            $fields['billing']['billing_country']['label'] = 'Country / Region';
            $fields['billing']['billing_country']['priority'] = 30;
        }
        if (isset($fields['billing']['billing_phone'])) {
            $fields['billing']['billing_phone']['label'] = 'Phone number';
            $fields['billing']['billing_phone']['priority'] = 40;
        }
        if (isset($fields['billing']['billing_email'])) {
            $fields['billing']['billing_email']['label'] = 'Email address';
            $fields['billing']['billing_email']['priority'] = 50;
        }

        if (isset($fields['order']['order_comments'])) {
            unset($fields['order']['order_comments']);
        }

        return $fields;
    }

    public static function checkout_button_text($text) {
        if (function_exists('is_checkout') && is_checkout() && self::cart_is_raffle_only()) {
            return 'COMPLETE ENTRY';
        }
        return $text;
    }

    public static function checkout_text_replacements($translated, $text, $domain) {
        if (!function_exists('is_checkout') || !is_checkout() || !self::cart_is_raffle_only()) {
            return $translated;
        }

        $map = [
            'Billing details' => 'Entry Details',
            'Billing Details' => 'Entry Details',
            'Your order' => 'Entry Summary',
            'Your Order' => 'Entry Summary',
        ];

        return $map[$translated] ?? $translated;
    }

    public static function checkout_intro() {
        if (!self::cart_is_raffle_only()) {
            return;
        }

        echo '<div class="rafflelb-checkout-intro">
                <span class="rl-checkout-kicker">SECURE ENTRY CHECKOUT</span>
                <h1>Complete your entry</h1>
                <p>Confirm your details and payment. Your unique raffle entries are assigned automatically after successful payment.</p>
                <div class="rl-checkout-trust">● SECURE CHECKOUT &nbsp;&nbsp; ● LIVE ENTRY RESERVATION &nbsp;&nbsp; ● AUTOMATIC ENTRY ASSIGNMENT</div>
              </div>';
    }

    public static function checkout_styles() {
        if (!function_exists('is_checkout') || !is_checkout() || is_order_received_page()) {
            return;
        }
        ?>
        <style id="rafflelb-checkout-engine-minimal-v0259">
        /*
         * v0.25.9
         * Draw Engine no longer controls checkout columns, payment boxes,
         * order-table backgrounds, totals, field styling, or checkout layout.
         * RaffleLB Dark Checkout is the single source of truth for those.
         */
        body.rafflelb-checkout-page{
            background:#0b0c0a;
        }
        .rafflelb-checkout-page .main-page-wrapper,
        .rafflelb-checkout-page .site-content{
            background:#0b0c0a;
        }
        .rafflelb-checkout-page .whb-header,
        .rafflelb-checkout-page .whb-row,
        .rafflelb-checkout-page .whb-general-header,
        .rafflelb-checkout-page .whb-header .container{
            background:#fff !important;
        }
        .rafflelb-checkout-page .site-content{
            padding-top:36px;
            padding-bottom:70px;
        }
        .rafflelb-checkout-page .rafflelb-checkout-intro{
            max-width:1180px;
            margin:0 auto 34px;
            padding:26px 28px;
            border:1px solid #252822;
            border-radius:18px;
            background:linear-gradient(145deg,#151713,#0f100e);
            color:#fff;
        }
        .rafflelb-checkout-page .rl-checkout-kicker{
            display:inline-block;
            margin-bottom:10px;
            color:#caff16;
            font-size:11px;
            font-weight:800;
            letter-spacing:1.5px;
        }
        .rafflelb-checkout-page .rafflelb-checkout-intro h1{
            margin:0 0 8px;
            color:#fff;
            font-size:34px;
            line-height:1.1;
        }
        .rafflelb-checkout-page .rafflelb-checkout-intro p{
            margin:0;
            max-width:760px;
            color:#a6aaa0;
            font-size:14px;
        }
        .rafflelb-checkout-page .rl-checkout-trust{
            margin-top:18px;
            color:#d7d9d2;
            font-size:10px;
            letter-spacing:.7px;
        }
        .rafflelb-checkout-page .woocommerce-form-coupon-toggle{
            max-width:1180px;
            margin:0 auto 22px;
            color:#f4f5f1 !important;
        }
        .rafflelb-checkout-page .woocommerce-form-coupon-toggle .woocommerce-info{
            padding:14px 18px;
            border:1px solid #252822;
            border-radius:12px;
            background:#11120f !important;
            color:#f4f5f1 !important;
        }
        .rafflelb-checkout-page .woocommerce-form-coupon-toggle a{
            color:#caff16 !important;
            font-weight:700;
            text-decoration:none !important;
        }
        .rafflelb-checkout-page form.checkout_coupon{
            max-width:1180px;
            margin:0 auto 24px;
            padding:18px;
            border:1px solid #252822;
            border-radius:14px;
            background:#11120f;
        }
        @media(max-width:900px){
            .rafflelb-checkout-page .rafflelb-checkout-intro{
                margin-left:12px;
                margin-right:12px;
                padding:22px 20px;
            }
            .rafflelb-checkout-page .rafflelb-checkout-intro h1{
                font-size:28px;
            }
        }
        </style>
        <?php
    }



    public static function account_hard_contrast_fix() {
        // Account presentation now lives exclusively in the RaffleLB Account plugin.
        // Keep this callback/function as a compatibility bridge so existing hook
        // identities, priorities, and third-party callers remain stable.
        if (class_exists('RaffleLB_Account', false) && RaffleLB_Account::ready()) {
            return RaffleLB_Account::account_hard_contrast_fix(...func_get_args());
        }

        return;
    }



    public static function checkout_hard_contrast_fix() {
        if (!function_exists('is_checkout') || !is_checkout()) return;
        ?>
        <style id="rafflelb-checkout-hard-contrast-v0189">
            /* Target the ACTUAL RaffleLB checkout body class. */
            html body.rafflelb-checkout-page form.checkout input.input-text,
            html body.rafflelb-checkout-page form.checkout input[type="text"],
            html body.rafflelb-checkout-page form.checkout input[type="email"],
            html body.rafflelb-checkout-page form.checkout input[type="tel"],
            html body.rafflelb-checkout-page form.checkout input[type="password"],
            html body.rafflelb-checkout-page form.checkout textarea {
                background:#171b14 !important;
                background-color:#171b14 !important;
                color:#ffffff !important;
                -webkit-text-fill-color:#ffffff !important;
                caret-color:#caff16 !important;
                border-color:#4b5346 !important;
                box-shadow:none !important;
            }

            html body.rafflelb-checkout-page form.checkout input::placeholder,
            html body.rafflelb-checkout-page form.checkout textarea::placeholder {
                color:#9da398 !important;
                -webkit-text-fill-color:#9da398 !important;
                opacity:1 !important;
            }

            /* Chrome / Android autofill: keep the field dark instead of pale blue. */
            html body.rafflelb-checkout-page form.checkout input.input-text:-webkit-autofill,
            html body.rafflelb-checkout-page form.checkout input.input-text:-webkit-autofill:hover,
            html body.rafflelb-checkout-page form.checkout input.input-text:-webkit-autofill:focus,
            html body.rafflelb-checkout-page form.checkout input[type="text"]:-webkit-autofill,
            html body.rafflelb-checkout-page form.checkout input[type="text"]:-webkit-autofill:hover,
            html body.rafflelb-checkout-page form.checkout input[type="text"]:-webkit-autofill:focus,
            html body.rafflelb-checkout-page form.checkout input[type="email"]:-webkit-autofill,
            html body.rafflelb-checkout-page form.checkout input[type="email"]:-webkit-autofill:hover,
            html body.rafflelb-checkout-page form.checkout input[type="email"]:-webkit-autofill:focus,
            html body.rafflelb-checkout-page form.checkout input[type="tel"]:-webkit-autofill,
            html body.rafflelb-checkout-page form.checkout input[type="tel"]:-webkit-autofill:hover,
            html body.rafflelb-checkout-page form.checkout input[type="tel"]:-webkit-autofill:focus {
                -webkit-text-fill-color:#ffffff !important;
                color:#ffffff !important;
                caret-color:#caff16 !important;
                -webkit-box-shadow:0 0 0 1000px #171b14 inset !important;
                box-shadow:0 0 0 1000px #171b14 inset !important;
                background:#171b14 !important;
                background-color:#171b14 !important;
                border-color:#4b5346 !important;
                transition:background-color 99999s ease-out 0s !important;
            }

            /* Select/country field remains dark too. */
            html body.rafflelb-checkout-page form.checkout select,
            html body.rafflelb-checkout-page form.checkout .select2-selection {
                background:#171b14 !important;
                background-color:#171b14 !important;
                color:#ffffff !important;
                -webkit-text-fill-color:#ffffff !important;
                border-color:#4b5346 !important;
            }
        </style>

        <script id="rafflelb-checkout-hard-contrast-js-v0189">
        (function(){
            function forceRaffleLBCheckoutFields(){
                document.querySelectorAll(
                    'body.rafflelb-checkout-page form.checkout input.input-text,' +
                    'body.rafflelb-checkout-page form.checkout input[type="text"],' +
                    'body.rafflelb-checkout-page form.checkout input[type="email"],' +
                    'body.rafflelb-checkout-page form.checkout input[type="tel"],' +
                    'body.rafflelb-checkout-page form.checkout input[type="password"],' +
                    'body.rafflelb-checkout-page form.checkout textarea'
                ).forEach(function(el){
                    el.style.setProperty('background', '#171b14', 'important');
                    el.style.setProperty('background-color', '#171b14', 'important');
                    el.style.setProperty('color', '#ffffff', 'important');
                    el.style.setProperty('-webkit-text-fill-color', '#ffffff', 'important');
                    el.style.setProperty('caret-color', '#caff16', 'important');
                    el.style.setProperty('border-color', '#4b5346', 'important');
                    el.style.setProperty('box-shadow', 'none', 'important');
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', forceRaffleLBCheckoutFields);
            } else {
                forceRaffleLBCheckoutFields();
            }

            window.addEventListener('load', forceRaffleLBCheckoutFields);
            document.body.addEventListener('updated_checkout', forceRaffleLBCheckoutFields);
            document.addEventListener('input', forceRaffleLBCheckoutFields, true);
            document.addEventListener('change', forceRaffleLBCheckoutFields, true);
            setTimeout(forceRaffleLBCheckoutFields, 300);
            setTimeout(forceRaffleLBCheckoutFields, 1000);
            setTimeout(forceRaffleLBCheckoutFields, 2000);
        })();
        </script>
        <?php
    }



    /* view_order_client_cleanup() removed in v0.33.89: rafflelb_account_view_order_premium()
       now fully replaces the View Order endpoint's own output with custom markup that
       never includes an "Order again" button and titles its own address section
       "Entry Contact Details" directly, so this DOM-patching cleanup has nothing
       left to do. See rafflelb_account_view_order_premium() below. */


    private static function get_draw_result($pid) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}" . self::RESULT_TABLE . " WHERE product_id=%d LIMIT 1",
            $pid
        ));
    }


    private static function winner_display_name($result) {
        if (!$result) return '';

        $order = wc_get_order(absint($result->order_id));
        if ($order) {
            $name = trim($order->get_formatted_billing_full_name());
            if ($name) return wp_strip_all_tags($name);
        }

        if (!empty($result->user_id)) {
            $user = get_user_by('id', absint($result->user_id));
            if ($user) return $user->display_name;
        }

        return 'Winner';
    }

    /**
     * Public-facing winner name: full first name, family name reduced to
     * its first letter + asterisks (e.g. "Paul H***"). Used everywhere a
     * winner name is shown to visitors other than the winner themselves
     * (homepage, /winners/, product-page result panel). The winner's own
     * notification email still uses the real full name via
     * winner_display_name() directly - masking there would be pointless
     * since it's addressed to them.
     */
    private static function winner_masked_name($result) {
        $winner_name = trim(wp_strip_all_tags(self::winner_display_name($result)));
        if ($winner_name === '' || strtolower($winner_name) === 'winner') return 'Winner';

        $parts = preg_split('/\s+/', $winner_name);
        $first = isset($parts[0]) ? $parts[0] : '';
        $last  = count($parts) > 1 ? $parts[count($parts) - 1] : '';
        if ($first === '') return 'Winner';

        $masked = $first;
        if ($last !== '') {
            $last_initial = function_exists('mb_substr') ? mb_substr($last, 0, 1) : substr($last, 0, 1);
            $masked .= ' ' . strtoupper($last_initial) . '***';
        }
        return $masked;
    }


    private static function winner_email_address($result) {
        if (!$result) return '';

        $order = wc_get_order(absint($result->order_id));
        if ($order && is_email($order->get_billing_email())) {
            return $order->get_billing_email();
        }

        if (!empty($result->user_id)) {
            $user = get_user_by('id', absint($result->user_id));
            if ($user && is_email($user->user_email)) return $user->user_email;
        }

        return '';
    }

    private static function send_winner_email($result) {
        if (!$result) return false;

        $to = self::winner_email_address($result);
        if (!$to) return false;

        $winner_name = self::winner_display_name($result);
        $reward = get_the_title(absint($result->product_id));
        $entry = '#' . str_pad((string)absint($result->entry_number), 3, '0', STR_PAD_LEFT);
        $account_url = wc_get_account_endpoint_url('rafflelb-entries');

        $subject = 'You won ' . $reward . ' - RaffleLB';

        $message = '
        <div style="margin:0;padding:30px;background:#0b0d09;font-family:Arial,Helvetica,sans-serif;color:#ffffff">
          <div style="max-width:620px;margin:0 auto;border:1px solid #2c3227;border-radius:18px;background:#12150f;overflow:hidden">
            <div style="padding:28px 30px;border-bottom:1px solid #2c3227">
              <div style="font-size:12px;font-weight:800;letter-spacing:2px;color:#caff16">RAFFLELB WINNER</div>
              <h1 style="margin:10px 0 0;font-size:30px;line-height:1.1;color:#ffffff">Congratulations, '.esc_html($winner_name).'!</h1>
            </div>
            <div style="padding:28px 30px">
              <p style="margin:0 0 18px;color:#c7cec0;font-size:15px;line-height:1.6">Your entry was selected as the winning entry for <strong style="color:#ffffff">'.esc_html($reward).'</strong>.</p>
              <div style="display:block;padding:18px;border:1px solid #3c452e;border-radius:12px;background:#171b14;margin-bottom:18px">
                <div style="font-size:11px;color:#8f9887;letter-spacing:1px">WINNING ENTRY</div>
                <div style="margin-top:5px;font-size:28px;font-weight:800;color:#caff16">'.esc_html($entry).'</div>
              </div>
              <p style="margin:0 0 22px;color:#c7cec0;font-size:14px;line-height:1.6">Your result has been permanently recorded in your RaffleLB account. Our team will contact you regarding prize fulfillment.</p>
              <a href="'.esc_url($account_url).'" style="display:inline-block;padding:14px 20px;border-radius:10px;background:#caff16;color:#080908;text-decoration:none;font-weight:800;font-size:13px">VIEW MY WINNING ENTRY</a>
            </div>
          </div>
        </div>';

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: RaffleLB <notifications@rafflelb.com>',
        ];
        $sent = wp_mail($to, $subject, $message, $headers);

        if ($sent) {
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . self::RESULT_TABLE,
                ['winner_email_sent_at' => current_time('mysql')],
                ['id' => absint($result->id)],
                ['%s'],
                ['%d']
            );
        }

        return (bool) $sent;
    }

    // Lets a trusted admin screen (e.g. RaffleLB Raffle Manager) send admins
    // back to itself after an action instead of the built-in Entries page.
    // Restricted to admin.php on this install so it can never become an
    // open redirect.
    private static function redirect_target($default) {
        if (!empty($_POST['rafflelb_redirect_to'])) {
            $target = esc_url_raw(wp_unslash($_POST['rafflelb_redirect_to']));
            if ($target && strpos($target, admin_url('admin.php')) === 0) {
                return $target;
            }
        }
        return $default;
    }

    private static function fulfillment_label($status) {
        $labels = [
            'pending'   => 'PENDING',
            'contacted' => 'WINNER CONTACTED',
            'claimed'   => 'PRIZE CLAIMED',
            'fulfilled' => 'PRIZE FULFILLED',
        ];
        return isset($labels[$status]) ? $labels[$status] : 'PENDING';
    }

    public static function handle_send_winner_email() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('You are not allowed to send winner notifications.');
        }

        $result_id = isset($_POST['result_id']) ? absint($_POST['result_id']) : 0;
        check_admin_referer('rafflelb_send_winner_email_' . $result_id);

        global $wpdb;
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}" . self::RESULT_TABLE . " WHERE id=%d LIMIT 1",
            $result_id
        ));

        $redirect = self::redirect_target(admin_url('admin.php?page=rafflelb-entries'));
        if (!$result) {
            wp_safe_redirect(add_query_arg('rafflelb_draw', 'email_result_missing', $redirect));
            exit;
        }

        $sent = self::send_winner_email($result);
        wp_safe_redirect(add_query_arg('rafflelb_draw', $sent ? 'email_sent' : 'email_failed', $redirect));
        exit;
    }

    public static function handle_update_fulfillment() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('You are not allowed to update prize fulfillment.');
        }

        $result_id = isset($_POST['result_id']) ? absint($_POST['result_id']) : 0;
        check_admin_referer('rafflelb_update_fulfillment_' . $result_id);

        $allowed = ['pending','contacted','claimed','fulfilled'];
        $status = isset($_POST['fulfillment_status'])
            ? sanitize_key(wp_unslash($_POST['fulfillment_status']))
            : 'pending';
        if (!in_array($status, $allowed, true)) $status = 'pending';

        $note = isset($_POST['fulfillment_note'])
            ? sanitize_textarea_field(wp_unslash($_POST['fulfillment_note']))
            : '';

        global $wpdb;
        $table = $wpdb->prefix . self::RESULT_TABLE;
        $current = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d LIMIT 1", $result_id));

        $redirect = self::redirect_target(admin_url('admin.php?page=rafflelb-entries'));
        if (!$current) {
            wp_safe_redirect(add_query_arg('rafflelb_draw', 'fulfillment_missing', $redirect));
            exit;
        }

        $data = [
            'fulfillment_status' => $status,
            'fulfillment_note'   => $note,
        ];
        $formats = ['%s','%s'];
        $now = current_time('mysql');

        if ($status === 'contacted' && empty($current->contacted_at)) {
            $data['contacted_at'] = $now;
            $formats[] = '%s';
        }
        if ($status === 'claimed' && empty($current->claimed_at)) {
            $data['claimed_at'] = $now;
            $formats[] = '%s';
        }
        if ($status === 'fulfilled' && empty($current->fulfilled_at)) {
            $data['fulfilled_at'] = $now;
            $formats[] = '%s';
        }

        $wpdb->update($table, $data, ['id' => $result_id], $formats, ['%d']);

        $order = wc_get_order(absint($current->order_id));
        if ($order) {
            $order->add_order_note(
                'RaffleLB prize fulfillment updated to ' . self::fulfillment_label($status) .
                ($note ? '. Note: ' . $note : '')
            );
        }

        wp_safe_redirect(add_query_arg('rafflelb_draw', 'fulfillment_saved', $redirect));
        exit;
    }

    public static function handle_close_raffle_early() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('You are not allowed to close RaffleLB raffles.');
        }

        $pid = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $reason = isset($_POST['close_reason']) ? sanitize_textarea_field(wp_unslash($_POST['close_reason'])) : '';
        $redirect = self::redirect_target(admin_url('admin.php?page=rafflelb-entries'));

        if (!$pid) {
            wp_safe_redirect(add_query_arg('rafflelb_draw', 'invalid', $redirect));
            exit;
        }

        check_admin_referer('rafflelb_close_raffle_early_' . $pid);

        if ($reason === '') {
            wp_safe_redirect(add_query_arg('rafflelb_draw', 'early_reason_required', $redirect));
            exit;
        }

        if (self::get_draw_result($pid) || get_post_meta($pid, self::META_DRAW_STATUS, true) === 'winner_selected') {
            wp_safe_redirect(add_query_arg('rafflelb_draw', 'already_selected', $redirect));
            exit;
        }

        $total = absint(get_post_meta($pid, self::META_TOTAL, true));
        $claimed = self::claimed($pid);
        $status = self::draw_status($pid);

        if ($status !== 'live' || $total < 1 || $claimed < 1 || $claimed >= $total) {
            wp_safe_redirect(add_query_arg('rafflelb_draw', 'early_not_available', $redirect));
            exit;
        }

        $closed_at = current_time('mysql');
        $admin_id = get_current_user_id();

        update_post_meta($pid, self::META_DRAW_STATUS, 'ready_to_draw');
        update_post_meta($pid, self::META_CLOSED_AT, $closed_at);
        update_post_meta($pid, self::META_EARLY_CLOSED, 'yes');
        update_post_meta($pid, self::META_EARLY_CLOSE_REASON, $reason);
        update_post_meta($pid, self::META_EARLY_CLOSED_BY, $admin_id);
        update_post_meta($pid, self::META_EARLY_CLOSE_CLAIMED, $claimed);

        // Closing early must immediately remove every temporary reservation so
        // no pending cart can consume another raffle slot after closure.
        global $wpdb;
        $wpdb->delete(
            $wpdb->prefix . self::HOLD_TABLE,
            ['product_id' => $pid],
            ['%d']
        );

        // Add the closure record to every currently eligible paid order so the
        // audit trail is visible from both the raffle admin and WooCommerce.
        $order_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT order_id FROM {$wpdb->prefix}" . self::ENTRY_TABLE . "
             WHERE product_id=%d AND status='active' AND order_id>0",
            $pid
        ));
        foreach ($order_ids as $order_id) {
            $order = wc_get_order(absint($order_id));
            if ($order) {
                $order->add_order_note(
                    'RaffleLB RAFFLE CLOSED EARLY for ' . get_the_title($pid) .
                    '. Eligible paid entries at closure: ' . $claimed . ' / ' . $total .
                    '. Reason: ' . $reason .
                    '. Closed by admin user ID ' . $admin_id . ' at ' . $closed_at . '.'
                );
            }
        }

        wp_safe_redirect(add_query_arg('rafflelb_draw', 'early_closed', $redirect));
        exit;
    }

    public static function handle_select_winner() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('You are not allowed to select RaffleLB winners.');
        }

        $pid = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        if (!$pid) {
            wp_safe_redirect(admin_url('admin.php?page=rafflelb-entries&rafflelb_draw=invalid'));
            exit;
        }

        check_admin_referer('rafflelb_select_winner_' . $pid);

        $redirect = self::redirect_target(admin_url('admin.php?page=rafflelb-entries'));

        // Never allow a second draw for the same reward.
        if (self::get_draw_result($pid) || get_post_meta($pid, self::META_DRAW_STATUS, true) === 'winner_selected') {
            wp_safe_redirect(add_query_arg('rafflelb_draw', 'already_selected', $redirect));
            exit;
        }

        $total = absint(get_post_meta($pid, self::META_TOTAL, true));
        $claimed = self::claimed($pid);
        $status = self::draw_status($pid);
        $early_closed = get_post_meta($pid, self::META_EARLY_CLOSED, true) === 'yes';

        if ($status !== 'ready_to_draw' || $total < 1 || $claimed < 1 || (!$early_closed && $claimed !== $total)) {
            wp_safe_redirect(add_query_arg('rafflelb_draw', 'not_ready', $redirect));
            exit;
        }

        global $wpdb;
        $entries_table = $wpdb->prefix . self::ENTRY_TABLE;
        $results_table = $wpdb->prefix . self::RESULT_TABLE;

        // Freeze the exact eligible pool at draw time: active paid entries only.
        $eligible = $wpdb->get_results($wpdb->prepare(
            "SELECT id, entry_number, order_id, user_id
             FROM {$entries_table}
             WHERE product_id=%d AND status='active'
             ORDER BY entry_number ASC",
            $pid
        ));

        if (count($eligible) !== $claimed || count($eligible) < 1) {
            wp_safe_redirect(add_query_arg('rafflelb_draw', 'pool_mismatch', $redirect));
            exit;
        }

        try {
            // PHP random_int() uses a cryptographically secure source supplied by the OS.
            $winner_index = random_int(0, count($eligible) - 1);
            $rng_token = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            wp_safe_redirect(add_query_arg('rafflelb_draw', 'rng_error', $redirect));
            exit;
        }

        $winner = $eligible[$winner_index];
        $selected_at = current_time('mysql');

        $audit_payload = implode('|', [
            (string) $pid,
            (string) $winner->id,
            (string) $winner->entry_number,
            (string) $winner->order_id,
            (string) $winner->user_id,
            (string) $selected_at,
            (string) $rng_token,
            (string) $claimed,
        ]);
        $audit_hash = hash_hmac('sha256', $audit_payload, wp_salt('auth'));

        $wpdb->query('START TRANSACTION');

        $inserted = $wpdb->insert(
            $results_table,
            [
                'product_id'   => $pid,
                'entry_id'     => absint($winner->id),
                'entry_number' => absint($winner->entry_number),
                'order_id'     => absint($winner->order_id),
                'user_id'      => absint($winner->user_id),
                'selected_at'      => $selected_at,
                'selection_method' => 'random',
                'selected_by'      => get_current_user_id(),
                'selection_note'   => 'Secure random draw using PHP random_int().',
                'rng_token'        => $rng_token,
                'audit_hash'       => $audit_hash,
            ],
            ['%d','%d','%d','%d','%d','%s','%s','%d','%s','%s','%s']
        );

        if (!$inserted) {
            $wpdb->query('ROLLBACK');
            wp_safe_redirect(add_query_arg('rafflelb_draw', 'save_error', $redirect));
            exit;
        }
        $result_id = $wpdb->insert_id;

        update_post_meta($pid, self::META_DRAW_STATUS, 'winner_selected');
        update_post_meta($pid, self::META_WINNER_ENTRY_ID, absint($winner->id));
        update_post_meta($pid, self::META_WINNER_SELECTED_AT, $selected_at);

        $wpdb->query('COMMIT');

        // Lets listeners (e.g. RaffleLB Notifications) react to a completed
        // draw without this plugin knowing anything about them.
        do_action('rafflelb_draw_completed', $pid, absint($winner->user_id), absint($winner->id), absint($result_id), 'random');

        $order = wc_get_order(absint($winner->order_id));
        if ($order) {
            $order->add_order_note(
                'RaffleLB WINNER SELECTED for ' . get_the_title($pid) .
                ': Entry #' . str_pad((string) absint($winner->entry_number), 3, '0', STR_PAD_LEFT) .
                '. Draw timestamp: ' . $selected_at .
                '. Audit hash: ' . $audit_hash
            );
        }

        $saved_result = self::get_draw_result($pid);
        if ($saved_result) self::send_winner_email($saved_result);

        wp_safe_redirect(add_query_arg('rafflelb_draw', 'success', $redirect));
        exit;
    }


    public static function handle_choose_winner() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('You are not allowed to record RaffleLB winners.');
        }

        $pid = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $entry_number = isset($_POST['entry_number']) ? absint($_POST['entry_number']) : 0;
        $reason = isset($_POST['selection_note']) ? sanitize_textarea_field(wp_unslash($_POST['selection_note'])) : '';

        if (!$pid || !$entry_number) {
            wp_safe_redirect(admin_url('admin.php?page=rafflelb-entries&rafflelb_draw=invalid'));
            exit;
        }

        check_admin_referer('rafflelb_choose_winner_' . $pid);
        $redirect = self::redirect_target(admin_url('admin.php?page=rafflelb-entries'));

        if (self::get_draw_result($pid) || get_post_meta($pid, self::META_DRAW_STATUS, true) === 'winner_selected') {
            wp_safe_redirect(add_query_arg('rafflelb_draw', 'already_selected', $redirect));
            exit;
        }

        $total = absint(get_post_meta($pid, self::META_TOTAL, true));
        $claimed = self::claimed($pid);
        $early_closed = get_post_meta($pid, self::META_EARLY_CLOSED, true) === 'yes';
        if (self::draw_status($pid) !== 'ready_to_draw' || $total < 1 || $claimed < 1 || (!$early_closed && $claimed !== $total)) {
            wp_safe_redirect(add_query_arg('rafflelb_draw', 'not_ready', $redirect));
            exit;
        }

        if ($reason === '') {
            wp_safe_redirect(add_query_arg('rafflelb_draw', 'manual_reason_required', $redirect));
            exit;
        }

        global $wpdb;
        $entries_table = $wpdb->prefix . self::ENTRY_TABLE;
        $results_table = $wpdb->prefix . self::RESULT_TABLE;

        $winner = $wpdb->get_row($wpdb->prepare(
            "SELECT id, entry_number, order_id, user_id
             FROM {$entries_table}
             WHERE product_id=%d AND entry_number=%d AND status='active'
             LIMIT 1",
            $pid, $entry_number
        ));

        if (!$winner) {
            wp_safe_redirect(add_query_arg('rafflelb_draw', 'manual_entry_invalid', $redirect));
            exit;
        }

        $selected_at = current_time('mysql');
        $selected_by = get_current_user_id();
        $audit_payload = implode('|', [
            (string)$pid, (string)$winner->id, (string)$winner->entry_number,
            (string)$winner->order_id, (string)$winner->user_id, (string)$selected_at,
            'manual', (string)$selected_by, (string)$reason, (string)$claimed
        ]);
        $audit_hash = hash_hmac('sha256', $audit_payload, wp_salt('auth'));

        $wpdb->query('START TRANSACTION');

        $inserted = $wpdb->insert(
            $results_table,
            [
                'product_id'       => $pid,
                'entry_id'         => absint($winner->id),
                'entry_number'     => absint($winner->entry_number),
                'order_id'         => absint($winner->order_id),
                'user_id'          => absint($winner->user_id),
                'selected_at'      => $selected_at,
                'selection_method' => 'manual',
                'selected_by'      => $selected_by,
                'selection_note'   => $reason,
                'rng_token'        => '',
                'audit_hash'       => $audit_hash,
            ],
            ['%d','%d','%d','%d','%d','%s','%s','%d','%s','%s','%s']
        );

        if (!$inserted) {
            $wpdb->query('ROLLBACK');
            wp_safe_redirect(add_query_arg('rafflelb_draw', 'save_error', $redirect));
            exit;
        }
        $result_id = $wpdb->insert_id;

        update_post_meta($pid, self::META_DRAW_STATUS, 'winner_selected');
        update_post_meta($pid, self::META_WINNER_ENTRY_ID, absint($winner->id));
        update_post_meta($pid, self::META_WINNER_SELECTED_AT, $selected_at);
        $wpdb->query('COMMIT');

        // Lets listeners (e.g. RaffleLB Notifications) react to a completed
        // draw without this plugin knowing anything about them.
        do_action('rafflelb_draw_completed', $pid, absint($winner->user_id), absint($winner->id), absint($result_id), 'manual');

        $order = wc_get_order(absint($winner->order_id));
        if ($order) {
            $order->add_order_note(
                'RaffleLB MANUAL/EXTERNAL WINNER RECORDED for ' . get_the_title($pid) .
                ': Entry #' . str_pad((string)absint($winner->entry_number), 3, '0', STR_PAD_LEFT) .
                '. Reason/reference: ' . $reason .
                '. Audit hash: ' . $audit_hash
            );
        }

        $saved_result = self::get_draw_result($pid);
        if ($saved_result) self::send_winner_email($saved_result);

        wp_safe_redirect(add_query_arg('rafflelb_draw', 'manual_success', $redirect));
        exit;
    }


    public static function handle_admin_order_action() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('You are not allowed to manage RaffleLB entry orders.');
        }

        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $requested_action = isset($_POST['raffle_order_action'])
            ? sanitize_key(wp_unslash($_POST['raffle_order_action']))
            : '';
        $reason = isset($_POST['reason'])
            ? sanitize_textarea_field(wp_unslash($_POST['reason']))
            : '';

        check_admin_referer('rafflelb_admin_order_action_' . $order_id);

        $redirect = self::redirect_target(admin_url('admin.php?page=rafflelb-entries'));

        if (!$order_id || !in_array($requested_action, ['cancel','refund'], true)) {
            wp_safe_redirect(add_query_arg('rafflelb_order_action', 'invalid', $redirect));
            exit;
        }

        if ($reason === '') {
            wp_safe_redirect(add_query_arg('rafflelb_order_action', 'reason_required', $redirect));
            exit;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_safe_redirect(add_query_arg('rafflelb_order_action', 'order_missing', $redirect));
            exit;
        }

        global $wpdb;
        $entries_table = $wpdb->prefix . self::ENTRY_TABLE;

        $active_entries = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$entries_table}
             WHERE order_id=%d AND status='active'
             ORDER BY id ASC",
            $order_id
        ));

        if (!$active_entries) {
            wp_safe_redirect(add_query_arg('rafflelb_order_action', 'no_active_entries', $redirect));
            exit;
        }

        // Safety: do not let this admin shortcut alter an order attached to a
        // completed draw. Historical winner results must remain untouched.
        foreach ($active_entries as $entry) {
            if (self::get_draw_result(absint($entry->product_id))
                || get_post_meta(absint($entry->product_id), self::META_DRAW_STATUS, true) === 'winner_selected') {
                wp_safe_redirect(add_query_arg('rafflelb_order_action', 'winner_locked', $redirect));
                exit;
            }
        }

        $new_status = ($requested_action === 'refund') ? 'refunded' : 'cancelled';
        $label = ($requested_action === 'refund') ? 'REFUNDED' : 'CANCELLED';

        // Record the administrator's reason before the standard status hook
        // voids the entries.
        $order->add_order_note(
            'RaffleLB admin action requested: ' . $label .
            '. Reason: ' . $reason .
            '. Admin user ID: ' . get_current_user_id()
        );

        // Persist the exact administrator-entered reason BEFORE changing
        // status. WooCommerce fires the status hook during update_status(), so
        // void_entries_for_order() can read this value and write it into the
        // permanent RaffleLB audit history.
        $order->update_meta_data('_rafflelb_pending_void_reason', $reason);
        $order->update_meta_data('_rafflelb_pending_void_action', $new_status);
        $order->save();

        // This triggers the existing WooCommerce status hooks, including
        // RaffleLB void_entries_for_order().
        $order->update_status(
            $new_status,
            'RaffleLB admin entry-order action. Reason: ' . $reason,
            true
        );

        wp_safe_redirect(add_query_arg(
            [
                'rafflelb_order_action' => 'success',
                'rafflelb_order_id' => $order_id,
                'rafflelb_order_status' => $new_status,
            ],
            $redirect
        ));
        exit;
    }


    public static function admin_menu() {
        add_submenu_page('woocommerce','RaffleLB Entries','RaffleLB Entries','manage_woocommerce','rafflelb-entries',[__CLASS__,'admin_page']);
    }

    public static function admin_page() {
        if(!current_user_can('manage_woocommerce')) return;
        global $wpdb;

        $entries=$wpdb->get_results("SELECT * FROM {$wpdb->prefix}".self::ENTRY_TABLE." ORDER BY id DESC LIMIT 500");
        $holds=$wpdb->get_results("SELECT * FROM {$wpdb->prefix}".self::HOLD_TABLE." WHERE expires_at>='".esc_sql(current_time('mysql'))."' ORDER BY id DESC");

        echo '<div class="wrap"><h1>RaffleLB Entries</h1>';


        $order_action_notice = isset($_GET['rafflelb_order_action'])
            ? sanitize_key(wp_unslash($_GET['rafflelb_order_action']))
            : '';

        if ($order_action_notice) {
            $order_messages = [
                'success'           => ['success', 'Entry order updated. The associated active raffle entries were processed by the VOID protection system.'],
                'invalid'           => ['error', 'Invalid RaffleLB order action.'],
                'reason_required'   => ['error', 'A cancellation/refund reason is required.'],
                'order_missing'     => ['error', 'The WooCommerce order could not be found.'],
                'no_active_entries' => ['warning', 'This order has no active raffle entries to cancel or refund.'],
                'winner_locked'     => ['error', 'Action blocked: this order is attached to a draw with a permanently selected winner. Manual review is required.'],
            ];

            if (isset($order_messages[$order_action_notice])) {
                [$type, $message] = $order_messages[$order_action_notice];
                echo '<div class="notice notice-'.esc_attr($type).' is-dismissible"><p>'.esc_html($message).'</p></div>';
            }
        }

        $notice = isset($_GET['rafflelb_draw']) ? sanitize_key(wp_unslash($_GET['rafflelb_draw'])) : '';
        $messages = [
            'success'          => ['success', 'Winner selected and permanently recorded.'],
            'manual_success'   => ['success', 'Manual/external winner recorded and permanently audited.'],
            'early_closed'     => ['success', 'Raffle closed early. New raffle entries are now blocked and the current paid-entry pool is frozen for winner selection.'],
            'early_reason_required' => ['error', 'Closing a raffle early requires an admin reason.'],
            'early_not_available' => ['error', 'This raffle cannot be closed early. It must be live, partially filled, and have at least one eligible paid entry.'],
            'already_selected' => ['warning', 'A winner has already been selected for this reward. A second draw was blocked.'],
            'manual_reason_required' => ['error', 'Manual winner selection requires a reason or external draw reference.'],
            'manual_entry_invalid'   => ['error', 'The chosen entry is not an active paid entry for this reward.'],
            'not_ready'        => ['error', 'This reward is not Ready to Draw.'],
            'pool_mismatch'    => ['error', 'Winner selection was blocked because the frozen eligible paid-entry pool is inconsistent.'],
            'rng_error'        => ['error', 'Secure random selection could not be completed. No winner was recorded.'],
            'save_error'       => ['error', 'The result could not be saved. No winner was recorded.'],
            'invalid'          => ['error', 'Invalid reward.'],
            'email_sent'       => ['success', 'Winner email sent successfully.'],
            'email_failed'     => ['error', 'Winner email could not be sent. Check the winner email address and WordPress mail configuration.'],
            'email_result_missing' => ['error', 'Winner result could not be found.'],
            'fulfillment_saved' => ['success', 'Prize fulfillment status saved.'],
            'fulfillment_missing' => ['error', 'Prize fulfillment record could not be found.'],
        ];
        if ($notice && isset($messages[$notice])) {
            [$type, $text] = $messages[$notice];
            echo '<div class="notice notice-'.esc_attr($type).' is-dismissible"><p>'.esc_html($text).'</p></div>';
        }

        $live_products = get_posts([
            'post_type'      => 'product',
            'post_status'    => ['publish','private','draft'],
            'posts_per_page' => -1,
            'meta_key'       => self::META_ENABLED,
            'meta_value'     => 'yes',
            'fields'         => 'ids',
        ]);

        $early_close_products = [];
        foreach ($live_products as $live_pid) {
            $live_total = absint(get_post_meta($live_pid, self::META_TOTAL, true));
            $live_claimed = self::claimed($live_pid);
            if ($live_total > 0 && $live_claimed > 0 && $live_claimed < $live_total && self::draw_status($live_pid) === 'live') {
                $early_close_products[] = $live_pid;
            }
        }

        if ($early_close_products) {
            echo '<div style="margin:18px 0 24px;padding:18px 20px;border-left:5px solid #dba617;background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08)">';
            echo '<h2 style="margin:0 0 6px">Active Raffles — Early Close</h2>';
            echo '<p style="margin:0 0 14px;color:#646970"><strong>Admin only.</strong> Closing early immediately blocks new raffle entries and freezes the current active paid-entry pool. Buy Now remains available if enabled. A reason is mandatory and the closure is recorded in the audit trail.</p>';
            echo '<table class="widefat striped"><thead><tr><th>Reward</th><th>Paid Entries</th><th>Remaining</th><th>Action</th></tr></thead><tbody>';
            foreach ($early_close_products as $live_pid) {
                $live_total = absint(get_post_meta($live_pid, self::META_TOTAL, true));
                $live_claimed = self::claimed($live_pid);
                echo '<tr>';
                echo '<td><strong>'.esc_html(get_the_title($live_pid)).'</strong></td>';
                echo '<td><strong>'.esc_html($live_claimed).'</strong> / '.esc_html($live_total).'</td>';
                echo '<td>'.esc_html(max(0,$live_total-$live_claimed)).'</td>';
                echo '<td style="min-width:360px">';
                echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" onsubmit="return confirm(\'Close this raffle early now? New raffle entries will be blocked immediately. This action prepares the current paid entries for winner selection.\');">';
                echo '<input type="hidden" name="action" value="rafflelb_close_raffle_early">';
                echo '<input type="hidden" name="product_id" value="'.esc_attr($live_pid).'">';
                wp_nonce_field('rafflelb_close_raffle_early_' . $live_pid);
                echo '<textarea name="close_reason" rows="2" required placeholder="Required reason for early closure" style="width:100%;max-width:340px;margin-bottom:7px"></textarea>';
                echo '<br><button type="submit" class="button" style="border-color:#b7791f;color:#7a4b00;font-weight:700">CLOSE RAFFLE EARLY</button>';
                echo '</form>';
                echo '</td></tr>';
            }
            echo '</tbody></table></div>';
        }

        $ready_products = get_posts([
            'post_type'      => 'product',
            'post_status'    => ['publish','private','draft'],
            'posts_per_page' => -1,
            'meta_key'       => self::META_DRAW_STATUS,
            'meta_value'     => 'ready_to_draw',
            'fields'         => 'ids',
        ]);

        if ($ready_products) {
            echo '<div style="margin:18px 0 24px;padding:18px 20px;border-left:5px solid #9cff00;background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08)">';
            echo '<h2 style="margin:0 0 6px">Ready to Draw</h2>';
            echo '<p style="margin:0 0 14px;color:#646970">Use <strong>Secure Random Draw</strong> for an automatic draw from the frozen eligible paid-entry pool. <strong>Record Chosen Winner</strong> lets an administrator explicitly choose one eligible paid entry; it requires a reason/reference and is permanently labeled in the audit record.</p>';
            echo '<table class="widefat striped"><thead><tr><th>Reward</th><th>Final Entries</th><th>Status</th><th>Closed At</th><th>Action</th></tr></thead><tbody>';
            foreach ($ready_products as $ready_pid) {
                $ready_total = absint(get_post_meta($ready_pid, self::META_TOTAL, true));
                $ready_claimed = self::claimed($ready_pid);
                $closed_at = get_post_meta($ready_pid, self::META_CLOSED_AT, true);
                $early_closed = get_post_meta($ready_pid, self::META_EARLY_CLOSED, true) === 'yes';
                $early_reason = (string) get_post_meta($ready_pid, self::META_EARLY_CLOSE_REASON, true);
                $eligible_rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, entry_number, order_id, user_id FROM {$wpdb->prefix}" . self::ENTRY_TABLE . "
                     WHERE product_id=%d AND status='active' ORDER BY entry_number ASC",
                    $ready_pid
                ));

                echo '<tr><td><strong>'.esc_html(get_the_title($ready_pid)).'</strong></td>';
                echo '<td>'.esc_html($ready_claimed).' / '.esc_html($ready_total).'</td>';
                echo '<td><strong style="color:#4d7600">READY TO DRAW</strong>' . ($early_closed ? '<br><small style="color:#a15c00">CLOSED EARLY</small>' : '') . '</td>';
                echo '<td>'.esc_html($closed_at ?: '—').'</td>';
                echo '<td style="min-width:330px">';
                echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="margin:0 0 10px" onsubmit="return confirm(\'Run the secure random draw now? This result is permanent.\');">';
                echo '<input type="hidden" name="action" value="rafflelb_select_winner">';
                echo '<input type="hidden" name="product_id" value="'.esc_attr($ready_pid).'">';
                wp_nonce_field('rafflelb_select_winner_' . $ready_pid);
                echo '<button type="submit" class="button button-primary" style="background:#10130d;border-color:#10130d;color:#b8ff00;font-weight:700">SECURE RANDOM DRAW</button>';
                echo '</form>';
                echo '<details><summary style="cursor:pointer;font-weight:700">Record Chosen Winner</summary>';
                echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="margin-top:10px" onsubmit="return confirm(\'Record this chosen entry as the winner? This is permanent and will be labeled manual/external.\');">';
                echo '<input type="hidden" name="action" value="rafflelb_choose_winner">';
                echo '<input type="hidden" name="product_id" value="'.esc_attr($ready_pid).'">';
                wp_nonce_field('rafflelb_choose_winner_' . $ready_pid);
                if ($early_closed && $early_reason !== '') echo '<p style="margin:8px 0;color:#7a4b00"><small><strong>Early-close reason:</strong> '.esc_html($early_reason).'</small></p>';
                echo '<p><label><strong>Choose eligible winning entry</strong><br><select name="entry_number" required style="width:100%;max-width:310px"><option value="">Select paid entry…</option>';
                foreach ($eligible_rows as $eligible_row) {
                    $eligible_user = $eligible_row->user_id ? get_user_by('id', absint($eligible_row->user_id)) : false;
                    $eligible_order = wc_get_order(absint($eligible_row->order_id));
                    $eligible_name = $eligible_user ? $eligible_user->display_name : ($eligible_order ? trim($eligible_order->get_formatted_billing_full_name()) : 'Guest');
                    if ($eligible_name === '') $eligible_name = 'Guest';
                    $option_label = '#' . str_pad((string)absint($eligible_row->entry_number),3,'0',STR_PAD_LEFT) . ' — ' . $eligible_name . ' — Order #' . absint($eligible_row->order_id);
                    echo '<option value="'.esc_attr($eligible_row->entry_number).'">'.esc_html($option_label).'</option>';
                }
                echo '</select></label></p>';
                echo '<p><label><strong>Reason / external draw reference</strong><br><textarea name="selection_note" rows="2" required style="width:100%;max-width:310px"></textarea></label></p>';
                echo '<button type="submit" class="button">RECORD CHOSEN WINNER</button>';
                echo '</form></details>';
                echo '</td></tr>';
            }
            echo '</tbody></table></div>';
        }

        $results = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}" . self::RESULT_TABLE . " ORDER BY selected_at DESC, id DESC"
        );

        if ($results) {
            // Resolve customer name / product title / order once per row so
            // the search box can match on any of them. Result volume is
            // bounded by completed draws, not raw entries, so doing this in
            // PHP (rather than a cross-table SQL join across postmeta/users/
            // WooCommerce orders) keeps the query simple and safe.
            foreach ($results as $result) {
                $result->_rlb_user = $result->user_id ? get_user_by('id', $result->user_id) : false;
                $result->_rlb_order = wc_get_order($result->order_id);
                $result->_rlb_customer_name = $result->_rlb_user
                    ? $result->_rlb_user->display_name
                    : ($result->_rlb_order ? trim($result->_rlb_order->get_formatted_billing_full_name()) : 'Guest');
                if (!$result->_rlb_customer_name) $result->_rlb_customer_name = 'Guest';
                $result->_rlb_product_title = get_the_title($result->product_id);
                $result->_rlb_fulfillment_status = !empty($result->fulfillment_status) ? $result->fulfillment_status : 'pending';
            }

            $rlb_search = isset($_GET['rlb_s']) ? sanitize_text_field(wp_unslash($_GET['rlb_s'])) : '';
            $rlb_status_filter = isset($_GET['rlb_status']) ? sanitize_key(wp_unslash($_GET['rlb_status'])) : '';
            $rlb_paged = max(1, isset($_GET['rlb_paged']) ? absint($_GET['rlb_paged']) : 1);
            $rlb_per_page = 20;

            $filtered_results = array_values(array_filter($results, function ($result) use ($rlb_search, $rlb_status_filter) {
                if ($rlb_status_filter !== '' && $result->_rlb_fulfillment_status !== $rlb_status_filter) return false;
                if ($rlb_search !== '') {
                    $haystack = strtolower($result->_rlb_product_title . ' ' . $result->_rlb_customer_name . ' #' . $result->order_id . ' #' . str_pad((string)$result->entry_number,3,'0',STR_PAD_LEFT));
                    if (strpos($haystack, strtolower($rlb_search)) === false) return false;
                }
                return true;
            }));

            $rlb_total = count($filtered_results);
            $rlb_total_pages = max(1, (int) ceil($rlb_total / $rlb_per_page));
            $rlb_paged = min($rlb_paged, $rlb_total_pages);
            $rlb_page_results = array_slice($filtered_results, ($rlb_paged - 1) * $rlb_per_page, $rlb_per_page);

            $rlb_base_url = admin_url('admin.php?page=rafflelb-entries');

            echo '<div style="margin:18px 0 24px;padding:18px 20px;border-left:5px solid #111;background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08)">';
            echo '<h2 style="margin:0 0 12px">Winner Selected</h2>';

            echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:12px">';
            echo '<input type="hidden" name="page" value="rafflelb-entries">';
            echo '<input type="search" name="rlb_s" value="' . esc_attr($rlb_search) . '" placeholder="Search reward, customer, order #, entry #" style="min-width:280px">';
            echo '<select name="rlb_status">';
            echo '<option value="">All fulfillment statuses</option>';
            foreach (['pending'=>'Pending','contacted'=>'Winner Contacted','claimed'=>'Prize Claimed','fulfilled'=>'Prize Fulfilled'] as $key=>$label) {
                echo '<option value="'.esc_attr($key).'" '.selected($rlb_status_filter,$key,false).'>'.esc_html($label).'</option>';
            }
            echo '</select>';
            echo '<button type="submit" class="button">Filter</button>';
            if ($rlb_search !== '' || $rlb_status_filter !== '') {
                echo '<a class="button" href="' . esc_url($rlb_base_url) . '">Clear</a>';
            }
            echo '<span style="margin-left:auto;color:#646970">' . esc_html($rlb_total) . ' result' . ($rlb_total === 1 ? '' : 's') . '</span>';
            echo '</form>';

            if (!$rlb_page_results) {
                echo '<p style="color:#646970">No completed draws match this search/filter.</p>';
            } else {

            echo '<table class="widefat striped"><thead><tr><th>Reward</th><th>Winning Entry</th><th>Customer</th><th>Order</th><th>Method</th><th>Selected At</th><th>Notification</th><th>Prize Fulfillment</th><th>Audit Hash</th></tr></thead><tbody>';

            foreach ($rlb_page_results as $result) {
                $customer_name = $result->_rlb_customer_name;

                echo '<tr>';
                echo '<td><strong>'.esc_html($result->_rlb_product_title).'</strong><br><span style="color:#4d7600;font-weight:700">WINNER SELECTED</span></td>';
                echo '<td><strong style="font-size:16px">#'.esc_html(str_pad((string)$result->entry_number,3,'0',STR_PAD_LEFT)).'</strong></td>';
                echo '<td>'.esc_html($customer_name).'</td>';
                echo '<td>#'.esc_html($result->order_id).'</td>';
                $method_label = (!empty($result->selection_method) && $result->selection_method === 'manual') ? 'MANUAL / EXTERNAL' : 'SECURE RANDOM';
                echo '<td><strong>'.esc_html($method_label).'</strong></td>';
                echo '<td>'.esc_html($result->selected_at).'</td>';

                echo '<td style="min-width:180px">';
                if (!empty($result->winner_email_sent_at)) {
                    echo '<strong style="color:#4d7600">EMAIL SENT</strong><br><small>'.esc_html($result->winner_email_sent_at).'</small>';
                } else {
                    echo '<strong>NOT SENT</strong>';
                }
                echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="margin-top:8px">';
                echo '<input type="hidden" name="action" value="rafflelb_send_winner_email">';
                echo '<input type="hidden" name="result_id" value="'.esc_attr($result->id).'">';
                wp_nonce_field('rafflelb_send_winner_email_' . $result->id);
                echo '<button type="submit" class="button">'.(!empty($result->winner_email_sent_at) ? 'RESEND EMAIL' : 'SEND WINNER EMAIL').'</button>';
                echo '</form>';
                echo '</td>';

                echo '<td style="min-width:280px">';
                $fulfillment_status = $result->_rlb_fulfillment_status;
                $is_fulfilled = $fulfillment_status === 'fulfilled';
                $lock_id = 'rlb-fulfillment-lock-' . absint($result->id);

                if ($is_fulfilled) {
                    echo '<strong style="color:#4d7600">🔒 PRIZE FULFILLED</strong>';
                    if (!empty($result->fulfilled_at)) echo '<br><small>Fulfilled: '.esc_html($result->fulfilled_at).'</small>';
                    if (!empty($result->fulfillment_note)) echo '<div style="margin-top:6px;color:#3c434a"><small>'.esc_html($result->fulfillment_note).'</small></div>';
                    echo '<div style="margin-top:8px"><label style="font-weight:400"><input type="checkbox" id="'.esc_attr($lock_id).'" onchange="var f=this.closest(\'td\').querySelector(\'form\');f.querySelectorAll(\'select,textarea,button[type=submit]\').forEach(function(el){el.disabled=!this.checked}.bind(this));" style="margin-right:5px"> Edit anyway</label></div>';
                }

                echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="'.($is_fulfilled ? 'margin-top:8px' : '').'">';
                echo '<input type="hidden" name="action" value="rafflelb_update_fulfillment">';
                echo '<input type="hidden" name="result_id" value="'.esc_attr($result->id).'">';
                wp_nonce_field('rafflelb_update_fulfillment_' . $result->id);
                $disabled_attr = $is_fulfilled ? ' disabled' : '';
                echo '<select name="fulfillment_status" style="width:100%;max-width:250px"'.$disabled_attr.'>';
                foreach (['pending'=>'Pending','contacted'=>'Winner Contacted','claimed'=>'Prize Claimed','fulfilled'=>'Prize Fulfilled'] as $key=>$label) {
                    echo '<option value="'.esc_attr($key).'" '.selected($fulfillment_status,$key,false).'>'.esc_html($label).'</option>';
                }
                echo '</select>';
                echo '<textarea name="fulfillment_note" rows="2" placeholder="Admin fulfillment note" style="display:block;width:100%;max-width:250px;margin-top:6px"'.$disabled_attr.'>'.esc_textarea((string)$result->fulfillment_note).'</textarea>';
                echo '<button type="submit" class="button button-secondary" style="margin-top:6px"'.$disabled_attr.'>SAVE STATUS</button>';

                if (!$is_fulfilled) {
                    if (!empty($result->contacted_at)) echo '<div style="margin-top:6px"><small>Contacted: '.esc_html($result->contacted_at).'</small></div>';
                    if (!empty($result->claimed_at)) echo '<div><small>Claimed: '.esc_html($result->claimed_at).'</small></div>';
                }

                echo '</form>';
                echo '</td>';

                echo '<td><code title="'.esc_attr($result->audit_hash).'">'.esc_html(substr($result->audit_hash,0,16)).'…</code></td>';
                echo '</tr>';
            }

            echo '</tbody></table>';

            if ($rlb_total_pages > 1) {
                echo '<div style="margin-top:14px;display:flex;gap:6px;align-items:center">';
                for ($p = 1; $p <= $rlb_total_pages; $p++) {
                    $page_url = add_query_arg(array_filter(['rlb_s' => $rlb_search, 'rlb_status' => $rlb_status_filter, 'rlb_paged' => $p]), $rlb_base_url);
                    if ($p === $rlb_paged) {
                        echo '<strong style="padding:4px 9px;border:1px solid #111;border-radius:4px">'.esc_html($p).'</strong>';
                    } else {
                        echo '<a class="button" href="'.esc_url($page_url).'">'.esc_html($p).'</a>';
                    }
                }
                echo '</div>';
            }

            }

            echo '</div>';
        }


        // Admin-only entry-order management. Group active entries by order so
        // the operator can cancel/refund from RaffleLB Entries without opening
        // WooCommerce Orders.
        $active_order_ids = $wpdb->get_col(
            "SELECT DISTINCT order_id
             FROM {$wpdb->prefix}" . self::ENTRY_TABLE . "
             WHERE status='active' AND order_id>0
             ORDER BY order_id DESC"
        );

        if ($active_order_ids) {
            $managed_rows = [];
            foreach ($active_order_ids as $active_order_id) {
                $managed_order = wc_get_order(absint($active_order_id));
                if (!$managed_order) continue;

                $managed_entries = $wpdb->get_results($wpdb->prepare(
                    "SELECT product_id, COUNT(*) AS qty, MIN(entry_number) AS first_entry, MAX(entry_number) AS last_entry
                     FROM {$wpdb->prefix}" . self::ENTRY_TABLE . "
                     WHERE order_id=%d AND status='active'
                     GROUP BY product_id
                     ORDER BY product_id ASC",
                    $active_order_id
                ));

                if (!$managed_entries) continue;

                $customer_name = trim($managed_order->get_formatted_billing_full_name());
                if ($customer_name === '') {
                    $customer_name = $managed_order->get_user_id()
                        ? (get_user_by('id', $managed_order->get_user_id())->display_name ?? 'Customer')
                        : 'Guest';
                }

                $reward_lines = [];
                $reward_titles = [];
                $winner_locked = false;
                foreach ($managed_entries as $managed_entry) {
                    $pid = absint($managed_entry->product_id);
                    if (self::get_draw_result($pid)
                        || get_post_meta($pid, self::META_DRAW_STATUS, true) === 'winner_selected') {
                        $winner_locked = true;
                    }

                    $product_title = get_the_title($pid);
                    $reward_titles[] = $product_title;
                    $first = '#' . str_pad((string)absint($managed_entry->first_entry), 3, '0', STR_PAD_LEFT);
                    $last  = '#' . str_pad((string)absint($managed_entry->last_entry), 3, '0', STR_PAD_LEFT);
                    $range = absint($managed_entry->qty) > 1 ? ($first . '–' . $last) : $first;
                    $reward_lines[] = esc_html($product_title) . ' × ' . absint($managed_entry->qty) . ' (' . esc_html($range) . ')';
                }

                $managed_rows[] = [
                    'order_id'       => $active_order_id,
                    'order'          => $managed_order,
                    'customer_name'  => $customer_name,
                    'reward_lines'   => $reward_lines,
                    'search_haystack'=> strtolower($customer_name . ' #' . $active_order_id . ' ' . implode(' ', $reward_titles)),
                    'winner_locked'  => $winner_locked,
                ];
            }

            $rlbo_search = isset($_GET['rlbo_s']) ? sanitize_text_field(wp_unslash($_GET['rlbo_s'])) : '';
            $rlbo_paged = max(1, isset($_GET['rlbo_paged']) ? absint($_GET['rlbo_paged']) : 1);
            $rlbo_per_page = 20;

            $filtered_managed_rows = $rlbo_search === '' ? $managed_rows : array_values(array_filter($managed_rows, function ($row) use ($rlbo_search) {
                return strpos($row['search_haystack'], strtolower($rlbo_search)) !== false;
            }));

            $rlbo_total = count($filtered_managed_rows);
            $rlbo_total_pages = max(1, (int) ceil($rlbo_total / $rlbo_per_page));
            $rlbo_paged = min($rlbo_paged, $rlbo_total_pages);
            $rlbo_page_rows = array_slice($filtered_managed_rows, ($rlbo_paged - 1) * $rlbo_per_page, $rlbo_per_page);

            $rlbo_base_url = admin_url('admin.php?page=rafflelb-entries');

            echo '<div style="margin:18px 0 26px;padding:18px 20px;background:#fff;border-left:5px solid #b8ff00;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08)">';
            echo '<h2 style="margin:0 0 6px">Entry Order Management</h2>';
            echo '<p style="margin:0 0 16px">Admin only. Cancelling or marking an order Refunded will trigger the existing RaffleLB VOID-entry protection. A reason is mandatory.</p>';

            echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:12px">';
            echo '<input type="hidden" name="page" value="rafflelb-entries">';
            echo '<input type="search" name="rlbo_s" value="' . esc_attr($rlbo_search) . '" placeholder="Search customer, order #, reward" style="min-width:280px">';
            echo '<button type="submit" class="button">Filter</button>';
            if ($rlbo_search !== '') {
                echo '<a class="button" href="' . esc_url($rlbo_base_url) . '">Clear</a>';
            }
            echo '<span style="margin-left:auto;color:#646970">' . esc_html($rlbo_total) . ' order' . ($rlbo_total === 1 ? '' : 's') . '</span>';
            echo '</form>';

            if (!$rlbo_page_rows) {
                echo '<p style="color:#646970">No orders match this search.</p>';
            } else {

            echo '<table class="widefat striped"><thead><tr><th>Order</th><th>Customer</th><th>Reward / Entries</th><th>Order Status</th><th>Total</th><th>Action</th></tr></thead><tbody>';

            foreach ($rlbo_page_rows as $row) {
                $managed_order = $row['order'];
                $active_order_id = $row['order_id'];

                echo '<tr>';
                echo '<td><strong><a href="'.esc_url($managed_order->get_edit_order_url()).'">#'.esc_html($active_order_id).'</a></strong></td>';
                echo '<td>'.esc_html($row['customer_name']).'</td>';
                echo '<td>'.implode('<br>', $row['reward_lines']).'</td>';
                echo '<td><strong>'.esc_html(wc_get_order_status_name($managed_order->get_status())).'</strong></td>';
                echo '<td>'.wp_kses_post($managed_order->get_formatted_order_total()).'</td>';
                echo '<td style="min-width:310px">';

                if ($row['winner_locked']) {
                    echo '<strong style="color:#b42318">LOCKED — WINNER SELECTED</strong><br><small>Use manual review for post-draw changes.</small>';
                } elseif ($managed_order->has_status(['cancelled','refunded','failed'])) {
                    echo '<em>No action available for this order status.</em>';
                } else {
                    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="margin:0">';
                    echo '<input type="hidden" name="action" value="rafflelb_admin_order_action">';
                    echo '<input type="hidden" name="order_id" value="'.esc_attr($active_order_id).'">';
                    wp_nonce_field('rafflelb_admin_order_action_' . $active_order_id);

                    echo '<select name="raffle_order_action" required style="width:100%;max-width:280px;margin-bottom:7px">';
                    echo '<option value="">Choose action…</option>';
                    echo '<option value="cancel">Cancel Entry Order</option>';
                    echo '<option value="refund">Mark Order Refunded</option>';
                    echo '</select>';

                    echo '<textarea name="reason" rows="2" required placeholder="Required reason / reference" style="display:block;width:100%;max-width:280px;margin-bottom:7px"></textarea>';
                    echo '<button type="submit" class="button button-secondary" onclick="return confirm(\'Apply this order action? Active raffle entries will be VOID and the draw may reopen.\');">APPLY ACTION</button>';
                    echo '<div style="margin-top:6px"><small><strong>Note:</strong> “Mark Order Refunded” changes WooCommerce status only. It does not send money through a payment gateway.</small></div>';
                    echo '</form>';
                }

                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';

            if ($rlbo_total_pages > 1) {
                echo '<div style="margin-top:14px;display:flex;gap:6px;align-items:center">';
                for ($p = 1; $p <= $rlbo_total_pages; $p++) {
                    $page_url = add_query_arg(array_filter(['rlbo_s' => $rlbo_search, 'rlbo_paged' => $p]), $rlbo_base_url);
                    if ($p === $rlbo_paged) {
                        echo '<strong style="padding:4px 9px;border:1px solid #111;border-radius:4px">'.esc_html($p).'</strong>';
                    } else {
                        echo '<a class="button" href="'.esc_url($page_url).'">'.esc_html($p).'</a>';
                    }
                }
                echo '</div>';
            }

            }

            echo '</div>';
        }

        echo '<p><strong>Active temporary reservations:</strong> '.count($holds).'</p>';
        if($holds){
            echo '<table class="widefat striped" style="margin-bottom:25px"><thead><tr><th>Product</th><th>Reserved Qty</th><th>Expires</th></tr></thead><tbody>';
            foreach($holds as $h){
                echo '<tr><td>'.esc_html(get_the_title($h->product_id)).'</td><td><strong>'.esc_html($h->quantity).'</strong></td><td>'.esc_html($h->expires_at).'</td></tr>';
            }
            echo '</tbody></table>';
        }


        $void_history = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}" . self::HISTORY_TABLE . "
             WHERE event_type='void'
             ORDER BY id DESC LIMIT 200"
        );

        if ($void_history) {
            echo '<h2 style="margin-top:28px">Voided Entry History</h2>';
            echo '<p>Voided entries are permanently retained here for audit. Their slots may later be reassigned to a new paid entry.</p>';
            echo '<table class="widefat striped" style="margin-bottom:24px"><thead><tr><th>Entry</th><th>Reward</th><th>Original Order</th><th>Customer</th><th>Reason</th><th>Voided At</th></tr></thead><tbody>';
            foreach ($void_history as $v) {
                $vu = $v->user_id ? get_user_by('id', $v->user_id) : false;
                echo '<tr>';
                echo '<td><strong>#'.esc_html(str_pad((string)$v->entry_number,3,'0',STR_PAD_LEFT)).'</strong></td>';
                echo '<td>'.esc_html(get_the_title($v->product_id)).'</td>';
                echo '<td>#'.esc_html($v->order_id).'</td>';
                echo '<td>'.esc_html($vu ? $vu->display_name : 'Guest').'</td>';
                echo '<td>'.esc_html($v->reason).'</td>';
                echo '<td>'.esc_html($v->event_at).'</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        // "Status" here is the entry's own validity (Confirmed = a real,
        // paid, non-voided entry that permanently counts toward the draw;
        // Void = cancelled/refunded and excluded). It intentionally never
        // changes to anything else once a winner is picked - a winning
        // entry, and every other entry that was in the pool, stays
        // Confirmed forever as the permanent record of who legitimately
        // participated. "Draw" shows the raffle's own progress instead, so
        // the two don't get confused for one another.
        $rlb_entry_status_labels = ['active' => 'Confirmed', 'void' => 'Void'];
        $rlb_draw_status_labels = ['live' => 'Live', 'ready_to_draw' => 'Ready to Draw', 'winner_selected' => 'Winner Selected'];
        $rlb_draw_status_cache = [];

        echo '<table class="widefat striped"><thead><tr><th>Entry</th><th>Product</th><th>Order</th><th>Customer</th><th>Entry Status</th><th>Draw</th><th>Created</th></tr></thead><tbody>';
        foreach($entries as $r){
            $u=$r->user_id?get_user_by('id',$r->user_id):false;
            $pid = absint($r->product_id);
            if (!array_key_exists($pid, $rlb_draw_status_cache)) {
                $rlb_draw_status_cache[$pid] = (string) get_post_meta($pid, self::META_DRAW_STATUS, true);
            }
            $draw_status_key = $rlb_draw_status_cache[$pid];
            $draw_status_label = isset($rlb_draw_status_labels[$draw_status_key]) ? $rlb_draw_status_labels[$draw_status_key] : '—';
            $entry_status_label = isset($rlb_entry_status_labels[$r->status]) ? $rlb_entry_status_labels[$r->status] : ucfirst($r->status);

            echo '<tr><td><strong>#'.esc_html(str_pad((string)$r->entry_number,3,'0',STR_PAD_LEFT)).'</strong></td>';
            echo '<td>'.esc_html(get_the_title($pid)).'</td><td>#'.esc_html($r->order_id).'</td>';
            echo '<td>'.esc_html($u?$u->display_name:'Guest').'</td><td>'.esc_html($entry_status_label).'</td>';
            echo '<td>'.esc_html($draw_status_label).'</td><td>'.esc_html($r->created_at).'</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public static function register_endpoint(){
        // Account presentation now lives exclusively in the RaffleLB Account plugin.
        // Keep this callback/function as a compatibility bridge so existing hook
        // identities, priorities, and third-party callers remain stable.
        if (class_exists('RaffleLB_Account', false) && RaffleLB_Account::ready()) {
            return RaffleLB_Account::register_endpoint(...func_get_args());
        }

        return;
    }
    public static function query_vars($vars){
        // Account presentation now lives exclusively in the RaffleLB Account plugin.
        // Keep this callback/function as a compatibility bridge so existing hook
        // identities, priorities, and third-party callers remain stable.
        if (class_exists('RaffleLB_Account', false) && RaffleLB_Account::ready()) {
            return RaffleLB_Account::query_vars(...func_get_args());
        }

        return $vars;
    }

    public static function account_menu($items){
        // Account presentation now lives exclusively in the RaffleLB Account plugin.
        // Keep this callback/function as a compatibility bridge so existing hook
        // identities, priorities, and third-party callers remain stable.
        if (class_exists('RaffleLB_Account', false) && RaffleLB_Account::ready()) {
            return RaffleLB_Account::account_menu(...func_get_args());
        }

        return $items;
    }

    // Runs last (priority 999) regardless of what other RaffleLB plugins'
    // own woocommerce_account_menu_items filters did before this, so the
    // final tab order is always correct even if one of them is out of date
    // or a future plugin adds a new tab without knowing about the others.
    public static function final_account_menu_order($items) {
        // Account presentation now lives exclusively in the RaffleLB Account plugin.
        // Keep this callback/function as a compatibility bridge so existing hook
        // identities, priorities, and third-party callers remain stable.
        if (class_exists('RaffleLB_Account', false) && RaffleLB_Account::ready()) {
            return RaffleLB_Account::final_account_menu_order(...func_get_args());
        }

        return $items;
    }

    private static function current_user_wins() {
        // Account presentation now lives exclusively in the RaffleLB Account plugin.
        // Keep this callback/function as a compatibility bridge so existing hook
        // identities, priorities, and third-party callers remain stable.
        if (class_exists('RaffleLB_Account', false) && RaffleLB_Account::ready()) {
            return RaffleLB_Account::current_user_wins(...func_get_args());
        }

        return [];
    }

    private static function dismissed_win_ids($user_id) {
        // Account presentation now lives exclusively in the RaffleLB Account plugin.
        // Keep this callback/function as a compatibility bridge so existing hook
        // identities, priorities, and third-party callers remain stable.
        if (class_exists('RaffleLB_Account', false) && RaffleLB_Account::ready()) {
            return RaffleLB_Account::dismissed_win_ids(...func_get_args());
        }

        return [];
    }

    public static function ajax_dismiss_winner_banner() {
        // Account presentation now lives exclusively in the RaffleLB Account plugin.
        // Keep this callback/function as a compatibility bridge so existing hook
        // identities, priorities, and third-party callers remain stable.
        if (class_exists('RaffleLB_Account', false) && RaffleLB_Account::ready()) {
            return RaffleLB_Account::ajax_dismiss_winner_banner(...func_get_args());
        }

        if (function_exists('wp_send_json_error')) {
            wp_send_json_error(['message' => 'RaffleLB Account module unavailable.'], 503);
        }
        return;
    }

    public static function account_winner_summary() {
        // Account presentation now lives exclusively in the RaffleLB Account plugin.
        // Keep this callback/function as a compatibility bridge so existing hook
        // identities, priorities, and third-party callers remain stable.
        if (class_exists('RaffleLB_Account', false) && RaffleLB_Account::ready()) {
            return RaffleLB_Account::account_winner_summary(...func_get_args());
        }

        return;
    }




    private static function order_contains_raffle_entries($order) {
        if (!$order instanceof WC_Order) return false;

        foreach ($order->get_items('line_item') as $item) {
            $product = $item->get_product();
            if ($product && self::draw_id($product) && self::order_item_purchase_mode($item) !== 'buy_now') {
                return true;
            }
        }

        return false;
    }


    public static function disable_default_raffle_processing_email($enabled, $order, $email = null) {
        if ($order instanceof WC_Order && self::order_contains_raffle_entries($order)) {
            return false;
        }
        return $enabled;
    }

    public static function disable_default_raffle_completed_email($enabled, $order, $email = null) {
        if ($order instanceof WC_Order && self::order_contains_raffle_entries($order)) {
            return false;
        }
        return $enabled;
    }

    public static function send_raffle_order_confirmation_email($order_id) {
        $order = wc_get_order($order_id);
        if (!$order || !self::order_contains_raffle_entries($order)) return false;

        // Send once only. Status may be triggered more than once by gateways.
        if ($order->get_meta('_rafflelb_order_confirmation_email_sent_at', true)) {
            return true;
        }

        $to = sanitize_email($order->get_billing_email());
        if (!$to && $order->get_user_id()) {
            $user = get_user_by('id', $order->get_user_id());
            if ($user) $to = sanitize_email($user->user_email);
        }

        if (!$to || !is_email($to)) {
            $order->add_order_note('RaffleLB: branded order confirmation email was not sent because no valid customer email was available.');
            return false;
        }

        $customer_name = trim($order->get_formatted_billing_full_name());
        if ($customer_name === '') $customer_name = 'RaffleLB Member';

        global $wpdb;
        $entries_table = $wpdb->prefix . self::ENTRY_TABLE;

        $entry_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT product_id, entry_number
             FROM {$entries_table}
             WHERE order_id=%d AND status='active'
             ORDER BY product_id ASC, entry_number ASC",
            $order_id
        ));

        $grouped = [];
        foreach ($entry_rows as $row) {
            $pid = absint($row->product_id);
            if (!isset($grouped[$pid])) $grouped[$pid] = [];
            $grouped[$pid][] = absint($row->entry_number);
        }

        // Fallback to order items if entry generation has not populated rows yet.
        if (!$grouped) {
            foreach ($order->get_items('line_item') as $item) {
                $product = $item->get_product();
                if (!$product) continue;
                $pid = self::draw_id($product);
                if (!$pid) continue;
                if (!isset($grouped[$pid])) $grouped[$pid] = [];
            }
        }

        $reward_blocks = '';
        foreach ($grouped as $pid => $numbers) {
            $reward_name = get_the_title($pid);
            $entry_html = '';

            if ($numbers) {
                sort($numbers, SORT_NUMERIC);
                $formatted = array_map(static function($n){
                    return '#' . str_pad((string)absint($n), 3, '0', STR_PAD_LEFT);
                }, $numbers);
                $entry_html =
                    '<div style="margin-top:10px">' .
                      '<div style="font-size:11px;color:#8f9887;letter-spacing:.8px;text-transform:uppercase">Your Entries</div>' .
                      '<div style="margin-top:4px;color:#caff16;font-size:16px;font-weight:800;line-height:1.55">' . esc_html(implode(', ', $formatted)) . '</div>' .
                    '</div>';
            }

            $reward_blocks .=
                '<div style="margin:0 0 12px;padding:15px 16px;border:1px solid #333b2b;border-radius:10px;background:#171b14">' .
                    '<div style="font-size:11px;color:#8f9887;letter-spacing:.8px;text-transform:uppercase">Raffle</div>' .
                    '<div style="margin-top:4px;color:#ffffff;font-size:15px;font-weight:700">' . esc_html($reward_name) . '</div>' .
                    $entry_html .
                '</div>';
        }

        $account_url = function_exists('wc_get_account_endpoint_url')
            ? wc_get_account_endpoint_url('rafflelb-entries')
            : home_url('/my-account/rafflelb-entries/');

        $subject = 'Your RaffleLB entries are confirmed — Order #' . $order->get_order_number();

        $message =
        '<div style="margin:0;padding:28px 12px;background:#090b08;font-family:Arial,Helvetica,sans-serif;color:#ffffff">' .
          '<div style="max-width:620px;margin:0 auto;border:1px solid #293021;border-radius:16px;background:#10130d;overflow:hidden">' .
            '<div style="padding:22px 24px;border-bottom:1px solid #293021">' .
              '<div style="font-size:20px;font-weight:800;letter-spacing:.5px">RAFFLE<span style="color:#caff16">LB</span></div>' .
            '</div>' .
            '<div style="padding:26px 24px">' .
              '<div style="display:inline-block;padding:7px 10px;border-radius:999px;background:#252b20;color:#caff16;font-size:11px;font-weight:800;letter-spacing:1px">PAID</div>' .
              '<h2 style="margin:16px 0 10px;color:#ffffff;font-size:23px">Your entries are confirmed</h2>' .
              '<p style="margin:0 0 18px;color:#c7cec0;font-size:14px;line-height:1.65">Hello ' . esc_html($customer_name) . ', payment for RaffleLB order <strong style="color:#ffffff">#' . esc_html($order->get_order_number()) . '</strong> has been received and your entries are active.</p>' .
              $reward_blocks .
              '<div style="margin:18px 0;padding:15px 16px;border-left:3px solid #caff16;background:#151912">' .
                '<div style="font-size:11px;color:#8f9887;letter-spacing:.8px;text-transform:uppercase">Order Total</div>' .
                '<div style="margin-top:5px;color:#ffffff;font-size:18px;font-weight:800">' . wp_kses_post($order->get_formatted_order_total()) . '</div>' .
              '</div>' .
              '<p style="margin:0 0 20px;color:#c7cec0;font-size:14px;line-height:1.65">Keep your entry numbers safe. You can view your active entries and draw results at any time from your RaffleLB account.</p>' .
              '<a href="' . esc_url($account_url) . '" style="display:inline-block;padding:13px 18px;border-radius:9px;background:#caff16;color:#080908;text-decoration:none;font-weight:800;font-size:13px">VIEW MY ENTRIES</a>' .
            '</div>' .
          '</div>' .
        '</div>';

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: RaffleLB <notifications@rafflelb.com>',
        ];

        $sent = wp_mail($to, $subject, $message, $headers);

        if ($sent) {
            $order->update_meta_data('_rafflelb_order_confirmation_email_sent_at', current_time('mysql'));
            $order->save();
            $order->add_order_note('RaffleLB: branded paid-entry confirmation email sent to ' . $to . '.');
        } else {
            $order->add_order_note('RaffleLB: branded paid-entry confirmation email could not be sent.');
        }

        return (bool)$sent;
    }


    public static function email_subject_paid_order($subject, $order) {
        if (!$order instanceof WC_Order || !self::order_contains_raffle_entries($order)) {
            return $subject;
        }

        return 'Your RaffleLB entries are confirmed — Order #' . $order->get_order_number();
    }

    public static function email_heading_paid_order($heading, $order) {
        if (!$order instanceof WC_Order || !self::order_contains_raffle_entries($order)) {
            return $heading;
        }

        return 'Your entries are confirmed';
    }

    public static function email_subject_completed_order($subject, $order) {
        if (!$order instanceof WC_Order || !self::order_contains_raffle_entries($order)) {
            return $subject;
        }

        return 'RaffleLB order #' . $order->get_order_number() . ' is complete';
    }

    public static function email_heading_completed_order($heading, $order) {
        if (!$order instanceof WC_Order || !self::order_contains_raffle_entries($order)) {
            return $heading;
        }

        return 'Your RaffleLB entry order is complete';
    }

    public static function rafflelb_order_status_labels($statuses) {
        // Keep WooCommerce's internal status slug as "processing" so payment
        // gateways, entry generation and existing draw logic continue to work.
        // Only change the human-facing label.
        if (isset($statuses['wc-processing'])) {
            $statuses['wc-processing'] = 'Paid';
        }
        return $statuses;
    }


    public static function remove_customer_cancel_action($actions, $order) {
        // Account presentation now lives exclusively in the RaffleLB Account plugin.
        // Keep this callback/function as a compatibility bridge so existing hook
        // identities, priorities, and third-party callers remain stable.
        if (class_exists('RaffleLB_Account', false) && RaffleLB_Account::ready()) {
            return RaffleLB_Account::remove_customer_cancel_action(...func_get_args());
        }

        return $actions;
    }

    public static function account_entries_grouped(){
        // Account presentation now lives exclusively in the RaffleLB Account plugin.
        // Keep this callback/function as a compatibility bridge so existing hook
        // identities, priorities, and third-party callers remain stable.
        if (class_exists('RaffleLB_Account', false) && RaffleLB_Account::ready()) {
            return RaffleLB_Account::account_entries_grouped(...func_get_args());
        }

        return;
    }

    public static function account_entries(){
        // Account presentation now lives exclusively in the RaffleLB Account plugin.
        // Keep this callback/function as a compatibility bridge so existing hook
        // identities, priorities, and third-party callers remain stable.
        if (class_exists('RaffleLB_Account', false) && RaffleLB_Account::ready()) {
            return RaffleLB_Account::account_entries(...func_get_args());
        }

        return;
    }




    public static function ensure_winners_page() {
        if (get_option('rafflelb_winners_page_id')) {
            $existing_id = absint(get_option('rafflelb_winners_page_id'));
            if ($existing_id && get_post_status($existing_id)) return;
        }

        $existing = get_page_by_path('winners');
        if ($existing) {
            update_option('rafflelb_winners_page_id', $existing->ID);
            return;
        }

        if (!current_user_can('manage_options') && !is_admin()) return;

        $page_id = wp_insert_post([
            'post_title'   => 'Winners',
            'post_name'    => 'winners',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '[rafflelb_winners]',
        ]);

        if (!is_wp_error($page_id) && $page_id) {
            update_option('rafflelb_winners_page_id', $page_id);
        }
    }


    public static function winners_body_class($classes) {
        $winners_page_id = absint(get_option('rafflelb_winners_page_id'));
        if ($winners_page_id && is_page($winners_page_id)) {
            $classes[] = 'rafflelb-winners-public-page';
        } elseif (is_page('winners')) {
            $classes[] = 'rafflelb-winners-public-page';
        }
        return $classes;
    }

    public static function homepage_hero_products_inject() {
        if (class_exists('RaffleLB_Homepage', false) && RaffleLB_Homepage::ready()) {
            return RaffleLB_Homepage::homepage_hero_products_inject(...func_get_args());
        }
        return;
    }

    public static function homepage_live_raffles_inject() {
        if (class_exists('RaffleLB_Homepage', false) && RaffleLB_Homepage::ready()) {
            return RaffleLB_Homepage::homepage_live_raffles_inject(...func_get_args());
        }
        return;
    }


    public static function featured_products_shortcode($atts = []) {
        if (class_exists('RaffleLB_Homepage', false) && RaffleLB_Homepage::ready()) {
            return RaffleLB_Homepage::featured_products_shortcode(...func_get_args());
        }
        return '';
    }


    public static function shop_categories_shortcode($atts = []) {
        if (class_exists('RaffleLB_Homepage', false) && RaffleLB_Homepage::ready()) {
            return RaffleLB_Homepage::shop_categories_shortcode(...func_get_args());
        }
        return '';
    }


    public static function raffles_marketplace_shortcode($atts = []) {
        // Shop presentation now lives exclusively in the RaffleLB Shop plugin.
        // Keep this callback as a compatibility delegate so existing Draw Engine
        // hook registrations and third-party callers remain stable.
        if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
            return RaffleLB_Shop::raffles_marketplace_shortcode(...func_get_args());
        }

        return '';
    }

    public static function live_raffles_shortcode() {
        if (class_exists('RaffleLB_Homepage', false) && RaffleLB_Homepage::ready()) {
            return RaffleLB_Homepage::live_raffles_shortcode(...func_get_args());
        }
        return '';
    }

    public static function recent_winners_shortcode($atts = []) {
        if (class_exists('RaffleLB_Homepage', false) && RaffleLB_Homepage::ready()) {
            return RaffleLB_Homepage::recent_winners_shortcode(...func_get_args());
        }
        return '';
    }

    /**
     * RaffleLB homepage community features:
     * - Curated, admin-approved community reviews.
     * - Public "Request a Raffle" form stored privately in WordPress admin.
     */
    public static function register_community_content_types() {
        register_post_type('rafflelb_review', [
            'labels' => [
                'name' => 'RaffleLB Reviews',
                'singular_name' => 'RaffleLB Review',
                'add_new_item' => 'Add Community Review',
                'edit_item' => 'Edit Community Review',
                'menu_name' => 'RaffleLB Reviews',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'woocommerce',
            'supports' => ['title', 'editor'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);

        register_post_type('rafflelb_request', [
            'labels' => [
                'name' => 'Raffle Requests',
                'singular_name' => 'Raffle Request',
                'edit_item' => 'View Raffle Request',
                'menu_name' => 'Raffle Requests',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'woocommerce',
            'supports' => ['title'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }

    public static function community_meta_boxes() {
        add_meta_box(
            'rafflelb_review_details',
            'Review Details',
            [__CLASS__, 'review_meta_box'],
            'rafflelb_review',
            'side',
            'default'
        );

        add_meta_box(
            'rafflelb_request_details',
            'Request Details',
            [__CLASS__, 'request_meta_box'],
            'rafflelb_request',
            'normal',
            'high'
        );
    }

    public static function review_meta_box($post) {
        wp_nonce_field('rafflelb_save_review_meta', 'rafflelb_review_meta_nonce');
        $rating = max(1, min(5, absint(get_post_meta($post->ID, '_rafflelb_review_rating', true) ?: 5)));
        $raffle = (string) get_post_meta($post->ID, '_rafflelb_review_raffle', true);
        ?>
        <p>
            <label for="rafflelb_review_rating"><strong>Rating</strong></label><br>
            <select name="rafflelb_review_rating" id="rafflelb_review_rating" style="width:100%;">
                <?php for ($i = 5; $i >= 1; $i--): ?>
                    <option value="<?php echo esc_attr($i); ?>" <?php selected($rating, $i); ?>>
                        <?php echo esc_html($i); ?> / 5
                    </option>
                <?php endfor; ?>
            </select>
        </p>
        <p>
            <label for="rafflelb_review_raffle"><strong>Raffle / Prize (optional)</strong></label><br>
            <input type="text" class="widefat" id="rafflelb_review_raffle" name="rafflelb_review_raffle" value="<?php echo esc_attr($raffle); ?>">
        </p>
        <p style="color:#646970;font-size:12px;line-height:1.45;">
            Use the post title for the customer's display name and the editor for the real review text.
            Only published reviews are shown publicly.
        </p>
        <?php
    }

    public static function save_review_meta($post_id) {
        if (!isset($_POST['rafflelb_review_meta_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['rafflelb_review_meta_nonce'])), 'rafflelb_save_review_meta')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $rating = isset($_POST['rafflelb_review_rating']) ? absint($_POST['rafflelb_review_rating']) : 5;
        $rating = max(1, min(5, $rating));
        update_post_meta($post_id, '_rafflelb_review_rating', $rating);

        $raffle = isset($_POST['rafflelb_review_raffle'])
            ? sanitize_text_field(wp_unslash($_POST['rafflelb_review_raffle']))
            : '';
        if ($raffle !== '') {
            update_post_meta($post_id, '_rafflelb_review_raffle', $raffle);
        } else {
            delete_post_meta($post_id, '_rafflelb_review_raffle');
        }
    }

    public static function handle_review_submission() {
        $referer = wp_get_referer() ?: home_url('/');
        if (!isset($_POST['rafflelb_review_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['rafflelb_review_nonce'])), 'rafflelb_submit_review')) { wp_safe_redirect(add_query_arg('review_submit','security',$referer).'#community-reviews'); exit; }
        if (!empty($_POST['review_website'])) { wp_safe_redirect(add_query_arg('review_submit','success',$referer).'#community-reviews'); exit; }
        $name = isset($_POST['review_name']) ? sanitize_text_field(wp_unslash($_POST['review_name'])) : '';
        $rating = isset($_POST['review_rating']) ? absint($_POST['review_rating']) : 0;
        $text = isset($_POST['review_text']) ? sanitize_textarea_field(wp_unslash($_POST['review_text'])) : '';
        $order_number = isset($_POST['review_order_number']) ? sanitize_text_field(wp_unslash($_POST['review_order_number'])) : '';
        if ($name === '' || $text === '' || $rating < 1 || $rating > 5) { wp_safe_redirect(add_query_arg('review_submit','missing',$referer).'#community-reviews'); exit; }
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $rate_key = 'rafflelb_review_' . md5($ip ?: wp_generate_uuid4());
        if ($ip && get_transient($rate_key)) { wp_safe_redirect(add_query_arg('review_submit','rate',$referer).'#community-reviews'); exit; }
        $review_id = wp_insert_post(['post_type'=>'rafflelb_review','post_status'=>'pending','post_title'=>$name,'post_content'=>$text], true);
        if (is_wp_error($review_id)) { wp_safe_redirect(add_query_arg('review_submit','error',$referer).'#community-reviews'); exit; }
        update_post_meta($review_id,'_rafflelb_review_rating',$rating);
        update_post_meta($review_id,'_rafflelb_review_order_number',$order_number);
        update_post_meta($review_id,'_rafflelb_review_submitted',current_time('mysql'));
        if ($ip) set_transient($rate_key,1,2 * MINUTE_IN_SECONDS);
        wp_mail('info@rafflelb.com','New RaffleLB Review Submitted',"A new review is waiting for approval.\n\nName: {$name}\nRating: {$rating}/5\nOrder: ".($order_number ?: '—')."\n\nReview:\n{$text}\n\nReview it: ".admin_url('post.php?post='.absint($review_id).'&action=edit'));
        wp_safe_redirect(add_query_arg('review_submit','success',$referer).'#community-reviews'); exit;
    }

    public static function request_meta_box($post) {
        $fields = [
            'Item requested' => '_rafflelb_request_item',
            'Brand' => '_rafflelb_request_brand',
            'Category' => '_rafflelb_request_category',
            'Product link' => '_rafflelb_request_link',
            'Contact' => '_rafflelb_request_contact',
            'Notes' => '_rafflelb_request_notes',
            'Submitted' => '_rafflelb_request_submitted',
        ];
        echo '<table class="widefat striped"><tbody>';
        foreach ($fields as $label => $key) {
            $value = (string) get_post_meta($post->ID, $key, true);
            if ($key === '_rafflelb_request_link' && $value) {
                $display = '<a href="' . esc_url($value) . '" target="_blank" rel="noopener">' . esc_html($value) . '</a>';
            } else {
                $display = nl2br(esc_html($value ?: '—'));
            }
            echo '<tr><th style="width:160px;">' . esc_html($label) . '</th><td>' . $display . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    public static function append_home_community_sections($content) {
        if (class_exists('RaffleLB_Homepage', false) && RaffleLB_Homepage::ready()) {
            return RaffleLB_Homepage::append_home_community_sections(...func_get_args());
        }
        return;
    }

    public static function handle_raffle_request() {
        $referer = wp_get_referer() ?: home_url('/');

        if (!isset($_POST['rafflelb_request_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['rafflelb_request_nonce'])), 'rafflelb_submit_raffle_request')) {
            wp_safe_redirect(add_query_arg('raffle_request', 'security', $referer) . '#request-a-raffle');
            exit;
        }

        // Honeypot: silently treat bot submissions as successful.
        if (!empty($_POST['website'])) {
            wp_safe_redirect(add_query_arg('raffle_request', 'success', $referer) . '#request-a-raffle');
            exit;
        }

        $item = isset($_POST['item']) ? sanitize_text_field(wp_unslash($_POST['item'])) : '';
        $brand = isset($_POST['brand']) ? sanitize_text_field(wp_unslash($_POST['brand'])) : '';
        $category = isset($_POST['category']) ? sanitize_text_field(wp_unslash($_POST['category'])) : '';
        $link = isset($_POST['product_link']) ? esc_url_raw(wp_unslash($_POST['product_link'])) : '';
        $contact = isset($_POST['contact']) ? sanitize_text_field(wp_unslash($_POST['contact'])) : '';
        $notes = isset($_POST['notes']) ? sanitize_textarea_field(wp_unslash($_POST['notes'])) : '';

        if ($item === '' || $contact === '') {
            wp_safe_redirect(add_query_arg('raffle_request', 'missing', $referer) . '#request-a-raffle');
            exit;
        }

        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $rate_key = 'rafflelb_req_' . md5($ip ?: wp_generate_uuid4());
        if ($ip && get_transient($rate_key)) {
            wp_safe_redirect(add_query_arg('raffle_request', 'rate', $referer) . '#request-a-raffle');
            exit;
        }

        $request_id = wp_insert_post([
            'post_type' => 'rafflelb_request',
            'post_status' => 'private',
            'post_title' => wp_trim_words($item, 12, ''),
        ], true);

        if (is_wp_error($request_id)) {
            wp_safe_redirect(add_query_arg('raffle_request', 'error', $referer) . '#request-a-raffle');
            exit;
        }

        $submitted = current_time('mysql');
        update_post_meta($request_id, '_rafflelb_request_item', $item);
        update_post_meta($request_id, '_rafflelb_request_brand', $brand);
        update_post_meta($request_id, '_rafflelb_request_category', $category);
        update_post_meta($request_id, '_rafflelb_request_link', $link);
        update_post_meta($request_id, '_rafflelb_request_contact', $contact);
        update_post_meta($request_id, '_rafflelb_request_notes', $notes);
        update_post_meta($request_id, '_rafflelb_request_submitted', $submitted);

        if ($ip) {
            set_transient($rate_key, 1, 2 * MINUTE_IN_SECONDS);
        }

        $notify = 'info@rafflelb.com';
        $subject = 'New Raffle Request: ' . $item;
        $body = "A new raffle request was submitted.\n\n"
              . "Item: {$item}\n"
              . "Brand: " . ($brand ?: '—') . "\n"
              . "Category: " . ($category ?: '—') . "\n"
              . "Product link: " . ($link ?: '—') . "\n"
              . "Contact: {$contact}\n"
              . "Notes: " . ($notes ?: '—') . "\n\n"
              . "View it in WordPress: " . admin_url('post.php?post=' . absint($request_id) . '&action=edit');
        wp_mail($notify, $subject, $body);

        wp_safe_redirect(add_query_arg('raffle_request', 'success', $referer) . '#request-a-raffle');
        exit;
    }

    public static function community_sections_shortcode() {
        if (class_exists('RaffleLB_Homepage', false) && RaffleLB_Homepage::ready()) {
            return RaffleLB_Homepage::community_sections_shortcode(...func_get_args());
        }
        return '';
    }

    public static function winners_shortcode_premium_v3378() {
        global $wpdb;

        $results = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}" . self::RESULT_TABLE . " ORDER BY selected_at DESC, id DESC"
        );
        $count = count($results);
        $cards = [];
        $categories = [];

        foreach ($results as $result) {
            $pid = absint($result->product_id);
            $product = function_exists('wc_get_product') ? wc_get_product($pid) : false;
            $title = $product ? $product->get_name() : get_the_title($pid);
            $image = ($product && $product->get_image_id())
                ? wp_get_attachment_image_url($product->get_image_id(), 'large')
                : '';
            $url = get_permalink($pid);
            $winner = self::winner_masked_name($result);
            $winner_parts = preg_split('/\s+/', trim($winner));
            $initials = '';
            foreach (array_slice((array)$winner_parts, 0, 2) as $part) {
                $clean_part = preg_replace('/[^\p{L}\p{N}]/u', '', (string)$part);
                if ($clean_part !== '') {
                    $initials .= function_exists('mb_substr') ? mb_substr($clean_part, 0, 1) : substr($clean_part, 0, 1);
                }
            }
            $initials = strtoupper($initials ?: 'W');

            $location = '';
            $order = function_exists('wc_get_order') ? wc_get_order(absint($result->order_id)) : false;
            if ($order) {
                $city = trim((string)$order->get_billing_city());
                $country_code = strtoupper(trim((string)$order->get_billing_country()));
                $country_name = '';
                if ($country_code && function_exists('WC') && WC() && WC()->countries) {
                    $country_names = WC()->countries->get_countries();
                    $country_name = isset($country_names[$country_code]) ? $country_names[$country_code] : '';
                }
                $location = implode(', ', array_filter([$city, $country_name]));
            }
            if ($location === '') $location = 'Lebanon';

            $terms = wp_get_post_terms($pid, 'product_cat');
            $category_slug = 'other';
            $category_name = 'Other';
            if (!is_wp_error($terms) && $terms) {
                $term = reset($terms);
                $category_slug = sanitize_title($term->slug ?: $term->name);
                $category_name = $term->name;
            }
            $categories[$category_slug] = $category_name;

            $cards[] = [
                'result'   => $result,
                'pid'      => $pid,
                'title'    => $title ?: __('Raffle prize', 'rafflelb'),
                'image'    => $image,
                'url'      => $url,
                'winner'   => $winner,
                'initials' => $initials,
                'location' => $location,
                'category' => $category_slug,
                'date'     => mysql2date('j M Y', $result->selected_at),
                'stamp'    => strtotime((string)$result->selected_at) ?: 0,
                'entry'    => '#' . str_pad((string)absint($result->entry_number), 3, '0', STR_PAD_LEFT),
            ];
        }
        asort($categories, SORT_NATURAL | SORT_FLAG_CASE);

        ob_start();
        ?>
        <main class="rlwin" id="rafflelb-winners-directory">
            <section class="rlwin-hero">
                <div class="rlwin-hero-inner<?php echo $count ? '' : ' is-empty'; ?>">
                    <div class="rlwin-intro">
                        <span class="rlwin-eyebrow">VERIFIED RAFFLELB RESULTS</span>
                        <h1>Our Winners</h1>
                        <p class="rlwin-lede">The full record of every completed draw — winner, prize, ticket and date.</p>
                        <a class="rlwin-play" href="<?php echo esc_url('https://rafflelb.com/shop/?rl_view=raffle#rl-shop-controls'); ?>">RAFFLE NOW <span aria-hidden="true">→</span></a>
                        <?php if ($count): ?>
                        <div class="rlwin-tally"><strong><?php echo esc_html($count); ?></strong><span>draw<?php echo $count === 1 ? '' : 's'; ?> completed and verified</span></div>
                        <?php endif; ?>
                    </div>

                    <?php if ($count && isset($cards[0])): $latest = $cards[0]; ?>
                    <a class="rlwin-spotlight" href="<?php echo esc_url($latest['url']); ?>" aria-label="View <?php echo esc_attr($latest['title']); ?> draw result">
                        <span class="rlwin-spotlight-tag">MOST RECENT WIN</span>
                        <div class="rlwin-spotlight-media">
                            <?php if ($latest['image']): ?>
                                <img src="<?php echo esc_url($latest['image']); ?>" alt="<?php echo esc_attr($latest['title']); ?>" loading="lazy">
                            <?php else: ?>
                                <span class="rlwin-placeholder" aria-hidden="true">R</span>
                            <?php endif; ?>
                        </div>
                        <div class="rlwin-spotlight-body">
                            <span class="rlwin-spotlight-date"><?php echo esc_html($latest['date']); ?></span>
                            <h2><?php echo esc_html($latest['winner']); ?></h2>
                            <p><?php echo esc_html($latest['title']); ?></p>
                            <div class="rlwin-spotlight-stub"><span>Winning Ticket</span><strong><?php echo esc_html($latest['entry']); ?></strong></div>
                        </div>
                    </a>
                    <?php endif; ?>
                </div>
            </section>

            <section class="rlwin-directory">
                <div class="rlwin-directory-inner">
                    <div class="rlwin-directory-head<?php echo $count ? '' : ' is-empty'; ?>">
                        <div><span>LATEST RESULTS</span><h2>Meet our winners</h2><p>Search completed draws or browse by prize category.</p></div>
                        <?php if ($count): ?>
                        <div class="rlwin-tools">
                            <label class="rlwin-search">
                                <span aria-hidden="true"></span>
                                <input type="search" data-rlwin-search placeholder="Search winners or prizes…" aria-label="Search winners or prizes">
                            </label>
                            <label class="rlwin-sort">
                                <span class="screen-reader-text">Sort winners</span>
                                <select data-rlwin-sort aria-label="Sort winners">
                                    <option value="newest">Newest First</option>
                                    <option value="oldest">Oldest First</option>
                                    <option value="title">Prize A–Z</option>
                                </select>
                            </label>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($count): ?>
                    <nav class="rlwin-filters" aria-label="Winner categories">
                        <button type="button" class="is-active" data-rlwin-filter="all">All Winners <span><?php echo esc_html($count); ?></span></button>
                        <?php foreach ($categories as $slug => $name): ?>
                            <button type="button" data-rlwin-filter="<?php echo esc_attr($slug); ?>"><?php echo esc_html($name); ?></button>
                        <?php endforeach; ?>
                    </nav>

                    <div class="rlwin-grid" data-rlwin-grid>
                        <?php foreach ($cards as $card):
                            $search_text = strtolower(wp_strip_all_tags($card['title'].' '.$card['winner'].' '.$card['location']));
                        ?>
                        <article class="rlwin-card" data-category="<?php echo esc_attr($card['category']); ?>" data-search="<?php echo esc_attr($search_text); ?>" data-date="<?php echo esc_attr($card['stamp']); ?>" data-title="<?php echo esc_attr(strtolower($card['title'])); ?>">
                            <a class="rlwin-media" href="<?php echo esc_url($card['url']); ?>" aria-label="View <?php echo esc_attr($card['title']); ?> draw result">
                                <?php if ($card['image']): ?>
                                    <img src="<?php echo esc_url($card['image']); ?>" alt="<?php echo esc_attr($card['title']); ?>" loading="lazy">
                                <?php else: ?>
                                    <span class="rlwin-placeholder" aria-hidden="true">R</span>
                                <?php endif; ?>
                                <span class="rlwin-verified"><b aria-hidden="true">✓</b> Verified Draw</span>
                            </a>
                            <div class="rlwin-card-body">
                                <h3><a href="<?php echo esc_url($card['url']); ?>"><?php echo esc_html($card['title']); ?></a></h3>
                                <div class="rlwin-person">
                                    <span class="rlwin-avatar" aria-hidden="true"><?php echo esc_html($card['initials']); ?></span>
                                    <div><strong><?php echo esc_html($card['winner']); ?></strong><small><i aria-hidden="true">⌖</i> <?php echo esc_html($card['location']); ?></small></div>
                                </div>
                                <div class="rlwin-result-meta"><span>Draw Date: <strong><?php echo esc_html($card['date']); ?></strong></span><span>Winning Ticket: <strong><?php echo esc_html($card['entry']); ?></strong></span></div>
                                <a class="rlwin-details" href="<?php echo esc_url($card['url']); ?>">View Draw Details <span aria-hidden="true">→</span></a>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    </div>
                    <div class="rlwin-no-results" data-rlwin-empty hidden><strong>No matching winners</strong><span>Try another search or category.</span></div>
                    <?php else: ?>
                    <div class="rlwin-empty">
                        <div class="rlwin-empty-mark" aria-hidden="true"><span>✓</span></div>
                        <div><span>THE WINNERS WALL IS READY</span><h3>Our first winner will appear here.</h3><p>Completed RaffleLB draws are published automatically with the verified winning ticket and result date.</p><a href="<?php echo esc_url('https://rafflelb.com/shop/?rl_view=raffle#rl-shop-controls'); ?>">EXPLORE LIVE RAFFLES <b aria-hidden="true">→</b></a></div>
                    </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,560;0,9..144,650;1,9..144,560&display=swap" rel="stylesheet">
        <style id="rafflelb-winners-premium-v03392">
        body.rafflelb-winners-public-page .main-page-wrapper,body.rafflelb-winners-public-page .site-content,body.rafflelb-winners-public-page .wd-content-layout,body.rafflelb-winners-public-page .content-layout-wrapper{background:#070907!important}
        body.rafflelb-winners-public-page .main-page-wrapper,body.rafflelb-winners-public-page .site-content{padding-top:0!important;padding-bottom:0!important}
        /* v0.33.92: a dedicated display face (used only for winner names and
           the page's own headlines) so this reads as a record of real people,
           not another dashboard panel set entirely in the UI grotesque. */
        .rlwin,.rlwin *{box-sizing:border-box}.rlwin{--lime:#baff00;--bg:#070907;--panel:#0c100c;--line:#293128;--display:'Fraunces',Georgia,serif;position:relative;left:50%;width:100vw;max-width:none;margin-left:-50vw;overflow-x:hidden;background:var(--bg);color:#fff;font-family:Inter,"Segoe UI",Arial,sans-serif!important;-webkit-font-smoothing:antialiased}.rlwin a{text-decoration:none!important}
        .rlwin-hero{position:relative;overflow:hidden;border-bottom:1px solid rgba(255,255,255,.07);background:radial-gradient(circle at 76% 0,rgba(186,255,0,.075),transparent 31%),linear-gradient(135deg,#050705,#090c09)}
        .rlwin-hero:after{content:"GOOD THINGS HAPPEN HERE";position:absolute;right:3.5%;bottom:18px;color:rgba(186,255,0,.09);font-size:clamp(22px,3.2vw,54px);font-weight:900;font-style:italic;letter-spacing:-.04em;transform:rotate(-5deg);pointer-events:none}
        .rlwin-hero-inner{position:relative;z-index:2;display:grid;grid-template-columns:minmax(400px,.86fr) minmax(400px,.9fr);align-items:center;gap:52px;max-width:1560px;margin:0 auto;padding:56px 34px 60px}
        .rlwin-hero-inner.is-empty{grid-template-columns:1fr;max-width:760px;text-align:center}.rlwin-hero-inner.is-empty .rlwin-eyebrow{justify-content:center}
        .rlwin-eyebrow{display:flex;align-items:center;gap:8px;margin-bottom:16px;color:var(--lime)!important;font-size:11px!important;font-weight:850!important;letter-spacing:.13em!important}.rlwin-eyebrow:before{content:"";width:6px;height:6px;flex:0 0 6px;border-radius:50%;background:var(--lime);box-shadow:0 0 10px rgba(186,255,0,.5)}
        .rlwin-intro h1{margin:0!important;color:#fff!important;font-family:var(--display)!important;font-size:clamp(52px,4.6vw,74px)!important;line-height:.98!important;font-weight:650!important;letter-spacing:-.01em!important}
        .rlwin-lede{max-width:420px;margin:16px 0 24px!important;color:#aeb6aa!important;font-size:15px!important;line-height:1.6!important}
        /* v0.33.94: text-align:center (from .rlwin-hero-inner.is-empty) only
           centers the wrapped lines inside this paragraph's own box - the
           box itself stays pinned left by its zero left/right margin, so it
           reads as off-center under the fully-centered "Our Winners" above
           it. Centering the box itself only in the empty state; the
           non-empty hero's lede stays left-aligned under its own headline. */
        .rlwin-hero-inner.is-empty .rlwin-lede{margin-left:auto!important;margin-right:auto!important}
        /* v0.33.95: was the only icon+arrow button on this page using
           justify-content:space-between instead of a fixed gap (see
           .rlwin-details / .rlwin-empty a below) - space-between only
           keeps text and arrow close together when the button happens to
           shrink-wrap exactly to its content; any extra width (a wider
           viewport, an external rule) shoves the arrow to the far edge
           instead, which is why the gap looked loose and inconsistent on
           mobile versus tight on desktop. width:max-content also stops
           anything outside this rule from stretching the button at all. */
        .rlwin-play{display:inline-flex!important;width:max-content!important;min-width:165px;min-height:46px;align-items:center;justify-content:center;gap:12px;padding:0 20px;border:1px solid var(--lime);border-radius:8px;background:var(--lime);color:#050705!important;-webkit-text-fill-color:#050705!important;font-size:12px!important;font-weight:850!important}.rlwin-play:hover{box-shadow:0 10px 26px rgba(186,255,0,.18)}
        .rlwin-tally{display:flex;align-items:baseline;gap:9px;margin-top:26px;padding-top:22px;border-top:1px solid rgba(255,255,255,.09)}.rlwin-tally strong{color:var(--lime)!important;font-size:26px!important;font-weight:800!important;line-height:1!important}.rlwin-tally span{color:#929b90!important;font-size:13px!important}
        .rlwin-spotlight{position:relative;display:block;overflow:hidden;border:1px solid var(--line);border-radius:20px;background:var(--panel);box-shadow:0 24px 60px rgba(0,0,0,.35);transition:transform .2s ease,border-color .2s ease}.rlwin-spotlight:hover{transform:translateY(-3px);border-color:rgba(186,255,0,.4)}
        .rlwin-spotlight-tag{position:absolute;z-index:3;top:16px;left:16px;display:inline-flex;align-items:center;min-height:27px;padding:0 12px;border-radius:999px;background:var(--lime);color:#050705!important;font-size:10px!important;font-weight:850!important;letter-spacing:.08em!important}
        .rlwin-spotlight-media{position:relative;display:flex;height:240px;align-items:center;justify-content:center;overflow:hidden;background:#070a07}.rlwin-spotlight-media img{display:block!important;width:100%!important;height:100%!important;object-fit:cover!important;object-position:center!important}.rlwin-spotlight-media .rlwin-placeholder{color:var(--lime)!important;font-family:var(--display)!important;font-size:64px!important;font-weight:650!important}
        .rlwin-spotlight-body{padding:22px 26px 26px}.rlwin-spotlight-date{display:block;margin-bottom:8px;color:#8f978c!important;font-size:11px!important;font-weight:700!important;letter-spacing:.07em!important;text-transform:uppercase}
        .rlwin-spotlight-body h2{margin:0!important;color:#fff!important;font-family:var(--display)!important;font-size:32px!important;line-height:1.08!important;font-weight:650!important}
        .rlwin-spotlight-body>p{margin:6px 0 0!important;color:#b6bdb2!important;font-size:15px!important;line-height:1.4!important}
        .rlwin-spotlight-stub{position:relative;display:flex;align-items:center;justify-content:space-between;margin-top:20px;padding-top:18px;border-top:1px dashed rgba(255,255,255,.22)}
        .rlwin-spotlight-stub:before,.rlwin-spotlight-stub:after{content:"";position:absolute;top:-9px;width:18px;height:18px;border-radius:50%;background:var(--bg);border:1px solid var(--line)}.rlwin-spotlight-stub:before{left:-27px}.rlwin-spotlight-stub:after{right:-27px}
        .rlwin-spotlight-stub span{color:#8f978c!important;font-size:11px!important;font-weight:700!important;letter-spacing:.05em!important;text-transform:uppercase}.rlwin-spotlight-stub strong{color:var(--lime)!important;font-size:17px!important;font-weight:800!important;letter-spacing:.02em!important}
        .rlwin-directory{padding:34px 28px 54px}.rlwin-directory-inner{max-width:1660px;margin:0 auto}.rlwin-directory-head{display:flex;align-items:flex-end;justify-content:space-between;gap:30px;margin-bottom:18px}.rlwin-directory-head>div:first-child>span{display:block;margin-bottom:6px;color:var(--lime)!important;font-size:10px!important;font-weight:850!important;letter-spacing:.12em!important}.rlwin-directory-head h2{margin:0!important;color:#fff!important;font-family:var(--display)!important;font-size:32px!important;line-height:1.15!important;font-weight:650!important}.rlwin-directory-head p{margin:6px 0 0!important;color:#929b90!important;font-size:13px!important;line-height:1.45!important}
        /* v0.33.93: with zero winners recorded, the search/sort toolbar this
           row normally balances against (justify-content:space-between)
           doesn't render, so the lone heading gets pinned to the left edge
           instead of appearing centered - same treatment as the hero above. */
        .rlwin-directory-head.is-empty{justify-content:center;max-width:640px;margin-left:auto;margin-right:auto;text-align:center}
        .rlwin-directory-head.is-empty>div:first-child>span{text-align:center}
        .rlwin-tools{display:flex;align-items:center;gap:10px}.rlwin-search{position:relative;display:block;width:300px}.rlwin-search>span{position:absolute;z-index:2;left:15px;top:50%;width:14px;height:14px;border:1.7px solid #aab2a8;border-radius:50%;transform:translateY(-55%)}.rlwin-search>span:after{content:"";position:absolute;width:6px;height:1.7px;right:-5px;bottom:-2px;background:#aab2a8;transform:rotate(45deg)}.rlwin-search input,.rlwin-sort select{height:43px!important;margin:0!important;border:1px solid #303830!important;border-radius:10px!important;background:#0d110d!important;color:#fff!important;-webkit-text-fill-color:#fff!important;font-family:Inter,"Segoe UI",Arial,sans-serif!important;font-size:12px!important;box-shadow:none!important}.rlwin-search input{width:100%!important;padding:0 15px 0 43px!important}.rlwin-search input::placeholder{color:#7f887d!important;opacity:1}.rlwin-sort select{min-width:152px!important;padding:0 36px 0 14px!important;cursor:pointer}
        .rlwin-filters{display:flex;gap:0;margin:0 0 15px;overflow-x:auto;border-bottom:1px solid #252c25;scrollbar-width:none}.rlwin-filters::-webkit-scrollbar{display:none}.rlwin-filters button{position:relative;flex:0 0 auto;min-height:43px;padding:0 18px;border:0!important;background:transparent!important;color:#b9c0b7!important;font-family:Inter,"Segoe UI",Arial,sans-serif!important;font-size:12px!important;font-weight:650!important;white-space:nowrap;cursor:pointer}.rlwin-filters button span{margin-left:4px;color:inherit!important}.rlwin-filters button.is-active{color:var(--lime)!important}.rlwin-filters button.is-active:after{content:"";position:absolute;left:8px;right:8px;bottom:0;height:3px;border-radius:3px 3px 0 0;background:var(--lime)}
        .rlwin-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px}.rlwin-card{min-width:0;overflow:hidden;border:1px solid var(--line);border-radius:12px;background:linear-gradient(180deg,#101410,#0b0e0b);box-shadow:0 12px 30px rgba(0,0,0,.2);transition:transform .2s,border-color .2s,box-shadow .2s}.rlwin-card:hover{transform:translateY(-3px);border-color:rgba(186,255,0,.46);box-shadow:0 18px 38px rgba(0,0,0,.31)}.rlwin-card[hidden]{display:none!important}
        .rlwin-media{position:relative;display:flex!important;height:185px;align-items:center;justify-content:center;overflow:hidden;border-bottom:1px solid #242b24;background:#070a07}.rlwin-media:after{content:"";position:absolute;inset:55% 0 0;background:linear-gradient(transparent,rgba(0,0,0,.42));pointer-events:none}.rlwin-media img{display:block!important;width:100%!important;height:100%!important;object-fit:cover!important;object-position:center!important;transition:transform .25s}.rlwin-card:hover .rlwin-media img{transform:scale(1.025)}.rlwin-placeholder{color:var(--lime)!important;font-size:56px!important;font-weight:900!important}.rlwin-verified{position:absolute;z-index:3;top:10px;right:10px;display:inline-flex;min-height:27px;align-items:center;gap:5px;padding:0 9px;border:1px solid rgba(186,255,0,.45);border-radius:999px;background:rgba(7,10,7,.92);color:var(--lime)!important;font-size:10px!important;font-weight:750!important}.rlwin-verified b{display:inline-flex;width:15px;height:15px;align-items:center;justify-content:center;border-radius:50%;background:var(--lime);color:#050705!important;font-size:9px!important}
        .rlwin-card-body{padding:12px}.rlwin-card h3{min-height:39px;margin:0 0 10px!important;color:#fff!important;font-size:15px!important;line-height:1.3!important;font-weight:750!important;letter-spacing:-.01em!important}.rlwin-card h3 a{color:#fff!important}.rlwin-person{display:flex;align-items:center;gap:9px;margin-bottom:9px}.rlwin-avatar{display:flex;width:37px;height:37px;flex:0 0 37px;align-items:center;justify-content:center;border:1px solid #414841;border-radius:50%;background:linear-gradient(145deg,#444a44,#252a25);color:#fff!important;font-size:11px!important;font-weight:750!important}.rlwin-person>div{min-width:0}.rlwin-person strong{display:block;overflow:hidden;color:#fff!important;font-size:12px!important;line-height:1.2!important;font-weight:700!important;text-overflow:ellipsis;white-space:nowrap}.rlwin-person small{display:block;margin-top:4px;overflow:hidden;color:#9da69b!important;font-size:10px!important;line-height:1.2!important;text-overflow:ellipsis;white-space:nowrap}.rlwin-person small i{color:var(--lime)!important;font-style:normal}
        .rlwin-result-meta{display:grid;grid-template-columns:1fr;gap:4px;margin:0 0 10px;padding-top:9px;border-top:1px solid rgba(255,255,255,.075)}.rlwin-result-meta span{display:flex;justify-content:space-between;gap:8px;color:#929a90!important;font-size:10px!important;line-height:1.3!important}.rlwin-result-meta strong{color:#e9ede7!important;font-weight:650!important}.rlwin-details{display:flex!important;width:100%;min-height:36px;align-items:center;justify-content:center;gap:12px;border:1px solid var(--lime);border-radius:7px;background:transparent;color:var(--lime)!important;font-size:11px!important;font-weight:750!important}.rlwin-details:hover{background:var(--lime);color:#050705!important}
        .rlwin-empty{display:grid;grid-template-columns:150px minmax(0,1fr);align-items:center;gap:28px;min-height:230px;padding:32px 40px;border:1px solid #293229;border-radius:16px;background:radial-gradient(circle at 12% 50%,rgba(186,255,0,.075),transparent 24%),#0c100c}.rlwin-empty-mark{display:flex;width:125px;height:125px;align-items:center;justify-content:center;border:1px solid rgba(186,255,0,.35);border-radius:50%;box-shadow:0 0 45px rgba(186,255,0,.06)}.rlwin-empty-mark span{display:flex;width:58px;height:58px;align-items:center;justify-content:center;border-radius:50%;background:var(--lime);color:#050705!important;font-size:25px!important;font-weight:900!important}.rlwin-empty>div:last-child>span{display:block;margin-bottom:7px;color:var(--lime)!important;font-size:10px!important;font-weight:850!important;letter-spacing:.12em!important}.rlwin-empty h3{margin:0!important;color:#fff!important;font-family:var(--display)!important;font-size:29px!important;font-weight:650!important}.rlwin-empty p{max-width:680px;margin:9px 0 17px!important;color:#9da69b!important;font-size:13px!important;line-height:1.6!important}.rlwin-empty a{display:inline-flex!important;min-height:40px;align-items:center;gap:24px;padding:0 15px;border:1px solid var(--lime);border-radius:7px;color:var(--lime)!important;font-size:11px!important;font-weight:800!important}.rlwin-empty a:hover{background:var(--lime);color:#050705!important}.rlwin-no-results{padding:32px;border:1px solid var(--line);border-radius:12px;background:#0c100c;text-align:center}.rlwin-no-results strong,.rlwin-no-results span{display:block}.rlwin-no-results strong{color:#fff!important;font-size:18px!important}.rlwin-no-results span{margin-top:5px;color:#919a8e!important;font-size:12px!important}
        @media(max-width:1370px){.rlwin-hero-inner{gap:36px;padding-left:28px;padding-right:28px}.rlwin-grid{grid-template-columns:repeat(4,minmax(0,1fr))}.rlwin-media{height:180px}}
        @media(max-width:1080px){.rlwin-hero-inner{grid-template-columns:1fr;max-width:640px;padding:42px 28px}.rlwin-spotlight{max-width:460px}.rlwin-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
        @media(max-width:820px){.rlwin-directory-head{align-items:stretch;flex-direction:column}.rlwin-tools{width:100%}.rlwin-search{flex:1;width:auto}.rlwin-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.rlwin-media{height:200px}}
        @media(max-width:600px){.rlwin-hero-inner{padding:36px 16px 31px}.rlwin-intro h1{font-size:42px!important}.rlwin-lede{font-size:13px!important}.rlwin-spotlight-body{padding:18px 18px 20px}.rlwin-spotlight-body h2{font-size:26px!important}.rlwin-spotlight-stub:before,.rlwin-spotlight-stub:after{width:16px;height:16px}.rlwin-spotlight-stub:before{left:-25px}.rlwin-spotlight-stub:after{right:-25px}.rlwin-directory{padding:28px 12px 42px}.rlwin-directory-head h2{font-size:25px!important}.rlwin-tools{display:grid;grid-template-columns:minmax(0,1fr) 132px;gap:7px}.rlwin-search input,.rlwin-sort select{font-size:11px!important}.rlwin-sort select{min-width:0!important;width:100%!important}.rlwin-filters button{padding:0 13px;font-size:11px!important}.rlwin-grid{gap:9px}.rlwin-media{height:142px}.rlwin-card-body{padding:10px}.rlwin-card h3{min-height:35px;font-size:13px!important}.rlwin-verified{top:7px;right:7px;min-height:23px;padding:0 7px;font-size:8px!important}.rlwin-person{gap:7px}.rlwin-avatar{width:32px;height:32px;flex-basis:32px;font-size:9px!important}.rlwin-person strong{font-size:10px!important}.rlwin-person small,.rlwin-result-meta span{font-size:8.5px!important}.rlwin-details{min-height:34px;font-size:9px!important}.rlwin-empty{grid-template-columns:1fr;gap:18px;padding:27px 20px;text-align:center}.rlwin-empty-mark{width:95px;height:95px;margin:auto}.rlwin-empty h3{font-size:22px!important}.rlwin-empty p{font-size:12px!important}.rlwin-empty a{justify-content:center}}
        @media(max-width:390px){.rlwin-grid{grid-template-columns:1fr}.rlwin-media{height:205px}.rlwin-card h3{min-height:0;font-size:15px!important}.rlwin-person strong{font-size:12px!important}.rlwin-person small,.rlwin-result-meta span{font-size:10px!important}.rlwin-details{font-size:11px!important}}
        </style>

        <?php if ($count): ?>
        <script id="rafflelb-winners-directory-js-v03378">
        (function(){
            var root=document.getElementById('rafflelb-winners-directory');if(!root)return;
            var grid=root.querySelector('[data-rlwin-grid]'),cards=Array.prototype.slice.call(root.querySelectorAll('.rlwin-card')),buttons=root.querySelectorAll('[data-rlwin-filter]'),search=root.querySelector('[data-rlwin-search]'),sort=root.querySelector('[data-rlwin-sort]'),empty=root.querySelector('[data-rlwin-empty]'),active='all';
            function render(){var query=(search&&search.value||'').trim().toLowerCase(),shown=0;cards.forEach(function(card){var okCat=active==='all'||card.getAttribute('data-category')===active,okSearch=!query||(card.getAttribute('data-search')||'').indexOf(query)!==-1,show=okCat&&okSearch;card.hidden=!show;if(show)shown++;});if(empty)empty.hidden=shown!==0;}
            buttons.forEach(function(button){button.addEventListener('click',function(){active=button.getAttribute('data-rlwin-filter');buttons.forEach(function(item){item.classList.toggle('is-active',item===button)});render();});});
            if(search)search.addEventListener('input',render);
            if(sort)sort.addEventListener('change',function(){var mode=sort.value;cards.sort(function(a,b){if(mode==='oldest')return Number(a.dataset.date)-Number(b.dataset.date);if(mode==='title')return (a.dataset.title||'').localeCompare(b.dataset.title||'');return Number(b.dataset.date)-Number(a.dataset.date);});cards.forEach(function(card){grid.appendChild(card)});render();});
        })();
        </script>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }

    public static function winners_shortcode() {
        return self::winners_shortcode_premium_v3378();

        /* Legacy renderer retained below for rollback/reference. */
        global $wpdb;

        $results = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}" . self::RESULT_TABLE . " ORDER BY selected_at DESC, id DESC"
        );

        $count = count($results);
        ob_start();
        ?>
        <div class="rafflelb-winners-wrap">
            <section class="rafflelb-winners-hero">
                <div class="rafflelb-winners-hero-inner">
                    <span class="rafflelb-winners-kicker">RAFFLELB WINNERS</span>
                    <h1>Real winners.<br><span>Real raffles.</span></h1>
                    <p>Every completed RaffleLB draw is recorded transparently with the winner, winning entry and draw details.</p>

                    <div class="rafflelb-winners-stats">
                        <div><strong><?php echo esc_html($count); ?></strong><span>COMPLETED DRAWS</span></div>
                        <div><strong><?php echo esc_html($count); ?></strong><span>WINNERS RECORDED</span></div>
                        <div><strong>100%</strong><span>RESULTS TRACKED</span></div>
                    </div>
                </div>
            </section>

            <section class="rafflelb-winners-content">
                <div class="rafflelb-winners-section-head">
                    <span>RECENT RESULTS</span>
                    <h2>Latest winners</h2>
                    <p>Completed draws appear here automatically once a winner has been recorded.</p>
                </div>

                <?php if (!$results): ?>
                    <div class="rafflelb-winners-empty">
                        <strong>No completed draws yet.</strong>
                        <span>Completed RaffleLB winners will appear here automatically.</span>
                    </div>
                <?php else: ?>
                    <div class="rafflelb-winners-grid">
                        <?php foreach ($results as $result):
                            $pid = absint($result->product_id);
                            $product = wc_get_product($pid);
                            $winner_name = self::winner_masked_name($result);
                            $entry = '#' . str_pad((string)absint($result->entry_number), 3, '0', STR_PAD_LEFT);
                            $method = (!empty($result->selection_method) && $result->selection_method === 'manual')
                                ? 'Manual / External Result'
                                : 'Secure Random Draw';
                            $image = $product ? wp_get_attachment_image_url($product->get_image_id(), 'large') : '';
                            $url = get_permalink($pid);
                        ?>
                        <article class="rafflelb-winner-card">
                            <a class="rafflelb-winner-image" href="<?php echo esc_url($url); ?>">
                                <?php if ($image): ?>
                                    <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr(get_the_title($pid)); ?>">
                                <?php endif; ?>
                                <span class="rafflelb-winner-badge">🏆 WINNER</span>
                                <span class="rafflelb-winner-entry-chip"><?php echo esc_html($entry); ?></span>
                            </a>

                            <div class="rafflelb-winner-card-body">
                                <div class="rafflelb-winner-card-top">
                                    <span>DRAW COMPLETE</span>
                                    <small><?php echo esc_html(mysql2date('M j, Y', $result->selected_at)); ?></small>
                                </div>

                                <h3><a href="<?php echo esc_url($url); ?>"><?php echo esc_html(get_the_title($pid)); ?></a></h3>

                                <div class="rafflelb-winner-details">
                                    <div>
                                        <small>WINNER</small>
                                        <strong><?php echo esc_html($winner_name); ?></strong>
                                    </div>
                                    <div>
                                        <small>WINNING ENTRY</small>
                                        <strong><?php echo esc_html($entry); ?></strong>
                                    </div>
                                </div>

                                <div class="rafflelb-winner-method">
                                    <span><?php echo esc_html($method); ?></span>
                                    <span><?php echo esc_html(mysql2date('H:i', $result->selected_at)); ?></span>
                                </div>

                                <a class="rafflelb-winner-view" href="<?php echo esc_url($url); ?>">
                                    VIEW RESULT <span>→</span>
                                </a>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <style>
        /* Winners page only. Do NOT restyle WoodMart header/footer containers. */
        body.rafflelb-winners-public-page .main-page-wrapper,
        body.rafflelb-winners-public-page .site-content,
        body.rafflelb-winners-public-page .wd-content-layout,
        body.rafflelb-winners-public-page .content-layout-wrapper{
            background:#090b08 !important;
        }

        body.rafflelb-winners-public-page .main-page-wrapper{
            padding-top:0 !important;
        }

        body.rafflelb-winners-public-page .site-content{
            padding-top:0 !important;
            padding-bottom:0 !important;
        }

        .rafflelb-winners-wrap{
            width:100vw;
            position:relative;
            left:50%;
            margin-left:-50vw;
            background:
                radial-gradient(circle at 20% 9%,rgba(202,255,22,.09),transparent 30%),
                radial-gradient(circle at 83% 15%,rgba(202,255,22,.05),transparent 28%),
                #090b08;
            color:#fff;
        }

        .rafflelb-winners-hero{
            position:relative;
            padding:68px 20px 48px;
            border-bottom:1px solid rgba(255,255,255,.06);
            overflow:hidden;
        }

        .rafflelb-winners-hero:before{
            content:"";
            position:absolute;
            inset:0;
            background:linear-gradient(90deg,transparent,rgba(202,255,22,.035),transparent);
            pointer-events:none;
        }

        .rafflelb-winners-hero-inner{
            position:relative;
            z-index:1;
            max-width:1180px;
            margin:0 auto;
            text-align:center;
        }

        .rafflelb-winners-kicker{
            display:inline-flex;
            align-items:center;
            gap:8px;
            color:#caff16 !important;
            -webkit-text-fill-color:#caff16 !important;
            font-size:11px;
            font-weight:900;
            letter-spacing:.18em;
            margin-bottom:15px;
        }

        .rafflelb-winners-kicker:before,
        .rafflelb-winners-kicker:after{
            content:"";
            width:26px;
            height:1px;
            background:#caff16;
        }

        .rafflelb-winners-hero h1{
            margin:0;
            color:#fff !important;
            -webkit-text-fill-color:#fff !important;
            font-size:clamp(46px,6vw,76px);
            line-height:.94;
            letter-spacing:-.04em;
            font-weight:900;
        }

        .rafflelb-winners-hero h1 span{
            color:#caff16 !important;
            -webkit-text-fill-color:#caff16 !important;
        }

        .rafflelb-winners-hero p{
            max-width:700px;
            margin:20px auto 0;
            color:#aeb5a8 !important;
            -webkit-text-fill-color:#aeb5a8 !important;
            font-size:15px;
            line-height:1.7;
        }

        .rafflelb-winners-stats{
            max-width:760px;
            margin:32px auto 0;
            display:grid;
            grid-template-columns:repeat(3,1fr);
            gap:12px;
        }

        .rafflelb-winners-stats>div{
            padding:16px;
            border:1px solid #2c3227;
            border-radius:14px;
            background:rgba(17,20,15,.88);
        }

        .rafflelb-winners-stats strong{
            display:block;
            color:#fff !important;
            -webkit-text-fill-color:#fff !important;
            font-size:22px;
            margin-bottom:6px;
        }

        .rafflelb-winners-stats span{
            display:block;
            color:#8b9484 !important;
            -webkit-text-fill-color:#8b9484 !important;
            font-size:9px;
            font-weight:800;
            letter-spacing:.1em;
        }

        .rafflelb-winners-content{
            max-width:1180px;
            margin:0 auto;
            padding:40px 20px 58px;
        }

        .rafflelb-winners-section-head{
            margin-bottom:24px;
            max-width:620px;
        }

        .rafflelb-winners-section-head>span{
            display:block;
            color:#caff16 !important;
            -webkit-text-fill-color:#caff16 !important;
            font-size:10px;
            font-weight:900;
            letter-spacing:.14em;
            margin-bottom:7px;
        }

        .rafflelb-winners-section-head h2{
            margin:0 0 8px;
            color:#fff !important;
            -webkit-text-fill-color:#fff !important;
            font-size:30px;
        }

        .rafflelb-winners-section-head p{
            max-width:560px;
            margin:0;
            color:#90998b !important;
            -webkit-text-fill-color:#90998b !important;
            text-align:left;
            font-size:13px;
            line-height:1.55;
        }

        .rafflelb-winners-grid{
            display:grid;
            grid-template-columns:repeat(3,minmax(0,1fr));
            gap:18px;
        }

        .rafflelb-winner-card{
            overflow:hidden;
            border:1px solid #2a3026;
            border-radius:20px;
            background:linear-gradient(180deg,#12150f 0%,#0e110c 100%);
            box-shadow:0 18px 44px rgba(0,0,0,.24);
            transition:transform .2s ease,border-color .2s ease,box-shadow .2s ease;
        }

        .rafflelb-winner-card:hover{
            transform:translateY(-4px);
            border-color:rgba(202,255,22,.42);
            box-shadow:0 22px 52px rgba(0,0,0,.34);
        }

        .rafflelb-winner-image{
            position:relative;
            display:block;
            aspect-ratio:1/1;
            background:#0c0f0a;
            overflow:hidden;
        }

        .rafflelb-winner-image:after{
            content:"";
            position:absolute;
            inset:auto 0 0 0;
            height:38%;
            background:linear-gradient(180deg,transparent,rgba(0,0,0,.55));
            pointer-events:none;
        }

        .rafflelb-winner-image img{
            width:100%;
            height:100%;
            object-fit:cover;
            display:block;
            transition:transform .25s ease;
        }

        .rafflelb-winner-card:hover .rafflelb-winner-image img{
            transform:scale(1.02);
        }

        .rafflelb-winner-badge{
            position:absolute;
            z-index:2;
            left:14px;
            top:14px;
            padding:7px 10px;
            border-radius:999px;
            background:#caff16;
            color:#080908 !important;
            -webkit-text-fill-color:#080908 !important;
            font-size:10px;
            font-weight:900;
            letter-spacing:.08em;
        }

        .rafflelb-winner-entry-chip{
            position:absolute;
            z-index:2;
            right:14px;
            bottom:14px;
            padding:8px 11px;
            border:1px solid rgba(255,255,255,.14);
            border-radius:999px;
            background:rgba(9,11,8,.86);
            color:#fff !important;
            -webkit-text-fill-color:#fff !important;
            font-size:12px;
            font-weight:900;
        }

        .rafflelb-winner-card-body{
            padding:16px;
        }

        .rafflelb-winner-card-top{
            display:flex;
            justify-content:space-between;
            gap:10px;
            margin-bottom:10px;
        }

        .rafflelb-winner-card-top span{
            color:#caff16 !important;
            -webkit-text-fill-color:#caff16 !important;
            font-size:10px;
            font-weight:900;
            letter-spacing:.08em;
        }

        .rafflelb-winner-card-top small{
            color:#7f8878 !important;
            -webkit-text-fill-color:#7f8878 !important;
            font-size:10px;
        }

        .rafflelb-winner-card h3{
            margin:0 0 14px;
            font-size:20px;
            line-height:1.2;
        }

        .rafflelb-winner-card h3 a{
            color:#fff !important;
            -webkit-text-fill-color:#fff !important;
        }

        .rafflelb-winner-details{
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:10px;
            margin-bottom:13px;
        }

        .rafflelb-winner-details>div{
            padding:11px;
            border:1px solid #31372d;
            border-radius:12px;
            background:#171b14;
        }

        .rafflelb-winner-details small{
            display:block;
            margin-bottom:5px;
            color:#8d9686 !important;
            -webkit-text-fill-color:#8d9686 !important;
            font-size:9px;
            font-weight:800;
            letter-spacing:.08em;
        }

        .rafflelb-winner-details strong{
            display:block;
            color:#fff !important;
            -webkit-text-fill-color:#fff !important;
            font-size:14px;
        }

        .rafflelb-winner-method{
            display:flex;
            justify-content:space-between;
            gap:12px;
            margin:0 0 15px;
            color:#7f8878 !important;
            -webkit-text-fill-color:#7f8878 !important;
            font-size:10px;
        }

        .rafflelb-winner-view{
            display:flex;
            align-items:center;
            justify-content:space-between;
            min-height:44px;
            width:100%;
            padding:0 16px;
            border-radius:12px;
            background:#caff16;
            color:#080908 !important;
            -webkit-text-fill-color:#080908 !important;
            font-size:11px;
            font-weight:900;
            letter-spacing:.05em;
        }

        .rafflelb-winner-view span{
            color:#080908 !important;
            -webkit-text-fill-color:#080908 !important;
            font-size:15px;
        }

        .rafflelb-winner-view:hover,
        .rafflelb-winner-view:focus{
            color:#080908 !important;
            -webkit-text-fill-color:#080908 !important;
            background:#bfff00;
        }

        .rafflelb-winners-empty{
            padding:38px;
            border:1px solid #2b3027;
            border-radius:18px;
            background:#11140f;
            text-align:center;
        }

        .rafflelb-winners-empty strong,
        .rafflelb-winners-empty span{display:block}

        .rafflelb-winners-empty strong{
            color:#fff !important;
            -webkit-text-fill-color:#fff !important;
            font-size:21px;
            margin-bottom:7px;
        }

        .rafflelb-winners-empty span{
            color:#8d9686 !important;
            -webkit-text-fill-color:#8d9686 !important;
        }

        @media(max-width:980px){
            .rafflelb-winners-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
        }

        @media(max-width:760px){
            .rafflelb-winners-hero{padding:50px 16px 38px}
            .rafflelb-winners-stats{grid-template-columns:1fr}
            .rafflelb-winners-content{padding:34px 14px 48px}
            .rafflelb-winners-grid{grid-template-columns:1fr}
            .rafflelb-winner-details{grid-template-columns:1fr}
        }
        </style>
        <?php
        return ob_get_clean();
    }


    public static function customer_area_typography() {
        if (
            (!function_exists('is_cart') || !is_cart()) &&
            (!function_exists('is_checkout') || !is_checkout()) &&
            (!function_exists('is_account_page') || !is_account_page())
        ) {
            return;
        }
        ?>
        <style id="rafflelb-customer-typography">
        /* Use the same clean font treatment across customer-facing WooCommerce pages.
           We intentionally inherit the site's active font family so no external font
           dependency is introduced. */
        body.rafflelb-cart-page,
        body.rafflelb-checkout-page,
        body.rafflelb-account-page,
        body.rafflelb-cart-page input,
        body.rafflelb-cart-page button,
        body.rafflelb-cart-page select,
        body.rafflelb-checkout-page input,
        body.rafflelb-checkout-page button,
        body.rafflelb-checkout-page select,
        body.rafflelb-account-page input,
        body.rafflelb-account-page button,
        body.rafflelb-account-page select{
            font-family:inherit !important;
        }

        /* Main headings */
        .rafflelb-cart-page .cart_totals h2,
        .rafflelb-checkout-page .rafflelb-checkout-intro h1,
        .rafflelb-checkout-page .woocommerce-billing-fields > h3,
        .rafflelb-account-page .woocommerce-MyAccount-navigation:before,
        .rafflelb-account-page .woocommerce-MyAccount-content h2,
        .rafflelb-account-page .woocommerce-MyAccount-content h3{
            font-weight:800 !important;
            letter-spacing:-0.02em !important;
        }

        /* Standard UI copy */
        .rafflelb-cart-page .woocommerce,
        .rafflelb-checkout-page form.checkout,
        .rafflelb-account-page .woocommerce-MyAccount-content,
        .rafflelb-account-page .woocommerce-MyAccount-navigation{
            font-size:14px !important;
            line-height:1.55 !important;
        }

        /* Buttons */
        .rafflelb-cart-page button,
        .rafflelb-cart-page .button,
        .rafflelb-checkout-page button,
        .rafflelb-checkout-page .button,
        .rafflelb-account-page button,
        .rafflelb-account-page .button{
            font-weight:800 !important;
            letter-spacing:.04em !important;
            text-transform:uppercase !important;
        }

        /* Small labels */
        .rafflelb-cart-page .product-price:before,
        .rafflelb-cart-page .product-quantity:before,
        .rafflelb-cart-page .product-subtotal:before,
        .rafflelb-checkout-page .rl-checkout-kicker,
        .rafflelb-checkout-page #order_review table.shop_table thead th,
        .rafflelb-account-page table.shop_table th{
            font-size:10px !important;
            font-weight:800 !important;
            letter-spacing:.12em !important;
            text-transform:uppercase !important;
        }

        /* Sidebar / account menu */
        .rafflelb-account-page .woocommerce-MyAccount-navigation a{
            font-size:13px !important;
            font-weight:700 !important;
            letter-spacing:.01em !important;
        }

        /* Product / card titles */
        .rafflelb-cart-page .product-name a,
        .rafflelb-account-page .wd-my-account-links a{
            font-weight:800 !important;
            letter-spacing:-0.01em !important;
        }

        /* Totals */
        .rafflelb-cart-page .cart_totals .order-total td,
        .rafflelb-cart-page .cart_totals .order-total .amount,
        .rafflelb-checkout-page #order_review table.shop_table .order-total td,
        .rafflelb-checkout-page #order_review table.shop_table .order-total .amount{
            font-weight:800 !important;
            letter-spacing:-0.02em !important;
        }

        /* v0.10.8: final high-contrast guard for all dark customer areas */
        body.rafflelb-account-page .site-content .woocommerce,
        body.rafflelb-account-page .site-content .woocommerce h1,
        body.rafflelb-account-page .site-content .woocommerce h2,
        body.rafflelb-account-page .site-content .woocommerce h3,
        body.rafflelb-account-page .site-content .woocommerce h4,
        body.rafflelb-account-page .site-content .woocommerce p,
        body.rafflelb-account-page .site-content .woocommerce label,
        body.rafflelb-account-page .site-content .woocommerce span,
        body.rafflelb-account-page .site-content .woocommerce small,
        body.rafflelb-account-page .site-content .woocommerce strong,
        body.rafflelb-account-page .site-content .woocommerce li,
        body.rafflelb-cart-page .site-content .woocommerce,
        body.rafflelb-cart-page .site-content .woocommerce h1,
        body.rafflelb-cart-page .site-content .woocommerce h2,
        body.rafflelb-cart-page .site-content .woocommerce h3,
        body.rafflelb-cart-page .site-content .woocommerce p,
        body.rafflelb-cart-page .site-content .woocommerce label,
        body.rafflelb-cart-page .site-content .woocommerce span,
        body.rafflelb-cart-page .site-content .woocommerce small,
        body.rafflelb-cart-page .site-content .woocommerce strong,
        body.rafflelb-cart-page .site-content .woocommerce th,
        body.rafflelb-cart-page .site-content .woocommerce td,
        body.rafflelb-checkout-page .site-content .woocommerce,
        body.rafflelb-checkout-page .site-content .woocommerce h1,
        body.rafflelb-checkout-page .site-content .woocommerce h2,
        body.rafflelb-checkout-page .site-content .woocommerce h3,
        body.rafflelb-checkout-page .site-content .woocommerce h4,
        body.rafflelb-checkout-page .site-content .woocommerce p,
        body.rafflelb-checkout-page .site-content .woocommerce label,
        body.rafflelb-checkout-page .site-content .woocommerce span,
        body.rafflelb-checkout-page .site-content .woocommerce small,
        body.rafflelb-checkout-page .site-content .woocommerce strong,
        body.rafflelb-checkout-page .site-content .woocommerce li{
            color:#e8ebe4 !important;
            -webkit-text-fill-color:#e8ebe4 !important;
            opacity:1 !important;
        }

        body.rafflelb-account-page .site-content .woocommerce a:not(.button),
        body.rafflelb-cart-page .site-content .woocommerce a:not(.button):not(.remove),
        body.rafflelb-checkout-page .site-content .woocommerce a:not(.button){
            color:#caff16 !important;
            -webkit-text-fill-color:#caff16 !important;
            opacity:1 !important;
        }

        body.rafflelb-account-page .site-content .woocommerce input,
        body.rafflelb-account-page .site-content .woocommerce textarea,
        body.rafflelb-account-page .site-content .woocommerce select,
        body.rafflelb-cart-page .site-content .woocommerce input,
        body.rafflelb-cart-page .site-content .woocommerce textarea,
        body.rafflelb-cart-page .site-content .woocommerce select,
        body.rafflelb-checkout-page .site-content .woocommerce input,
        body.rafflelb-checkout-page .site-content .woocommerce textarea,
        body.rafflelb-checkout-page .site-content .woocommerce select{
            color:#ffffff !important;
            -webkit-text-fill-color:#ffffff !important;
            caret-color:#caff16 !important;
            opacity:1 !important;
        }

        body.rafflelb-account-page .site-content .woocommerce input::placeholder,
        body.rafflelb-account-page .site-content .woocommerce textarea::placeholder,
        body.rafflelb-cart-page .site-content .woocommerce input::placeholder,
        body.rafflelb-cart-page .site-content .woocommerce textarea::placeholder,
        body.rafflelb-checkout-page .site-content .woocommerce input::placeholder,
        body.rafflelb-checkout-page .site-content .woocommerce textarea::placeholder{
            color:#9da398 !important;
            -webkit-text-fill-color:#9da398 !important;
            opacity:1 !important;
        }

        /* Bright/white controls need dark lettering. */
        body.rafflelb-account-page .site-content .woocommerce-form-login button.button,
        body.rafflelb-account-page .site-content .woocommerce-form-register button.button,
        body.rafflelb-account-page .site-content .woocommerce-MyAccount-content a.button,
        body.rafflelb-account-page .site-content .woocommerce-MyAccount-content button.button,
        body.rafflelb-cart-page .site-content .wc-proceed-to-checkout a.checkout-button,
        body.rafflelb-checkout-page .site-content .woocommerce #place_order,
        body.rafflelb-checkout-page .site-content .woocommerce #place_order *{
            color:#090a08 !important;
            -webkit-text-fill-color:#090a08 !important;
            opacity:1 !important;
        }

        /* The checkout order table is white, so keep its contents dark. */
        body.rafflelb-checkout-page .site-content #order_review table.shop_table,
        body.rafflelb-checkout-page .site-content #order_review table.shop_table th,
        body.rafflelb-checkout-page .site-content #order_review table.shop_table td,
        body.rafflelb-checkout-page .site-content #order_review table.shop_table th *,
        body.rafflelb-checkout-page .site-content #order_review table.shop_table td *{
            color:#11130f !important;
            -webkit-text-fill-color:#11130f !important;
            opacity:1 !important;
        }

        body.rafflelb-account-page .site-content .woocommerce-MyAccount-navigation .is-active a,
        body.rafflelb-account-page .site-content .woocommerce-MyAccount-navigation .is-active a *{
            color:#090a08 !important;
            -webkit-text-fill-color:#090a08 !important;
        }

        body.rafflelb-cart-page .site-content .product-price,
        body.rafflelb-cart-page .site-content .product-price .amount,
        body.rafflelb-cart-page .site-content .product-subtotal,
        body.rafflelb-cart-page .site-content .product-subtotal .amount,
        body.rafflelb-cart-page .site-content .cart_totals .order-total td,
        body.rafflelb-cart-page .site-content .cart_totals .order-total .amount{
            color:#caff16 !important;
            -webkit-text-fill-color:#caff16 !important;
        }

        /* v0.11.0: real Elementor/WoodMart wrappers used on rafflelb.com. */
        body.rafflelb-account-page #customer_login h2,
        body.rafflelb-account-page #customer_login h3,
        body.rafflelb-account-page #customer_login p,
        body.rafflelb-account-page #customer_login label,
        body.rafflelb-account-page #customer_login label span,
        body.rafflelb-account-page #customer_login .registration-info,
        body.rafflelb-account-page #customer_login .login-info,
        body.rafflelb-account-page #customer_login .wd-login-divider,
        body.rafflelb-account-page #customer_login .wd-login-divider span{
            color:#f0f2ed !important;
            -webkit-text-fill-color:#f0f2ed !important;
            opacity:1 !important;
        }

        body.rafflelb-account-page #customer_login a:not(.btn),
        body.rafflelb-account-page #customer_login .lost_password,
        body.rafflelb-account-page #customer_login .woocommerce-LostPassword{
            color:#caff16 !important;
            -webkit-text-fill-color:#caff16 !important;
            opacity:1 !important;
        }

        body.rafflelb-account-page #customer_login input.input-text{
            color:#ffffff !important;
            -webkit-text-fill-color:#ffffff !important;
            caret-color:#caff16 !important;
            background:#171915 !important;
            border-color:#3a4035 !important;
            opacity:1 !important;
        }

        body.rafflelb-account-page #customer_login button.button,
        body.rafflelb-account-page #customer_login a.wd-switch-to-register,
        body.rafflelb-account-page #customer_login a.btn{
            border-color:#caff16 !important;
            background:#caff16 !important;
            color:#090a08 !important;
            -webkit-text-fill-color:#090a08 !important;
            opacity:1 !important;
        }

        body.rafflelb-cart-page .main-page-wrapper h1,
        body.rafflelb-cart-page .main-page-wrapper h2,
        body.rafflelb-cart-page .main-page-wrapper h3,
        body.rafflelb-cart-page .main-page-wrapper p,
        body.rafflelb-cart-page .main-page-wrapper label,
        body.rafflelb-cart-page .main-page-wrapper small,
        body.rafflelb-cart-page .main-page-wrapper th,
        body.rafflelb-cart-page .main-page-wrapper td,
        body.rafflelb-checkout-page .main-page-wrapper h1,
        body.rafflelb-checkout-page .main-page-wrapper h2,
        body.rafflelb-checkout-page .main-page-wrapper h3,
        body.rafflelb-checkout-page .main-page-wrapper h4,
        body.rafflelb-checkout-page .main-page-wrapper p,
        body.rafflelb-checkout-page .main-page-wrapper label,
        body.rafflelb-checkout-page .main-page-wrapper small,
        body.rafflelb-checkout-page .main-page-wrapper li{
            color:#f0f2ed !important;
            -webkit-text-fill-color:#f0f2ed !important;
            opacity:1 !important;
        }

        body.rafflelb-cart-page .main-page-wrapper a:not(.button):not(.remove),
        body.rafflelb-checkout-page .main-page-wrapper a:not(.button){
            color:#caff16 !important;
            -webkit-text-fill-color:#caff16 !important;
            opacity:1 !important;
        }

        body.rafflelb-cart-page .main-page-wrapper input,
        body.rafflelb-cart-page .main-page-wrapper textarea,
        body.rafflelb-cart-page .main-page-wrapper select,
        body.rafflelb-checkout-page .main-page-wrapper input,
        body.rafflelb-checkout-page .main-page-wrapper textarea,
        body.rafflelb-checkout-page .main-page-wrapper select{
            color:#ffffff !important;
            -webkit-text-fill-color:#ffffff !important;
            caret-color:#caff16 !important;
            opacity:1 !important;
        }

        body.rafflelb-checkout-page .main-page-wrapper #order_review table.shop_table,
        body.rafflelb-checkout-page .main-page-wrapper #order_review table.shop_table th,
        body.rafflelb-checkout-page .main-page-wrapper #order_review table.shop_table td,
        body.rafflelb-checkout-page .main-page-wrapper #order_review table.shop_table th *,
        body.rafflelb-checkout-page .main-page-wrapper #order_review table.shop_table td *{
            color:#11130f !important;
            -webkit-text-fill-color:#11130f !important;
            opacity:1 !important;
        }

        body.rafflelb-checkout-page .main-page-wrapper #place_order,
        body.rafflelb-checkout-page .main-page-wrapper #place_order *,
        body.rafflelb-cart-page .main-page-wrapper .checkout-button{
            color:#090a08 !important;
            -webkit-text-fill-color:#090a08 !important;
        }

        body.rafflelb-cart-page .main-page-wrapper .product-price,
        body.rafflelb-cart-page .main-page-wrapper .product-price .amount,
        body.rafflelb-cart-page .main-page-wrapper .product-subtotal,
        body.rafflelb-cart-page .main-page-wrapper .product-subtotal .amount,
        body.rafflelb-cart-page .main-page-wrapper .cart_totals .order-total td,
        body.rafflelb-cart-page .main-page-wrapper .cart_totals .order-total .amount{
            color:#caff16 !important;
            -webkit-text-fill-color:#caff16 !important;
        }
        </style>
        <?php
    }

    public static function customer_area_contrast() {
        if (
            (!function_exists('is_cart') || !is_cart()) &&
            (!function_exists('is_checkout') || !is_checkout()) &&
            (!function_exists('is_account_page') || !is_account_page())
        ) {
            return;
        }
        ?>
        <style id="rafflelb-final-customer-contrast">
        /* This block intentionally prints in wp_footer after WoodMart. */
        body.rafflelb-account-page #customer_login h2,
        body.rafflelb-account-page #customer_login p,
        body.rafflelb-account-page #customer_login label,
        body.rafflelb-account-page #customer_login label span,
        body.rafflelb-account-page #customer_login .registration-info,
        body.rafflelb-account-page #customer_login .login-info,
        body.rafflelb-cart-page .main-page-wrapper h1,
        body.rafflelb-cart-page .main-page-wrapper h2,
        body.rafflelb-cart-page .main-page-wrapper h3,
        body.rafflelb-cart-page .main-page-wrapper p,
        body.rafflelb-cart-page .main-page-wrapper label,
        body.rafflelb-cart-page .main-page-wrapper th,
        body.rafflelb-cart-page .main-page-wrapper td,
        body.rafflelb-checkout-page .main-page-wrapper h1,
        body.rafflelb-checkout-page .main-page-wrapper h2,
        body.rafflelb-checkout-page .main-page-wrapper h3,
        body.rafflelb-checkout-page .main-page-wrapper h4,
        body.rafflelb-checkout-page .main-page-wrapper p,
        body.rafflelb-checkout-page .main-page-wrapper label,
        body.rafflelb-checkout-page .main-page-wrapper li{
            color:#f0f2ed !important;
            -webkit-text-fill-color:#f0f2ed !important;
            opacity:1 !important;
        }

        body.rafflelb-account-page #customer_login a:not(.btn),
        body.rafflelb-account-page #customer_login .lost_password,
        body.rafflelb-cart-page .main-page-wrapper a:not(.button):not(.remove),
        body.rafflelb-checkout-page .main-page-wrapper a:not(.button){
            color:#caff16 !important;
            -webkit-text-fill-color:#caff16 !important;
            opacity:1 !important;
        }

        body.rafflelb-account-page #customer_login button.button,
        body.rafflelb-account-page #customer_login a.wd-switch-to-register,
        body.rafflelb-account-page #customer_login a.btn{
            border-color:#caff16 !important;
            background:#caff16 !important;
            color:#090a08 !important;
            -webkit-text-fill-color:#090a08 !important;
            opacity:1 !important;
        }

        body.rafflelb-account-page .site-content h1,
        body.rafflelb-account-page .site-content h2,
        body.rafflelb-account-page .site-content h3,
        body.rafflelb-account-page .site-content h4,
        body.rafflelb-account-page .site-content h5,
        body.rafflelb-account-page .site-content p,
        body.rafflelb-account-page .site-content label,
        body.rafflelb-account-page .site-content small,
        body.rafflelb-account-page .site-content .col-login,
        body.rafflelb-account-page .site-content .col-register,
        body.rafflelb-account-page .site-content .login,
        body.rafflelb-account-page .site-content .register,
        body.rafflelb-account-page .site-content .woocommerce-form-login,
        body.rafflelb-account-page .site-content .woocommerce-form-register,
        body.rafflelb-cart-page .site-content h1,
        body.rafflelb-cart-page .site-content h2,
        body.rafflelb-cart-page .site-content h3,
        body.rafflelb-cart-page .site-content p,
        body.rafflelb-cart-page .site-content label,
        body.rafflelb-cart-page .site-content small,
        body.rafflelb-cart-page .site-content th,
        body.rafflelb-cart-page .site-content td,
        body.rafflelb-checkout-page .site-content h1,
        body.rafflelb-checkout-page .site-content h2,
        body.rafflelb-checkout-page .site-content h3,
        body.rafflelb-checkout-page .site-content h4,
        body.rafflelb-checkout-page .site-content p,
        body.rafflelb-checkout-page .site-content label,
        body.rafflelb-checkout-page .site-content small,
        body.rafflelb-checkout-page .site-content li{
            color:#f0f2ed !important;
            -webkit-text-fill-color:#f0f2ed !important;
            opacity:1 !important;
        }

        body.rafflelb-account-page .site-content a:not(.button),
        body.rafflelb-cart-page .site-content a:not(.button):not(.remove),
        body.rafflelb-checkout-page .site-content a:not(.button){
            color:#caff16 !important;
            -webkit-text-fill-color:#caff16 !important;
            opacity:1 !important;
        }

        body.rafflelb-account-page .site-content input,
        body.rafflelb-account-page .site-content textarea,
        body.rafflelb-account-page .site-content select,
        body.rafflelb-cart-page .site-content input,
        body.rafflelb-cart-page .site-content textarea,
        body.rafflelb-cart-page .site-content select,
        body.rafflelb-checkout-page .site-content input,
        body.rafflelb-checkout-page .site-content textarea,
        body.rafflelb-checkout-page .site-content select{
            color:#ffffff !important;
            -webkit-text-fill-color:#ffffff !important;
            caret-color:#caff16 !important;
            opacity:1 !important;
        }

        body.rafflelb-account-page .site-content input::placeholder,
        body.rafflelb-account-page .site-content textarea::placeholder,
        body.rafflelb-cart-page .site-content input::placeholder,
        body.rafflelb-cart-page .site-content textarea::placeholder,
        body.rafflelb-checkout-page .site-content input::placeholder,
        body.rafflelb-checkout-page .site-content textarea::placeholder{
            color:#9da398 !important;
            -webkit-text-fill-color:#9da398 !important;
            opacity:1 !important;
        }

        /* Login and Register use the same RaffleLB button treatment. */
        body.rafflelb-account-page .site-content .col-login button,
        body.rafflelb-account-page .site-content .col-login .button,
        body.rafflelb-account-page .site-content .col-register button,
        body.rafflelb-account-page .site-content .col-register .button,
        body.rafflelb-account-page .site-content .login button,
        body.rafflelb-account-page .site-content .login .button,
        body.rafflelb-account-page .site-content .register button,
        body.rafflelb-account-page .site-content .register .button,
        body.rafflelb-account-page .site-content .woocommerce-form-login button,
        body.rafflelb-account-page .site-content .woocommerce-form-register button{
            border-color:#caff16 !important;
            background:#caff16 !important;
            color:#090a08 !important;
            -webkit-text-fill-color:#090a08 !important;
            opacity:1 !important;
        }

        /* Preserve the intentional dark-on-light checkout summary. */
        body.rafflelb-checkout-page .site-content #order_review table.shop_table,
        body.rafflelb-checkout-page .site-content #order_review table.shop_table th,
        body.rafflelb-checkout-page .site-content #order_review table.shop_table td,
        body.rafflelb-checkout-page .site-content #order_review table.shop_table th *,
        body.rafflelb-checkout-page .site-content #order_review table.shop_table td *{
            color:#11130f !important;
            -webkit-text-fill-color:#11130f !important;
            opacity:1 !important;
        }

        body.rafflelb-checkout-page .site-content #place_order,
        body.rafflelb-checkout-page .site-content #place_order * ,
        body.rafflelb-cart-page .site-content .checkout-button,
        body.rafflelb-account-page .site-content .woocommerce-MyAccount-navigation .is-active a,
        body.rafflelb-account-page .site-content .woocommerce-MyAccount-navigation .is-active a *{
            color:#090a08 !important;
            -webkit-text-fill-color:#090a08 !important;
        }

        body.rafflelb-cart-page .site-content .product-price,
        body.rafflelb-cart-page .site-content .product-price .amount,
        body.rafflelb-cart-page .site-content .product-subtotal,
        body.rafflelb-cart-page .site-content .product-subtotal .amount,
        body.rafflelb-cart-page .site-content .cart_totals .order-total td,
        body.rafflelb-cart-page .site-content .cart_totals .order-total .amount{
            color:#caff16 !important;
            -webkit-text-fill-color:#caff16 !important;
        }

        /* v0.11.1: complete WooCommerce dark-area contrast audit. */
        body.rafflelb-checkout-page .woocommerce-form-login-toggle,
        body.rafflelb-checkout-page .woocommerce-form-login-toggle .woocommerce-info,
        body.rafflelb-checkout-page .woocommerce-form-login-toggle .woocommerce-info::before,
        body.rafflelb-checkout-page .woocommerce-form-coupon-toggle,
        body.rafflelb-checkout-page .woocommerce-form-coupon-toggle .woocommerce-info,
        body.rafflelb-checkout-page .woocommerce-form-coupon-toggle .woocommerce-info::before,
        body.rafflelb-checkout-page .woocommerce-form-login,
        body.rafflelb-checkout-page .woocommerce-form-login p,
        body.rafflelb-checkout-page .woocommerce-form-login label,
        body.rafflelb-checkout-page form.checkout .woocommerce-billing-fields,
        body.rafflelb-checkout-page form.checkout .woocommerce-billing-fields h3,
        body.rafflelb-checkout-page form.checkout .form-row label,
        body.rafflelb-checkout-page form.checkout .woocommerce-privacy-policy-text,
        body.rafflelb-checkout-page form.checkout .woocommerce-terms-and-conditions-checkbox-text,
        body.rafflelb-checkout-page #payment .payment_methods,
        body.rafflelb-checkout-page #payment .payment_methods label,
        body.rafflelb-checkout-page #payment .payment_box,
        body.rafflelb-checkout-page #payment .payment_box p,
        body.rafflelb-checkout-page .woocommerce-notices-wrapper .woocommerce-info,
        body.rafflelb-checkout-page .woocommerce-notices-wrapper .woocommerce-message,
        body.rafflelb-checkout-page .woocommerce-notices-wrapper .woocommerce-error,
        body.rafflelb-cart-page .woocommerce-notices-wrapper .woocommerce-info,
        body.rafflelb-cart-page .woocommerce-notices-wrapper .woocommerce-message,
        body.rafflelb-cart-page .woocommerce-notices-wrapper .woocommerce-error,
        body.rafflelb-cart-page .wc-empty-cart-message,
        body.rafflelb-cart-page .cart-empty,
        body.rafflelb-cart-page .wd-empty-page,
        body.rafflelb-cart-page .wd-empty-page-title,
        body.rafflelb-cart-page .wd-empty-page-text,
        body.rafflelb-account-page .woocommerce-notices-wrapper .woocommerce-info,
        body.rafflelb-account-page .woocommerce-notices-wrapper .woocommerce-message,
        body.rafflelb-account-page .woocommerce-notices-wrapper .woocommerce-error,
        body.rafflelb-account-page .woocommerce-MyAccount-content,
        body.rafflelb-account-page .woocommerce-MyAccount-content h1,
        body.rafflelb-account-page .woocommerce-MyAccount-content h2,
        body.rafflelb-account-page .woocommerce-MyAccount-content h3,
        body.rafflelb-account-page .woocommerce-MyAccount-content p,
        body.rafflelb-account-page .woocommerce-MyAccount-content label{
            color:#f0f2ed !important;
            -webkit-text-fill-color:#f0f2ed !important;
            opacity:1 !important;
        }

        body.rafflelb-checkout-page .woocommerce-form-login-toggle a,
        body.rafflelb-checkout-page .woocommerce-form-coupon-toggle a,
        body.rafflelb-checkout-page form.checkout .woocommerce-privacy-policy-text a,
        body.rafflelb-checkout-page form.checkout .woocommerce-terms-and-conditions-checkbox-text a,
        body.rafflelb-checkout-page .woocommerce-notices-wrapper a,
        body.rafflelb-cart-page .woocommerce-notices-wrapper a,
        body.rafflelb-account-page .woocommerce-notices-wrapper a{
            color:#caff16 !important;
            -webkit-text-fill-color:#caff16 !important;
            opacity:1 !important;
        }

        body.rafflelb-checkout-page .select2-container .select2-selection__rendered,
        body.rafflelb-checkout-page .select2-container .select2-selection__placeholder,
        body.rafflelb-checkout-page .select2-container .select2-selection__arrow{
            color:#ffffff !important;
            -webkit-text-fill-color:#ffffff !important;
            opacity:1 !important;
        }

        /* White order-summary card remains deliberately dark and readable. */
        body.rafflelb-checkout-page #order_review table.shop_table,
        body.rafflelb-checkout-page #order_review table.shop_table th,
        body.rafflelb-checkout-page #order_review table.shop_table td,
        body.rafflelb-checkout-page #order_review table.shop_table th *,
        body.rafflelb-checkout-page #order_review table.shop_table td *{
            color:#11130f !important;
            -webkit-text-fill-color:#11130f !important;
            opacity:1 !important;
        }
        
        /* v0.15.2 FINAL BUTTON CONTRAST GUARD
           This is emitted in wp_footer at priority 999, after WoodMart. */
        body.rafflelb-account-page .woocommerce-MyAccount-content a.button,
        body.rafflelb-account-page .woocommerce-MyAccount-content a.woocommerce-button,
        body.rafflelb-account-page .woocommerce-MyAccount-content .woocommerce-button,
        body.rafflelb-account-page .woocommerce-MyAccount-content .woocommerce-orders-table__cell-order-actions a,
        body.rafflelb-account-page .woocommerce-MyAccount-content .woocommerce-orders-table__cell-order-actions a.button,
        body.rafflelb-account-page .woocommerce-MyAccount-content a.rafflelb-view-winning-entry{
            --btn-color:#090a08 !important;
            --btn-color-hover:#090a08 !important;
            --btn-bgcolor:#caff16 !important;
            --btn-bgcolor-hover:#bfff00 !important;
            color:#090a08 !important;
            -webkit-text-fill-color:#090a08 !important;
            text-shadow:none !important;
            opacity:1 !important;
        }

        body.rafflelb-account-page .woocommerce-MyAccount-content a.button *,
        body.rafflelb-account-page .woocommerce-MyAccount-content a.woocommerce-button *,
        body.rafflelb-account-page .woocommerce-MyAccount-content .woocommerce-button *,
        body.rafflelb-account-page .woocommerce-MyAccount-content .woocommerce-orders-table__cell-order-actions a *,
        body.rafflelb-account-page .woocommerce-MyAccount-content a.rafflelb-view-winning-entry *{
            color:#090a08 !important;
            -webkit-text-fill-color:#090a08 !important;
            text-shadow:none !important;
            opacity:1 !important;
        }

        body.rafflelb-account-page .woocommerce-MyAccount-content a.button:hover,
        body.rafflelb-account-page .woocommerce-MyAccount-content a.button:focus,
        body.rafflelb-account-page .woocommerce-MyAccount-content a.woocommerce-button:hover,
        body.rafflelb-account-page .woocommerce-MyAccount-content a.woocommerce-button:focus,
        body.rafflelb-account-page .woocommerce-MyAccount-content .woocommerce-orders-table__cell-order-actions a:hover,
        body.rafflelb-account-page .woocommerce-MyAccount-content .woocommerce-orders-table__cell-order-actions a:focus,
        body.rafflelb-account-page .woocommerce-MyAccount-content a.rafflelb-view-winning-entry:hover,
        body.rafflelb-account-page .woocommerce-MyAccount-content a.rafflelb-view-winning-entry:focus{
            color:#090a08 !important;
            -webkit-text-fill-color:#090a08 !important;
        }

</style>
        <?php
    }

    public static function cart_body_class($classes) {
        if (function_exists('is_cart') && is_cart()) {
            $classes[] = 'rafflelb-cart-page';
        }
        return $classes;
    }

    private static function is_raffle_product($product = null) {
        // Preserve the legacy implementation when Shop is unavailable.
        if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
            return RaffleLB_Shop::is_raffle_product(...func_get_args());
        }

        // Product objects are not always available yet when WordPress builds
        // body classes or prints wp_head. Fall back to the queried product ID
        // so the raffle layout is applied reliably on every single product page.
        if (!$product instanceof WC_Product) {
            $queried_id = function_exists('get_queried_object_id') ? (int) get_queried_object_id() : 0;
            if ($queried_id > 0 && get_post_type($queried_id) === 'product') {
                $product = wc_get_product($queried_id);
            }
        }

        if (!$product instanceof WC_Product) {
            global $product;
            if (!$product instanceof WC_Product) return false;
        }

        return get_post_meta($product->get_id(), self::META_ENABLED, true) === 'yes';
    }

    public static function cart_button_text($text) {
        // Shop presentation now lives exclusively in the RaffleLB Shop plugin.
        // Keep this callback as a compatibility delegate so existing Draw Engine
        // hook registrations and third-party callers remain stable.
        if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
            return RaffleLB_Shop::cart_button_text(...func_get_args());
        }

        return $text;
    }

    public static function raffle_product_body_class($classes) {
        // Shop presentation now lives exclusively in the RaffleLB Shop plugin.
        // Keep this callback as a compatibility delegate so existing Draw Engine
        // hook registrations and third-party callers remain stable.
        if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
            return RaffleLB_Shop::raffle_product_body_class(...func_get_args());
        }

        return $classes;
    }

    public static function raffle_product_price_html($price_html, $product) {
        // Shop presentation now lives exclusively in the RaffleLB Shop plugin.
        // Keep this callback as a compatibility delegate so existing Draw Engine
        // hook registrations and third-party callers remain stable.
        if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
            return RaffleLB_Shop::raffle_product_price_html(...func_get_args());
        }

        return $price_html;
    }

    public static function raffle_product_availability_text($text, $product) {
        // Shop presentation now lives exclusively in the RaffleLB Shop plugin.
        // Keep this callback as a compatibility delegate so existing Draw Engine
        // hook registrations and third-party callers remain stable.
        if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
            return RaffleLB_Shop::raffle_product_availability_text(...func_get_args());
        }

        return $text;
    }

    public static function raffle_quantity_label() {
        // Shop presentation now lives exclusively in the RaffleLB Shop plugin.
        // Keep this callback as a compatibility delegate so existing Draw Engine
        // hook registrations and third-party callers remain stable.
        if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
            return RaffleLB_Shop::raffle_quantity_label(...func_get_args());
        }

        return;
    }






    public static function product_details_relocator() {
        // Shop presentation now lives exclusively in the RaffleLB Shop plugin.
        // Keep this callback as a compatibility delegate so existing Draw Engine
        // hook registrations and third-party callers remain stable.
        if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
            return RaffleLB_Shop::product_details_relocator(...func_get_args());
        }

        return;
    }

    public static function product_details_panel() {
        // Shop presentation now lives exclusively in the RaffleLB Shop plugin.
        // Keep this callback as a compatibility delegate so existing Draw Engine
        // hook registrations and third-party callers remain stable.
        if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
            return RaffleLB_Shop::product_details_panel(...func_get_args());
        }

        return;
    }

    public static function raffle_option_open() {
        // Shop presentation now lives exclusively in the RaffleLB Shop plugin.
        // Keep this callback as a compatibility delegate so existing Draw Engine
        // hook registrations and third-party callers remain stable.
        if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
            return RaffleLB_Shop::raffle_option_open(...func_get_args());
        }

        return;
    }

    public static function raffle_option_close() {
        // Shop presentation now lives exclusively in the RaffleLB Shop plugin.
        // Keep this callback as a compatibility delegate so existing Draw Engine
        // hook registrations and third-party callers remain stable.
        if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
            return RaffleLB_Shop::raffle_option_close(...func_get_args());
        }

        return;
    }

    public static function raffle_secondary_intro() {
        // Shop presentation now lives exclusively in the RaffleLB Shop plugin.
        // Keep this callback as a compatibility delegate so existing Draw Engine
        // hook registrations and third-party callers remain stable.
        if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
            return RaffleLB_Shop::raffle_secondary_intro(...func_get_args());
        }

        return;
    }

    public static function buy_now_panel() {
        // Shop presentation now lives exclusively in the RaffleLB Shop plugin.
        // Keep this callback as a compatibility delegate so existing Draw Engine
        // hook registrations and third-party callers remain stable.
        if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
            return RaffleLB_Shop::buy_now_panel(...func_get_args());
        }

        return;
    }

    public static function raffle_entry_trust() {
        // Shop presentation now lives exclusively in the RaffleLB Shop plugin.
        // Keep this callback as a compatibility delegate so existing Draw Engine
        // hook registrations and third-party callers remain stable.
        if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
            return RaffleLB_Shop::raffle_entry_trust(...func_get_args());
        }

        return;
    }



    public static function raffle_remove_native_short_description() {
        // Shop presentation now lives exclusively in the RaffleLB Shop plugin.
        // Keep this callback as a compatibility delegate so existing Draw Engine
        // hook registrations and third-party callers remain stable.
        if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
            return RaffleLB_Shop::raffle_remove_native_short_description(...func_get_args());
        }

        return;
    }

    public static function raffle_short_description() {
        // Shop presentation now lives exclusively in the RaffleLB Shop plugin.
        // Keep this callback as a compatibility delegate so existing Draw Engine
        // hook registrations and third-party callers remain stable.
        if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
            return RaffleLB_Shop::raffle_short_description(...func_get_args());
        }

        return;
    }

    public static function raffle_product_tabs($tabs) {
        // Shop presentation now lives exclusively in the RaffleLB Shop plugin.
        // Keep this callback as a compatibility delegate so existing Draw Engine
        // hook registrations and third-party callers remain stable.
        if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
            return RaffleLB_Shop::raffle_product_tabs(...func_get_args());
        }

        return $tabs;
    }

    public static function raffle_details_section() {
        // Shop presentation now lives exclusively in the RaffleLB Shop plugin.
        // Keep this callback as a compatibility delegate so existing Draw Engine
        // hook registrations and third-party callers remain stable.
        if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
            return RaffleLB_Shop::raffle_details_section(...func_get_args());
        }

        return;
    }

    public static function raffle_archive_body_class($classes) {
        // Shop presentation now lives exclusively in the RaffleLB Shop plugin.
        // Keep this callback as a compatibility delegate so existing Draw Engine
        // hook registrations and third-party callers remain stable.
        if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
            return RaffleLB_Shop::raffle_archive_body_class(...func_get_args());
        }

        return $classes;
    }

    public static function raffle_archive_product_class($classes, $class = '', $post_id = 0) {
        // Shop presentation now lives exclusively in the RaffleLB Shop plugin.
        // Keep this callback as a compatibility delegate so existing Draw Engine
        // hook registrations and third-party callers remain stable.
        if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
            return RaffleLB_Shop::raffle_archive_product_class(...func_get_args());
        }

        return $classes;
    }

    public static function raffle_archive_hero() {
        // Shop presentation now lives exclusively in the RaffleLB Shop plugin.
        // Keep this callback as a compatibility delegate so existing Draw Engine
        // hook registrations and third-party callers remain stable.
        if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
            return RaffleLB_Shop::raffle_archive_hero(...func_get_args());
        }

        return;
    }

    private static function shop_query_is_catalog($query = null) {
        // Preserve the legacy implementation when Shop is unavailable.
        if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
            return RaffleLB_Shop::shop_query_is_catalog(...func_get_args());
        }

        if ($query instanceof WP_Query) {
            return $query->is_post_type_archive('product') || $query->is_tax('product_cat');
        }

        $is_shop_archive = function_exists('is_shop') && is_shop();
        $is_cat_archive  = function_exists('is_product_category') && is_product_category();
        return $is_shop_archive || $is_cat_archive;
    }

    private static function shop_view_mode() {
        // Preserve the legacy implementation when Shop is unavailable.
        if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
            return RaffleLB_Shop::shop_view_mode(...func_get_args());
        }

        $mode = isset($_GET['rl_view']) ? sanitize_key(wp_unslash($_GET['rl_view'])) : 'both';
        return in_array($mode, ['both', 'retail', 'raffle'], true) ? $mode : 'both';
    }

    private static function shop_mode_url($mode) {
        // Preserve the legacy implementation when Shop is unavailable.
        if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
            return RaffleLB_Shop::shop_mode_url(...func_get_args());
        }

        $mode = in_array($mode, ['both', 'retail', 'raffle'], true) ? $mode : 'both';
        $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/shop/';
        $url = home_url($request_uri);
        $url = remove_query_arg(['rl_view', 'min_price', 'max_price', 'paged', 'product-page', 'orderby'], $url);
        if ($mode !== 'both') {
            $url = add_query_arg('rl_view', $mode, $url);
        }
        return $url . '#rl-shop-controls';
    }

    private static function shop_has_native_price_filter($query = null) {
        // Preserve the legacy implementation when Shop is unavailable.
        if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
            return RaffleLB_Shop::shop_has_native_price_filter(...func_get_args());
        }

        if (isset($_GET['min_price']) || isset($_GET['max_price'])) {
            return true;
        }
        if ($query instanceof WP_Query) {
            return $query->get('min_price') !== '' || $query->get('max_price') !== '';
        }
        return false;
    }

    private static function shop_native_price_value($key, $query = null, $fallback = null) {
        // Preserve the legacy implementation when Shop is unavailable.
        if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
            return RaffleLB_Shop::shop_native_price_value(...func_get_args());
        }

        if (isset($_GET[$key])) {
            return max(0, (float) wc_format_decimal(wp_unslash($_GET[$key])));
        }
        if ($query instanceof WP_Query) {
            $value = $query->get($key);
            if ($value !== '' && $value !== null) {
                return max(0, (float) wc_format_decimal($value));
            }
        }
        return $fallback;
    }

    public static function shop_catalog_scope($query) {
        // Shop presentation now lives exclusively in the RaffleLB Shop plugin.
        // Keep this callback as a compatibility delegate so existing Draw Engine
        // hook registrations and third-party callers remain stable.
        if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
            return RaffleLB_Shop::shop_catalog_scope(...func_get_args());
        }

        return;
    }

    public static function shop_premium_toolbar() {
        // Shop presentation now lives exclusively in the RaffleLB Shop plugin.
        // Keep this callback as a compatibility delegate so existing Draw Engine
        // hook registrations and third-party callers remain stable.
        if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
            return RaffleLB_Shop::shop_premium_toolbar(...func_get_args());
        }

        return;
    }

    public static function shop_orderby_options($options) {
        // Shop presentation now lives exclusively in the RaffleLB Shop plugin.
        // Keep this callback as a compatibility delegate so existing Draw Engine
        // hook registrations and third-party callers remain stable.
        if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
            return RaffleLB_Shop::shop_orderby_options(...func_get_args());
        }

        return is_array($options) ? $options : [];
    }

    public static function shop_native_price_filter_sql($sql, $meta_query_sql, $tax_query_sql) {
        // Shop presentation now lives exclusively in the RaffleLB Shop plugin.
        // Keep this callback as a compatibility delegate so existing Draw Engine
        // hook registrations and third-party callers remain stable.
        if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
            return RaffleLB_Shop::shop_native_price_filter_sql(...func_get_args());
        }

        return $sql;
    }

    public static function shop_disable_core_price_filtering($enabled, $query) {
        // Shop presentation now lives exclusively in the RaffleLB Shop plugin.
        // Keep this callback as a compatibility delegate so existing Draw Engine
        // hook registrations and third-party callers remain stable.
        if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
            return RaffleLB_Shop::shop_disable_core_price_filtering(...func_get_args());
        }

        return $enabled;
    }

    public static function shop_native_price_filter_clauses($clauses, $query) {
        // Shop presentation now lives exclusively in the RaffleLB Shop plugin.
        // Keep this callback as a compatibility delegate so existing Draw Engine
        // hook registrations and third-party callers remain stable.
        if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
            return RaffleLB_Shop::shop_native_price_filter_clauses(...func_get_args());
        }

        return $clauses;
    }

    public static function raffle_archive_progress() {
        // Shop presentation now lives exclusively in the RaffleLB Shop plugin.
        // Keep this callback as a compatibility delegate so existing Draw Engine
        // hook registrations and third-party callers remain stable.
        if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
            return RaffleLB_Shop::raffle_archive_progress(...func_get_args());
        }

        return;
    }

    public static function raffle_archive_view_button() {
        // Shop presentation now lives exclusively in the RaffleLB Shop plugin.
        // Keep this callback as a compatibility delegate so existing Draw Engine
        // hook registrations and third-party callers remain stable.
        if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
            return RaffleLB_Shop::raffle_archive_view_button(...func_get_args());
        }

        return;
    }

    public static function shop_enqueue_inter_font() {
        // Shop presentation now lives exclusively in the RaffleLB Shop plugin.
        // Keep this callback as a compatibility delegate so existing Draw Engine
        // hook registrations and third-party callers remain stable.
        if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
            return RaffleLB_Shop::shop_enqueue_inter_font(...func_get_args());
        }

        return;
    }

    public static function global_inter_font_default() {
        if (is_admin()) return;

        // Loading the Inter stylesheet (shop_enqueue_inter_font) only makes
        // it available to the browser - it doesn't make anything actually
        // use it. Every RaffleLB-built block explicitly sets
        // font-family:Inter on itself, but native WooCommerce/WoodMart
        // markup we never touched (product title, breadcrumbs, tabs,
        // buttons, etc.) still inherits the theme's own default font, so
        // those elements look visibly different from our own UI on the
        // same page. Setting it on body - deliberately with no !important
        // - makes Inter the sitewide default while leaving every more
        // specific selector (icon fonts included) free to override it.
        echo '<style id="rafflelb-global-inter-font">body{font-family:Inter,"Segoe UI",Arial,sans-serif}</style>';
    }

    public static function raffle_archive_styles() {
        // Shop presentation now lives exclusively in the RaffleLB Shop plugin.
        // Keep this callback as a compatibility delegate so existing Draw Engine
        // hook registrations and third-party callers remain stable.
        if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
            return RaffleLB_Shop::raffle_archive_styles(...func_get_args());
        }

        return;
    }

    public static function shop_navigation_loading_overlay() {
        // Shop presentation now lives exclusively in the RaffleLB Shop plugin.
        // Keep this callback as a compatibility delegate so existing Draw Engine
        // hook registrations and third-party callers remain stable.
        if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
            return RaffleLB_Shop::shop_navigation_loading_overlay(...func_get_args());
        }

        return;
    }

    /* Site-wide loading overlay: same visual design as the shop's own
       #rl-shop-nav-overlay (spinning ring + pulsing site icon + "Loading"
       text), reusing that CSS id/classes so the two never render at the
       same time on the same page. */

    public static function site_wide_loading_overlay_styles() {
        ?>
        <style id="rafflelb-site-nav-overlay-v1">
        #rl-shop-nav-overlay{
            position:fixed!important;
            inset:0!important;
            z-index:999999!important;
            display:none;
            align-items:center!important;
            justify-content:center!important;
            background:#050805!important;
            opacity:1;
            transition:opacity .25s ease!important;
        }
        #rl-shop-nav-overlay.is-active{
            display:flex!important;
        }
        #rl-shop-nav-overlay.is-hiding{
            opacity:0!important;
            pointer-events:none!important;
        }
        #rl-shop-nav-overlay .rl-shop-nav-overlay-inner{
            display:flex!important;
            flex-direction:column!important;
            align-items:center!important;
            gap:16px!important;
        }
        #rl-shop-nav-overlay .rl-shop-nav-overlay-ring{
            position:relative!important;
            width:76px!important;
            height:76px!important;
            display:flex!important;
            align-items:center!important;
            justify-content:center!important;
        }
        #rl-shop-nav-overlay .rl-shop-nav-overlay-ring:before{
            content:""!important;
            position:absolute!important;
            inset:0!important;
            border-radius:50%!important;
            border:3px solid rgba(186,255,0,.18)!important;
            border-top-color:#baff00!important;
            animation:rlShopNavSpin .8s linear infinite!important;
        }
        #rl-shop-nav-overlay img{
            width:52px!important;
            height:52px!important;
            border-radius:14px!important;
            animation:rlShopNavPulse 1.1s ease-in-out infinite!important;
        }
        #rl-shop-nav-overlay .rl-shop-nav-overlay-text{
            color:rgba(240,245,237,.72)!important;
            font-family:Inter,"Segoe UI",Arial,sans-serif!important;
            font-size:12px!important;
            font-weight:700!important;
            letter-spacing:.14em!important;
            text-transform:uppercase!important;
        }
        @keyframes rlShopNavSpin{ to{ transform:rotate(360deg); } }
        @keyframes rlShopNavPulse{ 0%,100%{ transform:scale(1); } 50%{ transform:scale(1.08); } }
        </style>
        <?php
    }

    public static function site_wide_loading_overlay_markup() {
        // The shop archive already prints its own copy (with the extra
        // "return to filter position" behavior) - never print a second
        // one with the same id on the same page.
        if (self::shop_query_is_catalog()) return;

        $icon = plugins_url('assets/rafflelb-site-icon.webp', __FILE__);
        echo '<div id="rl-shop-nav-overlay" aria-hidden="true">';
            echo '<div class="rl-shop-nav-overlay-inner">';
                echo '<div class="rl-shop-nav-overlay-ring"><img src="' . esc_url($icon) . '" alt=""></div>';
                echo '<span class="rl-shop-nav-overlay-text">Loading</span>';
            echo '</div>';
        echo '</div>';
        ?>
        <script>
        (function(){
            // This runs immediately at the very top of <body>, before any
            // of the actual page content (account page, or anywhere else)
            // has been parsed. Showing the overlay here - not later in
            // wp_footer - is what covers the page's own default styling
            // while it's still loading/being restyled by this page's own
            // scripts, instead of letting it flash visible first.
            var FLAG_KEY = 'rafflelb_nav_loading_v1';
            var FLAG_MAX_AGE = 8000;

            function overlayEl(){ return document.getElementById('rl-shop-nav-overlay'); }

            function showOverlay(){
                var el = overlayEl();
                if (!el) return;
                el.classList.remove('is-hiding');
                el.classList.add('is-active');
            }

            function hideOverlay(){
                var el = overlayEl();
                if (!el || !el.classList.contains('is-active')) return;
                el.classList.add('is-hiding');
                window.setTimeout(function(){
                    el.classList.remove('is-active', 'is-hiding');
                }, 260);
            }

            // Shared with the later wp_footer script (click handler +
            // hide-when-ready), so both act on the exact same overlay
            // state instead of each keeping their own.
            window.rlNavOverlay = {show: showOverlay, hide: hideOverlay};

            // Was this page reached by a click tracked on the page before it?
            try {
                var raw = window.sessionStorage.getItem(FLAG_KEY);
                if (raw) {
                    window.sessionStorage.removeItem(FLAG_KEY);
                    var data = JSON.parse(raw);
                    if (data && data.ts && (Date.now() - Number(data.ts)) <= FLAG_MAX_AGE) {
                        showOverlay();
                    }
                }
            } catch (e) {}

            // Self-contained safety net: never let it get stuck, even if
            // the later wp_footer script fails to run for any reason.
            window.setTimeout(hideOverlay, 6000);
        })();
        </script>
        <?php
    }

    public static function site_wide_loading_overlay_script() {
        if (self::shop_query_is_catalog()) return;
        ?>
        <script id="rafflelb-site-nav-overlay-script-v1">
        (function(){
            var api = window.rlNavOverlay;
            if (!api) return;

            function readyToHide(){
                // Small minimum-visible time so a very fast load doesn't flash.
                window.setTimeout(api.hide, 150);
            }
            if (document.readyState === 'complete') {
                readyToHide();
            } else {
                window.addEventListener('load', readyToHide);
            }
            // Back/forward-cache restores don't re-fire "load".
            window.addEventListener('pageshow', function(e){
                if (e.persisted) api.hide();
            });

            document.addEventListener('click', function(e){
                if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
                var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
                if (!a) return;
                if (a.target && a.target !== '' && a.target !== '_self') return;
                if (a.hasAttribute('download')) return;

                var href = a.getAttribute('href') || '';
                if (!href || href.charAt(0) === '#') return;
                if (/^(mailto:|tel:|javascript:)/i.test(href)) return;

                var url;
                try { url = new URL(href, window.location.href); } catch (e2) { return; }
                if (url.origin !== window.location.origin) return;
                // Same-page anchor jump, not a real navigation.
                if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) return;

                try {
                    window.sessionStorage.setItem('rafflelb_nav_loading_v1', JSON.stringify({ts: Date.now()}));
                } catch (e3) {}
                api.show();
            }, true);
        })();
        </script>
        <?php
    }

    public static function shop_native_filters_ui() {
        // Shop presentation now lives exclusively in the RaffleLB Shop plugin.
        // Keep this callback as a compatibility delegate so existing Draw Engine
        // hook registrations and third-party callers remain stable.
        if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
            return RaffleLB_Shop::shop_native_filters_ui(...func_get_args());
        }

        return;
    }

    /* Neither a high-specificity !important CSS rule nor forcing the same
       properties via inline JS style changed anything visible, despite
       both being confirmed present/running - something about how this
       theme's native <select> renders its own box is out of CSS's reach
       here. Rather than keep fighting it, replace it with a fully custom
       button+menu built from its own <option> list, and drive the real
       select from that (so WooCommerce's actual sort/navigation logic
       needs zero changes - the hidden select still submits the form
       exactly as before). Full visual control, no native rendering left
       to fight. */
    public static function shop_sort_select_force_fix() {
        // Shop presentation now lives exclusively in the RaffleLB Shop plugin.
        // Keep this callback as a compatibility delegate so existing Draw Engine
        // hook registrations and third-party callers remain stable.
        if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
            return RaffleLB_Shop::shop_sort_select_force_fix(...func_get_args());
        }

        return;
    }

    public static function raffle_product_styles() {
        // Shop presentation now lives exclusively in the RaffleLB Shop plugin.
        // Keep this callback as a compatibility delegate so existing Draw Engine
        // hook registrations and third-party callers remain stable.
        if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
            return RaffleLB_Shop::raffle_product_styles(...func_get_args());
        }

        return;
    }

    public static function cart_styles() {
        if (!function_exists('is_cart') || !is_cart()) {
            return;
        }
        ?>
        <style id="rafflelb-cart-styles">
        body.rafflelb-cart-page{
            background:#0b0c0a;
        }

        .rafflelb-cart-page .main-page-wrapper,
        .rafflelb-cart-page .site-content{
            background:#0b0c0a;
        }

        .rafflelb-cart-page .whb-header,
        .rafflelb-cart-page .whb-row,
        .rafflelb-cart-page .whb-general-header,
        .rafflelb-cart-page .whb-header .container{
            background:#fff !important;
        }

        .rafflelb-cart-page .site-content{
            padding-top:38px;
            padding-bottom:60px;
        }

        .rafflelb-cart-page .site-content > .container,
        .rafflelb-cart-page .main-page-wrapper .container{
            width:calc(100% - 48px) !important;
            max-width:1320px !important;
            margin-left:auto !important;
            margin-right:auto !important;
        }

        /* IMPORTANT: WoodMart controls Cart with .cart-content-wrapper.
           Target that real wrapper instead of .woocommerce. */
        .rafflelb-cart-page .cart-content-wrapper{
            display:grid !important;
            grid-template-columns:minmax(0,1fr) 360px !important;
            gap:36px !important;
            align-items:start !important;
            width:100% !important;
            max-width:1240px !important;
            margin:0 auto !important;
        }

        .rafflelb-cart-page .cart-content-wrapper > .woocommerce-cart-form,
        .rafflelb-cart-page .cart-content-wrapper > .cart-totals-section,
        .rafflelb-cart-page .cart-content-wrapper > .cart_totals{
            width:100% !important;
            max-width:none !important;
            min-width:0 !important;
            margin:0 !important;
            padding-left:0 !important;
            padding-right:0 !important;
            float:none !important;
            clear:none !important;
            box-sizing:border-box !important;
        }

        .rafflelb-cart-page .cart-content-wrapper > .woocommerce-cart-form{
            grid-column:1 !important;
            padding:28px !important;
            border:1px solid #292d27 !important;
            border-radius:18px !important;
            background:#121310 !important;
        }

        .rafflelb-cart-page .cart-content-wrapper > .cart-totals-section,
        .rafflelb-cart-page .cart-content-wrapper > .cart_totals{
            grid-column:2 !important;
        }

        .rafflelb-cart-page .cart-content-wrapper .cart-totals-inner,
        .rafflelb-cart-page .cart-content-wrapper .cart_totals{
            width:100% !important;
            max-width:none !important;
            min-width:0 !important;
            margin:0 !important;
            padding:28px !important;
            border:1px solid #292d27 !important;
            border-radius:18px !important;
            background:#121310 !important;
            color:#fff !important;
            box-sizing:border-box !important;
            position:static !important;
            transform:none !important;
        }

        /* Cart row */
        .rafflelb-cart-page .woocommerce-cart-form table.shop_table{
            display:block !important;
            width:100% !important;
            border:0 !important;
            background:transparent !important;
        }

        .rafflelb-cart-page .woocommerce-cart-form table.shop_table thead{
            display:none !important;
        }

        .rafflelb-cart-page .woocommerce-cart-form table.shop_table tbody{
            display:block !important;
            width:100% !important;
        }

        .rafflelb-cart-page .woocommerce-cart-form table.shop_table tr.cart_item{
            display:grid !important;
            grid-template-columns:34px 88px minmax(210px,1fr) 105px 138px 105px !important;
            gap:15px !important;
            align-items:center !important;
            width:100% !important;
            padding:0 0 24px !important;
            margin:0 0 22px !important;
            border-bottom:1px solid #292d27 !important;
            background:transparent !important;
        }

        .rafflelb-cart-page .woocommerce-cart-form table.shop_table tr.cart_item td{
            display:block !important;
            width:auto !important;
            min-width:0 !important;
            padding:0 !important;
            border:0 !important;
            background:transparent !important;
            color:#eef1eb !important;
        }

        .rafflelb-cart-page .product-thumbnail img{
            width:78px !important;
            height:78px !important;
            object-fit:cover !important;
            border-radius:12px !important;
        }

        .rafflelb-cart-page .product-name a{
            display:block !important;
            margin-bottom:8px !important;
            color:#fff !important;
            font-size:16px !important;
            font-weight:900 !important;
            line-height:1.3 !important;
        }

        /* Readable SKU */
        .rafflelb-cart-page .product-name,
        .rafflelb-cart-page .product-name span,
        .rafflelb-cart-page .product-name small,
        .rafflelb-cart-page .product-name p,
        .rafflelb-cart-page .product-name .sku_wrapper{
            color:#aeb4a8 !important;
            -webkit-text-fill-color:#aeb4a8 !important;
            opacity:1 !important;
            font-size:10px !important;
        }

        .rafflelb-cart-page .product-name .sku,
        .rafflelb-cart-page .product-name .sku_wrapper .sku{
            color:#eef1eb !important;
            -webkit-text-fill-color:#eef1eb !important;
            opacity:1 !important;
            font-weight:700 !important;
        }

        .rafflelb-cart-page .product-price:before{content:"ENTRY PRICE";}
        .rafflelb-cart-page .product-quantity:before{content:"ENTRIES";}
        .rafflelb-cart-page .product-subtotal:before{content:"TOTAL";}

        .rafflelb-cart-page .product-price:before,
        .rafflelb-cart-page .product-quantity:before,
        .rafflelb-cart-page .product-subtotal:before{
            display:block !important;
            margin-bottom:7px !important;
            color:#858c81 !important;
            font-size:9px !important;
            font-weight:900 !important;
            letter-spacing:1px !important;
        }

        .rafflelb-cart-page .product-price,
        .rafflelb-cart-page .product-subtotal,
        .rafflelb-cart-page .product-price .amount,
        .rafflelb-cart-page .product-subtotal .amount{
            color:#caff16 !important;
            font-weight:900 !important;
        }

        .rafflelb-cart-page .product-price{text-align:left !important;}
        .rafflelb-cart-page .product-quantity{text-align:center !important;}
        .rafflelb-cart-page .product-subtotal{text-align:right !important;}

        /* Quantity */
        .rafflelb-cart-page .product-quantity .quantity{
            width:126px !important;
            min-width:126px !important;
            height:42px !important;
            display:flex !important;
            align-items:center !important;
            justify-content:center !important;
            margin:0 auto !important;
            border:1px solid #3a3f36 !important;
            border-radius:999px !important;
            background:#181a17 !important;
            overflow:hidden !important;
        }

        .rafflelb-cart-page .product-quantity .quantity .minus,
        .rafflelb-cart-page .product-quantity .quantity .plus,
        .rafflelb-cart-page .product-quantity .quantity button{
            width:38px !important;
            min-width:38px !important;
            height:40px !important;
            padding:0 !important;
            margin:0 !important;
            display:flex !important;
            align-items:center !important;
            justify-content:center !important;
            position:static !important;
            border:0 !important;
            border-radius:0 !important;
            background:transparent !important;
            color:#d8ddd3 !important;
            font-size:17px !important;
            line-height:1 !important;
            opacity:1 !important;
        }

        .rafflelb-cart-page .product-quantity .quantity input.qty{
            width:50px !important;
            min-width:50px !important;
            height:40px !important;
            padding:0 !important;
            margin:0 !important;
            display:block !important;
            position:static !important;
            border:0 !important;
            border-left:1px solid #30342d !important;
            border-right:1px solid #30342d !important;
            border-radius:0 !important;
            background:#181a17 !important;
            color:#fff !important;
            -webkit-text-fill-color:#fff !important;
            opacity:1 !important;
            visibility:visible !important;
            text-align:center !important;
            font-size:13px !important;
            font-weight:900 !important;
            line-height:40px !important;
            box-shadow:none !important;
        }

        /* Remove X */
        .rafflelb-cart-page .product-remove{
            display:flex !important;
            align-items:center !important;
            justify-content:center !important;
        }

        .rafflelb-cart-page .product-remove a,
        .rafflelb-cart-page a.remove{
            width:30px !important;
            height:30px !important;
            min-width:30px !important;
            min-height:30px !important;
            display:flex !important;
            align-items:center !important;
            justify-content:center !important;
            padding:0 !important;
            margin:0 !important;
            border:1px solid #3a3f36 !important;
            border-radius:50% !important;
            background:#181a17 !important;
            color:transparent !important;
            font-size:0 !important;
            line-height:1 !important;
            position:relative !important;
        }

        .rafflelb-cart-page .product-remove a:before,
        .rafflelb-cart-page a.remove:before{
            content:"×" !important;
            display:block !important;
            position:static !important;
            color:#c5cac0 !important;
            font-size:18px !important;
            line-height:1 !important;
        }

        /* Coupon / update */
        .rafflelb-cart-page .woocommerce-cart-form .actions{
            display:grid !important;
            grid-template-columns:minmax(0,1fr) auto !important;
            gap:18px !important;
            align-items:center !important;
            padding:0 !important;
        }

        .rafflelb-cart-page .woocommerce-cart-form .coupon{
            display:grid !important;
            grid-template-columns:minmax(180px,1fr) auto !important;
            gap:10px !important;
            width:100% !important;
            max-width:520px !important;
            margin:0 !important;
        }

        .rafflelb-cart-page .woocommerce-cart-form .coupon input.input-text{
            width:100% !important;
            min-width:0 !important;
            height:46px !important;
            margin:0 !important;
            padding:0 14px !important;
            border:1px solid #343832 !important;
            border-radius:10px !important;
            background:#191b17 !important;
            color:#fff !important;
        }

        .rafflelb-cart-page .woocommerce-cart-form .coupon button,
        .rafflelb-cart-page button[name="update_cart"]{
            height:46px !important;
            min-height:46px !important;
            padding:0 22px !important;
            margin:0 !important;
            display:inline-flex !important;
            align-items:center !important;
            justify-content:center !important;
            border:0 !important;
            border-radius:999px !important;
            background:#1d201a !important;
            color:#fff !important;
            font-size:11px !important;
            font-weight:900 !important;
            line-height:1 !important;
        }

        /* Totals */
        .rafflelb-cart-page .cart-totals-inner h2,
        .rafflelb-cart-page .cart_totals h2{
            margin:0 0 20px !important;
            color:#fff !important;
            font-size:23px !important;
            white-space:nowrap !important;
        }

        .rafflelb-cart-page .cart-totals-inner table,
        .rafflelb-cart-page .cart_totals table{
            width:100% !important;
            margin:0 0 20px !important;
            border:0 !important;
            background:transparent !important;
        }

        .rafflelb-cart-page .cart-totals-inner th,
        .rafflelb-cart-page .cart-totals-inner td,
        .rafflelb-cart-page .cart_totals th,
        .rafflelb-cart-page .cart_totals td{
            padding:15px 0 !important;
            border-color:#2a2e28 !important;
            background:transparent !important;
            color:#d9ddd3 !important;
        }

        .rafflelb-cart-page .cart-totals-inner .order-total td,
        .rafflelb-cart-page .cart-totals-inner .order-total .amount,
        .rafflelb-cart-page .cart_totals .order-total td,
        .rafflelb-cart-page .cart_totals .order-total .amount{
            color:#caff16 !important;
            font-size:22px !important;
            font-weight:900 !important;
        }

        .rafflelb-cart-page .wc-proceed-to-checkout{
            padding:0 !important;
        }

        .rafflelb-cart-page .wc-proceed-to-checkout a.checkout-button{
            width:100% !important;
            min-height:54px !important;
            display:flex !important;
            align-items:center !important;
            justify-content:center !important;
            border-radius:999px !important;
            background:#caff16 !important;
            color:#090a08 !important;
            font-weight:900 !important;
            text-align:center !important;
            line-height:1.1 !important;
        }

        @media(max-width:1080px){
            .rafflelb-cart-page .cart-content-wrapper{
                grid-template-columns:minmax(0,1fr) 320px !important;
                gap:24px !important;
            }

            .rafflelb-cart-page .woocommerce-cart-form table.shop_table tr.cart_item{
                grid-template-columns:30px 76px minmax(160px,1fr) 90px 124px 92px !important;
                gap:12px !important;
            }
        }

        @media(max-width:900px){
            .rafflelb-cart-page .cart-content-wrapper{
                display:block !important;
            }

            .rafflelb-cart-page .cart-content-wrapper > .woocommerce-cart-form,
            .rafflelb-cart-page .cart-content-wrapper > .cart-totals-section,
            .rafflelb-cart-page .cart-content-wrapper > .cart_totals{
                width:100% !important;
                margin-bottom:20px !important;
            }

            .rafflelb-cart-page .woocommerce-cart-form table.shop_table tr.cart_item{
                grid-template-columns:30px 76px minmax(0,1fr) !important;
            }

            .rafflelb-cart-page .product-price,
            .rafflelb-cart-page .product-quantity,
            .rafflelb-cart-page .product-subtotal{
                grid-column:3 !important;
                text-align:left !important;
                margin-top:8px !important;
            }

            .rafflelb-cart-page .product-quantity .quantity{
                margin:0 !important;
            }

            .rafflelb-cart-page .woocommerce-cart-form .actions{
                display:block !important;
            }

            .rafflelb-cart-page .woocommerce-cart-form .coupon{
                max-width:none !important;
                margin-bottom:12px !important;
            }

            .rafflelb-cart-page button[name="update_cart"]{
                width:100% !important;
            }
        }

        /* v0.10.4 cart typography + coupon alignment */
        .rafflelb-cart-page .woocommerce-cart-form,
        .rafflelb-cart-page .cart-totals-section,
        .rafflelb-cart-page .cart_totals,
        .rafflelb-cart-page .woocommerce-cart-form input,
        .rafflelb-cart-page .woocommerce-cart-form button,
        .rafflelb-cart-page .cart_totals a,
        .rafflelb-cart-page .cart_totals button{
            font-family:Inter, Arial, Helvetica, sans-serif !important;
        }

        .rafflelb-cart-page .product-name a{
            font-family:Inter, Arial, Helvetica, sans-serif !important;
            font-size:16px !important;
            font-weight:700 !important;
            letter-spacing:-0.01em !important;
        }

        .rafflelb-cart-page .product-name,
        .rafflelb-cart-page .product-name span,
        .rafflelb-cart-page .product-name small,
        .rafflelb-cart-page .product-name .sku_wrapper,
        .rafflelb-cart-page .product-name .sku{
            font-family:Inter, Arial, Helvetica, sans-serif !important;
        }

        .rafflelb-cart-page .product-price:before,
        .rafflelb-cart-page .product-quantity:before,
        .rafflelb-cart-page .product-subtotal:before{
            font-family:Inter, Arial, Helvetica, sans-serif !important;
            font-size:9px !important;
            font-weight:700 !important;
            letter-spacing:.12em !important;
        }

        .rafflelb-cart-page .cart_totals h2{
            font-family:Inter, Arial, Helvetica, sans-serif !important;
            font-weight:700 !important;
            letter-spacing:-0.02em !important;
        }

        .rafflelb-cart-page .woocommerce-cart-form .coupon{
            display:flex !important;
            align-items:center !important;
            justify-content:flex-start !important;
            gap:10px !important;
            width:auto !important;
            max-width:none !important;
        }

        .rafflelb-cart-page .woocommerce-cart-form .coupon input.input-text{
            flex:0 1 250px !important;
            width:250px !important;
            min-width:180px !important;
            max-width:250px !important;
        }

        .rafflelb-cart-page .woocommerce-cart-form .coupon button{
            flex:0 0 auto !important;
            margin:0 !important;
        }

        .rafflelb-cart-page .woocommerce-cart-form .actions{
            grid-template-columns:minmax(0,1fr) auto !important;
            align-items:center !important;
        }

        @media(max-width:700px){
            .rafflelb-cart-page .woocommerce-cart-form .coupon{
                display:grid !important;
                grid-template-columns:1fr !important;
                width:100% !important;
            }

            .rafflelb-cart-page .woocommerce-cart-form .coupon input.input-text{
                width:100% !important;
                max-width:none !important;
            }

            .rafflelb-cart-page .woocommerce-cart-form .coupon button{
                width:100% !important;
            }
        }

        
        /* v0.10.5 cart action placement */
        .rafflelb-cart-page .woocommerce-cart-form .actions{
            display:grid !important;
            grid-template-columns:minmax(0,1fr) 150px !important;
            column-gap:18px !important;
            align-items:start !important;
        }

        .rafflelb-cart-page .woocommerce-cart-form .coupon{
            grid-column:1 !important;
            display:flex !important;
            align-items:center !important;
            justify-content:flex-start !important;
            gap:10px !important;
            width:auto !important;
            max-width:none !important;
            margin:0 !important;
        }

        .rafflelb-cart-page .woocommerce-cart-form .coupon input.input-text{
            width:250px !important;
            max-width:250px !important;
            flex:0 1 250px !important;
        }

        .rafflelb-cart-page button[name="update_cart"]{
            grid-column:2 !important;
            justify-self:end !important;
            align-self:start !important;
            width:150px !important;
            margin:0 !important;
        }

        @media(max-width:700px){
            .rafflelb-cart-page .woocommerce-cart-form .actions{
                display:block !important;
            }

            .rafflelb-cart-page .woocommerce-cart-form .coupon{
                display:grid !important;
                grid-template-columns:1fr !important;
                width:100% !important;
                margin-bottom:12px !important;
            }

            .rafflelb-cart-page .woocommerce-cart-form .coupon input.input-text,
            .rafflelb-cart-page button[name="update_cart"]{
                width:100% !important;
                max-width:none !important;
            }
        }

        /* v0.10.6: empty-cart readability */
        .rafflelb-cart-page .cart-empty,
        .rafflelb-cart-page .woocommerce-info.cart-empty,
        .rafflelb-cart-page .woocommerce .wd-empty-page,
        .rafflelb-cart-page .woocommerce .wd-empty-page-title,
        .rafflelb-cart-page .woocommerce .wd-empty-page-text,
        .rafflelb-cart-page .woocommerce .wd-empty-page-text p,
        .rafflelb-cart-page .woocommerce .return-to-shop{
            color:#dfe3da !important;
            opacity:1 !important;
        }
        .rafflelb-cart-page .woocommerce .wd-empty-page-title,
        .rafflelb-cart-page .woocommerce .cart-empty{
            color:#ffffff !important;
        }
        .rafflelb-cart-page .woocommerce .return-to-shop .button{
            color:#090a08 !important;
            background:#caff16 !important;
            opacity:1 !important;
        }

        /* v0.10.7: clean phone cart layout */
        @media(max-width:700px){
            .rafflelb-cart-page .site-content{
                padding-top:24px !important;
                padding-bottom:36px !important;
            }
            .rafflelb-cart-page .site-content > .container,
            .rafflelb-cart-page .main-page-wrapper .container{
                width:calc(100% - 28px) !important;
            }
            .rafflelb-cart-page .cart-content-wrapper > .woocommerce-cart-form{
                padding:20px 16px !important;
            }
            .rafflelb-cart-page .woocommerce-cart-form table.shop_table tr.cart_item{
                grid-template-columns:82px minmax(0,1fr) 34px !important;
                gap:14px 12px !important;
                align-items:start !important;
            }
            .rafflelb-cart-page .product-thumbnail{
                grid-column:1 !important;
                grid-row:1 !important;
            }
            .rafflelb-cart-page .product-thumbnail img{
                width:82px !important;
                height:82px !important;
            }
            .rafflelb-cart-page .product-name{
                grid-column:2 !important;
                grid-row:1 !important;
                overflow-wrap:anywhere !important;
            }
            .rafflelb-cart-page .product-name a{
                font-size:15px !important;
                line-height:1.25 !important;
            }
            .rafflelb-cart-page .product-remove{
                grid-column:3 !important;
                grid-row:1 !important;
                align-self:start !important;
            }
            .rafflelb-cart-page .product-price,
            .rafflelb-cart-page .product-quantity,
            .rafflelb-cart-page .product-subtotal{
                grid-column:1 / -1 !important;
                display:grid !important;
                grid-template-columns:minmax(110px,1fr) minmax(0,1fr) !important;
                align-items:center !important;
                gap:14px !important;
                width:100% !important;
                margin:0 !important;
                padding-top:14px !important;
                border-top:1px solid #292d27 !important;
                text-align:left !important;
            }
            .rafflelb-cart-page .product-price:before,
            .rafflelb-cart-page .product-quantity:before,
            .rafflelb-cart-page .product-subtotal:before{
                margin:0 !important;
            }
            .rafflelb-cart-page .product-quantity .quantity{
                margin:0 !important;
            }
            .rafflelb-cart-page .cart-content-wrapper .cart-totals-inner,
            .rafflelb-cart-page .cart-content-wrapper .cart_totals{
                padding:22px 18px !important;
            }
            .rafflelb-cart-page .cart_totals h2,
            .rafflelb-cart-page .cart_totals th,
            .rafflelb-cart-page .cart_totals td,
            .rafflelb-cart-page .cart_totals .cart-subtotal th,
            .rafflelb-cart-page .cart_totals .cart-subtotal td{
                color:#eef1eb !important;
                opacity:1 !important;
            }
        }

        </style>
        <?php
    }

    public static function account_body_class($classes) {
        // Account presentation now lives exclusively in the RaffleLB Account plugin.
        // Keep this callback/function as a compatibility bridge so existing hook
        // identities, priorities, and third-party callers remain stable.
        if (class_exists('RaffleLB_Account', false) && RaffleLB_Account::ready()) {
            return RaffleLB_Account::account_body_class(...func_get_args());
        }

        return $classes;
    }

    public static function account_styles() {
        // Account presentation now lives exclusively in the RaffleLB Account plugin.
        // Keep this callback/function as a compatibility bridge so existing hook
        // identities, priorities, and third-party callers remain stable.
        if (class_exists('RaffleLB_Account', false) && RaffleLB_Account::ready()) {
            return RaffleLB_Account::account_styles(...func_get_args());
        }

        return;
    }

    public static function auto_progress(){
        global $product;
        if(!$product instanceof WC_Product)return;
        $pid=self::draw_id($product);
        if(!$pid)return;
        $s=self::stats($pid,true);
        if($s)echo self::render_progress($s);
    }

    public static function progress_shortcode($atts){
        $a=shortcode_atts(['product_id'=>0],$atts);
        $pid=absint($a['product_id']);
        if(!$pid){global $product;if($product instanceof WC_Product)$pid=self::draw_id($product);}
        if(!$pid)return '';
        $s=self::stats($pid,true);
        return $s?self::render_progress($s):'';
    }

    private static function render_progress($s){
        if (!empty($s['status']) && $s['status'] === 'winner_selected' && !empty($s['product_id'])) {
            $result = self::get_draw_result(absint($s['product_id']));
            if ($result) {
                $winner_name = self::winner_masked_name($result);
                $selected_display = !empty($result->selected_at) ? mysql2date('M j, Y \a\t g:i A', $result->selected_at) : '';

                ob_start(); ?>
                <div class="rafflelb-live-panel" style="--rl-progress:100%;">
                  <div class="rlp-top">
                    <span class="rlp-status">🏆 WINNER SELECTED</span>
                    <span class="rlp-percent">DRAW COMPLETE</span>
                  </div>
                  <div class="rlp-winner-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin:18px 0 14px">
                    <div class="rlp-winner-box">
                      <span class="rlp-winner-label">WINNER</span>
                      <strong class="rlp-winner-value"><?php echo esc_html($winner_name); ?></strong>
                    </div>
                    <div class="rlp-winner-box">
                      <span class="rlp-winner-label">WINNING ENTRY</span>
                      <strong class="rlp-winner-value">#<?php echo esc_html(str_pad((string)$result->entry_number,3,'0',STR_PAD_LEFT)); ?></strong>
                    </div>
                  </div>
                  <div class="rlp-winner-meta">
                    <span><?php echo esc_html($selected_display); ?></span>
                  </div>
                  <div class="rlp-bar"><span></span></div>
                  <div class="rlp-foot rlp-foot-solo"><span>Final result permanently recorded</span></div>
                </div>
                <style>
                .rafflelb-live-panel{
                    margin:18px 0 24px !important;
                    padding:18px 20px !important;
                    border:1px solid #2b2e29 !important;
                    border-radius:16px !important;
                    background:linear-gradient(145deg,#1b1d1a,#101110) !important;
                    color:#fff !important;
                    box-shadow:none !important;
                    font-family:Inter,"Segoe UI",Arial,sans-serif !important;
                }
                .rafflelb-live-panel *{box-sizing:border-box}
                .rafflelb-live-panel .rlp-top,
                .rafflelb-live-panel .rlp-foot{
                    display:flex !important;
                    justify-content:space-between !important;
                    align-items:center !important;
                    gap:14px !important;
                }
                .rafflelb-live-panel .rlp-status{
                    display:inline-flex !important;
                    align-items:center !important;
                    gap:7px !important;
                    padding:7px 11px !important;
                    border-radius:999px !important;
                    background:#caff16 !important;
                    color:#080908 !important;
                    -webkit-text-fill-color:#080908 !important;
                    font-size:9px !important;
                    font-weight:900 !important;
                    letter-spacing:1px !important;
                }
                .rafflelb-live-panel .rlp-percent{
                    color:#c9cec4 !important;
                    -webkit-text-fill-color:#c9cec4 !important;
                    font-size:9px !important;
                    font-weight:700 !important;
                }
                .rafflelb-live-panel .rlp-winner-box{
                    padding:15px !important;
                    border:1px solid rgba(202,255,22,.35) !important;
                    border-radius:12px !important;
                    background:rgba(202,255,22,.05) !important;
                }
                .rafflelb-live-panel .rlp-winner-box .rlp-winner-label{
                    display:block !important;
                    color:#9da697 !important;
                    -webkit-text-fill-color:#9da697 !important;
                    font-size:9px !important;
                    font-weight:800 !important;
                    letter-spacing:.11em !important;
                    margin-bottom:6px !important;
                }
                .rafflelb-live-panel .rlp-winner-box .rlp-winner-value{
                    display:block !important;
                    color:#ffffff !important;
                    -webkit-text-fill-color:#ffffff !important;
                    font-size:20px !important;
                    font-weight:800 !important;
                    line-height:1.2 !important;
                }
                .rafflelb-live-panel .rlp-winner-box:nth-child(2) .rlp-winner-value{
                    color:#caff16 !important;
                    -webkit-text-fill-color:#caff16 !important;
                }
                .rafflelb-live-panel .rlp-winner-meta{
                    display:flex !important;
                    justify-content:flex-start !important;
                    gap:12px !important;
                    color:#aeb5a8 !important;
                    -webkit-text-fill-color:#aeb5a8 !important;
                    font-size:9px !important;
                    margin-bottom:8px !important;
                }
                .rafflelb-live-panel .rlp-bar{
                    height:7px !important;
                    margin:16px 0 12px !important;
                    background:#343732 !important;
                    border-radius:999px !important;
                    overflow:hidden !important;
                }
                .rafflelb-live-panel .rlp-bar span{
                    display:block !important;
                    width:100% !important;
                    height:100% !important;
                    background:#caff16 !important;
                    border-radius:999px !important;
                }
                .rafflelb-live-panel .rlp-foot{
                    padding-top:10px !important;
                    border-top:1px solid rgba(255,255,255,.07) !important;
                    color:#aeb5a8 !important;
                    -webkit-text-fill-color:#aeb5a8 !important;
                    font-size:9px !important;
                }
                .rafflelb-live-panel .rlp-foot strong{
                    color:#caff16 !important;
                    -webkit-text-fill-color:#caff16 !important;
                    margin-left:auto !important;
                }
                @media(max-width:600px){
                    .rafflelb-live-panel .rlp-winner-grid{grid-template-columns:1fr !important}
                    .rafflelb-live-panel .rlp-winner-meta{flex-direction:column !important}
                }
                </style>
                <?php return ob_get_clean();
            }
        }

        ob_start(); ?>
        <div class="rafflelb-live-panel" style="--rl-progress:<?php echo esc_attr($s['percent']); ?>%;">
          <div class="rlp-top"><span class="rlp-status"><i></i><?php echo (in_array($s['status'],['ready_to_draw','winner_selected'],true) || $s['left']<=0)?'DRAW CLOSED':'LIVE NOW'; ?></span><span class="rlp-percent"><?php echo esc_html($s['percent']); ?>% CLAIMED</span></div>
          <div class="rlp-counts"><div><strong><?php echo esc_html($s['claimed']); ?></strong><span>ENTRIES CLAIMED</span></div><div class="rlp-right"><strong><?php echo esc_html($s['available']); ?></strong><span>AVAILABLE NOW</span></div></div>
          <div class="rlp-bar"><span></span></div>
          <div class="rlp-foot"><span><?php echo esc_html($s['left']); ?> left total</span><strong><?php echo esc_html($s['total']); ?> TOTAL ENTRIES</strong></div>
          <?php if($s['held']>0): ?><div class="rlp-held"><?php echo esc_html($s['held']); ?> temporarily reserved in other carts</div><?php endif; ?>
        </div>
        <style>
        .rafflelb-live-panel{margin:18px 0 24px;padding:18px 20px;border:1px solid #2b2e29;border-radius:16px;background:linear-gradient(145deg,#1b1d1a,#101110);color:#fff}
        .rafflelb-live-panel *{box-sizing:border-box}.rlp-top,.rlp-counts,.rlp-foot{display:flex;justify-content:space-between;align-items:center;gap:14px}
        .rlp-status{display:inline-flex;align-items:center;gap:7px;padding:7px 11px;border-radius:999px;background:#caff16;color:#080908;font-size:9px;font-weight:900;letter-spacing:1px}
        .rlp-status i{width:5px;height:5px;border-radius:50%;background:#080908}.rlp-percent,.rlp-counts span,.rlp-foot,.rlp-held{color:#8c9087;font-size:9px}
        .rlp-counts{margin-top:18px}.rlp-counts strong{font-size:30px;line-height:1}.rlp-right{text-align:right}.rlp-bar{height:7px;margin:16px 0 12px;background:#343732;border-radius:999px;overflow:hidden}
        .rlp-bar span{display:block;width:var(--rl-progress);height:100%;background:#caff16;border-radius:999px}.rlp-foot{padding-top:10px;border-top:1px solid rgba(255,255,255,.07)}.rlp-foot strong{color:#caff16}.rlp-held{margin-top:9px;text-align:right}

        /* FINAL v0.7.2 desktop layout override */
        @media(min-width:901px){
            .rafflelb-checkout-page form.checkout{
                display:flex !important;
                flex-wrap:wrap !important;
                gap:30px !important;
                align-items:flex-start !important;
                grid-template-columns:none !important;
            }

            .rafflelb-checkout-page #customer_details{
                flex:0 0 calc(58% - 15px) !important;
                width:calc(58% - 15px) !important;
                max-width:calc(58% - 15px) !important;
                min-width:0 !important;
            }

            .rafflelb-checkout-page form.checkout > .checkout-order-review,
            .rafflelb-checkout-page form.checkout > .wd-checkout-order-review,
            .rafflelb-checkout-page form.checkout > .woocommerce-checkout-review-order,
            .rafflelb-checkout-page form.checkout > div:has(> #order_review){
                flex:0 0 calc(42% - 15px) !important;
                width:calc(42% - 15px) !important;
                max-width:calc(42% - 15px) !important;
                min-width:0 !important;
                float:none !important;
                clear:none !important;
                margin:0 !important;
                padding:0 !important;
            }

            .rafflelb-checkout-page #order_review{
                width:100% !important;
                max-width:100% !important;
                min-width:0 !important;
                margin:0 !important;
            }
        }


        /* v0.7.3 visual cleanup */
        .rafflelb-checkout-page .woocommerce-form-coupon-toggle{
            color:#d8dcd2 !important;
        }
        .rafflelb-checkout-page .woocommerce-form-coupon-toggle .woocommerce-info{
            color:#d8dcd2 !important;
            background:#11120f !important;
            border:1px solid #2b2e29 !important;
        }
        .rafflelb-checkout-page .woocommerce-form-coupon-toggle a{
            color:#caff16 !important;
            text-decoration:none !important;
        }

        /* Keep all order-summary text readable */
        .rafflelb-checkout-page #order_review,
        .rafflelb-checkout-page #order_review p,
        .rafflelb-checkout-page #order_review span,
        .rafflelb-checkout-page #order_review label,
        .rafflelb-checkout-page #order_review small{
            color:#d9ddd4 !important;
        }
        .rafflelb-checkout-page #order_review table.shop_table th,
        .rafflelb-checkout-page #order_review table.shop_table td,
        .rafflelb-checkout-page #order_review table.shop_table td.product-name,
        .rafflelb-checkout-page #order_review table.shop_table .cart-subtotal th,
        .rafflelb-checkout-page #order_review table.shop_table .cart-subtotal td{
            color:#d9ddd4 !important;
            opacity:1 !important;
        }
        .rafflelb-checkout-page #order_review table.shop_table thead th{
            color:#8f9488 !important;
        }
        .rafflelb-checkout-page #order_review table.shop_table .order-total th,
        .rafflelb-checkout-page #order_review table.shop_table .order-total td,
        .rafflelb-checkout-page #order_review table.shop_table .order-total strong,
        .rafflelb-checkout-page #order_review table.shop_table .order-total .amount{
            color:#caff16 !important;
        }

        /* Align every Entry Details field in one clean column */
        .rafflelb-checkout-page #customer_details .woocommerce-billing-fields__field-wrapper{
            display:block !important;
        }
        .rafflelb-checkout-page #customer_details .form-row,
        .rafflelb-checkout-page #customer_details .form-row-first,
        .rafflelb-checkout-page #customer_details .form-row-last,
        .rafflelb-checkout-page #customer_details .form-row-wide{
            width:100% !important;
            max-width:100% !important;
            float:none !important;
            clear:both !important;
            margin-left:0 !important;
            margin-right:0 !important;
        }

        /* Remove unused Additional Information area completely */
        .rafflelb-checkout-page .woocommerce-additional-fields,
        .rafflelb-checkout-page .woocommerce-additional-fields__field-wrapper,
        .rafflelb-checkout-page #customer_details .col-2{
            display:none !important;
        }

        /* Remove WoodMart white ticket strips / scalloped background around review */
        .rafflelb-checkout-page form.checkout > .checkout-order-review,
        .rafflelb-checkout-page form.checkout > .wd-checkout-order-review,
        .rafflelb-checkout-page form.checkout > .woocommerce-checkout-review-order,
        .rafflelb-checkout-page form.checkout > div:has(> #order_review){
            background:transparent !important;
            border:0 !important;
            box-shadow:none !important;
        }
        .rafflelb-checkout-page form.checkout > .checkout-order-review:before,
        .rafflelb-checkout-page form.checkout > .checkout-order-review:after,
        .rafflelb-checkout-page form.checkout > .wd-checkout-order-review:before,
        .rafflelb-checkout-page form.checkout > .wd-checkout-order-review:after,
        .rafflelb-checkout-page form.checkout > .woocommerce-checkout-review-order:before,
        .rafflelb-checkout-page form.checkout > .woocommerce-checkout-review-order:after,
        .rafflelb-checkout-page form.checkout > div:has(> #order_review):before,
        .rafflelb-checkout-page form.checkout > div:has(> #order_review):after{
            display:none !important;
            content:none !important;
            background:none !important;
        }


        /* v0.7.4 readability fixes */
        .rafflelb-checkout-page .woocommerce-form-coupon-toggle,
        .rafflelb-checkout-page .woocommerce-form-coupon-toggle .woocommerce-info,
        .rafflelb-checkout-page .woocommerce-form-coupon-toggle .woocommerce-info::before {
            color:#f4f5f1 !important;
        }
        .rafflelb-checkout-page .woocommerce-form-coupon-toggle a,
        .rafflelb-checkout-page .woocommerce-form-coupon-toggle a:link,
        .rafflelb-checkout-page .woocommerce-form-coupon-toggle a:visited {
            color:#caff16 !important;
            opacity:1 !important;
        }
        .rafflelb-checkout-page form.checkout_coupon input.input-text,
        .rafflelb-checkout-page form.checkout_coupon input[type="text"] {
            color:#ffffff !important;
            -webkit-text-fill-color:#ffffff !important;
            caret-color:#caff16 !important;
            background:#181b16 !important;
            border-color:#343a30 !important;
            opacity:1 !important;
        }
        .rafflelb-checkout-page form.checkout_coupon input::placeholder {
            color:#8f9589 !important;
            opacity:1 !important;
        }

        /* The order table itself is white, therefore its contents must be dark */
        .rafflelb-checkout-page #order_review table.shop_table,
        .rafflelb-checkout-page #order_review table.shop_table thead,
        .rafflelb-checkout-page #order_review table.shop_table tbody,
        .rafflelb-checkout-page #order_review table.shop_table tfoot {
            background:#ffffff !important;
        }
        .rafflelb-checkout-page #order_review table.shop_table th,
        .rafflelb-checkout-page #order_review table.shop_table td,
        .rafflelb-checkout-page #order_review table.shop_table td *,
        .rafflelb-checkout-page #order_review table.shop_table th *,
        .rafflelb-checkout-page #order_review table.shop_table .amount {
            color:#11130f !important;
            opacity:1 !important;
        }
        .rafflelb-checkout-page #order_review table.shop_table thead th {
            color:#686d63 !important;
        }
        .rafflelb-checkout-page #order_review table.shop_table .order-total th {
            color:#b8ee00 !important;
            font-weight:800 !important;
        }
        .rafflelb-checkout-page #order_review table.shop_table .order-total td,
        .rafflelb-checkout-page #order_review table.shop_table .order-total td *,
        .rafflelb-checkout-page #order_review table.shop_table .order-total .amount {
            color:#11130f !important;
            font-weight:800 !important;
        }

        
        /* v0.14.0: winner experience */
        .rafflelb-account-page .rafflelb-winner-summary,
        .rafflelb-account-page .rafflelb-winning-entry-card{
            position:relative !important;
            border:1px solid #394327 !important;
            border-radius:16px !important;
            background:linear-gradient(135deg,#151912 0%,#0d100b 100%) !important;
            padding:22px !important;
            margin:0 0 22px !important;
            box-shadow:0 16px 35px rgba(0,0,0,.18) !important;
        }

        .rafflelb-account-page .rafflelb-winner-dismiss{
            position:absolute;
            top:14px;
            right:14px;
            width:28px;
            height:28px;
            display:flex;
            align-items:center;
            justify-content:center;
            border:1px solid #394327;
            border-radius:50%;
            background:#10130e;
            color:#9da594;
            font-size:16px;
            line-height:1;
            cursor:pointer;
            transition:background .15s ease,color .15s ease,border-color .15s ease;
        }
        .rafflelb-account-page .rafflelb-winner-dismiss:hover{
            background:#caff16;
            color:#0b0d08;
            border-color:#caff16;
        }

        .rafflelb-account-page .rafflelb-winner-kicker,
        .rafflelb-account-page .rafflelb-winning-entry-label{
            display:inline-block;
            background:#caff16;
            color:#0b0d08;
            font-size:11px;
            font-weight:800;
            letter-spacing:.12em;
            border-radius:999px;
            padding:6px 10px;
            margin-bottom:10px;
        }

        .rafflelb-account-page .rafflelb-winner-summary h3,
        .rafflelb-account-page .rafflelb-winning-entry-card h3{
            margin:0 0 10px;
            color:#fff !important;
        }

        .rafflelb-account-page .rafflelb-winner-summary-row{
            display:grid;
            grid-template-columns:repeat(3,minmax(0,1fr));
            gap:12px;
            margin:16px 0;
        }

        .rafflelb-account-page .rafflelb-winner-summary-row > div{
            border:1px solid #2a2f23;
            border-radius:12px;
            background:#10130e;
            padding:12px 14px;
        }

        .rafflelb-account-page .rafflelb-winner-summary-row span{
            display:block;
            color:#9da594;
            font-size:11px;
            text-transform:uppercase;
            letter-spacing:.08em;
            margin-bottom:4px;
        }

        .rafflelb-account-page .rafflelb-winner-summary-row strong{
            color:#fff;
        }

        .rafflelb-account-page .rafflelb-winner-summary-note{
            color:#c1c7bb;
            margin:0 0 15px;
        }

        .rafflelb-account-page .rafflelb-view-winning-entry{
            background:#caff16 !important;
            color:#0b0d08 !important;
            border-color:#caff16 !important;
            font-weight:800 !important;
        }

        .rafflelb-account-page .rafflelb-winning-cards{
            display:grid;
            grid-template-columns:1fr;
            gap:22px;
            margin:0 0 30px;
        }

        .rafflelb-account-page .rafflelb-winning-entry-card{
            display:block !important;
            width:100% !important;
            box-sizing:border-box !important;
            border:1px solid rgba(202,255,22,.68) !important;
            border-radius:16px !important;
            background:linear-gradient(135deg,#11150e 0%,#0d100b 100%) !important;
            padding:22px 24px !important;
            margin:0 !important;
            overflow:hidden !important;
            box-shadow:0 10px 28px rgba(0,0,0,.20) !important;
        }

        .rafflelb-account-page .rafflelb-winning-entry-topline{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:12px;
            padding-bottom:14px;
            margin-bottom:18px;
            border-bottom:1px solid rgba(202,255,22,.16);
        }

        .rafflelb-account-page .rafflelb-winning-entry-topline .rafflelb-winning-entry-label{
            margin:0;
        }

        .rafflelb-account-page .rafflelb-winning-entry-count{
            color:#9da594;
            font-size:10px;
            font-weight:800;
            letter-spacing:.1em;
            white-space:nowrap;
        }

        .rafflelb-account-page .rafflelb-winning-entry-body{
            display:flex;
            align-items:center;
            gap:18px;
            min-height:92px;
        }

        .rafflelb-account-page .rafflelb-winning-entry-copy h3{
            margin:0 0 10px !important;
        }

        .rafflelb-account-page .rafflelb-winning-entry-copy p{
            margin:0 0 10px !important;
        }

        .rafflelb-account-page .rafflelb-winning-entry-copy small{
            display:block;
            margin-top:6px;
            opacity:.78;
        }

        .rafflelb-account-page .rafflelb-winning-entry-icon{
            flex:0 0 auto;
            font-size:38px;
            line-height:1;
        }

        .rafflelb-account-page .rafflelb-winning-entry-copy p{
            margin:0 0 5px;
            color:#d6dbd1;
        }

        .rafflelb-account-page .rafflelb-winning-entry-copy small{
            color:#8f9888;
        }

        .rafflelb-account-page .rafflelb-winning-entry-row td{
            background:#18200f !important;
            border-color:#46562a !important;
        }

        .rafflelb-account-page .rafflelb-winning-badge{
            display:inline-block;
            background:#caff16;
            color:#0b0d08;
            font-weight:800;
            font-size:11px;
            border-radius:999px;
            padding:6px 9px;
            white-space:nowrap;
        }

        .rafflelb-account-page .rafflelb-result-complete,
        .rafflelb-account-page .rafflelb-result-pending,
        .rafflelb-account-page .rafflelb-result-live{
            display:inline-block;
            font-size:10px;
            font-weight:800;
            letter-spacing:.06em;
            white-space:nowrap;
        }

        .rafflelb-account-page .rafflelb-result-complete{color:#aeb5a8;}
        .rafflelb-account-page .rafflelb-result-pending{color:#f2d875;}
        .rafflelb-account-page .rafflelb-result-live{color:#caff16;}

        @media (max-width:767px){
            .rafflelb-account-page .rafflelb-winner-summary-row{
                grid-template-columns:1fr;
            }
            .rafflelb-account-page .rafflelb-winning-entry-body{
                align-items:flex-start;
            }
            .rafflelb-account-page .rafflelb-winning-entry-topline{
                align-items:flex-start;
                flex-direction:column;
            }
        }


        /* v0.15.1 CONTRAST HARDENING
           Lime RaffleLB CTAs must always use black text, including WoodMart inner spans. */
        body.rafflelb-account-page .site-content .woocommerce-MyAccount-content a.button,
        body.rafflelb-account-page .site-content .woocommerce-MyAccount-content a.woocommerce-button,
        body.rafflelb-account-page .site-content .woocommerce-MyAccount-content .woocommerce-orders-table__cell-order-actions a.button,
        body.rafflelb-account-page .site-content .rafflelb-view-winning-entry{
            color:#090a08 !important;
            -webkit-text-fill-color:#090a08 !important;
        }

        body.rafflelb-account-page .site-content .woocommerce-MyAccount-content a.button *,
        body.rafflelb-account-page .site-content .woocommerce-MyAccount-content a.woocommerce-button *,
        body.rafflelb-account-page .site-content .woocommerce-MyAccount-content .woocommerce-orders-table__cell-order-actions a.button *,
        body.rafflelb-account-page .site-content .rafflelb-view-winning-entry *{
            color:#090a08 !important;
            -webkit-text-fill-color:#090a08 !important;
        }

        body.rafflelb-account-page .site-content .woocommerce-MyAccount-content a.button:hover,
        body.rafflelb-account-page .site-content .woocommerce-MyAccount-content a.button:focus,
        body.rafflelb-account-page .site-content .woocommerce-MyAccount-content a.woocommerce-button:hover,
        body.rafflelb-account-page .site-content .woocommerce-MyAccount-content a.woocommerce-button:focus,
        body.rafflelb-account-page .site-content .rafflelb-view-winning-entry:hover,
        body.rafflelb-account-page .site-content .rafflelb-view-winning-entry:focus{
            color:#090a08 !important;
            -webkit-text-fill-color:#090a08 !important;
        }

        /* Same rule for other bright-lime primary CTAs in RaffleLB checkout/cart. */
        body.rafflelb-cart-page .site-content .checkout-button,
        body.rafflelb-checkout-page .site-content #place_order{
            color:#090a08 !important;
            -webkit-text-fill-color:#090a08 !important;
        }
        body.rafflelb-cart-page .site-content .checkout-button *,
        body.rafflelb-checkout-page .site-content #place_order *{
            color:#090a08 !important;
            -webkit-text-fill-color:#090a08 !important;
        }

</style>
        <?php return ob_get_clean();
    }
}

/* =========================================================
 * v0.31.1 — Premium My Account (layout-safe rebuild)
 * IMPORTANT: does NOT change WoodMart's account columns/widths.
 * ========================================================= */

add_action('woocommerce_account_dashboard', function () {
        // Account presentation now lives exclusively in the RaffleLB Account plugin.
        // Keep this callback/function as a compatibility bridge so existing hook
        // identities, priorities, and third-party callers remain stable.
        if (class_exists('RaffleLB_Account', false) && RaffleLB_Account::ready()) {
            return RaffleLB_Account::legacy_callback_16373(...func_get_args());
        }

        return;
    }, 1);

add_action('wp_head', function () {
        // Account presentation now lives exclusively in the RaffleLB Account plugin.
        // Keep this callback/function as a compatibility bridge so existing hook
        // identities, priorities, and third-party callers remain stable.
        if (class_exists('RaffleLB_Account', false) && RaffleLB_Account::ready()) {
            return RaffleLB_Account::legacy_callback_16412(...func_get_args());
        }

        return;
    }, 99);

/* =========================================================
 * v0.33.73 — Product image containment consistency.
 * Featured Products + Shop cards now always show the complete
 * product image without cover-cropping or zoom transforms.
 * ========================================================= */

RaffleLB_Draw_Engine::init();

/* v0.33.77 — final My Raffles visual correction.
 * Printed after theme/plugin styles so cached or overly broad Woodmart rules
 * cannot restore the old badge position or condensed account typography. */
add_action('wp_footer', static function () {
        // Account presentation now lives exclusively in the RaffleLB Account plugin.
        // Keep this callback/function as a compatibility bridge so existing hook
        // identities, priorities, and third-party callers remain stable.
        if (class_exists('RaffleLB_Account', false) && RaffleLB_Account::ready()) {
            return RaffleLB_Account::legacy_callback_17070(...func_get_args());
        }

        return;
    }, PHP_INT_MAX);

add_action('wp_head', function(){ if(!is_admin()){ ?>
<style>

/* ==========================================================
   v0.26.1 — Live Raffles grid balance / card polish
   ========================================================== */

/* Balanced centered container */
.rafflelb-live-raffles-grid,
.rafflelb-live-grid,
.rlb-live-raffles-grid{
  width:min(1180px,calc(100% - 40px))!important;
  margin-left:auto!important;
  margin-right:auto!important;
  display:grid!important;
  grid-template-columns:repeat(auto-fit,minmax(250px,280px))!important;
  justify-content:center!important;
  gap:22px!important;
}

/* Keep cards compact but not cramped */
.rafflelb-live-raffles-grid > *,
.rafflelb-live-grid > *,
.rlb-live-raffles-grid > *{
  width:100%!important;
  max-width:280px!important;
  min-width:0!important;
}

/* Slightly reduce visual height of card imagery */
.rafflelb-live-raffles-grid img,
.rafflelb-live-grid img,
.rlb-live-raffles-grid img{
  object-fit:cover!important;
}

/* Tighter title/body rhythm */
.rafflelb-live-raffles-grid h2,
.rafflelb-live-raffles-grid h3,
.rafflelb-live-grid h2,
.rafflelb-live-grid h3,
.rlb-live-raffles-grid h2,
.rlb-live-raffles-grid h3{
  line-height:1.2!important;
  margin-bottom:10px!important;
}

/* Desktop density */
@media(min-width:1200px){
  .rafflelb-live-raffles-grid,
  .rafflelb-live-grid,
  .rlb-live-raffles-grid{
    grid-template-columns:repeat(4,minmax(250px,280px))!important;
  }
}

/* Medium desktop / tablet landscape */
@media(min-width:768px) and (max-width:1199px){
  .rafflelb-live-raffles-grid,
  .rafflelb-live-grid,
  .rlb-live-raffles-grid{
    grid-template-columns:repeat(auto-fit,minmax(235px,265px))!important;
    gap:18px!important;
  }
}

/* Mobile: 2 columns when space allows, 1 when too narrow */
@media(max-width:767px){
  .rafflelb-live-raffles-grid,
  .rafflelb-live-grid,
  .rlb-live-raffles-grid{
    width:min(100% - 24px,680px)!important;
    grid-template-columns:repeat(2,minmax(0,1fr))!important;
    gap:10px!important;
  }

  .rafflelb-live-raffles-grid > *,
  .rafflelb-live-grid > *,
  .rlb-live-raffles-grid > *{
    max-width:none!important;
  }
}

@media(max-width:430px){
  .rafflelb-live-raffles-grid,
  .rafflelb-live-grid,
  .rlb-live-raffles-grid{
    grid-template-columns:1fr!important;
    width:min(100% - 28px,360px)!important;
  }
}



        /* v0.33.30 — compact premium shopping guidance.
           Shorter copy creates breathing room beside the truly centered mode
           selector, while the lime accent makes the purpose of the control clear. */
        body.rafflelb-raffle-archive .rl-shop-mode-copy{
            position:relative!important;
            max-width:235px!important;
            padding:2px 10px 2px 17px!important;
            gap:4px!important;
        }
        body.rafflelb-raffle-archive .rl-shop-mode-copy:before{
            content:""!important;
            position:absolute!important;
            left:0!important;
            top:3px!important;
            bottom:3px!important;
            width:3px!important;
            border-radius:999px!important;
            background:#baff00!important;
            box-shadow:0 0 12px rgba(186,255,0,.22)!important;
        }
        body.rafflelb-raffle-archive .rl-shop-mode-copy strong{
            color:#fff!important;
            font-family:Inter,"Segoe UI Variable","Segoe UI",Roboto,Arial,sans-serif!important;
            font-size:18px!important;
            line-height:1.18!important;
            font-weight:700!important;
            letter-spacing:-.015em!important;
            white-space:nowrap!important;
        }
        body.rafflelb-raffle-archive .rl-shop-mode-copy span{
            color:rgba(240,245,237,.78)!important;
            font-family:Inter,"Segoe UI Variable","Segoe UI",Roboto,Arial,sans-serif!important;
            font-size:15px!important;
            line-height:1.3!important;
            font-weight:500!important;
            letter-spacing:0!important;
            white-space:nowrap!important;
        }
        @media(max-width:1180px){
            body.rafflelb-raffle-archive .rl-shop-mode-copy{
                max-width:none!important;
                width:auto!important;
            }
        }
        @media(max-width:520px){
            body.rafflelb-raffle-archive .rl-shop-mode-copy{
                padding-left:14px!important;
            }
            body.rafflelb-raffle-archive .rl-shop-mode-copy strong{
                font-size:17px!important;
                white-space:normal!important;
            }
            body.rafflelb-raffle-archive .rl-shop-mode-copy span{
                font-size:15px!important;
                white-space:normal!important;
            }
        }

        /* v0.33.31 — product-card typography and Buy Now icon polish.
           Product names/categories are centered and use Inter. The direct-purchase
           CTA uses the same shopping-bag glyph as the homepage Featured Products. */
        body.rafflelb-raffle-archive .rl-raffle-card .product-information{
            text-align:center!important;
            align-items:stretch!important;
        }
        body.rafflelb-raffle-archive .rl-raffle-card .wd-entities-title,
        body.rafflelb-raffle-archive .rl-raffle-card .product-title,
        body.rafflelb-raffle-archive .rl-raffle-card h3{
            width:100%!important;
            text-align:center!important;
            font-family:Inter,"Segoe UI",Arial,sans-serif!important;
            font-weight:700!important;
            letter-spacing:-.02em!important;
        }
        body.rafflelb-raffle-archive .rl-raffle-card .wd-entities-title a,
        body.rafflelb-raffle-archive .rl-raffle-card .product-title a,
        body.rafflelb-raffle-archive .rl-raffle-card h3 a{
            display:block!important;
            width:100%!important;
            text-align:center!important;
            font-family:Inter,"Segoe UI",Arial,sans-serif!important;
        }
        body.rafflelb-raffle-archive .rl-raffle-card .wd-product-cats,
        body.rafflelb-raffle-archive .rl-raffle-card .product-categories{
            width:100%!important;
            text-align:center!important;
            font-family:Inter,"Segoe UI",Arial,sans-serif!important;
            font-size:12px!important;
            line-height:1.4!important;
            font-weight:600!important;
            letter-spacing:.045em!important;
        }
        body.rafflelb-raffle-archive .rl-raffle-card .wd-product-cats a,
        body.rafflelb-raffle-archive .rl-raffle-card .product-categories a{
            font-family:Inter,"Segoe UI",Arial,sans-serif!important;
        }
        body.rafflelb-raffle-archive .rl-shop-buy{
            display:flex!important;
            align-items:center!important;
            justify-content:space-between!important;
            gap:12px!important;
        }
        body.rafflelb-raffle-archive .rl-shop-buy .rl-shop-buy-label{
            display:inline-flex!important;
            align-items:center!important;
            font:inherit!important;
            color:inherit!important;
        }
        body.rafflelb-raffle-archive .rl-shop-buy .rl-shop-buy-bag{
            display:inline-flex!important;
            align-items:center!important;
            justify-content:center!important;
            flex:0 0 auto!important;
            width:22px!important;
            height:22px!important;
            color:#050705!important;
        }
        body.rafflelb-raffle-archive .rl-shop-buy .rl-shop-buy-bag svg{
            display:block!important;
            width:21px!important;
            height:21px!important;
            fill:none!important;
            stroke:currentColor!important;
            stroke-width:1.8!important;
            stroke-linecap:round!important;
            stroke-linejoin:round!important;
        }
        @media(max-width:767px){
            body.rafflelb-raffle-archive .rl-raffle-card .wd-product-cats,
            body.rafflelb-raffle-archive .rl-raffle-card .product-categories{
                font-size:11px!important;
            }
            body.rafflelb-raffle-archive .rl-shop-buy .rl-shop-buy-bag{
                width:20px!important;
                height:20px!important;
            }
            body.rafflelb-raffle-archive .rl-shop-buy .rl-shop-buy-bag svg{
                width:19px!important;
                height:19px!important;
            }
        }

        /* v0.33.32 — direct-purchase CTA centering + stronger shopping guidance.
           BUY NOW and its bag icon are treated as one centered unit. The guidance
           area uses more of the left toolbar zone without disturbing the true-center
           Store/Raffle selector. */
        body.rafflelb-raffle-archive .rl-shop-buy{
            justify-content:center!important;
            gap:9px!important;
            text-align:center!important;
        }
        body.rafflelb-raffle-archive .rl-shop-buy .rl-shop-buy-label,
        body.rafflelb-raffle-archive .rl-shop-buy .rl-shop-buy-bag{
            position:static!important;
            margin:0!important;
            transform:none!important;
        }
        body.rafflelb-raffle-archive .rl-shop-mode-copy{
            position:relative!important;
            width:min(100%,420px)!important;
            max-width:420px!important;
            min-width:0!important;
            min-height:58px!important;
            padding:9px 18px 9px 20px!important;
            justify-content:center!important;
            gap:3px!important;
            border:1px solid rgba(255,255,255,.075)!important;
            border-radius:11px!important;
            background:linear-gradient(90deg,rgba(186,255,0,.055),rgba(255,255,255,.018) 72%,transparent)!important;
            box-sizing:border-box!important;
        }
        body.rafflelb-raffle-archive .rl-shop-mode-copy:before{
            left:8px!important;
            top:10px!important;
            bottom:10px!important;
            width:3px!important;
            border-radius:999px!important;
            background:#baff00!important;
            box-shadow:none!important;
        }
        body.rafflelb-raffle-archive .rl-shop-mode-copy strong{
            font-family:Inter,"Segoe UI Variable","Segoe UI",Arial,sans-serif!important;
            font-size:18px!important;
            line-height:1.2!important;
            font-weight:700!important;
            letter-spacing:-.012em!important;
            color:#fff!important;
        }
        body.rafflelb-raffle-archive .rl-shop-mode-copy span{
            font-family:Inter,"Segoe UI Variable","Segoe UI",Arial,sans-serif!important;
            font-size:15px!important;
            line-height:1.28!important;
            font-weight:500!important;
            letter-spacing:0!important;
            color:rgba(244,247,242,.82)!important;
        }
        @media(min-width:1450px){
            body.rafflelb-raffle-archive .rl-shop-mode-copy{
                width:min(100%,420px)!important;
                max-width:420px!important;
            }
        }
        @media(min-width:1181px) and (max-width:1449px){
            body.rafflelb-raffle-archive .rl-shop-mode-copy{
                width:min(100%,390px)!important;
                max-width:390px!important;
            }
        }
        @media(max-width:1180px){
            body.rafflelb-raffle-archive .rl-shop-mode-copy{
                width:100%!important;
                max-width:none!important;
            }
        }
        @media(max-width:767px){
            body.rafflelb-raffle-archive .rl-shop-mode-copy{
                min-height:56px!important;
                padding:9px 14px 9px 18px!important;
            }
            body.rafflelb-raffle-archive .rl-shop-mode-copy strong{font-size:17px!important}
            body.rafflelb-raffle-archive .rl-shop-mode-copy span{font-size:14.5px!important}
            body.rafflelb-raffle-archive .rl-shop-buy{gap:7px!important}
        }
</style>
<?php }});

/* =========================================================
 * v0.32.4 — Premium authenticated My Account presentation.
 * Presentation only: no entry, draw, reservation, payment,
 * order-processing, authentication, or database-write logic.
 * ========================================================= */

if (!function_exists('rafflelb_premium_account_product_image')) {
    function rafflelb_premium_account_product_image($product, $class = 'rlpa-product-image') {
        // Account presentation now lives exclusively in the RaffleLB Account plugin.
        // Keep this callback/function as a compatibility bridge so existing hook
        // identities, priorities, and third-party callers remain stable.
        if (class_exists('RaffleLB_Account', false) && RaffleLB_Account::ready()) {
            return RaffleLB_Account::rafflelb_premium_account_product_image(...func_get_args());
        }

        return '';
    }
}

if (!function_exists('rafflelb_premium_account_dashboard')) {
    function rafflelb_premium_account_dashboard() {
        // Account presentation now lives exclusively in the RaffleLB Account plugin.
        // Keep this callback/function as a compatibility bridge so existing hook
        // identities, priorities, and third-party callers remain stable.
        if (class_exists('RaffleLB_Account', false) && RaffleLB_Account::ready()) {
            return RaffleLB_Account::rafflelb_premium_account_dashboard(...func_get_args());
        }

        return;
    }
    add_action('woocommerce_account_dashboard', 'rafflelb_premium_account_dashboard', 20);
}

/* =========================================================
 * v0.33.87 — My Orders as a single premium card list.
 *
 * Previously, account-premium.css only re-styled WooCommerce's
 * default orders <table> for mobile (grid-stacking each <td>).
 * That table also carries WooCommerce/theme's own default
 * responsive card CSS (data-title labels shown via ::before).
 * Both rulesets applied to the same table at once, so small
 * screens showed the table content twice - once styled by this
 * plugin, once by the untouched default - and a table cell's
 * width was narrow enough that long product names wrapped one
 * word per line. Replacing the endpoint output outright (same
 * technique already used for My Raffles) removes the table
 * entirely so there's nothing left for the default styling to
 * double up on, and the title sits in its own full-width block
 * with no fixed/narrow column forcing it to wrap word-by-word.
 * Presentation only: no order, payment, or draw logic changed.
 *
 * v0.33.88 — even with that rewrite, something outside this
 * plugin (the active theme's own account/orders template, not
 * tracked here) still renders WooCommerce's classic orders table
 * a second time beneath our card list; account-premium.css now
 * hides table.woocommerce-orders-table outright on this tab,
 * since our own markup never produces one. Also fixed the single
 * "View" button rendering with white-on-lime unreadable text
 * (needed -webkit-text-fill-color alongside color, matching the
 * pattern already used elsewhere in this file).
 * ========================================================= */
if (!function_exists('rafflelb_account_orders_premium')) {
    function rafflelb_account_orders_premium() {
        // Account presentation now lives exclusively in the RaffleLB Account plugin.
        // Keep this callback/function as a compatibility bridge so existing hook
        // identities, priorities, and third-party callers remain stable.
        if (class_exists('RaffleLB_Account', false) && RaffleLB_Account::ready()) {
            return RaffleLB_Account::rafflelb_account_orders_premium(...func_get_args());
        }

        return;
    }
    remove_action('woocommerce_account_orders_endpoint', 'woocommerce_account_orders');
    add_action('woocommerce_account_orders_endpoint', 'rafflelb_account_orders_premium');
}

/* =========================================================
 * v0.33.89 — Order details as a premium receipt, not a bare
 * WooCommerce table + address columns.
 *
 * Same technique as rafflelb_account_orders_premium() above:
 * fully replaces the View Order endpoint's default output, this
 * time with a hero-style item list (product thumbnail, name,
 * purchase type, quantity, line total), a totals card built from
 * WooCommerce's own get_order_item_totals() (so tax/shipping/
 * discount/refund math is exactly what WooCommerce itself would
 * show - only the presentation changes), and the customer/billing
 * columns reused as-is from WooCommerce's own
 * order/order-details-customer.php template (so payment gateway
 * and downloadable-product integrations keep working), restyled
 * to match and retitled "Entry Contact Details" via a scoped
 * gettext filter instead of the DOM-patching this replaces.
 * Presentation only: no order, payment, or draw logic changed.
 * ========================================================= */
if (!function_exists('rafflelb_account_view_order_premium')) {
    function rafflelb_account_view_order_premium($order_id) {
        // Account presentation now lives exclusively in the RaffleLB Account plugin.
        // Keep this callback/function as a compatibility bridge so existing hook
        // identities, priorities, and third-party callers remain stable.
        if (class_exists('RaffleLB_Account', false) && RaffleLB_Account::ready()) {
            return RaffleLB_Account::rafflelb_account_view_order_premium(...func_get_args());
        }

        return;
    }

    if (!function_exists('rafflelb_rename_billing_details_label')) {
        function rafflelb_rename_billing_details_label($translated, $original, $domain) {
        // Account presentation now lives exclusively in the RaffleLB Account plugin.
        // Keep this callback/function as a compatibility bridge so existing hook
        // identities, priorities, and third-party callers remain stable.
        if (class_exists('RaffleLB_Account', false) && RaffleLB_Account::ready()) {
            return RaffleLB_Account::rafflelb_rename_billing_details_label(...func_get_args());
        }

        return $translated;
    }
    }

    remove_action('woocommerce_account_view-order_endpoint', 'woocommerce_account_view_order');
    add_action('woocommerce_account_view-order_endpoint', 'rafflelb_account_view_order_premium');
}

/* WooCommerce's default 'orders' table and 'view-order' items table are
   both fully replaced above, but something outside this plugin (the
   active theme's own account templates, not tracked in this codebase)
   has been observed rendering WooCommerce's raw default table a second
   time regardless. account-premium.css hides those tables outright on
   these two endpoints; this collapses the blank space their wrapper(s)
   would otherwise leave behind, by hiding ancestor elements up the tree
   for as long as the stray table is their only visible content. */
add_action('wp_footer', function() {
        // Account presentation now lives exclusively in the RaffleLB Account plugin.
        // Keep this callback/function as a compatibility bridge so existing hook
        // identities, priorities, and third-party callers remain stable.
        if (class_exists('RaffleLB_Account', false) && RaffleLB_Account::ready()) {
            return RaffleLB_Account::legacy_callback_17961(...func_get_args());
        }

        return;
    }, 20006);

add_action('wp_footer', function() {
        // Account presentation now lives exclusively in the RaffleLB Account plugin.
        // Keep this callback/function as a compatibility bridge so existing hook
        // identities, priorities, and third-party callers remain stable.
        if (class_exists('RaffleLB_Account', false) && RaffleLB_Account::ready()) {
            return RaffleLB_Account::legacy_callback_18014(...func_get_args());
        }

        return;
    }, 20000);

/* Real menu icon and label elements avoid WoodMart's pseudo-icon direction rules. */
add_action('wp_footer', function() {
        // Account presentation now lives exclusively in the RaffleLB Account plugin.
        // Keep this callback/function as a compatibility bridge so existing hook
        // identities, priorities, and third-party callers remain stable.
        if (class_exists('RaffleLB_Account', false) && RaffleLB_Account::ready()) {
            return RaffleLB_Account::legacy_callback_18021(...func_get_args());
        }

        return;
    }, 20001);

add_action('wp_footer', function() {
        // Account presentation now lives exclusively in the RaffleLB Account plugin.
        // Keep this callback/function as a compatibility bridge so existing hook
        // identities, priorities, and third-party callers remain stable.
        if (class_exists('RaffleLB_Account', false) && RaffleLB_Account::ready()) {
            return RaffleLB_Account::legacy_callback_18045(...func_get_args());
        }

        return;
    }, 20002);

/* Progressive enhancement: native WooCommerce forms and hooks remain intact. */
add_action('wp_footer', function() {
        // Account presentation now lives exclusively in the RaffleLB Account plugin.
        // Keep this callback/function as a compatibility bridge so existing hook
        // identities, priorities, and third-party callers remain stable.
        if (class_exists('RaffleLB_Account', false) && RaffleLB_Account::ready()) {
            return RaffleLB_Account::legacy_callback_18052(...func_get_args());
        }

        return;
    }, 20003);

add_filter('body_class', function($classes) {
        // Account presentation now lives exclusively in the RaffleLB Account plugin.
        // Keep this callback/function as a compatibility bridge so existing hook
        // identities, priorities, and third-party callers remain stable.
        if (class_exists('RaffleLB_Account', false) && RaffleLB_Account::ready()) {
            return RaffleLB_Account::legacy_callback_18058(...func_get_args());
        }

        return $classes;
    }, 100);

/**
 * The WoodMart header's "My Account" dropdown (hover/click on the account
 * icon in the header, on every page) is a different element from the full
 * My Account page nav above - it isn't covered by account_styles() or
 * account-premium.css, both of which only load on is_account_page(). It
 * was rendering with the theme's default white background / black text
 * instead of matching the rest of the site. Scoped under the already-
 * proven .wd-header-my-account wrapper (used by the header points badge)
 * so this can't leak into unrelated dropdowns (cart, wishlist, etc.).
 */
add_action('wp_head', function() {
        // Account presentation now lives exclusively in the RaffleLB Account plugin.
        // Keep this callback/function as a compatibility bridge so existing hook
        // identities, priorities, and third-party callers remain stable.
        if (class_exists('RaffleLB_Account', false) && RaffleLB_Account::ready()) {
            return RaffleLB_Account::legacy_callback_18091(...func_get_args());
        }

        return;
    }, 25);


/* v0.33.70 compatibility hook — presentation owned by RaffleLB Shop. */
add_action('wp_head', function() {
    if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
        return RaffleLB_Shop::legacy_callback_18334(...func_get_args());
    }
    return;
}, 99);


/* v0.33.71 compatibility hook — presentation owned by RaffleLB Shop. */
add_action('wp_head', function() {
    if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
        return RaffleLB_Shop::legacy_callback_18430(...func_get_args());
    }
    return;
}, 120);


/* v0.33.72 compatibility hook — presentation owned by RaffleLB Shop. */
add_action('wp_head', function() {
    if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
        return RaffleLB_Shop::legacy_callback_18600(...func_get_args());
    }
    return;
}, 130);


/* Compatibility hook: Homepage owns the artwork output. */
add_action('wp_footer', function() {
    if (class_exists('RaffleLB_Homepage', false) && RaffleLB_Homepage::ready()) {
        RaffleLB_Homepage::hero_artwork();
    }
}, 20005);

/* Header reference styling; native WoodMart controls retained. */
/* v0.34.18.9: this styling must be in wp_head, not wp_footer.
   Printing it before first paint prevents the previous WoodMart header from
   flashing while the RaffleLB header waits for footer JavaScript/CSS. */
add_action('wp_head', function () { ?>
<style id='rafflelb-header-reference'>
/* Preserve WoodMart's native account, cart, sticky and mobile-menu behavior. */
body .whb-header.whb-header_231291 .whb-main-header,
body .whb-header.whb-header_231291 .whb-row,
body .whb-header.whb-header_231291 .whb-row>.container{background:#080e08!important;color:#f5f7f0!important}
body .whb-header.whb-header_231291 .whb-general-header{border-bottom:2px solid #baff00!important;box-shadow:0 4px 22px #baff0015}
body .whb-header.whb-header_231291 .whb-row>.container{width:100%;max-width:1800px;padding-inline:28px}
body .whb-header.whb-header_231291 .wd-tools-element>a,
body .whb-header.whb-header_231291 .wd-tools-icon,
body .whb-header.whb-header_231291 .wd-tools-text{color:#f5f7f0!important}
body .whb-header.whb-header_231291 .wd-nav-header>li>a{color:#f5f7f0!important;font-family:Inter,"Segoe UI",Arial,sans-serif!important;font-size:12px!important;font-weight:750!important;letter-spacing:.04em}
body .whb-header.whb-header_231291 .wd-nav-header>li:is(.current-menu-item,:hover)>a{color:#baff00!important}
body .whb-header.whb-header_231291 .wd-nav-header>li.current-menu-item>a .nav-link-text{box-shadow:0 2px #baff00;padding-block:8px}
body .whb-header.whb-header_231291 .rafflelb-header-points{min-height:44px;padding:8px 15px;border:1px solid #658322;border-radius:28px;background:#0c1408;color:white!important}
body .whb-header.whb-header_231291 .wd-tools-count{background:#baff00!important;color:#0a1003!important}
body .whb-header.whb-header_231291 a:focus-visible{outline:2px solid #baff00;outline-offset:4px}
/* Original black-letter logo stays readable until a white-letter asset is supplied. */
body .whb-header.whb-header_231291 .site-logo{background:#f6f8f2;border-radius:10px;padding:0 8px;line-height:0}
body .whb-header.whb-header_231291 .wd-logo img{max-width:240px!important;max-height:72px!important;width:auto!important;object-fit:contain}
.rl-header-more{position:relative}.rl-header-more>summary{cursor:pointer;color:#f5f7f0;font:700 11px Inter,Arial,sans-serif;list-style:none;padding:14px 10px}.rl-header-more>summary:after{content:' ▾';color:#baff00}.rl-header-more>ul{position:absolute;top:100%;right:0;min-width:185px;margin:0;padding:10px;list-style:none;background:#10190e;border:1px solid #526530;border-radius:10px;box-shadow:0 10px 25px #0008}.rl-header-more>ul a{display:block;padding:12px;color:#f5f7f0;font:600 12px Arial,sans-serif;text-decoration:none}.rl-header-more>ul a:hover{color:#baff00}
@media(min-width:1025px){body .whb-header.whb-header_231291 .whb-general-header-inner{gap:22px}body .whb-header.whb-header_231291 .whb-col-center{order:0;flex:0 0 auto!important}body .whb-header.whb-header_231291 .whb-col-left{order:1;flex:1 1 auto!important;justify-content:center;margin:0}body .whb-header.whb-header_231291 .whb-col-right{order:2;flex:0 0 auto!important;gap:10px;margin:0}body .whb-header.whb-header_231291 .wd-header-main-nav{flex:0 1 auto;padding:0}body .whb-header.whb-header_231291 .wd-nav-header{gap:0;align-items:center;padding:3px 14px;border:1px solid #577718;border-radius:40px;background:linear-gradient(#111c09,#070d07);box-shadow:inset 0 1px 0 #baff0020}body .whb-header.whb-header_231291 .wd-nav-header>li>a{padding:14px 15px;min-height:48px}body .whb-header.whb-header_231291 .whb-col-right>.wd-header-my-account{border-left:1px solid #34452a;padding-left:14px}body .whb-header.whb-header_231291 .whb-col-right>.wd-header-my-account>a:after{content:'My Account';font:600 12px Arial,sans-serif;padding-left:9px}body .whb-header.whb-header_231291 .wd-header-cart{border-left:1px solid #34452a;padding-left:12px}}
@media(min-width:1025px) and (max-width:1250px){body .whb-header.whb-header_231291 .whb-general-header-inner{gap:12px}body .whb-header.whb-header_231291 .wd-logo img{max-width:190px!important}body .whb-header.whb-header_231291 .wd-nav-header>li>a{padding-inline:9px;font-size:10px!important}body .whb-header.whb-header_231291 .whb-col-right>.wd-header-my-account>a:after{display:none}}
@media(max-width:1024px){body .whb-header.whb-header_231291 .whb-row>.container{padding-inline:12px}body .whb-header.whb-header_231291 .whb-general-header-inner{height:76px!important;max-height:none;gap:8px}body .whb-header.whb-header_231291 .whb-mobile-left{flex:0 0 36px;margin:0}body .whb-header.whb-header_231291 .whb-mobile-center{flex:1;min-width:0;justify-content:flex-start}body .whb-header.whb-header_231291 .whb-mobile-right{flex:0 0 auto;gap:0;margin:0}body .whb-header.whb-header_231291 .wd-logo img{max-width:155px!important;max-height:52px!important}body.logged-in .whb-header.whb-header_231291 .wd-logo img{max-width:115px!important}body .whb-header.whb-header_231291 .wd-tools-element>a{min-width:34px;min-height:44px;padding-inline:5px}body .whb-header.whb-header_231291 .rafflelb-header-points{padding:6px;min-height:36px;font-size:11px}body .whb-header.whb-header_231291 .rafflelb-header-points-icon{width:18px;height:18px}body .whb-header.whb-header_231291 .site-logo{padding-inline:4px;border-radius:6px}}
@media(max-width:360px){body .whb-header.whb-header_231291 .wd-logo img{max-width:125px!important}body.logged-in .whb-header.whb-header_231291 .wd-logo img{max-width:88px!important}}


body .whb-header.whb-header_231291,body .whb-header.whb-header_231291 *{box-sizing:border-box}body .whb-header.whb-header_231291 .wd-nav-header{display:flex;flex-wrap:nowrap;list-style:none;margin:0}body .whb-header.whb-header_231291 .wd-nav-header>li>a{display:flex;align-items:center;text-decoration:none}body .whb-header.whb-header_231291 .whb-general-header-inner{width:100%}

/* One edge only: the divider belongs to the outer header boundary. */
body .whb-header.whb-header_231291 .whb-general-header,body .whb-header.whb-header_231291.whb-sticked .whb-row:last-child{border-bottom:0!important;box-shadow:none!important}
@media(min-width:1025px){body .whb-header.whb-header_231291 .whb-general-header-inner{height:78px!important;gap:20px}body .whb-header.whb-header_231291 .wd-logo img{max-width:210px!important;max-height:60px!important}body .whb-header.whb-header_231291 .wd-nav-header>li>a{min-height:40px;padding-block:10px;font-size:11px!important}body .whb-header.whb-header_231291 .wd-nav-header{padding-block:2px}body .whb-header.whb-header_231291 .rafflelb-header-points{min-height:40px}}
@media(min-width:1025px){body .whb-header.whb-header_231291 .whb-general-header-inner{align-items:center!important}body .whb-header.whb-header_231291 .whb-general-header-inner>.whb-column{align-self:center!important}}

/* Readable header type and reference control surfaces. */
body .whb-header.whb-header_231291 .wd-nav-header>li>a,body .whb-header.whb-header_231291 .wd-nav-header>li>a .nav-link-text{font-family:Inter,"Segoe UI",Arial,sans-serif!important;font-size:13px!important;font-weight:650!important;letter-spacing:.035em!important;line-height:1.35!important;text-shadow:none!important;-webkit-font-smoothing:antialiased}
body .whb-header.whb-header_231291 .wd-nav-header{padding:3px 16px;border-color:#638714;background:radial-gradient(ellipse at 50% 0,#253b0b66,transparent 75%),#080e07}
body .whb-header.whb-header_231291 .wd-nav-header>li>a{padding-inline:20px;min-height:44px}
body .whb-header.whb-header_231291 .wd-nav-header>li.current-menu-item>a{border-radius:28px;background:radial-gradient(ellipse at center,#baff000e,transparent 75%)}
body .whb-header.whb-header_231291 .whb-col-right>.wd-header-my-account{border-left:1px solid #34452a;padding:0 8px 0 14px}
body .whb-header.whb-header_231291 .whb-col-right>.wd-header-my-account>a{border:1px solid #baff0017;border-right-color:transparent;border-radius:28px;padding:11px 15px;background:radial-gradient(ellipse at 15% 50%,#23301988,transparent 80%),#0a1009;box-shadow:inset 1px 1px 0 #ffffff06;min-height:44px}
body .whb-header.whb-header_231291 .whb-col-right>.wd-header-my-account>a:after{font-family:Inter,"Segoe UI",Arial,sans-serif;font-size:13px;font-weight:600;line-height:1.3}
body .whb-header.whb-header_231291 .rafflelb-header-points{border:1px solid #ffffffb3!important;color:#fff!important;background:#0b1209;gap:9px}
body .whb-header.whb-header_231291 .rafflelb-header-points-count{color:#fff!important;-webkit-text-fill-color:#fff!important;font-family:Inter,"Segoe UI",Arial,sans-serif!important;font-size:16px!important;font-weight:650!important;line-height:1.2!important}
body .whb-header.whb-header_231291 .site-logo{background:transparent!important;border-radius:0;padding:0}
body .whb-header.whb-header_231291 .wd-logo img{max-width:240px!important;max-height:72px!important;padding:0!important}
@media(min-width:1025px) and (max-width:1250px){body .whb-header.whb-header_231291 .wd-logo img{max-width:190px!important}body .whb-header.whb-header_231291 .wd-nav-header>li>a{padding-inline:11px}body .whb-header.whb-header_231291 .wd-nav-header>li>a,body .whb-header.whb-header_231291 .wd-nav-header>li>a .nav-link-text{font-size:12px!important}}
@media(max-width:1024px){body .whb-header.whb-header_231291 .wd-logo img{max-width:155px!important}body.logged-in .whb-header.whb-header_231291 .wd-logo img{max-width:115px!important}body .whb-header.whb-header_231291 .rafflelb-header-points-count{font-size:12px!important}}
@media(max-width:360px){body .whb-header.whb-header_231291 .wd-logo img{max-width:125px!important}body.logged-in .whb-header.whb-header_231291 .wd-logo img{max-width:88px!important}}

/* Final reference corrections. */
body .whb-header.whb-header_231291 .rl-header-more{display:none!important}
body .whb-header.whb-header_231291 .rafflelb-header-points{background:#0b1209!important;background-image:none!important;border:1px solid #fff!important;color:#fff!important;box-shadow:none!important}
body .whb-header.whb-header_231291 .rafflelb-header-points-count{color:#fff!important;-webkit-text-fill-color:#fff!important;background:transparent!important}
body .whb-header.whb-header_231291{position:relative!important;background:#080e08!important;margin-bottom:0!important}
@media(min-width:1025px){body .whb-header.whb-header_231291.whb-sticky-prepared:not(.whb-sticked){height:78px!important;padding-top:78px!important}}
@media(max-width:1024px){body .whb-header.whb-header_231291.whb-sticky-prepared:not(.whb-sticked){height:76px!important;padding-top:76px!important}}
body .whb-header.whb-header_231291::after{content:""!important;display:block!important;position:absolute!important;z-index:2!important;pointer-events:none!important;left:0!important;right:0!important;bottom:-2px!important;height:2px!important;background:#c6ff00!important;box-shadow:0 1px 6px #c6ff0033!important}
body .whb-header.whb-header_231291.whb-sticked::after{content:none!important;display:none!important}body .whb-header.whb-header_231291.whb-sticked .whb-row:last-child{position:relative!important;border-bottom:0!important;box-shadow:none!important}body .whb-header.whb-header_231291.whb-sticked .whb-row:last-child::after{content:""!important;display:block!important;position:absolute!important;z-index:2!important;pointer-events:none!important;left:0!important;right:0!important;bottom:0!important;height:2px!important;background:#c6ff00!important;box-shadow:0 1px 6px #c6ff0033!important}
@media(min-width:1025px){body .whb-header.whb-header_231291.whb-sticked .whb-row.whb-sticky-row,body .whb-header.whb-header_231291.whb-sticked .whb-row.whb-sticky-row .whb-general-header-inner{height:82px!important}}
body .whb-header.whb-header_231291 .wd-logo{position:relative;display:block}
@media(min-width:1025px){body .whb-header.whb-header_231291 .whb-row>.container{max-width:1600px;padding-inline:24px}body .whb-header.whb-header_231291 .whb-general-header-inner{gap:28px;justify-content:space-between}body .whb-header.whb-header_231291 .whb-col-left{flex:0 1 auto!important}body .whb-header.whb-header_231291 .wd-logo img{max-width:290px!important;max-height:76px!important}body .whb-header.whb-header_231291 .wd-nav-header>li:has(>.rl-header-more){display:none!important}body .whb-header.whb-header_231291 .wd-nav-header>li>a{padding-inline:24px}}
@media(min-width:1025px) and (max-width:1250px){body .whb-header.whb-header_231291 .wd-logo img{max-width:225px!important}body .whb-header.whb-header_231291 .whb-general-header-inner{gap:14px}body .whb-header.whb-header_231291 .wd-nav-header>li>a{padding-inline:14px}}

body .whb-header.whb-header_231291 .rafflelb-header-points{display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:9px!important;min-height:44px!important;padding:8px 15px!important;border:1px solid #ffffffa6!important;border-radius:999px!important;outline:none!important;box-shadow:none!important;background:#0b1209!important;line-height:1!important}
body .whb-header.whb-header_231291 .rafflelb-header-points::before,body .whb-header.whb-header_231291 .rafflelb-header-points::after{content:none!important}
body .whb-header.whb-header_231291 .wd-header-cart .wd-tools-icon::before{content:none!important;display:none!important}
body .whb-header.whb-header_231291 .rl-cart-bag{display:block;width:25px;height:28px;fill:none;stroke:#fff;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round}
body .whb-header.whb-header_231291 .wd-header-cart .wd-tools-icon{position:relative;display:inline-flex;align-items:center;justify-content:center;width:30px;height:32px}
body .whb-header.whb-header_231291 .wd-header-cart .wd-tools-count{position:absolute;top:-5px;right:-6px;min-width:17px;height:17px;font:600 10px/17px Arial,sans-serif}
@media(max-width:1024px){body .whb-header.whb-header_231291 .rafflelb-header-points{min-height:36px!important;padding:6px 9px!important;gap:5px!important}.rl-cart-bag{width:23px!important;height:26px!important}}

/* Wordmark boundary measured as a percentage of the supplied image. */
body .whb-header.whb-header_231291 .wd-nav-header>li>a,body .whb-header.whb-header_231291 .wd-nav-header>li>a .nav-link-text{font-family:Arial,Helvetica,sans-serif!important;font-size:13px!important;font-weight:700!important;letter-spacing:.025em!important;line-height:1.4!important;text-shadow:none!important;filter:none!important;opacity:1!important}
body .whb-header.whb-header_231291 .whb-col-right>.wd-header-my-account>a{border:1px solid #6b785c!important;border-radius:24px!important;background:linear-gradient(110deg,#182013,#0b1209)!important;box-shadow:none!important;padding:10px 15px!important;gap:8px}
body .whb-header.whb-header_231291 .whb-col-right>.wd-header-my-account>a:after{font:600 13px/1.4 Arial,Helvetica,sans-serif!important;color:#fff!important;text-shadow:none!important;padding-left:0}
body .whb-header.whb-header_231291 .wd-header-my-account .wd-tools-icon::before{display:none!important;content:none!important}
body .whb-header.whb-header_231291 .rl-account-icon{display:block;width:25px;height:25px;fill:none;stroke:#fff;stroke-width:1.65;stroke-linecap:round;stroke-linejoin:round}
body .whb-header.whb-header_231291 .rafflelb-header-points-count{font-family:Arial,Helvetica,sans-serif!important;font-weight:700!important;text-shadow:none!important;filter:none!important}
@media(min-width:1025px) and (max-width:1250px){body .whb-header.whb-header_231291 .wd-nav-header>li>a,body .whb-header.whb-header_231291 .wd-nav-header>li>a .nav-link-text{font-size:12px!important}}

/* v0.34.16: responsive mobile logo, including logged-in customers. */

/* Mobile header only: replace the 115px logged-in logo cap. */
@media (max-width:1024px) {
  body .whb-header.whb-header_231291 .whb-mobile-center {
    min-width:0 !important;
  }
  body .whb-header.whb-header_231291 .whb-mobile-center .wd-logo {
    width:170px !important;
    max-width:100% !important;
    min-width:0 !important;
    flex:0 1 170px !important;
  }
  body .whb-header.whb-header_231291 .whb-mobile-center .wd-logo img {
    width:100% !important;
    max-width:100% !important;
    height:auto !important;
    max-height:60px !important;
    object-fit:contain !important;
  }
  body:not(.logged-in) .whb-header.whb-header_231291 .whb-mobile-center .wd-logo {
    width:204px !important;
    flex:0 1 204px !important;
  }
  body:not(.logged-in) .whb-header.whb-header_231291 .whb-mobile-center .wd-logo img {
    max-height:68px !important;
  }
}



/* v0.34.18.9 — first-paint header guard.
   The header used to be styled from wp_footer, so WoodMart's default header
   could paint first. Keep the final RaffleLB header visible from the first paint. */
body .whb-header.whb-header_231291 .wd-logo{
  background-image:url("<?php echo esc_url(plugins_url('assets/header-logo-official.webp', __FILE__)); ?>")!important;
  background-repeat:no-repeat!important;
  background-position:center!important;
  background-size:contain!important;
}
body .whb-header.whb-header_231291 .wd-logo img{opacity:0!important}
body .whb-header.whb-header_231291 .wd-logo.rl-header-logo-ready{background-image:none!important}
body .whb-header.whb-header_231291 .wd-logo.rl-header-logo-ready img{opacity:1!important}
</style>
<script id="rafflelb-header-first-paint" data-nowprocket data-no-optimize="1" data-cfasync="false">
(function(){
  'use strict';
  var logoUrl=<?php echo wp_json_encode(plugins_url('assets/header-logo-official.webp', __FILE__)); ?>;

  function fixLogo(root){
    (root || document).querySelectorAll('.whb-header_231291 .wd-logo img').forEach(function(img){
      var holder=img.closest('.wd-logo');
      function ready(){ if(holder) holder.classList.add('rl-header-logo-ready'); }
      img.removeAttribute('srcset');
      img.removeAttribute('sizes');
      img.alt='RaffleLB — Shop, Win, Be Rewarded';
      if(img.src !== logoUrl){
        img.addEventListener('load', ready, {once:true});
        img.src=logoUrl;
      } else if(img.complete && img.naturalWidth){
        ready();
      } else {
        img.addEventListener('load', ready, {once:true});
      }
    });
  }

  function fixIcons(root){
    (root || document).querySelectorAll('.whb-header_231291 .wd-header-cart .wd-tools-icon').forEach(function(e){
      if(!e.querySelector('.rl-cart-bag')) e.insertAdjacentHTML('afterbegin','<svg class="rl-cart-bag" viewBox="0 0 28 32" aria-hidden="true"><path d="M5 10h18l1 18H4l1-18ZM9 12V8a5 5 0 0 1 10 0v4"/></svg>');
    });
    (root || document).querySelectorAll('.whb-header_231291 .wd-header-my-account .wd-tools-icon').forEach(function(e){
      if(!e.querySelector('.rl-account-icon')) e.insertAdjacentHTML('afterbegin','<svg class="rl-account-icon" viewBox="0 0 28 28" aria-hidden="true"><rect x="2.5" y="2.5" width="23" height="23" rx="8"/><circle cx="14" cy="10.5" r="3.5"/><path d="M7.5 21a6.5 6.5 0 0 1 13 0"/></svg>');
    });
  }

  function fixMenu(root){
    (root || document).querySelectorAll('.whb-header_231291 .whb-col-left .wd-nav-header').forEach(function(menu){
      var extra=[];
      Array.from(menu.children).forEach(function(li){
        if(li.querySelector(':scope > .rl-header-more')) return;
        var a=li.querySelector('a'), text=a?a.textContent.trim():'';
        if(text==='RAFFELS'){ var label=a.querySelector('.nav-link-text'); if(label) label.textContent='RAFFLES'; text='RAFFLES'; }
        if(/^(MY RAFFLES|REFER & EARN|CONTACT US)$/.test(text)) extra.push(li);
      });
      if(extra.length){
        var holder=menu.querySelector(':scope > li.rl-header-more-holder');
        var details, list;
        if(!holder){
          holder=document.createElement('li'); holder.className='rl-header-more-holder';
          details=document.createElement('details'); details.className='rl-header-more';
          var summary=document.createElement('summary'); summary.textContent='MORE';
          list=document.createElement('ul'); details.append(summary,list); holder.appendChild(details); menu.appendChild(holder);
          details.addEventListener('keydown',function(e){if(e.key==='Escape'){details.open=false;summary.focus()}});
          document.addEventListener('click',function(e){if(!details.contains(e.target))details.open=false});
        } else { details=holder.querySelector('.rl-header-more'); list=details && details.querySelector('ul'); }
        if(list) extra.forEach(function(li){list.appendChild(li)});
      }
    });
  }

  function enhance(root){ fixLogo(root); fixIcons(root); fixMenu(root); }

  /* Install before body/header markup is parsed. MutationObserver runs as the
     WoodMart header enters the DOM, preventing the old header from painting. */
  var observer=new MutationObserver(function(mutations){
    mutations.forEach(function(m){
      m.addedNodes.forEach(function(node){
        if(node.nodeType!==1) return;
        if(node.matches && (node.matches('.whb-header_231291') || node.querySelector('.whb-header_231291'))) enhance(node.matches('.whb-header_231291') ? node : node);
        else if(node.closest && node.closest('.whb-header_231291')) enhance(node.closest('.whb-header_231291'));
      });
    });
  });
  observer.observe(document.documentElement,{childList:true,subtree:true});
  if(document.readyState==='loading'){
    document.addEventListener('DOMContentLoaded',function(){enhance(document); setTimeout(function(){observer.disconnect();},0);},{once:true});
  } else { enhance(document); observer.disconnect(); }
})();
</script>
<?php }, 1);
/* Store reference compatibility hooks; presentation owned by RaffleLB Shop. */
add_filter('body_class', function ($classes) {
    if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
        return RaffleLB_Shop::legacy_callback_18802(...func_get_args());
    }
    return $classes;
});
add_action('wp_enqueue_scripts', function () {
    if (class_exists('RaffleLB_Shop', false) && RaffleLB_Shop::ready()) {
        return RaffleLB_Shop::legacy_callback_18806(...func_get_args());
    }
    return;
}, 1000);
