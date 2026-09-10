<?php
/**
 * Plugin Name: BubbaHub Production
 * Description: Production WordPress application for BubbaHub: groups, events, family hub, support, app directory, leader portal and REST API.
 * Version: 3.9.0
 * Author: BubbaHub
 * Requires at least: 6.4
 * Requires PHP: 8.0
 */
if (!defined('ABSPATH')) exit;

define('BUBBAHUB_VERSION','3.9.0');
define('BUBBAHUB_DIR',plugin_dir_path(__FILE__));
define('BUBBAHUB_URL',plugin_dir_url(__FILE__));
require_once BUBBAHUB_DIR.'includes/class-bubbahub.php';
require_once BUBBAHUB_DIR.'includes/class-rest.php';
require_once BUBBAHUB_DIR.'includes/class-admin.php';
register_activation_hook(__FILE__, ['BubbaHub','activate']);
register_deactivation_hook(__FILE__, ['BubbaHub','deactivate']);
add_action('plugins_loaded', function(){ new BubbaHub(); });
