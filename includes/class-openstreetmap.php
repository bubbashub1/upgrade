<?php
if (!defined('ABSPATH')) exit;

/**
 * BubbaHub OpenStreetMap integration.
 * Uses Leaflet + OpenStreetMap tiles and Nominatim geocoding without Google Maps.
 */
class BubbaHubOpenStreetMap {
  const NONCE_ACTION = 'bubbahub_osm';
  const TILE_URL = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
  const GEOCODER_URL = 'https://nominatim.openstreetmap.org/search';

  public static function register() {
    add_shortcode('bubbahub_osm_map', [__CLASS__, 'shortcode']);
    add_filter('the_content', [__CLASS__, 'append_to_group_content'], 30);
    add_action('rest_api_init', [__CLASS__, 'register_rest']);
  }

  public static function register_rest() {
    register_rest_route('bubbahub/v1', '/geo/geocode', [
      'methods' => 'POST',
      'callback' => [__CLASS__, 'geocode'],
      'permission_callback' => function () { return is_user_logged_in(); },
    ]);
    register_rest_route('bubbahub/v1', '/geo/reverse', [
      'methods' => 'POST',
      'callback' => [__CLASS__, 'reverse'],
      'permission_callback' => function () { return is_user_logged_in(); },
    ]);
  }

  public static function append_to_group_content($content) {
    if (is_admin() || !is_singular('bh_group') || !in_the_loop() || !is_main_query()) return $content;
    return $content . self::render_map(get_the_ID());
  }

  public static function shortcode($atts = []) {
    $atts = shortcode_atts(['id' => 0, 'height' => '360'], $atts, 'bubbahub_osm_map');
    $id = absint($atts['id']) ?: get_the_ID();
    return self::render_map($id, absint($atts['height']) ?: 360);
  }

  private static function render_map($id, $height = 360) {
    if (!$id || !class_exists('BubbaHubListings')) return '';
    $item = BubbaHubListings::get($id);
    if (!$item || $item['status'] !== 'publish') return '';
    $lat = self::valid_lat($item['latitude']) ? (float)$item['latitude'] : null;
    $lng = self::valid_lng($item['longitude']) ? (float)$item['longitude'] : null;
    if ($lat === null || $lng === null) {
      return '<div class="bh-osm-empty" role="status">Location map unavailable for this listing.</div>';
    }
    $map_id = 'bh-osm-map-' . $id . '-' . wp_rand(1000, 99999);
    $data = wp_json_encode([
      'id' => $id,
      'lat' => $lat,
      'lng' => $lng,
      'title' => $item['title'],
      'url' => $item['url'],
      'tileUrl' => self::TILE_URL,
    ]);
    ob_start();
    ?>
    <div class="bh-osm-wrap" data-map="<?php echo esc_attr($map_id); ?>">
      <div id="<?php echo esc_attr($map_id); ?>" class="bh-osm-map" style="height:<?php echo esc_attr($height); ?>px" aria-label="Map showing <?php echo esc_attr($item['title']); ?> location"></div>
      <small class="bh-osm-attribution">© OpenStreetMap contributors</small>
    </div>
    <style>
      .bh-osm-wrap{position:relative;width:100%;margin:20px 0;border-radius:16px;overflow:hidden;background:#eef2f0;border:1px solid #dfe7e3}
      .bh-osm-map{width:100%;min-height:240px}
      .bh-osm-attribution{display:block;padding:5px 9px;background:#fff;color:#65756e;font-size:11px}
      .bh-osm-empty{padding:14px 16px;border-radius:12px;background:#f4f7f5;color:#65756e}
      .bh-osm-popup a{font-weight:700}
    </style>
    <script>
    (function(){
      const data=<?php echo $data; ?>;
      function load(){
        if(typeof window.L==='undefined'){
          if(document.querySelector('link[data-bh-leaflet]')) return;
          const css=document.createElement('link');css.rel='stylesheet';css.href='https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';css.dataset.bhLeaflet='1';document.head.appendChild(css);
          const js=document.createElement('script');js.src='https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';js.dataset.bhLeaflet='1';js.onload=init;document.head.appendChild(js);
        } else init();
      }
      function init(){
        const el=document.getElementById(<?php echo wp_json_encode($map_id); ?>);if(!el||el.dataset.ready==='1'||typeof window.L==='undefined')return;el.dataset.ready='1';
        const map=L.map(el,{scrollWheelZoom:false}).setView([data.lat,data.lng],14);
        L.tileLayer(data.tileUrl,{maxZoom:19,attribution:'© OpenStreetMap contributors'}).addTo(map);
        const marker=L.marker([data.lat,data.lng]).addTo(map);
        marker.bindPopup('<div class="bh-osm-popup"><strong>'+escapeHtml(data.title)+'</strong><br><a href="'+escapeAttr(data.url)+'">View listing</a></div>');
      }
      function escapeHtml(s){return String(s||'').replace(/[&<>\"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;'}[c]||c;});}
      function escapeAttr(s){return String(s||'').replace(/\"/g,'&quot;');}
      if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',load);else load();
    })();
    </script>
    <?php
    return ob_get_clean();
  }

  public static function geocode($request) {
    $address = sanitize_text_field($request->get_param('address'));
    if ($address === '') return new WP_Error('invalid_address', 'Enter an address to find its location.', ['status'=>400]);
    return self::nominatim_request(self::GEOCODER_URL, ['q'=>$address, 'format'=>'jsonv2', 'limit'=>1, 'addressdetails'=>1]);
  }

  public static function reverse($request) {
    $lat = $request->get_param('lat');
    $lng = $request->get_param('lng');
    if (!self::valid_lat($lat) || !self::valid_lng($lng)) return new WP_Error('invalid_coordinates', 'Valid latitude and longitude are required.', ['status'=>400]);
    return self::nominatim_request('https://nominatim.openstreetmap.org/reverse', ['lat'=>(float)$lat, 'lon'=>(float)$lng, 'format'=>'jsonv2', 'addressdetails'=>1]);
  }

  private static function nominatim_request($url, $query) {
    $cache_key = 'bh_osm_' . md5($url . '|' . wp_json_encode($query));
    $cached = get_transient($cache_key);
    if ($cached !== false) return rest_ensure_response($cached);
    $response = wp_remote_get(add_query_arg($query, $url), [
      'timeout' => 12,
      'headers' => ['Accept'=>'application/json', 'User-Agent'=>'BubbaHub WordPress plugin; '.home_url('/')],
    ]);
    if (is_wp_error($response)) return new WP_Error('geocoder_unavailable', 'The OpenStreetMap geocoder is temporarily unavailable.', ['status'=>502]);
    $code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);
    if ($code < 200 || $code >= 300 || !is_array($body)) return new WP_Error('geocoder_error', 'OpenStreetMap could not process this location.', ['status'=>502]);
    $result = isset($body[0]) ? $body[0] : $body;
    if (!$result) return new WP_Error('not_found', 'No matching location was found.', ['status'=>404]);
    $lat = isset($result['lat']) ? (float)$result['lat'] : null;
    $lng = isset($result['lon']) ? (float)$result['lon'] : null;
    if (!self::valid_lat($lat) || !self::valid_lng($lng)) return new WP_Error('invalid_result', 'The geocoder returned invalid coordinates.', ['status'=>502]);
    $out = ['lat'=>(string)$lat, 'lng'=>(string)$lng, 'display_name'=>sanitize_text_field($result['display_name']??''), 'address'=>$result['address']??[]];
    set_transient($cache_key, $out, DAY_IN_SECONDS);
    return rest_ensure_response($out);
  }

  private static function valid_lat($value) { return is_numeric($value) && (float)$value >= -90 && (float)$value <= 90; }
  private static function valid_lng($value) { return is_numeric($value) && (float)$value >= -180 && (float)$value <= 180; }
}

add_action('plugins_loaded', function(){
  if (class_exists('BubbaHubOpenStreetMap')) BubbaHubOpenStreetMap::register();
}, 25);
