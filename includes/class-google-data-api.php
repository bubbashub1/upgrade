<?php
if (!defined('ABSPATH')) exit;

/**
 * Real directory data API used by the Figma-inspired front end.
 *
 * WordPress remains the runtime source of truth. Google Sheets is imported by
 * BubbaHubGoogleSync; this API never ships demo/sample rows and only exposes
 * published bh_group records.
 */
class BubbaHubGoogleDataApi {
    public static function boot() {
        add_action('rest_api_init', [__CLASS__, 'routes']);
    }

    public static function routes() {
        register_rest_route('bubbahub/v1', '/listings', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'listings'],
            'permission_callback' => '__return_true',
            'args' => [
                'page' => ['default' => 1, 'sanitize_callback' => 'absint'],
                'per_page' => ['default' => 24, 'sanitize_callback' => 'absint'],
                'search' => ['default' => '', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);
        register_rest_route('bubbahub/v1', '/listings/(?P<id>\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'listing'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('bubbahub/v1', '/google-sync', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [__CLASS__, 'sync'],
            'permission_callback' => function () { return current_user_can('manage_bubbahub'); },
        ]);
    }

    private static function value($id, $key, $default = '') {
        $v = get_post_meta($id, '_bubbahub_' . $key, true);
        return $v === '' ? $default : $v;
    }

    private static function item($post) {
        $id = $post->ID;
        return [
            'id' => $id,
            'google_id' => (string) get_post_meta($id, '_bubbahub_google_id', true),
            'title' => get_the_title($id),
            'description' => wp_strip_all_tags($post->post_content),
            'url' => get_permalink($id),
            'street' => (string) self::value($id, 'street'),
            'city' => (string) self::value($id, 'city'),
            'region' => (string) self::value($id, 'region'),
            'zip' => (string) self::value($id, 'zip'),
            'latitude' => (float) self::value($id, 'latitude', 0),
            'longitude' => (float) self::value($id, 'longitude', 0),
            'age_range' => (string) self::value($id, 'age_range'),
            'session_length' => (string) self::value($id, 'session_length'),
            'price' => (string) self::value($id, 'price'),
            'is_free' => self::value($id, 'is_free', '0') === '1',
            'day' => (string) self::value($id, 'day'),
            'timetable' => (string) self::value($id, 'timetable'),
            'business_hours' => (string) self::value($id, 'business_hours'),
            'term_time' => self::value($id, 'term_time', '0') === '1',
            'featured' => self::value($id, 'featured', '0') === '1',
            'sen' => (string) self::value($id, 'sen'),
            'category' => (string) self::value($id, 'category'),
            'tags' => (string) self::value($id, 'tags'),
            'website' => esc_url_raw(self::value($id, 'website')),
            'email' => sanitize_email(self::value($id, 'email')),
            'facebook' => esc_url_raw(self::value($id, 'facebook')),
            'instagram' => esc_url_raw(self::value($id, 'instagram')),
            'images' => self::value($id, 'images', []),
        ];
    }

    public static function listings(WP_REST_Request $request) {
        $page = max(1, (int) $request->get_param('page'));
        $per_page = min(100, max(1, (int) $request->get_param('per_page')));
        $args = [
            'post_type' => 'bh_group',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'orderby' => 'title',
            'order' => 'ASC',
            's' => (string) $request->get_param('search'),
        ];
        $q = new WP_Query($args);
        $items = array_map([__CLASS__, 'item'], $q->posts);
        return new WP_REST_Response([
            'success' => true,
            'items' => $items,
            'page' => $page,
            'per_page' => $per_page,
            'total' => (int) $q->found_posts,
            'pages' => (int) $q->max_num_pages,
            'source' => 'wordpress-bh_group',
            'google_sync' => get_option('bubbahub_google_sync_last', []),
        ], 200);
    }

    public static function listing(WP_REST_Request $request) {
        $post = get_post((int) $request['id']);
        if (!$post || $post->post_type !== 'bh_group' || $post->post_status !== 'publish') {
            return new WP_Error('not_found', 'Listing not found.', ['status' => 404]);
        }
        return rest_ensure_response(self::item($post));
    }

    public static function sync() {
        if (!class_exists('BubbaHubGoogleSync')) {
            return new WP_Error('sync_unavailable', 'Google sync is not available.', ['status' => 500]);
        }
        $ok = BubbaHubGoogleSync::sync();
        if (!$ok) return new WP_Error('sync_failed', 'Google Sheets sync returned no usable listings.', ['status' => 502]);
        return rest_ensure_response([
            'success' => true,
            'last_sync' => get_option('bubbahub_google_sync_last', []),
        ]);
    }
}
BubbaHubGoogleDataApi::boot();
