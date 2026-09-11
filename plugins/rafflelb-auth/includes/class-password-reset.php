<?php
if (!defined('ABSPATH')) { exit; }

final class RaffleLB_Auth_Password_Reset {
    public static function init() {
        add_action('wp_ajax_nopriv_rafflelb_auth_reset_send', array(__CLASS__, 'send'));
        add_action('wp_ajax_nopriv_rafflelb_auth_reset_verify', array(__CLASS__, 'verify'));
        add_action('wp_ajax_nopriv_rafflelb_auth_reset_finish', array(__CLASS__, 'finish'));
    }
    private static function guard() { check_ajax_referer('rafflelb_auth_frontend', 'nonce'); }
    private static function post($key) { return isset($_POST[$key]) ? (string) wp_unslash($_POST[$key]) : ''; }
    private static function fail($e, $s = 400) { wp_send_json_error(array('message' => is_wp_error($e) ? $e->get_error_message() : $e), $s); }
    public static function send() {
        self::guard(); $phone = RaffleLB_Auth_Phone_Normalizer::normalize(self::post('phone'));
        if (is_wp_error($phone)) { self::fail($phone); }
        $user = RaffleLB_Auth_User_Service::find_by_phone($phone);
        $id = RaffleLB_Auth_OTP_Service::issue($phone, 'reset', $user ? $user->ID : 0, (bool) $user, true);
        if (is_wp_error($id)) {
            // Rate-limit/cooldown failures are deliberately returned through the
            // same public success shape so reset-send never becomes an oracle.
            $id = bin2hex(random_bytes(24));
        }
        wp_send_json_success(array('transaction' => $id, 'masked_phone' => RaffleLB_Auth_Phone_Normalizer::mask($phone), 'message' => __('If this phone number is associated with an account, a verification code will be sent.', 'rafflelb-auth')));
    }
    public static function verify() {
        self::guard(); $state = RaffleLB_Auth_OTP_Service::verify(self::post('transaction'), self::post('code'), 'reset');
        if (is_wp_error($state)) { self::fail($state); }
        wp_send_json_success(array('message' => __('Code verified.', 'rafflelb-auth')));
    }
    public static function finish() {
        self::guard(); $id = self::post('transaction'); $state = RaffleLB_Auth_OTP_Service::verified($id, 'reset');
        if (is_wp_error($state) || empty($state['user_id'])) { self::fail(new WP_Error('invalid', __('This reset session is invalid or expired.', 'rafflelb-auth'))); }
        $user = get_user_by('id', absint($state['user_id']));
        if (!$user || !hash_equals((string) get_user_meta($user->ID, '_rafflelb_phone', true), (string) $state['phone']) || get_user_meta($user->ID, '_rafflelb_phone_verified', true) !== 'yes') { self::fail(new WP_Error('invalid', __('This reset session is invalid or expired.', 'rafflelb-auth'))); }
        $password = self::post('password'); $confirm = self::post('confirm_password');
        if (strlen($password) < 8) { self::fail(new WP_Error('password', __('Use a password of at least 8 characters.', 'rafflelb-auth'))); }
        if (!hash_equals($password, $confirm)) { self::fail(new WP_Error('confirm', __('Passwords do not match.', 'rafflelb-auth'))); }
        wp_set_password($password, $user->ID); RaffleLB_Auth_OTP_Service::consume($id);
        wp_send_json_success(array('message' => __('Password updated. You can now sign in.', 'rafflelb-auth')));
    }
}
