<?php
if (!defined('ABSPATH')) exit;

/**
 * BubbaHub Community Groups theme.
 * Uses the supplied "Bubba Hub Community Groups Panel" artwork as the visual source of truth.
 */
class BubbaHubCommunityTheme {
  public static function register() {
    add_action('wp_loaded', [__CLASS__, 'repair_page_map'], 29);
    add_action('wp_loaded', [__CLASS__, 'navigation_shortcode'], 30);
    add_action('wp_enqueue_scripts', [__CLASS__, 'assets'], 40);
  }

  public static function repair_page_map() {
    if (!class_exists('BubbaHubFrontendPages')) return;
    $saved = (array) get_option('bubbahub_frontend_pages_v1', []);
    $defs = BubbaHubFrontendPages::definitions();
    $changed = false;
    foreach ($defs as $key => $definition) {
      $expected_slug = sanitize_title((string) ($definition['slug'] ?? $key));
      $id = absint($saved[$key] ?? 0);
      $post = $id ? get_post($id) : null;
      if ($post && $post->post_type === 'page' && $post->post_status !== 'trash' && $post->post_name === $expected_slug) continue;
      $existing = get_page_by_path($expected_slug, OBJECT, 'page');
      if ($existing) {
        $saved[$key] = (int) $existing->ID;
        $changed = true;
      }
    }
    if ($changed) update_option('bubbahub_frontend_pages_v1', $saved, false);
  }

  public static function assets() {
    if (is_admin()) return;
    $post = get_post();
    if (!is_front_page() && (!$post || !has_shortcode((string) $post->post_content, 'bubba_hub'))) return;

    wp_enqueue_style(
      'bubbahub-community-theme',
      BUBBAHUB_URL . 'assests/css/community-theme.css',
      ['bubbahub-figma-ui'],
      BUBBAHUB_VERSION
    );
    wp_enqueue_script(
      'bubbahub-community-theme',
      BUBBAHUB_URL . 'assests/js/community-theme.js',
      [],
      BUBBAHUB_VERSION,
      true
    );
  }

  public static function navigation_shortcode() {
    remove_shortcode('bubbahub_navigation');
    add_shortcode('bubbahub_navigation', [__CLASS__, 'navigation']);
  }

  public static function navigation() {
    $saved = (array) get_option('bubbahub_frontend_pages_v1', []);
    $defs = class_exists('BubbaHubFrontendPages') ? BubbaHubFrontendPages::definitions() : [];
    $url = static function($key) use ($saved, $defs) {
      $id = absint($saved[$key] ?? 0);
      if ($id && get_post($id)) return get_permalink($id);
      return home_url('/' . trim((string) ($defs[$key]['slug'] ?? $key), '/') . '/');
    };
    $current = get_queried_object_id();
    $items = ['whats_on','buddy','myhub','support','about','leader'];
    $labels = [
      'whats_on' => "What's On",
      'buddy'    => 'Bubba Buddy',
      'myhub'    => 'My Hub',
      'support'  => 'Support',
      'about'    => 'About Us',
      'leader'   => 'Leader Portal',
    ];
    ob_start();
    ?>
    <nav class="bh-community-nav" aria-label="Bubba Hub primary navigation">
      <div class="bh-community-nav-inner">
        <a class="bh-community-logo" href="<?php echo esc_url($url('home')); ?>" aria-label="Bubba Hub home">
          <span class="bh-community-logo-mark" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
          <span class="bh-community-logo-word">Bubba Hub</span>
        </a>
        <div class="bh-community-links">
          <?php foreach ($items as $key):
            $active = absint($saved[$key] ?? 0) === $current ? ' is-active' : '';
          ?>
            <a class="bh-community-link<?php echo esc_attr($active); ?>" href="<?php echo esc_url($url($key)); ?>"><?php echo esc_html($labels[$key]); ?></a>
          <?php endforeach; ?>
        </div>
        <button class="bh-community-search" type="button" aria-label="Search" data-community-focus-search>
          <span aria-hidden="true"></span>
        </button>
        <button class="bh-community-menu" type="button" aria-expanded="false" aria-controls="bh-community-mobile-menu">Menu</button>
      </div>
      <div id="bh-community-mobile-menu" class="bh-community-mobile-menu" hidden>
        <?php foreach ($items as $key): ?>
          <a href="<?php echo esc_url($url($key)); ?>"><?php echo esc_html($labels[$key]); ?></a>
        <?php endforeach; ?>
      </div>
    </nav>
    <?php
    return ob_get_clean();
  }
}
add_action('plugins_loaded', ['BubbaHubCommunityTheme', 'register'], 26);
