<?php
if (!defined('ABSPATH')) exit;
add_action('wp_enqueue_scripts', function () {
    $post = get_post();
    $has_shortcode = is_front_page() || (is_singular() && has_shortcode($post->post_content ?? '', 'bubba_hub'));
    if (!$has_shortcode) return;
    wp_dequeue_style('bubbahub'); wp_deregister_style('bubbahub');
    wp_dequeue_script('bubbahub'); wp_deregister_script('bubbahub');
    wp_enqueue_style('bubbahub', BUBBAHUB_URL . 'assests/css/app.css', [], BUBBAHUB_VERSION);
    wp_enqueue_script('bubbahub', BUBBAHUB_URL . 'assests/js/app.js', [], BUBBAHUB_VERSION, true);
    $pages = class_exists('BubbaHub') ? BubbaHub::page_map() : [];
    wp_localize_script('bubbahub', 'BubbaHubConfig', [
        'api'=>esc_url_raw(rest_url('bubbahub/v1/')), 'nonce'=>wp_create_nonce('wp_rest'),
        'initialGroup'=>sanitize_title((string)get_query_var('bubbahub_group')),
        'directoryUrl'=>esc_url_raw($pages['directory']['url'] ?? home_url('/directory/')),
        'pageUrls'=>array_map(function($x){return $x['url']??'';},$pages),
        'loggedIn'=>is_user_logged_in(),
        'user'=>is_user_logged_in()?['id'=>get_current_user_id(),'name'=>wp_get_current_user()->display_name,'roles'=>array_values((array)wp_get_current_user()->roles)]:null,
        'themeShell'=>current_theme_supports('bubbahub-shell'),'initialPage'=>'home','internalNav'=>false,
    ]);
    wp_enqueue_style('bubbahub-listings', BUBBAHUB_URL . 'assests/css/listings.css', ['bubbahub'], BUBBAHUB_VERSION);
    wp_enqueue_script('bubbahub-listings', BUBBAHUB_URL . 'assests/js/listings.js', ['bubbahub'], BUBBAHUB_VERSION, true);
}, 99);
