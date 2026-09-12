<?php
if (!defined('ABSPATH')) exit;

/**
 * Secure front-end listing editor for leaders and administrators.
 * WordPress bh_group posts remain the source of truth.
 */
class BubbaHubListingEditor {
  const META_PREFIX = '_bubbahub_';

  public static function register() {
    register_rest_route('bubbahub/v1', '/leader/listings/(?P<id>\d+)', [
      'methods' => 'GET',
      'callback' => [__CLASS__, 'get'],
      'permission_callback' => [__CLASS__, 'can_access'],
    ]);
    register_rest_route('bubbahub/v1', '/leader/listings/(?P<id>\d+)', [
      'methods' => 'POST',
      'callback' => [__CLASS__, 'save'],
      'permission_callback' => [__CLASS__, 'can_access'],
    ]);
  }

  public static function can_access($request) {
    if (!is_user_logged_in()) return false;
    $id = absint($request['id']);
    $post = get_post($id);
    if (!$post || $post->post_type !== 'bh_group') return false;
    if (current_user_can('manage_bubbahub')) return true;
    if (!current_user_can('edit_bubbahub_items')) return false;
    return self::owns($post);
  }

  private static function owns($post) {
    $uid = get_current_user_id();
    if ((int) $post->post_author === $uid) return true;
    $username = (string) get_post_meta($post->ID, BubbaHubListings::META_OWNER, true);
    $user = wp_get_current_user();
    return $username !== '' && $user && $user->user_login === $username;
  }

  public static function get($request) {
    $id = absint($request['id']);
    return rest_ensure_response(BubbaHubListings::get($id));
  }

  public static function save($request) {
    $id = absint($request['id']);
    $post = get_post($id);
    if (!$post || $post->post_type !== 'bh_group') {
      return new WP_Error('not_found', 'Listing not found.', ['status' => 404]);
    }

    $data = $request->get_json_params();
    if (!is_array($data)) $data = [];

    $post_update = ['ID' => $id];
    if (array_key_exists('title', $data)) {
      $title = sanitize_text_field($data['title']);
      if ($title === '') return new WP_Error('invalid_title', 'A listing title is required.', ['status' => 400]);
      $post_update['post_title'] = $title;
    }
    if (array_key_exists('content', $data)) {
      $post_update['post_content'] = wp_kses_post($data['content']);
    }
    if (count($post_update) > 1) {
      $result = wp_update_post(wp_slash($post_update), true);
      if (is_wp_error($result)) return $result;
    }

    $field_keys = [
      'street','city','region','zip','latitude','longitude','website','email',
      'facebook','instagram','price','is_free','term_time','age_range',
      'session_length','day','timetable','business_hours','sen','start_date','end_date',
    ];
    foreach ($field_keys as $key) {
      if (!array_key_exists($key, $data)) continue;
      $value = self::sanitize_field($key, $data[$key]);
      update_post_meta($id, self::META_PREFIX . $key, $value);
      self::write_coordinate_aliases($id, $key, $value);
    }

    update_post_meta($id, '_bubbahub_last_frontend_edit', current_time('mysql'));
    update_post_meta($id, '_bubbahub_last_frontend_editor', get_current_user_id());

    return rest_ensure_response(BubbaHubListings::get($id));
  }

  private static function sanitize_field($key, $value) {
    if (is_array($value)) {
      return array_values(array_filter(array_map('sanitize_text_field', $value), static function($v) { return $v !== ''; }));
    }
    if (in_array($key, ['latitude','longitude'], true)) {
      $number = is_numeric($value) ? (float) $value : 0;
      if ($key === 'latitude' && ($number < -90 || $number > 90)) return '';
      if ($key === 'longitude' && ($number < -180 || $number > 180)) return '';
      return $number === 0.0 && (string)$value !== '0' ? '' : (string) $number;
    }
    if ($key === 'email') return sanitize_email($value);
    if ($key === 'website' || $key === 'facebook' || $key === 'instagram') return esc_url_raw($value);
    if ($key === 'is_free') return !empty($value) ? '1' : '0';
    if ($key === 'price') return sanitize_text_field($value);
    if ($key === 'timetable' || $key === 'business_hours' || $key === 'session_length') return sanitize_textarea_field($value);
    return sanitize_text_field($value);
  }

  private static function write_coordinate_aliases($id, $key, $value) {
    if ($key === 'latitude') {
      update_post_meta($id, self::META_PREFIX . 'lat', $value);
      update_post_meta($id, self::META_PREFIX . 'manual_lat', $value);
    }
    if ($key === 'longitude') {
      update_post_meta($id, self::META_PREFIX . 'lng', $value);
      update_post_meta($id, self::META_PREFIX . 'long', $value);
      update_post_meta($id, self::META_PREFIX . 'lon', $value);
      update_post_meta($id, self::META_PREFIX . 'manual_lng', $value);
    }
  }
}
add_action('rest_api_init', ['BubbaHubListingEditor', 'register']);
