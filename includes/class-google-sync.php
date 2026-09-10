<?php
if (!defined('ABSPATH')) exit;

/**
 * BubbaHub Google Sheets integration + group term-status engine.
 *
 * Reads the two supplied Google Apps Script deployments server-side and maps
 * the published Google group data into the existing bh_group records.
 */
class BubbaHubGoogleSync {
  const OPTION = 'bubbahub_google_sync';
  const CRON = 'bubbahub_google_sync_cron';
  const MAIN_URL = 'https://script.google.com/macros/s/AKfycbwL70V_m3FIoxNTuc0MiI_tdAIfMlJrt0Zw62vnefNwctEmg5Pe93UhFaWzaRl3gTv1/exec';
  const TERM_URL = 'https://script.google.com/macros/s/AKfycbzxEfyvcyb9oJ25nvWgrJqRuuvoh5tWoTp5uS1j1R8SUFJgNSVauawY35cVaWo6LBSH/exec';

  public static function boot() {
    add_action('admin_menu', [__CLASS__, 'admin_menu'], 30);
    add_action('admin_post_bubbahub_google_sync', [__CLASS__, 'handle_sync']);
    add_filter('rest_post_dispatch', [__CLASS__, 'rest_status'], 20, 3);
    add_action('wp_enqueue_scripts', [__CLASS__, 'frontend_assets'], 30);
    add_action(self::CRON, [__CLASS__, 'sync'], 10);
    add_action('init', [__CLASS__, 'schedule']);
  }

  public static function schedule() {
    if (!wp_next_scheduled(self::CRON)) wp_schedule_event(time() + 300, 'twicedaily', self::CRON);
  }

  public static function settings() {
    $saved = get_option(self::OPTION, []);
    return wp_parse_args(is_array($saved) ? $saved : [], [
      'main_url' => self::MAIN_URL,
      'term_url' => self::TERM_URL,
      'auto_sync' => 1,
    ]);
  }

  public static function admin_menu() {
    add_submenu_page('bubbahub','Google Sheets Sync','Google Sheets Sync','manage_bubbahub','bubbahub-google-sync',[__CLASS__, 'admin_page']);
  }

  public static function admin_page() {
    if (!current_user_can('manage_bubbahub')) return;
    $s = self::settings();
    $notice = isset($_GET['bh_google_sync']) ? sanitize_key($_GET['bh_google_sync']) : '';
    echo '<div class="wrap"><h1>Google Sheets Sync</h1>';
    echo '<p>BubbaHub reads the published Google Apps Script feeds server-side and maps rows into the existing <code>bh_group</code> directory records.</p>';
    if ($notice === 'success') echo '<div class="notice notice-success"><p>Google sync completed.</p></div>';
    if ($notice === 'error') echo '<div class="notice notice-error"><p>Google sync failed. Check the endpoint response and WordPress debug log.</p></div>';
    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
    wp_nonce_field('bubbahub_google_sync_settings','bubbahub_google_nonce');
    echo '<input type="hidden" name="action" value="bubbahub_google_sync">';
    echo '<table class="form-table"><tr><th><label for="bh-google-main">Main Google deployment</label></th><td><input class="regular-text code" id="bh-google-main" name="main_url" value="'.esc_attr($s['main_url']).'"><p class="description">Main group/listing feed.</p></td></tr>';
    echo '<tr><th><label for="bh-google-term">Term-time deployment</label></th><td><input class="regular-text code" id="bh-google-term" name="term_url" value="'.esc_attr($s['term_url']).'"><p class="description">Second feed for term dates and term-time information.</p></td></tr>';
    echo '<tr><th>Automatic sync</th><td><label><input type="checkbox" name="auto_sync" value="1" '.checked(!empty($s['auto_sync']),true,false).'> Enable twice-daily Google sync</label></td></tr></table>';
    echo '<p><button class="button button-primary" type="submit">Save settings &amp; sync now</button></p></form>';
    echo '<hr><h2>Group status rule</h2><p><strong>Term Time must be true.</strong> If today in Europe/London is between the inclusive start and end dates, the group is <strong>Active</strong> with a green circle. Otherwise it is <strong>Inactive</strong> with an orange circle. If Term Time is false, no badge is shown.</p>';
    echo '<h2>Matched Google Sheet columns</h2><p><code>ID</code> → Google ID, <code>organizer_username</code> → organiser, <code>title</code> → listing title, <code>images</code> → images, <code>description</code> → content, <code>Tags</code> → tags, <code>category</code> → category, <code>is_featured</code> → featured, <code>address</code> → street/address, <code>city</code> → city, <code>region</code> → region, <code>zip</code> → postcode, <code>manual_lat</code> → latitude, <code>manual_lng</code> → longitude, <code>Timetable</code> → timetable, <code>openingHours</code> → business hours, <code>website</code> → website, <code>email</code> → email, <code>facebook</code> → Facebook, <code>instagram</code> → Instagram, <code>is_free</code> → free flag, <code>price</code> → price, <code>Term Time</code> → term time, <code>Age Range</code> → age range, <code>Session Length</code> → session length, <code>Day</code> → day, <code>SEN</code> → SEN.</p>';
    echo '</div>';
  }

  public static function handle_sync() {
    if (!current_user_can('manage_bubbahub') || !check_admin_referer('bubbahub_google_sync_settings','bubbahub_google_nonce')) wp_die('Permission denied.');
    $s = self::settings();
    $s['main_url'] = esc_url_raw(wp_unslash($_POST['main_url'] ?? $s['main_url']));
    $s['term_url'] = esc_url_raw(wp_unslash($_POST['term_url'] ?? $s['term_url']));
    $s['auto_sync'] = !empty($_POST['auto_sync']) ? 1 : 0;
    update_option(self::OPTION, $s);
    $ok = self::sync();
    wp_safe_redirect(add_query_arg(['page'=>'bubbahub-google-sync','bh_google_sync'=>$ok ? 'success' : 'error'], admin_url('admin.php')));
    exit;
  }

  public static function request_json($url) {
    if (!$url) return [];
    $response = wp_remote_get($url, [
      'timeout' => 20,
      'redirection' => 5,
      'headers' => ['Accept' => 'application/json'],
      'user-agent' => 'BubbaHub/'.BUBBAHUB_VERSION,
    ]);
    if (is_wp_error($response)) { error_log('BubbaHub Google Sync: '.$response->get_error_message()); return []; }
    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    if ($code < 200 || $code >= 300 || !$body) { error_log('BubbaHub Google Sync: HTTP '.$code.' from '.$url); return []; }
    $data = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE) { error_log('BubbaHub Google Sync: invalid JSON from '.$url.' - '.json_last_error_msg()); return []; }
    return self::normalise_rows($data);
  }

  private static function normalise_rows($data) {
    if (isset($data['data']) && is_array($data['data'])) $data = $data['data'];
    if (isset($data['rows']) && is_array($data['rows'])) $data = $data['rows'];
    if (isset($data['groups']) && is_array($data['groups'])) $data = $data['groups'];
    if (!is_array($data)) return [];
    $out = [];
    foreach ($data as $row) {
      if (!is_array($row)) continue;
      $clean = [];
      foreach ($row as $k => $v) $clean[self::key($k)] = is_scalar($v) ? trim((string)$v) : $v;
      if ($clean) $out[] = $clean;
    }
    return $out;
  }

  private static function key($key) { return sanitize_title_with_dashes(strtolower(trim((string)$key))); }

  private static function value($row, $aliases) {
    foreach ($aliases as $alias) {
      $k = self::key($alias);
      if (array_key_exists($k, $row) && $row[$k] !== '' && $row[$k] !== null) return $row[$k];
    }
    return '';
  }

  private static function truthy($value) {
    if (is_bool($value)) return $value;
    return in_array(strtolower(trim((string)$value)), ['1','true','yes','y','on'], true);
  }

  private static function normalise_date($value) {
    if (!$value) return '';
    $value = trim((string)$value);
    $formats = ['Y-m-d','d/m/Y','d-m-Y','d.m.Y','Y/m/d','j/n/Y','j-n-Y'];
    foreach ($formats as $format) {
      $d = DateTimeImmutable::createFromFormat('!'.$format, $value, new DateTimeZone('Europe/London'));
      if ($d && $d->format($format) === $value) return $d->format('Y-m-d');
    }
    $ts = strtotime($value);
    return $ts ? wp_date('Y-m-d', $ts, new DateTimeZone('Europe/London')) : '';
  }

  private static function merge_rows($main, $term) {
    if (!$term) return $main;
    $index = [];
    foreach ($term as $row) {
      $id = self::value($row, ['id','group_id','listing_id','external_id','group id']);
      $name = self::value($row, ['name','title','group_name','group name','listing_name']);
      if ($id !== '') $index['id:'.strtolower((string)$id)] = $row;
      if ($name !== '') $index['name:'.sanitize_title((string)$name)] = $row;
    }
    foreach ($main as &$row) {
      $id = self::value($row, ['id','group_id','listing_id','external_id','group id']);
      $name = self::value($row, ['name','title','group_name','group name','listing_name']);
      $extra = ($id !== '' && isset($index['id:'.strtolower((string)$id)])) ? $index['id:'.strtolower((string)$id)] : ($name !== '' ? ($index['name:'.sanitize_title((string)$name)] ?? []) : []);
      if ($extra) $row = array_merge($extra, $row);
    }
    return $main;
  }

  public static function sync() {
    $s = self::settings();
    if (empty($s['auto_sync']) && wp_doing_cron()) return false;
    $main = self::request_json($s['main_url']);
    if (!$main) return false;
    $term = self::request_json($s['term_url']);
    $rows = self::merge_rows($main, $term);
    $count = 0;
    foreach ($rows as $row) {
      $title = sanitize_text_field(self::value($row, ['title','name','group_name','group name','listing_name']));
      if (!$title) continue;
      $external = sanitize_text_field(self::value($row, ['id','group_id','listing_id','external_id','group id']));
      $post_id = 0;
      if ($external !== '') $post_id = absint(get_posts(['post_type'=>'bh_group','post_status'=>'any','posts_per_page'=>1,'fields'=>'ids','meta_key'=>'_bubbahub_google_id','meta_value'=>$external])[0] ?? 0);
      if (!$post_id) $post_id = absint(get_page_by_title($title, OBJECT, 'bh_group')->ID ?? 0);
      $description = self::value($row, ['description','content','post_content','about']);
      $args = ['post_title'=>$title,'post_type'=>'bh_group','post_status'=>'publish'];
      if ($description !== '') $args['post_content']=wp_kses_post($description);
      if ($post_id) {$args['ID']=$post_id; wp_update_post($args);} else {$post_id=wp_insert_post(wp_slash($args),true); if(is_wp_error($post_id)) continue;}
      if ($external !== '') update_post_meta($post_id,'_bubbahub_google_id',$external);
      $map = [
        'organizer_username'=>['organizer_username','organizer username','organiser_username','organiser username','organizer','organiser'],
        'images'=>['images','image','gallery','gallery_images'],
        'tags'=>['tags','tag'],
        'category'=>['category','categories'],
        'featured'=>['is_featured','featured','featured listing'],
        'street'=>['street','address1','address','address line 1'],
        'city'=>['city','town','postal_town'],
        'region'=>['region','county'],
        'zip'=>['zip','postcode','post_code','postal_code'],
        'latitude'=>['manual_lat','latitude','lat'],
        'longitude'=>['manual_lng','longitude','lng','lon'],
        'timetable'=>['timetable','schedule'],
        'business_hours'=>['openingHours','opening hours','business_hours','opening_hours'],
        'website'=>['website','url'],
        'email'=>['email'],
        'facebook'=>['facebook'],
        'instagram'=>['instagram'],
        'is_free'=>['is_free','free','free_session'],
        'price'=>['price','cost'],
        'term_time'=>['term_time','term time','termtime','term-time'],
        'age_range'=>['age_range','age range'],
        'session_length'=>['session_length','session length'],
        'day'=>['day','days'],
        'sen'=>['sen','special educational needs'],
        'start_date'=>['start_date','start date','term_start','term start','start'],
        'end_date'=>['end_date','end date','term_end','term end','end'],
      ];
      foreach ($map as $field=>$aliases) {
        $value = self::value($row, $aliases);
        if ($field === 'start_date' || $field === 'end_date') $value = self::normalise_date($value);
        if (in_array($field, ['term_time','featured','is_free'], true)) $value = self::truthy($value) ? '1' : '0';
        if ($value !== '') update_post_meta($post_id,'_bubbahub_'.$field, is_scalar($value) ? sanitize_text_field((string)$value) : $value);
      }
      update_post_meta($post_id,'_bubbahub_google_last_sync',current_time('mysql'));
      $count++;
    }
    update_option(self::OPTION.'_last_sync', ['time'=>current_time('mysql'),'count'=>$count]);
    return $count > 0;
  }

  public static function group_status($post_id) {
    if (get_post_type($post_id) !== 'bh_group') return null;
    $term = self::meta_value($post_id, ['term_time','term time','termtime','term-time']);
    if (!self::truthy($term)) return null;
    $start = self::meta_value($post_id, ['start_date','start date','term_start','term start','start']);
    $end = self::meta_value($post_id, ['end_date','end date','term_end','term end','end']);
    $start = self::normalise_date($start); $end = self::normalise_date($end);
    if (!$start || !$end) return ['key'=>'inactive','label'=>'Inactive','class'=>'bh-status-inactive','reason'=>'missing_dates'];
    $today = new DateTimeImmutable('today', new DateTimeZone('Europe/London'));
    $from = new DateTimeImmutable($start, new DateTimeZone('Europe/London'));
    $to = new DateTimeImmutable($end, new DateTimeZone('Europe/London'));
    $active = $today >= $from && $today <= $to;
    return $active ? ['key'=>'active','label'=>'Active','class'=>'bh-status-active'] : ['key'=>'inactive','label'=>'Inactive','class'=>'bh-status-inactive'];
  }

  private static function meta_value($post_id, $aliases) {
    foreach ($aliases as $key) {
      $value = get_post_meta($post_id, '_bubbahub_'.sanitize_key(str_replace(' ','_',$key)), true);
      if ($value !== '' && $value !== null) return $value;
    }
    $fields = class_exists('BubbaHubAdmin') ? BubbaHubAdmin::get_directory_fields('bh_group') : [];
    foreach ($fields as $field) {
      $fk = sanitize_key($field['key'] ?? '');
      foreach ($aliases as $alias) if ($fk === sanitize_key(str_replace(' ','_',$alias))) return get_post_meta($post_id,'_bubbahub_'.$fk,true);
    }
    return '';
  }

  public static function rest_status($response, $server, $request) {
    if (!($response instanceof WP_REST_Response)) return $response;
    $route = $request->get_route();
    if (strpos($route,'/bubbahub/v1/groups') !== 0 && strpos($route,'/bubbahub/v1/bootstrap') !== 0) return $response;
    $data = $response->get_data();
    if (!is_array($data)) return $response;
    $decorate = function($item) {
      if (is_array($item) && !empty($item['id'])) $item['activity_status'] = self::group_status((int)$item['id']);
      return $item;
    };
    if (isset($data['groups']) && is_array($data['groups'])) $data['groups'] = array_map($decorate,$data['groups']);
    elseif (isset($data[0]) && is_array($data)) $data = array_map($decorate,$data);
    $response->set_data($data);
    return $response;
  }

  public static function frontend_assets() {
    $post = get_post();
    $is_app = is_front_page() || (is_singular() && has_shortcode($post->post_content ?? '', 'bubba_hub'));
    if (!$is_app) return;
    wp_enqueue_style('bubbahub-group-status',BUBBAHUB_URL.'assests/css/group-status.css',[],BUBBAHUB_VERSION);
    wp_enqueue_script('bubbahub-group-status',BUBBAHUB_URL.'assests/js/group-status.js',[],BUBBAHUB_VERSION,true);
    wp_localize_script('bubbahub-group-status','BubbaHubStatusConfig',['api'=>esc_url_raw(rest_url('bubbahub/v1/groups'))]);
  }
}
BubbaHubGoogleSync::boot();
