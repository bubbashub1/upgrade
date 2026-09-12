<?php
if (!defined('ABSPATH')) exit;

/**
 * BubbaHub security and audit layer.
 * Keeps authorisation server-side and records meaningful listing changes.
 */
final class BubbaHubSecurityAudit {
    const AUDIT_OPTION = 'bubbahub_audit_log_v1';
    const AUDIT_MAX = 250;

    public static function boot() {
        add_action('init', [__CLASS__, 'ensure_capabilities'], 5);
        add_action('save_post_bh_group', [__CLASS__, 'audit_listing_save'], 20, 3);
        add_action('before_delete_post', [__CLASS__, 'audit_listing_delete'], 20);
    }

    public static function ensure_capabilities() {
        foreach (['administrator','editor'] as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                $role->add_cap('manage_bubbahub');
                $role->add_cap('edit_bubbahub_items');
            }
        }
        foreach (['author','contributor'] as $role_name) {
            $role = get_role($role_name);
            if ($role) $role->add_cap('edit_bubbahub_items');
        }
    }

    public static function can_manage() { return current_user_can('manage_bubbahub'); }

    public static function can_edit_listing($post_id) {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'bh_group') return false;
        if (self::can_manage()) return true;
        if (!current_user_can('edit_bubbahub_items')) return false;
        $owner = (string) get_post_meta($post_id, '_bubbahub_owner', true);
        return (int) $post->post_author === get_current_user_id() ||
            ($owner !== '' && wp_get_current_user()->user_login === $owner);
    }

    public static function audit_listing_save($post_id, $post, $update) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;
        if (!$update && get_post_status($post_id) === 'auto-draft') return;
        $before = get_post_meta($post_id, '_bubbahub_audit_snapshot', true);
        $after = self::snapshot($post_id, $post);
        $changes = [];
        if (is_array($before)) {
            foreach ($after as $key => $value) {
                $old = isset($before[$key]) ? $before[$key] : '';
                if ((string) $old !== (string) $value) $changes[] = $key;
            }
        } else {
            $changes = array_keys($after);
        }
        if ($changes) {
            self::write('listing_updated', $post_id, ['fields' => array_values($changes)]);
        }
        update_post_meta($post_id, '_bubbahub_audit_snapshot', $after);
    }

    public static function audit_listing_delete($post_id) {
        if (get_post_type($post_id) !== 'bh_group') return;
        self::write('listing_deleted', $post_id, []);
    }

    private static function snapshot($post_id, $post = null) {
        if (!$post) $post = get_post($post_id);
        $keys = ['street','city','region','zip','latitude','longitude','age_range','session_length','price','day','timetable','business_hours','term_time','website','email','facebook','instagram','featured'];
        $snapshot = ['title' => $post ? $post->post_title : ''];
        foreach ($keys as $key) $snapshot[$key] = get_post_meta($post_id, '_bubbahub_' . $key, true);
        return $snapshot;
    }

    private static function write($action, $object_id, $context) {
        $user = wp_get_current_user();
        $entry = [
            'time' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'user' => $user ? (string) $user->user_login : '',
            'action' => sanitize_key($action),
            'object_id' => absint($object_id),
            'context' => is_array($context) ? $context : [],
        ];
        $log = get_option(self::AUDIT_OPTION, []);
        if (!is_array($log)) $log = [];
        array_unshift($log, $entry);
        update_option(self::AUDIT_OPTION, array_slice($log, 0, self::AUDIT_MAX), false);
    }

    public static function get_audit_log($limit = 100) {
        if (!self::can_manage()) return [];
        $log = get_option(self::AUDIT_OPTION, []);
        return array_slice(is_array($log) ? $log : [], 0, max(1, min(250, absint($limit))));
    }
}
BubbaHubSecurityAudit::boot();
