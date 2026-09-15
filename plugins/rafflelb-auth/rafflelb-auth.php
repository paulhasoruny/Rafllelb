<?php
/**
 * Plugin Name: RaffleLB Auth
 * Description: Phone verification, customer authentication, registration, and recovery for RaffleLB.
 * Version: 0.1.24
 * Author: RaffleLB
 * Requires PHP: 7.4
 * Requires at least: 6.0
 * Text Domain: rafflelb-auth
 */

if (!defined('ABSPATH')) { exit; }

define('RAFFLELB_AUTH_VERSION', '0.1.24');
define('RAFFLELB_AUTH_FILE', __FILE__);
define('RAFFLELB_AUTH_DIR', plugin_dir_path(__FILE__));

require_once RAFFLELB_AUTH_DIR . 'includes/class-phone-normalizer.php';
require_once RAFFLELB_AUTH_DIR . 'includes/class-rate-limiter.php';
require_once RAFFLELB_AUTH_DIR . 'includes/sms/interface-sms-provider.php';
require_once RAFFLELB_AUTH_DIR . 'includes/sms/class-disabled-provider.php';
require_once RAFFLELB_AUTH_DIR . 'includes/sms/class-test-provider.php';
require_once RAFFLELB_AUTH_DIR . 'includes/sms/class-bestsmsbulk-provider.php';
require_once RAFFLELB_AUTH_DIR . 'includes/sms/class-provider-factory.php';
require_once RAFFLELB_AUTH_DIR . 'includes/class-phone-registry.php';
require_once RAFFLELB_AUTH_DIR . 'includes/class-otp-service.php';
require_once RAFFLELB_AUTH_DIR . 'includes/class-user-service.php';
require_once RAFFLELB_AUTH_DIR . 'includes/class-authentication.php';
require_once RAFFLELB_AUTH_DIR . 'includes/class-registration.php';
require_once RAFFLELB_AUTH_DIR . 'includes/class-password-reset.php';
require_once RAFFLELB_AUTH_DIR . 'includes/class-frontend.php';
require_once RAFFLELB_AUTH_DIR . 'includes/class-admin.php';

final class RaffleLB_Auth {
    public static function init() {
        RaffleLB_Auth_Phone_Registry::init();
        RaffleLB_Auth_Authentication::init();
        RaffleLB_Auth_Registration::init();
        RaffleLB_Auth_Password_Reset::init();
        RaffleLB_Auth_Frontend::init();
        RaffleLB_Auth_Admin::init();
    }

    public static function activate() {
        RaffleLB_Auth_Phone_Registry::install();
        add_option('rafflelb_auth_settings', RaffleLB_Auth_Admin::defaults());
        RaffleLB_Auth_Frontend::register_email_login_route();
        flush_rewrite_rules(false);
        update_option('rafflelb_auth_rewrite_version', RAFFLELB_AUTH_VERSION, false);
    }
}

register_activation_hook(__FILE__, array('RaffleLB_Auth', 'activate'));
add_action('plugins_loaded', array('RaffleLB_Auth', 'init'));
