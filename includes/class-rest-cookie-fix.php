<?php
if (!defined('ABSPATH')) exit;

/**
 * BubbaHub REST cookie/nonce bridge.
 *
 * WordPress cookie-authenticated REST requests require a valid REST nonce.
 * The front-end app already uses fetch(), so this small bridge injects the
 * current logged-in user's X-WP-Nonce before the app makes its first request.
 * This fixes the "Cookie check failed" response without weakening REST auth.
 */
class BubbaHubRestCookieFix {
  public static function boot() {
    add_action('wp_head', [__CLASS__, 'output_nonce_bridge'], 1);
  }

  public static function output_nonce_bridge() {
    if (!is_user_logged_in()) return;

    $nonce = wp_create_nonce('wp_rest');
    if (!$nonce) return;

    $json = wp_json_encode($nonce);
    ?>
    <script id="bubbahub-rest-nonce-bridge">
    (function(nonce){
      if (!nonce || window.__BubbaHubRestNonceBridge) return;
      window.__BubbaHubRestNonceBridge = true;
      window.BubbaHubRestNonce = nonce;

      var originalFetch = window.fetch;
      if (typeof originalFetch !== 'function') return;

      window.fetch = function(input, init) {
        init = init || {};
        var url = typeof input === 'string' ? input : (input && input.url ? input.url : '');
        var sameOrigin = false;
        try { sameOrigin = new URL(url, window.location.href).origin === window.location.origin; } catch(e) {}

        if (sameOrigin && url.indexOf('/wp-json/') !== -1) {
          var headers = new Headers(init.headers || (input && input.headers) || {});
          if (!headers.has('X-WP-Nonce')) headers.set('X-WP-Nonce', nonce);
          init.headers = headers;
          init.credentials = init.credentials || 'same-origin';
        }
        return originalFetch.call(this, input, init);
      };
    })(<?php echo $json; ?>);
    </script>
    <?php
  }
}

BubbaHubRestCookieFix::boot();
