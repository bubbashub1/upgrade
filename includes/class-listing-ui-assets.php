<?php
if (!defined('ABSPATH')) exit;
class BubbaHubListingUIAssets {
    public static function boot() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue'], 99);
    }
    public static function enqueue() {
        if (is_admin()) return;
        wp_enqueue_style('bubbahub-listings-card-upgrade', BUBBAHUB_URL . 'assests/css/listings-card-upgrade.css', [], BUBBAHUB_VERSION . '-card');
        wp_enqueue_style('bubbahub-listings-directory-controls', BUBBAHUB_URL . 'assests/css/listings-directory-controls.css', ['bubbahub-listings-card-upgrade'], BUBBAHUB_VERSION . '-directory-controls');
        wp_enqueue_script('bubbahub-listings-upgrade', BUBBAHUB_URL . 'assests/js/listings-upgrade.js', [], BUBBAHUB_VERSION . '-listing', true);
        wp_enqueue_script('bubbahub-listings-directory-controls', BUBBAHUB_URL . 'assests/js/listings-directory-controls.js', ['bubbahub-listings-upgrade'], BUBBAHUB_VERSION . '-directory-controls', true);
    }
}
BubbaHubListingUIAssets::boot();
