<?php
if (!defined('ABSPATH')) { exit; }

final class RaffleLB_Auth_Authentication {
    const IDENTIFIER_FAILURE_LIMIT = 10;
    const IDENTIFIER_FAILURE_WINDOW = 900;

    public static function init() { add_action('wp_ajax_nopriv_rafflelb_auth_login', array(__CLASS__, 'login')); }
    public static function login() {
        self::guard();
        $identifier = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
        $mode = isset($_POST['login_mode']) && sanitize_key(wp_unslash($_POST['login_mode'])) === 'legacy' ? 'legacy' : 'phone';
        $password = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : '';

        if ($mode === 'legacy') {
            $rate_identity = 'legacy:' . strtolower(trim($identifier));
            if ($rate_identity === 'legacy:') { $rate_identity = 'legacy:empty'; }
        } else {
            $normalized = RaffleLB_Auth_Phone_Normalizer::normalize($identifier);
            $rate_identity = !is_wp_error($normalized) ? $normalized : strtolower(sanitize_text_field(trim($identifier)));
            if ($rate_identity === '') { $rate_identity = 'empty'; }
        }

        if (RaffleLB_Auth_Rate_Limiter::is_limited('login_identifier_failure', $rate_identity, self::IDENTIFIER_FAILURE_LIMIT)) {
            wp_send_json_error(array('message' => __('Too many login attempts. Please try again later.', 'rafflelb-auth')), 429);
        }

        if ($mode === 'legacy') {
            $legacy_token = isset($_POST['legacy_token']) ? sanitize_text_field(wp_unslash($_POST['legacy_token'])) : '';
            if (!$legacy_token || !wp_verify_nonce($legacy_token, 'rafflelb_auth_legacy')) {
                self::failed_login($rate_identity);
            }
            $user = get_user_by('login', $identifier);
            if (!$user && is_email($identifier)) {
                $user = get_user_by('email', sanitize_email($identifier));
            }
            // This unlisted route is only for manually-created accounts without a
            // verified RaffleLB phone. Normal registered customers stay phone-only.
            if (!$user || get_user_meta($user->ID, '_rafflelb_phone_verified', true) === 'yes') {
                self::failed_login($rate_identity);
            }
        } else {
            if (is_wp_error($normalized)) { self::failed_login($rate_identity); }
            $user = RaffleLB_Auth_User_Service::find_by_phone($normalized);
            if (!$user) { self::failed_login($rate_identity); }
        }

        // Resolve the exact account first, then validate its WordPress password
        // directly. This preserves the hardened 0.1.21 behavior and avoids
        // unrelated username/email authentication filters interfering.
        if ($password === '' || !wp_check_password($password, $user->user_pass, $user->ID)) {
            self::failed_login($rate_identity);
        }

        $remember = !empty($_POST['remember']);
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, $remember, is_ssl());
        do_action('wp_login', $user->user_login, $user);

        RaffleLB_Auth_Rate_Limiter::clear('login_identifier_failure', $rate_identity);
        wp_send_json_success(array('message' => __('Welcome back.', 'rafflelb-auth'), 'redirect' => self::account_url()));
    }

    private static function failed_login($rate_identity) {
        RaffleLB_Auth_Rate_Limiter::hit('login_identifier_failure', $rate_identity, self::IDENTIFIER_FAILURE_LIMIT, self::IDENTIFIER_FAILURE_WINDOW);
        wp_send_json_error(array('message' => __('The login details are incorrect.', 'rafflelb-auth')), 401);
    }
    private static function guard() {
        check_ajax_referer('rafflelb_auth_frontend', 'nonce');
        if (!RaffleLB_Auth_Rate_Limiter::hit('ip_action', RaffleLB_Auth_Rate_Limiter::client_ip(), 40, HOUR_IN_SECONDS)) { wp_send_json_error(array('message' => __('Too many attempts. Please try again later.', 'rafflelb-auth')), 429); }
    }
    public static function account_url() { return function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/'); }
}
