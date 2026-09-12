<?php
if (!defined('ABSPATH')) exit;

/**
 * BubbaHub Community Groups theme.
 * Uses the active WordPress theme for typography and primary colours while
 * retaining the soft Bubba Hub card treatment.
 */
class BubbaHubCommunityTheme {
  public static function register() {
    add_action('after_setup_theme', [__CLASS__, 'register_menus'], 20);
    add_action('wp_loaded', [__CLASS__, 'repair_page_map'], 29);
    add_action('wp_loaded', [__CLASS__, 'navigation_shortcode'], 30);
    add_action('wp_enqueue_scripts', [__CLASS__, 'assets'], 40);
  }

  public static function register_menus() {
    register_nav_menus([
      'bubbahub_primary' => __('Bubba Hub Primary Menu', 'bubbahub'),
      'bubbahub_mobile'  => __('Bubba Hub Mobile Menu', 'bubbahub'),
    ]);
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
    wp_enqueue_style('bubbahub-community-theme', BUBBAHUB_URL . 'assests/css/community-theme.css', ['bubbahub-figma-ui'], BUBBAHUB_VERSION);
    wp_enqueue_script('bubbahub-community-theme', BUBBAHUB_URL . 'assests/js/community-theme.js', [], BUBBAHUB_VERSION, true);
  }

  public static function navigation_shortcode() {
    remove_shortcode('bubbahub_navigation');
    add_shortcode('bubbahub_navigation', [__CLASS__, 'navigation']);
  }

  /** Render the WordPress-managed menu instead of a hard-coded inner navigation. */
  public static function navigation() {
    $primary = wp_nav_menu([
      'theme_location' => 'bubbahub_primary',
      'container' => false,
      'menu_class' => 'bh-community-wp-menu bh-community-wp-menu-primary',
      'menu_id' => 'bh-community-primary-menu',
      'fallback_cb' => false,
      'echo' => false,
      'depth' => 2,
    ]);
    $mobile = wp_nav_menu([
      'theme_location' => 'bubbahub_mobile',
      'container' => false,
      'menu_class' => 'bh-community-wp-menu bh-community-wp-menu-mobile',
      'menu_id' => 'bh-community-mobile-menu-list',
      'fallback_cb' => false,
      'echo' => false,
      'depth' => 2,
    ]);
    if (!$primary && $mobile) $primary = $mobile;
    if (!$primary) return '';
    if (!$mobile) $mobile = $primary;

    ob_start();
    ?>
    <nav class="bh-community-nav" aria-label="Bubba Hub site navigation">
      <div class="bh-community-nav-inner">
        <a class="bh-community-logo" href="<?php echo esc_url(home_url('/')); ?>" aria-label="Bubba Hub home">
          <span class="bh-community-logo-mark" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
          <span class="bh-community-logo-word">Bubba Hub</span>
        </a>
        <div class="bh-community-links">
          <?php echo $primary; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
        <button class="bh-community-menu" type="button" aria-expanded="false" aria-controls="bh-community-mobile-menu" aria-label="Open menu">Menu</button>
      </div>
      <div id="bh-community-mobile-menu" class="bh-community-mobile-menu" hidden>
        <?php echo $mobile; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
      </div>
    </nav>
    <?php
    return ob_get_clean();
  }
}
add_action('plugins_loaded', ['BubbaHubCommunityTheme', 'register'], 26);
