<?php
/**
 * Plugin Name: RaffleLB Core
 * Description: Additive shared contracts and read-only compatibility adapters for the existing RaffleLB plugins.
 * Version: 0.1.2
 * Author: RaffleLB
 * Requires PHP: 7.4
 */
namespace RaffleLB\Core;
if (!defined('ABSPATH')) { exit; }
require_once __DIR__ . '/core.php';

add_action('plugins_loaded', [Access::class, 'boot'], 20);
