<?php

namespace RaffleLB\AI_Assistant;

if (!defined('ABSPATH')) {
    exit;
}

final class Settings {
    const OPTION = 'rafflelb_ai_assistant_settings';
    const PAGE_SLUG = 'rafflelb-ai-assistant';
    private static $admin_hook = '';

    public static function boot() {
        add_action('admin_menu', array(__CLASS__, 'admin_menu'), 99);
        add_action('admin_init', array(__CLASS__, 'maybe_migrate'), 5);
        add_action('admin_init', array(__CLASS__, 'register'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'admin_assets'));
        add_action('wp_ajax_rafflelb_ai_test_connection', array(__CLASS__, 'ajax_test_connection'));
    }

    public static function activate() {
        if (get_option(self::OPTION, null) === null) {
            add_option(self::OPTION, self::defaults(), '', 'no');
        }
    }

    public static function defaults() {
        return array(
            'enabled'            => 0,
            'api_key'            => '',
            'model'              => 'gpt-5.6-luna',
            'welcome_heading'    => "Hi 👋\nHow can I help you today?",
            'welcome_description'=> 'Get quick answers about raffles, orders, shopping, or anything else.',
            'quick_questions'    => array(
                array(
                    'label'  => 'How do raffles work?',
                    'prompt' => 'How do raffles work?',
                ),
                array(
                    'label'  => 'My entries',
                    'prompt' => 'How can I get help with my entries?',
                ),
                array(
                    'label'  => 'Orders & delivery',
                    'prompt' => 'What should I know about orders and delivery?',
                ),
            ),
            'daily_ceiling'      => 500,
            'rate_ip_limit'      => 10,
            'rate_session_limit' => 15,
            'rate_window'        => 300,
            'knowledge'          => self::starter_knowledge(),
        );
    }

    public static function all() {
        $saved = get_option(self::OPTION, array());
        $saved = is_array($saved) ? $saved : array();
        $saved = self::reconcile_legacy($saved);
        $defaults = self::defaults();
        $settings = wp_parse_args($saved, $defaults);
        $stored_questions = isset($settings['quick_questions']) && is_array($settings['quick_questions']) ? $settings['quick_questions'] : array();
        $settings['quick_questions'] = array();
        foreach ($defaults['quick_questions'] as $index => $default_question) {
            $question = isset($stored_questions[$index]) && is_array($stored_questions[$index]) ? $stored_questions[$index] : $default_question;
            $settings['quick_questions'][$index] = array(
                'label'  => self::truncate(sanitize_text_field(isset($question['label']) ? $question['label'] : ''), 60),
                'prompt' => self::truncate(sanitize_text_field(isset($question['prompt']) ? $question['prompt'] : ''), 250),
            );
        }
        $settings['knowledge'] = wp_parse_args(
            isset($settings['knowledge']) && is_array($settings['knowledge']) ? $settings['knowledge'] : array(),
            self::starter_knowledge()
        );
        return $settings;
    }

    public static function maybe_migrate() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $saved = get_option(self::OPTION, null);
        if (!is_array($saved)) {
            return;
        }
        $migrated = self::reconcile_legacy($saved);
        if ($migrated !== $saved) {
            update_option(self::OPTION, $migrated, false);
        }
    }

    public static function frontend_quick_questions() {
        $settings = self::all();
        $questions = array();
        foreach ($settings['quick_questions'] as $index => $question) {
            $label = isset($question['label']) ? trim((string) $question['label']) : '';
            $prompt = isset($question['prompt']) ? trim((string) $question['prompt']) : '';
            if ($label === '' && $prompt === '') {
                continue;
            }
            if ($label === '') {
                $label = self::truncate($prompt, 60);
            }
            if ($prompt === '') {
                $prompt = $label;
            }
            $questions[] = array(
                'index'  => (int) $index,
                'label'  => $label,
                'prompt' => $prompt,
            );
        }
        return $questions;
    }

    public static function enabled() {
        $settings = self::all();
        return !empty($settings['enabled']);
    }

    public static function api_key() {
        if (defined('RAFFLELB_AI_OPENAI_API_KEY') && is_string(RAFFLELB_AI_OPENAI_API_KEY) && trim(RAFFLELB_AI_OPENAI_API_KEY) !== '') {
            return trim(RAFFLELB_AI_OPENAI_API_KEY);
        }
        $settings = self::all();
        return isset($settings['api_key']) && is_string($settings['api_key']) ? trim($settings['api_key']) : '';
    }

    public static function model() {
        if (defined('RAFFLELB_AI_OPENAI_MODEL') && is_string(RAFFLELB_AI_OPENAI_MODEL) && trim(RAFFLELB_AI_OPENAI_MODEL) !== '') {
            return trim(RAFFLELB_AI_OPENAI_MODEL);
        }
        $settings = self::all();
        return isset($settings['model']) && is_string($settings['model']) && $settings['model'] !== '' ? $settings['model'] : 'gpt-5.6-luna';
    }

    public static function register() {
        register_setting('rafflelb_ai_assistant', self::OPTION, array(
            'type'              => 'array',
            'sanitize_callback' => array(__CLASS__, 'sanitize'),
            'default'           => self::defaults(),
        ));
    }

    public static function sanitize($input) {
        if (!current_user_can('manage_options')) {
            return self::all();
        }

        $input = is_array($input) ? $input : array();
        $old = self::all();
        $clean = self::defaults();
        $clean['enabled'] = empty($input['enabled']) ? 0 : 1;

        $submitted_key = '';
        if (isset($input['api_key']) && is_string($input['api_key'])) {
            $submitted_key = trim(wp_unslash($input['api_key']));
        }
        $clean['api_key'] = $submitted_key === '' ? (string) $old['api_key'] : $submitted_key;

        $model = isset($input['model']) ? sanitize_text_field(wp_unslash($input['model'])) : (string) $old['model'];
        $clean['model'] = preg_match('/^[A-Za-z0-9._:-]{1,100}$/', $model) ? $model : 'gpt-5.6-luna';
        $welcome_heading = isset($input['welcome_heading']) ? sanitize_textarea_field(wp_unslash($input['welcome_heading'])) : (string) $old['welcome_heading'];
        $welcome_description = isset($input['welcome_description']) ? sanitize_textarea_field(wp_unslash($input['welcome_description'])) : (string) $old['welcome_description'];
        $clean['welcome_heading'] = self::truncate($welcome_heading, 150);
        $clean['welcome_description'] = self::truncate($welcome_description, 300);

        $submitted_questions = isset($input['quick_questions']) && is_array($input['quick_questions']) ? $input['quick_questions'] : array();
        foreach ($clean['quick_questions'] as $index => $default_question) {
            $old_question = isset($old['quick_questions'][$index]) && is_array($old['quick_questions'][$index]) ? $old['quick_questions'][$index] : $default_question;
            $submitted_question = isset($submitted_questions[$index]) && is_array($submitted_questions[$index]) ? $submitted_questions[$index] : $old_question;
            $label = isset($submitted_question['label']) ? sanitize_text_field(wp_unslash($submitted_question['label'])) : '';
            $prompt = isset($submitted_question['prompt']) ? sanitize_text_field(wp_unslash($submitted_question['prompt'])) : '';
            $clean['quick_questions'][$index] = array(
                'label'  => self::truncate($label, 60),
                'prompt' => self::truncate($prompt, 250),
            );
        }

        $clean['daily_ceiling'] = min(100000, absint($input['daily_ceiling'] ?? 500));
        $clean['rate_ip_limit'] = max(1, min(1000, absint($input['rate_ip_limit'] ?? 10)));
        $clean['rate_session_limit'] = max(1, min(1000, absint($input['rate_session_limit'] ?? 15)));
        $clean['rate_window'] = max(60, min(3600, absint($input['rate_window'] ?? 300)));

        $submitted_knowledge = isset($input['knowledge']) && is_array($input['knowledge']) ? $input['knowledge'] : array();
        foreach (self::knowledge_sections() as $key => $label) {
            $value = isset($submitted_knowledge[$key]) ? sanitize_textarea_field(wp_unslash($submitted_knowledge[$key])) : '';
            $clean['knowledge'][$key] = self::truncate($value, 3000);
        }

        return $clean;
    }

    public static function admin_menu() {
        self::$admin_hook = add_menu_page(
            __('RaffleLB AI Assistant', 'rafflelb-ai-assistant'),
            __('RaffleLB AI Assistant', 'rafflelb-ai-assistant'),
            'manage_options',
            self::PAGE_SLUG,
            array(__CLASS__, 'render_page'),
            'dashicons-format-chat',
            56
        );
    }

    public static function admin_assets($hook) {
        if ($hook !== self::$admin_hook) {
            return;
        }
        wp_enqueue_style('rafflelb-ai-admin', RAFFLELB_AI_ASSISTANT_URL . 'assets/css/admin.css', array(), RAFFLELB_AI_ASSISTANT_VERSION);
        wp_enqueue_script('rafflelb-ai-admin', RAFFLELB_AI_ASSISTANT_URL . 'assets/js/admin.js', array(), RAFFLELB_AI_ASSISTANT_VERSION, true);
        wp_localize_script('rafflelb-ai-admin', 'RaffleLBAIAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('rafflelb_ai_test_connection'),
        ));
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage these settings.', 'rafflelb-ai-assistant'));
        }
        $settings = self::all();
        ?>
        <div class="wrap rlb-ai-admin">
            <div class="rlb-ai-admin__hero">
                <span class="rlb-ai-admin__eyebrow">RaffleLB</span>
                <h1><?php echo esc_html__('AI Assistant', 'rafflelb-ai-assistant'); ?></h1>
                <p><?php echo esc_html__('Configure the read-only customer assistant, controlled knowledge, and abuse limits.', 'rafflelb-ai-assistant'); ?></p>
            </div>
            <?php settings_errors(); ?>
            <form method="post" action="options.php">
                <?php settings_fields('rafflelb_ai_assistant'); ?>
                <section class="rlb-ai-admin__card">
                    <h2><?php echo esc_html__('Assistant', 'rafflelb-ai-assistant'); ?></h2>
                    <label class="rlb-ai-toggle">
                        <input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[enabled]" value="1" <?php checked(!empty($settings['enabled'])); ?>>
                        <span><?php echo esc_html__('Assistant enabled', 'rafflelb-ai-assistant'); ?></span>
                    </label>
                    <label class="rlb-ai-field">
                        <span><?php echo esc_html__('Welcome heading', 'rafflelb-ai-assistant'); ?></span>
                        <textarea rows="2" maxlength="150" name="<?php echo esc_attr(self::OPTION); ?>[welcome_heading]"><?php echo esc_textarea($settings['welcome_heading']); ?></textarea>
                    </label>
                    <label class="rlb-ai-field">
                        <span><?php echo esc_html__('Welcome description', 'rafflelb-ai-assistant'); ?></span>
                        <textarea rows="3" maxlength="300" name="<?php echo esc_attr(self::OPTION); ?>[welcome_description]"><?php echo esc_textarea($settings['welcome_description']); ?></textarea>
                    </label>

                    <h3><?php echo esc_html__('Opening quick questions', 'rafflelb-ai-assistant'); ?></h3>
                    <p class="description"><?php echo esc_html__('Configure up to three plain-text opening shortcuts. Leave both fields blank to hide a row. Do not enter customer data, ticket numbers, or tool instructions.', 'rafflelb-ai-assistant'); ?></p>
                    <?php foreach ($settings['quick_questions'] as $index => $question) : ?>
                        <h4><?php echo esc_html(sprintf(__('Quick Question %d', 'rafflelb-ai-assistant'), $index + 1)); ?></h4>
                        <div class="rlb-ai-admin__grid">
                            <label class="rlb-ai-field">
                                <span><?php echo esc_html__('Visible label', 'rafflelb-ai-assistant'); ?></span>
                                <input type="text" maxlength="60" name="<?php echo esc_attr(self::OPTION); ?>[quick_questions][<?php echo esc_attr($index); ?>][label]" value="<?php echo esc_attr($question['label']); ?>">
                            </label>
                            <label class="rlb-ai-field">
                                <span><?php echo esc_html__('Prompt sent to assistant', 'rafflelb-ai-assistant'); ?></span>
                                <input type="text" maxlength="250" name="<?php echo esc_attr(self::OPTION); ?>[quick_questions][<?php echo esc_attr($index); ?>][prompt]" value="<?php echo esc_attr($question['prompt']); ?>">
                            </label>
                        </div>
                    <?php endforeach; ?>
                </section>

                <section class="rlb-ai-admin__card">
                    <h2><?php echo esc_html__('OpenAI', 'rafflelb-ai-assistant'); ?></h2>
                    <p class="description">
                        <?php echo self::api_key() !== '' ? esc_html__('API key is configured.', 'rafflelb-ai-assistant') : esc_html__('No API key is configured.', 'rafflelb-ai-assistant'); ?>
                        <?php if (defined('RAFFLELB_AI_OPENAI_API_KEY') && trim((string) RAFFLELB_AI_OPENAI_API_KEY) !== '') : ?>
                            <?php echo esc_html__(' The wp-config constant is taking precedence.', 'rafflelb-ai-assistant'); ?>
                        <?php endif; ?>
                    </p>
                    <label class="rlb-ai-field">
                        <span><?php echo esc_html__('OpenAI API key', 'rafflelb-ai-assistant'); ?></span>
                        <input type="password" autocomplete="new-password" value="" name="<?php echo esc_attr(self::OPTION); ?>[api_key]" placeholder="<?php echo esc_attr__('Leave blank to preserve the configured key', 'rafflelb-ai-assistant'); ?>">
                    </label>
                    <label class="rlb-ai-field">
                        <span><?php echo esc_html__('Model', 'rafflelb-ai-assistant'); ?></span>
                        <input type="text" maxlength="100" value="<?php echo esc_attr($settings['model']); ?>" name="<?php echo esc_attr(self::OPTION); ?>[model]" <?php disabled(defined('RAFFLELB_AI_OPENAI_MODEL') && trim((string) RAFFLELB_AI_OPENAI_MODEL) !== ''); ?>>
                    </label>
                    <?php if (defined('RAFFLELB_AI_OPENAI_MODEL') && trim((string) RAFFLELB_AI_OPENAI_MODEL) !== '') : ?>
                        <p class="description"><?php echo esc_html__('The model is currently overridden by RAFFLELB_AI_OPENAI_MODEL.', 'rafflelb-ai-assistant'); ?></p>
                    <?php endif; ?>
                    <button type="button" class="button" id="rlb-ai-test-connection"><?php echo esc_html__('Connection Test', 'rafflelb-ai-assistant'); ?></button>
                    <span id="rlb-ai-test-result" class="rlb-ai-test-result" role="status" aria-live="polite"></span>
                </section>

                <section class="rlb-ai-admin__card">
                    <h2><?php echo esc_html__('Cost and abuse controls', 'rafflelb-ai-assistant'); ?></h2>
                    <div class="rlb-ai-admin__grid">
                        <?php self::number_field('daily_ceiling', __('Daily request ceiling (0 = unlimited)', 'rafflelb-ai-assistant'), $settings['daily_ceiling'], 0, 100000); ?>
                        <?php self::number_field('rate_ip_limit', __('Requests per IP/window', 'rafflelb-ai-assistant'), $settings['rate_ip_limit'], 1, 1000); ?>
                        <?php self::number_field('rate_session_limit', __('Requests per browser session/window', 'rafflelb-ai-assistant'), $settings['rate_session_limit'], 1, 1000); ?>
                        <?php self::number_field('rate_window', __('Rate window (seconds)', 'rafflelb-ai-assistant'), $settings['rate_window'], 60, 3600); ?>
                    </div>
                </section>

                <section class="rlb-ai-admin__card">
                    <h2><?php echo esc_html__('Knowledge base', 'rafflelb-ai-assistant'); ?></h2>
                    <p class="description"><?php echo esc_html__('Plain-text business knowledge only. It is passed to the model as untrusted reference data, never executed as code or treated as higher-priority instructions.', 'rafflelb-ai-assistant'); ?></p>
                    <?php foreach (self::knowledge_sections() as $key => $label) : ?>
                        <label class="rlb-ai-field">
                            <span><?php echo esc_html($label); ?></span>
                            <textarea rows="5" maxlength="3000" name="<?php echo esc_attr(self::OPTION); ?>[knowledge][<?php echo esc_attr($key); ?>]"><?php echo esc_textarea($settings['knowledge'][$key]); ?></textarea>
                        </label>
                    <?php endforeach; ?>
                </section>
                <?php submit_button(__('Save AI Assistant settings', 'rafflelb-ai-assistant')); ?>
            </form>
        </div>
        <?php
    }

    private static function number_field($key, $label, $value, $min, $max) {
        ?>
        <label class="rlb-ai-field">
            <span><?php echo esc_html($label); ?></span>
            <input type="number" min="<?php echo esc_attr($min); ?>" max="<?php echo esc_attr($max); ?>" name="<?php echo esc_attr(self::OPTION); ?>[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($value); ?>">
        </label>
        <?php
    }

    public static function ajax_test_connection() {
        check_ajax_referer('rafflelb_ai_test_connection', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Connection failed.', 'rafflelb-ai-assistant')), 403);
        }

        $result = (new OpenAI_Provider())->test_connection();
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => __('Connection failed.', 'rafflelb-ai-assistant')), 502);
        }
        wp_send_json_success(array('message' => __('Connected successfully', 'rafflelb-ai-assistant')));
    }

    public static function knowledge_sections() {
        return array(
            'general'       => __('General', 'rafflelb-ai-assistant'),
            'how_it_works'  => __('How RaffleLB Works', 'rafflelb-ai-assistant'),
            'shopping'      => __('Shopping', 'rafflelb-ai-assistant'),
            'raffles'       => __('Raffles', 'rafflelb-ai-assistant'),
            'points'        => __('Raffle Points', 'rafflelb-ai-assistant'),
            'account'       => __('Account & Registration', 'rafflelb-ai-assistant'),
            'payments'      => __('Payments', 'rafflelb-ai-assistant'),
            'delivery'      => __('Delivery', 'rafflelb-ai-assistant'),
            'returns'       => __('Returns / Refunds', 'rafflelb-ai-assistant'),
            'winners'       => __('Winners', 'rafflelb-ai-assistant'),
            'contact'       => __('Contact / Support', 'rafflelb-ai-assistant'),
            'additional_faq'=> __('Additional FAQ', 'rafflelb-ai-assistant'),
        );
    }

    private static function starter_knowledge() {
        return array(
            'general'        => 'RaffleLB combines a public online shop with customer-facing raffle opportunities. Use the live product tools for current product details.',
            'how_it_works'   => 'Customers can browse public products and raffles. Purchases and raffle participation must be completed through the relevant RaffleLB website pages.',
            'shopping'       => 'Product prices, availability, and purchase options can change. Use live product information and direct customers to the product page to confirm important details.',
            'raffles'        => 'Only valid active paid entries count toward a raffle. Describe the published customer-facing process and official rules; do not discuss internal administration or security mechanisms.',
            'points'         => 'Raffle Points information shown in a customer account is private. Direct customers to My Account for their balance and to published help content for general explanations.',
            'account'        => 'Customers should use My Account to register, sign in, update account details, or review their own information. The assistant cannot access or change account data.',
            'payments'       => 'Customers should use the checkout pages for available payment methods. The assistant cannot take payments, inspect payment details, or change an order.',
            'delivery'       => 'Delivery terms may vary by product and destination. Direct customers to the product page, published delivery policy, or support when the knowledge base does not provide a confirmed answer.',
            'returns'        => 'Returns and refunds are governed by the published policy and the circumstances of the purchase. The assistant cannot cancel or refund an order.',
            'winners'        => 'Use only official published winner information. Never invent a winner or disclose private winner or entry-owner information.',
            'contact'        => 'For account-specific, payment, order, or unresolved questions, direct the customer to the public Contact or Support page when one can be located.',
            'additional_faq' => 'Add administrator-approved customer-facing answers here.',
        );
    }

    private static function reconcile_legacy($saved) {
        $defaults = self::defaults();
        if (!array_key_exists('welcome_heading', $saved)) {
            $saved['welcome_heading'] = $defaults['welcome_heading'];
        }
        if (!array_key_exists('welcome_description', $saved)) {
            $legacy = isset($saved['welcome_message']) ? sanitize_textarea_field((string) $saved['welcome_message']) : '';
            $legacy_default = 'Hi! Ask me about RaffleLB products, raffles, or how the site works.';
            $saved['welcome_description'] = $legacy !== '' && $legacy !== $legacy_default
                ? self::truncate($legacy, 300)
                : $defaults['welcome_description'];
        }
        if (!isset($saved['quick_questions']) || !is_array($saved['quick_questions'])) {
            $saved['quick_questions'] = $defaults['quick_questions'];
        }
        unset($saved['welcome_message']);
        return $saved;
    }

    private static function truncate($value, $length) {
        return function_exists('mb_substr') ? mb_substr((string) $value, 0, $length) : substr((string) $value, 0, $length);
    }
}
