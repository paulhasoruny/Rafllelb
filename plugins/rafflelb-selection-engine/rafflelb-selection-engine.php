<?php
/**
 * Plugin Name: RaffleLB Selection Engine
 * Description: Public transparency pages for RaffleLB raffle status, native entry access, and recorded results.
 * Version: 0.2.19
 * Author: RaffleLB
 * Text Domain: rafflelb-selection-engine
 * Requires Plugins: woocommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

define('RAFFLELB_SELECTION_ENGINE_VERSION', '0.2.19');
define('RAFFLELB_SELECTION_ENGINE_FILE', __FILE__);
define('RAFFLELB_SELECTION_ENGINE_DIR', plugin_dir_path(__FILE__));
define('RAFFLELB_SELECTION_ENGINE_URL', plugin_dir_url(__FILE__));

require_once RAFFLELB_SELECTION_ENGINE_DIR . 'includes/class-rafflelb-selection-adapter.php';
require_once RAFFLELB_SELECTION_ENGINE_DIR . 'includes/class-rafflelb-selection-renderer.php';

final class RaffleLB_Selection_Engine {
    public static function init() {
        register_activation_hook(__FILE__, [__CLASS__, 'activate']);
        register_deactivation_hook(__FILE__, [__CLASS__, 'deactivate']);

        add_action('init', [__CLASS__, 'register_routes']);
        add_action('init', [__CLASS__, 'register_shortcode'], 40);
        add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_filter('query_vars', [__CLASS__, 'query_vars']);
        add_filter('template_include', [__CLASS__, 'template_include']);
        add_action('template_redirect', [__CLASS__, 'virtual_route_status'], 1);
        add_filter('redirect_canonical', [__CLASS__, 'disable_virtual_canonical'], 10, 2);
        add_filter('body_class', [__CLASS__, 'body_class']);
        add_filter('document_title_parts', [__CLASS__, 'document_title'], 9999);
        add_filter('pre_get_document_title', [__CLASS__, 'pre_document_title'], 9999);
        // Compatibility with older themes and the most common SEO title layers.
        add_filter('wp_title', [__CLASS__, 'legacy_wp_title'], 9999, 3);
        add_filter('wpseo_title', [__CLASS__, 'seo_title'], 9999);
        add_filter('rank_math/frontend/title', [__CLASS__, 'seo_title'], 9999);
        add_filter('aioseo_title', [__CLASS__, 'seo_title'], 9999);
        add_filter('seopress_titles_title', [__CLASS__, 'seo_title'], 9999);
        // Final browser-title fallback for themes that print their own title tag.
        add_action('wp_head', [__CLASS__, 'force_browser_title'], 9999);
        add_action('woocommerce_after_add_to_cart_form', [__CLASS__, 'product_status_link'], 5);
    }

    public static function activate() {
        self::register_routes();
        flush_rewrite_rules(false);
    }

    public static function deactivate() {
        flush_rewrite_rules(false);
    }

    public static function register_routes() {
        add_rewrite_rule('^selection-engine/?$', 'index.php?rafflelb_selection_hub=1', 'top');
        add_rewrite_rule('^selection/([^/]+)/?$', 'index.php?rafflelb_selection=$matches[1]', 'top');
    }

    public static function query_vars($vars) {
        $vars[] = 'rafflelb_selection_hub';
        $vars[] = 'rafflelb_selection';
        return $vars;
    }

    public static function register_shortcode() {
        add_shortcode('rafflelb_selection_engine', [__CLASS__, 'shortcode']);

        // The stable Draw Engine owns the page and result records. Selection
        // Engine owns the public Winners presentation through WordPress's
        // existing shortcode extension point, without changing Draw Engine.
        if (class_exists('RaffleLB_Draw_Engine')) {
            remove_shortcode('rafflelb_winners');
            add_shortcode('rafflelb_winners', [__CLASS__, 'winners_shortcode']);
        }
    }

    public static function shortcode() {
        self::enqueue_engine_assets();
        return RaffleLB_Selection_Renderer::hub();
    }

    public static function winners_shortcode() {
        self::enqueue_public_result_assets(true);
        return RaffleLB_Selection_Renderer::winners();
    }

    public static function is_engine_request() {
        return (bool) get_query_var('rafflelb_selection_hub') || (string) get_query_var('rafflelb_selection') !== '';
    }

    public static function enqueue_assets() {
        wp_register_style(
            'rafflelb-selection-engine',
            RAFFLELB_SELECTION_ENGINE_URL . 'assets/css/selection-engine.css',
            [],
            RAFFLELB_SELECTION_ENGINE_VERSION
        );
        wp_register_script(
            'rafflelb-selection-engine',
            RAFFLELB_SELECTION_ENGINE_URL . 'assets/js/selection-engine.js',
            [],
            RAFFLELB_SELECTION_ENGINE_VERSION,
            true
        );
        wp_register_style(
            'rafflelb-selection-results',
            RAFFLELB_SELECTION_ENGINE_URL . 'assets/css/public-results.css',
            [],
            RAFFLELB_SELECTION_ENGINE_VERSION
        );

        $needs_engine = self::is_engine_request();
        $needs_results = function_exists('is_front_page') && is_front_page();
        if (is_singular()) {
            $post = get_post();
            if ($post) {
                $content = (string) $post->post_content;
                $needs_engine = $needs_engine || has_shortcode($content, 'rafflelb_selection_engine');
                $needs_results = $needs_results
                    || has_shortcode($content, 'rafflelb_winners')
                    || has_shortcode($content, 'rafflelb_latest_winners')
                    || has_shortcode($content, 'rafflelb_recent_winners');
            }
        }

        $product_id = 0;
        if (function_exists('is_product') && is_product()) {
            $product_id = absint(get_queried_object_id());
        }

        if ($needs_engine) {
            self::enqueue_engine_assets();
        } elseif ($product_id && RaffleLB_Selection_Adapter::is_public_raffle_product($product_id)) {
            wp_enqueue_style('rafflelb-selection-engine');
        }
        if ($needs_results) {
            self::enqueue_public_result_assets(is_page('winners'));
        }
    }

    private static function enqueue_engine_assets() {
        wp_enqueue_style('rafflelb-selection-engine');
        wp_enqueue_script('rafflelb-selection-engine');
        wp_localize_script('rafflelb-selection-engine', 'RaffleLBSelectionEngine', [
            'restBase' => esc_url_raw(rest_url('rafflelb-selection-engine/v1/status/')),
            'lockedPoolBase' => esc_url_raw(rest_url('rafflelb-selection-engine/v1/locked-pool/')),
            'livePoolBase' => esc_url_raw(rest_url('rafflelb-selection-engine/v1/live-pool/')),
            'pollMs'   => 25000,
        ]);
    }

    public static function enqueue_public_result_assets($with_script = false) {
        wp_enqueue_style('rafflelb-selection-results');
        if ($with_script) {
            wp_enqueue_script('rafflelb-selection-engine');
        }
    }

    public static function template_include($template) {
        if (!self::is_engine_request()) {
            return $template;
        }
        self::enqueue_engine_assets();
        return RAFFLELB_SELECTION_ENGINE_DIR . 'templates/selection-page.php';
    }

    public static function virtual_route_status() {
        if (!self::is_engine_request()) {
            return;
        }
        global $wp_query;
        $exists = (bool) get_query_var('rafflelb_selection_hub');
        if (!$exists) {
            $exists = (bool) RaffleLB_Selection_Adapter::product_by_slug((string) get_query_var('rafflelb_selection'));
        }
        if ($exists) {
            status_header(200);
            if ($wp_query instanceof WP_Query) {
                $wp_query->is_404 = false;
                $wp_query->is_page = true;
            }
        } else {
            status_header(404);
        }
    }

    public static function disable_virtual_canonical($redirect_url, $requested_url) {
        return self::is_engine_request() ? false : $redirect_url;
    }

    public static function body_class($classes) {
        if (self::is_engine_request()) {
            $classes[] = 'rafflelb-selection-engine-page';
        }
        return $classes;
    }

    private static function browser_title() {
        if (!self::is_engine_request()) {
            return '';
        }

        $site_name = trim((string) get_bloginfo('name'));
        if ($site_name === '') {
            $site_name = 'RaffleLB';
        }

        if (get_query_var('rafflelb_selection_hub')) {
            return sprintf(__('Selection Engine - %s', 'rafflelb-selection-engine'), $site_name);
        }

        $slug = (string) get_query_var('rafflelb_selection');
        if ($slug !== '') {
            $product = RaffleLB_Selection_Adapter::product_by_slug($slug);
            if ($product) {
                return sprintf(__('Selection Status — %1$s - %2$s', 'rafflelb-selection-engine'), $product->get_name(), $site_name);
            }
        }

        return sprintf(__('Selection Engine - %s', 'rafflelb-selection-engine'), $site_name);
    }

    public static function document_title($parts) {
        if (!self::is_engine_request()) {
            return $parts;
        }

        if (get_query_var('rafflelb_selection_hub')) {
            $parts['title'] = __('Selection Engine', 'rafflelb-selection-engine');
        } else {
            $slug = (string) get_query_var('rafflelb_selection');
            $product = $slug !== '' ? RaffleLB_Selection_Adapter::product_by_slug($slug) : false;
            $parts['title'] = $product
                ? sprintf(__('Selection Status — %s', 'rafflelb-selection-engine'), $product->get_name())
                : __('Selection Engine', 'rafflelb-selection-engine');
        }

        // Let WordPress/theme add the site name normally; the pre-title filter below
        // remains the authoritative full browser title for virtual routes.
        return $parts;
    }

    public static function pre_document_title($title) {
        $forced = self::browser_title();
        return $forced !== '' ? $forced : $title;
    }

    public static function legacy_wp_title($title, $sep = '', $seplocation = '') {
        $forced = self::browser_title();
        return $forced !== '' ? $forced : $title;
    }

    public static function seo_title($title) {
        $forced = self::browser_title();
        return $forced !== '' ? $forced : $title;
    }

    public static function force_browser_title() {
        $forced = self::browser_title();
        if ($forced === '') {
            return;
        }
        // Some themes/SEO plugins render a title independently of WordPress's
        // document-title API. Setting document.title here is a narrow fallback
        // only on Selection Engine virtual routes and does not affect other pages.
        echo '<script id="rafflelb-selection-browser-title">document.title=' . wp_json_encode($forced) . ';</script>' . "\n";
    }

    public static function register_rest_routes() {
        register_rest_route('rafflelb-selection-engine/v1', '/status/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'rest_status'],
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => [
                    'sanitize_callback' => 'absint',
                    'validate_callback' => static function ($value) {
                        return absint($value) > 0;
                    },
                ],
            ],
        ]);
        register_rest_route('rafflelb-selection-engine/v1', '/locked-pool/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'rest_locked_pool'],
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => [
                    'sanitize_callback' => 'absint',
                    'validate_callback' => static function ($value) { return absint($value) > 0; },
                ],
                'page' => [
                    'default' => 1,
                    'sanitize_callback' => 'absint',
                ],
                'per_page' => [
                    'default' => 60,
                    'sanitize_callback' => 'absint',
                ],
                'entry' => [
                    'default' => 0,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
        register_rest_route('rafflelb-selection-engine/v1', '/live-pool/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'rest_live_pool'],
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => [
                    'sanitize_callback' => 'absint',
                    'validate_callback' => static function ($value) { return absint($value) > 0; },
                ],
                'page' => [
                    'default' => 1,
                    'sanitize_callback' => 'absint',
                ],
                'per_page' => [
                    'default' => 60,
                    'sanitize_callback' => 'absint',
                ],
                'entry' => [
                    'default' => 0,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    public static function rest_status(WP_REST_Request $request) {
        $product_id = absint($request['id']);
        if (!RaffleLB_Selection_Adapter::available()) {
            return new WP_Error('rafflelb_selection_unavailable', __('Selection status is temporarily unavailable.', 'rafflelb-selection-engine'), ['status' => 503]);
        }
        if (!RaffleLB_Selection_Adapter::is_public_raffle_product($product_id)) {
            return new WP_Error('rafflelb_selection_not_found', __('Raffle not found.', 'rafflelb-selection-engine'), ['status' => 404]);
        }
        $snapshot = RaffleLB_Selection_Adapter::snapshot($product_id);
        $response = rest_ensure_response($snapshot);
        if (is_array($snapshot) && ($snapshot['status'] ?? '') !== 'complete') {
            $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->header('Pragma', 'no-cache');
        }
        return $response;
    }

    public static function rest_locked_pool(WP_REST_Request $request) {
        $product_id = absint($request['id']);
        if (!RaffleLB_Selection_Adapter::available()) {
            return new WP_Error('rafflelb_selection_unavailable', __('Selection status is temporarily unavailable.', 'rafflelb-selection-engine'), ['status' => 503]);
        }
        if (!RaffleLB_Selection_Adapter::is_public_raffle_product($product_id)) {
            return new WP_Error('rafflelb_selection_not_found', __('Raffle not found.', 'rafflelb-selection-engine'), ['status' => 404]);
        }

        $pool = RaffleLB_Selection_Adapter::locked_pool_page(
            $product_id,
            max(1, absint($request->get_param('page'))),
            min(100, max(1, absint($request->get_param('per_page')))),
            absint($request->get_param('entry'))
        );
        if (!$pool) {
            return new WP_Error('rafflelb_locked_pool_unavailable', __('No authoritative locked pool is available for this raffle.', 'rafflelb-selection-engine'), ['status' => 404]);
        }
        return rest_ensure_response($pool);
    }

    public static function rest_live_pool(WP_REST_Request $request) {
        $product_id = absint($request['id']);
        if (!RaffleLB_Selection_Adapter::available()) {
            return new WP_Error('rafflelb_selection_unavailable', __('Selection status is temporarily unavailable.', 'rafflelb-selection-engine'), ['status' => 503]);
        }
        if (!RaffleLB_Selection_Adapter::is_public_raffle_product($product_id)) {
            return new WP_Error('rafflelb_selection_not_found', __('Raffle not found.', 'rafflelb-selection-engine'), ['status' => 404]);
        }

        $pool = RaffleLB_Selection_Adapter::live_pool_page(
            $product_id,
            max(1, absint($request->get_param('page'))),
            min(100, max(1, absint($request->get_param('per_page')))),
            absint($request->get_param('entry'))
        );
        if (!$pool) {
            return new WP_Error('rafflelb_live_pool_unavailable', __('The live entry list is no longer available for this raffle.', 'rafflelb-selection-engine'), ['status' => 409]);
        }
        $response = rest_ensure_response($pool);
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->header('Pragma', 'no-cache');
        return $response;
    }

    public static function product_status_link() {
        if (class_exists('RaffleLB_Shop')
            && method_exists('RaffleLB_Shop', 'is_selection_entry_form_context')
            && RaffleLB_Shop::is_selection_entry_form_context()) {
            return;
        }
        global $product;
        if (!$product instanceof WC_Product || !RaffleLB_Selection_Adapter::is_public_raffle_product($product->get_id())) {
            return;
        }
        $url = RaffleLB_Selection_Adapter::selection_url($product);
        echo '<div class="rlse-product-link-wrap"><a class="rlse-product-link" href="' . esc_url($url) . '">' . esc_html__('VIEW SELECTION STATUS', 'rafflelb-selection-engine') . '<span aria-hidden="true">→</span></a></div>';
    }
}

RaffleLB_Selection_Engine::init();
