<?php
if (!defined('ABSPATH')) exit;

/**
 * BubbaHub Listing Admin Tools
 * Keeps WordPress as the master listing store and makes the manual
 * Google Sheets import/export workflow easy to reach from Listings.
 */
class BubbaHubListingAdminTools {
    public static function boot() {
        add_action('admin_menu', [__CLASS__, 'menu'], 31);
        add_action('admin_notices', [__CLASS__, 'notice'], 20);
    }

    public static function menu() {
        add_submenu_page(
            'edit.php?post_type=bh_group',
            'Google Sheets Import / Export',
            'Google Sheets Sync',
            'manage_bubbahub',
            'bubbahub-google-sync-shortcut',
            [__CLASS__, 'redirect']
        );
    }

    public static function redirect() {
        if (!current_user_can('manage_bubbahub')) {
            wp_die('Permission denied.');
        }
        wp_safe_redirect(admin_url('admin.php?page=bubbahub-google-sync'));
        exit;
    }

    public static function notice() {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== 'bh_group') return;
        if (!current_user_can('manage_bubbahub')) return;
        echo '<div class="notice notice-info"><p><strong>BubbaHub:</strong> WordPress is the master copy of your listings. Edit listings here, then use <a href="' . esc_url(admin_url('admin.php?page=bubbahub-google-sync')) . '">Google Sheets Sync</a> to manually import new/updated Sheet records or export your WordPress changes back to Google Sheets.</p></div>';
    }
}

BubbaHubListingAdminTools::boot();
