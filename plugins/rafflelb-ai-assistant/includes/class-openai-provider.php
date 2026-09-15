<?php

namespace RaffleLB\AI_Assistant;

if (!defined('ABSPATH')) {
    exit;
}

final class OpenAI_Provider implements Provider {
    const ENDPOINT = 'https://api.openai.com/v1/responses';
    const MAX_TOOL_CALLS = 4;
    const MAX_OUTPUT_TOKENS = 700;
    const REQUEST_TIMEOUT = 20;

    public function chat($instructions, array $messages, Tools $tools, $safety_identifier = '') {
        $input = array();
        foreach ($messages as $message) {
            $input[] = array(
                'role'    => $message['role'],
                'content' => $message['content'],
            );
        }

        $tool_calls = 0;
        $last_personal_tool = '';
        $last_personal_result = array();
        for ($round = 0; $round <= self::MAX_TOOL_CALLS; $round++) {
            $body = array(
                'model'               => Settings::model(),
                'instructions'        => (string) $instructions,
                'input'               => $input,
                'tools'               => $tools->definitions(),
                'tool_choice'         => 'auto',
                'parallel_tool_calls' => false,
                'max_output_tokens'   => self::MAX_OUTPUT_TOKENS,
                'reasoning'           => array('effort' => 'none'),
                'store'               => false,
            );

            if ($safety_identifier !== '') {
                $body['safety_identifier'] = substr((string) $safety_identifier, 0, 64);
            }

            $response = $this->request($body);
            if (is_wp_error($response)) {
                $fallback = $last_personal_tool !== '' ? $tools->personal_fallback_text($last_personal_tool, $last_personal_result) : '';
                return $fallback !== '' ? $fallback : $response;
            }

            $output = isset($response['output']) && is_array($response['output']) ? $response['output'] : array();
            $calls = array();
            foreach ($output as $item) {
                if (is_array($item) && isset($item['type']) && $item['type'] === 'function_call') {
                    $calls[] = $item;
                }
            }

            if (!$calls) {
                $text = $this->extract_text($output);
                if ($text !== '') {
                    return $text;
                }
                $fallback = $last_personal_tool !== '' ? $tools->personal_fallback_text($last_personal_tool, $last_personal_result) : '';
                return $fallback !== '' ? $fallback : new \WP_Error('rafflelb_ai_empty_response', 'The AI provider returned no usable text.');
            }

            if ($tool_calls + count($calls) > self::MAX_TOOL_CALLS) {
                $fallback = $last_personal_tool !== '' ? $tools->personal_fallback_text($last_personal_tool, $last_personal_result) : '';
                return $fallback !== '' ? $fallback : new \WP_Error('rafflelb_ai_tool_limit', 'The read-only tool-call limit was reached.');
            }

            // The Responses API expects its output items to be carried forward
            // before matching function_call_output items are appended.
            foreach ($output as $item) {
                if (is_array($item)) {
                    $input[] = $item;
                }
            }

            foreach ($calls as $call) {
                $tool_calls++;
                $call_id = isset($call['call_id']) && is_string($call['call_id']) ? $call['call_id'] : '';
                $name = isset($call['name']) && is_string($call['name']) ? $call['name'] : '';
                $arguments_json = isset($call['arguments']) && is_string($call['arguments']) ? $call['arguments'] : '';
                $arguments = json_decode($arguments_json, true);

                if ($call_id === '' || !is_array($arguments)) {
                    $result = array('ok' => false, 'error' => 'The tool arguments were invalid.');
                } else {
                    $result = $tools->execute($name, $arguments);
                }

                if (in_array($name, array('get_my_raffles', 'get_my_orders', 'get_my_raffle_points'), true) && is_array($result)) {
                    $last_personal_tool = $name;
                    $last_personal_result = $result;
                }

                $input[] = array(
                    'type'    => 'function_call_output',
                    'call_id' => $call_id,
                    'output'  => wp_json_encode($result, JSON_UNESCAPED_SLASHES),
                );
            }
        }

        $fallback = $last_personal_tool !== '' ? $tools->personal_fallback_text($last_personal_tool, $last_personal_result) : '';
        return $fallback !== '' ? $fallback : new \WP_Error('rafflelb_ai_tool_limit', 'The read-only tool-call limit was reached.');
    }

    public function test_connection() {
        $response = $this->request(array(
            'model'             => Settings::model(),
            'instructions'      => 'This is a controlled connection test. Reply with exactly OK.',
            'input'             => 'Connection test.',
            'max_output_tokens' => 24,
            'reasoning'         => array('effort' => 'none'),
            'store'             => false,
        ));

        return is_wp_error($response) ? $response : true;
    }

    private function request(array $body) {
        $key = Settings::api_key();
        if ($key === '') {
            return new \WP_Error('rafflelb_ai_missing_key', 'The AI provider is not configured.');
        }

        $response = wp_remote_post(self::ENDPOINT, array(
            'timeout'     => self::REQUEST_TIMEOUT,
            'redirection' => 0,
            'headers'     => array(
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
            ),
            'body'        => wp_json_encode($body, JSON_UNESCAPED_SLASHES),
            'data_format' => 'body',
        ));

        if (is_wp_error($response)) {
            return new \WP_Error('rafflelb_ai_provider_unavailable', 'The AI provider could not be reached.');
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            return new \WP_Error('rafflelb_ai_provider_error', 'The AI provider request failed.');
        }

        $decoded = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($decoded) || empty($decoded['output']) || !is_array($decoded['output'])) {
            return new \WP_Error('rafflelb_ai_invalid_provider_response', 'The AI provider returned an invalid response.');
        }

        return $decoded;
    }

    private function extract_text(array $output) {
        $parts = array();
        foreach ($output as $item) {
            if (!is_array($item) || !isset($item['type']) || $item['type'] !== 'message' || empty($item['content']) || !is_array($item['content'])) {
                continue;
            }
            foreach ($item['content'] as $content) {
                if (is_array($content) && isset($content['type'], $content['text']) && $content['type'] === 'output_text' && is_string($content['text'])) {
                    $parts[] = $content['text'];
                }
            }
        }
        return trim(implode("\n", $parts));
    }
}

