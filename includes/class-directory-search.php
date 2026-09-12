<?php
if (!defined('ABSPATH')) exit;

/**
 * BubbaHub directory search/filter service.
 *
 * Uses the bh_group WordPress records directly. Filtering and pagination happen
 * server-side; the browser never needs to download the complete directory.
 */
class BubbaHubDirectorySearch {
  const REST_NAMESPACE = 'bubbahub/v1';
  const REST_ROUTE = '/directory/search';
  const DEFAULT_PER_PAGE = 12;
  const MAX_PER_PAGE = 48;

  public static function register() {
    add_action('rest_api_init', [__CLASS__, 'register_rest']);
    add_shortcode('bubbahub_directory', [__CLASS__, 'shortcode']);
  }

  public static function register_rest() {
    register_rest_route(self::REST_NAMESPACE, self::REST_ROUTE, [
      'methods' => 'GET',
      'callback' => [__CLASS__, 'rest_search'],
      'permission_callback' => '__return_true',
      'args' => [
        'q' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
        'location' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
        'city' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
        'postcode' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
        'category' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
        'age' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
        'price' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
        'day' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
        'featured' => ['type' => 'boolean'],
        'lat' => ['type' => 'number'],
        'lng' => ['type' => 'number'],
        'radius' => ['type' => 'number'],
        'page' => ['type' => 'integer', 'minimum' => 1],
        'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => self::MAX_PER_PAGE],
        'sort' => ['type' => 'string', 'sanitize_callback' => 'sanitize_key'],
      ],
    ]);
  }

  public static function rest_search($request) {
    $params = $request->get_params();
    $result = self::search($params);
    return rest_ensure_response($result);
  }

  public static function search($params = []) {
    global $wpdb;

    $page = max(1, absint($params['page'] ?? 1));
    $per_page = min(self::MAX_PER_PAGE, max(1, absint($params['per_page'] ?? self::DEFAULT_PER_PAGE)));
    $sort = sanitize_key($params['sort'] ?? 'relevance');
    $q = sanitize_text_field($params['q'] ?? '');
    $location = sanitize_text_field($params['location'] ?? '');
    $city = sanitize_text_field($params['city'] ?? '');
    $postcode = sanitize_text_field($params['postcode'] ?? '');
    $category = sanitize_text_field($params['category'] ?? '');
    $age = sanitize_text_field($params['age'] ?? '');
    $price = sanitize_text_field($params['price'] ?? '');
    $day = sanitize_text_field($params['day'] ?? '');
    $featured = isset($params['featured']) && filter_var($params['featured'], FILTER_VALIDATE_BOOLEAN);
    $lat = isset($params['lat']) ? (float) $params['lat'] : null;
    $lng = isset($params['lng']) ? (float) $params['lng'] : null;
    $radius = isset($params['radius']) ? (float) $params['radius'] : null;

    if ($lat !== null && ($lat < -90 || $lat > 90)) $lat = null;
    if ($lng !== null && ($lng < -180 || $lng > 180)) $lng = null;
    $allowed_radii = [5, 10, 15, 25, 50];
    if ($radius !== null && !in_array((int) $radius, $allowed_radii, true)) $radius = null;
    if (($lat === null || $lng === null) || $radius === null) {
      $radius = null;
    }

    $posts = $wpdb->posts;
    $meta = $wpdb->postmeta;
    $where = ["p.post_type = 'bh_group'", "p.post_status = 'publish'"];
    $join = '';
    $values = [];

    if ($q !== '') {
      $like = '%' . $wpdb->esc_like($q) . '%';
      $where[] = '(p.post_title LIKE %s OR p.post_content LIKE %s OR EXISTS (SELECT 1 FROM ' . $meta . ' mq WHERE mq.post_id=p.ID AND mq.meta_key IN (\'_bubbahub_tags\',\'_bubbahub_tag\',\'_bubbahub_category\',\'_bubbahub_city\',\'_bubbahub_town\',\'_bubbahub_region\',\'_bubbahub_age_range\') AND mq.meta_value LIKE %s))';
      array_push($values, $like, $like, $like);
    }

    self::add_meta_filter($where, $values, $meta, ['_bubbahub_city','_bubbahub_town','_bubbahub_postal_town'], $city);
    self::add_meta_filter($where, $values, $meta, ['_bubbahub_street','_bubbahub_address','_bubbahub_city','_bubbahub_town','_bubbahub_region','_bubbahub_county','_bubbahub_zip','_bubbahub_postcode','_bubbahub_post_code','_bubbahub_postal_code'], $location);
    self::add_meta_filter($where, $values, $meta, ['_bubbahub_zip','_bubbahub_postcode','_bubbahub_post_code','_bubbahub_postal_code'], $postcode);
    self::add_meta_filter($where, $values, $meta, ['_bubbahub_category','_bubbahub_categories'], $category);
    self::add_meta_filter($where, $values, $meta, ['_bubbahub_age_range','_bubbahub_age_range'], $age);

    if ($day !== '') {
      $like = '%' . $wpdb->esc_like($day) . '%';
      $where[] = "EXISTS (SELECT 1 FROM {$meta} md WHERE md.post_id=p.ID AND md.meta_key IN ('_bubbahub_day','_bubbahub_days','_bubbahub_timetable','_bubbahub_business_hours') AND md.meta_value LIKE %s)";
      $values[] = $like;
    }

    if ($price !== '') {
      $price_key = sanitize_key($price);
      if ($price_key === 'free') {
        $where[] = "EXISTS (SELECT 1 FROM {$meta} mp WHERE mp.post_id=p.ID AND mp.meta_key IN ('_bubbahub_is_free','_bubbahub_free','_bubbahub_free_session') AND LOWER(mp.meta_value) IN ('1','yes','true','free'))";
      } elseif ($price_key === 'paid') {
        $where[] = "NOT EXISTS (SELECT 1 FROM {$meta} mp WHERE mp.post_id=p.ID AND mp.meta_key IN ('_bubbahub_is_free','_bubbahub_free','_bubbahub_free_session') AND LOWER(mp.meta_value) IN ('1','yes','true','free'))";
      }
    }

    if ($featured) {
      $where[] = "EXISTS (SELECT 1 FROM {$meta} mf WHERE mf.post_id=p.ID AND mf.meta_key IN ('_bubbahub_featured','_bubbahub_is_featured','_bubbahub_featured_listing') AND LOWER(mf.meta_value) IN ('1','yes','true','featured'))";
    }

    $distance_sql = 'NULL';
    if ($lat !== null && $lng !== null && $radius !== null) {
      // Bounding box first, then Haversine. This avoids calculating distance
      // for every listing and remains compatible with standard WordPress MySQL.
      $lat_delta = $radius / 69.0;
      $cos = max(0.01, cos(deg2rad($lat)));
      $lng_delta = $radius / (69.0 * $cos);
      $min_lat = $lat - $lat_delta;
      $max_lat = $lat + $lat_delta;
      $min_lng = $lng - $lng_delta;
      $max_lng = $lng + $lng_delta;
      $lat_expr = "CAST(COALESCE(NULLIF(m_lat.meta_value,''),NULLIF(m_lat2.meta_value,''),NULLIF(m_lat3.meta_value,'')) AS DECIMAL(10,7))";
      $lng_expr = "CAST(COALESCE(NULLIF(m_lng.meta_value,''),NULLIF(m_lng2.meta_value,''),NULLIF(m_lng3.meta_value,'')) AS DECIMAL(10,7))";
      $join .= " LEFT JOIN {$meta} m_lat ON m_lat.post_id=p.ID AND m_lat.meta_key='_bubbahub_latitude' LEFT JOIN {$meta} m_lat2 ON m_lat2.post_id=p.ID AND m_lat2.meta_key='_bubbahub_lat' LEFT JOIN {$meta} m_lat3 ON m_lat3.post_id=p.ID AND m_lat3.meta_key='_bubbahub_manual_latitude'";
      $join .= " LEFT JOIN {$meta} m_lng ON m_lng.post_id=p.ID AND m_lng.meta_key='_bubbahub_longitude' LEFT JOIN {$meta} m_lng2 ON m_lng2.post_id=p.ID AND m_lng2.meta_key='_bubbahub_lng' LEFT JOIN {$meta} m_lng3 ON m_lng3.post_id=p.ID AND m_lng3.meta_key='_bubbahub_manual_lng'";
      $where[] = "{$lat_expr} BETWEEN %f AND %f";
      $where[] = "{$lng_expr} BETWEEN %f AND %f";
      array_push($values, $min_lat, $max_lat, $min_lng, $max_lng);
      $distance_sql = "(3958.7613 * ACOS(LEAST(1, GREATEST(-1, COS(RADIANS(%f)) * COS(RADIANS({$lat_expr})) * COS(RADIANS({$lng_expr}) - RADIANS(%f)) + SIN(RADIANS(%f)) * SIN(RADIANS({$lat_expr})) ))))";
    }

    $where_sql = implode(' AND ', $where);
    $from = "FROM {$posts} p {$join}";
    $count_sql = "SELECT COUNT(DISTINCT p.ID) {$from} WHERE {$where_sql}";
    $count_values = $values;
    if ($distance_sql !== 'NULL') {
      $distance_values = [$lat, $lng, $lng, $lat];
      // The distance expression contains placeholders plus the where values.
      $count_sql = "SELECT COUNT(DISTINCT p.ID) {$from} WHERE {$where_sql}";
    }
    $total = (int) $wpdb->get_var($wpdb->prepare($count_sql, $count_values));

    $order_sql = 'p.post_date DESC';
    if ($sort === 'newest') $order_sql = 'p.post_date DESC';
    if ($sort === 'oldest') $order_sql = 'p.post_date ASC';
    if ($sort === 'title') $order_sql = 'p.post_title ASC';
    if ($distance_sql !== 'NULL') $order_sql = $distance_sql . ' ASC, p.post_title ASC';

    $select_distance = $distance_sql === 'NULL' ? 'NULL AS distance_miles' : $distance_sql . ' AS distance_miles';
    $sql = "SELECT DISTINCT p.ID, {$select_distance} {$from} WHERE {$where_sql} ORDER BY {$order_sql} LIMIT %d OFFSET %d";
    $query_values = $values;
    if ($distance_sql !== 'NULL') {
      array_unshift($query_values, $lat, $lng, $lng, $lat);
      // ORDER BY uses the same expression, so it needs another set of values.
      $query_values = array_merge($query_values, [$lat, $lng, $lng, $lat]);
    }
    $query_values[] = $per_page;
    $query_values[] = ($page - 1) * $per_page;
    $prepared = $wpdb->prepare($sql, $query_values);
    $rows = $wpdb->get_results($prepared, ARRAY_A);

    $items = [];
    foreach ($rows as $row) {
      $item = BubbaHubListings::get((int) $row['ID']);
      if (!$item) continue;
      $item['distance_miles'] = $row['distance_miles'] !== null ? round((float) $row['distance_miles'], 1) : null;
      $items[] = $item;
    }

    return [
      'items' => $items,
      'total' => $total,
      'page' => $page,
      'per_page' => $per_page,
      'pages' => $total ? (int) ceil($total / $per_page) : 0,
      'filters' => [
        'q' => $q, 'location' => $location, 'city' => $city, 'postcode' => $postcode,
        'category' => $category, 'age' => $age, 'price' => $price, 'day' => $day,
        'featured' => $featured, 'radius' => $radius, 'lat' => $lat, 'lng' => $lng, 'sort' => $sort,
      ],
    ];
  }

  private static function add_meta_filter(&$where, &$values, $meta_table, $keys, $value) {
    if ($value === '') return;
    $placeholders = implode(',', array_fill(0, count($keys), '%s'));
    $like = '%' . $GLOBALS['wpdb']->esc_like($value) . '%';
    $where[] = "EXISTS (SELECT 1 FROM {$meta_table} mm WHERE mm.post_id=p.ID AND mm.meta_key IN ({$placeholders}) AND mm.meta_value LIKE %s)";
    foreach ($keys as $key) $values[] = $key;
    $values[] = $like;
  }

  public static function shortcode() {
    $endpoint = esc_url_raw(rest_url(self::REST_ROUTE));
    ob_start(); ?>
    <section class="bh-directory" data-endpoint="<?php echo esc_attr($endpoint); ?>" aria-label="BubbaHub directory">
      <form class="bh-directory-filters" data-bh-directory-form>
        <div class="bh-directory-search-row">
          <label class="bh-directory-field bh-directory-search"><span>Search</span><input name="q" type="search" placeholder="Groups, classes or activities"></label>
          <label class="bh-directory-field"><span>Town or city</span><input name="city" type="text" placeholder="e.g. Torquay"></label>
          <label class="bh-directory-field"><span>Postcode</span><input name="postcode" type="text" placeholder="e.g. TQ1"></label>
          <button type="submit">Search</button>
        </div>
        <details class="bh-directory-more"><summary>More filters</summary><div class="bh-directory-grid">
          <label class="bh-directory-field"><span>Category</span><input name="category" type="text" placeholder="e.g. baby groups"></label>
          <label class="bh-directory-field"><span>Age range</span><input name="age" type="text" placeholder="e.g. 0-3"></label>
          <label class="bh-directory-field"><span>Price</span><select name="price"><option value="">Any price</option><option value="free">Free</option><option value="paid">Paid</option></select></label>
          <label class="bh-directory-field"><span>Day</span><select name="day"><option value="">Any day</option><option>Monday</option><option>Tuesday</option><option>Wednesday</option><option>Thursday</option><option>Friday</option><option>Saturday</option><option>Sunday</option></select></label>
          <label class="bh-directory-field"><span>Sort</span><select name="sort"><option value="relevance">Relevance</option><option value="newest">Newest</option><option value="title">A-Z</option><option value="oldest">Oldest</option></select></label>
          <label class="bh-directory-check"><input name="featured" type="checkbox" value="1"> Featured only</label>
          <label class="bh-directory-field"><span>Location search</span><input name="location" type="text" placeholder="Street, area or county"></label>
          <label class="bh-directory-field"><span>Radius</span><select name="radius"><option value="">Any distance</option><option value="5">5 miles</option><option value="10">10 miles</option><option value="15">15 miles</option><option value="25">25 miles</option><option value="50">50 miles</option></select></label>
          <div class="bh-directory-location"><button type="button" data-bh-use-location>Use my location</button><small data-bh-location-status>Optional: allow location access for radius search.</small></div>
        </div></details>
      </form>
      <div class="bh-directory-status" data-bh-directory-status aria-live="polite"></div>
      <div class="bh-directory-results" data-bh-directory-results></div>
      <nav class="bh-directory-pagination" data-bh-directory-pagination aria-label="Directory pagination"></nav>
    </section>
    <style>
      .bh-directory{max-width:1400px;margin:0 auto;padding:24px;font-family:inherit;color:#1a2e22}.bh-directory-filters{background:#fff;border:1px solid #e4edea;border-radius:18px;padding:18px;box-shadow:0 8px 30px rgba(26,46,34,.06)}.bh-directory-search-row{display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:12px;align-items:end}.bh-directory-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;padding-top:16px}.bh-directory-field{display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:700;color:#526b61}.bh-directory-field input,.bh-directory-field select{box-sizing:border-box;width:100%;min-height:44px;border:1px solid #dce8e3;border-radius:10px;padding:9px 11px;background:#fff;color:#1a2e22}.bh-directory-search-row>button,.bh-directory-location button{min-height:44px;border:0;border-radius:10px;padding:0 18px;background:#18b97a;color:#fff;font-weight:800;cursor:pointer}.bh-directory-more{margin-top:14px}.bh-directory-more summary{cursor:pointer;font-weight:800;color:#18b97a}.bh-directory-check{display:flex;gap:8px;align-items:center;font-size:13px;font-weight:700;padding-top:27px}.bh-directory-location{display:flex;flex-direction:column;gap:6px;justify-content:flex-end}.bh-directory-location small{font-size:11px;color:#71847c}.bh-directory-status{padding:18px 2px 10px;font-size:14px;color:#61766d}.bh-directory-results{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.bh-directory-card{overflow:hidden;border:1px solid #e4edea;border-radius:16px;background:#fff;box-shadow:0 6px 22px rgba(26,46,34,.05)}.bh-directory-card-body{padding:16px}.bh-directory-card h3{margin:0 0 7px;font-size:18px}.bh-directory-card h3 a{color:inherit;text-decoration:none}.bh-directory-card-meta{margin:0;color:#647a71;font-size:13px;line-height:1.6}.bh-directory-distance{display:inline-block;margin-top:8px;font-size:12px;font-weight:800;color:#18a66f}.bh-directory-empty{grid-column:1/-1;padding:40px;text-align:center;border:1px dashed #d5e3dd;border-radius:16px;background:#fafcfb}.bh-directory-pagination{display:flex;justify-content:center;gap:7px;padding:24px 0}.bh-directory-pagination button{min-width:38px;height:38px;border:1px solid #dce8e3;border-radius:9px;background:#fff;cursor:pointer}.bh-directory-pagination button[aria-current=true]{background:#e3f5ee;border-color:#18b97a;color:#11885c;font-weight:800}@media(max-width:900px){.bh-directory-search-row,.bh-directory-grid{grid-template-columns:1fr 1fr}.bh-directory-results{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:600px){.bh-directory{padding:14px}.bh-directory-search-row,.bh-directory-grid,.bh-directory-results{grid-template-columns:1fr}.bh-directory-search-row>button{width:100%}}
    </style>
    <script>
    (function(){
      const root=document.currentScript.previousElementSibling.previousElementSibling;
      if(!root||!root.matches('.bh-directory'))return;
      const form=root.querySelector('[data-bh-directory-form]'),results=root.querySelector('[data-bh-directory-results]'),status=root.querySelector('[data-bh-directory-status]'),pager=root.querySelector('[data-bh-directory-pagination]'),endpoint=root.dataset.endpoint,locBtn=root.querySelector('[data-bh-use-location]'),locStatus=root.querySelector('[data-bh-location-status]');
      let page=1,coords=null;
      function params(){const p=new URLSearchParams(new FormData(form));p.set('page',page);p.set('per_page','12');if(coords){p.set('lat',coords.lat);p.set('lng',coords.lng);}return p;}
      function esc(s){return String(s??'').replace(/[&<>\"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','\\':'&#92;','"':'&quot;'}[c]));}
      function load(){status.textContent='Loading groups…';results.setAttribute('aria-busy','true');fetch(endpoint+'?'+params().toString(),{headers:{Accept:'application/json'}}).then(r=>{if(!r.ok)throw new Error('Search failed');return r.json();}).then(data=>{status.textContent=data.total+' group'+(data.total===1?'':'s')+' found';results.innerHTML=data.items.length?data.items.map(i=>'<article class="bh-directory-card"><div class="bh-directory-card-body"><h3><a href="'+esc(i.url)+'">'+esc(i.title)+'</a></h3><p class="bh-directory-card-meta">'+esc(i.fields.city||i.fields.region||'Local group')+(i.fields.age_range?' · '+esc(i.fields.age_range):'')+(i.fields.price?' · £'+esc(i.fields.price):'')+'</p>'+(i.distance_miles!==null?'<span class="bh-directory-distance">'+esc(i.distance_miles)+' miles away</span>':'')+'</div></article>').join(''):'<div class="bh-directory-empty"><strong>No groups found</strong><br>Try a wider area or remove one of your filters.</div>';pager.innerHTML='';for(let n=1;n<=data.pages;n++){if(n>1&&n<data.pages&&Math.abs(n-page)>2)continue;const b=document.createElement('button');b.type='button';b.textContent=n;b.setAttribute('aria-current',n===page?'true':'false');b.onclick=function(){page=n;load();window.scrollTo({top:root.offsetTop-20,behavior:'smooth'});};pager.appendChild(b);} }).catch(e=>{status.textContent='We could not load the directory. Please try again.';results.innerHTML='<div class="bh-directory-empty">Search is temporarily unavailable.</div>';}).finally(()=>results.removeAttribute('aria-busy'));}
      form.addEventListener('submit',e=>{e.preventDefault();page=1;load();});
      locBtn.addEventListener('click',()=>{if(!navigator.geolocation){locStatus.textContent='Location access is not available in this browser.';return;}locStatus.textContent='Requesting your location…';navigator.geolocation.getCurrentPosition(pos=>{coords={lat:pos.coords.latitude,lng:pos.coords.longitude};locStatus.textContent='Location set. Choose a radius and search.';},()=>{locStatus.textContent='Location permission was not granted.';},{enableHighAccuracy:false,timeout:8000,maximumAge:300000});});
      load();
    })();
    </script>
    <?php return ob_get_clean();
  }
}
add_action('wp_loaded', ['BubbaHubDirectorySearch','register'], 21);
