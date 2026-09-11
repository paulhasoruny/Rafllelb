<?php
if (!defined('ABSPATH')) { exit; }

final class RaffleLB_Auth_BestSMSBulk_Provider implements RaffleLB_Auth_SMS_Provider_Interface {
    const ENDPOINT = 'https://www.bestsmsbulk.com/bestsmsbulkapi/common/messaging_api_secured_json.php';

    public function get_id() { return 'bestsmsbulk'; }
    public function get_label() { return __('Best SMS Bulk / PROXIREACH', 'rafflelb-auth'); }

    public function is_available() {
        $credentials = $this->credentials();
        return $credentials['api_key'] !== '' && $credentials['api_secret'] !== '' &&
            $credentials['secured_key'] !== '' && $credentials['sender_id'] !== '';
    }

    public function send($phone, $message, $context = array()) {
        $credentials = $this->credentials();
        if (!$this->is_available()) {
            return new WP_Error('bsb_auth_error', __('Provider authentication is not configured.', 'rafflelb-auth'));
        }

        $canonical = RaffleLB_Auth_Phone_Normalizer::normalize($phone);
        if (is_wp_error($canonical) || !hash_equals($canonical, (string) $phone)) {
            return new WP_Error('bsb_send_failed', __('The SMS destination is invalid.', 'rafflelb-auth'));
        }

        $destination = ltrim($phone, '+');
        $payload = array(
            'api_key' => $credentials['api_key'],
            'api_secret' => $credentials['api_secret'],
            'destination' => $destination,
            'message' => (string) $message,
            'route' => 'sms',
            'senderid' => $credentials['sender_id'],
        );

        $response = wp_remote_post(self::ENDPOINT, array(
            'timeout' => 10,
            'redirection' => 0,
            'sslverify' => true,
            'headers' => array(
                'API-Key' => $credentials['api_key'],
                'API-Secured-Key' => $credentials['secured_key'],
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ),
            'body' => wp_json_encode($payload),
            'data_format' => 'body',
        ));

        if (is_wp_error($response)) {
            return new WP_Error('bsb_network_error', __('Provider network error.', 'rafflelb-auth'));
        }

        $http_status = (int) wp_remote_retrieve_response_code($response);
        $decoded = json_decode(wp_remote_retrieve_body($response), true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return new WP_Error('bsb_invalid_response', __('Provider returned an invalid response.', 'rafflelb-auth'));
        }

        $item = isset($decoded[0]) && is_array($decoded[0]) ? $decoded[0] : null;
        if ($http_status >= 200 && $http_status < 300 && is_array($item) &&
            isset($item['status']) && in_array((int) $item['status'], array(200, 201), true) &&
            isset($item['confirmationid']) && trim((string) $item['confirmationid']) !== '') {
            return true;
        }

        return $this->safe_error($http_status, $item, $decoded);
    }

    private function credentials() {
        $settings = wp_parse_args(get_option('rafflelb_auth_settings', array()), RaffleLB_Auth_Admin::defaults());
        return array(
            'api_key' => $this->credential('RAFFLELB_BSB_API_KEY', $settings, 'bsb_api_key'),
            'api_secret' => $this->credential('RAFFLELB_BSB_API_SECRET', $settings, 'bsb_api_secret'),
            'secured_key' => $this->credential('RAFFLELB_BSB_SECURED_KEY', $settings, 'bsb_secured_key'),
            'sender_id' => $this->credential('RAFFLELB_BSB_SENDER_ID', $settings, 'bsb_sender_id'),
        );
    }

    private function credential($constant_name, $settings, $setting_name) {
        if (defined($constant_name) && is_scalar(constant($constant_name)) && trim((string) constant($constant_name)) !== '') {
            return trim((string) constant($constant_name));
        }
        return isset($settings[$setting_name]) ? trim((string) $settings[$setting_name]) : '';
    }

    private function safe_error($http_status, $item, $decoded) {
        $status = is_array($item) && isset($item['status']) ? (int) $item['status'] : 0;
        $source = is_array($item) ? $item : $decoded;
        $summary = strtolower(implode(' ', $this->scalar_values($source)));

        if ($http_status === 401 || $http_status === 403 || in_array($status, array(401, 403), true) ||
            strpos($summary, 'api key') !== false || strpos($summary, 'api-key') !== false ||
            strpos($summary, 'api secret') !== false || strpos($summary, 'secured-key') !== false ||
            strpos($summary, 'secured key') !== false || strpos($summary, 'mismatch') !== false) {
            return new WP_Error('bsb_auth_error', __('Provider authentication failed.', 'rafflelb-auth'));
        }
        if (strpos($summary, 'sender') !== false && (strpos($summary, 'authoriz') !== false || strpos($summary, 'invalid') !== false)) {
            return new WP_Error('bsb_sender_error', __('Sender ID is not authorized.', 'rafflelb-auth'));
        }
        if (strpos($summary, 'credit') !== false || strpos($summary, 'balance') !== false) {
            return new WP_Error('bsb_no_credits', __('Insufficient SMS credits.', 'rafflelb-auth'));
        }
        if (!is_array($item)) {
            return new WP_Error('bsb_invalid_response', __('Provider returned an unexpected response.', 'rafflelb-auth'));
        }
        return new WP_Error('bsb_send_failed', __('The SMS provider rejected the message.', 'rafflelb-auth'));
    }

    private function scalar_values($value) {
        $values = array();
        foreach ((array) $value as $item) {
            if (is_scalar($item)) { $values[] = (string) $item; }
            elseif (is_array($item)) { $values = array_merge($values, $this->scalar_values($item)); }
        }
        return $values;
    }
}
