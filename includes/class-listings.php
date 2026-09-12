<?php
if (!defined('ABSPATH')) exit;

/**
 * Canonical BubbaHub listing data access layer.
 *
 * WordPress bh_group posts remain the editable source of truth. Google Sheets
 * metadata is retained as integration identity and is never used as a second
 * listing database.
 */
class BubbaHubListings {
  const POST_TYPE = 'bh_group';
  const META_GOOGLE_ID = '_bubbahub_google_id';
  const META_OWNER = '_bubbahub_google_wp_username';

  public static function post_type() {
    return self::POST_TYPE;
  }

  public static function get($id) {
    $post = get_post(absint($id));
    if (!$post || $post->post_type !== self::POST_TYPE) return null;
    return self::format($post);
  }

  public static function query($args = []) {
    $defaults = [
      'post_type' => self::POST_TYPE,
      'post_status' => 'publish',
      'posts_per_page' => 12,
      'paged' => 1,
      'orderby' => 'date',
      'order' => 'DESC',
    ];
    $query = new WP_Query(wp_parse_args($args, $defaults));
    return [
      'items' => array_map([__CLASS__, 'format'], $query->posts),
      'total' => (int) $query->found_posts,
      'pages' => (int) $query->max_num_pages,
      'page' => (int) ($args['paged'] ?? 1),
    ];
  }

  public static function find_by_google_id($google_id) {
    $google_id = sanitize_text_field((string) $google_id);
    if ($google_id === '') return null;
    $ids = get_posts([
      'post_type' => self::POST_TYPE,
      'post_status' => 'any',
      'posts_per_page' => 1,
      'fields' => 'ids',
      'meta_key' => self::META_GOOGLE_ID,
      'meta_value' => $google_id,
    ]);
    return !empty($ids[0]) ? self::get($ids[0]) : null;
  }

  public static function fields($id) {
    $id = absint($id);
    $map = [
      'images' => ['images', 'image', 'gallery', 'gallery_images'],
      'tags' => ['tags', 'tag'],
      'category' => ['category', 'categories'],
      'featured' => ['featured', 'is_featured', 'featured_listing'],
      'street' => ['street', 'address'],
      'city' => ['city', 'town', 'postal_town'],
      'region' => ['region', 'county'],
      'zip' => ['zip', 'postcode', 'post_code', 'postal_code'],
      'latitude' => ['latitude', 'lat', 'manual_lat'],
      'longitude' => ['longitude', 'lng', 'long', 'lon', 'manual_lng'],
      'website' => ['website', 'url'],
      'email' => ['email'],
      'facebook' => ['facebook'],
      'instagram' => ['instagram'],
      'price' => ['price', 'cost'],
      'is_free' => ['is_free', 'free', 'free_session'],
      'term_time' => ['term_time', 'term time', 'termtime', 'term-time'],
      'age_range' => ['age_range', 'age range'],
      'session_length' => ['session_length', 'session length'],
      'day' => ['day', 'days'],
      'timetable' => ['timetable', 'schedule'],
      'business_hours' => ['business_hours', 'opening_hours', 'openinghours', 'opening hours'],
      'sen' => ['sen', 'special educational needs'],
      'start_date' => ['start_date', 'start date', 'term_start', 'term start'],
      'end_date' => ['end_date', 'end date', 'term_end', 'term end'],
    ];
    $out = [];
    foreach ($map as $field => $aliases) {
      $value = '';
      foreach ($aliases as $alias) {
        $value = get_post_meta($id, '_bubbahub_' . sanitize_key(str_replace(' ', '_', $alias)), true);
        if ($value !== '') break;
      }
      $out[$field] = is_scalar($value) ? (string) $value : $value;
    }
    return $out;
  }

  private static function format($post) {
    $fields = self::fields($post->ID);
    return [
      'id' => (int) $post->ID,
      'title' => get_the_title($post),
      'slug' => $post->post_name,
      'url' => get_permalink($post),
      'description' => apply_filters('the_content', $post->post_content),
      'excerpt' => get_the_excerpt($post),
      'author_id' => (int) $post->post_author,
      'status' => $post->post_status,
      'date' => $post->post_date,
      'modified' => $post->post_modified,
      'google_id' => (string) get_post_meta($post->ID, self::META_GOOGLE_ID, true),
      'owner_username' => (string) get_post_meta($post->ID, self::META_OWNER, true),
      'fields' => $fields,
      'latitude' => $fields['latitude'],
      'longitude' => $fields['longitude'],
    ];
  }
}
