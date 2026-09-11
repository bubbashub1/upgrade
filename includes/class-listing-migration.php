<?php
if (!defined('ABSPATH')) exit;

/**
 * Converts legacy Google-imported listing posts into the BubbaHub Group CPT.
 * Existing post IDs, content, authors, dates and post meta are preserved.
 */
class BubbaHubListingMigration {
  const VERSION = '1.0.0';
  const OPTION = 'bubbahub_listing_migration_version';

  public static function run() {
    if (get_option(self::OPTION) === self::VERSION) return;

    global $wpdb;

    // Never touch attachments, pages, events, products or other unrelated content.
    // A legacy listing is identified by the Google ID/owner metadata used by the importer.
    $ids = $wpdb->get_col(
      "SELECT DISTINCT p.ID
       FROM {$wpdb->posts} p
       INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
       WHERE p.post_type IN ('post','listing','directorist_listing','gd_place','bh_group')
         AND pm.meta_key IN ('_bubbahub_google_id','_bubbahub_google_wp_username','_bubbahub_organizer_username')
         AND pm.meta_value <> ''"
    );

    $converted = 0;
    foreach ((array) $ids as $id) {
      $id = absint($id);
      if (!$id) continue;
      $post = get_post($id);
      if (!$post || $post->post_type === 'bh_group') continue;

      $result = wp_update_post([
        'ID' => $id,
        'post_type' => 'bh_group',
      ], true);

      if (!is_wp_error($result)) {
        update_post_meta($id, '_bubbahub_listing_migrated', current_time('mysql'));
        $converted++;
      }
    }

    update_option(self::OPTION, self::VERSION, false);
    update_option('bubbahub_listing_migration_last_run', [
      'time' => current_time('mysql'),
      'converted' => $converted,
    ], false);

    // Make sure the new CPT URLs/admin screens are available immediately.
    if (function_exists('flush_rewrite_rules')) flush_rewrite_rules(false);
  }
}
