<?php
if (!defined('ABSPATH')) exit;

/**
 * BubbaHub frontend information architecture.
 * Creates only missing pages and never overwrites existing WordPress content.
 */
class BubbaHubFrontendPages {
  const OPTION = 'bubbahub_frontend_pages_v1';

  public static function definitions() {
    return [
      'home'=>['title'=>'BubbaHub','slug'=>'bubba-hub','page'=>'home','nav'=>'Home'],
      'directory'=>['title'=>'Directory','slug'=>'directory','page'=>'directory','nav'=>'Directory'],
      'events'=>['title'=>'Events','slug'=>'events','page'=>'events','nav'=>'Events'],
      'support'=>['title'=>'Support Hub','slug'=>'support','page'=>'support','nav'=>'Support'],
      'myhub'=>['title'=>'My Hub','slug'=>'my-hub','page'=>'dashboard','nav'=>'My Hub'],
      'compare'=>['title'=>'Compare Groups','slug'=>'compare-groups','page'=>'compare','nav'=>'Compare'],
      'antenatal'=>['title'=>'Antenatal Planner','slug'=>'antenatal','page'=>'antenatal','nav'=>'Antenatal'],
      'pricing'=>['title'=>'Membership Plans','slug'=>'membership','page'=>'pricing','nav'=>'Membership'],
      'signup'=>['title'=>'Join BubbaHub','slug'=>'join','page'=>'signup','nav'=>'Join'],
      'leader'=>['title'=>'Leader Portal','slug'=>'leader','page'=>'leader','nav'=>'Leader Portal'],
    ];
  }

  public static function ensure() {
    $saved=(array)get_option(self::OPTION,[]);$changed=false;
    foreach(self::definitions() as $key=>$definition){
      $page_id=!empty($saved[$key])?absint($saved[$key]):0;
      if($page_id&&get_post($page_id)&&get_post_type($page_id)==='page')continue;
      $existing=get_page_by_path($definition['slug'],OBJECT,'page');
      if($existing)$page_id=(int)$existing->ID;
      else{
        $content='[bubba_hub]';
        if($definition['page']==='pricing')$content='[bubbahub_pricing]';
        if($definition['page']==='signup')$content='[bubbahub_signup]';
        $page_id=wp_insert_post(['post_title'=>$definition['title'],'post_name'=>$definition['slug'],'post_content'=>$content,'post_status'=>'publish','post_type'=>'page','comment_status'=>'closed'],true);
        if(is_wp_error($page_id))continue;
        $changed=true;
      }
      $saved[$key]=(int)$page_id;$changed=true;
    }
    if($changed)update_option(self::OPTION,$saved,false);
  }

  public static function urls() {
    $saved=(array)get_option(self::OPTION,[]);$urls=[];
    foreach(self::definitions() as $key=>$definition){$id=absint($saved[$key]??0);$urls[$key]=$id?get_permalink($id):home_url('/'.trim($definition['slug'],'/').'/');}
    return $urls;
  }

  public static function navigation() {
    $definitions=self::definitions();$urls=self::urls();$saved=(array)get_option(self::OPTION,[]);
    $items=['home','directory','events','support','myhub','compare','antenatal','pricing','signup'];
    if(is_user_logged_in()&&class_exists('BubbaHub')&&BubbaHub::get_user_type()==='leader')$items[]='leader';
    $current=get_queried_object_id();
    ob_start();
    echo '<style>.bh-site-nav{position:relative;z-index:50;width:100%;background:rgba(255,255,255,.96);border-bottom:1px solid #e4edea;box-shadow:0 2px 14px rgba(26,46,34,.05);font-family:inherit}.bh-site-nav-inner{max-width:1400px;min-height:68px;margin:0 auto;padding:0 24px;display:flex;align-items:center;gap:20px}.bh-site-logo{display:inline-flex;align-items:center;text-decoration:none;font-size:21px;font-weight:800;letter-spacing:-.03em;color:#1a2e22;white-space:nowrap}.bh-site-logo span{color:#18b97a}.bh-site-links{display:flex;align-items:center;justify-content:center;gap:2px;flex:1}.bh-site-link{padding:10px 12px;border-radius:10px;color:#657b73;text-decoration:none;font-size:14px;font-weight:700;white-space:nowrap}.bh-site-link:hover,.bh-site-link.is-active{background:#e3f5ee;color:#18b97a}.bh-site-actions{display:flex;align-items:center;gap:10px}.bh-site-user{font-size:13px;font-weight:700;color:#657b73;max-width:150px;overflow:hidden;text-overflow:ellipsis}.bh-site-join{display:inline-flex;align-items:center;justify-content:center;padding:10px 15px;border-radius:11px;background:#18b97a;color:#fff!important;text-decoration:none;font-weight:800;font-size:13px}.bh-site-menu{display:none;border:1px solid #e4edea;background:#fff;border-radius:10px;padding:9px 12px;font-weight:800;color:#1a2e22}.bh-mobile-links{display:none}.bh-mobile-links a{display:block;padding:12px 20px;color:#1a2e22;text-decoration:none;font-weight:700;border-top:1px solid #eef3f1}.bh-mobile-links a:hover{background:#e3f5ee;color:#18b97a}@media(max-width:1050px){.bh-site-links{gap:0}.bh-site-link{padding:9px 8px;font-size:13px}.bh-site-user{display:none}}@media(max-width:820px){.bh-site-nav-inner{min-height:60px;padding:0 14px;justify-content:space-between}.bh-site-links,.bh-site-actions{display:none}.bh-site-menu{display:block}.bh-mobile-links.is-open{display:block}.bh-mobile-links[hidden]{display:none}}</style>';
    echo '<nav class="bh-site-nav" aria-label="BubbaHub primary navigation"><div class="bh-site-nav-inner">';
    echo '<a class="bh-site-logo" href="'.esc_url($urls['home']).'" aria-label="BubbaHub home"><strong>Bubba</strong><span>Hub</span></a><div class="bh-site-links">';
    foreach($items as $key){$id=absint($saved[$key]??0);$active=$id&&$id===$current?' is-active':'';echo '<a class="bh-site-link'.$active.'" href="'.esc_url($urls[$key]).'">'.esc_html($definitions[$key]['nav']).'</a>';}
    echo '</div><div class="bh-site-actions">';
    if(is_user_logged_in()){echo '<span class="bh-site-user">'.esc_html(wp_get_current_user()->display_name?:wp_get_current_user()->user_login).'</span><a class="bh-site-join" href="'.esc_url(wp_logout_url($urls['home'])).'">Sign out</a>';}else echo '<a class="bh-site-join" href="'.esc_url($urls['signup']).'">Join BubbaHub</a>';
    echo '</div><button class="bh-site-menu" type="button" aria-expanded="false" aria-controls="bh-mobile-links">Menu</button></div><div id="bh-mobile-links" class="bh-mobile-links" hidden>';
    foreach($items as $key)echo '<a href="'.esc_url($urls[$key]).'">'.esc_html($definitions[$key]['nav']).'</a>';
    echo '</div></nav><script>(function(){var b=document.querySelector(".bh-site-menu"),m=document.getElementById("bh-mobile-links");if(!b||!m)return;b.addEventListener("click",function(){var open=b.getAttribute("aria-expanded")==="true";b.setAttribute("aria-expanded",String(!open));m.hidden=open;m.classList.toggle("is-open",!open);});})();</script>';
    return ob_get_clean();
  }

  public static function shortcode(){return self::navigation();}

  public static function register(){
    add_shortcode('bubbahub_navigation',[__CLASS__,'shortcode']);
    if(!get_option(self::OPTION,false))add_action('init',[__CLASS__,'ensure'],20);
  }
}
add_action('wp_loaded',['BubbaHubFrontendPages','register'],20);
