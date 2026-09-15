<?php

namespace RaffleLB\AI_Assistant;

if (!defined('ABSPATH')) {
    exit;
}

final class Frontend {
    public static function boot() {
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue'));
        add_action('wp_footer', array(__CLASS__, 'render'), 20);
    }

    public static function enqueue() {
        if (is_admin() || !Settings::enabled()) {
            return;
        }
        wp_enqueue_style('rafflelb-ai-widget', RAFFLELB_AI_ASSISTANT_URL . 'assets/css/widget.css', array(), RAFFLELB_AI_ASSISTANT_VERSION);
        wp_enqueue_script('rafflelb-ai-widget', RAFFLELB_AI_ASSISTANT_URL . 'assets/js/widget.js', array(), RAFFLELB_AI_ASSISTANT_VERSION, true);
        $settings = Settings::all();
        $quick_questions = Settings::frontend_quick_questions();
        wp_localize_script('rafflelb-ai-widget', 'RaffleLBAssistant', array(
            'restUrl'           => esc_url_raw(rest_url(REST_Controller::NAMESPACE . '/chat')),
            'restNonce'         => is_user_logged_in() ? wp_create_nonce('wp_rest') : '',
            'siteOrigin'        => esc_url_raw(home_url('/')),
            'welcomeHeading'    => (string) $settings['welcome_heading'],
            'welcomeDescription'=> (string) $settings['welcome_description'],
            'quickQuestions'    => $quick_questions,
            'maxLength'         => REST_Controller::MAX_MESSAGE_LENGTH,
            'iconUrl'           => esc_url_raw(RAFFLELB_AI_ASSISTANT_URL . 'assets/images/rafflelb-assistant.png'),
        ));
    }

    private static function render_quick_icon($icon, $icon_url) {
        switch ($icon) {
            case 'ticket':
                echo '<svg viewBox="0 0 24 24"><path d="M4 7.2h16v3a2 2 0 0 0 0 4v2.6H4v-2.6a2 2 0 0 0 0-4z"/><path d="M9 7.2v9.6"/></svg>';
                break;
            case 'package':
                echo '<svg viewBox="0 0 24 24"><path d="m4.5 7.2 7.5-4 7.5 4v9.6l-7.5 4-7.5-4z"/><path d="m4.8 7.4 7.2 4 7.2-4M12 11.4v9.1M8.2 5.2l7.5 4"/></svg>';
                break;
            case 'raffle':
                echo '<img class="rlb-ai__quick-action-brand" src="' . esc_url($icon_url) . '" alt="">';
                break;
            case 'shopping_bag':
                echo '<svg viewBox="0 0 24 24"><path d="M5 8.5h14l-1 11H6z"/><path d="M8.5 9V7a3.5 3.5 0 0 1 7 0v2"/></svg>';
                break;
            case 'perfume':
                echo '<svg viewBox="0 0 24 24"><path d="M9 3h6v3H9zM8 6h8l2.2 4v9.5H5.8V10z"/><path d="M9.5 12h5"/></svg>';
                break;
            case 'points':
                echo '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><path d="M9 8.5h4.2a2.3 2.3 0 0 1 0 4.6H10m0 0h3.6a2.4 2.4 0 0 1 0 4.8H9M11 6.5v11"/></svg>';
                break;
            case 'delivery':
                echo '<svg viewBox="0 0 24 24"><path d="M3.5 6.5h11v9h-11zM14.5 10h3.4l2.6 3v2.5h-6z"/><circle cx="7" cy="17.5" r="1.7"/><circle cx="17.5" cy="17.5" r="1.7"/></svg>';
                break;
            case 'payment':
                echo '<svg viewBox="0 0 24 24"><rect x="3.5" y="5.5" width="17" height="13" rx="2"/><path d="M3.8 9h16.4M7 14h3"/></svg>';
                break;
            case 'account':
                echo '<svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="3"/><path d="M5.5 19a6.5 6.5 0 0 1 13 0"/></svg>';
                break;
            case 'winners':
                echo '<svg viewBox="0 0 24 24"><path d="M8 4h8v4a4 4 0 0 1-8 0zM10 12h4v4h-4zM7.5 20h9"/><path d="M8 6H4.5v1.5A3.5 3.5 0 0 0 8 11M16 6h3.5v1.5A3.5 3.5 0 0 1 16 11"/></svg>';
                break;
            case 'question':
            default:
                echo '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9.2"/><path d="M9.7 9a2.5 2.5 0 0 1 4.8 1c0 1.8-2.5 2.1-2.5 3.9M12 17.4h.01"/></svg>';
                break;
        }
    }

    public static function render() {
        if (is_admin() || !Settings::enabled()) {
            return;
        }
        $icon_url = RAFFLELB_AI_ASSISTANT_URL . 'assets/images/rafflelb-assistant.png';
        $settings = Settings::all();
        $quick_questions = Settings::frontend_quick_questions();
        ?>
        <div class="rlb-ai" id="rlb-ai" data-open="false" data-conversation="welcome">
            <section class="rlb-ai__panel" id="rlb-ai-panel" role="dialog" aria-labelledby="rlb-ai-title" aria-describedby="rlb-ai-subtitle" aria-modal="false" hidden>
                <header class="rlb-ai__header">
                    <img class="rlb-ai__brand-image" src="<?php echo esc_url($icon_url); ?>" alt="" aria-hidden="true">
                    <div class="rlb-ai__heading">
                        <h2 id="rlb-ai-title">RaffleLB Assistant</h2>
                        <p id="rlb-ai-subtitle"><span class="rlb-ai__status-dot" aria-hidden="true"></span>Online <span aria-hidden="true">•</span> Here to help</p>
                    </div>
                    <button type="button" class="rlb-ai__control" data-rlb-ai-close aria-label="<?php echo esc_attr__('Close RaffleLB Assistant', 'rafflelb-ai-assistant'); ?>">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 6.5l11 11M17.5 6.5l-11 11"/></svg>
                    </button>
                </header>

                <div class="rlb-ai__conversation">
                    <div class="rlb-ai__intro">
                        <section class="rlb-ai__welcome" aria-labelledby="rlb-ai-welcome-title">
                            <h3 id="rlb-ai-welcome-title"><?php echo esc_html($settings['welcome_heading']); ?></h3>
                            <?php if ((string) $settings['welcome_description'] !== '') : ?>
                                <p><?php echo esc_html($settings['welcome_description']); ?></p>
                            <?php endif; ?>
                        </section>

                        <?php if (!empty($quick_questions)) : ?>
                            <div class="rlb-ai__quick-actions" aria-label="<?php echo esc_attr__('Quick questions', 'rafflelb-ai-assistant'); ?>">
                                <?php foreach ($quick_questions as $question) : ?>
                                    <?php $icon = isset($question['icon']) ? (string) $question['icon'] : 'question'; ?>
                                    <button type="button" class="rlb-ai__quick-action<?php echo in_array($icon, array('package', 'payment', 'winners'), true) ? ' rlb-ai__quick-action--gold' : ''; ?>" data-rlb-ai-suggestion="<?php echo esc_attr($question['index']); ?>">
                                        <span class="rlb-ai__quick-action-icon" aria-hidden="true">
                                            <?php self::render_quick_icon($icon, $icon_url); ?>
                                        </span>
                                        <span class="rlb-ai__quick-action-label"><?php echo esc_html($question['label']); ?></span>
                                        <span class="rlb-ai__quick-action-chevron" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></svg></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="rlb-ai__date" data-rlb-ai-date aria-label="<?php echo esc_attr__('Conversation date', 'rafflelb-ai-assistant'); ?>">Today</div>
                    <div class="rlb-ai__messages" data-rlb-ai-messages role="log" aria-live="polite" aria-relevant="additions"></div>
                </div>

                <form class="rlb-ai__form" data-rlb-ai-form>
                    <label class="screen-reader-text" for="rlb-ai-input"><?php echo esc_html__('Message RaffleLB Assistant', 'rafflelb-ai-assistant'); ?></label>
                    <textarea class="rlb-ai__input" id="rlb-ai-input" data-rlb-ai-input rows="1" maxlength="1500" placeholder="Type a message..." required></textarea>
                    <button type="submit" class="rlb-ai__send" data-rlb-ai-send aria-label="<?php echo esc_attr__('Send message', 'rafflelb-ai-assistant'); ?>">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5.2 4.7 20 12 5.2 19.3l1.3-5.6 7.7-1.7-7.7-1.7z"/></svg>
                    </button>
                </form>
            </section>

            <button type="button" class="rlb-ai__launcher" data-rlb-ai-launcher aria-controls="rlb-ai-panel" aria-expanded="false" aria-label="<?php echo esc_attr__('Open RaffleLB Assistant', 'rafflelb-ai-assistant'); ?>">
                <span class="rlb-ai__launcher-label">Need help? Ask RaffleLB</span>
                <img class="rlb-ai__launcher-image" src="<?php echo esc_url($icon_url); ?>" alt="" aria-hidden="true">
            </button>
        </div>
        <?php
    }
}
