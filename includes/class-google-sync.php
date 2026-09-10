<?php
if (!defined('ABSPATH')) exit;

/**
 * BubbaHub Google Sheets / Listings API integration.
 *
 * Google remains the source of the external listing ID. WordPress stores that
 * ID plus wp_username and can optionally write changes back to Google through
 * the authenticated Listings API.
 */
class BubbaHubGoogleSync {
  const OPTION = 'bubbahub_google_sync';
  const CRON = 'bubbahub_google_sync_cron';
  const MAIN_URL = 'https://script.google.com/macros/s/AKfycbwL70V_m3FIoxNTuc0MiI_tdAIfMlJrt0Zw62vnefNwctEmg5Pe93UhFaWzaRl3gTv1/exec';
  const TERM_URL = 'https://script.google.com/macros/s/AKfycbzxEfyvcyb9oJ25nvWgrJqRuuvoh5tWoTp5uS1j1R8SUFJgNSVauawY35cVaWo6LBSH/exec';

  private static $importing = false;

  public static function boot() {
    add_action('admin_menu', [__CLASS__, 'admin_menu'], 30);
    add_action('admin_post_bubbahub_google_sync', [__CLASS__, 'handle_sync']);
    add_filter('rest_post_dispatch', [__CLASS__, 'rest_status'], 20, 3);
    add_action('wp_enqueue_scripts', [__CLASS__, 'frontend_assets'], 30);
    add_action(self::CRON, [__CLASS__, 'sync'], 10);
    add_action('init', [__CLASS__, 'schedule']);
    add_action('save_post_bh_group', [__CLASS__, 'save_post'], 30, 3);
    add_action('before_delete_post', [__CLASS__, 'before_delete_post'], 20);
  }

  public static function schedule() {
    if (!wp_next_scheduled(self::CRON)) wp_schedule_event(time() + 300, 'twicedaily', self::CRON);
  }

  public static function settings() {
    $saved = get_option(self::OPTION, []);
    return wp_parse_args(is_array($saved) ? $saved : [], [
      'main_url' => self::MAIN_URL,
      'term_url' => self::TERM_URL,
      'shared_secret' => '',
      'secure_api' => 0,
      'writeback' => 0,
      'auto_sync' => 1,
    ]);
  }

  public static function admin_menu() {
    add_submenu_page('bubbahub','Google Sheets Sync','Google Sheets Sync','manage_bubbahub','bubbahub-google-sync',[__CLASS__, 'admin_page']);
  }

  public static function admin_page() {
    if (!current_user_can('manage_bubbahub')) return;
    $s = self::settings();
    $last = get_option(self::OPTION.'_last_sync', []);
    $notice = isset($_GET['bh_google_sync']) ? sanitize_key($_GET['bh_google_sync']) : '';
    echo '<div class="wrap"><h1>Google Sheets Sync</h1>';
    echo '<p>Google listing IDs are matched to BubbaHub groups. <code>wp_username</code> identifies the WordPress owner. Secure write-back is disabled until a shared secret is configured.</p>';
    if ($notice === 'success') echo '<div class="notice notice-success"><p>Google sync completed.</p></div>';
    if ($notice === 'error') echo '<div class="notice notice-error"><p>Google sync failed. Check the endpoint response and the configured API mode.</p></div>';
    if (!empty($last['time'])) echo '<p><strong>Last sync:</strong> '.esc_html($last['time']).' &nbsp; <strong>Listings:</strong> '.absint($last['count'] ?? 0).'</p>';
    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
    wp_nonce_field('bubbahub_google_sync_settings','bubbahub_google_nonce');
    echo '<input type="hidden" name="action" value="bubbahub_google_sync">';
    echo '<table class="form-table">';
    echo '<tr><th><label for="bh-google-main">Listings API</label></th><td><input class="regular-text code" id="bh-google-main" name="main_url" value="'.esc_attr($s['main_url']).'"><p class="description">Main Google Listings API deployment.</p></td></tr>';
    echo '<tr><th><label for="bh-google-term">Term-time API</label></th><td><input class="regular-text code" id="bh-google-term" name="term_url" value="'.esc_attr($s['term_url']).'"><p class="description">Dedicated Term Dates deployment.</p></td></tr>';
    echo '<tr><th><label for="bh-google-secret">Shared secret</label></th><td><input type="password" autocomplete="new-password" class="regular-text code" id="bh-google-secret" name="shared_secret" value="'.esc_attr($s['shared_secret']).'"><p class="description">Use the same secret as the Google Apps Script Script Property <code>BUBBAHUB_SHARED_SECRET</code>. Never put this secret in JavaScript or a public page.</p></td></tr>';
    echo '<tr><th>Secure Listings API</th><td><label><input type="checkbox" name="secure_api" value="1" '.checked(!empty($s['secure_api']),true,false).'> Use signed POST requests for the Listings API</label><p class="description">Enable this after the Google deployment has been changed to the authenticated API.</p></td></tr>';
    echo '<tr><th>Write changes back</th><td><label><input type="checkbox" name="writeback" value="1" '.checked(!empty($s['writeback']),true,false).'> Send leader listing changes to Google</label><p class="description">Only works with Secure Listings API enabled. Ownership is checked against <code>wp_username</code>.</p></td></tr>';
    echo '<tr><th>Automatic sync</th><td><label><input type="checkbox" name="auto_sync" value="1" '.checked(!empty($s['auto_sync']),true,false).'> Enable twice-daily Google sync</label></td></tr>';
    echo '</table><p><button class="button button-primary" type="submit">Save settings &amp; sync now</button></p></form>';
    echo '<hr><h2>Listing identity</h2><p><code>id</code> is the permanent Google listing ID. <code>wp_username</code> is the WordPress <code>user_login</code> of the leader. Titles are only a legacy fallback and are not used as the primary identity.</p>';
    echo '<h2>Matched columns</h2><p><code>ID</code>, <code>wp_username</code>, <code>title</code>, <code>images</code>, <code>description</code>, <code>tags</code>, <code>category</code>, <code>is_featured</code>, <code>address</code>, <code>city</code>, <code>region</code>, <code>zip</code>, <code>manual_lat</code>, <code>manual_lng</code>, <code>timetable</code>, <code>openinghours</code>, <code>website</code>, <code>email</code>, <code>facebook</code>, <code>instagram</code>, <code>is_free</code>, <code>price</code>, <code>term time</code>, <code>age range</code>, <code>session length</code>, <code>day</code>, <code>sen</code>.</p>';
    echo '</div>';
  }

  public static function handle_sync() {
    if (!current_user_can('manage_bubbahub') || !check_admin_referer('bubbahub_google_sync_settings','bubbahub_google_nonce')) wp_die('Permission denied.');
    $s = self::settings();
    $s['main_url'] = esc_url_raw(wp_unslash($_POST['main_url'] ?? $s['main_url']));
    $s['term_url'] = esc_url_raw(wp_unslash($_POST['term_url'] ?? $s['term_url']));
    $s['shared_secret'] = sanitize_text_field(wp_unslash($_POST['shared_secret'] ?? $s['shared_secret']));
    $s['secure_api'] = !empty($_POST['secure_api']) ? 1 : 0;
    $s['writeback'] = !empty($_POST['writeback']) ? 1 : 0;
    $s['auto_sync'] = !empty($_POST['auto_sync']) ? 1 : 0;
    if (!$s['secure_api']) $s['writeback'] = 0;
    update_option(self::OPTION, $s);
    $ok = self::sync();
    wp_safe_redirect(add_query_arg(['page'=>'bubbahub-google-sync','bh_google_sync'=>$ok ? 'success' : 'error'], admin_url('admin.php')));
    exit;
  }

  private static function signed_request($url, $action, $payload = []) {
    $s = self::settings();
    if (!$url || empty($s['shared_secret'])) return [];
    $timestamp = time();
    $nonce = wp_generate_uuid4();
    $body = [
      'action' => $action,
      'timestamp' => $timestamp,
      'nonce' => $nonce,
      'payload' => $payload,
    ];
    $json = wp_json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    $canonical = $action."\n".$timestamp."\n".$nonce."\n".$json;
    $signature = hash_hmac('sha256', $canonical, $s['shared_secret']);
    $body['signature'] = $signature;
    $response = wp_remote_post($url, [
      'timeout' => 30,
      'redirection' => 3,
      'headers' => ['Accept'=>'application/json','Content-Type'=>'application/json'],
      'body' => wp_json_encode($body, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
      'user-agent' => 'BubbaHub/'.BUBBAHUB_VERSION,
    ]);
    if (is_wp_error($response)) { error_log('BubbaHub Google Listings API: '.$response->get_error_message()); return []; }
    $code = wp_remote_retrieve_response_code($response);
    $raw = wp_remote_retrieve_body($response);
    $data = json_decode($raw, true);
    if ($code < 200 || $code >= 300 || !is_array($data)) {
      error_log('BubbaHub Google Listings API: HTTP '.$code.' response '.substr((string)$raw,0,500));
      return [];
    }
    if (isset($data['success']) && !$data['success']) {
      error_log('BubbaHub Google Listings API: '.sanitize_text_field((string)($data['error'] ?? 'request failed')));
      return [];
    }
    return $data;
  }

  public static function request_json($url) {
    if (!$url) return [];
    $s = self::settings();
    if (!empty($s['secure_api'])) {
      $data = self::signed_request($url, 'list', []);
      if (!$data) return [];
      return self::normalise_rows($data);
    }
    $response = wp_remote_get($url, [
      'timeout' => 30,
      'redirection' => 5,
      'headers' => ['Accept'=>'application/json'],
      'user-agent' => 'BubbaHub/'.BUBBAHUB_VERSION,
    ]);
    if (is_wp_error($response)) { error_log('BubbaHub Google Sync: '.$response->get_error_message()); return []; }
    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    if ($code < 200 || $code >= 300 || !$body) { error_log('BubbaHub Google Sync: HTTP '.$code.' from '.$url); return []; }
    $data = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE) { error_log('BubbaHub Google Sync: invalid JSON - '.json_last_error_msg()); return []; }
    return self::normalise_rows($data);
  }

  private static function normalise_rows($data) {
    foreach (['data','rows','groups','listings'] as $container) if (isset($data[$container]) && is_array($data[$container])) { $data = $data[$container]; break; }
    if (!is_array($data)) return [];
    $out = [];
    foreach ($data as $row) {
      if (!is_array($row)) continue;
      $clean = [];
      foreach ($row as $k=>$v) $clean[self::key($k)] = is_scalar($v) ? trim((string)$v) : $v;
      if ($clean) $out[] = $clean;
    }
    return $out;
  }

  private static function key($key) { return sanitize_title_with_dashes(strtolower(trim((string)$key))); }

  private static function value($row, $aliases) {
    foreach ($aliases as $alias) {
      $k = self::key($alias);
      if (array_key_exists($k,$row) && $row[$k] !== '' && $row[$k] !== null) return $row[$k];
    }
    return '';
  }

  private static function truthy($value) { return is_bool($value) ? $value : in_array(strtolower(trim((string)$value)),['1','true','yes','y','on'],true); }

  private static function normalise_date($value) {
    if (!$value) return '';
    $value = trim((string)$value);
    foreach (['Y-m-d','d/m/Y','d-m-Y','d.m.Y','Y/m/d','j/n/Y','j-n-Y'] as $format) {
      $d = DateTimeImmutable::createFromFormat('!'.$format,$value,new DateTimeZone('Europe/London'));
      if ($d && $d->format($format)===$value) return $d->format('Y-m-d');
    }
    $ts = strtotime($value);
    return $ts ? wp_date('Y-m-d',$ts,new DateTimeZone('Europe/London')) : '';
  }

  private static function merge_rows($main,$term) {
    if (!$term) return $main;
    $index=[];
    foreach($term as $row){
      $id=self::value($row,['id','group_id','listing_id','external_id','group id']);
      $name=self::value($row,['name','title','group_name','group name','listing_name']);
      if($id!=='')$index['id:'.strtolower((string)$id)]=$row;
      if($name!=='')$index['name:'.sanitize_title((string)$name)]=$row;
    }
    foreach($main as &$row){
      $id=self::value($row,['id','group_id','listing_id','external_id','group id']);
      $name=self::value($row,['name','title','group_name','group name','listing_name']);
      $extra=$id!==''?($index['id:'.strtolower((string)$id)]??[]):($name!==''?($index['name:'.sanitize_title((string)$name)]??[]):[]);
      if($extra)$row=array_merge($extra,$row);
    }
    return $main;
  }

  private static function map_fields() {
    return [
      'wp_username'=>['wp_username','organizer_username','organizer username','organiser_username','organiser username','organizer','organiser'],
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
      'business_hours'=>['openinghours','opening hours','business_hours','opening_hours'],
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
      'start_date'=>['start_date','start date','term_start','term start'],
      'end_date'=>['end_date','end date','term_end','term end'],
    ];
  }

  public static function sync() {
    $s=self::settings();
    if(empty($s['auto_sync']) && wp_doing_cron()) return false;
    $main=self::request_json($s['main_url']);
    if(!$main)return false;
    $term=self::request_json($s['term_url']);
    $rows=self::merge_rows($main,$term);
    $count=0;
    self::$importing=true;
    try {
      foreach($rows as $row){
        $title=sanitize_text_field(self::value($row,['title','name','group_name','group name','listing_name']));
        if(!$title)continue;
        $external=sanitize_text_field(self::value($row,['id','group_id','listing_id','external_id','group id']));
        $username=sanitize_user(self::value($row,['wp_username','organizer_username','organizer username','organiser_username','organiser username']));
        $post_id=0;
        if($external!=='')$post_id=absint(get_posts(['post_type'=>'bh_group','post_status'=>'any','posts_per_page'=>1,'fields'=>'ids','meta_key'=>'_bubbahub_google_id','meta_value'=>$external])[0]??0);
        if($post_id && $username){
          $stored=sanitize_user((string)get_post_meta($post_id,'_bubbahub_google_wp_username',true));
          if($stored && strcasecmp($stored,$username)!==0){ error_log('BubbaHub Google Sync: ownership mismatch for listing '.$external); continue; }
        }
        if(!$post_id && $external!==''){
          $post_id=absint(get_posts(['post_type'=>'bh_group','post_status'=>'any','posts_per_page'=>1,'fields'=>'ids','meta_key'=>'_bubbahub_google_wp_username','meta_value'=>$username])[0]??0);
        }
        if(!$post_id)$post_id=absint(get_page_by_title($title,OBJECT,'bh_group')->ID??0);
        $description=self::value($row,['description','content','post_content','about']);
        $args=['post_title'=>$title,'post_type'=>'bh_group','post_status'=>'publish'];
        if($description!=='')$args['post_content']=wp_kses_post($description);
        if($username){$u=get_user_by('login',$username);if($u)$args['post_author']=$u->ID;}
        if($post_id){$args['ID']=$post_id;wp_update_post($args);}else{$post_id=wp_insert_post(wp_slash($args),true);if(is_wp_error($post_id))continue;}
        if($external!=='')update_post_meta($post_id,'_bubbahub_google_id',$external);
        if($username){update_post_meta($post_id,'_bubbahub_google_wp_username',$username);update_post_meta($post_id,'_bubbahub_organizer_username',$username);}
        foreach(self::map_fields() as $field=>$aliases){
          $value=self::value($row,$aliases);
          if($field==='wp_username')continue;
          if($field==='start_date'||$field==='end_date')$value=self::normalise_date($value);
          if(in_array($field,['term_time','featured','is_free'],true))$value=self::truthy($value)?'1':'0';
          if($value!=='' )update_post_meta($post_id,'_bubbahub_'.$field,is_scalar($value)?sanitize_text_field((string)$value:$value):$value);
        }
        update_post_meta($post_id,'_bubbahub_google_last_sync',current_time('mysql'));
        $count++;
      }
    } finally { self::$importing=false; }
    update_option(self::OPTION.'_last_sync',['time'=>current_time('mysql'),'count'=>$count]);
    return $count>0;
  }

  /** Build the exact Google row shape used by the Listings API. */
  private static function listing_payload($post_id) {
    $post=get_post($post_id); if(!$post||$post->post_type!=='bh_group')return [];
    $username=sanitize_user((string)get_post_meta($post_id,'_bubbahub_google_wp_username',true));
    if(!$username && $post->post_author){$u=get_userdata($post->post_author);if($u)$username=$u->user_login;}
    $meta=function($key,$default=''){ $v=get_post_meta($GLOBALS['bubbahub_google_payload_post'],'_bubbahub_'.$key,true); return $v===''?$default:$v; };
    $GLOBALS['bubbahub_google_payload_post']=$post_id;
    $payload=[
      'id'=>(string)get_post_meta($post_id,'_bubbahub_google_id',true),
      'wp_username'=>$username,
      'title'=>get_the_title($post_id),
      'images'=>(string)$meta('images'),
      'description'=>$post->post_content,
      'tags'=>(string)$meta('tags'),
      'category'=>(string)$meta('category'),
      'is_featured'=>(string)$meta('featured','0'),
      'address'=>(string)$meta('street'),
      'city'=>(string)$meta('city'),
      'region'=>(string)$meta('region'),
      'zip'=>(string)$meta('zip'),
      'manual_lat'=>(string)$meta('latitude'),
      'manual_lng'=>(string)$meta('longitude'),
      'timetable'=>(string)$meta('timetable'),
      'openinghours'=>(string)$meta('business_hours'),
      'website'=>(string)$meta('website'),
      'email'=>(string)$meta('email'),
      'facebook'=>(string)$meta('facebook'),
      'instagram'=>(string)$meta('instagram'),
      'is_free'=>(string)$meta('is_free','0'),
      'price'=>(string)$meta('price'),
      'term time'=>(string)$meta('term_time'),
      'age range'=>(string)$meta('age_range'),
      'session length'=>(string)$meta('session_length'),
      'day'=>(string)$meta('day'),
      'sen'=>(string)$meta('sen'),
    ];
    unset($GLOBALS['bubbahub_google_payload_post']);
    return $payload;
  }

  private static function can_write($post_id) {
    $s=self::settings();
    if(empty($s['secure_api'])||empty($s['writeback']))return false;
    $post=get_post($post_id);if(!$post||$post->post_type!=='bh_group')return false;
    $username=sanitize_user((string)get_post_meta($post_id,'_bubbahub_google_wp_username',true));
    if(!$username&&$post->post_author){$u=get_userdata($post->post_author);if($u)$username=$u->user_login;}
    if(!$username)return false;
    $current=is_user_logged_in()?wp_get_current_user():null;
    if(!$current)return false;
    if(current_user_can('manage_bubbahub'))return true;
    return $current->user_login===$username && current_user_can('edit_bubbahub_items');
  }

  public static function push_listing($post_id) {
    $post_id=absint($post_id);
    if(!$post_id||!self::can_write($post_id))return false;
    $payload=self::listing_payload($post_id);
    if(empty($payload['id']))return false;
    $result=self::signed_request(self::settings()['main_url'],'update',$payload);
    if(!$result)return false;
    update_post_meta($post_id,'_bubbahub_google_last_push',current_time('mysql'));
    return true;
  }

  public static function create_listing($post_id) {
    $post_id=absint($post_id);
    $s=self::settings();
    if(empty($s['secure_api'])||empty($s['writeback']))return false;
    $payload=self::listing_payload($post_id); unset($payload['id']);
    if(empty($payload['wp_username']))return false;
    $result=self::signed_request($s['main_url'],'create',$payload);
    $id=self::value(is_array($result)?($result['listing']??$result):[],['id','listing_id']);
    if($id==='')$id=$result['id']??'';
    if($id==='')return false;
    update_post_meta($post_id,'_bubbahub_google_id',sanitize_text_field((string)$id));
    update_post_meta($post_id,'_bubbahub_google_last_push',current_time('mysql'));
    return true;
  }

  public static function delete_listing($post_id) {
    $post_id=absint($post_id);$s=self::settings();
    if(empty($s['secure_api'])||empty($s['writeback']))return false;
    $payload=self::listing_payload($post_id);
    if(empty($payload['id'])||empty($payload['wp_username']))return false;
    return !empty(self::signed_request($s['main_url'],'delete',['id'=>$payload['id'],'wp_username'=>$payload['wp_username']]));
  }

  public static function save_post($post_id,$post,$update) {
    if(self::$importing||wp_is_post_revision($post_id)||wp_is_post_autosave($post_id))return;
    if($post->post_type!=='bh_group'||$post->post_status==='auto-draft')return;
    $s=self::settings();if(empty($s['secure_api'])||empty($s['writeback']))return;
    $google_id=(string)get_post_meta($post_id,'_bubbahub_google_id',true);
    if($google_id){self::push_listing($post_id);return;}
    if($update)self::create_listing($post_id);
  }

  public static function before_delete_post($post_id) {
    if(self::$importing||get_post_type($post_id)!=='bh_group')return;
    $s=self::settings();if(empty($s['secure_api'])||empty($s['writeback']))return;
    self::delete_listing($post_id);
  }

  public static function group_status($post_id) {
    if(get_post_type($post_id)!=='bh_group')return null;
    $term=self::meta_value($post_id,['term_time','term time','termtime','term-time']);
    if(!self::truthy($term))return null;
    $start=self::normalise_date(self::meta_value($post_id,['start_date','start date','term_start','term start']));
    $end=self::normalise_date(self::meta_value($post_id,['end_date','end date','term_end','term end']));
    if(!$start||!$end)return ['key'=>'inactive','label'=>'Inactive','class'=>'bh-status-inactive','reason'=>'missing_dates'];
    $today=new DateTimeImmutable('today',new DateTimeZone('Europe/London'));
    $from=new DateTimeImmutable($start,new DateTimeZone('Europe/London'));$to=new DateTimeImmutable($end,new DateTimeZone('Europe/London'));
    return $today>=$from&&$today<=$to?['key'=>'active','label'=>'Active','class'=>'bh-status-active']:['key'=>'inactive','label'=>'Inactive','class'=>'bh-status-inactive'];
  }

  private static function meta_value($post_id,$aliases) {
    foreach($aliases as $key){$value=get_post_meta($post_id,'_bubbahub_'.sanitize_key(str_replace(' ','_',$key)),true);if($value!==''&&$value!==null)return $value;}
    $fields=class_exists('BubbaHubAdmin')?BubbaHubAdmin::get_directory_fields('bh_group'):[];
    foreach($fields as $field){$fk=sanitize_key($field['key']??'');foreach($aliases as $alias)if($fk===sanitize_key(str_replace(' ','_',$alias)))return get_post_meta($post_id,'_bubbahub_'.$fk,true);}
    return '';
  }

  public static function rest_status($response,$server,$request) {
    if(!($response instanceof WP_REST_Response))return $response;
    $route=$request->get_route();if(strpos($route,'/bubbahub/v1/groups')!==0&&strpos($route,'/bubbahub/v1/bootstrap')!==0)return $response;
    $data=$response->get_data();if(!is_array($data))return $response;
    $decorate=function($item){if(is_array($item)&&!empty($item['id']))$item['activity_status']=self::group_status((int)$item['id']);return $item;};
    if(isset($data['groups'])&&is_array($data['groups']))$data['groups']=array_map($decorate,$data['groups']);elseif(isset($data[0])&&is_array($data))$data=array_map($decorate,$data);
    $response->set_data($data);return $response;
  }

  public static function frontend_assets() {
    $post=get_post();$is_app=is_front_page()||(is_singular()&&has_shortcode($post->post_content??'','bubba_hub'));if(!$is_app)return;
    wp_enqueue_style('bubbahub-group-status',BUBBAHUB_URL.'assests/css/group-status.css',[],BUBBAHUB_VERSION);
    wp_enqueue_script('bubbahub-group-status',BUBBAHUB_URL.'assests/js/group-status.js',[],BUBBAHUB_VERSION,true);
    wp_localize_script('bubbahub-group-status','BubbaHubStatusConfig',['api'=>esc_url_raw(rest_url('bubbahub/v1/groups'))]);
  }
}
BubbaHubGoogleSync::boot();
