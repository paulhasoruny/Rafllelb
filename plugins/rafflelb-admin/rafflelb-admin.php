<?php
/**
 * Plugin Name: RaffleLB Admin
 * Description: A focused operations dashboard and simplified navigation for RaffleLB administrators.
 * Version: 1.1.3
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: RaffleLB
 * Text Domain: rafflelb-admin
 */

defined('ABSPATH') || exit;

define('RAFFLELB_ADMIN_VERSION', '1.1.3');
define('RAFFLELB_ADMIN_FILE', __FILE__);
define('RAFFLELB_ADMIN_DIR', plugin_dir_path(__FILE__));
define('RAFFLELB_ADMIN_URL', plugin_dir_url(__FILE__));

require_once RAFFLELB_ADMIN_DIR . 'includes/class-rafflelb-admin-dashboard.php';
require_once RAFFLELB_ADMIN_DIR . 'includes/class-rafflelb-admin-navigation.php';

RaffleLB_Admin_Dashboard::init();
RaffleLB_Admin_Navigation::init();
