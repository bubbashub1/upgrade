<?php
if (!defined('ABSPATH')) exit;

class BubbaHubFrontendAssetsFix {
  public static function boot() {
    add_filter('script_loader_src', [__CLASS__, 'fix_src'], 99, 2);
    add_filter('style_loader_src', [__CLASS__, 'fix_src'], 99, 2);
    add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_leader_editor'], 100);
  }

  public static function fix_src($src, $handle) {
    if ($handle !== 'bubbahub') return $src;
    return str_replace('/assets/', '/assests/', $src);
  }

  public static function enqueue_leader_editor() {
    $post = get_post();
    $is_app = is_front_page() || (is_singular() && has_shortcode($post->post_content ?? '', 'bubba_hub'));
    if (!$is_app) return;
    wp_enqueue_script('bubbahub-leader-editor', BUBBAHUB_URL.'assests/js/leader-portal.js', ['bubbahub'], BUBBAHUB_VERSION, true);
  }
}
BubbaHubFrontendAssetsFix::boot();
