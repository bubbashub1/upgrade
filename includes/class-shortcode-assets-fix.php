<?php
if (!defined('ABSPATH')) exit;

add_action('wp_enqueue_scripts', function () {
    $post = get_post();
    $has_shortcode = is_front_page() || (is_singular() && has_shortcode($post->post_content ?? '', 'bubba_hub'));
    if (!$has_shortcode) return;

    wp_dequeue_style('bubbahub');
    wp_deregister_style('bubbahub');
    wp_dequeue_script('bubbahub');
    wp_deregister_script('bubbahub');

    wp_enqueue_style('bubbahub', BUBBAHUB_URL . 'assests/css/app.css', [], BUBBAHUB_VERSION);

    $pages = class_exists('BubbaHub') ? BubbaHub::page_map() : [];
    $directory_url = esc_url_raw($pages['directory']['url'] ?? home_url('/directory/'));

    $current_url = is_singular() ? get_permalink($post) : home_url('/');
    $current_path = trim((string) wp_parse_url($current_url, PHP_URL_PATH), '/');
    $directory_path = trim((string) wp_parse_url($directory_url, PHP_URL_PATH), '/');
    $is_directory = ($current_path !== '' && $directory_path !== '' && untrailingslashit('/' . $current_path) === untrailingslashit('/' . $directory_path));
    $initial_page = $is_directory ? 'directory' : 'home';

    $config = [
        'api' => esc_url_raw(rest_url('bubbahub/v1/')),
        'nonce' => wp_create_nonce('wp_rest'),
        'initialGroup' => sanitize_title((string) get_query_var('bubbahub_group')),
        'directoryUrl' => $directory_url,
        'pageUrls' => array_map(function($x){ return $x['url'] ?? ''; }, $pages),
        'loggedIn' => is_user_logged_in(),
        'user' => is_user_logged_in() ? [
            'id' => get_current_user_id(),
            'name' => wp_get_current_user()->display_name,
            'roles' => array_values((array) wp_get_current_user()->roles),
        ] : null,
        'themeShell' => current_theme_supports('bubbahub-shell'),
        'initialPage' => $initial_page,
        'internalNav' => false,
    ];

    if ($is_directory) {
        // The enhanced directory renderer is standalone on this page.
        // Do not load app.js here: its legacy renderer would overwrite the
        // enhanced search/cards immediately after first paint.
        wp_enqueue_script('bubbahub-listings', BUBBAHUB_URL . 'assests/js/listings.js', [], BUBBAHUB_VERSION, true);
        wp_localize_script('bubbahub-listings', 'BubbaHubConfig', $config);
        return;
    }

    wp_enqueue_script('bubbahub', BUBBAHUB_URL . 'assests/js/app.js', [], BUBBAHUB_VERSION, true);
    wp_localize_script('bubbahub', 'BubbaHubConfig', $config);

    // Keep the enhanced listing assets available for any future directory
    // view reached without a full page reload, but listings.js will not
    // mount on normal pages.
    wp_enqueue_style('bubbahub-listings', BUBBAHUB_URL . 'assests/css/listings.css', ['bubbahub'], BUBBAHUB_VERSION);
    wp_enqueue_script('bubbahub-listings', BUBBAHUB_URL . 'assests/js/listings.js', ['bubbahub'], BUBBAHUB_VERSION, true);
}, 99);
