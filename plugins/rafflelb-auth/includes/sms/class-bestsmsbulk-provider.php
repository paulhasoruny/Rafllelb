<?php
if (!defined('ABSPATH')) { exit; }

final class RaffleLB_Auth_BestSMSBulk_Provider implements RaffleLB_Auth_SMS_Provider_Interface {
    const ENDPOINT = 'https://www.bestsmsbulk.com/bestsmsbulkapi/sendSmsAPI.php';

    public function get_id() { return 'bestsmsbulk'; }
    public function get_label() { return __('Best SMS Bulk / PROXIREACH', 'rafflelb-auth'); }

    public function is_available() {
        $credentials = $this->credentials();
        return $credentials['api_key'] !== '' && $credentials['api_secret'] !== '' && $credentials['sender_id'] !== '';
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

        $destination = ltrim((string) $phone, '+');
        if (!preg_match('/^961[0-9]{7,8}$/', $destination)) {
            return new WP_Error('bsb_send_failed', __('The SMS destination is invalid.', 'rafflelb-auth'));
        }

        $payload = array(
            'api_key' => $credentials['api_key'],
            'api_secret' => $credentials['api_secret'],
            'senderid' => $credentials['sender_id'],
            'destination' => $destination,
            'message' => (string) $message,
        );

        // PROXIREACH / Best SMS Bulk "Send SMS (Text HTTP)" endpoint.
        // Passing an array to wp_remote_post sends application/x-www-form-urlencoded,
        // matching the provider's documented PHP example.
        $response = wp_remote_post(self::ENDPOINT, array(
            'timeout' => 10,
            'redirection' => 0,
            'sslverify' => true,
            'headers' => array(
                'Accept' => 'text/plain',
            ),
            'body' => $payload,
        ));

        if (is_wp_error($response)) {
            return new WP_Error('bsb_network_error', __('Provider network error.', 'rafflelb-auth'));
        }

        $http_status = (int) wp_remote_retrieve_response_code($response);
        $body = trim((string) wp_remote_retrieve_body($response));

        if ($http_status >= 200 && $http_status < 300 && $this->is_success_response($body, $destination)) {
            return true;
        }

        return $this->safe_error($http_status, $body);
    }

    private function credentials() {
        $settings = wp_parse_args(get_option('rafflelb_auth_settings', array()), RaffleLB_Auth_Admin::defaults());
        return array(
            'api_key' => $this->credential('RAFFLELB_BSB_API_KEY', $settings, 'bsb_api_key'),
            'api_secret' => $this->credential('RAFFLELB_BSB_API_SECRET', $settings, 'bsb_api_secret'),
            'sender_id' => $this->credential('RAFFLELB_BSB_SENDER_ID', $settings, 'bsb_sender_id'),
        );
    }

    private function credential($constant_name, $settings, $setting_name) {
        if (defined($constant_name) && is_scalar(constant($constant_name)) && trim((string) constant($constant_name)) !== '') {
            return trim((string) constant($constant_name));
        }
        return isset($settings[$setting_name]) ? trim((string) $settings[$setting_name]) : '';
    }

    private function is_success_response($body, $destination) {
        if ($body === '') { return false; }

        // The provider documents: confirmationID;recipientNumber;numSMSParts.
        // In production the same successful reply can be wrapped in whitespace/HTML
        // or return the part count as a numeric decimal. Normalize first, then look
        // for the documented tuple anywhere in the response rather than requiring
        // the whole line to match one exact representation.
        $normalized = preg_replace('/^\xEF\xBB\xBF/', '', (string) $body);
        $normalized = html_entity_decode(wp_strip_all_tags($normalized), ENT_QUOTES, 'UTF-8');
        $normalized = trim($normalized);

        if (preg_match_all('/([A-Za-z0-9_-]+)\s*;\s*\+?([0-9]{7,15})\s*;\s*([0-9]+(?:\.[0-9]+)?)/', $normalized, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $recipient = preg_replace('/\D+/', '', (string) $match[2]);
                $parts = (float) $match[3];
                if ($match[1] !== '' && hash_equals($destination, $recipient) && $parts > 0) {
                    return true;
                }
            }
        }

        // Be tolerant of a JSON success envelope if the provider/account returns
        // one even though this endpoint is documented as plain text.
        $decoded = json_decode($normalized, true);
        if (is_array($decoded)) {
            $candidate = $decoded;
            if (isset($decoded[0]) && is_array($decoded[0])) { $candidate = $decoded[0]; }
            $status = isset($candidate['status']) ? (int) $candidate['status'] : 0;
            $message = isset($candidate['message']) ? strtolower((string) $candidate['message']) : '';
            if (in_array($status, array(200, 201), true) &&
                ($message === '' || strpos($message, 'success') !== false || strpos($message, 'queue') !== false || strpos($message, 'sent') !== false)) {
                return true;
            }
        }

        // Some BSB/PROXIREACH accounts return a short success acknowledgement
        // rather than the documented tuple. If HTTP itself succeeded, accept a
        // non-empty provider acknowledgement unless it clearly looks like an error.
        // This avoids telling the customer delivery failed after the SMS was in fact
        // accepted, while still rejecting common provider failure wording.
        $lower = strtolower($normalized);
        $error_markers = array(
            'error', 'fail', 'invalid', 'unauthor', 'not authoriz', 'no credits',
            'insufficient', 'empty', 'cannot use', 'reject', 'denied', 'blocked'
        );
        foreach ($error_markers as $marker) {
            if (strpos($lower, $marker) !== false) { return false; }
        }

        return $normalized !== '';
    }

    private function safe_error($http_status, $body) {
        $summary = strtolower(trim((string) $body));

        if ($http_status === 401 || $http_status === 403 ||
            strpos($summary, 'invalid api credentials') !== false ||
            strpos($summary, 'invalid api key') !== false ||
            strpos($summary, 'api_key field is empty') !== false ||
            strpos($summary, 'api_secret field is empty') !== false) {
            return new WP_Error('bsb_auth_error', __('Provider authentication failed.', 'rafflelb-auth'));
        }
        if (strpos($summary, 'senderid is not authorized') !== false ||
            (strpos($summary, 'sender') !== false && strpos($summary, 'authoriz') !== false)) {
            return new WP_Error('bsb_sender_error', __('Sender ID is not authorized.', 'rafflelb-auth'));
        }
        if (strpos($summary, 'no credits') !== false || strpos($summary, 'insufficient') !== false ||
            strpos($summary, 'balance') !== false || strpos($summary, 'error 101') !== false) {
            return new WP_Error('bsb_no_credits', __('Insufficient SMS credits.', 'rafflelb-auth'));
        }
        if (strpos($summary, 'api key is set, you cannot use this api link') !== false) {
            return new WP_Error('bsb_api_mode_error', __('The SMS provider account is not enabled for the configured API method.', 'rafflelb-auth'));
        }
        if (strpos($summary, 'message field is empty') !== false || strpos($summary, 'destination field is empty') !== false) {
            return new WP_Error('bsb_send_failed', __('The SMS provider rejected the message.', 'rafflelb-auth'));
        }
        if ($http_status < 200 || $http_status >= 300) {
            return new WP_Error('bsb_network_error', __('Provider network error.', 'rafflelb-auth'));
        }
        return new WP_Error('bsb_invalid_response', __('Provider returned an unexpected response.', 'rafflelb-auth'));
    }
}
