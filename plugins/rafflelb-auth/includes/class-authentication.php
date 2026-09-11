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
        $normalized = $mode === 'phone' ? RaffleLB_Auth_Phone_Normalizer::normalize($identifier) : new WP_Error('legacy_identifier', '');
        $rate_identity = !is_wp_error($normalized) ? $normalized : strtolower(sanitize_user($identifier, true));
        if ($rate_identity === '') { $rate_identity = strtolower(sanitize_text_field(trim($identifier))); }
        if (RaffleLB_Auth_Rate_Limiter::is_limited('login_identifier_failure', $rate_identity, self::IDENTIFIER_FAILURE_LIMIT)) {
            wp_send_json_error(array('message' => __('Too many login attempts. Please try again later.', 'rafflelb-auth')), 429);
        }
        if ($mode === 'phone') {
            if (is_wp_error($normalized)) { self::failed_login($rate_identity); }
            $user = RaffleLB_Auth_User_Service::find_by_phone($normalized);
            if (!$user) { self::failed_login($rate_identity); }
            $login = $user->user_login;
        } else {
            $login = $identifier;
        }
        $signed = wp_signon(array('user_login' => $login, 'user_password' => $password, 'remember' => !empty($_POST['remember'])), is_ssl());
        if (is_wp_error($signed)) { self::failed_login($rate_identity); }
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
