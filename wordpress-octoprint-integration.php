<?php
/**
 * Plugin Name: WordPress OctoPrint Integration
 * Plugin URI: https://zubirimanteo.com
 * Description: Integra OctoPrint con WordPress para monitorear y controlar tu impresora 3D
 * Version: 0.3.2
 * Author: Pablo Rubio, Miren Esnaola
 * License: GPL-2.0+
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WPOI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPOI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPOI_VERSION', '0.3.2');

// Include required files
require_once WPOI_PLUGIN_DIR . 'includes/class-wpoi-main.php';
require_once WPOI_PLUGIN_DIR . 'includes/class-wpoi-admin.php';
require_once WPOI_PLUGIN_DIR . 'includes/class-wpoi-api.php';
require_once WPOI_PLUGIN_DIR . 'includes/class-wpoi-shortcodes.php';
require_once WPOI_PLUGIN_DIR . 'includes/class-wpoi-file-handler.php';

// Inicializar el plugin
function wpoi_load() {
    return WPOI_Main::get_instance();
}
add_action('plugins_loaded', 'wpoi_load');
