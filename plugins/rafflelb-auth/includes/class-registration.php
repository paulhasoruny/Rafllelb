<?php
if (!defined('ABSPATH')) { exit; }

final class RaffleLB_Auth_Registration {
    public static function init() {
        add_action('wp_ajax_nopriv_rafflelb_auth_register_send', array(__CLASS__, 'send'));
        add_action('wp_ajax_nopriv_rafflelb_auth_register_verify', array(__CLASS__, 'verify'));
        add_action('wp_ajax_nopriv_rafflelb_auth_register_create', array(__CLASS__, 'create'));
    }
    private static function guard() { check_ajax_referer('rafflelb_auth_frontend', 'nonce'); }
    private static function fail($error, $status = 400) { wp_send_json_error(array('message' => is_wp_error($error) ? $error->get_error_message() : $error), $status); }
    public static function send() {
        self::guard(); $phone = RaffleLB_Auth_Phone_Normalizer::normalize(isset($_POST['phone']) ? wp_unslash($_POST['phone']) : '');
        if (is_wp_error($phone)) { self::fail($phone); }
        if (RaffleLB_Auth_User_Service::phone_exists($phone)) { self::fail(new WP_Error('exists', __('This phone number is already registered. Sign in instead.', 'rafflelb-auth'))); }
        $id = RaffleLB_Auth_OTP_Service::issue($phone, 'registration');
        if (is_wp_error($id)) { self::fail($id); }
        wp_send_json_success(array('transaction' => $id, 'masked_phone' => RaffleLB_Auth_Phone_Normalizer::mask($phone), 'message' => __('Verification code sent.', 'rafflelb-auth')));
    }
    public static function verify() {
        self::guard(); $result = RaffleLB_Auth_OTP_Service::verify(self::post('transaction'), self::post('code'), 'registration');
        if (is_wp_error($result)) { self::fail($result); } wp_send_json_success(array('message' => __('Phone verified.', 'rafflelb-auth')));
    }
    public static function create() {
        self::guard(); $id = self::post('transaction'); $state = RaffleLB_Auth_OTP_Service::verified($id, 'registration');
        if (is_wp_error($state)) { self::fail($state); }
        $first_name = sanitize_text_field(self::post('first_name'));
        $last_name = sanitize_text_field(self::post('last_name'));
        $username_raw = trim(self::post('username'));
        $username = sanitize_user($username_raw, true);
        $email = sanitize_email(self::post('email'));
        $password = self::post('password');
        $confirm = self::post('confirm_password');
        if ($first_name === '') { self::fail(new WP_Error('first_name', __('Enter your first name.', 'rafflelb-auth'))); }
        if ($last_name === '') { self::fail(new WP_Error('last_name', __('Enter your family name.', 'rafflelb-auth'))); }
        if ($username === '' || strlen($username) < 3 || strlen($username) > 60 || $username !== $username_raw || !preg_match('/^[A-Za-z0-9._-]+$/', $username)) {
            self::fail(new WP_Error('username', __('Choose a username using 3–60 letters, numbers, dots, underscores, or hyphens.', 'rafflelb-auth')));
        }
        if (username_exists($username)) { self::fail(new WP_Error('username_exists', __('That username is already taken. Choose another one.', 'rafflelb-auth'))); }
        if (!$email || !is_email($email)) { self::fail(new WP_Error('email', __('Enter a valid email address.', 'rafflelb-auth'))); }
        if ($password === '' || strlen($password) < 8) { self::fail(new WP_Error('password', __('Use a password of at least 8 characters.', 'rafflelb-auth'))); }
        if (!hash_equals($password, $confirm)) { self::fail(new WP_Error('confirm', __('Passwords do not match.', 'rafflelb-auth'))); }
        $user = RaffleLB_Auth_User_Service::create_customer($state['phone'], $email, $password, $id, $first_name, $last_name, $username);
        if (is_wp_error($user)) { self::fail($user); }
        RaffleLB_Auth_OTP_Service::consume($id); wp_set_current_user($user->ID); wp_set_auth_cookie($user->ID, true, is_ssl());
        do_action('wp_login', $user->user_login, $user);
        wp_send_json_success(array('message' => __('Account created.', 'rafflelb-auth'), 'redirect' => RaffleLB_Auth_Authentication::account_url()));
    }
    private static function post($key) { return isset($_POST[$key]) ? (string) wp_unslash($_POST[$key]) : ''; }
}

