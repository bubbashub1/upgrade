<?php
if (!defined('ABSPATH')) exit;

/**
 * Front-end Leader Editor bridge.
 *
 * Keeps the existing REST API intact while making leader edits safe and
 * synchronised with the Google Sheets integration. This file deliberately
 * lives beside the existing REST class so it can be added/removed without
 * replacing the production REST implementation.
 */
class BubbaHubLeaderEditorSync {
  public static function boot() {
    add_filter('rest_pre_dispatch', [__CLASS__, 'intercept'], 20, 3);
  }

  private static function is_leader() {
    return is_user_logged_in() && (current_user_can('edit_bubbahub_items') || current_user_can('manage_bubbahub'));
  }

  private static function owns_post($post) {
    if (!$post || !in_array($post->post_type, ['bh_group', 'bh_event'], true)) return false;
    if (current_user_can('manage_bubbahub')) return true;

    $login = wp_get_current_user()->user_login;
    $owner = (string) get_post_meta($post->ID, '_bubbahub_google_wp_username', true);
    if (!$owner && $post->post_author) {
      $author = get_userdata($post->post_author);
      if ($author) $owner = $author->user_login;
    }
    return $owner !== '' && strcasecmp($owner, $login) === 0;
  }

  private static function clean_value($value, $field = null) {
    if (is_array($value)) {
      return array_values(array_map(function ($v) { return is_scalar($v) ? sanitize_text_field((string)$v) : ''; }, $value));
    }
    if ($field && !empty($field['type']) && in_array($field['type'], ['textarea','wysiwyg'], true)) {
      return wp_kses_post((string)$value);
    }
    return sanitize_text_field((string)$value);
  }

  private static function field_map($post_type) {
    $map = [];
    if (class_exists('BubbaHubAdmin') && method_exists('BubbaHubAdmin', 'get_directory_fields')) {
      foreach ((array) BubbaHubAdmin::get_directory_fields($post_type) as $field) {
        if (!empty($field['key'])) $map[sanitize_key($field['key'])] = $field;
      }
    }
    return $map;
  }

  private static function response_post($post) {
    $custom = [];
    foreach (self::field_map($post->post_type) as $key => $field) {
      $value = get_post_meta($post->ID, '_bubbahub_' . $key, true);
      if ($value !== '' && $value !== null) {
        $custom[$key] = [
          'label' => $field['label'] ?? $key,
          'type'  => $field['type'] ?? 'text',
          'value' => $value,
        ];
      }
    }
    return [
      'id' => $post->ID,
      'title' => get_the_title($post),
      'slug' => $post->post_name,
      'content' => apply_filters('the_content', $post->post_content),
      'raw_content' => $post->post_content,
      'excerpt' => get_the_excerpt($post),
      'image' => get_the_post_thumbnail_url($post, 'large'),
      'status' => $post->post_status,
      'custom_fields' => $custom,
      'google_id' => (string) get_post_meta($post->ID, '_bubbahub_google_id', true),
      'google_writeback_enabled' => (bool) get_option('bubbahub_google_sync', [])['writeback'] ?? false,
      'link' => get_permalink($post),
    ];
  }

  private static function save($post, $request) {
    if (!self::owns_post($post)) {
      return new WP_Error('forbidden', 'You can only edit your own listings.', ['status' => 403]);
    }

    $data = $request->get_json_params();
    if (!is_array($data)) $data = [];

    // Accept either {meta:{field:value}} or direct field keys.
    $meta = isset($data['meta']) && is_array($data['meta']) ? $data['meta'] : [];
    $fields = self::field_map($post->post_type);
    foreach ($fields as $key => $field) {
      if (array_key_exists($key, $data)) $meta[$key] = $data[$key];
      if (array_key_exists('bubbahub_' . $key, $data)) $meta[$key] = $data['bubbahub_' . $key];
    }

    // Update custom fields BEFORE wp_update_post so the Google save_post hook
    // sees the newest values when it performs write-back.
    foreach ($meta as $key => $value) {
      $key = sanitize_key(str_replace('bubbahub_', '', (string)$key));
      if (!isset($fields[$key])) continue;
      update_post_meta($post->ID, '_bubbahub_' . $key, self::clean_value($value, $fields[$key]));
    }

    $args = ['ID' => $post->ID];
    if (array_key_exists('title', $data)) $args['post_title'] = sanitize_text_field($data['title']);
    if (array_key_exists('content', $data)) $args['post_content'] = wp_kses_post($data['content']);
    if (array_key_exists('status', $data) && in_array($data['status'], ['publish','draft','pending'], true)) $args['post_status'] = $data['status'];

    $updated = wp_update_post($args, true);
    if (is_wp_error($updated)) return $updated;

    $saved = get_post($post->ID);
    if (!$saved) return new WP_Error('save_failed', 'The listing could not be loaded after saving.', ['status' => 500]);

    $writeback = false;
    if (class_exists('BubbaHubGoogleSync') && method_exists('BubbaHubGoogleSync', 'push')) {
      // save_post normally performs this automatically. Calling push here is
      // intentionally avoided to prevent duplicate Google updates.
      $settings = get_option('bubbahub_google_sync', []);
      $writeback = !empty($settings['secure_api']) && !empty($settings['writeback']);
    }

    return rest_ensure_response(array_merge(self::response_post($saved), [
      'saved' => true,
      'google_writeback_queued' => $writeback,
      'message' => $writeback ? 'Listing saved. Google Sheets write-back has been requested.' : 'Listing saved. Enable Secure API and Write-back in BubbaHub → Google Sheets Sync to update Google Sheets.',
    ]));
  }

  public static function intercept($result, $server, $request) {
    $route = (string) $request->get_route();
    $method = strtoupper($request->get_method());

    if (!preg_match('#^/bubbahub/v1/leader/(groups|events)(?:/(\d+))?$#', $route, $m)) return $result;
    if (!self::is_leader()) return new WP_Error('forbidden', 'Leader access required.', ['status' => 403]);

    $type = $m[1] === 'groups' ? 'bh_group' : 'bh_event';
    $id = !empty($m[2]) ? absint($m[2]) : 0;

    // The existing dashboard endpoint should only expose listings belonging to
    // the current leader. This prevents another leader's IDs being presented in
    // the editor even before an edit request is attempted.
    if ($method === 'GET' && !$id) {
      $query = new WP_Query([
        'post_type' => $type,
        'post_status' => ['publish','draft','pending'],
        'posts_per_page' => 100,
        'orderby' => 'title',
        'order' => 'ASC',
      ]);
      $out = [];
      foreach ($query->posts as $post) if (self::owns_post($post)) $out[] = self::response_post($post);
      return rest_ensure_response($out);
    }

    if (!$id) return $result;
    $post = get_post($id);
    if (!$post || $post->post_type !== $type) return new WP_Error('not_found', 'Listing not found.', ['status' => 404]);
    if (!self::owns_post($post)) return new WP_Error('forbidden', 'You can only access your own listings.', ['status' => 403]);

    if ($method === 'DELETE') {
      $deleted = wp_delete_post($id, true);
      if (!$deleted) return new WP_Error('delete_failed', 'The listing could not be deleted.', ['status' => 500]);
      return rest_ensure_response(['deleted' => true, 'id' => $id]);
    }

    if (in_array($method, ['POST','PUT','PATCH'], true)) return self::save($post, $request);
    if ($method === 'GET') return rest_ensure_response(self::response_post($post));

    return $result;
  }
}

BubbaHubLeaderEditorSync::boot();
