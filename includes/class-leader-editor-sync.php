<?php
if (!defined('ABSPATH')) exit;

class BubbaHubLeaderEditorSync {
  public static function boot() { add_filter('rest_pre_dispatch', [__CLASS__, 'intercept'], 20, 3); }
  private static function is_leader() { return is_user_logged_in() && (current_user_can('edit_bubbahub_items') || current_user_can('manage_bubbahub')); }
  private static function owns_post($post) {
    if (!$post || !in_array($post->post_type, ['bh_group','bh_event'], true)) return false;
    if (current_user_can('manage_bubbahub')) return true;
    $login = wp_get_current_user()->user_login;
    $owner = (string)get_post_meta($post->ID, '_bubbahub_google_wp_username', true);
    if (!$owner && $post->post_author) { $author=get_userdata($post->post_author); if($author)$owner=$author->user_login; }
    return $owner !== '' && strcasecmp($owner,$login)===0;
  }
  private static function clean_value($value,$field=null) {
    if(is_array($value)) return array_values(array_map(function($v){return is_scalar($v)?sanitize_text_field((string)$v):'';},$value));
    if($field&&!empty($field['type'])&&in_array($field['type'],['textarea','wysiwyg'],true)) return wp_kses_post((string)$value);
    return sanitize_text_field((string)$value);
  }
  private static function field_map($post_type) {
    $map=[];
    if(class_exists('BubbaHubAdmin')&&method_exists('BubbaHubAdmin','get_directory_fields')) foreach((array)BubbaHubAdmin::get_directory_fields($post_type) as $field) if(!empty($field['key'])) $map[sanitize_key($field['key'])]=$field;
    return $map;
  }
  private static function response_post($post) {
    $custom=[];
    foreach(self::field_map($post->post_type) as $key=>$field){$value=get_post_meta($post->ID,'_bubbahub_'.$key,true);if($value!==''&&$value!==null)$custom[$key]=['label'=>$field['label']??$key,'type'=>$field['type']??'text','value'=>$value];}
    $settings=get_option('bubbahub_google_sync',[]);
    return ['id'=>$post->ID,'title'=>get_the_title($post),'slug'=>$post->post_name,'content'=>apply_filters('the_content',$post->post_content),'raw_content'=>$post->post_content,'excerpt'=>get_the_excerpt($post),'image'=>get_the_post_thumbnail_url($post,'large'),'status'=>$post->post_status,'custom_fields'=>$custom,'google_id'=>(string)get_post_meta($post->ID,'_bubbahub_google_id',true),'google_writeback_enabled'=>!empty($settings['secure_api'])&&!empty($settings['writeback']),'link'=>get_permalink($post)];
  }
  private static function save($post,$request) {
    if(!self::owns_post($post)) return new WP_Error('forbidden','You can only edit your own listings.',['status'=>403]);
    $data=$request->get_json_params(); if(!is_array($data))$data=[];
    $meta=isset($data['meta'])&&is_array($data['meta'])?$data['meta']:[];
    $fields=self::field_map($post->post_type);
    foreach($fields as $key=>$field){if(array_key_exists($key,$data))$meta[$key]=$data[$key];if(array_key_exists('bubbahub_'.$key,$data))$meta[$key]=$data['bubbahub_'.$key];}
    foreach($meta as $key=>$value){$key=sanitize_key(str_replace('bubbahub_','',(string)$key));if(isset($fields[$key]))update_post_meta($post->ID,'_bubbahub_'.$key,self::clean_value($value,$fields[$key]));}
    $args=['ID'=>$post->ID];
    if(array_key_exists('title',$data))$args['post_title']=sanitize_text_field($data['title']);
    if(array_key_exists('content',$data))$args['post_content']=wp_kses_post($data['content']);
    if(array_key_exists('status',$data)&&in_array($data['status'],['publish','draft','pending'],true))$args['post_status']=$data['status'];
    $updated=wp_update_post($args,true); if(is_wp_error($updated))return$updated;
    $saved=get_post($post->ID); if(!$saved)return new WP_Error('save_failed','The listing could not be loaded after saving.',['status'=>500]);
    $settings=get_option('bubbahub_google_sync',[]); $enabled=!empty($settings['secure_api'])&&!empty($settings['writeback']); $google_ok=null;
    if($enabled&&class_exists('BubbaHubGoogleSync')&&method_exists('BubbaHubGoogleSync','push')&&get_post_meta($post->ID,'_bubbahub_google_id',true)) $google_ok=(bool)BubbaHubGoogleSync::push($post->ID);
    return rest_ensure_response(array_merge(self::response_post($saved),['saved'=>true,'google_writeback_enabled'=>$enabled,'google_writeback_success'=>$google_ok,'message'=>$google_ok===true?'Listing saved and Google Sheets updated.':($enabled?'Listing saved, but Google Sheets write-back failed. Check the Google Sheets Sync settings and shared secret.':'Listing saved. Enable Secure API and Write-back in BubbaHub → Google Sheets Sync to update Google Sheets.') ]));
  }
  private static function create($request,$type) {
    $data=$request->get_json_params(); if(!is_array($data))$data=[];
    $title=isset($data['title'])?sanitize_text_field($data['title']):'';
    if($title==='')return new WP_Error('missing_title','A listing name is required.',['status'=>400]);
    $status=(isset($data['status'])&&in_array($data['status'],['publish','draft','pending'],true))?$data['status']:'draft';
    $user=wp_get_current_user();
    $args=['post_type'=>$type,'post_status'=>$status,'post_title'=>$title,'post_author'=>$user->ID];
    if(array_key_exists('content',$data))$args['post_content']=wp_kses_post($data['content']);
    $id=wp_insert_post(wp_slash($args),true); if(is_wp_error($id))return$id;
    $post=get_post($id); if(!$post)return new WP_Error('create_failed','The listing could not be created.',['status'=>500]);
    update_post_meta($id,'_bubbahub_google_wp_username',sanitize_user($user->user_login));
    update_post_meta($id,'_bubbahub_organizer_username',sanitize_user($user->user_login));
    $fields=self::field_map($type);
    $meta=isset($data['meta'])&&is_array($data['meta'])?$data['meta']:[];
    foreach($fields as $key=>$field){if(array_key_exists($key,$data))$meta[$key]=$data[$key];if(array_key_exists('bubbahub_'.$key,$data))$meta[$key]=$data['bubbahub_'.$key];}
    foreach($meta as $key=>$value){$key=sanitize_key(str_replace('bubbahub_','',(string)$key));if(isset($fields[$key]))update_post_meta($id,'_bubbahub_'.$key,self::clean_value($value,$fields[$key]));}
    $settings=get_option('bubbahub_google_sync',[]); $enabled=!empty($settings['secure_api'])&&!empty($settings['writeback']); $google_ok=null;
    // The current Google Listings API creates bh_group listings. Events remain
    // WordPress-managed until an event-specific Google endpoint is introduced.
    if($type==='bh_group'&&$enabled&&class_exists('BubbaHubGoogleSync')&&method_exists('BubbaHubGoogleSync','create'))$google_ok=(bool)BubbaHubGoogleSync::create($id);
    $saved=get_post($id);
    return rest_ensure_response(array_merge(self::response_post($saved),['created'=>true,'google_writeback_enabled'=>$enabled,'google_create_success'=>$google_ok,'message'=>$google_ok===true?'Listing created and added to Google Sheets.':($type==='bh_group'&&$enabled?'Listing created in WordPress, but Google Sheets creation failed. Check the Google Sheets Sync settings and shared secret.':'Listing created in WordPress.')));
  }
  public static function intercept($result,$server,$request) {
    $route=(string)$request->get_route(); $method=strtoupper($request->get_method());
    if(!preg_match('#^/bubbahub/v1/leader/(groups|events)(?:/(\d+))?$#',$route,$m))return$result;
    if(!self::is_leader())return new WP_Error('forbidden','Leader access required.',['status'=>403]);
    $type=$m[1]==='groups'?'bh_group':'bh_event'; $id=!empty($m[2])?absint($m[2]):0;
    if($method==='POST'&&!$id)return self::create($request,$type);
    if($method==='GET'&&!$id){$query=new WP_Query(['post_type'=>$type,'post_status'=>['publish','draft','pending'],'posts_per_page'=>100,'orderby'=>'title','order'=>'ASC']);$out=[];foreach($query->posts as $post)if(self::owns_post($post))$out[]=self::response_post($post);return rest_ensure_response($out);}
    if(!$id)return$result;
    $post=get_post($id);if(!$post||$post->post_type!==$type)return new WP_Error('not_found','Listing not found.',['status'=>404]);if(!self::owns_post($post))return new WP_Error('forbidden','You can only access your own listings.',['status'=>403]);
    if($method==='DELETE'){ $deleted=wp_delete_post($id,true);if(!$deleted)return new WP_Error('delete_failed','The listing could not be deleted.',['status'=>500]);return rest_ensure_response(['deleted'=>true,'id'=>$id]); }
    if(in_array($method,['POST','PUT','PATCH'],true))return self::save($post,$request);
    if($method==='GET')return rest_ensure_response(self::response_post($post));
    return$result;
  }
}
BubbaHubLeaderEditorSync::boot();