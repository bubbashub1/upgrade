<?php
if (!defined('ABSPATH')) exit;

/**
 * Safe installer/upgrader for MariaDB/WordPress dbDelta.
 * dbDelta expects CREATE TABLE fields and indexes on separate lines.
 *
 * Important: post types must not be registered during plugins_loaded.
 * WordPress has not initialised WP_Rewrite at that point, so calling
 * register_post_type() there can cause add_rewrite_tag() to dereference
 * a null $wp_rewrite object on modern WordPress versions.
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

    public static function register_runtime_schema() {
        if (!class_exists('BubbaHub')) return;
        BubbaHub::post_types();
        BubbaHub::taxonomies();
        BubbaHub::roles();
        BubbaHub::ensure_pages();
    }

    public static function install() {
        self::schema();

        // register_post_type() must run after WordPress has initialised
        // WP_Rewrite. During normal plugin loading this means waiting for init.
        if (did_action('init')) {
            self::register_runtime_schema();
            flush_rewrite_rules(false);
        } else {
            add_action('init', [__CLASS__, 'register_runtime_schema'], 1);
            add_action('init', function () {
                flush_rewrite_rules(false);
            }, 99);
        }

        update_option('bubbahub_db_version', BUBBAHUB_VERSION, false);
        update_option('bubbahub_safe_install_complete', BUBBAHUB_VERSION, false);
    }
}