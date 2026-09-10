<?php
if (!defined('ABSPATH')) exit;

class BubbaHubShortcodes {
  public static function register(){
    remove_shortcode('bubba_hub');
    add_shortcode('bubba_hub',[__CLASS__,'shortcode']);
  }
  public static function shortcode($atts=[],$content=null){
    $atts=shortcode_atts(['page'=>'home'],(array)$atts,'bubba_hub');
    $page=sanitize_key((string)$atts['page']);
    if($page==='') $page='home';
    $aliases=['index'=>'home','start'=>'home','listings'=>'directory','directory-builder'=>'directory','my-hub'=>'dashboard','account'=>'profile','calendar'=>'bookings','payment'=>'wallet','plans'=>'pricing','register'=>'signup','join'=>'signup'];
    if(isset($aliases[$page])) $page=$aliases[$page];
    if($page==='pricing' && method_exists('BubbaHub','pricing_shortcode')) return BubbaHub::pricing_shortcode($atts);
    if($page==='signup' && method_exists('BubbaHub','signup_shortcode')) return BubbaHub::signup_shortcode($atts);
    $instance=isset($GLOBALS['bubbahub_instance'])?$GLOBALS['bubbahub_instance']:null;
    $handlers=['home'=>'render_home','directory'=>'render_directory','groups'=>'render_groups','group'=>'render_group','dashboard'=>'render_dashboard','profile'=>'render_profile','bookings'=>'render_bookings','wallet'=>'render_wallet'];
    if($instance instanceof BubbaHub && isset($handlers[$page]) && method_exists($instance,$handlers[$page])) return (string)call_user_func([$instance,$handlers[$page]],$atts,$content);
    if($page==='home') return '<div class="bh-app bh-app-home"><div class="bh-builder-eyebrow">BubbaHub</div><h1>Welcome to BubbaHub</h1><p>Your sunny, supportive corner for families and local groups across Devon & Cornwall.</p></div>';
    $label=ucwords(str_replace(['-','_'],' ',$page));
    return '<div class="bh-shortcode-message"><h2>'.esc_html($label).'</h2><p>The BubbaHub page is not available yet.</p></div>';
  }
}
add_action('wp_loaded',['BubbaHubShortcodes','register'],20);
