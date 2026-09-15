<?php
if (!defined('ABSPATH')) { exit; }

final class RaffleLB_Auth_Phone_Normalizer {
    public static function normalize($raw) {
        $value = trim((string) $raw);
        if ($value === '') { return new WP_Error('phone_required', __('Enter a phone number.', 'rafflelb-auth')); }
        $value = preg_replace('/[^0-9+]/', '', $value);
        if (strpos($value, '00') === 0) { $value = '+' . substr($value, 2); }
        if (strpos($value, '+961') === 0) {
            $national = ltrim(substr($value, 4), '0');
        } elseif (strpos($value, '961') === 0) {
            $national = ltrim(substr($value, 3), '0');
        } elseif (strpos($value, '+') === 0) {
            return new WP_Error('phone_country', __('Use a Lebanese phone number.', 'rafflelb-auth'));
        } else {
            $national = ltrim($value, '0');
        }
        if (!preg_match('/^[0-9]{7,8}$/', $national)) {
            return new WP_Error('phone_invalid', __('Enter a valid Lebanese phone number.', 'rafflelb-auth'));
        }
        return '+961' . $national;
    }

    public static function mask($phone) {
        return substr($phone, 0, 6) . str_repeat('•', max(0, strlen($phone) - 8)) . substr($phone, -2);
    }
}

