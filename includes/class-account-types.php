<?php
if (!defined('ABSPATH')) exit;
/** Parent/Leader account layer for the public registration API. */
class BubbaHubAccountTypes {
  public static function init(){add_action('init',[__CLASS__,'roles'],5);add_filter('rest_pre_dispatch',[__CLASS__,'intercept_register'],4,3);}
  public static function roles(){
    if(!get_role('bubbahub_parent')) add_role('bubbahub_parent','BubbaHub Parent',['read'=>true]);
    if(!get_role('bubbahub_leader')) add_role('bubbahub_leader','BubbaHub Leader',['read'=>true,'edit_bubbahub_items'=>true]);
  }
  public static function intercept_register($result,$server,$request){
    if($result!==null || $request->get_method()!=='POST' || $request->get_route()!=='/bubbahub/v1/auth/register') return $result;
    $p=$request->get_json_params();$type=sanitize_key($p['account_type']??$p['user_type']??'parent');if(!in_array($type,['parent','leader'],true))$type='parent';
    $email=sanitize_email($p['email']??'');$pass=(string)($p['password']??'');$name=sanitize_text_field($p['name']??'');
    if(!$email||!is_email($email)||strlen($pass)<8||!$name)return new WP_Error('invalid','Name, valid email and password of at least 8 characters are required',['status'=>400]);
    if(email_exists($email))return new WP_Error('exists','An account already exists',['status'=>409]);
    $login=sanitize_user(strtok($email,'@'));$base=$login;$i=1;while(username_exists($login))$login=$base.$i++;
    $uid=wp_create_user($login,$pass,$email);if(is_wp_error($uid))return $uid;$u=new WP_User($uid);$u->set_role($type==='leader'?'bubbahub_leader':'bubbahub_parent');
    update_user_meta($uid,'bubbahub_user_type',$type);update_user_meta($uid,'bubbahub_account_type',$type);update_user_meta($uid,'bubbahub_plan_id',$type==='leader'?'leader-basic':'parent-free');update_user_meta($uid,'bubbahub_subscription_status',$type==='leader'?'pending':'active');
    if($type==='leader')update_user_meta($uid,'bubbahub_leader_status','pending');
    wp_set_current_user($uid);wp_set_auth_cookie($uid,true);
    return rest_ensure_response(['user'=>['id'=>$uid,'name'=>$name,'email'=>$email,'roles'=>$u->roles,'account_type'=>$type,'plan_id'=>get_user_meta($uid,'bubbahub_plan_id',true)]]);
  }
}
BubbaHubAccountTypes::init();
