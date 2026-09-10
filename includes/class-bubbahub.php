<?php
if (!defined('ABSPATH')) exit;
class BubbaHub {
  public static function subscription_features(){
    return [
      'parent'=>[
        'directory'=>'Directory search & listings','saved_groups'=>'Save favourite groups','compare'=>'Compare groups','my_hub'=>'My Hub','school_tracker'=>'School tracker','antenatal'=>'Antenatal planner','buddy'=>'Bubba Buddy community','priority_support'=>'Priority family support'
      ],
      'leader'=>[
        'listing'=>'Create/manage listings','builder'=>'Listing Builder','venues'=>'Venue Manager','schedule'=>'Schedule repeater','bookings'=>'Bookings','tickets'=>'Ticket sales','calendar'=>'Leader calendar','priority'=>'Priority visibility','featured'=>'Featured promotion','unlimited'=>'Unlimited listings','enhanced_profile'=>'Enhanced leader profile','priority_support'=>'Priority support'
      ]
    ];
  }
  public static function subscription_plans(){
    $defaults=[
      'parent-free'=>['user_type'=>'parent','name'=>'Parent Free','description'=>'Free access to the core BubbaHub family directory.','price'=>0,'currency'=>'GBP','billing'=>'yearly','cta'=>'Join free','features'=>['directory'=>1,'saved_groups'=>1,'my_hub'=>1,'school_tracker'=>1]],
      'parent-pro'=>['user_type'=>'parent','name'=>'Parent Pro','description'=>'More planning and community tools for families.','price'=>25,'currency'=>'GBP','billing'=>'yearly','cta'=>'Choose Parent Pro','features'=>['directory'=>1,'saved_groups'=>1,'compare'=>1,'my_hub'=>1,'school_tracker'=>1,'antenatal'=>1,'buddy'=>1,'priority_support'=>1]],
      'leader-basic'=>['user_type'=>'leader','name'=>'Leader Basic','description'=>'Core tools for local group and class leaders.','price'=>10,'currency'=>'GBP','billing'=>'yearly','cta'=>'Choose Basic','features'=>['listing'=>1,'builder'=>1,'venues'=>1,'schedule'=>1]],
      'leader-premium'=>['user_type'=>'leader','name'=>'Leader Premium','description'=>'Bookings, tickets and priority visibility for growing leaders.','price'=>25,'currency'=>'GBP','billing'=>'yearly','cta'=>'Choose Premium','features'=>['listing'=>1,'builder'=>1,'venues'=>1,'schedule'=>1,'bookings'=>1,'tickets'=>1,'calendar'=>1,'priority'=>1,'priority_support'=>1]],
      'leader-ultimate'=>['user_type'=>'leader','name'=>'Leader Ultimate','description'=>'The complete BubbaHub toolkit for established providers.','price'=>50,'currency'=>'GBP','billing'=>'yearly','cta'=>'Choose Ultimate','features'=>['listing'=>1,'builder'=>1,'venues'=>1,'schedule'=>1,'bookings'=>1,'tickets'=>1,'calendar'=>1,'priority'=>1,'featured'=>1,'unlimited'=>1,'enhanced_profile'=>1,'priority_support'=>1]],
    ];
    $saved=get_option('bubbahub_settings',[]);$configured=(array)($saved['plans']??[]);
    foreach($defaults as $id=>$d){if(isset($configured[$id])&&is_array($configured[$id]))$defaults[$id]=array_merge($d,$configured[$id]);$defaults[$id]['features']=array_merge($d['features'],(array)($configured[$id]['features']??[]));}
    return $defaults;
  }
  public static function get_user_type($user_id=0){
    $user_id=$user_id?:get_current_user_id();if(!$user_id)return 'parent';$type=get_user_meta($user_id,'bubbahub_user_type',true);if(in_array($type,['parent','leader'],true))return $type;$u=get_userdata($user_id);$roles=(array)($u?$u->roles:[]);if(array_intersect($roles,['bubbahub_leader','leader','leaderpro']))return 'leader';return 'parent';
  }
  public static function get_user_plan($user_id=0){
    $user_id=$user_id?:get_current_user_id();$type=self::get_user_type($user_id);$plans=self::subscription_plans();$saved=sanitize_key((string)get_user_meta($user_id,'bubbahub_plan_id',true));if($saved&&isset($plans[$saved])&&($plans[$saved]['user_type']??'')===$type)return $saved;$signup=(array)(get_option('bubbahub_settings',[])['signup']??[]);$default=$signup[$type.'_default_plan']??($type==='leader'?'leader-basic':'parent-free');return isset($plans[$default])?$default:($type==='leader'?'leader-basic':'parent-free');
  }
  public static function user_has_feature($feature,$user_id=0){$plan=self::get_user_plan($user_id);$plans=self::subscription_plans();return !empty($plans[$plan]['enabled']) && !empty($plans[$plan]['features'][$feature]);}
  public static function subscription_label($plan_id){$plans=self::subscription_plans();return $plans[$plan_id]['name']??ucwords(str_replace('-',' ',$plan_id));}
  public static function pricing_shortcode($atts=[]){
    $atts=shortcode_atts(['type'=>'all'],(array)$atts,'bubbahub_pricing');$plans=self::subscription_plans();$types=$atts['type']==='parent'?['parent']:($atts['type']==='leader'?['leader']:['parent','leader']);$settings=get_option('bubbahub_settings',[]);$methods=(array)($settings['payment_methods']??[]);$page_map=self::page_map();$join_base=$page_map['join']['url']??home_url('/join/');
    ob_start(); echo '<div class="bh-public-pricing"><div class="bh-pricing-head"><span class="bh-builder-eyebrow">BubbaHub memberships</span><h2>Choose the plan that fits you</h2><p>Parents and leaders have separate plan families, so you only see features relevant to your account.</p></div>';
    foreach($types as $type){echo '<section class="bh-pricing-family"><div class="bh-pricing-family-head"><h3>'.($type==='parent'?'For Parents':'For Leaders').'</h3><span>'.($type==='parent'?'Family tools & community':'Directory, bookings & business tools').'</span></div><div class="bh-pricing-grid">';foreach($plans as $id=>$plan){if(($plan['user_type']??'')!==$type||empty($plan['enabled']))continue;$price=(float)($plan['price']??0);$currency=$plan['currency']??'GBP';$symbol=$currency==='GBP'?'£':$currency.' ';$billing=$plan['billing']??'yearly';$billing_label=['one_time'=>'one-off','weekly'=>'per week','monthly'=>'per month','quarterly'=>'every 3 months','yearly'=>'per year'][$billing]??'per year';$join=add_query_arg(['bh_signup'=>'1','bh_type'=>$type,'bh_plan'=>$id],$join_base);echo '<article class="bh-public-plan '.($id===self::get_user_plan()?'is-current':'').'">'.($id===self::get_user_plan()&&is_user_logged_in()?'<span class="bh-current-badge">Current plan</span>':'').'<h4>'.esc_html($plan['name']).'</h4><p>'.esc_html($plan['description']).'</p><div class="bh-public-price">'.esc_html($symbol).number_format($price,2).' <small>'.esc_html($billing_label).'</small></div><ul>';foreach((array)($plan['features']??[]) as $fk=>$on){if(!$on)continue;$all=self::subscription_features()[$type]??[];echo '<li>✓ '.esc_html($all[$fk]??ucwords(str_replace('_',' ',$fk))).'</li>';}echo '</ul><a class="bh-pricing-button" href="'.esc_url($join).'">'.esc_html($plan['cta']??'Choose plan').'</a></article>'; }echo '</div></section>';}
    $enabled_methods=array_keys(array_filter($methods));if($enabled_methods){echo '<p class="bh-payment-note">Payment options: '.esc_html(implode(' · ',array_map(function($m){return ['stripe'=>'Stripe','paypal'=>'PayPal','bank'=>'Manual / bank transfer','free'=>'Free checkout'][$m]??ucfirst($m);},$enabled_methods))).'</p>';}
    echo '</div>';return ob_get_clean();
  }
  public static function signup_shortcode($atts=[]){
    $settings=get_option('bubbahub_settings',[]);$signup=(array)($settings['signup']??[]);$plans=self::subscription_plans();$type=sanitize_key($_GET['bh_type']??'parent');if(!in_array($type,['parent','leader'],true))$type='parent';$plan=sanitize_key($_GET['bh_plan']??($signup[$type.'_default_plan']??($type==='leader'?'leader-basic':'parent-free')));if(!isset($plans[$plan])||($plans[$plan]['user_type']??'')!==$type)$plan=$type==='leader'?'leader-basic':'parent-free';$message='';
    if(isset($_POST['bh_signup_nonce'])&&wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bh_signup_nonce'])),'bubbahub_signup')){ $email=sanitize_email(wp_unslash($_POST['email']??''));$username=sanitize_user(wp_unslash($_POST['username']??''));$password=(string)($_POST['password']??'');$post_type=sanitize_key($_POST['user_type']??$type);$post_plan=sanitize_key($_POST['plan']??$plan);if(!in_array($post_type,['parent','leader'],true)||!isset($plans[$post_plan])||($plans[$post_plan]['user_type']??'')!==$post_type)$message='<div class="bh-signup-error">Invalid account type or plan.</div>';elseif($post_type==='parent'&&isset($signup['parent_enabled'])&&!$signup['parent_enabled'])$message='<div class="bh-signup-error">Parent signup is currently closed.</div>';elseif($post_type==='leader'&&isset($signup['leader_enabled'])&&!$signup['leader_enabled'])$message='<div class="bh-signup-error">Leader signup is currently closed.</div>';elseif(!$email||!is_email($email)||!$username||strlen($password)<8)$message='<div class="bh-signup-error">Please enter a valid email, username and password of at least 8 characters.</div>';elseif(email_exists($email)||username_exists($username))$message='<div class="bh-signup-error">That email or username is already in use.</div>';else{$uid=wp_create_user($username,$password,$email);if(is_wp_error($uid))$message='<div class="bh-signup-error">'.esc_html($uid->get_error_message()).'</div>';else{$role=$post_type==='leader'?'bubbahub_leader':'bubbahub_parent';$u=new WP_User($uid);$u->set_role($role);update_user_meta($uid,'bubbahub_user_type',$post_type);update_user_meta($uid,'bubbahub_plan_id',$post_plan);update_user_meta($uid,'bubbahub_subscription_status',((float)$plans[$post_plan]['price']>0)?'pending_payment':'active');if($post_type==='leader'&&(!empty($signup['leader_approval'])||!isset($signup['leader_approval'])))update_user_meta($uid,'bubbahub_leader_status','pending');else update_user_meta($uid,'bubbahub_leader_status','active');$message='<div class="bh-signup-success"><strong>Account created.</strong> Your selected plan is '.esc_html($plans[$post_plan]['name']).'.'.((float)$plans[$post_plan]['price']>0?' Payment setup is required before the paid subscription becomes active.':'').' <a href="'.esc_url(wp_login_url()).'">Sign in</a>.</div>';}}}
    ob_start();echo '<div class="bh-public-signup"><div class="bh-pricing-head"><span class="bh-builder-eyebrow">Join BubbaHub</span><h2>Create your account</h2><p>Choose whether you are joining as a parent or a leader.</p></div>'.$message.'<form method="post" class="bh-signup-form">'.wp_nonce_field('bubbahub_signup','bh_signup_nonce',true,false).'<label>Account type<select name="user_type" id="bh-signup-type"><option value="parent" '.selected($type,'parent',false).'>Parent</option><option value="leader" '.selected($type,'leader',false).'>Leader</option></select></label><label>Plan<select name="plan" id="bh-signup-plan">';foreach($plans as $id=>$p)if(empty($p['enabled'])===false)echo '<option data-user-type="'.esc_attr($p['user_type']??''). '" value="'.esc_attr($id).'" '.selected($plan,$id,false).'>'.esc_html($p['name']).' — '.esc_html(($p['currency']??'GBP')==='GBP'?'£':($p['currency']??'GBP').' ').number_format((float)($p['price']??0),2).' / '.esc_html($p['billing']??'yearly').'</option>';echo '</select></label><label>Username<input type="text" name="username" required autocomplete="username"></label><label>Email<input type="email" name="email" required autocomplete="email"></label><label>Password<input type="password" name="password" required minlength="8" autocomplete="new-password"></label><button class="bh-pricing-button" type="submit">Create account</button></form><script>(function(){var t=document.getElementById("bh-signup-type"),p=document.getElementById("bh-signup-plan");if(!t||!p)return;function sync(){var opts=p.querySelectorAll("option"),first=null;opts.forEach(function(o){var show=o.getAttribute("data-user-type")===t.value;o.hidden=!show;if(show&&!first)first=o;});if(!p.querySelector("option:not([hidden]):checked"))p.value=first?first.value:"";}t.addEventListener("change",sync);sync();})();</script></div>';return ob_get_clean();
  }
  public static function activate(){
    global $wpdb;
    require_once ABSPATH.'wp-admin/includes/upgrade.php';
    $charset=$wpdb->get_charset_collate();
    $tables=[
      'children'=>"CREATE TABLE {$wpdb->prefix}bubbahub_children (id bigint unsigned NOT NULL AUTO_INCREMENT,user_id bigint unsigned NOT NULL,name varchar(190) NOT NULL,birth_date date NULL,stage varchar(80) NULL,notes text NULL,created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY user_id(user_id)) $charset;",
      'saved'=>"CREATE TABLE {$wpdb->prefix}bubbahub_saved (id bigint unsigned NOT NULL AUTO_INCREMENT,user_id bigint unsigned NOT NULL,object_type varchar(40) NOT NULL,object_id bigint unsigned NOT NULL,created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY user_object(user_id,object_type,object_id)) $charset;",
      'notes'=>"CREATE TABLE {$wpdb->prefix}bubbahub_notes (id bigint unsigned NOT NULL AUTO_INCREMENT,user_id bigint unsigned NOT NULL,group_id bigint unsigned NOT NULL,note text NOT NULL,created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY user_group(user_id,group_id)) $charset;",
      'members'=>"CREATE TABLE {$wpdb->prefix}bubbahub_members (id bigint unsigned NOT NULL AUTO_INCREMENT,user_id bigint unsigned NOT NULL,group_id bigint unsigned NOT NULL,status varchar(30) NOT NULL DEFAULT 'active',joined_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY user_group(user_id,group_id)) $charset;",
      'buddy_messages'=>"CREATE TABLE {$wpdb->prefix}bubbahub_buddy_messages (id bigint unsigned NOT NULL AUTO_INCREMENT,sender_id bigint unsigned NOT NULL,recipient_id bigint unsigned NOT NULL,message text NOT NULL,created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,read_at datetime NULL,PRIMARY KEY(id),KEY recipient(recipient_id),KEY sender(sender_id)) $charset;",
    ];
    foreach($tables as $sql) dbDelta($sql);
    self::post_types(); self::taxonomies(); self::roles(); self::seed(); self::ensure_pages(); update_option('bubbahub_db_version',BUBBAHUB_VERSION); flush_rewrite_rules();
  }
  public static function deactivate(){ flush_rewrite_rules(); }
  public function __construct(){
    if(version_compare((string)get_option('bubbahub_db_version','0'),BUBBAHUB_VERSION,'<')){ self::activate(); update_option('bubbahub_db_version',BUBBAHUB_VERSION); }
    add_action('init',[__CLASS__,'post_types']); add_action('init',[__CLASS__,'taxonomies']); add_action('init',[__CLASS__,'rewrite_rules']); add_filter('query_vars',[__CLASS__,'query_vars']); add_action('template_redirect',[__CLASS__,'group_permalink_guard']);
    add_action('wp_enqueue_scripts',[$this,'assets']); add_shortcode('bubba_hub',[$this,'shortcode']); add_shortcode('bubbahub_pricing',[__CLASS__,'pricing_shortcode']); add_shortcode('bubbahub_signup',[__CLASS__,'signup_shortcode']);
    add_action('rest_api_init',['BubbaHubRest','register']); add_action('admin_menu',['BubbaHubAdmin','menu']);
    add_action('admin_init',['BubbaHubAdmin','settings']); BubbaHubAdmin::register_directory_hooks();
  }
  public function assets(){
    $post = get_post();
    $is_app = is_front_page() || (is_singular() && has_shortcode($post->post_content ?? '','bubba_hub'));
    if (!$is_app) return;
    wp_enqueue_style('bubbahub',BUBBAHUB_URL.'assets/css/app.css',[],BUBBAHUB_VERSION);
    wp_enqueue_script('bubbahub',BUBBAHUB_URL.'assets/js/app.js',[],BUBBAHUB_VERSION,true);
    $pages = self::page_map();
    wp_localize_script('bubbahub','BubbaHubConfig',[
      'api'=>esc_url_raw(rest_url('bubbahub/v1/')),
      'nonce'=>wp_create_nonce('wp_rest'),
      'initialGroup'=>sanitize_title((string)get_query_var('bubbahub_group')),
      'directoryUrl'=>esc_url_raw($pages['directory']['url'] ?? home_url('/directory/')),
      'pageUrls'=>array_map(function($x){return $x['url'];},$pages),
      'loggedIn'=>is_user_logged_in(),
      'user'=>is_user_logged_in()?['id'=>get_current_user_id(),'name'=>wp_get_current_user()->display_name,'roles'=>array_values((array)wp_get_current_user()->roles)]:null,
      'themeShell'=>current_theme_supports('bubbahub-shell'),
      'initialPage'=>self::initial_page(),
      'internalNav'=>false
    ]);
  }
  public static function rewrite_rules(){ add_rewrite_rule('^directory/group/([^/]+)/?$', 'index.php?pagename=directory&bubbahub_group=$matches[1]', 'top'); }
  public static function query_vars($vars){ $vars[]='bubbahub_group'; return $vars; }
  public static function group_permalink_guard(){ if(get_query_var('bubbahub_group') && !get_page_by_path('directory')){ wp_safe_redirect(home_url('/directory/'),301); exit; } }
  public static function post_types(){
    $common=['public'=>true,'show_ui'=>true,'show_in_rest'=>true,'supports'=>['title','editor','thumbnail','excerpt','author'],'menu_position'=>25,'capability_type'=>['bubbahub_item','bubbahub_items'],'map_meta_cap'=>true];
    register_post_type('bh_group',$common+['labels'=>['name'=>'Groups','singular_name'=>'Group'],'menu_icon'=>'dashicons-groups','has_archive'=>true,'rewrite'=>['slug'=>'groups']]);
    register_post_type('bh_event',$common+['labels'=>['name'=>'Events','singular_name'=>'Event'],'menu_icon'=>'dashicons-calendar-alt','has_archive'=>true,'rewrite'=>['slug'=>'events']]);
    register_post_type('bh_app',$common+['labels'=>['name'=>'Apps','singular_name'=>'App'],'menu_icon'=>'dashicons-smartphone','has_archive'=>true]);
    register_post_type('bh_specialist',$common+['labels'=>['name'=>'Specialists','singular_name'=>'Specialist'],'menu_icon'=>'dashicons-businessperson']);
    register_post_type('bh_article',$common+['labels'=>['name'=>'Support Library','singular_name'=>'Support Article'],'menu_icon'=>'dashicons-book-alt']);
  }
  public static function taxonomies(){
    foreach(['bh_category'=>'Categories','bh_stage'=>'Life Stages'] as $tax=>$label) register_taxonomy($tax,['bh_group','bh_event','bh_app'],['public'=>true,'show_ui'=>true,'show_in_rest'=>true,'labels'=>['name'=>$label,'singular_name'=>rtrim($label,'s')]]);
  }
  public static function roles(){
    $caps=['read'=>true,'upload_files'=>true,'edit_bubbahub_items'=>true,'publish_bubbahub_items'=>true,'delete_bubbahub_items'=>true,'edit_published_bubbahub_items'=>true,'delete_published_bubbahub_items'=>true];
    $roles=[
      'bubbahub_parent'=>['BubbaHub Parent',['read'=>true]],
      'bubbahub_leader'=>['BubbaHub Leader',$caps],
      'leader'=>['Leader',$caps],
      'leaderpro'=>['Leader Pro',$caps],
    ];
    foreach($roles as $role_key=>$role_data){
      $role=get_role($role_key);
      if(!$role) $role=add_role($role_key,$role_data[0],$role_data[1]);
      if($role){ foreach(array_keys($caps) as $c) $role->add_cap($c); }
    }
    $admin=get_role('administrator'); if($admin){ foreach(array_keys($caps) as $c) $admin->add_cap($c); $admin->add_cap('manage_bubbahub'); }
  }
  public static function seed(){
    if(get_option('bubbahub_seeded')) return;
    $groups=[['Little Explorers','Music','Weekly sensory music and movement sessions for babies and toddlers.'],['Splash Tots','Swimming','Gentle parent-and-baby swimming classes with warm-water sessions.'],['Tiny Yogis','Yoga','Calm, playful yoga and movement for little ones and their grown-ups.'],['Wild & Free','Outdoors','Outdoor play, nature walks and muddy adventures for families.']];
    foreach($groups as $g){$p=wp_insert_post(['post_title'=>$g[0],'post_type'=>'bh_group','post_status'=>'publish','post_content'=>$g[2]]); if($p && !is_wp_error($p)) wp_set_object_terms($p,$g[1],'bh_category');}
    $events=[['Baby Music Morning','Little Explorers','2026-09-15 10:00:00'],['Family Swim','Splash Tots','2026-09-17 11:30:00'],['Park Playdate','Wild & Free','2026-09-20 10:00:00']];
    foreach($events as $e){$p=wp_insert_post(['post_title'=>$e[0],'post_type'=>'bh_event','post_status'=>'publish','post_content'=>'Family-friendly event.']); if($p && !is_wp_error($p)) {update_post_meta($p,'event_datetime',$e[2]);update_post_meta($p,'event_group',$e[1]);}}
    $apps=[['Huckleberry','Smart sleep tracking with personalised insights and wake window guidance.','Sleep Tracking'],['Ovia Pregnancy','Pregnancy tracking, symptom logging and appointment management.','Pregnancy'],['Wonder Weeks','Track developmental leaps and understand fussy periods.','Development'],['Tinybeans','Private family journal for memories and milestones.','Family Memories']];
    foreach($apps as $a){$p=wp_insert_post(['post_title'=>$a[0],'post_type'=>'bh_app','post_status'=>'publish','post_content'=>$a[1]]); if($p && !is_wp_error($p)) wp_set_object_terms($p,$a[2],'bh_category');}
    update_option('bubbahub_seeded',1);
  }


  public static function ensure_pages(){
    $defaults=['home'=>'Home','directory'=>'Directory','events'=>'What’s On','apps'=>'Apps','support'=>'Support & Guidance','myhub'=>'My Hub','buddy'=>'Bubba Buddy','antenatal'=>'Antenatal Planner','leader'=>'Leader Portal','pricing'=>'Pricing Plans','join'=>'Join BubbaHub'];
    $saved=get_option('bubbahub_pages',[]);
    foreach($defaults as $key=>$title){
      $id=absint($saved[$key]['id']??0);$p=$id?get_post($id):null;
      if(!$p){$slug=$key==='home'?'bubba-hub':$key;$p=get_page_by_path($slug);if(!$p){$new=wp_insert_post(['post_title'=>$title,'post_name'=>$slug,'post_status'=>'publish','post_type'=>'page','post_content'=>($key==='pricing'?'[bubbahub_pricing]':($key==='join'?'[bubbahub_signup]':'[bubba_hub page="'.$key.'"]'))],true);if(!is_wp_error($new))$p=get_post($new);}}
      if($p)$saved[$key]=['id'=>$p->ID,'slug'=>$p->post_name];
    }
    update_option('bubbahub_pages',$saved);
  }
  public static function page_map(){
    $defaults=['home'=>'Home','directory'=>'Directory','events'=>'What’s On','apps'=>'Apps','support'=>'Support & Guidance','myhub'=>'My Hub','buddy'=>'Bubba Buddy','antenatal'=>'Antenatal Planner','leader'=>'Leader Portal','pricing'=>'Pricing Plans','join'=>'Join BubbaHub'];
    $saved=get_option('bubbahub_pages',[]); $out=[];
    foreach($defaults as $key=>$title){
      $id=absint($saved[$key]['id']??0); $p=$id?get_post($id):null;
      if($p && $p->post_status!=='trash') $out[$key]=['id'=>$p->ID,'title'=>$p->post_title,'url'=>get_permalink($p->ID),'shortcode'=>($key==='pricing'?'[bubbahub_pricing]':($key==='join'?'[bubbahub_signup]':'[bubba_hub page="'.$key.'"]'))];
    }
    return $out;
  }
  private static function initial_page(){
    $slug='home';
    if(get_query_var('bubbahub_group')) return 'group';
    $post=get_post();
    if($post && $post->post_type==='page'){
      $saved=get_option('bubbahub_pages',[]);
      foreach($saved as $key=>$cfg){ if(!empty($cfg['id']) && (int)$cfg['id']===(int)$post->ID) return sanitize_key($key); }
      if(has_shortcode($post->post_content??'','bubbahub_pricing')) return 'pricing';
      if(has_shortcode($post->post_content??'','bubbahub_signup')) return 'join';
      if(has_shortcode($post->post_content??'','bubba_hub')){
        if (preg_match("/\[bubba_hub[^\]]*page=[\"']([^\"']+)[\"']/", $post->post_content, $m)) return sanitize_key($m[1]);
      }
    }
    return $slug;
  }
  public function shortcode($atts=[]){ $atts=shortcode_atts(['page'=>''],(array)$atts,'bubba_hub'); if(!empty($atts['page'])) $GLOBALS['bubbahub_shortcode_page']=sanitize_key($atts['page']); ob_start(); include BUBBAHUB_DIR.'templates/app.php'; return ob_get_clean(); }
}
