<?php
if (!defined('ABSPATH')) exit;

class BubbaHubDirectoryViews {
  public static function register() {
    add_action('rest_api_init', [__CLASS__, 'rest']);
    add_action('wp_enqueue_scripts', [__CLASS__, 'assets'], 55);
  }

  public static function rest() {
    register_rest_route('bubbahub/v1', '/directory', [
      'methods' => 'GET',
      'callback' => [__CLASS__, 'directory'],
      'permission_callback' => '__return_true',
    ]);
  }

  public static function directory() {
    $q = sanitize_text_field(wp_unslash($_GET['search'] ?? ''));
    $args = ['posts_per_page' => -1, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC'];
    if ($q !== '') $args['s'] = $q;
    $result = BubbaHubListings::query($args);
    return rest_ensure_response(['items' => $result['items'], 'total' => count($result['items'])]);
  }

  public static function assets() {
    if (is_admin()) return;
    $post = get_post();
    $is_directory = is_page('directory') || ($post && has_shortcode((string)$post->post_content, 'bubba_hub'));
    if (!$is_directory) return;
    wp_enqueue_script('bubbahub-directory-views', BUBBAHUB_URL.'assests/js/directory-views.js', [], BUBBAHUB_VERSION, true);
    wp_localize_script('bubbahub-directory-views', 'BubbaHubDirectoryConfig', [
      'api' => rest_url('bubbahub/v1/directory'),
      'nonce' => wp_create_nonce('wp_rest'),
      'mapTiles' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    ]);
    wp_add_inline_style('bubbahub-community-theme', self::css());
  }

  private static function css() {
    return '.bh-directory-toolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin:18px 0;padding:14px;border:1px solid color-mix(in srgb,currentColor 12%,transparent);border-radius:16px;background:color-mix(in srgb,currentColor 3%,transparent)}.bh-directory-toolbar button,.bh-directory-toolbar select{font:inherit;border:1px solid color-mix(in srgb,currentColor 16%,transparent);background:transparent;border-radius:10px;padding:9px 12px;cursor:pointer}.bh-directory-toolbar .is-active{font-weight:700;box-shadow:0 0 0 2px currentColor inset}.bh-directory-advanced{display:none;width:100%;padding:14px 0 2px;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.bh-directory-advanced.is-open{display:grid}.bh-directory-advanced label{display:flex;flex-direction:column;gap:5px;font-size:.92em}.bh-directory-advanced input,.bh-directory-advanced select{font:inherit;border:1px solid color-mix(in srgb,currentColor 16%,transparent);border-radius:9px;padding:9px;background:transparent}.bh-directory-results{--bh-cols:3;display:grid;grid-template-columns:repeat(var(--bh-cols),minmax(0,1fr));gap:18px}.bh-directory-results.is-list{display:grid;grid-template-columns:1fr}.bh-directory-card{min-width:0}.bh-directory-card .bh-card-image{display:block;width:100%;aspect-ratio:16/9;object-fit:cover;border-radius:14px;margin-bottom:12px}.bh-directory-map{display:none;min-height:520px;border-radius:18px;overflow:hidden;border:1px solid color-mix(in srgb,currentColor 12%,transparent)}.bh-directory-map.is-open{display:block}.bh-directory-layout.is-map{display:grid;grid-template-columns:minmax(0,1fr) minmax(340px,1fr);gap:18px}.bh-directory-layout.is-map .bh-directory-results{max-height:520px;overflow:auto;grid-template-columns:1fr}.bh-directory-pagination{display:flex;justify-content:center;gap:8px;flex-wrap:wrap;margin:22px 0}.bh-directory-pagination button{font:inherit;border:1px solid color-mix(in srgb,currentColor 16%,transparent);background:transparent;border-radius:9px;padding:8px 11px;cursor:pointer}.bh-directory-pagination .is-active{font-weight:700;box-shadow:0 0 0 2px currentColor inset}@media(max-width:900px){.bh-directory-results{--bh-cols:2}.bh-directory-advanced{grid-template-columns:repeat(2,minmax(0,1fr))}.bh-directory-layout.is-map{grid-template-columns:1fr}}@media(max-width:600px){.bh-directory-results{--bh-cols:1}.bh-directory-advanced{grid-template-columns:1fr}.bh-directory-toolbar{align-items:stretch}.bh-directory-toolbar>*{flex:1 1 auto}.bh-directory-map{min-height:380px}}';
  }
}
add_action('plugins_loaded', ['BubbaHubDirectoryViews','register'], 27);
