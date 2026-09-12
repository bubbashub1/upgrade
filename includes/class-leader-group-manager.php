<?php
if (!defined('ABSPATH')) exit;

class BubbaHubLeaderGroupManager {
  public static function register(){
    add_action('rest_api_init',[__CLASS__,'rest']);
    add_filter('the_content',[__CLASS__,'append'],35);
    add_shortcode('bubbahub_leader_groups',[__CLASS__,'shortcode']);
  }
  private static function can(){return is_user_logged_in()&&(current_user_can('manage_bubbahub')||current_user_can('edit_bubbahub_items')||BubbaHub::get_user_type()==='leader');}
  private static function owns($post){return current_user_can('manage_bubbahub')||(int)$post->post_author===get_current_user_id()||(string)get_post_meta($post->ID,BubbaHubListings::META_OWNER,true)===(string)wp_get_current_user()->user_login;}
  public static function rest(){
    register_rest_route('bubbahub/v1','/leader/groups',['methods'=>'GET','callback'=>[__CLASS__,'list'],'permission_callback'=>[__CLASS__,'can']]);
    register_rest_route('bubbahub/v1','/leader/groups',['methods'=>'POST','callback'=>[__CLASS__,'create'],'permission_callback'=>[__CLASS__,'can']]);
  }
  public static function list(){
    $args=['post_type'=>'bh_group','post_status'=>['publish','draft','pending'],'posts_per_page'=>-1,'orderby'=>'modified','order'=>'DESC'];
    if(!current_user_can('manage_bubbahub'))$args['author']=get_current_user_id();
    return rest_ensure_response(BubbaHubListings::query($args));
  }
  public static function create($request){
    if(!self::can())return new WP_Error('forbidden','You do not have permission to create groups.',['status'=>403]);
    $d=$request->get_json_params();$title=sanitize_text_field($d['title']??'');$content=wp_kses_post($d['content']??'');
    if($title==='')return new WP_Error('invalid_title','A group name is required.',['status'=>400]);
    $id=wp_insert_post(['post_type'=>'bh_group','post_status'=>'publish','post_title'=>$title,'post_content'=>$content,'post_author'=>get_current_user_id()],true);
    if(is_wp_error($id))return $id;
    update_post_meta($id,BubbaHubListings::META_OWNER,wp_get_current_user()->user_login);
    $keys=['street','city','region','zip','website','email','facebook','instagram','price','term_time','age_range','day','timetable','business_hours','latitude','longitude','session_length'];
    foreach($keys as $k){if(!array_key_exists($k,$d))continue;$v=$d[$k];if(in_array($k,['website','facebook','instagram'],true))$v=esc_url_raw($v);elseif($k==='email')$v=sanitize_email($v);elseif(in_array($k,['latitude','longitude'],true))$v=is_numeric($v)?(string)(float)$v:'';elseif(in_array($k,['timetable','business_hours'],true))$v=sanitize_textarea_field($v);else $v=sanitize_text_field($v);update_post_meta($id,'_bubbahub_'.$k,$v);}
    update_post_meta($id,'_bubbahub_last_frontend_editor',get_current_user_id());
    return rest_ensure_response(BubbaHubListings::get($id));
  }
  public static function append($content){
    if(is_admin()||!is_page('leader')||!in_the_loop()||!is_main_query()||!self::can()||has_shortcode((string)$content,'bubbahub_leader_groups'))return $content;
    return $content.self::shortcode();
  }
  public static function shortcode(){
    if(!self::can())return '';
    if(!empty($_GET['edit_group'])&&class_exists('BubbaHubListingEditor'))return BubbaHubListingEditor::shortcode(['id'=>absint($_GET['edit_group'])]);
    ob_start(); ?>
    <section class="bh-leader-manager"><div class="bh-leader-manager-head"><div><span>Leader Portal</span><h2>My groups</h2><p>Add a new group or choose an existing group to edit.</p></div><button type="button" class="bh-btn" data-bh-new-group>Add new group</button></div><div class="bh-leader-groups" data-bh-groups><p>Loading your groups…</p></div><form class="bh-leader-new" data-bh-new-form hidden><div class="bh-leader-fields"><label>Group name<input name="title" required></label><label>Description<textarea name="content" rows="5"></textarea></label><label>Town / city<input name="city"></label><label>Region / county<input name="region"></label><label>Postcode<input name="zip"></label><label>Age range<input name="age_range" placeholder="e.g. 0-3 years"></label><label>Price<input name="price"></label><label>Days<input name="day"></label><label>Timetable<textarea name="timetable" rows="3"></textarea></label><label>Website<input type="url" name="website"></label><label>Email<input type="email" name="email"></label><label>Latitude<input name="latitude" type="number" step="any"></label><label>Longitude<input name="longitude" type="number" step="any"></label></div><div><button class="bh-btn" type="submit">Create group</button> <button class="bh-btn secondary" type="button" data-bh-cancel>Cancel</button></div><p data-bh-status aria-live="polite"></p></form></section>
    <style>.bh-leader-manager{max-width:1100px;margin:28px auto;padding:24px;border:1px solid color-mix(in srgb,currentColor 12%,transparent);border-radius:20px;background:color-mix(in srgb,currentColor 2%,transparent)}.bh-leader-manager-head{display:flex;justify-content:space-between;gap:18px;align-items:center;margin-bottom:20px}.bh-leader-manager-head span{font-size:.8em;text-transform:uppercase;letter-spacing:.08em;font-weight:700;opacity:.65}.bh-leader-manager-head h2{margin:.2em 0}.bh-leader-manager-head p{margin:0;opacity:.7}.bh-leader-groups{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.bh-leader-group{padding:16px;border-radius:15px;border:1px solid color-mix(in srgb,currentColor 12%,transparent);background:transparent}.bh-leader-group h3{margin-top:0}.bh-leader-group a{display:inline-block;margin-top:8px}.bh-leader-new{margin-top:22px;padding-top:22px;border-top:1px solid color-mix(in srgb,currentColor 12%,transparent)}.bh-leader-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.bh-leader-fields label{display:flex;flex-direction:column;gap:5px;font-weight:600}.bh-leader-fields input,.bh-leader-fields textarea{font:inherit;font-weight:400;padding:10px;border:1px solid color-mix(in srgb,currentColor 16%,transparent);border-radius:10px;background:transparent}.bh-leader-fields label:nth-child(2),.bh-leader-fields label:nth-child(9){grid-column:1/-1}@media(max-width:700px){.bh-leader-manager-head{flex-direction:column;align-items:stretch}.bh-leader-groups,.bh-leader-fields{grid-template-columns:1fr}.bh-leader-fields label:nth-child(2),.bh-leader-fields label:nth-child(9){grid-column:auto}}</style>
    <script>(function(){const root=document.currentScript?.parentElement;if(!root)return;const groups=root.querySelector('[data-bh-groups]'),form=root.querySelector('[data-bh-new-form]'),api='<?php echo esc_js(rest_url('bubbahub/v1/leader/groups')); ?>',nonce='<?php echo esc_js(wp_create_nonce('wp_rest')); ?>';async function load(){try{const r=await fetch(api,{headers:{'X-WP-Nonce':nonce}}),d=await r.json();if(!r.ok)throw new Error(d.message||'Unable to load groups');const xs=d.items||[];groups.innerHTML=xs.length?xs.map(x=>'<article class="bh-leader-group"><h3>'+esc(x.title)+'</h3><p>'+esc(x.fields?.city||'')+(x.fields?.age_range?' · '+esc(x.fields.age_range):'')+'</p><a class="bh-btn secondary" href="?edit_group='+encodeURIComponent(x.id)+'">Edit group</a></article>').join(''):'<p>No groups yet.</p>'}catch(e){groups.innerHTML='<p>'+esc(e.message)+'</p>'}}function esc(s){return String(s??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]))}root.querySelector('[data-bh-new-group]').onclick=()=>form.hidden=false;root.querySelector('[data-bh-cancel]').onclick=()=>form.hidden=true;form.onsubmit=async e=>{e.preventDefault();const st=form.querySelector('[data-bh-status]'),data={};new FormData(form).forEach((v,k)=>data[k]=v);st.textContent='Creating…';try{const r=await fetch(api,{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},body:JSON.stringify(data)}),j=await r.json();if(!r.ok)throw new Error(j.message||'Unable to create group');st.textContent='Group created.';form.reset();form.hidden=true;load()}catch(err){st.textContent=err.message}};load();})();</script>
    <?php return ob_get_clean();
  }
}
add_action('plugins_loaded',['BubbaHubLeaderGroupManager','register'],28);
