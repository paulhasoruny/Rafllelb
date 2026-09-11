<?php

namespace RaffleLB\AI_Assistant;

if (!defined('ABSPATH')) {
    exit;
}

interface Provider {
    /**
     * @return string|\WP_Error
     */
    public function chat($instructions, array $messages, Tools $tools, $safety_identifier = '');

    /**
     * @return true|\WP_Error
     */
    public function test_connection();
}

