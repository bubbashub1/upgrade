<?php
if (!defined('ABSPATH')) exit;

/**
 * Detect BubbaHub app pages even when the WordPress page does not contain
 * the [bubba_hub] shortcode. This is important for the /directory/ page,
 * which can be created/managed separately from the BubbaHub shortcode page.
 */
function bubbahub_is_directory_page() {
    $post = get_post();
    $current_url = is_singular() && $post ? get_permalink($post) : home_url('/');
    $current_path = trim((string) wp_parse_url($current_url, PHP_URL_PATH), '/');

    if ($current_path === 'directory') return true;

    if (class_exists('BubbaHub') && method_exists('BubbaHub', 'page_map')) {
        $pages = BubbaHub::page_map();
        $directory_url = esc_url_raw($pages['directory']['url'] ?? '');
        if ($directory_url) {
            $directory_path = trim((string) wp_parse_url($directory_url, PHP_URL_PATH), '/');
            if ($directory_path !== '' && $current_path !== '' &&
                untrailingslashit('/' . $current_path) === untrailingslashit('/' . $directory_path)) {
                return true;
            }
        }
    }

    return false;
}

add_filter('body_class', function ($classes) {
    $post = get_post();
    $has_shortcode = is_front_page() || (is_singular() && has_shortcode($post->post_content ?? '', 'bubba_hub'));
    if ($has_shortcode || bubbahub_is_directory_page()) $classes[] = 'bubbahub-full-app';
    return $classes;
}, 20);

/**
 * If /directory/ is a normal WordPress page without the BubbaHub shortcode,
 * provide the app mount point so the enhanced directory renderer can take over.
 */
add_filter('the_content', function ($content) {
    if (is_admin() || !is_singular() || !in_the_loop() || !is_main_query()) return $content;
    if (!bubbahub_is_directory_page()) return $content;
    if (strpos($content, 'id="bubbahub-app"') !== false || strpos($content, "id='bubbahub-app'") !== false) return $content;
    return '<div id="bubbahub-app" class="bh-app"><noscript>Please enable JavaScript to use BubbaHub.</noscript></div>';
}, 20);

add_action('wp_enqueue_scripts', function () {
    $post = get_post();
    $is_directory = bubbahub_is_directory_page();
    $has_shortcode = is_front_page() || (is_singular() && has_shortcode($post->post_content ?? '', 'bubba_hub'));
    $is_group = (bool) get_query_var('bubbahub_group');
    $is_app = $has_shortcode || $is_directory || $is_group;
    if (!$is_app) return;

    wp_dequeue_style('bubbahub');
    wp_deregister_style('bubbahub');
    wp_dequeue_script('bubbahub');
    wp_deregister_script('bubbahub');

    wp_enqueue_style('bubbahub', BUBBAHUB_URL . 'assests/css/app.css', [], BUBBAHUB_VERSION);

    $pages = class_exists('BubbaHub') ? BubbaHub::page_map() : [];
    $directory_url = esc_url_raw($pages['directory']['url'] ?? home_url('/directory/'));
    $current_url = is_singular() ? get_permalink($post) : home_url('/');
    $current_path = trim((string) wp_parse_url($current_url, PHP_URL_PATH), '/');
    $initial_page = $is_group ? 'group' : ($is_directory ? 'directory' : 'home');

    if (!$is_directory && !$is_group) {
        foreach ($pages as $page_key => $page_config) {
            $page_url = esc_url_raw($page_config['url'] ?? '');
            if (!$page_url) continue;
            $page_path = trim((string) wp_parse_url($page_url, PHP_URL_PATH), '/');
            if ($page_path !== '' && $current_path !== '' &&
                untrailingslashit('/' . $current_path) === untrailingslashit('/' . $page_path)) {
                $initial_page = sanitize_key($page_key);
                break;
            }
        }
    }

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

    // Group URLs get the dedicated full listing page so the directory cards,
    // Google/Sheets data and leader editor remain separate from the detail layout.
    if ($is_group) {
        wp_enqueue_style('bubbahub-group-page', BUBBAHUB_URL . 'assests/css/group-page.css', ['bubbahub'], BUBBAHUB_VERSION);
        wp_enqueue_script('bubbahub-group-page', BUBBAHUB_URL . 'assests/js/group-page.js', [], BUBBAHUB_VERSION, true);
        wp_localize_script('bubbahub-group-page', 'BubbaHubConfig', $config);
        return;
    }

    // The Leader Portal has its own renderer. Do NOT load app.js or listings.js
    // on this page: both can render #bubbahub-app and overwrite the editor UI.
    if ($initial_page === 'leader') {
        wp_enqueue_style('bubbahub-leader-portal', BUBBAHUB_URL . 'assests/css/leader-portal.css', ['bubbahub'], BUBBAHUB_VERSION);
        wp_enqueue_script('bubbahub-leader-editor', BUBBAHUB_URL . 'assests/js/leader-portal.js', [], BUBBAHUB_VERSION, true);
        wp_localize_script('bubbahub-leader-editor', 'BubbaHubConfig', $config);
        return;
    }

    if ($initial_page === 'directory') {
        // Directory pages need BOTH the shared app shell and directory-specific CSS.
        wp_enqueue_style('bubbahub-listings', BUBBAHUB_URL . 'assests/css/listings.css', ['bubbahub'], BUBBAHUB_VERSION);
        wp_enqueue_script('bubbahub-listings', BUBBAHUB_URL . 'assests/js/listings.js', [], BUBBAHUB_VERSION, true);
        wp_localize_script('bubbahub-listings', 'BubbaHubConfig', $config);
        return;
    }

    wp_enqueue_script('bubbahub', BUBBAHUB_URL . 'assests/js/app.js', [], BUBBAHUB_VERSION, true);
    wp_localize_script('bubbahub', 'BubbaHubConfig', $config);
    wp_enqueue_style('bubbahub-listings', BUBBAHUB_URL . 'assests/css/listings.css', ['bubbahub'], BUBBAHUB_VERSION);
    wp_enqueue_script('bubbahub-listings', BUBBAHUB_URL . 'assests/js/listings.js', ['bubbahub'], BUBBAHUB_VERSION, true);
}, 99);
