<?php
if (!defined('ABSPATH')) { exit; }

final class RaffleLB_Auth_OTP_Service {
    private static function settings() { return wp_parse_args(get_option('rafflelb_auth_settings', array()), RaffleLB_Auth_Admin::defaults()); }
    private static function key($id) { return 'rl_auth_tx_' . hash('sha256', $id); }

    public static function issue($phone, $purpose, $user_id = 0, $deliver = true, $suppress_delivery_failure = false) {
        $settings = self::settings();
        $cooldown = RaffleLB_Auth_Rate_Limiter::cooldown_remaining($phone, $settings['resend_cooldown']);
        if ($cooldown > 0) { return new WP_Error('cooldown', sprintf(__('Please wait %d seconds before requesting another code.', 'rafflelb-auth'), $cooldown)); }
        if (!RaffleLB_Auth_Rate_Limiter::hit('phone_send', $phone, $settings['phone_hourly_limit'], HOUR_IN_SECONDS) ||
            !RaffleLB_Auth_Rate_Limiter::hit('ip_send', RaffleLB_Auth_Rate_Limiter::client_ip(), $settings['ip_hourly_limit'], HOUR_IN_SECONDS)) {
            return new WP_Error('rate_limited', __('Too many requests. Please try again later.', 'rafflelb-auth'));
        }
        $id = bin2hex(random_bytes(24));
        $code = (string) random_int(100000, 999999);
        $record = array(
            'phone' => $phone, 'purpose' => sanitize_key($purpose), 'user_id' => absint($user_id),
            'hash' => wp_hash_password($code), 'attempts' => 0, 'created' => time(),
            'expires' => time() + absint($settings['otp_expiry']), 'verified' => false,
            'verified_expires' => 0, 'ip_hash' => hash('sha256', RaffleLB_Auth_Rate_Limiter::client_ip()),
        );
        set_transient(self::key($id), $record, absint($settings['otp_expiry']) + 60);
        RaffleLB_Auth_Rate_Limiter::start_cooldown($phone, $settings['resend_cooldown']);
        if ($deliver) {
            $provider = RaffleLB_Auth_Provider_Factory::get();
            if (!$provider->is_available()) {
                if (!$suppress_delivery_failure) { delete_transient(self::key($id)); return new WP_Error('provider_unavailable', __('SMS verification is temporarily unavailable.', 'rafflelb-auth')); }
                do_action('rafflelb_auth_sms_delivery_failed', 'provider_unavailable', hash('sha256', $phone), $purpose);
            } else {
                $lifetime = self::expiry_label(absint($settings['otp_expiry']));
                $sent = $provider->send($phone, sprintf(__('Your RaffleLB verification code is %1$s. It expires in %2$s.', 'rafflelb-auth'), $code, $lifetime), array('code' => $code, 'purpose' => $purpose));
                if (is_wp_error($sent) || !$sent) {
                    if (!$suppress_delivery_failure) { delete_transient(self::key($id)); return is_wp_error($sent) ? $sent : new WP_Error('send_failed', __('The code could not be sent.', 'rafflelb-auth')); }
                    do_action('rafflelb_auth_sms_delivery_failed', is_wp_error($sent) ? $sent->get_error_code() : 'send_failed', hash('sha256', $phone), $purpose);
                }
            }
        }
        unset($code);
        return $id;
    }

    public static function verify($id, $code, $purpose) {
        $record = self::get($id, $purpose);
        if (is_wp_error($record)) { return $record; }
        if (!empty($record['verified'])) {
            return array('phone' => $record['phone'], 'masked_phone' => RaffleLB_Auth_Phone_Normalizer::mask($record['phone']));
        }
        if (empty($record['hash'])) {
            return new WP_Error('transaction_invalid', __('This verification session is invalid.', 'rafflelb-auth'));
        }
        $settings = self::settings();
        if ((int) $record['attempts'] >= absint($settings['max_attempts'])) {
            delete_transient(self::key($id));
            return new WP_Error('attempts', __('This code is no longer valid. Request a new one.', 'rafflelb-auth'));
        }
        $record['attempts']++;
        if (!preg_match('/^[0-9]{6}$/', (string) $code) || !wp_check_password((string) $code, $record['hash'])) {
            if ($record['attempts'] >= absint($settings['max_attempts'])) { delete_transient(self::key($id)); }
            else { set_transient(self::key($id), $record, max(1, $record['expires'] - time())); }
            return new WP_Error('invalid_code', __('The code is incorrect or expired.', 'rafflelb-auth'));
        }
        $record['verified'] = true;
        $record['verified_expires'] = time() + 10 * MINUTE_IN_SECONDS;
        unset($record['hash']);
        set_transient(self::key($id), $record, 10 * MINUTE_IN_SECONDS);
        return array('phone' => $record['phone'], 'masked_phone' => RaffleLB_Auth_Phone_Normalizer::mask($record['phone']));
    }

    public static function verified($id, $purpose) {
        $record = self::get($id, $purpose);
        if (is_wp_error($record) || empty($record['verified']) || empty($record['verified_expires']) || $record['verified_expires'] < time()) {
            return new WP_Error('verification_required', __('Phone verification has expired. Start again.', 'rafflelb-auth'));
        }
        return $record;
    }

    public static function consume($id) { delete_transient(self::key($id)); }

    private static function get($id, $purpose) {
        if (!preg_match('/^[a-f0-9]{48}$/', (string) $id)) { return new WP_Error('transaction_invalid', __('Invalid verification session.', 'rafflelb-auth')); }
        $record = get_transient(self::key($id));
        if (!is_array($record) || empty($record['purpose']) || !hash_equals($record['purpose'], sanitize_key($purpose))) {
            return new WP_Error('transaction_expired', __('This verification session has expired.', 'rafflelb-auth'));
        }
        $deadline = !empty($record['verified']) ? (int) $record['verified_expires'] : (int) $record['expires'];
        if (!$deadline || $deadline < time()) { return new WP_Error('transaction_expired', __('This verification session has expired.', 'rafflelb-auth')); }
        return $record;
    }

    private static function expiry_label($seconds) {
        if ($seconds >= MINUTE_IN_SECONDS && $seconds % MINUTE_IN_SECONDS === 0) {
            $minutes = (int) ($seconds / MINUTE_IN_SECONDS);
            return sprintf(_n('%d minute', '%d minutes', $minutes, 'rafflelb-auth'), $minutes);
        }
        return sprintf(_n('%d second', '%d seconds', $seconds, 'rafflelb-auth'), $seconds);
    }
}
