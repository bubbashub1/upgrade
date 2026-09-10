<?php
if (!defined('ABSPATH')) exit;

/**
 * Provides a safe bootstrap response for the frontend app.
 * This avoids the directory-field formatter taking down the public REST
 * bootstrap request while leaving the existing REST endpoints and Google Sync
 * untouched.
 */
add_filter('rest_pre_dispatch', function($result, $server, $request){
    if ($result !== null) return $result;
    if (!($request instanceof WP_REST_Request)) return $result;
    if ($request->get_route() !== '/bubbahub/v1/bootstrap') return $result;

    $make_posts = function($type, $limit) {
        $posts = get_posts([
            'post_type' => $type,
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);
        $out = [];
        foreach ($posts as $p) {
            $terms = [];
            foreach ((array) get_object_taxonomies($p->post_type) as $tax) {
                $names = wp_get_object_terms($p->ID, $tax, ['fields' => 'names']);
                if (!is_wp_error($names)) $terms = array_merge($terms, $names);
            }
            $out[] = [
                'id' => (int) $p->ID,
                'title' => get_the_title($p),
                'slug' => $p->post_name,
                'content' => apply_filters('the_content', $p->post_content),
                'excerpt' => get_the_excerpt($p),
                'image' => get_the_post_thumbnail_url($p, 'large'),
                'terms' => array_values(array_unique($terms)),
                'custom_fields' => [],
                'link' => get_permalink($p->ID),
            ];
        }
        return $out;
    };

    return new WP_REST_Response([
        'site' => [
            'name' => get_bloginfo('name'),
            'url' => home_url(),
        ],
        'groups' => $make_posts('bh_group', 12),
        'events' => $make_posts('bh_event', 12),
        'apps' => $make_posts('bh_app', 12),
        'specialists' => $make_posts('bh_specialist', 12),
        'articles' => $make_posts('bh_article', 12),
    ], 200);
}, 10, 3);
