<?php
if (!defined('ABSPATH')) exit;

/**
 * BubbaHub CPT capability repair.
 *
 * bh_group uses map_meta_cap with the custom capability type
 * bubbahub_item / bubbahub_items. Imported Google listings may have
 * post_author=0 or belong to another user, so an administrator needs the
 * "others" capabilities for WordPress to render the Edit link and allow
 * editing those existing records.
 */
class BubbaHubCPTCapabilityFix {
    public static function boot() {
        add_action('init', [__CLASS__, 'grant_admin_caps'], 5);
    }

    public static function grant_admin_caps() {
        $admin = get_role('administrator');
        if (!$admin) return;

        foreach ([
            'edit_bubbahub_items',
            'publish_bubbahub_items',
            'delete_bubbahub_items',
            'edit_published_bubbahub_items',
            'delete_published_bubbahub_items',
            'edit_others_bubbahub_items',
            'delete_others_bubbahub_items',
            'read_private_bubbahub_items',
            'edit_private_bubbahub_items',
            'delete_private_bubbahub_items',
        ] as $cap) {
            if (!$admin->has_cap($cap)) $admin->add_cap($cap);
        }

        if (!$admin->has_cap('manage_bubbahub')) $admin->add_cap('manage_bubbahub');
    }
}

BubbaHubCPTCapabilityFix::boot();
