<?php
if (!defined('ABSPATH')) exit;

add_action('after_setup_theme', function () {
    add_theme_support('wp-block-styles');
    add_theme_support('responsive-embeds');
    add_theme_support('editor-styles');
    add_theme_support('post-thumbnails');
    register_nav_menus(['primary' => __('Primary Menu', 'bubbahub-site-editor')]);
});

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('bubbahub-site-editor', get_theme_file_uri('/assets/css/theme.css'), [], '1.0.1');
});

add_filter('body_class', function ($classes) {
    $classes[] = 'bubbahub-site-editor';

    if (is_user_logged_in() && method_exists('BubbaHub', 'get_user_type')) {
        $classes[] = 'bubbahub-user-' . sanitize_html_class(BubbaHub::get_user_type());
    }

    // Stable hook for the public Leader Portal/editor. This lets the theme
    // restyle the plugin/Directorist output without overriding their templates.
    $request_path = trim((string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/');
    if ($request_path === 'leader' || strpos($request_path, 'leader/') === 0) {
        $classes[] = 'bubbahub-leader-portal';
    }

    return $classes;
});

add_action('wp_head', function () {
    echo '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">';
}, 1);

// Keep the plugin's full-width app experience intact when [bubba_hub] is used.
add_filter('body_class', function ($classes) {
    if (is_singular()) {
        $post = get_post();
        if ($post && has_shortcode($post->post_content, 'bubba_hub')) {
            $classes[] = 'bubbahub-full-app';
        }
    }
    return $classes;
});
