<?php
if (!defined('ABSPATH')) exit;

/**
 * Safe installer/upgrader for MariaDB/WordPress dbDelta.
 * dbDelta expects CREATE TABLE fields and indexes on separate lines.
 */
final class BubbaHubSafeInstaller {
    public static function schema() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $tables = [
            "CREATE TABLE {$wpdb->prefix}bubbahub_children (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                name varchar(190) NOT NULL,
                birth_date date NULL,
                stage varchar(80) NULL,
                notes text NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY user_id (user_id)
            ) $charset;",
            "CREATE TABLE {$wpdb->prefix}bubbahub_saved (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                object_type varchar(40) NOT NULL,
                object_id bigint(20) unsigned NOT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY user_object (user_id,object_type,object_id)
            ) $charset;",
            "CREATE TABLE {$wpdb->prefix}bubbahub_notes (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                group_id bigint(20) unsigned NOT NULL,
                note text NOT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY user_group (user_id,group_id)
            ) $charset;",
            "CREATE TABLE {$wpdb->prefix}bubbahub_members (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                group_id bigint(20) unsigned NOT NULL,
                status varchar(30) NOT NULL DEFAULT 'active',
                joined_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY user_group (user_id,group_id)
            ) $charset;",
            "CREATE TABLE {$wpdb->prefix}bubbahub_buddy_messages (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                sender_id bigint(20) unsigned NOT NULL,
                recipient_id bigint(20) unsigned NOT NULL,
                message text NOT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                read_at datetime NULL,
                PRIMARY KEY  (id),
                KEY recipient (recipient_id),
                KEY sender (sender_id)
            ) $charset;",
        ];
        foreach ($tables as $sql) {
            dbDelta($sql);
        }
    }

    public static function install() {
        self::schema();
        if (class_exists('BubbaHub')) {
            BubbaHub::post_types();
            BubbaHub::taxonomies();
            BubbaHub::roles();
            BubbaHub::ensure_pages();
        }
        update_option('bubbahub_db_version', BUBBAHUB_VERSION, false);
        update_option('bubbahub_safe_install_complete', BUBBAHUB_VERSION, false);
        flush_rewrite_rules(false);
    }
}
