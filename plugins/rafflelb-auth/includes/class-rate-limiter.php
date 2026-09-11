<?php
if (!defined('ABSPATH')) { exit; }

final class RaffleLB_Auth_Rate_Limiter {
    public static function client_ip() {
        return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
    }

    private static function key($scope, $value) {
        return 'rl_auth_rl_' . sanitize_key($scope) . '_' . hash('sha256', strtolower((string) $value));
    }

    public static function hit($scope, $value, $limit, $window) {
        $key = self::key($scope, $value);
        $record = get_transient($key);
        $now = time();
        if (!is_array($record) || empty($record['reset']) || $record['reset'] <= $now) {
            $record = array('count' => 0, 'reset' => $now + absint($window));
        }
        if ((int) $record['count'] >= absint($limit)) { return false; }
        $record['count']++;
        set_transient($key, $record, max(1, $record['reset'] - $now));
        return true;
    }

    public static function is_limited($scope, $value, $limit) {
        $record = get_transient(self::key($scope, $value));
        if (!is_array($record) || empty($record['reset']) || $record['reset'] <= time()) { return false; }
        return (int) $record['count'] >= absint($limit);
    }

    public static function clear($scope, $value) {
        return delete_transient(self::key($scope, $value));
    }

    public static function cooldown_remaining($phone, $seconds) {
        $last = (int) get_transient(self::key('cooldown', $phone));
        return $last ? max(0, absint($seconds) - (time() - $last)) : 0;
    }

    public static function start_cooldown($phone, $seconds) {
        set_transient(self::key('cooldown', $phone), time(), absint($seconds));
    }
}
