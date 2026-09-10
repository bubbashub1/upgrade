<?php
if (!defined('ABSPATH')) exit;
class BubbaHubRest {
  public static function register(){
    register_rest_route('bubbahub/v1','/bootstrap',['methods'=>'GET','callback'=>[__CLASS__,'bootstrap'],'permission_callback'=>'__return_true']);
    foreach(['groups','events','apps'] as $type) register_rest_route('bubbahub/v1','/'.$type,['methods'=>'GET','callback'=>[__CLASS__,$type],'permission_callback'=>'__return_true']);
    register_rest_route('bubbahub/v1','/auth/register',['methods'=>'POST','callback'=>[__CLASS__,'register_user'],'permission_callback'=>'__return_true']);
    register_rest_route('bubbahub/v1','/auth/google',['methods'=>'GET','callback'=>[__CLASS__,'google_start'],'permission_callback'=>'__return_true']);
    register_rest_route('bubbahub/v1','/auth/google/callback',['methods'=>'GET','callback'=>[__CLASS__,'google_callback'],'permission_callback'=>'__return_true']);
    register_rest_route('bubbahub/v1','/auth/facebook',['methods'=>'GET','callback'=>[__CLASS__,'facebook_start'],'permission_callback'=>'__return_true']);
    register_rest_route('bubbahub/v1','/auth/facebook/callback',['methods'=>'GET','callback'=>[__CLASS__,'facebook_callback'],'permission_callback'=>'__return_true']);
    register_rest_route('bubbahub/v1','/auth/logout',['methods'=>'POST','callback'=>[__CLASS__,'logout'],'permission_callback'=>[__CLASS__,'logged']]);
    register_rest_route('bubbahub/v1','/me',['methods'=>'GET','callback'=>[__CLASS__,'me'],'permission_callback'=>[__CLASS__,'logged']]);
    register_rest_route('bubbahub/v1','/profile',['methods'=>'GET','callback'=>[__CLASS__,'profile'],'permission_callback'=>[__CLASS__,'logged']]);
    register_rest_route('bubbahub/v1','/profile',['methods'=>'POST','callback'=>[__CLASS__,'save_profile'],'permission_callback'=>[__CLASS__,'logged']]);
    register_rest_route('bubbahub/v1','/children',['methods'=>['GET','POST'],'callback'=>[__CLASS__,'children_route'],'permission_callback'=>[__CLASS__,'logged']]);
    register_rest_route('bubbahub/v1','/children/(?P<id>\d+)',['methods'=>'PUT','callback'=>[__CLASS__,'update_child'],'permission_callback'=>[__CLASS__,'logged']]);
    register_rest_route('bubbahub/v1','/saved',['methods'=>'POST','callback'=>[__CLASS__,'toggle_saved'],'permission_callback'=>[__CLASS__,'logged']]);
    register_rest_route('bubbahub/v1','/saved',['methods'=>'GET','callback'=>[__CLASS__,'saved'],'permission_callback'=>[__CLASS__,'logged']]);
    register_rest_route('bubbahub/v1','/groups/(?P<id>\d+)/compare',['methods'=>'GET','callback'=>[__CLASS__,'compare_group'],'permission_callback'=>[__CLASS__,'logged']]);
    register_rest_route('bubbahub/v1','/local',['methods'=>'GET','callback'=>[__CLASS__,'local'],'permission_callback'=>'__return_true']);
    register_rest_route('bubbahub/v1','/buddies',['methods'=>'GET','callback'=>[__CLASS__,'buddies'],'permission_callback'=>[__CLASS__,'logged']]);
    register_rest_route('bubbahub/v1','/buddy/message',['methods'=>'POST','callback'=>[__CLASS__,'send_message'],'permission_callback'=>[__CLASS__,'logged']]);
    register_rest_route('bubbahub/v1','/buddy/messages',['methods'=>'GET','callback'=>[__CLASS__,'messages'],'permission_callback'=>[__CLASS__,'logged']]);
    register_rest_route('bubbahub/v1','/notes',['methods'=>'POST','callback'=>[__CLASS__,'save_note'],'permission_callback'=>[__CLASS__,'logged']]);
    register_rest_route('bubbahub/v1','/join',['methods'=>'POST','callback'=>[__CLASS__,'join'],'permission_callback'=>[__CLASS__,'logged']]);
    register_rest_route('bubbahub/v1','/leader/dashboard',['methods'=>'GET','callback'=>[__CLASS__,'leader_dashboard'],'permission_callback'=>[__CLASS__,'leader']]);
    register_rest_route('bubbahub/v1','/leader/groups',['methods'=>'GET','callback'=>[__CLASS__,'leader_groups'],'permission_callback'=>[__CLASS__,'leader']]);
    register_rest_route('bubbahub/v1','/leader/events',['methods'=>'GET','callback'=>[__CLASS__,'leader_events'],'permission_callback'=>[__CLASS__,'leader']]);
    register_rest_route('bubbahub/v1','/leader/groups/(?P<id>\d+)',['methods'=>['POST','DELETE'],'callback'=>[__CLASS__,'leader_group_mutate'],'permission_callback'=>[__CLASS__,'leader']]);
    register_rest_route('bubbahub/v1','/leader/events/(?P<id>\d+)',['methods'=>['POST','DELETE'],'callback'=>[__CLASS__,'leader_event_mutate'],'permission_callback'=>[__CLASS__,'leader']]);
  }
  public static function logged(){return is_user_logged_in();}
  public static function leader(){return is_user_logged_in() && (current_user_can('edit_bubbahub_items') || current_user_can('manage_bubbahub'));}
  private static function posts($type,$limit=50){$q=new WP_Query(['post_type'=>$type,'post_status'=>'publish','posts_per_page'=>$limit,'orderby'=>'date','order'=>'DESC']);$out=[];foreach($q->posts as $p)$out[]=self::post($p);return $out;}
  private static function post($p){$terms=[];foreach(get_object_taxonomies($p->post_type) as $tax){$terms=array_merge($terms,wp_get_object_terms($p->ID,$tax,['fields'=>'names']));}$custom=[];foreach(BubbaHubAdmin::get_directory_fields($p->post_type) as $f){$v=get_post_meta($p->ID,'_bubbahub_'.$f['key'],true);if($v!==''&&$v!==null)$custom[$f['key']]=['label'=>$f['label'],'type'=>$f['type'],'value'=>$v];}return ['id'=>$p->ID,'title'=>get_the_title($p),'slug'=>$p->post_name,'content'=>apply_filters('the_content',$p->post_content),'excerpt'=>get_the_excerpt($p),'image'=>get_the_post_thumbnail_url($p,'large'),'terms'=>array_values(array_unique($terms)),'meta'=>get_post_meta($p->ID),'custom_fields'=>$custom,'link'=>get_permalink($p)];}
  public static function bootstrap(){return rest_ensure_response(['site'=>['name'=>get_bloginfo('name'),'url'=>home_url()],'groups'=>self::posts('bh_group',12),'events'=>self::posts('bh_event',12),'apps'=>self::posts('bh_app',12),'specialists'=>self::posts('bh_specialist',12),'articles'=>self::posts('bh_article',12)]);}
  public static function groups(){return rest_ensure_response(self::posts('bh_group',100));} public static function events(){return rest_ensure_response(self::posts('bh_event',100));} public static function apps(){return rest_ensure_response(self::posts('bh_app',100));}
  public static function register_user($r){$p=$r->get_json_params();$email=sanitize_email($p['email']??'');$pass=(string)($p['password']??'');$name=sanitize_text_field($p['name']??'');if(!$email||!is_email($email)||strlen($pass)<8||!$name)return new WP_Error('invalid','Name, valid email and password of at least 8 characters are required',['status'=>400]);if(email_exists($email))return new WP_Error('exists','An account already exists',['status'=>409]);$login=sanitize_user(strtok($email,'@'));$base=$login;$i=1;while(username_exists($login))$login=$base.$i++;$uid=wp_create_user($login,$pass,$email);if(is_wp_error($uid))return $uid;wp_update_user(['ID'=>$uid,'display_name'=>$name,'role'=>'subscriber']);self::login_uid($uid);return rest_ensure_response(['user'=>self::user($uid)]);}
  private static function login_uid($uid){wp_set_current_user($uid);wp_set_auth_cookie($uid,true);}
  private static function user($uid){$u=get_userdata($uid);return ['id'=>$uid,'name'=>$u->display_name,'email'=>$u->user_email,'roles'=>$u->roles];}
  public static function logout(){wp_logout();return rest_ensure_response(['ok'=>true]);}
  public static function me(){return rest_ensure_response(['user'=>self::user(get_current_user_id())]);}

  public static function profile(){
    $uid=get_current_user_id();
    return rest_ensure_response(['location'=>get_user_meta($uid,'bubbahub_location',true),'lat'=>get_user_meta($uid,'bubbahub_lat',true),'lng'=>get_user_meta($uid,'bubbahub_lng',true),'interests'=>get_user_meta($uid,'bubbahub_interests',true),'due_date'=>get_user_meta($uid,'bubbahub_due_date',true)]);
  }
  public static function save_profile($r){
    $p=$r->get_json_params();$uid=get_current_user_id();
    foreach(['location','lat','lng','due_date'] as $k) if(array_key_exists($k,$p)) update_user_meta($uid,'bubbahub_'.$k, sanitize_text_field($p[$k]));
    if(isset($p['lat'],$p['lng']) && (float)$p['lat'] && (float)$p['lng']){
      $settings=get_option('bubbahub_settings',[]); $gkey=sanitize_text_field($settings['google_places_key']??'');
      if($gkey){$geo=wp_remote_get(add_query_arg(['latlng'=>$p['lat'].','.$p['lng'],'key'=>$gkey],'https://maps.googleapis.com/maps/api/geocode/json'),['timeout'=>10]);$gd=!is_wp_error($geo)?json_decode(wp_remote_retrieve_body($geo),true):null;$area='';
        foreach(($gd['results'][0]['address_components']??[]) as $component) if(in_array('administrative_area_level_2',$component['types']??[],true)){$area=$component['long_name'];break;}
        if(!$area) foreach(($gd['results'][0]['address_components']??[]) as $component) if(in_array('postal_town',$component['types']??[],true)){$area=$component['long_name'];break;}
        if($area) update_user_meta($uid,'bubbahub_location',sanitize_text_field($area));
      }
    }
    if(array_key_exists('interests',$p)){ $ints=is_array($p['interests'])?$p['interests']:preg_split('/\s*,\s*/',(string)$p['interests']); $ints=array_values(array_filter(array_map('sanitize_text_field',$ints))); update_user_meta($uid,'bubbahub_interests',$ints); }
    return self::profile();
  }

  public static function children_route($r){return $r->get_method()==='GET'?self::children():self::add_child($r);}
  public static function children(){global $wpdb;$rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}bubbahub_children WHERE user_id=%d ORDER BY id DESC",get_current_user_id()),ARRAY_A);foreach($rows as &$c){$c['school_tracker']=self::school_tracker($c['birth_date']);}return rest_ensure_response($rows);}
  public static function add_child($r){global $wpdb;$p=$r->get_json_params();$name=sanitize_text_field($p['name']??'');if(!$name)return new WP_Error('invalid','Child name is required',['status'=>400]);$dob=sanitize_text_field($p['birth_date']??'');if($dob&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$dob))return new WP_Error('invalid','Birth date must be YYYY-MM-DD',['status'=>400]);$wpdb->insert($wpdb->prefix.'bubbahub_children',['user_id'=>get_current_user_id(),'name'=>$name,'birth_date'=>$dob?:null,'stage'=>sanitize_text_field($p['stage']??''),'notes'=>sanitize_textarea_field($p['notes']??'')]);return rest_ensure_response(['id'=>$wpdb->insert_id,'school_tracker'=>self::school_tracker($dob)]);}
  public static function update_child($r){global $wpdb;$id=absint($r['id']);$uid=get_current_user_id();$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}bubbahub_children WHERE id=%d AND user_id=%d",$id,$uid),ARRAY_A);if(!$row)return new WP_Error('not_found','Child profile not found',['status'=>404]);$p=$r->get_json_params();$name=sanitize_text_field($p['name']??$row['name']);$dob=sanitize_text_field($p['birth_date']??$row['birth_date']);if(!$name)return new WP_Error('invalid','Child name is required',['status'=>400]);if(!$dob||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$dob))return new WP_Error('invalid','Please provide a valid date of birth.',['status'=>400]);$wpdb->update($wpdb->prefix.'bubbahub_children',['name'=>$name,'birth_date'=>$dob,'stage'=>sanitize_text_field($p['stage']??$row['stage']),'notes'=>sanitize_textarea_field($p['notes']??$row['notes'])],['id'=>$id,'user_id'=>$uid]);return rest_ensure_response(['id'=>$id,'name'=>$name,'birth_date'=>$dob,'school_tracker'=>self::school_tracker($dob)]);}
  private static function school_tracker($dob){
    if(!$dob)return null;$d=new DateTime($dob,new DateTimeZone('Europe/London'));$year=(int)$d->format('Y');$aug31=new DateTime(($year+4).'-08-31',new DateTimeZone('Europe/London'));
    $primary_start_year=(int)$d->format('Y')+4; if($d->format('m-d')>'08-31') $primary_start_year++; $secondary_start_year=(int)$d->format('Y')+11; if($d->format('m-d')>'08-31') $secondary_start_year++;
    return ['primary'=>['school_year'=>'September '.$primary_start_year,'apply_from'=>'September '.($primary_start_year-1),'typical_deadline'=>'15 January '.($primary_start_year),'note'=>'England-wide typical timetable; your local authority can set different dates.'],'secondary'=>['school_year'=>'September '.$secondary_start_year,'apply_from'=>'September '.($secondary_start_year-1),'typical_deadline'=>'31 October '.($secondary_start_year-1),'note'=>'England-wide typical timetable; your local authority can set different dates.']];
  }

  public static function toggle_saved($r){global $wpdb;$p=$r->get_json_params();$uid=get_current_user_id();$type=sanitize_key($p['object_type']??'group_favorite');$id=absint($p['object_id']??0);if(!$id)return new WP_Error('invalid','Invalid item',['status'=>400]);$allowed=['group_favorite','group_visited','group_compare','bh_group','group'];if(!in_array($type,$allowed,true))return new WP_Error('invalid','Invalid saved type',['status'=>400]);$table=$wpdb->prefix.'bubbahub_saved';$exists=$wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE user_id=%d AND object_type=%s AND object_id=%d",$uid,$type,$id));if($exists){$wpdb->delete($table,['id'=>$exists]);return ['active'=>false,'saved'=>false];}$wpdb->insert($table,['user_id'=>$uid,'object_type'=>$type,'object_id'=>$id]);return ['active'=>true,'saved'=>true];}
  public static function saved(){global $wpdb;$uid=get_current_user_id();$rows=$wpdb->get_results($wpdb->prepare("SELECT object_type,object_id FROM {$wpdb->prefix}bubbahub_saved WHERE user_id=%d",$uid),ARRAY_A);$out=['group_favorite'=>[],'group_visited'=>[],'group_compare'=>[]];foreach($rows as $r)if(isset($out[$r['object_type']]))$out[$r['object_type']][]=(int)$r['object_id'];return $out;}
  public static function compare_group($r){$id=absint($r['id']);$p=get_post($id);if(!$p||$p->post_type!=='bh_group')return new WP_Error('not_found','Group not found',['status'=>404]);return self::post($p);}

  public static function local($r){
    $p=$r->get_params();$lat=isset($p['lat'])?(float)$p['lat']:0;$lng=isset($p['lng'])?(float)$p['lng']:0;$q=sanitize_text_field($p['q']??'antenatal classes');$settings=get_option('bubbahub_settings',[]);$key=sanitize_text_field($settings['google_places_key']??'');
    if(!$key)return rest_ensure_response(['configured'=>false,'items'=>[],'message'=>'Add a Google Places API key in BubbaHub → Settings to enable local recommendations.']);
    if(!$lat||!$lng)return rest_ensure_response(['configured'=>true,'items'=>[],'message'=>'Location is required.']);
    $url=add_query_arg(['location'=>$lat.','.$lng,'radius'=>15000,'keyword'=>$q,'key'=>$key],'https://maps.googleapis.com/maps/api/place/nearbysearch/json');
    $res=wp_remote_get($url,['timeout'=>12]);if(is_wp_error($res))return new WP_Error('places_error',$res->get_error_message(),['status'=>502]);$data=json_decode(wp_remote_retrieve_body($res),true);$items=[];foreach(($data['results']??[]) as $x)$items[]=['name'=>$x['name']??'','address'=>$x['vicinity']??'','rating'=>$x['rating']??null,'url'=>'https://www.google.com/maps/search/?api=1&query='.rawurlencode(($x['name']??'').' '.($x['vicinity']??''))];return rest_ensure_response(['configured'=>true,'items'=>array_slice($items,0,10)]);
  }

  private static function buddy_score($uid,$other){
    $score=0;$a=(array)get_user_meta($uid,'bubbahub_interests',true);$b=(array)get_user_meta($other,'bubbahub_interests',true);$score+=count(array_intersect($a,$b))*5;
    $la=get_user_meta($uid,'bubbahub_location',true);$lb=get_user_meta($other,'bubbahub_location',true);if($la&&$lb&&strtolower($la)===strtolower($lb))$score+=8;
    global $wpdb;$t=$wpdb->prefix.'bubbahub_saved';$common=$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t a INNER JOIN $t b ON a.object_type=b.object_type AND a.object_id=b.object_id WHERE a.user_id=%d AND b.user_id=%d AND a.object_type IN ('group_favorite','group_visited')",$uid,$other));$score+=(int)$common*3;return $score;
  }
  public static function buddies(){
    $uid=get_current_user_id();$users=get_users(['exclude'=>[$uid],'number'=>100,'fields'=>['ID','display_name']]);$out=[];foreach($users as $u){$score=self::buddy_score($uid,$u->ID);if($score<1)continue;$loc=get_user_meta($u->ID,'bubbahub_location',true);$out[]=['id'=>$u->ID,'name'=>$u->display_name,'area'=>$loc?:'Nearby','score'=>$score,'interests'=>(array)get_user_meta($u->ID,'bubbahub_interests',true)];}usort($out,function($a,$b){return $b['score']<=>$a['score'];});return rest_ensure_response(array_slice($out,0,12));
  }
  public static function send_message($r){global $wpdb;$p=$r->get_json_params();$to=absint($p['recipient_id']??0);$msg=sanitize_textarea_field($p['message']??'');if(!$to||!get_userdata($to)||!$msg)return new WP_Error('invalid','Recipient and message are required',['status'=>400]);$wpdb->insert($wpdb->prefix.'bubbahub_buddy_messages',['sender_id'=>get_current_user_id(),'recipient_id'=>$to,'message'=>$msg]);return ['id'=>$wpdb->insert_id];}
  public static function messages(){global $wpdb;$uid=get_current_user_id();$rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}bubbahub_buddy_messages WHERE sender_id=%d OR recipient_id=%d ORDER BY created_at DESC LIMIT 100",$uid,$uid),ARRAY_A);foreach($rows as &$r){$r['sender_name']=get_the_author_meta('display_name',$r['sender_id']);$r['recipient_name']=get_the_author_meta('display_name',$r['recipient_id']);}return rest_ensure_response($rows);}

  public static function save_note($r){global $wpdb;$p=$r->get_json_params();$gid=absint($p['group_id']??0);$note=sanitize_textarea_field($p['note']??'');if(!$gid)return new WP_Error('invalid','Group required',['status'=>400]);$wpdb->insert($wpdb->prefix.'bubbahub_notes',['user_id'=>get_current_user_id(),'group_id'=>$gid,'note'=>$note]);return ['id'=>$wpdb->insert_id];}
  public static function join($r){global $wpdb;$gid=absint($r->get_json_params()['group_id']??0);if(!$gid)return new WP_Error('invalid','Group required',['status'=>400]);$wpdb->replace($wpdb->prefix.'bubbahub_members',['user_id'=>get_current_user_id(),'group_id'=>$gid,'status'=>'active']);return ['joined'=>true];}

  private static function oauth_user($provider,$data){
    $email=sanitize_email($data['email']??'');$name=sanitize_text_field($data['name']??'');$provider_id=sanitize_text_field($data['id']??'');if(!$email||!$provider_id)return new WP_Error('oauth_invalid','The social provider did not return a usable email address.',['status'=>400]);
    $uid=email_exists($email);if(!$uid){$login=sanitize_user(strtok($email,'@'));$base=$login;$i=1;while(username_exists($login))$login=$base.$i++;$uid=wp_create_user($login,wp_generate_password(32,true),$email);if(is_wp_error($uid))return $uid;wp_update_user(['ID'=>$uid,'display_name'=>$name?:$login,'role'=>'subscriber']);}
    update_user_meta($uid,'bubbahub_'.$provider.'_id',$provider_id);self::login_uid($uid);return rest_ensure_response(['user'=>self::user($uid)]);
  }
  private static function oauth_redirect($provider){$settings=get_option('bubbahub_settings',[]);$id=sanitize_text_field($settings[$provider.'_client_id']??'');if(!$id)return new WP_Error('oauth_not_configured','Social login is not configured in BubbaHub settings.',['status'=>503]);$state=wp_generate_password(24,false);set_transient('bubbahub_oauth_'.$state,['provider'=>$provider],10*MINUTE_IN_SECONDS);$callback=rest_url('bubbahub/v1/auth/'.$provider.'/callback');if($provider==='google')$url=add_query_arg(['client_id'=>$id,'redirect_uri'=>$callback,'response_type'=>'code','scope'=>'openid email profile','state'=>$state],'https://accounts.google.com/o/oauth2/v2/auth');else $url=add_query_arg(['client_id'=>$id,'redirect_uri'=>$callback,'response_type'=>'code','scope'=>'email,public_profile','state'=>$state],'https://www.facebook.com/v20.0/dialog/oauth');wp_safe_redirect($url);exit;}
  public static function google_start(){return self::oauth_redirect('google');}
  public static function facebook_start(){return self::oauth_redirect('facebook');}
  private static function oauth_callback($provider,$r){
    $state=sanitize_text_field($r->get_param('state'));$saved=get_transient('bubbahub_oauth_'.$state);if(!$saved||$saved['provider']!==$provider)return new WP_Error('oauth_state','Invalid or expired login state.',['status'=>400]);delete_transient('bubbahub_oauth_'.$state);
    $code=sanitize_text_field($r->get_param('code'));$settings=get_option('bubbahub_settings',[]);$id=sanitize_text_field($settings[$provider.'_client_id']??'');$secret=sanitize_text_field($settings[$provider.'_client_secret']??'');$callback=rest_url('bubbahub/v1/auth/'.$provider.'/callback');
    if(!$code||!$id||!$secret)return new WP_Error('oauth_config','OAuth configuration is incomplete.',['status'=>503]);
    if($provider==='google'){$tok=wp_remote_post('https://oauth2.googleapis.com/token',['body'=>['code'=>$code,'client_id'=>$id,'client_secret'=>$secret,'redirect_uri'=>$callback,'grant_type'=>'authorization_code'],'timeout'=>15]);if(is_wp_error($tok))return $tok;$td=json_decode(wp_remote_retrieve_body($tok),true);$access=$td['access_token']??'';$u=wp_remote_get('https://www.googleapis.com/oauth2/v3/userinfo',['headers'=>['Authorization'=>'Bearer '.$access],'timeout'=>15]);$ud=json_decode(wp_remote_retrieve_body($u),true);$data=['id'=>$ud['sub']??'','email'=>$ud['email']??'','name'=>$ud['name']??''];}
    else {$tok=wp_remote_get(add_query_arg(['client_id'=>$id,'client_secret'=>$secret,'redirect_uri'=>$callback,'code'=>$code],'https://graph.facebook.com/v20.0/oauth/access_token'),['timeout'=>15]);$td=json_decode(wp_remote_retrieve_body($tok),true);$access=$td['access_token']??'';$u=wp_remote_get(add_query_arg(['fields'=>'id,name,email','access_token'=>$access],'https://graph.facebook.com/me'),['timeout'=>15]);$ud=json_decode(wp_remote_retrieve_body($u),true);$data=['id'=>$ud['id']??'','email'=>$ud['email']??'','name'=>$ud['name']??''];}
    $result=self::oauth_user($provider,$data);if(is_wp_error($result))return $result;wp_safe_redirect(home_url('/'));exit;
  }
  public static function google_callback($r){return self::oauth_callback('google',$r);}
  public static function facebook_callback($r){return self::oauth_callback('facebook',$r);}

  public static function leader_dashboard(){global $wpdb;$groups=self::leader_groups_data();$events=self::leader_events_data();$members=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}bubbahub_members WHERE status='active'");return rest_ensure_response(['user'=>self::user(get_current_user_id()),'stats'=>['groups'=>count($groups),'events'=>count($events),'members'=>$members],'groups'=>$groups,'events'=>$events]);}
  private static function leader_groups_data(){ $q=new WP_Query(['post_type'=>'bh_group','post_status'=>['publish','draft'],'posts_per_page'=>100,'orderby'=>'title','order'=>'ASC']);return array_map([__CLASS__,'post'],$q->posts); }
  private static function leader_events_data(){ $q=new WP_Query(['post_type'=>'bh_event','post_status'=>['publish','draft'],'posts_per_page'=>100,'orderby'=>'meta_value','meta_key'=>'event_datetime','order'=>'ASC']);return array_map([__CLASS__,'post'],$q->posts); }
  public static function leader_groups(){return rest_ensure_response(self::leader_groups_data());} public static function leader_events(){return rest_ensure_response(self::leader_events_data());}
  public static function leader_group_mutate($r){$id=absint($r['id']);if(!get_post($id)||get_post_type($id)!=='bh_group')return new WP_Error('not_found','Group not found',['status'=>404]);if($r->get_method()==='DELETE'){wp_delete_post($id,true);return ['deleted'=>true];}$p=$r->get_json_params();$args=['ID'=>$id];if(isset($p['title']))$args['post_title']=sanitize_text_field($p['title']);if(isset($p['content']))$args['post_content']=wp_kses_post($p['content']);if(isset($p['status']))$args['post_status']=in_array($p['status'],['publish','draft'],true)?$p['status']:'draft';$updated=wp_update_post($args,true);if(is_wp_error($updated))return $updated;return rest_ensure_response(self::post(get_post($id)));}
  public static function leader_event_mutate($r){$id=absint($r['id']);if(!get_post($id)||get_post_type($id)!=='bh_event')return new WP_Error('not_found','Event not found',['status'=>404]);if($r->get_method()==='DELETE'){wp_delete_post($id,true);return ['deleted'=>true];}$p=$r->get_json_params();$args=['ID'=>$id];if(isset($p['title']))$args['post_title']=sanitize_text_field($p['title']);if(isset($p['content']))$args['post_content']=wp_kses_post($p['content']);if(isset($p['status']))$args['post_status']=in_array($p['status'],['publish','draft'],true)?$p['status']:'draft';$updated=wp_update_post($args,true);if(is_wp_error($updated))return $updated;if(isset($p['event_datetime']))update_post_meta($id,'event_datetime',sanitize_text_field($p['event_datetime']));return rest_ensure_response(self::post(get_post($id)));}
}
