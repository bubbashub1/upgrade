<?php
/**
 * Plugin Name: BubbaHub Production
 * Description: Production WordPress application for BubbaHub: groups, events, family hub, support, app directory, leader portal, bookings, payments, wallets and REST API.
 * Version: 4.1.2
 * Author: BubbaHub
 * Requires at least: 6.4
 * Requires PHP: 8.0
 */
if (!defined('ABSPATH')) exit;

define('BUBBAHUB_VERSION','4.1.2');
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
require_once BUBBAHUB_DIR.'includes/class-shortcode-assets-fix.php';

register_activation_hook(__FILE__, ['BubbaHub','activate']);
register_deactivation_hook(__FILE__, ['BubbaHub','deactivate']);

/**
 * Load BubbaHub without running the activation routine during normal requests.
 * Older versions called BubbaHub::activate() from the constructor. That routine
 * registers post types and flushes rewrites before WordPress has initialised its
 * rewrite system, which can cause a fatal error on the front end during upgrades.
 */
add_action('plugins_loaded', function(){
  $stored_version = (string) get_option('bubbahub_db_version','0');
  if (version_compare($stored_version, BUBBAHUB_VERSION, '<')) {
    update_option('bubbahub_pending_upgrade', [
      'from' => $stored_version,
      'to'   => BUBBAHUB_VERSION,
    ], false);
    // Prevent the legacy constructor upgrade path from running during load.
    update_option('bubbahub_db_version', BUBBAHUB_VERSION, false);
  }
  new BubbaHub();
});

/**
 * Run pending database/plugin upgrades only after WordPress has completed init.
 * admin_init is late enough for post types, taxonomies and rewrite globals to be
 * available, and the Throwable guard prevents an upgrade issue from taking the
 * entire website offline.
 */
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
    error_log('BubbaHub 4.1.2 upgrade failed: ' . $e->getMessage());
    // Keep the pending upgrade marker so it can be retried after the underlying
    // issue is corrected, but do not bring down wp-admin or the public site.
  }
}, 1);