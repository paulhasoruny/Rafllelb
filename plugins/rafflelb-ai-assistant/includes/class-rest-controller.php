<?php

namespace RaffleLB\AI_Assistant;

if (!defined('ABSPATH')) {
    exit;
}

final class REST_Controller {
    const NAMESPACE = 'rafflelb-ai/v1';
    const MAX_MESSAGE_LENGTH = 1500;
    const MAX_HISTORY_MESSAGES = 12;
    const MAX_HISTORY_CONTENT_LENGTH = 1500;
    const MAX_TOTAL_HISTORY_LENGTH = 9000;
    const MAX_REQUEST_BYTES = 25000;

    private $provider;
    private $tools;

    public function __construct(Provider $provider, Tools $tools) {
        $this->provider = $provider;
        $this->tools = $tools;
    }

    public function boot() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes() {
        register_rest_route(self::NAMESPACE, '/chat', array(
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => array($this, 'chat'),
            'permission_callback' => '__return_true',
        ));
    }

    public function chat(\WP_REST_Request $request) {
        if (!Settings::enabled()) {
            return $this->error('rafflelb_ai_disabled', __('The assistant is currently unavailable.', 'rafflelb-ai-assistant'), 503);
        }

        $content_length = (int) $request->get_header('Content-Length');
        if ($content_length > self::MAX_REQUEST_BYTES || strlen((string) $request->get_body()) > self::MAX_REQUEST_BYTES) {
            return $this->error('rafflelb_ai_request_too_large', __('Please send a shorter message.', 'rafflelb-ai-assistant'), 413);
        }

        $params = $request->get_json_params();
        if (!is_array($params)) {
            return $this->error('rafflelb_ai_invalid_request', __('Please send a valid message.', 'rafflelb-ai-assistant'), 400);
        }

        $message = $this->clean_message($params['message'] ?? null, self::MAX_MESSAGE_LENGTH);
        if (is_wp_error($message) || $message === '') {
            return $this->error('rafflelb_ai_invalid_message', __('Please enter a shorter valid message.', 'rafflelb-ai-assistant'), 400);
        }

        $history = $this->validate_history($params['history'] ?? array());
        if (is_wp_error($history)) {
            return $this->error('rafflelb_ai_invalid_history', __('The conversation context was invalid.', 'rafflelb-ai-assistant'), 400);
        }

        $identity = $this->request_identity($request);
        $rate_result = $this->enforce_rate_limits($identity);
        if (is_wp_error($rate_result)) {
            return $this->error('rafflelb_ai_rate_limited', __('Too many requests. Please wait and try again.', 'rafflelb-ai-assistant'), 429);
        }

        if (Settings::api_key() === '') {
            return $this->error('rafflelb_ai_unconfigured', __('The assistant is currently unavailable.', 'rafflelb-ai-assistant'), 503);
        }

        if (!$this->consume_daily_request()) {
            return $this->error('rafflelb_ai_daily_limit', __('The assistant has reached today’s request limit. Please try again later.', 'rafflelb-ai-assistant'), 429);
        }

        $messages = $history;
        $messages[] = array('role' => 'user', 'content' => $message);
        $answer = $this->provider->chat(
            Knowledge_Base::system_instructions(),
            $messages,
            $this->tools,
            hash('sha256', $identity['ip_hash'] . '|' . $identity['session_hash'])
        );

        if (is_wp_error($answer)) {
            return $this->error('rafflelb_ai_unavailable', __('I’m sorry, the assistant could not respond right now. Please try again later.', 'rafflelb-ai-assistant'), 502);
        }

        $answer = trim(wp_check_invalid_utf8((string) $answer, true));
        if ($answer === '') {
            return $this->error('rafflelb_ai_empty', __('I’m sorry, the assistant could not respond right now. Please try again later.', 'rafflelb-ai-assistant'), 502);
        }

        $response = new \WP_REST_Response(array('message' => $answer), 200);
        $response->header('Cache-Control', 'no-store, private');
        $response->header('X-Content-Type-Options', 'nosniff');
        return $response;
    }

    private function validate_history($history) {
        if (!is_array($history) || count($history) > self::MAX_HISTORY_MESSAGES) {
            return new \WP_Error('invalid_history');
        }

        $validated = array();
        $total_length = 0;
        foreach ($history as $item) {
            if (!is_array($item) || !isset($item['role'], $item['content']) || !is_string($item['role']) || !in_array($item['role'], array('user', 'assistant'), true)) {
                return new \WP_Error('invalid_history');
            }
            $content = $this->clean_message($item['content'], self::MAX_HISTORY_CONTENT_LENGTH);
            if (is_wp_error($content) || $content === '') {
                return new \WP_Error('invalid_history');
            }
            $total_length += $this->length($content);
            if ($total_length > self::MAX_TOTAL_HISTORY_LENGTH) {
                return new \WP_Error('invalid_history');
            }
            $validated[] = array('role' => $item['role'], 'content' => $content);
        }
        return $validated;
    }

    private function clean_message($value, $maximum) {
        if (!is_string($value)) {
            return new \WP_Error('invalid_message');
        }
        $value = trim(wp_check_invalid_utf8($value, true));
        if ($value === '' || $this->length($value) > $maximum) {
            return new \WP_Error('invalid_message');
        }
        // Preserve plain conversational punctuation while removing tags and
        // control characters. This content is still treated as untrusted.
        $value = wp_strip_all_tags($value, true);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
        return is_string($value) ? trim($value) : new \WP_Error('invalid_message');
    }

    private function request_identity(\WP_REST_Request $request) {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            $ip = 'unknown';
        }
        $session = (string) $request->get_header('X-RaffleLB-AI-Session');
        if (!preg_match('/^[A-Za-z0-9_-]{16,64}$/', $session)) {
            $session = '';
        }
        $salt = wp_salt('auth');
        return array(
            'ip_hash'      => hash_hmac('sha256', $ip, $salt),
            'session_hash' => $session === '' ? '' : hash_hmac('sha256', $session, $salt),
        );
    }

    private function enforce_rate_limits(array $identity) {
        $settings = Settings::all();
        $window = max(60, min(3600, absint($settings['rate_window'])));
        $ip_limit = max(1, min(1000, absint($settings['rate_ip_limit'])));
        $session_limit = max(1, min(1000, absint($settings['rate_session_limit'])));

        if (!$this->consume_window('ip_' . $identity['ip_hash'], $ip_limit, $window)) {
            return new \WP_Error('rate_limited');
        }
        if ($identity['session_hash'] !== '' && !$this->consume_window('session_' . $identity['session_hash'], $session_limit, $window)) {
            return new \WP_Error('rate_limited');
        }
        return true;
    }

    private function consume_window($identity, $limit, $window) {
        $bucket = (int) floor(time() / $window);
        $key = 'rlb_ai_rate_' . substr(hash('sha256', $identity . '|' . $bucket), 0, 40);
        $count = (int) get_transient($key);
        if ($count >= $limit) {
            return false;
        }
        set_transient($key, $count + 1, $window + 30);
        return true;
    }

    private function consume_daily_request() {
        $settings = Settings::all();
        $limit = absint($settings['daily_ceiling']);
        if ($limit === 0) {
            return true;
        }
        $date = wp_date('Y-m-d');
        $key = 'rlb_ai_daily_' . str_replace('-', '', $date);
        $count = (int) get_transient($key);
        if ($count >= $limit) {
            return false;
        }
        $expires = max(HOUR_IN_SECONDS, strtotime('tomorrow', current_time('timestamp')) - current_time('timestamp') + HOUR_IN_SECONDS);
        set_transient($key, $count + 1, $expires);
        return true;
    }

    private function error($code, $message, $status) {
        return new \WP_Error($code, $message, array('status' => $status));
    }

    private function length($value) {
        return function_exists('mb_strlen') ? mb_strlen((string) $value) : strlen((string) $value);
    }
}
