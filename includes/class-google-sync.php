<?php
if(!defined('ABSPATH'))exit;

/**
 * BubbaHub Google Sheets bridge.
 *
 * WordPress is the source of truth for listings. Google Sheets is a
 * controlled import/export source. Nothing is pushed automatically when a
 * post is saved and there is no scheduled sync.
 *
 * Identity: Google Sheet `id` is stored in _bubbahub_google_id and is the
 * primary external key. Existing IDs are preserved; new WordPress listings
 * receive a Google ID when they are exported.
 */
class BubbaHubGoogleSync{
 const OPTION='bubbahub_google_sync';
 const MAIN_URL='https://script.google.com/macros/s/AKfycbwL70V_m3FIoxNTuc0MiI_tdAIfMlJrt0Zw62vnefNwctEmg5Pe93UhFaWzaRl3gTv1/exec';
 const TERM_URL='https://script.google.com/macros/s/AKfycbzxEfyvcyb9oJ25nvWgrJqRuuvoh5tWoTp5uS1j1R8SUFJgNSVauawY35cVaWo6LBSH/exec';
 private static $importing=false;

 public static function boot(){
  add_action('admin_menu',[__CLASS__,'menu'],30);
  add_action('admin_post_bubbahub_google_import',[__CLASS__,'import_action']);
  add_action('admin_post_bubbahub_google_export',[__CLASS__,'export_action']);
  add_action('add_meta_boxes',[__CLASS__,'meta_box']);
  add_action('save_post_bh_group',[__CLASS__,'save_meta_box'],20,3);
 }
 public static function settings(){
  return wp_parse_args(get_option(self::OPTION,[]),[
   'main_url'=>self::MAIN_URL,'term_url'=>self::TERM_URL,'shared_secret'=>'','secure_api'=>0,
   'writeback'=>0,'auto_sync'=>0
  ]);
 }
 public static function menu(){add_submenu_page('bubbahub','Google Sheets Sync','Google Sheets Sync','manage_bubbahub','bubbahub-google-sync',[__CLASS__,'page']);}
 private static function admin_url($args=[]){return add_query_arg(array_merge(['page'=>'bubbahub-google-sync'],$args),admin_url('admin.php'));}
 public static function page(){
  if(!current_user_can('manage_bubbahub'))return;
  $s=self::settings();$last=(array)get_option(self::OPTION.'_last_sync',[]);$msg=sanitize_key($_GET['bh_google_sync']??'');
  echo '<div class="wrap"><h1>Google Sheets Sync</h1>';
  if($msg==='imported')echo '<div class="notice notice-success"><p>Google Sheets import completed: '.absint($_GET['count']??0).' listing(s) processed.</p></div>';
  if($msg==='exported')echo '<div class="notice notice-success"><p>Google Sheets export completed: '.absint($_GET['count']??0).' listing(s) exported.</p></div>';
  if($msg==='error')echo '<div class="notice notice-error"><p>Google Sheets sync could not complete. Check the error log and connection settings.</p></div>';
  echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;max-width:1100px;margin-top:20px">';
  echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:14px;padding:24px"><h2 style="margin-top:0">Import from Google Sheets</h2><p>Bring new or changed Sheet rows into WordPress. Listings are matched by <strong>ID</strong>; the ID is never replaced by the post title.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'.wp_nonce_field('bubbahub_google_import','bubbahub_google_import_nonce',true,false).'<input type="hidden" name="action" value="bubbahub_google_import"><button class="button button-primary button-large">Import new / updated listings</button></form></div>';
  echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:14px;padding:24px"><h2 style="margin-top:0">Export to Google Sheets</h2><p>Send new or changed WordPress listings to the Sheet. New listings are created first; existing listings are updated using their Google ID.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'.wp_nonce_field('bubbahub_google_export','bubbahub_google_export_nonce',true,false).'<input type="hidden" name="action" value="bubbahub_google_export"><button class="button button-primary button-large">Export new / updated listings</button></form></div>';
  echo '</div>';
  echo '<h2 style="margin-top:30px">Connection settings</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'.wp_nonce_field('bubbahub_google_settings','bubbahub_google_settings_nonce',true,false).'<input type="hidden" name="action" value="bubbahub_google_settings"><table class="form-table"><tr><th>Listings API</th><td><input class="regular-text code" name="main_url" value="'.esc_attr($s['main_url']).'"></td></tr><tr><th>Term-time API</th><td><input class="regular-text code" name="term_url" value="'.esc_attr($s['term_url']).'"></td></tr><tr><th>Shared secret</th><td><input type="password" class="regular-text code" name="shared_secret" value="'.esc_attr($s['shared_secret']).'"><p class="description">Same value as Google Script Property <code>BUBBAHUB_SHARED_SECRET</code>.</p></td></tr><tr><th>Secure API</th><td><label><input type="checkbox" name="secure_api" value="1" '.checked($s['secure_api'],1,false).'> Use signed POST requests</label></td></tr></table><p><button class="button button-primary">Save connection settings</button></p></form>';
  echo '<h2>Sync status</h2><p>Automatic sync is <strong>off</strong>. Saving a WordPress listing does not contact Google Sheets. Last manual sync: '.esc_html($last['time']??'Never').'</p><p><strong>Primary key:</strong> Google <code>id</code> → WordPress meta <code>_bubbahub_google_id</code>.</p></div>';
 }
 public static function settings_action(){
  if(!current_user_can('manage_bubbahub')||!check_admin_referer('bubbahub_google_settings','bubbahub_google_settings_nonce'))wp_die('Permission denied.');
  $s=self::settings();foreach(['main_url','term_url']as$k)$s[$k]=esc_url_raw(wp_unslash($_POST[$k]??$s[$k]));$secret=trim((string)wp_unslash($_POST['shared_secret']??''));if($secret!=='')$s['shared_secret']=$secret;$s['secure_api']=!empty($_POST['secure_api'])?1:0;$s['writeback']=0;$s['auto_sync']=0;update_option(self::OPTION,$s);wp_safe_redirect(self::admin_url(['saved'=>1]));exit;
 }
 private static function key($k){return sanitize_title_with_dashes(strtolower(trim((string)$k)));}
 private static function val($r,$a){foreach($a as$x){$k=self::key($x);if(array_key_exists($k,$r)&&$r[$k]!=='')return$r[$k];}return'';}
 private static function yes($v){return is_bool($v)?$v:in_array(strtolower(trim((string)$v)),['1','true','yes','y','on'],true);}
 private static function date($v){if(!$v)return'';$v=trim((string)$v);foreach(['Y-m-d','d/m/Y','d-m-Y','d.m.Y','Y/m/d','j/n/Y','j-n-Y']as$f){$d=DateTimeImmutable::createFromFormat('!'.$f,$v,new DateTimeZone('Europe/London'));if($d&&$d->format($f)===$v)return$d->format('Y-m-d');}$t=strtotime($v);return$t?wp_date('Y-m-d',$t,new DateTimeZone('Europe/London')):'';}
 private static function rows($d){foreach(['data','rows','groups','listings']as$c)if(isset($d[$c])&&is_array($d[$c])){$d=$d[$c];break;}if(!is_array($d))return[];$o=[];foreach($d as$r){if(!is_array($r))continue;$x=[];foreach($r as$k=>$v)$x[self::key($k)]=is_scalar($v)?trim((string)$v):$v;if($x)$o[]=$x;}return$o;}
 private static function fields(){return['wp_username'=>['wp_username','organizer_username','organizer username','organiser_username','organiser username','organizer','organiser'],'images'=>['images','image','gallery','gallery_images'],'tags'=>['tags','tag'],'category'=>['category','categories'],'featured'=>['is_featured','featured','featured listing'],'street'=>['address','street','address1'],'city'=>['city','town','postal_town'],'region'=>['region','county'],'zip'=>['zip','postcode','post_code','postal_code'],'latitude'=>['manual_lat','latitude','lat'],'longitude'=>['manual_lng','longitude','lng','lon'],'timetable'=>['timetable','schedule'],'business_hours'=>['openinghours','opening hours','business_hours','opening_hours'],'website'=>['website','url'],'email'=>['email'],'facebook'=>['facebook'],'instagram'=>['instagram'],'is_free'=>['is_free','free','free_session'],'price'=>['price','cost'],'term_time'=>['term time','term_time','termtime','term-time'],'age_range'=>['age range','age_range'],'session_length'=>['session length','session_length'],'day'=>['day','days'],'sen'=>['sen','special educational needs'],'start_date'=>['start_date','start date','term_start','term start'],'end_date'=>['end_date','end date','term_end','term end']];}
 private static function signed($url,$action,$payload=[]){$s=self::settings();if(!$s['shared_secret'])return[];$t=time();$n=wp_generate_uuid4();$j=wp_json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);$body=['action'=>$action,'timestamp'=>$t,'nonce'=>$n,'data'=>$payload,'signature'=>hash_hmac('sha256',$action."\n".$t."\n".$n."\n".$j,$s['shared_secret'])];$r=wp_remote_post($url,['timeout'=>45,'redirection'=>5,'headers'=>['Accept'=>'application/json','Content-Type'=>'application/json'],'body'=>wp_json_encode($body),'user-agent'=>'BubbaHub/'.BUBBAHUB_VERSION]);if(is_wp_error($r))return[];$code=wp_remote_retrieve_response_code($r);$d=json_decode(wp_remote_retrieve_body($r),true);return$code>=200&&$code<300&&is_array($d)&&!empty($d['success'])?$d:[];}
 private static function get_json($url){$s=self::settings();if($s['secure_api']){$d=self::signed($url,'list',[]);return$d?self::rows($d):[];}$r=wp_remote_get($url,['timeout'=>45,'redirection'=>5,'headers'=>['Accept'=>'application/json'],'user-agent'=>'BubbaHub/'.BUBBAHUB_VERSION]);if(is_wp_error($r))return[];$code=wp_remote_retrieve_response_code($r);$d=json_decode(wp_remote_retrieve_body($r),true);return$code>=200&&$code<300&&is_array($d)?self::rows($d):[];}
 private static function merge($a,$b){if(!$b)return$a;$i=[];foreach($b as$r){$id=self::val($r,['id','listing_id','group_id']);if($id!=='')$i['i:'.strtolower($id)]=$r;}foreach($a as&$r){$id=self::val($r,['id','listing_id','group_id']);if($id!==''&&isset($i['i:'.strtolower($id)]))$r=array_merge($i['i:'.strtolower($id)],$r);}return$a;}
 private static function import_row($r){
  $title=sanitize_text_field(self::val($r,['title','name','group_name','listing_name']));$gid=sanitize_text_field(self::val($r,['id','listing_id','group_id']));if(!$title||$gid==='')return['ok'=>false,'changed'=>false];
  $ids=get_posts(['post_type'=>'bh_group','post_status'=>'any','posts_per_page'=>1,'fields'=>'ids','meta_key'=>'_bubbahub_google_id','meta_value'=>$gid]);$id=absint($ids[0]??0);
  if(!$id){$id=absint(get_page_by_title($title,OBJECT,'bh_group')->ID??0);if($id)update_post_meta($id,'_bubbahub_google_id',$gid);}
  $args=['post_title'=>$title,'post_type'=>'bh_group'];$d=self::val($r,['description','content','post_content','about']);if($d!=='')$args['post_content']=wp_kses_post($d);
  if($id){$args['ID']=$id;}else{$args['post_status']='publish';$id=wp_insert_post(wp_slash($args),true);if(is_wp_error($id))return['ok'=>false,'changed'=>false];}
  $hash=hash('sha256',wp_json_encode($r,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));if(get_post_meta($id,'_bubbahub_google_import_hash',true)===$hash)return['ok'=>true,'changed'=>false];
  self::$importing=true;try{wp_update_post(wp_slash($args));update_post_meta($id,'_bubbahub_google_id',$gid);$u=sanitize_user(self::val($r,['wp_username','organizer_username','organizer username','organiser_username','organiser username']));if($u){update_post_meta($id,'_bubbahub_google_wp_username',$u);update_post_meta($id,'_bubbahub_organizer_username',$u);$x=get_user_by('login',$u);if($x)wp_update_post(['ID'=>$id,'post_author'=>$x->ID]);}foreach(self::fields()as$f=>$aa){if($f==='wp_username')continue;$v=self::val($r,$aa);if(in_array($f,['start_date','end_date'],true))$v=self::date($v);if(in_array($f,['term_time','featured','is_free'],true))$v=self::yes($v)?'1':'0';if($v!=='')update_post_meta($id,'_bubbahub_'.$f,is_scalar($v)?sanitize_text_field((string)$v):$v);else delete_post_meta($id,'_bubbahub_'.$f);}update_post_meta($id,'_bubbahub_google_import_hash',$hash);update_post_meta($id,'_bubbahub_google_last_import',current_time('mysql'));}finally{self::$importing=false;}
  return['ok'=>true,'changed'=>true,'id'=>$id];
 }
 public static function import_now(){
  $a=self::get_json(self::settings()['main_url']);if(!$a)return['ok'=>false,'count'=>0];$a=self::merge($a,self::get_json(self::settings()['term_url']));$count=0;$changed=0;foreach($a as$r){$x=self::import_row($r);if(!empty($x['ok'])){$count++;if(!empty($x['changed']))$changed++;}}update_option(self::OPTION.'_last_sync',['time'=>current_time('mysql'),'direction'=>'import','count'=>$changed,'processed'=>$count]);return['ok'=>true,'count'=>$changed,'processed'=>$count];
 }
 private static function payload($id){$p=get_post($id);if(!$p||$p->post_type!=='bh_group')return[];$u=sanitize_user(get_post_meta($id,'_bubbahub_google_wp_username',true));if(!$u&&$p->post_author){$x=get_userdata($p->post_author);if($x)$u=$x->user_login;}$m=function($k,$d='')use($id){$v=get_post_meta($id,'_bubbahub_'.$k,true);return$v===''?$d:$v;};return['id'=>(string)get_post_meta($id,'_bubbahub_google_id',true),'wp_username'=>$u,'title'=>get_the_title($id),'images'=>$m('images'),'description'=>$p->post_content,'tags'=>$m('tags'),'category'=>$m('category'),'is_featured'=>$m('featured','0'),'address'=>$m('street'),'city'=>$m('city'),'region'=>$m('region'),'zip'=>$m('zip'),'manual_lat'=>$m('latitude'),'manual_lng'=>$m('longitude'),'timetable'=>$m('timetable'),'openinghours'=>$m('business_hours'),'website'=>$m('website'),'email'=>$m('email'),'facebook'=>$m('facebook'),'instagram'=>$m('instagram'),'is_free'=>$m('is_free','0'),'price'=>$m('price'),'term time'=>$m('term_time'),'age range'=>$m('age_range'),'session length'=>$m('session_length'),'day'=>$m('day'),'sen'=>$m('sen')];}
 public static function export_post($id){
  $s=self::settings();if(!$s['secure_api']||!$s['shared_secret'])return['ok'=>false,'reason'=>'secure'];$p=self::payload($id);if(!$p)return['ok'=>false,'reason'=>'post'];
  $hash=hash('sha256',wp_json_encode($p,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));$old=(string)get_post_meta($id,'_bubbahub_google_export_hash',true);if($p['id']!==''&&$hash===$old)return['ok'=>true,'changed'=>false];
  if($p['id']===''){$q=$p;unset($q['id']);$r=self::signed($s['main_url'],'create',$q);$gid=sanitize_text_field((string)($r['id']??($r['listing']['id']??'')));if($gid==='')return['ok'=>false,'reason'=>'create'];update_post_meta($id,'_bubbahub_google_id',$gid);$p['id']=$gid;}else{$r=self::signed($s['main_url'],'update',$p);if(!$r)return['ok'=>false,'reason'=>'update'];}
  update_post_meta($id,'_bubbahub_google_export_hash',hash('sha256',wp_json_encode($p,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)));update_post_meta($id,'_bubbahub_google_last_export',current_time('mysql'));return['ok'=>true,'changed'=>true,'id'=>$p['id']];
 }
 public static function export_now(){
  $posts=get_posts(['post_type'=>'bh_group','post_status'=>'any','posts_per_page'=>-1,'orderby'=>'ID','order'=>'ASC']);$count=0;$processed=0;$errors=0;foreach($posts as$p){$processed++;$x=self::export_post($p->ID);if(!empty($x['changed']))$count++;if(empty($x['ok']))$errors++;}update_option(self::OPTION.'_last_sync',['time'=>current_time('mysql'),'direction'=>'export','count'=>$count,'processed'=>$processed,'errors'=>$errors]);return['ok'=>$errors===0,'count'=>$count,'processed'=>$processed,'errors'=>$errors];
 }
 public static function import_action(){if(!current_user_can('manage_bubbahub')||!check_admin_referer('bubbahub_google_import','bubbahub_google_import_nonce'))wp_die('Permission denied.');$r=self::import_now();wp_safe_redirect(self::admin_url([$r['ok']?'bh_google_sync':'bh_google_sync'=>'error']));if($r['ok']){wp_safe_redirect(self::admin_url(['bh_google_sync'=>'imported','count'=>$r['count']]));}exit;}
 public static function export_action(){if(!current_user_can('manage_bubbahub')||!check_admin_referer('bubbahub_google_export','bubbahub_google_export_nonce'))wp_die('Permission denied.');$r=self::export_now();wp_safe_redirect(self::admin_url(['bh_google_sync'=>$r['ok']?'exported':'error','count'=>$r['count']]));exit;}
 public static function mark_editor_save($state=true){/* retained for compatibility; sync is intentionally manual */}
 public static function push($id){$r=self::export_post($id);return!empty($r['ok']);}
 public static function create($id){$r=self::export_post($id);return!empty($r['ok']);}
 public static function remove($id){return false;}
 public static function meta_box(){add_meta_box('bubbahub_google_listing_fields','BubbaHub Listing Data',[__CLASS__,'render_meta_box'],'bh_group','normal','high');}
 public static function render_meta_box($post){
  wp_nonce_field('bubbahub_google_meta','bubbahub_google_meta_nonce');$fields=self::fields();$read=['wp_username','start_date','end_date'];echo '<p><strong>WordPress is the master copy.</strong> Edit these values here, then use BubbaHub → Google Sheets Sync → Export.</p><table class="form-table">';echo '<tr><th>Google ID</th><td><input class="regular-text" readonly value="'.esc_attr(get_post_meta($post->ID,'_bubbahub_google_id',true)).'"></td></tr>';foreach($fields as$key=>$aliases){if(in_array($key,$read,true))continue;$label=ucwords(str_replace('_',' ',$key));$v=get_post_meta($post->ID,'_bubbahub_'.$key,true);if(in_array($key,['tags','category','street','city','region','zip','latitude','longitude','website','email','facebook','instagram','price','term_time','age_range','session_length','day','sen'],true))echo '<tr><th><label for="bh-meta-'.esc_attr($key).'">'.esc_html($label).'</label></th><td><input class="regular-text" id="bh-meta-'.esc_attr($key).'" name="bubbahub_meta['.esc_attr($key).']" value="'.esc_attr($v).'"></td></tr>';else echo '<tr><th><label for="bh-meta-'.esc_attr($key).'">'.esc_html($label).'</label></th><td><textarea class="large-text" rows="3" id="bh-meta-'.esc_attr($key).'" name="bubbahub_meta['.esc_attr($key).']">'.esc_textarea($v).'</textarea></td></tr>';}echo '</table>';
 }
 public static function save_meta_box($id,$post,$update){if(self::$importing||!isset($_POST['bubbahub_google_meta_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bubbahub_google_meta_nonce'])),'bubbahub_google_meta'))return;if(defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE)return;if(wp_is_post_revision($id)||!current_user_can('edit_post',$id))return;$data=is_array($_POST['bubbahub_meta']??null)?$_POST['bubbahub_meta']:[];foreach(self::fields()as$key=>$aliases){if($key==='wp_username'||$key==='start_date'||$key==='end_date')continue;if(!array_key_exists($key,$data))continue;$v=wp_unslash($data[$key]);if(in_array($key,['images','timetable','business_hours'],true))$v=wp_kses_post((string)$v);else $v=sanitize_text_field((string)$v);if(in_array($key,['featured','is_free','term_time'],true))$v=self::yes($v)?'1':'0';update_post_meta($id,'_bubbahub_'.$key,$v);}delete_post_meta($id,'_bubbahub_google_export_hash');}
}
BubbaHubGoogleSync::boot();
add_action('admin_post_bubbahub_google_settings',['BubbaHubGoogleSync','settings_action']);
