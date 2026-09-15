<?php
/**
 * Plugin Name: RaffleLB AI Assistant
 * Description: Read-only customer-facing AI assistant for RaffleLB products, raffles, help content, and secure signed-in account lookups.
 * Version: 0.1.23
 * Author: RaffleLB
 * Text Domain: rafflelb-ai-assistant
 * Requires at least: 6.4
 * Requires PHP: 7.4
 */

namespace RaffleLB\AI_Assistant;

if (!defined('ABSPATH')) {
    exit;
}

define('RAFFLELB_AI_ASSISTANT_VERSION', '0.1.23');
define('RAFFLELB_AI_ASSISTANT_FILE', __FILE__);
define('RAFFLELB_AI_ASSISTANT_DIR', plugin_dir_path(__FILE__));
define('RAFFLELB_AI_ASSISTANT_URL', plugin_dir_url(__FILE__));

require_once RAFFLELB_AI_ASSISTANT_DIR . 'includes/interface-provider.php';
require_once RAFFLELB_AI_ASSISTANT_DIR . 'includes/class-settings.php';
require_once RAFFLELB_AI_ASSISTANT_DIR . 'includes/class-knowledge-base.php';
require_once RAFFLELB_AI_ASSISTANT_DIR . 'includes/class-tools.php';
require_once RAFFLELB_AI_ASSISTANT_DIR . 'includes/class-openai-provider.php';
require_once RAFFLELB_AI_ASSISTANT_DIR . 'includes/class-rest-controller.php';
require_once RAFFLELB_AI_ASSISTANT_DIR . 'includes/class-frontend.php';

function boot() {
    Settings::boot();
    (new REST_Controller(new OpenAI_Provider(), new Tools()))->boot();
    Frontend::boot();
}

add_action('plugins_loaded', __NAMESPACE__ . '\\boot', 20);
register_activation_hook(__FILE__, array(Settings::class, 'activate'));
