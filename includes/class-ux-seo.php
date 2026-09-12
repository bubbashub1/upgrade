<?php
if (!defined('ABSPATH')) exit;

/**
 * BubbaHub Stage 11 UX/SEO helpers.
 * Keeps WordPress as the data source and adds lightweight, theme-independent
 * metadata and accessibility improvements for live directory listings.
 */
class BubbaHubUXSEO {
  public static function register() {
    add_filter('document_title_parts', [__CLASS__, 'document_title'], 20);
    add_action('wp_head', [__CLASS__, 'meta'], 2);
    add_action('wp_footer', [__CLASS__, 'accessibility_script'], 99);
    add_filter('body_class', [__CLASS__, 'body_class']);
  }

  private static function is_listing() {
    return is_singular('bh_group');
  }

  public static function document_title($parts) {
    if (!self::is_listing()) return $parts;
    $title = get_the_title();
    if ($title) $parts['title'] = $title . ' | BubbaHub';
    return $parts;
  }

  public static function meta() {
    if (!self::is_listing()) return;
    $post = get_post();
    if (!$post) return;

    $description = wp_strip_all_tags(get_the_excerpt($post));
    if (!$description) $description = wp_trim_words(wp_strip_all_tags($post->post_content), 28, '…');
    if (!$description) $description = 'Find local baby, toddler, family and support activities on BubbaHub.';

    echo '<meta name="description" content="' . esc_attr($description) . '">\n';
    echo '<meta property="og:type" content="website">\n';
    echo '<meta property="og:title" content="' . esc_attr(get_the_title($post)) . ' | BubbaHub">\n';
    echo '<meta property="og:description" content="' . esc_attr($description) . '">\n';
    echo '<meta property="og:url" content="' . esc_url(get_permalink($post)) . '">\n';
  }

  public static function body_class($classes) {
    if (self::is_listing()) $classes[] = 'bh-listing-page';
    return $classes;
  }

  public static function accessibility_script() {
    if (is_admin()) return;
    echo '<script>(function(){document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll(".bh-site-menu").forEach(function(b){if(!b.hasAttribute("aria-label"))b.setAttribute("aria-label","Open navigation menu");});document.querySelectorAll("a[target=\"_blank\"]").forEach(function(a){var rel=a.getAttribute("rel")||"";if(rel.indexOf("noopener")===-1)a.setAttribute("rel",(rel+" noopener").trim());});});})();</script>';
  }
}
add_action('plugins_loaded', ['BubbaHubUXSEO', 'register'], 26);
