<?php
/**
 * Plugin Name: RaffleLB Premium Mobile Menu
 * Description: Replaces the Woodmart mobile hamburger drawer with the premium RaffleLB navigation design.
 * Version: 1.1.2
 * Author: RaffleLB
 * Text Domain: rafflelb-premium-mobile-menu
 */

if (!defined('ABSPATH')) {
    exit;
}

final class RaffleLB_Premium_Mobile_Menu {
    const VERSION = '1.1.2';
    const OPTION  = 'rafflelb_mobile_menu_socials';

    public static function init() {
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_assets'), 40);
        add_action('wp_footer', array(__CLASS__, 'render_menu'), 90);
        add_action('admin_menu', array(__CLASS__, 'admin_menu'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_filter('script_loader_tag', array(__CLASS__, 'protect_menu_script'), 10, 2);
        add_filter('rocket_delay_js_exclusions', array(__CLASS__, 'exclude_from_delayed_js'));
        add_filter('litespeed_optimize_js_excludes', array(__CLASS__, 'exclude_from_delayed_js'));
        add_filter('wp_nav_menu', array(__CLASS__, 'replace_woodmart_mobile_menu'), 999, 2);
    }

    public static function protect_menu_script($tag, $handle) {
        if ('rafflelb-premium-mobile-menu' !== $handle) {
            return $tag;
        }

        return str_replace(
            ' src=',
            ' data-cfasync="false" data-no-optimize="1" data-no-defer="1" src=',
            $tag
        );
    }

    public static function exclude_from_delayed_js($exclusions) {
        if (!is_array($exclusions)) {
            $exclusions = array();
        }

        $exclusions[] = 'rafflelb-premium-mobile-menu';
        $exclusions[] = 'mobile-menu.js';

        return array_values(array_unique($exclusions));
    }

    public static function enqueue_assets() {
        if (is_admin()) {
            return;
        }

        $base = plugin_dir_url(__FILE__) . 'assets/';

        wp_enqueue_style(
            'rafflelb-premium-mobile-menu',
            $base . 'mobile-menu.css',
            array(),
            self::VERSION
        );

        wp_enqueue_script(
            'rafflelb-premium-mobile-menu',
            $base . 'mobile-menu.js',
            array(),
            self::VERSION,
            true
        );
    }

    private static function page_url($slug, $fallback) {
        $page = get_page_by_path($slug);

        if ($page instanceof WP_Post) {
            return get_permalink($page);
        }

        return home_url($fallback);
    }

    private static function account_url($endpoint = '') {
        if ($endpoint && function_exists('wc_get_account_endpoint_url')) {
            return wc_get_account_endpoint_url($endpoint);
        }

        if (function_exists('wc_get_page_permalink')) {
            $account = wc_get_page_permalink('myaccount');
            if ($account) {
                return $endpoint
                    ? trailingslashit($account) . trailingslashit($endpoint)
                    : $account;
            }
        }

        return $endpoint
            ? home_url('/my-account/' . trailingslashit($endpoint))
            : home_url('/my-account/');
    }

    private static function shop_url() {
        if (function_exists('wc_get_page_permalink')) {
            $shop = wc_get_page_permalink('shop');
            if ($shop) {
                return $shop;
            }
        }

        return home_url('/shop/');
    }

    private static function website_logo_url() {
        $logo_id = absint(get_theme_mod('custom_logo'));

        if ($logo_id) {
            $url = wp_get_attachment_image_url($logo_id, 'full');
            if ($url) {
                return $url;
            }
        }

        if (function_exists('woodmart_get_opt')) {
            foreach (array('logo-mobile', 'logo_mobile', 'logo') as $option_name) {
                $logo = woodmart_get_opt($option_name);

                if (is_array($logo) && !empty($logo['url'])) {
                    return esc_url_raw($logo['url']);
                }

                if (is_numeric($logo)) {
                    $url = wp_get_attachment_image_url(absint($logo), 'full');
                    if ($url) {
                        return $url;
                    }
                }
            }
        }

        return content_url('/uploads/2026/08/Logo-Last.png');
    }

    private static function is_active($item) {
        switch ($item) {
            case 'shop':
                return function_exists('is_shop') && (is_shop() || is_product_category() || is_product());

            case 'raffles':
                return is_page('raffles');

            case 'how-it-works':
                return is_front_page();

            case 'winners':
                return is_page('winners');

            case 'my-raffles':
                return function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('rafflelb-entries');

            case 'refer':
                return function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('refer-and-earn');

            case 'contact':
                return is_page('contact-us');

            case 'account':
                return function_exists('is_account_page') && is_account_page()
                    && !(function_exists('is_wc_endpoint_url') && (
                        is_wc_endpoint_url('rafflelb-entries')
                        || is_wc_endpoint_url('refer-and-earn')
                    ));
        }

        return false;
    }

    private static function icon($name, $class = 'rlmm-icon-svg') {
        $open = '<svg class="' . esc_attr($class) . '" viewBox="0 0 32 32" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">';
        $close = '</svg>';

        $icons = array(
            'shop' => '<path d="M8.5 11.5h15l1.3 15H7.2l1.3-15Z"/><path d="M12 12V8.8a4 4 0 0 1 8 0V12"/>',
            'raffles' => '<path d="M7.2 12.2 17.6 3.8l10.6 13.1-10.4 8.4a3.1 3.1 0 0 1-4.4-.5L6.7 16.6a3.1 3.1 0 0 1 .5-4.4Z"/><path d="m13.1 10.3 1.2 1.5m2.2 2.7 1.2 1.5m2.2 2.7 1.2 1.5"/><path d="M20.8 7.4 23 10"/>',
            'how' => '<path d="M12.7 3.5h6.6l.8 3.2c.7.3 1.3.6 1.9 1.1l3-1.1 3.3 5.7-2.4 2.1c.1.5.1 1 .1 1.5s0 1-.1 1.5l2.4 2.1-3.3 5.7-3-1.1c-.6.5-1.2.8-1.9 1.1l-.8 3.2h-6.6l-.8-3.2c-.7-.3-1.3-.6-1.9-1.1l-3 1.1-3.3-5.7 2.4-2.1A9 9 0 0 1 6 16c0-.5 0-1 .1-1.5l-2.4-2.1L7 6.7l3 1.1c.6-.5 1.2-.8 1.9-1.1l.8-3.2Z"/><circle cx="16" cy="16" r="4.1"/>',
            'winners' => '<path d="M10.3 5.5h11.4v6.1a5.7 5.7 0 0 1-11.4 0V5.5Z"/><path d="M10.2 8H6.4v2.1a5.1 5.1 0 0 0 5.2 5.1M21.8 8h3.8v2.1a5.1 5.1 0 0 1-5.2 5.1M16 17.3v5.1M11.8 27h8.4M13.4 22.4h5.2V27"/>',
            'ticket' => '<path d="M5.2 9.2h21.6v5a3.2 3.2 0 0 0 0 6.4v5.2H5.2v-5.2a3.2 3.2 0 0 0 0-6.4v-5Z"/><path d="M12 12.2h9M12 16h7M12 19.8h9"/>',
            'refer' => '<circle cx="12" cy="10" r="4"/><path d="M4.8 25.5v-2.8a7.2 7.2 0 0 1 14.4 0v2.8M24 10v8M20 14h8"/>',
            'contact' => '<path d="M9.3 4.6 5.7 8.2c-1.5 1.5.1 6.8 5.4 12.1s10.6 6.9 12.1 5.4l3.6-3.6-5.2-4-2.9 2.6c-1.7-.6-3.4-1.7-5-3.3s-2.7-3.3-3.3-5l2.9-2.8-4-5Z"/>',
            'account' => '<circle cx="16" cy="9.4" r="5.2"/><path d="M6.4 27.2v-3.4a9.6 9.6 0 0 1 19.2 0v3.4H6.4Z"/>',
            'gift' => '<path d="M5.5 13h21v14h-21V13ZM4 9h24v4H4V9ZM16 9v18"/><path d="M16 9H11a3 3 0 1 1 3-3c0 1.5 2 3 2 3ZM16 9h5a3 3 0 1 0-3-3c0 1.5-2 3-2 3Z"/><path d="M3 17h-2M5 4 3.5 2.5M29 17h2"/>',
            'close' => '<path d="m9 9 14 14M23 9 9 23"/>',
        );

        return isset($icons[$name]) ? $open . $icons[$name] . $close : '';
    }

    private static function social_icon($name) {
        $icons = array(
            'instagram' => '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.9"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4.2"/><circle cx="17.4" cy="6.7" r=".8" fill="currentColor" stroke="none"/></svg>',
            'facebook' => '<svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M13.8 21v-8h2.8l.4-3.2h-3.2v-2c0-.9.3-1.6 1.7-1.6h1.7V3.3c-.3 0-1.3-.1-2.5-.1-2.5 0-4.2 1.5-4.2 4.4v2.2H7.7V13h2.8v8h3.3Z"/></svg>',
            'tiktok' => '<svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M15.2 3c.3 2.4 1.7 3.8 4 4v3.1c-1.5.1-2.8-.3-4-1.1v6.1a5.7 5.7 0 1 1-4.9-5.6v3.2a2.6 2.6 0 1 0 1.7 2.4V3h3.2Z"/></svg>',
        );

        return isset($icons[$name]) ? $icons[$name] : '';
    }

    private static function nav_item($key, $label, $url, $icon) {
        $active = self::is_active($key);
        ?>
        <a class="rlmm-nav-item<?php echo $active ? ' is-active' : ''; ?>"
           href="<?php echo esc_url($url); ?>"
           <?php echo $active ? 'aria-current="page"' : ''; ?>>
            <span class="rlmm-nav-icon"><?php echo self::icon($icon); ?></span>
            <span class="rlmm-nav-label"><?php echo esc_html($label); ?></span>
            <span class="rlmm-nav-arrow" aria-hidden="true">›</span>
        </a>
        <?php
    }

    private static function render_menu_content() {
        $website_logo = self::website_logo_url();
        $socials = wp_parse_args(
            (array) get_option(self::OPTION, array()),
            array(
                'instagram' => '',
                'facebook'  => '',
                'tiktok'    => '',
            )
        );
        $socials = array_intersect_key($socials, array_flip(array('instagram', 'facebook', 'tiktok')));

        $account_label = is_user_logged_in() ? 'My Account' : 'Login / Register';
        ?>
        <header class="rlmm-brand-card">
            <a class="rlmm-close close-side-widget" href="#" aria-label="Close menu">
                <?php echo self::icon('close', 'rlmm-close-svg'); ?>
            </a>

            <a class="rlmm-brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="RaffleLB home">
                <span class="rlmm-logo-lockup">
                    <span class="rlmm-website-logo-frame">
                        <img class="rlmm-website-logo" src="<?php echo esc_url($website_logo); ?>" alt="RaffleLB" loading="eager">
                    </span>
                    <span class="rlmm-wordmark-tagline">SHOP <i></i> ENTER <i></i> WIN</span>
                </span>
            </a>

            <p>More than raffles. A bigger tomorrow.</p>
        </header>

        <nav class="rlmm-nav-card" aria-label="Main mobile navigation">
            <?php self::nav_item('shop', 'Shop', self::shop_url(), 'shop'); ?>
            <?php self::nav_item('raffles', 'Raffles', self::page_url('raffles', '/raffles/'), 'raffles'); ?>
            <?php self::nav_item('how-it-works', 'How It Works', home_url('/#how-it-works'), 'how'); ?>
            <?php self::nav_item('winners', 'Winners', self::page_url('winners', '/winners/'), 'winners'); ?>
            <?php self::nav_item('my-raffles', 'My Raffles', self::account_url('rafflelb-entries'), 'ticket'); ?>
            <?php self::nav_item('refer', 'Refer & Earn', self::account_url('refer-and-earn'), 'refer'); ?>
        </nav>

        <nav class="rlmm-nav-card rlmm-nav-card-secondary" aria-label="Support and account navigation">
            <?php self::nav_item('contact', 'Contact Us', self::page_url('contact-us', '/contact-us/'), 'contact'); ?>
            <?php self::nav_item('account', $account_label, self::account_url(), 'account'); ?>
        </nav>

        <a class="rlmm-refer-card" href="<?php echo esc_url(self::account_url('refer-and-earn')); ?>">
            <span class="rlmm-refer-icon"><?php echo self::icon('gift'); ?></span>
            <span class="rlmm-refer-copy">
                <small>SHARE THE EXCITEMENT</small>
                <strong>Refer &amp; Earn</strong>
                <span>Get rewarded together</span>
            </span>
            <span class="rlmm-refer-arrow" aria-hidden="true">›</span>
        </a>

        <footer class="rlmm-footer">
            <div class="rlmm-socials" aria-label="RaffleLB social links">
                <?php foreach ($socials as $network => $url) : ?>
                    <a href="<?php echo $url ? esc_url($url) : '#'; ?>"
                       data-rlmm-social="<?php echo esc_attr($network); ?>"
                       data-rlmm-domain="<?php echo esc_attr($network . '.com'); ?>"
                       aria-label="<?php echo esc_attr(ucfirst($network)); ?>"
                       target="_blank"
                       rel="noopener noreferrer nofollow"
                       <?php echo $url ? '' : 'aria-disabled="true"'; ?>>
                        <?php echo self::social_icon($network); ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="rlmm-slogan">
                <span>Shop</span><i></i><span>Enter</span><i></i><span>Win</span>
            </div>
        </footer>
        <?php
    }

    public static function replace_woodmart_mobile_menu($nav_menu, $args) {
        if (is_admin()) {
            return $nav_menu;
        }

        $location = isset($args->theme_location) ? strtolower((string) $args->theme_location) : '';
        $menu_class = isset($args->menu_class) ? strtolower((string) $args->menu_class) : '';
        $menu_html = strtolower((string) $nav_menu);

        $is_mobile_menu = false !== strpos($location, 'mobile')
            || false !== strpos($menu_class, 'wd-nav-mobile')
            || false !== strpos($menu_html, 'wd-nav-mobile');

        if (!$is_mobile_menu) {
            return $nav_menu;
        }

        ob_start();
        ?>
        <ul class="wd-nav wd-nav-mobile rlmm-native-list" data-rlmm-version="<?php echo esc_attr(self::VERSION); ?>">
            <li class="rlmm-native-shell">
                <div class="rlmm-scroll">
                    <?php self::render_menu_content(); ?>
                </div>
            </li>
        </ul>
        <?php

        return ob_get_clean();
    }

    public static function render_menu() {
        if (is_admin()) {
            return;
        }

        ?>
        <div class="rlmm-backdrop" id="rlmm-backdrop" aria-hidden="true"></div>

        <aside class="rlmm-drawer" id="rlmm-drawer" data-rlmm-version="<?php echo esc_attr(self::VERSION); ?>" aria-hidden="true" aria-label="RaffleLB mobile navigation" inert>
            <div class="rlmm-scroll">
                <?php self::render_menu_content(); ?>
            </div>
        </aside>
        <?php
    }

    public static function admin_menu() {
        add_theme_page(
            'RaffleLB Mobile Menu',
            'RaffleLB Mobile Menu',
            'manage_options',
            'rafflelb-mobile-menu',
            array(__CLASS__, 'settings_page')
        );
    }

    public static function register_settings() {
        register_setting(
            'rafflelb_mobile_menu_group',
            self::OPTION,
            array(
                'type'              => 'array',
                'sanitize_callback' => array(__CLASS__, 'sanitize_socials'),
                'default'           => array(),
            )
        );
    }

    public static function sanitize_socials($value) {
        $clean = array();

        foreach (array('instagram', 'facebook', 'tiktok') as $network) {
            $clean[$network] = isset($value[$network])
                ? esc_url_raw(trim($value[$network]))
                : '';
        }

        return $clean;
    }

    public static function settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $socials = wp_parse_args(
            (array) get_option(self::OPTION, array()),
            array(
                'instagram' => '',
                'facebook'  => '',
                'tiktok'    => '',
            )
        );
        $socials = array_intersect_key($socials, array_flip(array('instagram', 'facebook', 'tiktok')));
        ?>
        <div class="wrap">
            <h1>RaffleLB Mobile Menu</h1>
            <p>
                Add the official RaffleLB social profile links. If these are empty,
                the menu will try to reuse matching links already present in the website footer.
            </p>

            <form method="post" action="options.php">
                <?php settings_fields('rafflelb_mobile_menu_group'); ?>

                <table class="form-table" role="presentation">
                    <?php foreach ($socials as $network => $url) : ?>
                        <tr>
                            <th scope="row">
                                <label for="rlmm-<?php echo esc_attr($network); ?>">
                                    <?php echo esc_html(ucfirst($network)); ?> URL
                                </label>
                            </th>
                            <td>
                                <input
                                    id="rlmm-<?php echo esc_attr($network); ?>"
                                    class="regular-text"
                                    type="url"
                                    name="<?php echo esc_attr(self::OPTION); ?>[<?php echo esc_attr($network); ?>]"
                                    value="<?php echo esc_attr($url); ?>"
                                    placeholder="https://">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <?php submit_button('Save Social Links'); ?>
            </form>
        </div>
        <?php
    }
}

RaffleLB_Premium_Mobile_Menu::init();
