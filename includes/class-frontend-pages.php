<?php
if (!defined('ABSPATH')) exit;

/**
 * BubbaHub frontend information architecture.
 * Creates only missing pages and never overwrites existing WordPress content.
 */
class BubbaHubFrontendPages {
  const OPTION = 'bubbahub_frontend_pages_v1';

  public static function definitions() {
    return [
      'home' => ['title'=>'BubbaHub','slug'=>'bubba-hub','page'=>'home','nav'=>'Home'],
      'directory' => ['title'=>'Directory','slug'=>'directory','page'=>'directory','nav'=>'Directory'],
      'events' => ['title'=>'Events','slug'=>'events','page'=>'events','nav'=>'Events'],
      'support' => ['title'=>'Support Hub','slug'=>'support','page'=>'support','nav'=>'Support'],
      'myhub' => ['title'=>'My Hub','slug'=>'my-hub','page'=>'dashboard','nav'=>'My Hub'],
      'compare' => ['title'=>'Compare Groups','slug'=>'compare-groups','page'=>'compare','nav'=>'Compare'],
      'antenatal' => ['title'=>'Antenatal Planner','slug'=>'antenatal','page'=>'antenatal','nav'=>'Antenatal'],
      'pricing' => ['title'=>'Membership Plans','slug'=>'membership','page'=>'pricing','nav'=>'Membership'],
      'signup' => ['title'=>'Join BubbaHub','slug'=>'join','page'=>'signup','nav'=>'Join'],
      'leader' => ['title'=>'Leader Portal','slug'=>'leader','page'=>'leader','nav'=>'Leader Portal'],
    ];
  }

  public static function ensure() {
    $saved = (array) get_option(self::OPTION, []);
    $changed = false;
    foreach (self::definitions() as $key => $definition) {
      $page_id = !empty($saved[$key]) ? absint($saved[$key]) : 0;
      if ($page_id && get_post($page_id) && get_post_type($page_id) === 'page') continue;

      $existing = get_page_by_path($definition['slug'], OBJECT, 'page');
      if ($existing) {
        $page_id = (int) $existing->ID;
      } else {
        $content = '[bubba_hub]';
        if ($definition['page'] === 'pricing') $content = '[bubbahub_pricing]';
        if ($definition['page'] === 'signup') $content = '[bubbahub_signup]';
        $page_id = wp_insert_post([
          'post_title' => $definition['title'],
          'post_name' => $definition['slug'],
          'post_content' => $content,
          'post_status' => 'publish',
          'post_type' => 'page',
          'comment_status' => 'closed',
        ], true);
        if (is_wp_error($page_id)) continue;
        $changed = true;
      }
      $saved[$key] = (int) $page_id;
      $changed = true;
    }
    if ($changed) update_option(self::OPTION, $saved, false);
  }

  public static function urls() {
    $saved = (array) get_option(self::OPTION, []);
    $urls = [];
    foreach (self::definitions() as $key => $definition) {
      $id = absint($saved[$key] ?? 0);
      $urls[$key] = $id ? get_permalink($id) : home_url('/' . trim($definition['slug'], '/') . '/');
    }
    return $urls;
  }

  public static function navigation() {
    $urls = self::urls();
    $items = ['home','directory','events','support','myhub','compare','antenatal','pricing','signup'];
    if (is_user_logged_in() && BubbaHub::get_user_type() === 'leader') $items[] = 'leader';
    $current = get_queried_object_id();
    ob_start();
    echo '<nav class="bh-site-nav" aria-label="BubbaHub primary navigation"><div class="bh-site-nav-inner">';
    echo '<a class="bh-site-logo" href="'.esc_url($urls['home']).'" aria-label="BubbaHub home"><strong>Bubba</strong><span>Hub</span></a>';
    echo '<div class="bh-site-links">';
    foreach ($items as $key) {
      $id = absint(get_option(self::OPTION, [])[$key] ?? 0);
      $active = $id && $id === $current ? ' is-active' : '';
      echo '<a class="bh-site-link'.$active.'" href="'.esc_url($urls[$key]).'">'.esc_html(self::definitions()[$key]['nav']).'</a>';
    }
    echo '</div>';
    echo '<div class="bh-site-actions">';
    if (is_user_logged_in()) {
      echo '<span class="bh-site-user">'.esc_html(wp_get_current_user()->display_name ?: wp_get_current_user()->user_login).'</span>';
      echo '<a class="bh-site-join" href="'.esc_url(wp_logout_url($urls['home'])).'">Sign out</a>';
    } else {
      echo '<a class="bh-site-join" href="'.esc_url($urls['signup']).'">Join BubbaHub</a>';
    }
    echo '</div><button class="bh-site-menu" type="button" aria-expanded="false" aria-controls="bh-mobile-links">Menu</button>';
    echo '</div><div id="bh-mobile-links" class="bh-mobile-links" hidden>';
    foreach ($items as $key) echo '<a href="'.esc_url($urls[$key]).'">'.esc_html(self::definitions()[$key]['nav']).'</a>';
    echo '</div></nav>';
    return ob_get_clean();
  }

  public static function shortcode() { return self::navigation(); }

  public static function register() {
    add_shortcode('bubbahub_navigation', [__CLASS__, 'shortcode']);
    if (!get_option(self::OPTION, false)) add_action('init', [__CLASS__, 'ensure'], 20);
  }
}
add_action('wp_loaded', ['BubbaHubFrontendPages','register'], 20);
