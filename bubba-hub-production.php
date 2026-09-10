<?php
/**
 * Plugin Name: BubbaHub Production
 * Description: Production WordPress application for BubbaHub: groups, events, family hub, support, app directory, leader portal, bookings, payments, wallets and REST API.
 * Version: 4.1.3
 * Author: BubbaHub
 * Requires at least: 6.4
 * Requires PHP: 8.0
 */
if (!defined('ABSPATH')) exit;

define('BUBBAHUB_VERSION','4.1.3');
define('BUBBAHUB_DIR',plugin_dir_path(__FILE__));
define('BUBBAHUB_URL',plugin_dir_url(__FILE__));
require_once BUBBAHUB_DIR.'includes/class-bubbahub.php';
require_once BUBBAHUB_DIR.'includes/class-rest.php';
require_once BUBBAHUB_DIR.'includes/class-admin.php';
require_once BUBBAHUB_DIR.'includes/class-finance.php';
require_once BUBBAHUB_DIR.'includes/class-finance-providers.php';
require_once BUBBAHUB_DIR.'includes/class-finance-security.php';
require_once BUBBAHUB_DIR.'includes/class-account-types.php';
require_once BUBBAHUB_DIR.'includes/class-google-sync.php';
require_once BUBBAHUB_DIR.'includes/class-leader-editor-sync.php';
require_once BUBBAHUB_DIR.'includes/class-rest-cookie-fix.php';
require_once BUBBAHUB_DIR.'includes/class-shortcode-assets-fix.php';
require_once BUBBAHUB_DIR.'includes/class-rest-bootstrap-fix.php';
require_once BUBBAHUB_DIR.'includes/class-frontend-assets-fix.php';

register_activation_hook(__FILE__, ['BubbaHub','activate']);
register_deactivation_hook(__FILE__, ['BubbaHub','deactivate']);

add_action('plugins_loaded', function(){
  $stored_version = (string) get_option('bubbahub_db_version','0');
  if (version_compare($stored_version, BUBBAHUB_VERSION, '<')) {
    update_option('bubbahub_pending_upgrade', [
      'from' => $stored_version,
      'to'   => BUBBAHUB_VERSION,
    ], false);
    update_option('bubbahub_db_version', BUBBAHUB_VERSION, false);
  }
  new BubbaHub();
});

add_action('admin_init', function(){
  $pending = get_option('bubbahub_pending_upgrade', false);
  if (!is_array($pending) || empty($pending['to']) || $pending['to'] !== BUBBAHUB_VERSION) {
    return;
  }

  try {
    BubbaHub::activate();
    delete_option('bubbahub_pending_upgrade');
    update_option('bubbahub_db_version', BUBBAHUB_VERSION, false);
  } catch (Throwable $e) {
    error_log('BubbaHub 4.1.3 upgrade failed: ' . $e->getMessage());
  }
}, 1);
