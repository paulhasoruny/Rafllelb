<?php
if (!defined('ABSPATH')) { exit; }

final class RaffleLB_Auth_User_Service {
    public static function find_by_phone($phone) {
        $user_id = RaffleLB_Auth_Phone_Registry::user_id($phone);
        if ($user_id && hash_equals((string) get_user_meta($user_id, '_rafflelb_phone', true), (string) $phone) && get_user_meta($user_id, '_rafflelb_phone_verified', true) === 'yes') {
            return get_user_by('id', $user_id);
        }
        $ids = get_users(array('meta_key' => '_rafflelb_phone', 'meta_value' => $phone, 'number' => 2, 'fields' => 'ids'));
        if (count($ids) === 1 && get_user_meta($ids[0], '_rafflelb_phone_verified', true) === 'yes') { return get_user_by('id', $ids[0]); }
        return false;
    }

    public static function phone_exists($phone) {
        if (self::find_by_phone($phone)) { return true; }
        $ids = get_users(array('meta_key' => '_rafflelb_phone', 'meta_value' => $phone, 'number' => 1, 'fields' => 'ids'));
        return !empty($ids);
    }

    public static function create_customer($phone, $email, $password, $transaction_id) {
        if (self::phone_exists($phone)) { return new WP_Error('phone_exists', __('This phone number is already registered.', 'rafflelb-auth')); }
        if (email_exists($email)) { return new WP_Error('email_exists', __('An account already uses this email address.', 'rafflelb-auth')); }
        if (!RaffleLB_Auth_Phone_Registry::reserve($phone, $transaction_id)) { return new WP_Error('phone_exists', __('This phone number is already registered.', 'rafflelb-auth')); }
        do { $username = 'rl_' . strtolower(wp_generate_password(14, false, false)); } while (username_exists($username));
        $user_id = wp_insert_user(array('user_login' => $username, 'user_email' => $email, 'user_pass' => $password, 'role' => class_exists('WooCommerce') ? 'customer' : get_option('default_role', 'subscriber')));
        if (is_wp_error($user_id)) { RaffleLB_Auth_Phone_Registry::release($phone, $transaction_id); return $user_id; }
        update_user_meta($user_id, '_rafflelb_phone', $phone);
        update_user_meta($user_id, '_rafflelb_phone_verified', 'yes');
        update_user_meta($user_id, '_rafflelb_phone_verified_at', current_time('mysql', true));
        update_user_meta($user_id, 'billing_phone', $phone);
        if (!RaffleLB_Auth_Phone_Registry::commit($phone, $transaction_id, $user_id)) {
            require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user($user_id);
            RaffleLB_Auth_Phone_Registry::release($phone, $transaction_id);
            return new WP_Error('phone_commit', __('Account creation could not be completed. Please try again.', 'rafflelb-auth'));
        }
        return get_user_by('id', $user_id);
    }
}
