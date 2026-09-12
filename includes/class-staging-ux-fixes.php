<?php
if (!defined('ABSPATH')) exit;

/**
 * BubbaHub staging UX fixes.
 * Keeps the Figma pages as real WordPress pages while ensuring each managed
 * page carries the correct [bubba_hub page="..."] state. Also removes only
 * the exact demo records shipped by the old seed routine.
 */
final class BubbaHubStagingUXFixes {
    const VERSION = '1.0.0';

    public static function boot() {
        add_action('wp_loaded', [__CLASS__, 'repair_managed_pages'], 30);
        add_action('wp_loaded', [__CLASS__, 'remove_legacy_demo_records'], 31);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_polish'], 30);
    }

    public static function repair_managed_pages() {
        $map = [
            'home' => 'home',
            'whats_on' => 'directory',
            'buddy' => 'buddy',
            'myhub' => 'dashboard',
            'support' => 'support',
            'about' => 'about',
            'leader' => 'leader',
            'events' => 'events',
            'compare' => 'compare',
            'antenatal' => 'antenatal',
            'pricing' => 'pricing',
            'signup' => 'signup',
        ];

        $saved = (array) get_option('bubbahub_frontend_pages_v1', []);
        $changed = false;
        foreach ($map as $key => $page) {
            $id = absint($saved[$key] ?? 0);
            if (!$id) continue;
            $post = get_post($id);
            if (!$post || $post->post_type !== 'page' || $post->post_status === 'trash') continue;

            $shortcode = in_array($page, ['pricing', 'signup'], true)
                ? ($page === 'pricing' ? '[bubbahub_pricing]' : '[bubbahub_signup]')
                : '[bubba_hub page="' . $page . '"]';

            // Only repair pages managed by our page registry. Never overwrite
            // unrelated content on pages outside the registry.
            if (trim($post->post_content) !== $shortcode) {
                wp_update_post([
                    'ID' => $id,
                    'post_content' => $shortcode,
                ]);
                $changed = true;
            }
        }
        if ($changed) flush_rewrite_rules(false);
    }

    public static function remove_legacy_demo_records() {
        if (get_option('bubbahub_legacy_demo_cleanup_420')) return;

        // These are the exact records created by the old built-in seed().
        // Do not delete any other real user content.
        $demo = [
            'bh_group' => ['Little Explorers', 'Splash Tots', 'Tiny Yogis', 'Wild & Free'],
            'bh_event' => ['Baby Music Morning', 'Family Swim', 'Park Playdate'],
            'bh_app' => ['Huckleberry', 'Ovia Pregnancy', 'Wonder Weeks', 'Tinybeans'],
        ];

        foreach ($demo as $post_type => $titles) {
            foreach ($titles as $title) {
                $ids = get_posts([
                    'post_type' => $post_type,
                    'post_status' => 'any',
                    'title' => $title,
                    'posts_per_page' => -1,
                    'fields' => 'ids',
                    'no_found_rows' => true,
                ]);
                foreach ($ids as $id) wp_delete_post((int) $id, true);
            }
        }

        update_option('bubbahub_legacy_demo_cleanup_420', 1, false);
    }

    public static function enqueue_polish() {
        $post = get_post();
        $is_app = is_front_page() || (is_singular() && has_shortcode($post->post_content ?? '', 'bubba_hub'));
        if (!$is_app) return;

        wp_enqueue_style(
            'bubbahub-figma-polish',
            BUBBAHUB_URL . 'assests/css/figma-polish.css',
            ['bubbahub'],
            BUBBAHUB_VERSION
        );
        wp_enqueue_script(
            'bubbahub-figma-polish',
            BUBBAHUB_URL . 'assests/js/figma-polish.js',
            ['bubbahub'],
            BUBBAHUB_VERSION,
            true
        );
    }
}

add_action('plugins_loaded', ['BubbaHubStagingUXFixes', 'boot'], 30);
