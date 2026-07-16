<?php
/**
 * Plugin Name: Elev8 OS
 * Description: The business operating system for creative studios, artists, and makers.
 * Version: 5.6.0
 * Author: Elev8 Arts
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: elev8-os
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ELEV8_OS_VERSION', '5.6.0');
define('ELEV8_OS_FILE', __FILE__);
define('ELEV8_OS_DIR', plugin_dir_path(__FILE__));
define('ELEV8_OS_URL', plugin_dir_url(__FILE__));

require_once ELEV8_OS_DIR . 'includes/class-elev8-os-loader.php';

Elev8_OS_Loader::boot();
register_activation_hook(ELEV8_OS_FILE, ['Elev8_OS', 'activate']);
