<?php
if (!defined('ABSPATH')) exit;

/**
 * BubbaHub Figma-derived visual foundation.
 *
 * This ports the design tokens and responsive shell behaviour from the supplied
 * Figma Make source into the WordPress application without shipping the React app.
 */
class BubbaHubFigmaUI {
  public static function register() {
    add_action('wp_enqueue_scripts', [__CLASS__, 'assets'], 30);
  }

  public static function assets() {
    if (!self::is_bubbahub_app()) return;

    wp_register_style('bubbahub-figma-ui', false, [], defined('BUBBAHUB_VERSION') ? BUBBAHUB_VERSION : null);
    wp_enqueue_style('bubbahub-figma-ui');

    $css = <<<'CSS'
:root {
  --bh-ink: #1e3330;
  --bh-brand: #18b97a;
  --bh-brand-dark: #137d59;
  --bh-mint: #e3f5ee;
  --bh-page: #f4f6f4;
  --bh-card: #ffffff;
  --bh-border: #e4edea;
  --bh-border-strong: #d6e3df;
  --bh-muted: #668785;
  --bh-cream: #faedcd;
  --bh-orange: #bc6c25;
  --bh-radius-card: 24px;
  --bh-radius-control: 12px;
  --bh-shadow: 0 8px 30px rgba(30,51,48,.07);
}

.bh-app,
.bh-public-pricing,
.bh-public-signup,
.bh-listing-editor,
.bh-directory-search {
  color: var(--bh-ink);
  font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}

.bh-app a,
.bh-public-pricing a,
.bh-public-signup a { color: var(--bh-brand-dark); }

.bh-app button,
.bh-app input,
.bh-app select,
.bh-app textarea,
.bh-public-pricing button,
.bh-public-signup button,
.bh-public-signup input,
.bh-public-signup select,
.bh-public-signup textarea { font: inherit; }

.bh-figma-card {
  background: var(--bh-card);
  border: 1px solid var(--bh-border);
  border-radius: var(--bh-radius-card);
  box-shadow: var(--bh-shadow);
}

.bh-figma-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 10px;
  border-radius: 999px;
  background: var(--bh-cream);
  color: var(--bh-orange);
  font-size: 10px;
  font-weight: 800;
  letter-spacing: .08em;
  text-transform: uppercase;
}

.bh-figma-primary {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 42px;
  padding: 0 16px;
  border: 0;
  border-radius: var(--bh-radius-control);
  background: var(--bh-brand);
  color: #fff !important;
  font-weight: 800;
  text-decoration: none !important;
  cursor: pointer;
  transition: transform .15s ease, background .15s ease, box-shadow .15s ease;
}
.bh-figma-primary:hover { background: var(--bh-brand-dark); transform: translateY(-1px); box-shadow: 0 6px 18px rgba(24,185,122,.2); }

@media (max-width: 820px) {
  .bh-figma-card { border-radius: 18px; }
}
CSS;

    wp_add_inline_style('bubbahub-figma-ui', $css);
  }

  private static function is_bubbahub_app() {
    if (is_admin()) return false;
    if (is_front_page()) return true;
    $post = get_post();
    return $post && has_shortcode((string) $post->post_content, 'bubba_hub');
  }
}

add_action('plugins_loaded', ['BubbaHubFigmaUI', 'register'], 25);
