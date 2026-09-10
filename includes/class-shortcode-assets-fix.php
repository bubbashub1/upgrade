<?php
if (!defined('ABSPATH')) exit;

/**
 * BubbaHub shortcode asset compatibility fix.
 *
 * The current plugin stores its frontend assets in /assests/ (legacy spelling),
 * while class-bubbahub.php was enqueueing /assets/. That leaves the shortcode
 * shell empty because app.js never loads. Keep this isolated so Google Sync and
 * directory imports are untouched.
 */
add_action('wp_enqueue_scripts', function () {
    $post = get_post();
    $has_shortcode = is_front_page() || (is_singular() && has_shortcode($post->post_content ?? '', 'bubba_hub'));
    if (!$has_shortcode) return;

    wp_dequeue_style('bubbahub');
    wp_deregister_style('bubbahub');
    wp_dequeue_script('bubbahub');
    wp_deregister_script('bubbahub');

    wp_enqueue_style(
        'bubbahub',
        BUBBAHUB_URL . 'assests/css/app.css',
        [],
        BUBBAHUB_VERSION
    );

    wp_enqueue_script(
        'bubbahub',
        BUBBAHUB_URL . 'assests/js/app.js',
        [],
        BUBBAHUB_VERSION,
        true
    );
}, 99);
