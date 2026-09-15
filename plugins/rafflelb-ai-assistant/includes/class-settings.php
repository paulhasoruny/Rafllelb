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
                    'icon'   => 'question',
                ),
                array(
                    'label'  => 'My entries',
                    'prompt' => 'How can I get help with my entries?',
                    'icon'   => 'ticket',
                ),
                array(
                    'label'  => 'Orders & delivery',
                    'prompt' => 'What should I know about orders and delivery?',
                    'icon'   => 'package',
                ),
            ),
            'daily_ceiling'      => 500,
            'rate_ip_limit'      => 10,
            'rate_session_limit' => 15,
            'rate_window'        => 300,
            'live_pages_enabled' => 1,
            'personal_assistance_enabled' => 1,
            'live_page_ids'      => array(),
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
            $icon = isset($question['icon']) ? sanitize_key((string) $question['icon']) : (isset($default_question['icon']) ? $default_question['icon'] : 'question');
            if (!array_key_exists($icon, self::quick_icon_choices())) {
                $icon = isset($default_question['icon']) ? $default_question['icon'] : 'question';
            }
            $settings['quick_questions'][$index] = array(
                'label'  => self::truncate(sanitize_text_field(isset($question['label']) ? $question['label'] : ''), 60),
                'prompt' => self::truncate(sanitize_text_field(isset($question['prompt']) ? $question['prompt'] : ''), 250),
                'icon'   => $icon,
            );
        }
        $settings['live_pages_enabled'] = empty($settings['live_pages_enabled']) ? 0 : 1;
        $settings['personal_assistance_enabled'] = empty($settings['personal_assistance_enabled']) ? 0 : 1;
        $settings['live_page_ids'] = self::sanitize_page_ids(isset($settings['live_page_ids']) ? $settings['live_page_ids'] : array());
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
                'icon'   => isset($question['icon']) ? (string) $question['icon'] : 'question',
            );
        }
        return $questions;
    }

    public static function enabled() {
        $settings = self::all();
        return !empty($settings['enabled']);
    }

    public static function personal_assistance_enabled() {
        $settings = self::all();
        return !empty($settings['personal_assistance_enabled']);
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
            $icon = isset($submitted_question['icon']) ? sanitize_key(wp_unslash($submitted_question['icon'])) : (isset($old_question['icon']) ? $old_question['icon'] : $default_question['icon']);
            if (!array_key_exists($icon, self::quick_icon_choices())) {
                $icon = isset($old_question['icon']) && array_key_exists($old_question['icon'], self::quick_icon_choices()) ? $old_question['icon'] : $default_question['icon'];
            }
            $clean['quick_questions'][$index] = array(
                'label'  => self::truncate($label, 60),
                'prompt' => self::truncate($prompt, 250),
                'icon'   => $icon,
            );
        }

        $clean['daily_ceiling'] = min(100000, absint($input['daily_ceiling'] ?? 500));
        $clean['rate_ip_limit'] = max(1, min(1000, absint($input['rate_ip_limit'] ?? 10)));
        $clean['rate_session_limit'] = max(1, min(1000, absint($input['rate_session_limit'] ?? 15)));
        $clean['rate_window'] = max(60, min(3600, absint($input['rate_window'] ?? 300)));
        $clean['live_pages_enabled'] = empty($input['live_pages_enabled']) ? 0 : 1;
        $clean['personal_assistance_enabled'] = empty($input['personal_assistance_enabled']) ? 0 : 1;
        $clean['live_page_ids'] = self::sanitize_page_ids(isset($input['live_page_ids']) ? $input['live_page_ids'] : array());

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
                        <div class="rlb-ai-admin__grid rlb-ai-admin__grid--quick">
                            <label class="rlb-ai-field">
                                <span><?php echo esc_html__('Visible label', 'rafflelb-ai-assistant'); ?></span>
                                <input type="text" maxlength="60" name="<?php echo esc_attr(self::OPTION); ?>[quick_questions][<?php echo esc_attr($index); ?>][label]" value="<?php echo esc_attr($question['label']); ?>">
                            </label>
                            <label class="rlb-ai-field">
                                <span><?php echo esc_html__('Prompt sent to assistant', 'rafflelb-ai-assistant'); ?></span>
                                <input type="text" maxlength="250" name="<?php echo esc_attr(self::OPTION); ?>[quick_questions][<?php echo esc_attr($index); ?>][prompt]" value="<?php echo esc_attr($question['prompt']); ?>">
                            </label>
                            <label class="rlb-ai-field">
                                <span><?php echo esc_html__('Icon', 'rafflelb-ai-assistant'); ?></span>
                                <select name="<?php echo esc_attr(self::OPTION); ?>[quick_questions][<?php echo esc_attr($index); ?>][icon]">
                                    <?php foreach (self::quick_icon_choices() as $icon_key => $icon_label) : ?>
                                        <option value="<?php echo esc_attr($icon_key); ?>" <?php selected($question['icon'], $icon_key); ?>><?php echo esc_html($icon_label); ?></option>
                                    <?php endforeach; ?>
                                </select>
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
                    <h2><?php echo esc_html__('Signed-in personal assistance', 'rafflelb-ai-assistant'); ?></h2>
                    <label class="rlb-ai-toggle">
                        <input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[personal_assistance_enabled]" value="1" <?php checked(!empty($settings['personal_assistance_enabled'])); ?>>
                        <span><?php echo esc_html__('Allow read-only help for the currently signed-in customer', 'rafflelb-ai-assistant'); ?></span>
                    </label>
                    <p class="description"><?php echo esc_html__('When enabled, authenticated customers can ask about only their own raffle entries/ticket numbers, recent order status, and Raffle Points balance. The assistant cannot modify anything and never exposes another customer, address, phone, email, or payment details.', 'rafflelb-ai-assistant'); ?></p>
                </section>

                <section class="rlb-ai-admin__card">
                    <h2><?php echo esc_html__('Live official website knowledge', 'rafflelb-ai-assistant'); ?></h2>
                    <label class="rlb-ai-toggle">
                        <input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[live_pages_enabled]" value="1" <?php checked(!empty($settings['live_pages_enabled'])); ?>>
                        <span><?php echo esc_html__('Use selected official WordPress pages as live AI knowledge', 'rafflelb-ai-assistant'); ?></span>
                    </label>
                    <p class="description"><?php echo esc_html__('Page text is read live for each assistant request, so published edits are reflected automatically on the next answer. Dynamic shortcodes/scripts are not executed and customer-specific page data is never read.', 'rafflelb-ai-assistant'); ?></p>
                    <?php $page_options = self::live_page_options(); ?>
                    <label class="rlb-ai-field">
                        <span><?php echo esc_html__('Official page sources', 'rafflelb-ai-assistant'); ?></span>
                        <select multiple size="10" name="<?php echo esc_attr(self::OPTION); ?>[live_page_ids][]">
                            <?php foreach ($page_options as $page_id => $page_title) : ?>
                                <option value="<?php echo esc_attr($page_id); ?>" <?php selected(in_array((int) $page_id, $settings['live_page_ids'], true)); ?>><?php echo esc_html($page_title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <p class="description"><?php echo esc_html__('Leave all pages unselected for automatic detection of official FAQ, raffle/selection rules, Contact/Support, Delivery/Returns, Terms, Privacy, and Refer & Earn pages. Select exact pages here if you want full control.', 'rafflelb-ai-assistant'); ?></p>
                    <?php if (empty($settings['live_page_ids']) && class_exists(Knowledge_Base::class)) : ?>
                        <?php $detected_ids = Knowledge_Base::discover_live_page_ids(); ?>
                        <?php if ($detected_ids) : ?>
                            <p class="description"><strong><?php echo esc_html__('Automatically detected now:', 'rafflelb-ai-assistant'); ?></strong>
                                <?php
                                $detected_titles = array();
                                foreach ($detected_ids as $detected_id) {
                                    $title = get_the_title($detected_id);
                                    if (is_string($title) && $title !== '') {
                                        $detected_titles[] = $title;
                                    }
                                }
                                echo esc_html(implode(', ', $detected_titles));
                                ?>
                            </p>
                        <?php endif; ?>
                    <?php endif; ?>
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

    public static function quick_icon_choices() {
        return array(
            'question'     => __('Question', 'rafflelb-ai-assistant'),
            'ticket'       => __('Ticket', 'rafflelb-ai-assistant'),
            'package'      => __('Package', 'rafflelb-ai-assistant'),
            'raffle'       => __('Raffle / R icon', 'rafflelb-ai-assistant'),
            'shopping_bag' => __('Shopping bag', 'rafflelb-ai-assistant'),
            'perfume'      => __('Perfume', 'rafflelb-ai-assistant'),
            'points'       => __('Raffle Points', 'rafflelb-ai-assistant'),
            'delivery'     => __('Delivery', 'rafflelb-ai-assistant'),
            'payment'      => __('Payment', 'rafflelb-ai-assistant'),
            'account'      => __('Account', 'rafflelb-ai-assistant'),
            'winners'      => __('Winners / trophy', 'rafflelb-ai-assistant'),
        );
    }

    public static function live_page_options() {
        $options = array();
        $pages = get_posts(array(
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => 250,
            'orderby'        => array('menu_order' => 'ASC', 'title' => 'ASC'),
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ));
        foreach ($pages as $page) {
            if (!isset($page->ID)) {
                continue;
            }
            $title = isset($page->post_title) ? trim((string) $page->post_title) : '';
            $options[(int) $page->ID] = $title !== '' ? $title : sprintf(__('Page #%d', 'rafflelb-ai-assistant'), (int) $page->ID);
        }
        return $options;
    }

    private static function sanitize_page_ids($ids) {
        if (!is_array($ids)) {
            return array();
        }
        $clean = array();
        foreach ($ids as $id) {
            $id = absint($id);
            if (!$id || isset($clean[$id])) {
                continue;
            }
            $page = get_post($id);
            if (!$page || !isset($page->post_type, $page->post_status) || $page->post_type !== 'page' || $page->post_status !== 'publish') {
                continue;
            }
            $clean[$id] = $id;
            if (count($clean) >= 8) {
                break;
            }
        }
        return array_values($clean);
    }

    public static function knowledge_sections() {
        return array(
            'general'       => __('General', 'rafflelb-ai-assistant'),
            'how_it_works'  => __('How RaffleLB Works', 'rafflelb-ai-assistant'),
            'shopping'      => __('Shopping', 'rafflelb-ai-assistant'),
            'trust'         => __('Trust & Authenticity', 'rafflelb-ai-assistant'),
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
            'trust'          => 'RaffleLB is the official RaffleLB online shopping and raffle platform operating through this website. Products sold by RaffleLB are authentic/original. For simple customer questions such as "Are the products authentic?" answer yes directly. For "Is this the official/legit RaffleLB website?" confirm that the customer is on the official RaffleLB website and, when useful, point to the Home URL. Do not add third-party verification disclaimers unless the customer specifically asks for independent evidence or external verification.',
            'raffles'        => 'Only valid active paid entries count toward a raffle. Describe the published customer-facing process and official rules; do not discuss internal administration or security mechanisms.',
            'points'         => 'Raffle Points balance is private. When the customer is signed in and personal assistance is enabled, the assistant may read only that customer’s current Raffle Points balance through the approved read-only account tool; otherwise direct them to My Account. Raffle Points cannot be combined with cash or another payment method: points may only be used when the customer has enough points to cover the full eligible payment amount; otherwise the full amount must be paid using an available payment method. Refer & Earn: when a friend joins through a customer’s personal referral link and completes qualifying paid purchases, the referrer earns a 10% reward value. Every $10 of qualifying paid purchase earns the referrer 10 Raffle Points, and at the current rate 10 Raffle Points equal $1 at checkout. Rewards apply only to qualifying paid orders made by friends connected through the referral link. Orders paid entirely with Raffle Points do not generate additional referral points. Referral rewards may be reversed if the order is cancelled, failed, or refunded. Self-referrals are not eligible.',
            'account'        => 'Customers should use My Account to register, sign in, or update account details. When signed in and personal assistance is enabled, the assistant may read only the current customer’s own raffle entries/ticket numbers, recent order status, and Raffle Points balance through approved read-only tools. It cannot change account data or reveal contact, address, payment, or another customer’s information.',
            'payments'       => 'Available payment methods are the methods currently shown at checkout. Do not name or promise a specific payment method unless it is confirmed by current official website content. The assistant cannot take payments, inspect payment details, or change an order.',
            'delivery'       => 'RaffleLB currently delivers within Lebanon. Standard delivery costs $4.50 and orders are typically delivered within 1–3 business days, depending on location and courier availability. RaffleLB covers standard delivery within Lebanon for raffle prizes.',
            'returns'        => 'Direct-purchase returns or exchanges may be requested within 7 days of delivery if the item is unused, unopened/sealed where applicable, and in its original packaging. Opened perfumes or cosmetics, activated or used vouchers/digital codes, and items used or damaged after delivery are excluded from normal change-of-mind returns. Damaged or incorrectly supplied items are handled separately: customers should contact RaffleLB within 48 hours of delivery with photos and order information, after which RaffleLB arranges the appropriate replacement or resolution. Raffle entries are non-refundable once successfully confirmed and issued, except for duplicate charges, a verified technical issue where payment was taken but no valid entry was created, or if RaffleLB cancels the raffle. The assistant cannot cancel or refund an order or raffle entry itself.',
            'winners'        => 'Use only official published winner information. Never invent a winner or disclose private winner or entry-owner information.',
            'contact'        => 'For account-specific, payment, order, or unresolved questions, direct the customer to the public Contact or Support page when one can be located.',
            'additional_faq' => 'Add administrator-approved customer-facing answers here.',
        );
    }

    private static function legacy_pre_015_knowledge() {
        return array(
            'points'   => 'Raffle Points information shown in a customer account is private. Direct customers to My Account for their balance and to published help content for general explanations.',
            'payments' => 'Customers should use the checkout pages for available payment methods. The assistant cannot take payments, inspect payment details, or change an order.',
            'delivery' => 'Delivery terms may vary by product and destination. Direct customers to the product page, published delivery policy, or support when the knowledge base does not provide a confirmed answer.',
            'returns'  => 'Returns and refunds are governed by the published policy and the circumstances of the purchase. The assistant cannot cancel or refund an order.',
        );
    }

    private static function reconcile_legacy($saved) {
        $defaults = self::defaults();
        if (!array_key_exists('live_pages_enabled', $saved)) {
            $saved['live_pages_enabled'] = 1;
        }
        if (!array_key_exists('personal_assistance_enabled', $saved)) {
            $saved['personal_assistance_enabled'] = 1;
        }
        if (!array_key_exists('live_page_ids', $saved) || !is_array($saved['live_page_ids'])) {
            $saved['live_page_ids'] = array();
        }
        $legacy_knowledge = self::legacy_pre_015_knowledge();
        if (isset($saved['knowledge']) && is_array($saved['knowledge'])) {
            foreach ($legacy_knowledge as $key => $legacy_value) {
                if (isset($saved['knowledge'][$key]) && trim((string) $saved['knowledge'][$key]) === $legacy_value) {
                    $saved['knowledge'][$key] = $defaults['knowledge'][$key];
                }
            }
        }
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
