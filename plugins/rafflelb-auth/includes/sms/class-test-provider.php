<?php
if (!defined('ABSPATH')) { exit; }

final class RaffleLB_Auth_Test_Provider implements RaffleLB_Auth_SMS_Provider_Interface {
    public function get_id() { return 'test'; }
    public function get_label() { return __('Development test adapter', 'rafflelb-auth'); }
    public function is_available() { return RaffleLB_Auth_Admin::test_mode_enabled(); }

    public function send($phone, $message, $context = array()) {
        if (!$this->is_available() || empty($context['code'])) {
            return new WP_Error('test_disabled', __('The development SMS adapter is disabled.', 'rafflelb-auth'));
        }
        $payload = wp_json_encode(array('phone' => $phone, 'code' => (string) $context['code'], 'created' => time()));
        $encrypted = self::encrypt($payload);
        if (is_wp_error($encrypted)) { return $encrypted; }
        $key = 'rl_auth_test_' . hash('sha256', $phone);
        set_transient($key, $encrypted, 10 * MINUTE_IN_SECONDS);
        $index = get_transient('rl_auth_test_index');
        $index = is_array($index) ? $index : array();
        $index[$phone] = $key;
        set_transient('rl_auth_test_index', array_slice($index, -20, null, true), 10 * MINUTE_IN_SECONDS);
        return true;
    }

    private static function key() { return hash('sha256', wp_salt('auth'), true); }

    private static function encrypt($plaintext) {
        if (function_exists('sodium_crypto_secretbox')) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            return 's:' . base64_encode($nonce . sodium_crypto_secretbox($plaintext, $nonce, self::key()));
        }
        if (function_exists('openssl_encrypt')) {
            $iv = random_bytes(12); $tag = '';
            $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
            return $cipher === false ? new WP_Error('encryption_failed', __('Test adapter encryption failed.', 'rafflelb-auth')) : 'o:' . base64_encode($iv . $tag . $cipher);
        }
        return new WP_Error('encryption_missing', __('Secure encryption is unavailable.', 'rafflelb-auth'));
    }

    public static function decrypt($value) {
        $type = substr((string) $value, 0, 2); $raw = base64_decode(substr((string) $value, 2), true);
        if ($raw === false) { return false; }
        if ($type === 's:' && function_exists('sodium_crypto_secretbox_open')) {
            $n = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
            return sodium_crypto_secretbox_open(substr($raw, $n), substr($raw, 0, $n), self::key());
        }
        if ($type === 'o:' && function_exists('openssl_decrypt')) {
            return openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        }
        return false;
    }
}

