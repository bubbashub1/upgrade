<?php
if (!defined('ABSPATH')) exit;
class BubbaHubAdmin {
  public static function menu(){
    add_menu_page('BubbaHub','BubbaHub','manage_bubbahub','bubbahub',[__CLASS__,'dashboard'],'dashicons-heart',24);
    add_submenu_page('bubbahub','Dashboard','Dashboard','manage_bubbahub','bubbahub',[__CLASS__,'dashboard']);
    add_submenu_page('bubbahub','Leader Portal','Leader Portal','edit_bubbahub_items',[__CLASS__,'leader_portal']);
    add_submenu_page('bubbahub','Directory Builder','Directory Builder','manage_bubbahub','bubbahub-directory-builder',[__CLASS__,'directory_builder']);
    add_submenu_page('bubbahub','Visual Listing Builder','Visual Listing Builder','manage_bubbahub','bubbahub-visual-builder',[__CLASS__,'visual_builder']);
    add_submenu_page('bubbahub','Pages & Shortcodes','Pages & Shortcodes','manage_bubbahub','bubbahub-pages',[__CLASS__,'pages_page']);
    add_submenu_page('bubbahub','Settings','Settings','manage_bubbahub','bubbahub-settings',[__CLASS__,'settings_page']);
  }
  public static function dashboard(){
    $counts=[];foreach(['bh_group'=>'Groups','bh_event'=>'Events','bh_app'=>'Apps','bh_specialist'=>'Specialists','bh_article'=>'Support articles'] as $type=>$label)$counts[$label]=wp_count_posts($type)->publish;
    echo '<div class="wrap"><h1>BubbaHub</h1><p>Manage the family community from WordPress.</p><div style="display:flex;gap:16px;flex-wrap:wrap">';foreach($counts as $label=>$count)echo '<div style="background:#fff;border:1px solid #ddd;border-radius:12px;padding:20px;min-width:160px"><strong style="font-size:28px">'.esc_html($count).'</strong><br>'.esc_html($label).'</div>';echo '</div><h2>Frontend</h2><p>Create a page with <code>[bubba_hub]</code>. Leaders can use <a href="'.esc_url(home_url('/leader/')).'">the Leader Portal</a> when signed in.</p></div>';
  }
  public static function leader_portal(){
    echo '<div class="wrap"><h1>BubbaHub Leader Portal</h1><p>Open the full leader dashboard in the frontend.</p><p><a class="button button-primary" target="_blank" href="'.esc_url(home_url('/leader/')).'">Open Leader Portal</a></p><hr><h2>Quick content links</h2><p><a href="'.esc_url(admin_url('edit.php?post_type=bh_group')).'">Manage Groups</a> &nbsp; <a href="'.esc_url(admin_url('edit.php?post_type=bh_event')).'">Manage Events</a> &nbsp; <a href="'.esc_url(admin_url('edit.php?post_type=bh_article')).'">Manage Support</a></p></div>';
  }
  public static function visual_builder(){
    if(!current_user_can('manage_bubbahub')) return;
    $types=get_post_types(['public'=>true],'objects'); unset($types['attachment']);
    $selected=sanitize_key($_GET['post_type']??'bh_group'); if(!isset($types[$selected])) $selected='bh_group';
    $post_id=absint($_GET['post_id']??0); if($post_id && get_post_type($post_id)!==$selected) $post_id=0;
    $posts=get_posts(['post_type'=>$selected,'post_status'=>['publish','draft','pending'],'numberposts'=>100,'orderby'=>'title','order'=>'ASC']);
    $default=['hero','about','schedule','venue','map','gallery','other_classes','booking','contact'];
    $layout=get_post_meta($post_id,'_bubbahub_builder_layout',true); if(!is_array($layout)||!$layout) $layout=$default;
    $labels=['hero'=>['Hero','Title, image and main call to action'],'about'=>['About','Description and introduction'],'schedule'=>['Schedule','Weekly classes and times'],'venue'=>['Venue','Venue details and address'],'map'=>['Map','Interactive map location'],'gallery'=>['Gallery','Photos and images'],'other_classes'=>['Other classes','Show other listings by this leader'],'booking'=>['Book this class','Tickets, capacity and online booking'],'contact'=>['Contact','Email, phone and social links']];
    echo '<div class="wrap"><h1>Visual Listing Builder</h1><p>Build the order of each public listing using drag and drop. Choose a listing, drag blocks into the canvas, reorder them and save.</p>';
    echo '<form method="get" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap;margin:20px 0"><input type="hidden" name="page" value="bubbahub-visual-builder"><label><strong>Listing type</strong><br><select name="post_type" onchange="this.form.submit()">';foreach($types as $name=>$obj)echo '<option value="'.esc_attr($name).'" '.selected($selected,$name,false).'>'.esc_html($obj->labels->singular_name).'</option>';echo '</select></label>';
    echo '<label><strong>Listing</strong><br><select name="post_id" onchange="this.form.submit()"><option value="0">— Choose a listing —</option>';foreach($posts as $post)echo '<option value="'.(int)$post->ID.'" '.selected($post_id,$post->ID,false).'>'.esc_html($post->post_title?:'(no title)').'</option>';echo '</select></label></form>';
    if(!$post_id){echo '<div class="notice notice-info inline"><p>Select a listing above to start building its layout.</p></div></div>';return;}
    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" id="bh-visual-builder-form">';wp_nonce_field('bubbahub_visual_builder_save','bubbahub_visual_builder_nonce');echo '<input type="hidden" name="action" value="bubbahub_save_visual_builder"><input type="hidden" name="post_id" value="'.(int)$post_id.'">';
    echo '<div class="bh-vb-wrap"><div class="bh-vb-panel"><h2>Blocks</h2><p>Drag a block to the page, or click <b>Add</b>.</p><div id="bh-vb-palette">';foreach($labels as $key=>$v)echo '<div class="bh-vb-palette-item" draggable="true" data-block="'.esc_attr($key).'"><div><strong>'.esc_html($v[0]).'</strong><small>'.esc_html($v[1]).'</small></div><button type="button" class="button">Add</button></div>';echo '</div></div>';
    echo '<div class="bh-vb-canvas-panel"><div class="bh-vb-head"><div><h2>'.esc_html(get_the_title($post_id)).'</h2><span>Drag blocks to reorder</span></div><div><a class="button" target="_blank" href="'.esc_url(get_permalink($post_id)).'">View listing</a> <button class="button button-primary" type="submit">Save layout</button></div></div><div id="bh-vb-canvas" class="bh-vb-canvas">';foreach($layout as $key){if(!isset($labels[$key]))continue;echo '<div class="bh-vb-block" draggable="true" data-block="'.esc_attr($key).'"><span class="bh-vb-grip">☷</span><div><strong>'.esc_html($labels[$key][0]).'</strong><small>'.esc_html($labels[$key][1]).'</small></div><button type="button" class="button-link-delete" data-remove>Remove</button><input type="hidden" name="layout[]" value="'.esc_attr($key).'" /></div>';}echo '</div><p class="description">The saved layout controls the order used by BubbaHub listing template. Content for each block continues to come from the listing fields, schedule, venue and booking data.</p></div></div></form>';
    echo '<style>.bh-vb-wrap{display:grid;grid-template-columns:340px 1fr;gap:20px;max-width:1200px}.bh-vb-panel,.bh-vb-canvas-panel{background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:18px}.bh-vb-palette-item,.bh-vb-block{display:flex;align-items:center;gap:10px;border:1px solid #dcdcde;border-radius:10px;padding:12px;margin:8px 0;background:#fff}.bh-vb-palette-item{cursor:grab}.bh-vb-palette-item small,.bh-vb-block small{display:block;color:#646970;margin-top:3px}.bh-vb-palette-item button{margin-left:auto}.bh-vb-head{display:flex;justify-content:space-between;align-items:center;gap:10px}.bh-vb-head h2{margin:0}.bh-vb-canvas{min-height:420px;padding:12px;border:2px dashed #c3c4c7;border-radius:12px;background:#f6f7f7}.bh-vb-block{cursor:grab;box-shadow:0 1px 2px rgba(0,0,0,.04)}.bh-vb-block.dragging{opacity:.45}.bh-vb-grip{font-size:22px;color:#8c8f94;cursor:grab}.bh-vb-block>div{flex:1}.bh-vb-block [data-remove]{margin-left:auto}.bh-vb-drop{height:6px;margin:3px 0;border-radius:4px;background:#2271b1}.bh-vb-block.is-duplicate{border-color:#dba617}@media(max-width:800px){.bh-vb-wrap{grid-template-columns:1fr}.bh-vb-head{flex-direction:column;align-items:flex-start}}</style>';
    echo '<script>(function(){const c=document.getElementById("bh-vb-canvas"),p=document.getElementById("bh-vb-palette");if(!c||!p)return;let drag=null;const add=k=>{if([...c.querySelectorAll("[data-block]")].some(x=>x.dataset.block===k))return;const src=p.querySelector(`[data-block="${k}"]`);const label=src.querySelector("strong").textContent,desc=src.querySelector("small").textContent;const el=document.createElement("div");el.className="bh-vb-block";el.draggable=true;el.dataset.block=k;el.innerHTML=`<span class="bh-vb-grip">☷</span><div><strong>${label}</strong><small>${desc}</small></div><button type="button" class="button-link-delete" data-remove>Remove</button><input type="hidden" name="layout[]" value="${k}">`;c.appendChild(el);bind(el)};const bind=el=>{el.addEventListener("dragstart",()=>{drag=el;el.classList.add("dragging")});el.addEventListener("dragend",()=>{drag=null;el.classList.remove("dragging")});el.addEventListener("dragover",e=>{e.preventDefault();if(drag&&drag!==el){const r=el.getBoundingClientRect();if(e.clientY<r.top+r.height/2)el.parentNode.insertBefore(drag,el);else el.parentNode.insertBefore(drag,el.nextSibling)}});el.querySelector("[data-remove]").onclick=()=>el.remove()};c.querySelectorAll(".bh-vb-block").forEach(bind);p.querySelectorAll(".bh-vb-palette-item").forEach(el=>{el.addEventListener("dragstart",()=>drag=null);el.addEventListener("dragend",()=>add(el.dataset.block));el.querySelector("button").onclick=()=>add(el.dataset.block)});c.addEventListener("dragover",e=>e.preventDefault());})();</script></div>';
  }
  public static function save_visual_builder(){if(!current_user_can('manage_bubbahub')||!check_admin_referer('bubbahub_visual_builder_save','bubbahub_visual_builder_nonce'))wp_die('You do not have permission to update the listing builder.');$id=absint($_POST['post_id']??0);if(!$id||!current_user_can('edit_post',$id))wp_die('You cannot edit this listing.');$allowed=['hero','about','schedule','venue','map','gallery','other_classes','booking','contact'];$raw=is_array($_POST['layout']??null)?$_POST['layout']:[];$out=[];foreach($raw as $x){$x=sanitize_key($x);if(in_array($x,$allowed,true)&&!in_array($x,$out,true))$out[]=$x;}update_post_meta($id,'_bubbahub_builder_layout',$out);wp_safe_redirect(add_query_arg(['page'=>'bubbahub-visual-builder','post_type'=>get_post_type($id),'post_id'=>$id,'updated'=>1],admin_url('admin.php')));exit;}
  public static function get_directory_fields($post_type){
    $all=get_option('bubbahub_directory_fields',[]);
    return !empty($all[$post_type]) && is_array($all[$post_type]) ? $all[$post_type] : [];
  }
  public static function directory_builder(){
    if(!current_user_can('manage_bubbahub')) return;

    $types=get_post_types(['public'=>true],'objects');
    unset($types['attachment']);
    $selected=sanitize_key($_GET['post_type']??'bh_group');
    if(!isset($types[$selected])) $selected='bh_group';

    $tab=sanitize_key($_GET['tab']??'add');
    $allowed_tabs=['general','add','single','archive','search'];
    if(!in_array($tab,$allowed_tabs,true)) $tab='add';

    $fields=self::get_directory_fields($selected);
    $layouts=get_option('bubbahub_directory_layouts',[]);
    $layout=isset($layouts[$selected]) && is_array($layouts[$selected]) ? $layouts[$selected] : [];
    $add_fields=isset($layout['add']) && is_array($layout['add']) ? $layout['add'] : [];
    $single_sections=isset($layout['single']) && is_array($layout['single']) ? $layout['single'] : [];
    $archive_fields=isset($layout['archive']) && is_array($layout['archive']) ? $layout['archive'] : [];
    $search_basic=isset($layout['search_basic']) && is_array($layout['search_basic']) ? $layout['search_basic'] : [];
    $search_advanced=isset($layout['search_advanced']) && is_array($layout['search_advanced']) ? $layout['search_advanced'] : [];

    if(!$add_fields){
      $add_fields=array_map(function($f){return ['key'=>$f['key'],'label'=>$f['label'],'type'=>$f['type'],'required'=>!empty($f['required'])];},$fields);
    }
    if(!$single_sections){
      $single_sections=[
        ['id'=>'description','label'=>'Description','icon'=>'dashicons-editor-alignleft','fields'=>['description']],
        ['id'=>'schedule','label'=>'Schedule','icon'=>'dashicons-calendar-alt','fields'=>['schedule']],
        ['id'=>'location','label'=>'Location','icon'=>'dashicons-location','fields'=>['venue','address','map']],
        ['id'=>'contact','label'=>'Contact','icon'=>'dashicons-email','fields'=>['email','website','social_links']],
      ];
    }
    if(!$archive_fields) $archive_fields=['featured_image','title','category','location','price','rating','favourite'];
    if(!$search_basic) $search_basic=['keyword','category','location'];
    if(!$search_advanced) $search_advanced=array_map(function($f){return $f['key'];},$fields);

    $obj=$types[$selected];
    $settings=isset($layout['general'])&&is_array($layout['general'])?$layout['general']:[];
    $singular=$settings['singular']??$obj->labels->singular_name;
    $plural=$settings['plural']??$obj->labels->name;
    $slug=$settings['slug']??($obj->rewrite['slug']??$selected);

    $base_url=admin_url('admin.php?page=bubbahub-directory-builder&post_type='.rawurlencode($selected));
    $tab_url=function($t)use($base_url){return $base_url.'&tab='.rawurlencode($t);};

    echo '<div class="wrap bh-builder-page">';
    echo '<div class="bh-builder-top"><div><div class="bh-builder-eyebrow">BubbaHub Directory Builder</div><h1>'.esc_html($plural).'<button type="button" class="bh-title-edit" title="Edit directory name">✎</button></h1><p>Configure the fields, listing layouts and search experience for this directory.</p></div><div class="bh-builder-actions"><a class="button" href="'.esc_url(admin_url('admin.php?page=bubbahub-visual-builder')).'">Visual Listing Builder</a><button form="bh-directory-builder-form" class="button button-primary" type="submit">Update</button></div></div>';

    echo '<div class="bh-directory-switch"><label><span>Directory</span><select onchange="location.href=\''.esc_js($base_url).'&tab='.esc_js($tab).'&post_type=\'+encodeURIComponent(this.value)">';
    foreach($types as $name=>$type_obj) echo '<option value="'.esc_attr($name).'" '.selected($selected,$name,false).'>'.esc_html($type_obj->labels->name).'</option>';
    echo '</select></label></div>';

    echo '<nav class="bh-builder-tabs">';
    $tabs=['general'=>'General','add'=>'Add Listing Form','single'=>'Single Page Layout','archive'=>'All Listing Layout','search'=>'Search Form'];
    foreach($tabs as $key=>$label) echo '<a class="'.($tab===$key?'active':'').'" href="'.esc_url($tab_url($key)).'"><span class="dashicons '.($key==='general'?'dashicons-admin-generic':($key==='add'?'dashicons-edit':($key==='single'?'dashicons-media-document':($key==='archive'?'dashicons-screenoptions':'dashicons-search')))).'"></span>'.esc_html($label).'</a>';
    echo '</nav>';

    if(!empty($_GET['updated'])) echo '<div class="notice notice-success is-dismissible"><p>Directory Builder settings updated.</p></div>';

    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" id="bh-directory-builder-form">';
    wp_nonce_field('bubbahub_directory_builder_save','bubbahub_directory_builder_nonce');
    echo '<input type="hidden" name="action" value="bubbahub_save_directory_fields"><input type="hidden" name="post_type" value="'.esc_attr($selected).'"><input type="hidden" name="builder_tab" value="'.esc_attr($tab).'">';

    if($tab==='general'){
      echo '<div class="bh-settings-grid"><section class="bh-card"><h2>General</h2><p class="description">Basic settings for this BubbaHub directory type.</p>';
      echo '<div class="bh-form-row"><label>Directory name<input type="text" name="general[plural]" value="'.esc_attr($plural).'"></label><label>Singular name<input type="text" name="general[singular]" value="'.esc_attr($singular).'"></label></div>';
      echo '<div class="bh-form-row"><label>URL slug<input type="text" name="general[slug]" value="'.esc_attr($slug).'"></label><label>Default archive view<select name="general[view]"><option value="grid" '.selected($settings['view']??'grid','grid',false).'>Grid</option><option value="list" '.selected($settings['view']??'grid','list',false).'>List</option><option value="map" '.selected($settings['view']??'grid','map',false).'>Map</option></select></label></div>';
      echo '<label class="bh-toggle-row"><input type="checkbox" name="general[enabled]" value="1" '.checked(($settings['enabled']??1),1,false).'><span><strong>Directory enabled</strong><small>Allow this directory type to appear in the public directory.</small></span></label></section>';
      echo '<section class="bh-card"><h2>BubbaHub behaviour</h2><label class="bh-toggle-row"><input type="checkbox" name="general[leader_submissions]" value="1" '.checked(($settings['leader_submissions']??1),1,false).'><span><strong>Leader submissions</strong><small>Allow eligible leaders to add and edit listings from the frontend.</small></span></label>';
      echo '<label class="bh-toggle-row"><input type="checkbox" name="general[reviews]" value="1" '.checked(($settings['reviews']??1),1,false).'><span><strong>Reviews and ratings</strong><small>Allow reviews to be displayed for this directory.</small></span></label></section></div>';
    }

    if($tab==='add'){
      echo '<div class="bh-builder-workspace bh-builder-add-workspace"><aside class="bh-field-palette"><div class="bh-panel-title"><h2>Form fields</h2><span class="bh-help">Drag or click Add</span></div>';
      echo '<div class="bh-palette-group"><h3>Preset fields</h3>';
      $preset=[
        'title'=>['Title','Listing title','dashicons-heading'],
        'description'=>['Description','Main listing description','dashicons-editor-alignleft'],
        'featured_image'=>['Featured Image','Main listing image','dashicons-format-image'],
        'category'=>['Category','Directory category','dashicons-category'],
        'location'=>['Location','Location taxonomy','dashicons-location'],
        'address'=>['Address','Full address','dashicons-location-alt'],
        'email'=>['Email','Contact email','dashicons-email'],
        'website'=>['Website','Website URL','dashicons-admin-links'],
        'social_links'=>['Social Links','Facebook, Instagram etc.','dashicons-share'],
        'venue'=>['Venue','Reusable BubbaHub venue','dashicons-building'],
        'schedule'=>['Schedule / Business Hours','Structured sessions repeater','dashicons-calendar-alt'],
        'age_range'=>['Age Range','Suitable child ages','dashicons-groups'],
        'price'=>['Price','Price / ticket information','dashicons-money-alt'],
        'session_length'=>['Session Length','Duration of session','dashicons-clock'],
        'sen_friendly'=>['SEN Friendly','SEN suitability','dashicons-heart'],
        'booking_url'=>['Booking URL','External booking link','dashicons-tickets-alt'],
      ];
      foreach($preset as $k=>$v) echo '<div class="bh-palette-item" draggable="true" data-key="'.esc_attr($k).'" data-label="'.esc_attr($v[0]).'" data-type="'.esc_attr($k==='schedule'?'schedule':'text').'"><span class="dashicons '.esc_attr($v[2]).'"></span><span><strong>'.esc_html($v[0]).'</strong><small>'.esc_html($v[1]).'</small></span><button type="button" class="button-link">Add</button></div>';
      echo '</div><div class="bh-palette-group"><h3>Custom fields</h3>';
      foreach($fields as $f) echo '<div class="bh-palette-item" draggable="true" data-key="'.esc_attr($f['key']).'" data-label="'.esc_attr($f['label']).'" data-type="'.esc_attr($f['type']).'"><span class="dashicons dashicons-admin-generic"></span><span><strong>'.esc_html($f['label']).'</strong><small>'.esc_html(ucfirst($f['type'])).'</small></span><button type="button" class="button-link">Add</button></div>';
      echo '</div><button type="button" class="bh-add-custom" id="bh-new-custom-field">＋ Add Custom Field</button></aside><main class="bh-builder-canvas"><div class="bh-canvas-head"><div><h2>Customize listing form</h2><p>Drag and drop fields to build the form leaders use to add or edit listings.</p></div><div class="bh-canvas-actions"><button type="button" class="button" id="bh-reset-add-form">Reset to Default</button><button type="button" class="button" id="bh-preview-form">Preview</button></div></div><div id="bh-active-fields" class="bh-active-list">';
      foreach($add_fields as $i=>$f){ $key=sanitize_key($f['key']??''); if(!$key)continue; $label=$f['label']??ucwords(str_replace('_',' ',$key)); $type=$f['type']??'text'; echo self::builder_field_card($i,$key,$label,$type,!empty($f['required'])); }
      echo '</div><div class="bh-drop-empty">Drag fields here or click <strong>Add</strong> in the left panel.</div></main><aside class="bh-field-inspector" id="bh-field-inspector"><div class="bh-inspector-empty"><span class="dashicons dashicons-admin-generic"></span><strong>Field Settings</strong><p>Select a field to configure its label, type, visibility and validation.</p></div><div class="bh-inspector-form" hidden><div class="bh-inspector-title"><h3>Field Settings</h3><button type="button" class="button-link" id="bh-inspector-close">×</button></div><label>Field Type<select id="bh-inspector-type"><option value="text">Text</option><option value="textarea">Textarea</option><option value="url">URL</option><option value="email">Email</option><option value="number">Number</option><option value="date">Date</option><option value="select">Select</option><option value="checkbox">Checkbox</option><option value="schedule">Schedule / Business Hours</option></select></label><label>Label<input id="bh-inspector-label" type="text"></label><label>Field Name<input id="bh-inspector-key" type="text" readonly></label><label class="bh-toggle-ui"><input id="bh-inspector-required" type="checkbox"><span><strong>Required</strong><small>Leader must complete this field.</small></span></label><label class="bh-toggle-ui"><input id="bh-inspector-search" type="checkbox"><span><strong>Show in Search</strong><small>Make this field available to the search/filter builder.</small></span></label><label>Help Text<textarea id="bh-inspector-help" rows="4" placeholder="Explain what should be entered here"></textarea></label><div class="bh-advanced-setting">Advanced Settings <span>›</span></div></div></aside></div>';
    }

    if($tab==='single'){
      echo '<div class="bh-builder-workspace"><aside class="bh-field-palette"><div class="bh-panel-title"><h2>Form fields</h2><span class="bh-help">Available content</span></div><div class="bh-palette-group">';
      $all_single=['description'=>'Description','gallery'=>'Gallery','video'=>'Video','schedule'=>'Schedule','venue'=>'Venue','map'=>'Map','age_range'=>'Age Range','price'=>'Price','session_length'=>'Session Length','sen_friendly'=>'SEN Friendly','email'=>'Email','website'=>'Website','social_links'=>'Social Links','booking'=>'Book This Class','contact'=>'Contact','other_classes'=>'Other Classes'];
      foreach($all_single as $k=>$v) echo '<div class="bh-palette-item" draggable="true" data-key="'.esc_attr($k).'" data-label="'.esc_attr($v).'" data-type="content"><span class="dashicons dashicons-menu"></span><span><strong>'.esc_html($v).'</strong><small>Listing content</small></span><button type="button" class="button-link">Add</button></div>';
      echo '</div><button type="button" class="button button-secondary" id="bh-add-section">+ Add Section</button></aside><main class="bh-builder-canvas"><div class="bh-canvas-head"><div><h2>Customize single listing</h2><p>Arrange sections and fields for the public listing page.</p></div><button type="button" class="button" id="bh-single-preview">Preview</button></div><div id="bh-single-sections" class="bh-sections">';
      foreach($single_sections as $section) echo self::builder_section_card($section);
      echo '</div></main></div>';
    }

    if($tab==='archive'){
      echo '<div class="bh-builder-workspace"><aside class="bh-field-palette"><div class="bh-panel-title"><h2>Card elements</h2><span class="bh-help">Drag or click Add</span></div><div class="bh-palette-group">';
      $cards=['featured_image'=>'Featured image','title'=>'Title','tagline'=>'Tagline','category'=>'Category','location'=>'Location','price'=>'Price','rating'=>'Rating','favourite'=>'Favourite','verified'=>'Verified badge','featured'=>'Featured badge','schedule'=>'Next session','age_range'=>'Age range','booking'=>'Book button'];
      foreach($cards as $k=>$v) echo '<div class="bh-palette-item" draggable="true" data-key="'.esc_attr($k).'" data-label="'.esc_attr($v).'" data-type="card"><span class="dashicons dashicons-screenoptions"></span><span><strong>'.esc_html($v).'</strong><small>Listing card element</small></span><button type="button" class="button-link">Add</button></div>';
      echo '</div></aside><main class="bh-builder-canvas"><div class="bh-canvas-head"><div><h2>Customize all listing layout</h2><p>Choose what appears on grid and list cards and arrange the order.</p></div><button type="button" class="button" id="bh-archive-preview">Preview</button></div><div id="bh-archive-fields" class="bh-active-list">';
      foreach($archive_fields as $i=>$key) echo self::builder_field_card($i,$key,ucwords(str_replace('_',' ',$key)),'card',false);
      echo '</div></main></div>';
    }

    if($tab==='search'){
      echo '<div class="bh-builder-workspace"><aside class="bh-field-palette"><div class="bh-panel-title"><h2>Search fields</h2><span class="bh-help">Use listing fields</span></div><div class="bh-palette-group">';
      $search_items=['keyword'=>'Keyword','category'=>'Category','location'=>'Location','age_range'=>'Age Range','price'=>'Price','sen_friendly'=>'SEN Friendly','schedule'=>'Schedule / Day','venue'=>'Venue','distance'=>'Distance / Radius'];
      foreach($search_items as $k=>$v) echo '<div class="bh-palette-item" draggable="true" data-key="'.esc_attr($k).'" data-label="'.esc_attr($v).'" data-type="search"><span class="dashicons dashicons-search"></span><span><strong>'.esc_html($v).'</strong><small>Search / filter field</small></span><button type="button" class="button-link">Add</button></div>';
      echo '</div></aside><main class="bh-builder-canvas"><div class="bh-canvas-head"><div><h2>Customize search form</h2><p>Build the basic search fields and advanced filters.</p></div><button type="button" class="button" id="bh-search-preview">Preview</button></div><div class="bh-search-columns"><section><h3>Basic</h3><div id="bh-search-basic" class="bh-active-list">';
      foreach($search_basic as $i=>$key) echo self::builder_field_card($i,$key,ucwords(str_replace('_',' ',$key)),'search',false);
      echo '</div></section><section><h3>Advanced filters</h3><div id="bh-search-advanced" class="bh-active-list">';
      foreach($search_advanced as $i=>$key) echo self::builder_field_card($i,$key,ucwords(str_replace('_',' ',$key)),'search',false);
      echo '</div></section></div></main></div>';
    }

    echo '<input type="hidden" name="builder_data" id="bh-builder-data" value="">';
    echo '</form>';
    echo '<style>
      .bh-builder-page{max-width:none;margin-right:20px}.bh-builder-top{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;margin:24px 0 18px}.bh-builder-eyebrow{font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#646970;font-weight:600}.bh-builder-top h1{font-size:26px;margin:5px 0}.bh-builder-top p{margin:0;color:#646970}.bh-builder-actions{display:flex;gap:8px;align-items:center}.bh-title-edit{border:0;background:transparent;color:#646970;cursor:pointer}.bh-directory-switch{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:12px 16px;margin-bottom:12px}.bh-directory-switch label{display:flex;align-items:center;gap:12px}.bh-directory-switch select{min-width:240px}.bh-builder-tabs{display:flex;background:#fff;border:1px solid #dcdcde;border-bottom:0;border-radius:8px 8px 0 0;overflow:auto}.bh-builder-tabs a{display:flex;align-items:center;gap:7px;padding:15px 18px;text-decoration:none;color:#50575e;border-right:1px solid #eee;font-weight:500;white-space:nowrap}.bh-builder-tabs a.active{color:#2271b1;border-bottom:3px solid #2271b1;background:#f8fbff}.bh-builder-workspace{display:grid;grid-template-columns:310px minmax(0,1fr);min-height:620px;background:#fff;border:1px solid #dcdcde;border-radius:0 0 8px 8px}.bh-field-palette{padding:18px;border-right:1px solid #e2e4e7;background:#fafafa}.bh-panel-title{display:flex;justify-content:space-between;align-items:center}.bh-panel-title h2{font-size:17px;margin:0}.bh-help{font-size:11px;color:#8c8f94}.bh-palette-group{margin-top:18px}.bh-palette-group h3{font-size:12px;text-transform:uppercase;color:#646970;margin:0 0 8px}.bh-palette-item{display:flex;align-items:center;gap:9px;padding:10px;border:1px solid #dcdcde;border-radius:6px;background:#fff;margin-bottom:7px;cursor:grab}.bh-palette-item:hover{border-color:#8c8f94}.bh-palette-item>span:nth-child(2){flex:1}.bh-palette-item strong,.bh-palette-item small{display:block}.bh-palette-item small{color:#8c8f94;margin-top:2px}.bh-palette-item .dashicons{color:#2271b1}.bh-palette-item .button-link{font-size:12px}.bh-builder-canvas{padding:20px;min-width:0}.bh-canvas-head{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:1px solid #eee;padding-bottom:15px;margin-bottom:15px}.bh-canvas-head h2{margin:0 0 5px;font-size:18px}.bh-canvas-head p{margin:0;color:#646970}.bh-active-list,.bh-sections{min-height:160px;padding:5px;border:2px dashed #dcdcde;border-radius:8px;background:#fafafa}.bh-field-card,.bh-section-card{display:flex;align-items:center;gap:10px;background:#fff;border:1px solid #dcdcde;border-radius:7px;padding:12px;margin:7px 0;box-shadow:0 1px 2px rgba(0,0,0,.03);cursor:grab}.bh-field-card .bh-grip,.bh-section-card .bh-grip{color:#8c8f94;font-size:18px}.bh-field-card .bh-card-main,.bh-section-card .bh-card-main{flex:1}.bh-field-card small,.bh-section-card small{display:block;color:#8c8f94;margin-top:3px}.bh-card-actions{display:flex;align-items:center;gap:7px}.bh-card-actions button{border:0;background:transparent;color:#646970;cursor:pointer}.bh-drop-empty{text-align:center;color:#8c8f94;padding:28px}.bh-toggle-row{display:flex;gap:12px;align-items:flex-start;padding:15px 0;border-bottom:1px solid #eee}.bh-toggle-row input{margin-top:3px}.bh-toggle-row small{display:block;color:#646970;margin-top:3px}.bh-settings-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;background:#f6f7f7;padding:18px}.bh-card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:20px}.bh-card h2{margin-top:0}.bh-form-row{display:grid;grid-template-columns:1fr 1fr;gap:15px}.bh-form-row label{font-weight:600}.bh-form-row input,.bh-form-row select{display:block;width:100%;margin-top:6px}.bh-search-columns{display:grid;grid-template-columns:1fr 1fr;gap:18px}.bh-search-columns section{background:#f6f7f7;padding:15px;border-radius:8px}.bh-search-columns h3{margin-top:0}.bh-builder-page .notice{margin:12px 0}.bh-modal{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;z-index:100000}.bh-modal.open{display:flex}.bh-modal-inner{background:#fff;width:min(560px,92vw);border-radius:10px;padding:20px;box-shadow:0 10px 35px rgba(0,0,0,.25)}.bh-modal-inner h2{margin-top:0}.bh-modal-inner label{display:block;margin:12px 0}.bh-modal-inner input,.bh-modal-inner select,.bh-modal-inner textarea{width:100%;margin-top:5px}.bh-section-fields{margin:10px 0 0 28px;padding:8px;border-left:2px solid #e2e4e7}.bh-section-fields .bh-field-card{margin:5px 0}.bh-mini-field{display:flex;align-items:center;gap:7px;padding:7px 8px;background:#f8f9fa;border:1px solid #e2e4e7;border-radius:5px;margin:4px 0}.bh-mini-field .bh-grip{font-size:12px}.bh-mini-field strong{flex:1;font-size:12px}.bh-mini-field button{border:0;background:transparent;color:#b32d2e;cursor:pointer}@media(max-width:900px){.bh-builder-workspace,.bh-settings-grid,.bh-search-columns{grid-template-columns:1fr}.bh-field-palette{border-right:0;border-bottom:1px solid #e2e4e7}.bh-builder-top{flex-direction:column}.bh-builder-actions{width:100%}}
      .bh-builder-page{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.bh-builder-page .bh-builder-top{position:relative;padding-top:10px}.bh-builder-page .bh-builder-top:before{content:"";position:absolute;left:0;right:0;top:0;height:5px;background:linear-gradient(90deg,#49b6c8,#f29ab0,#f5cf76,#7ccfb1)}.bh-builder-top h1{font-size:28px;color:#183b63}.bh-builder-eyebrow{color:#238ca3}.bh-directory-switch{border:0;background:transparent;padding:0;margin:0 0 14px}.bh-directory-switch select{border-radius:8px;min-width:260px}.bh-builder-tabs{box-shadow:0 1px 2px rgba(24,59,99,.04)}.bh-builder-tabs a.active{color:#2271b1;border-bottom-color:#43a9bd;background:#f7fbfd}.bh-builder-add-workspace{grid-template-columns:300px minmax(420px,1fr) 290px;gap:0;border:1px solid #dfe5ea;border-radius:0 0 12px 12px;background:#fff;box-shadow:0 8px 24px rgba(25,56,87,.06)}.bh-field-palette{background:#fbfcfd;border-right:1px solid #e5e9ee;padding:20px}.bh-palette-item{border-radius:9px;border-color:#e0e6ec;padding:11px;background:#fff}.bh-palette-item:hover{border-color:#75bccc;box-shadow:0 2px 8px rgba(34,113,177,.08)}.bh-palette-item .dashicons{color:#238ca3}.bh-add-custom{width:100%;margin-top:8px;border:1px dashed #9ecbd5;background:#f7fcfd;color:#18758a;border-radius:8px;padding:8px}.bh-builder-canvas{padding:22px;background:#fff}.bh-canvas-head{align-items:center}.bh-canvas-actions{display:flex;gap:7px}.bh-active-list{border:2px dashed #d9e0e7;background:#fbfcfd;border-radius:10px;padding:8px}.bh-field-card{border-color:#dfe5ea;border-radius:9px;padding:13px;min-height:34px}.bh-field-card:hover{border-color:#9cc9d4}.bh-field-card.is-selected{border-color:#4ba9bc;box-shadow:0 0 0 2px rgba(75,169,188,.12)}.bh-field-icon{color:#4ba9bc}.bh-field-card .bh-card-main strong{color:#193d62}.bh-inline-switch{display:inline-flex;align-items:center}.bh-inline-switch input{position:absolute;opacity:0;pointer-events:none}.bh-inline-switch span{width:34px;height:19px;background:#aebac6;border-radius:20px;position:relative;display:block}.bh-inline-switch span:after{content:"";width:15px;height:15px;background:#fff;border-radius:50%;position:absolute;top:2px;left:2px;box-shadow:0 1px 2px rgba(0,0,0,.18);transition:.15s}.bh-inline-switch input:checked+span{background:#36bca1}.bh-inline-switch input:checked+span:after{left:17px}.bh-field-inspector{background:#fbfcfd;border-left:1px solid #e5e9ee;padding:20px}.bh-inspector-empty{padding:60px 18px;text-align:center;color:#64748b}.bh-inspector-empty .dashicons{font-size:30px;width:30px;height:30px;color:#4ba9bc;margin-bottom:12px}.bh-inspector-empty strong{display:block;color:#193d62;font-size:15px}.bh-inspector-empty p{font-size:12px;line-height:1.5}.bh-inspector-form label{display:block;font-weight:600;color:#334e68;margin:0 0 15px}.bh-inspector-form input[type=text],.bh-inspector-form select,.bh-inspector-form textarea{width:100%;margin-top:6px;border-radius:7px;border-color:#d5dde5;box-sizing:border-box}.bh-inspector-title{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px}.bh-inspector-title h3{margin:0;color:#193d62}.bh-toggle-ui{display:flex!important;gap:10px;align-items:flex-start;font-weight:400!important;padding:12px 0;border-top:1px solid #e8edf1}.bh-toggle-ui input{margin-top:2px}.bh-toggle-ui strong{display:block;color:#334e68}.bh-toggle-ui small{display:block;color:#75879a;font-weight:400;margin-top:2px}.bh-advanced-setting{border-top:1px solid #e5e9ee;padding:15px 0;color:#334e68;font-weight:600}.bh-advanced-setting span{float:right;font-size:18px}@media(max-width:1100px){.bh-builder-add-workspace{grid-template-columns:260px minmax(0,1fr)}.bh-field-inspector{grid-column:1/-1;border-left:0;border-top:1px solid #e5e9ee}}@media(max-width:800px){.bh-builder-add-workspace{grid-template-columns:1fr}.bh-field-inspector{grid-column:auto}.bh-canvas-actions{flex-wrap:wrap}}
    </style>';

    echo '<script>
    (function(){
      const form=document.getElementById("bh-directory-builder-form"), data=document.getElementById("bh-builder-data"), tab="'.esc_js($tab).'";
      let selectedCard=null, drag=null; const customFields=[];
      const inspector=document.getElementById("bh-field-inspector"), inspectorForm=inspector?.querySelector(".bh-inspector-form"), empty=inspector?.querySelector(".bh-inspector-empty");
      const iType=document.getElementById("bh-inspector-type"), iLabel=document.getElementById("bh-inspector-label"), iKey=document.getElementById("bh-inspector-key"), iRequired=document.getElementById("bh-inspector-required"), iSearch=document.getElementById("bh-inspector-search"), iHelp=document.getElementById("bh-inspector-help");
      const esc=s=>String(s??"").replace(/[&<>"]/g,m=>m==="&"?"&amp;":m==="<"?"&lt;":m===">"?"&gt;":"&quot;");
      function selectCard(el){selectedCard=el;document.querySelectorAll(".bh-field-card.is-selected").forEach(x=>x.classList.remove("is-selected"));el.classList.add("is-selected");if(inspectorForm){inspectorForm.hidden=false;empty.hidden=true;}iType.value=el.dataset.type||"text";iLabel.value=el.dataset.label||el.querySelector("strong")?.textContent||"";iKey.value=el.dataset.key||"";iRequired.checked=el.dataset.required==="1";iSearch.checked=el.dataset.search!=="0";iHelp.value=el.dataset.help||"";}
      function updateCard(){if(!selectedCard)return;const label=iLabel.value.trim()||selectedCard.dataset.key,type=iType.value,required=iRequired.checked;selectedCard.dataset.label=label;selectedCard.dataset.type=type;selectedCard.dataset.required=required?"1":"0";selectedCard.dataset.search=iSearch.checked?"1":"0";selectedCard.dataset.help=iHelp.value||"";selectedCard.querySelector("strong").textContent=label;selectedCard.querySelector("small").textContent=type+(required?" • Required":"");const t=selectedCard.querySelector(".bh-required-toggle");if(t)t.checked=required;}
      [iType,iLabel,iRequired,iSearch,iHelp].forEach(el=>el&&el.addEventListener("input",updateCard));[iType,iRequired,iSearch].forEach(el=>el&&el.addEventListener("change",updateCard));
      document.getElementById("bh-inspector-close")?.addEventListener("click",()=>{if(inspectorForm){inspectorForm.hidden=true;empty.hidden=false;}selectedCard?.classList.remove("is-selected");selectedCard=null;});
      function bind(el){el.addEventListener("click",e=>{if(!e.target.closest("button")&&!e.target.closest("input"))selectCard(el)});el.addEventListener("dragstart",()=>{drag=el;el.classList.add("bh-dragging")});el.addEventListener("dragend",()=>{drag=null;el.classList.remove("bh-dragging")});el.addEventListener("dragover",e=>{e.preventDefault();if(drag&&drag!==el){const r=el.getBoundingClientRect();el.parentNode.insertBefore(drag,e.clientY<r.top+r.height/2?el:el.nextSibling)}});el.querySelector("[data-remove]")?.addEventListener("click",e=>{e.stopPropagation();if(selectedCard===el){selectedCard=null;if(inspectorForm){inspectorForm.hidden=true;empty.hidden=false;}}el.remove()});el.querySelector(".bh-required-toggle")?.addEventListener("change",e=>{selectCard(el);iRequired.checked=e.target.checked;updateCard()});el.querySelector("[data-config]")?.addEventListener("click",e=>{e.stopPropagation();selectCard(el)});}
      document.querySelectorAll("#bh-active-fields .bh-field-card,#bh-archive-fields .bh-field-card,#bh-search-basic .bh-field-card,#bh-search-advanced .bh-field-card").forEach(bind);
      function card(key,label,type,required=false){const el=document.createElement("div");el.className="bh-field-card";el.draggable=true;el.dataset.key=key;el.dataset.label=label;el.dataset.type=type||"text";el.dataset.required=required?"1":"0";el.dataset.search="1";el.dataset.help="";el.innerHTML=`<span class="bh-grip">⋮⋮</span><span class="bh-field-icon dashicons dashicons-admin-generic"></span><div class="bh-card-main"><strong>${esc(label)}</strong><small>${esc(type)}${required?" • Required":""}</small></div><div class="bh-card-actions"><label class="bh-inline-switch"><input type="checkbox" class="bh-required-toggle" ${required?"checked":""}><span></span></label><button type="button" data-config title="Configure">⚙</button><button type="button" data-remove title="Remove">×</button></div>`;bind(el);return el;}
      function addFromPalette(p,targetId){const target=document.getElementById(targetId);if(!target)return;const key=p.dataset.key;if([...target.querySelectorAll("[data-key]")].some(x=>x.dataset.key===key))return;const el=card(key,p.dataset.label,p.dataset.type||"text",false);target.appendChild(el);selectCard(el);}
      document.querySelectorAll(".bh-palette-item").forEach(p=>{const add=()=>{if(tab==="add")addFromPalette(p,"bh-active-fields");else if(tab==="search")addFromPalette(p,"bh-search-basic");else if(tab==="archive")addFromPalette(p,"bh-archive-fields")};p.querySelector("button")?.addEventListener("click",add);p.addEventListener("dragend",add)});
      document.getElementById("bh-new-custom-field")?.addEventListener("click",()=>{const label=prompt("Custom field name","New field");if(!label)return;const key=(prompt("Field key (letters, numbers and underscores)",label.toLowerCase().replace(/[^a-z0-9]+/g,"_"))||"").replace(/[^a-z0-9_]/g,"_");if(!key)return;const raw=(prompt("Type: text, textarea, url, email, number, date, select, checkbox, schedule","text")||"text").toLowerCase(),type=["text","textarea","url","email","number","date","select","checkbox","schedule"].includes(raw)?raw:"text";customFields.push({label,key,type,help:"",options:"",required:false});const group=document.querySelector(".bh-palette-group:nth-of-type(2)");if(group){const el=document.createElement("div");el.className="bh-palette-item";el.dataset.key=key;el.dataset.label=label;el.dataset.type=type;el.innerHTML=`<span class="dashicons dashicons-admin-generic"></span><span><strong>${esc(label)}</strong><small>${esc(type)}</small></span><button type="button" class="button-link">Add</button>`;el.querySelector("button").onclick=()=>addFromPalette(el,"bh-active-fields");group.appendChild(el)}addFromPalette({dataset:{key,label,type}},"bh-active-fields")});
      document.getElementById("bh-reset-add-form")?.addEventListener("click",()=>{if(confirm("Reload the default fields? Any unsaved changes will be lost."))location.reload()});
      const addSection=document.getElementById("bh-add-section");if(addSection){addSection.onclick=()=>{const wrap=document.getElementById("bh-single-sections");if(!wrap)return;const id="section_"+Date.now(),el=document.createElement("div");el.className="bh-section-card";el.draggable=true;el.dataset.id=id;el.innerHTML=`<span class="bh-grip">☷</span><div class="bh-card-main"><strong>New Section</strong><small>Custom content section</small><div class="bh-section-fields"></div></div><div class="bh-card-actions"><button type="button" data-rename>✎</button><button type="button" data-remove>×</button></div>`;wrap.appendChild(el);bindSection(el)}}
      function bindSection(el){el.querySelector("[data-remove]")?.addEventListener("click",()=>el.remove());el.querySelector("[data-rename]")?.addEventListener("click",()=>{const n=prompt("Section name",el.querySelector("strong").textContent);if(n)el.querySelector("strong").textContent=n});el.addEventListener("dragstart",()=>drag=el);el.addEventListener("dragend",()=>drag=null);el.addEventListener("dragover",e=>{e.preventDefault();if(drag&&drag!==el){const r=el.getBoundingClientRect();el.parentNode.insertBefore(drag,e.clientY<r.top+r.height/2?el:el.nextSibling)}});el.querySelectorAll("[data-remove-field]").forEach(b=>b.addEventListener("click",()=>b.closest(".bh-mini-field").remove()))}document.querySelectorAll(".bh-section-card").forEach(bindSection);
      if(form)form.addEventListener("submit",()=>{const payload={};if(tab==="add"){payload.add=[...document.querySelectorAll("#bh-active-fields .bh-field-card")].map(x=>({key:x.dataset.key,label:x.dataset.label||x.querySelector("strong")?.textContent||"",type:x.dataset.type||"text",required:x.dataset.required==="1",show_search:x.dataset.search!=="0",help:x.dataset.help||""}));payload.custom_fields=customFields;}if(tab==="single")payload.single=[...document.querySelectorAll("#bh-single-sections .bh-section-card")].map(x=>({id:x.dataset.id,label:x.querySelector("strong")?.textContent||"Section",icon:"dashicons-menu",fields:[...x.querySelectorAll(".bh-mini-field")].map(f=>f.dataset.key)}));if(tab==="archive")payload.archive=[...document.querySelectorAll("#bh-archive-fields .bh-field-card")].map(x=>x.dataset.key);if(tab==="search"){payload.search_basic=[...document.querySelectorAll("#bh-search-basic .bh-field-card")].map(x=>x.dataset.key);payload.search_advanced=[...document.querySelectorAll("#bh-search-advanced .bh-field-card")].map(x=>x.dataset.key)}if(data)data.value=JSON.stringify(payload)});
    })();
    </script>';
    echo '</div>';
  }

  private static function builder_field_card($i,$key,$label,$type='text',$required=false){
    return '<div class="bh-field-card" draggable="true" data-key="'.esc_attr($key).'" data-label="'.esc_attr($label).'" data-type="'.esc_attr($type).'" data-required="'.($required?'1':'0').'" data-search="1" data-help=""><span class="bh-grip">⋮⋮</span><span class="bh-field-icon dashicons dashicons-admin-generic"></span><div class="bh-card-main"><strong>'.esc_html($label).'</strong><small>'.esc_html($type).($required?' • Required':'').'</small></div><div class="bh-card-actions"><label class="bh-inline-switch"><input type="checkbox" class="bh-required-toggle" '.checked($required,true,false).'><span></span></label><button type="button" data-config title="Configure">⚙</button><button type="button" data-remove title="Remove">×</button></div></div>';
  }

  private static function builder_section_card($section){
    $section=wp_parse_args($section,['id'=>'section_'.wp_rand(),'label'=>'Section','icon'=>'dashicons-menu','fields'=>[]]);
    $html='<div class="bh-section-card" draggable="true" data-id="'.esc_attr($section['id']).'"><span class="bh-grip">☷</span><div class="bh-card-main"><strong>'.esc_html($section['label']).'</strong><small>Listing content section</small><div class="bh-section-fields">';
    foreach((array)$section['fields'] as $key){
      $html.='<div class="bh-mini-field" draggable="true" data-key="'.esc_attr($key).'"><span class="bh-grip">⋮⋮</span><strong>'.esc_html(ucwords(str_replace('_',' ',$key))).'</strong><button type="button" data-remove-field>×</button></div>';
    }
    $html.='</div></div><div class="bh-card-actions"><button type="button" data-rename>✎</button><button type="button" data-remove>×</button></div></div>';
    return $html;
  }

  public static function save_directory_fields(){
    if(!current_user_can('manage_bubbahub')||!check_admin_referer('bubbahub_directory_builder_save','bubbahub_directory_builder_nonce')) wp_die('You do not have permission to update BubbaHub directory fields.');
    $post_type=sanitize_key($_POST['post_type']??'');
    $has_fields=array_key_exists('fields',$_POST);
    $raw=$has_fields&&is_array($_POST['fields'])?$_POST['fields']:[];
    $out=[];
    foreach($raw as $f){
      $label=sanitize_text_field($f['label']??'');
      $key=sanitize_key($f['key']??'');
      $type=sanitize_key($f['type']??'text');
      if(!$label||!$key) continue;
      if(!in_array($type,['text','textarea','url','email','number','date','select','checkbox','schedule'],true)) $type='text';
      $out[]=['label'=>$label,'key'=>$key,'type'=>$type,'help'=>sanitize_text_field($f['help']??''),'options'=>sanitize_textarea_field($f['options']??''),'required'=>!empty($f['required'])?1:0];
    }
    $all=get_option('bubbahub_directory_fields',[]);
    if($has_fields && $post_type) $all[$post_type]=array_values($out);
    update_option('bubbahub_directory_fields',$all);

    $layouts=get_option('bubbahub_directory_layouts',[]);
    $current=isset($layouts[$post_type])&&is_array($layouts[$post_type])?$layouts[$post_type]:[];
    $tab=sanitize_key($_POST['builder_tab']??'add');
    $payload=json_decode(wp_unslash($_POST['builder_data']??''),true);
    if(is_array($payload)){
      if(isset($payload['custom_fields']) && is_array($payload['custom_fields'])){
        $custom_out=$all[$post_type]??[];
        foreach($payload['custom_fields'] as $cf){
          $ck=sanitize_key($cf['key']??''); $cl=sanitize_text_field($cf['label']??''); $ct=sanitize_key($cf['type']??'text');
          if(!$ck||!$cl||!in_array($ct,['text','textarea','url','email','number','date','select','checkbox','schedule'],true)) continue;
          $existing_keys=array_column($custom_out,'key'); if(!in_array($ck,$existing_keys,true)) $custom_out[]=['label'=>$cl,'key'=>$ck,'type'=>$ct,'help'=>sanitize_text_field($cf['help']??''),'options'=>sanitize_textarea_field($cf['options']??''),'required'=>!empty($cf['required'])?1:0];
        }
        $all[$post_type]=array_values($custom_out); update_option('bubbahub_directory_fields',$all);
      }
      if(isset($payload['add']) && is_array($payload['add'])){
        $safe_add=[];
        foreach($payload['add'] as $item){
          $key=sanitize_key($item['key']??''); $label=sanitize_text_field($item['label']??''); $type=sanitize_key($item['type']??'text');
          if(!$key||!$label||!in_array($type,['text','textarea','url','email','number','date','select','checkbox','schedule'],true)) continue;
          $safe_add[]=['key'=>$key,'label'=>$label,'type'=>$type,'required'=>!empty($item['required'])?1:0,'show_search'=>!empty($item['show_search'])?1:0,'help'=>sanitize_text_field($item['help']??'')];
        }
        $current['add']=$safe_add;
      }
      foreach(['single','archive','search_basic','search_advanced'] as $k){
        if(array_key_exists($k,$payload) && is_array($payload[$k])) $current[$k]=$payload[$k];
      }
    }
    if(isset($_POST['general'])&&is_array($_POST['general'])){
      $g=$_POST['general'];
      $current['general']=[
        'plural'=>sanitize_text_field($g['plural']??''),
        'singular'=>sanitize_text_field($g['singular']??''),
        'slug'=>sanitize_title($g['slug']??$post_type),
        'view'=>in_array(($g['view']??'grid'),['grid','list','map'],true)?$g['view']:'grid',
        'enabled'=>!empty($g['enabled'])?1:0,
        'leader_submissions'=>!empty($g['leader_submissions'])?1:0,
        'reviews'=>!empty($g['reviews'])?1:0,
      ];
    }
    $layouts[$post_type]=$current;
    update_option('bubbahub_directory_layouts',$layouts);

    wp_safe_redirect(add_query_arg(['page'=>'bubbahub-directory-builder','post_type'=>$post_type,'tab'=>$tab,'updated'=>1],admin_url('admin.php')));
    exit;
  }

  public static function register_directory_hooks(){
    add_action('add_meta_boxes',function(){foreach(get_post_types(['public'=>true],'names') as $type){if($type==='attachment'||!self::get_directory_fields($type))continue;add_meta_box('bubbahub_custom_fields','BubbaHub Directory Fields',[__CLASS__,'render_directory_meta_box'],$type,'normal','high');}});
    add_action('save_post',[__CLASS__,'save_directory_meta'],10,2);
    add_action('admin_post_bubbahub_save_directory_fields',[__CLASS__,'save_directory_fields']);
    add_action('admin_post_bubbahub_create_pages',[__CLASS__,'create_pages']);
    add_action('admin_post_bubbahub_save_visual_builder',[__CLASS__,'save_visual_builder']);
  }
  public static function render_directory_meta_box($post){
    self::schedule_admin_assets();
    $fields=self::get_directory_fields($post->post_type);wp_nonce_field('bubbahub_directory_meta','bubbahub_directory_meta_nonce');
    if(!$fields){echo '<p>No custom fields configured. Use <a href="'.esc_url(admin_url('admin.php?page=bubbahub-directory-builder&post_type='.rawurlencode($post->post_type))).'">Directory Builder</a>.</p>';return;}
    echo '<div class="bubbahub-custom-fields">';
    foreach($fields as $f){$key=$f['key'];$value=get_post_meta($post->ID,'_bubbahub_'.$key,true);echo '<p><label><strong>'.esc_html($f['label']).'</strong>'.(!empty($f['required'])?' <span aria-hidden="true">*</span>':'').'</label><br>';
      switch($f['type']){
        case 'textarea':echo '<textarea class="widefat" rows="4" name="bubbahub_fields['.esc_attr($key).']">'.esc_textarea($value).'</textarea>';break;
        case 'url':echo '<input class="widefat" type="url" name="bubbahub_fields['.esc_attr($key).']" value="'.esc_attr($value).'">';break;
        case 'email':echo '<input class="widefat" type="email" name="bubbahub_fields['.esc_attr($key).']" value="'.esc_attr($value).'">';break;
        case 'number':echo '<input class="widefat" type="number" name="bubbahub_fields['.esc_attr($key).']" value="'.esc_attr($value).'">';break;
        case 'date':echo '<input class="widefat" type="date" name="bubbahub_fields['.esc_attr($key).']" value="'.esc_attr($value).'">';break;
        case 'select':echo '<select class="widefat" name="bubbahub_fields['.esc_attr($key).']"><option value="">Select…</option>';foreach(preg_split('/\R/',(string)($f['options']??'')) as $o){$o=trim($o);if($o!=='')echo '<option value="'.esc_attr($o).'" '.selected($value,$o,false).'>'.esc_html($o).'</option>';}echo '</select>';break;
        case 'checkbox':echo '<label><input type="checkbox" name="bubbahub_fields['.esc_attr($key).']" value="1" '.checked($value,'1',false).'> Yes</label>';break;
        case 'schedule':
          $rows=is_array($value)?$value:[];
          echo '<div class="bh-admin-schedule" data-schedule-key="'.esc_attr($key).'">';
          if(!$rows) $rows=[['day'=>'Monday','start'=>'','end'=>'','session'=>'','price'=>'','capacity'=>'','repeat_until'=>'']];
          foreach($rows as $ri=>$row){
            echo '<div class="bh-schedule-row"><select name="bubbahub_fields['.esc_attr($key).']['.$ri.'][day]">';
            foreach(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $day) echo '<option value="'.esc_attr($day).'" '.selected($row['day']??'',$day,false).'>'.esc_html($day).'</option>';
            echo '</select><input type="time" name="bubbahub_fields['.esc_attr($key).']['.$ri.'][start]" value="'.esc_attr($row['start']??'').'"><input type="time" name="bubbahub_fields['.esc_attr($key).']['.$ri.'][end]" value="'.esc_attr($row['end']??'').'" placeholder="End"><input type="text" name="bubbahub_fields['.esc_attr($key).']['.$ri.'][session]" value="'.esc_attr($row['session']??'').'" placeholder="Session"><input type="text" name="bubbahub_fields['.esc_attr($key).']['.$ri.'][price]" value="'.esc_attr($row['price']??'').'" placeholder="Price"><input type="number" min="0" name="bubbahub_fields['.esc_attr($key).']['.$ri.'][capacity]" value="'.esc_attr($row['capacity']??'').'" placeholder="Capacity"><input type="date" name="bubbahub_fields['.esc_attr($key).']['.$ri.'][repeat_until]" value="'.esc_attr($row['repeat_until']??'').'"><button type="button" class="button-link-delete bh-remove-session">Remove</button></div>';
          }
          echo '<button type="button" class="button bh-add-session">+ Add session</button></div>';
          break;
        default:echo '<input class="widefat" type="text" name="bubbahub_fields['.esc_attr($key).']" value="'.esc_attr($value).'">';
      }
      if(!empty($f['help']))echo '<span class="description">'.esc_html($f['help']).'</span>';echo '</p>';
    }
    echo '</div>';
  }
  public static function save_directory_meta($post_id,$post){
    if(!isset($_POST['bubbahub_directory_meta_nonce'])||!wp_verify_nonce($_POST['bubbahub_directory_meta_nonce'],'bubbahub_directory_meta'))return;if(defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE)return;if(wp_is_post_revision($post_id)||wp_is_post_autosave($post_id))return;if(!current_user_can('edit_post',$post_id))return;
    foreach(self::get_directory_fields($post->post_type) as $f){$key=$f['key'];$v=$_POST['bubbahub_fields'][$key]??'';switch($f['type']){case 'url':$v=esc_url_raw($v);break;case 'email':$v=sanitize_email($v);break;case 'textarea':$v=sanitize_textarea_field($v);break;case 'checkbox':$v=!empty($v)?'1':'0';break;
        case 'schedule':
          $rows=[]; if(is_array($v)){foreach($v as $row){if(!is_array($row))continue;$rows[]=['day'=>sanitize_text_field($row['day']??''),'start'=>sanitize_text_field($row['start']??''),'end'=>sanitize_text_field($row['end']??''),'session'=>sanitize_text_field($row['session']??''),'price'=>sanitize_text_field($row['price']??''),'capacity'=>absint($row['capacity']??0),'repeat_until'=>sanitize_text_field($row['repeat_until']??'')];}} $v=$rows; break;
        default:$v=sanitize_text_field($v);}
      if(($f['type']==='schedule'&&empty($v))||($f['type']!=='schedule'&&$v==='' )||($f['type']==='checkbox'&&$v==='0'))delete_post_meta($post_id,'_bubbahub_'.$key);else update_post_meta($post_id,'_bubbahub_'.$key,$v);}
  }
  public static function schedule_admin_assets(){
    echo '<style>.bh-admin-schedule{margin-top:8px}.bh-schedule-row{display:grid;grid-template-columns:110px 95px 95px 1fr 100px 90px 130px auto;gap:6px;align-items:center;margin-bottom:7px}.bh-schedule-row input,.bh-schedule-row select{min-width:0}.bh-add-session{margin-top:4px}@media(max-width:1000px){.bh-schedule-row{grid-template-columns:1fr 1fr 1fr;}.bh-schedule-row .bh-remove-session{grid-column:1/-1;text-align:left}}}</style><script>(function(){document.addEventListener("click",function(e){if(e.target.classList.contains("bh-add-session")){const box=e.target.closest(".bh-admin-schedule"),row=box.querySelector(".bh-schedule-row");if(!row)return;const clone=row.cloneNode(true),idx=box.querySelectorAll(".bh-schedule-row").length;clone.querySelectorAll("[name]").forEach(el=>{el.name=el.name.replace(/\\[\\d+\\](?=\\[)/,"["+idx+"]");if(el.type!=="select-one")el.value="";});box.insertBefore(clone,e.target);}});document.addEventListener("click",function(e){if(e.target.classList.contains("bh-remove-session")){const box=e.target.closest(".bh-admin-schedule");if(box.querySelectorAll(".bh-schedule-row").length>1)e.target.closest(".bh-schedule-row").remove();}});})();</script>';
  }

  public static function pages_page(){
    if(!current_user_can('manage_bubbahub')) return;
    $defaults=['home'=>'Home','directory'=>'Directory','events'=>'What’s On','apps'=>'Apps','support'=>'Support & Guidance','myhub'=>'My Hub','buddy'=>'Bubba Buddy','antenatal'=>'Antenatal Planner','leader'=>'Leader Portal','pricing'=>'Pricing Plans','join'=>'Join BubbaHub'];
    $pages=get_option('bubbahub_pages',[]);
    if(!empty($_GET['created'])) echo '<div class="notice notice-success is-dismissible"><p>BubbaHub pages created/updated.</p></div>';
    echo '<div class="wrap"><h1>BubbaHub Pages & Shortcodes</h1><p><strong>Internal navigation is disabled.</strong> Each BubbaHub area is now a normal WordPress page. You control the site menu, page order and layout from WordPress. Put the supplied shortcode on any page, or let BubbaHub create the relative pages for you.</p>';
    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('bubbahub_create_pages','bubbahub_create_pages_nonce');echo '<input type="hidden" name="action" value="bubbahub_create_pages"><p><button class="button button-primary" type="submit">Create / repair BubbaHub pages</button></p></form>';
    echo '<table class="widefat striped"><thead><tr><th>Area</th><th>WordPress page</th><th>Shortcode</th><th>URL</th><th>Actions</th></tr></thead><tbody>';
    foreach($defaults as $key=>$title){$cfg=$pages[$key]??[];$id=absint($cfg['id']??0);$p=$id?get_post($id):null;$url=$p?get_permalink($p->ID):'';$sc='[bubba_hub page="'.esc_attr($key).'"]';echo '<tr><td><strong>'.esc_html($title).'</strong><br><code>'.esc_html($key).'</code></td><td>'.($p?'<a href="'.esc_url(get_edit_post_link($p->ID)).'">'.esc_html($p->post_title).'</a>':'<em>Not created</em>').'</td><td><code>'.esc_html($sc).'</code></td><td>'.($url?'<a target="_blank" rel="noopener" href="'.esc_url($url).'">'.esc_html($url).'</a>':'—').'</td><td>'.($p?'<a class="button" href="'.esc_url(get_edit_post_link($p->ID)).'">Edit page</a>':'—').'</td></tr>';}
    echo '</tbody></table><h2>How to use custom navigation</h2><ol><li>Create or edit a normal WordPress page.</li><li>Add the shortcode for the BubbaHub area you want.</li><li>Use <strong>Appearance → Editor → Navigation</strong> (or your classic menu) to add that page wherever you want.</li><li>You can create multiple pages using the same area if you want different surrounding content/layouts.</li></ol><p><strong>Tip:</strong> The plugin no longer renders its own navigation bar, so your WordPress theme/site navigation is the single source of truth.</p></div>';
  }
  public static function create_pages(){
    if(!current_user_can('manage_bubbahub')||!check_admin_referer('bubbahub_create_pages','bubbahub_create_pages_nonce'))wp_die('You do not have permission to create BubbaHub pages.');
    $defaults=['home'=>'Home','directory'=>'Directory','events'=>'What’s On','apps'=>'Apps','support'=>'Support & Guidance','myhub'=>'My Hub','buddy'=>'Bubba Buddy','antenatal'=>'Antenatal Planner','leader'=>'Leader Portal','pricing'=>'Pricing Plans','join'=>'Join BubbaHub'];
    $pages=get_option('bubbahub_pages',[]);
    foreach($defaults as $key=>$title){$id=absint($pages[$key]['id']??0);$p=$id?get_post($id):null;$shortcode=$key==='pricing'?'[bubbahub_pricing]':($key==='join'?'[bubbahub_signup]':'[bubba_hub page="'.$key.'"]');if(!$p){$slug=$key==='home'?'bubba-hub':$key;$existing=get_page_by_path($slug);if($existing){$p=$existing;}else{$new=wp_insert_post(['post_title'=>$title,'post_name'=>$slug,'post_status'=>'publish','post_type'=>'page','post_content'=>$shortcode],true);if(!is_wp_error($new))$p=get_post($new);}} if($p){$pages[$key]=['id'=>$p->ID,'slug'=>$p->post_name];if(!has_shortcode($p->post_content,$key==='pricing'?'bubbahub_pricing':($key==='join'?'bubbahub_signup':'bubba_hub')))wp_update_post(['ID'=>$p->ID,'post_content'=>trim($p->post_content)."\n\n$shortcode"]);}}
    update_option('bubbahub_pages',$pages);flush_rewrite_rules();wp_safe_redirect(add_query_arg(['page'=>'bubbahub-pages','created'=>1],admin_url('admin.php')));exit;
  }

  private static function subscription_checkbox($name,$label,$checked,$help=''){
    $html='<input type="hidden" name="bubbahub_settings['.esc_attr($name).']" value="0"><label class="bh-toggle-row bh-sub-toggle"><input type="checkbox" name="bubbahub_settings['.esc_attr($name).']" value="1" '.checked($checked,true,false).'><span><strong>'.esc_html($label).'</strong><small>'.esc_html($help).'</small></span></label>';
    return $html;
  }
  private static function subscription_select($name,$label,$value,$options){
    $html='<div class="bh-setting bh-sub-select"><label>'.esc_html($label).'</label><select name="bubbahub_settings['.esc_attr($name).']">';
    foreach($options as $k=>$v)$html.='<option value="'.esc_attr($k).'" '.selected($value,$k,false).'>'.esc_html($v).'</option>';
    return $html.'</select></div>';
  }
  private static function subscription_plan_editor($id,$plan,$feature_keys){
    $plan=(array)$plan;$enabled=!isset($plan['enabled'])||!empty($plan['enabled']);$features=(array)($plan['features']??[]);
    $out='<article class="bh-plan-editor" data-plan="'.esc_attr($id).'">';
    $out.='<div class="bh-plan-editor-head"><div><span class="bh-plan-type">'.esc_html(ucfirst($plan['user_type']??'' )).'</span><h3>'.esc_html($plan['name']??$id).'</h3></div><label class="bh-switch"><input type="hidden" name="bubbahub_settings[plans]['.esc_attr($id).'][enabled]" value="0"><input type="checkbox" name="bubbahub_settings[plans]['.esc_attr($id).'][enabled]" value="1" '.checked($enabled,true,false).'><span></span> Active</label></div>';
    $out.='<div class="bh-plan-fields">';
    $out.='<label>Plan name<input type="text" name="bubbahub_settings[plans]['.esc_attr($id).'][name]" value="'.esc_attr($plan['name']??'').'" /></label>';
    $out.='<label>Short description<textarea name="bubbahub_settings[plans]['.esc_attr($id).'][description]" rows="3">'.esc_textarea($plan['description']??'').'</textarea></label>';
    $out.='<div class="bh-inline-fields"><label>Price<input type="number" step="0.01" min="0" name="bubbahub_settings[plans]['.esc_attr($id).'][price]" value="'.esc_attr($plan['price']??0).'" /></label><label>Currency<input type="text" maxlength="3" name="bubbahub_settings[plans]['.esc_attr($id).'][currency]" value="'.esc_attr($plan['currency']??'GBP').'" /></label><label>Billing<select name="bubbahub_settings[plans]['.esc_attr($id).'][billing]">';
    foreach(['one_time'=>'One-off','weekly'=>'Weekly','monthly'=>'Monthly','quarterly'=>'Every 3 months','yearly'=>'Yearly'] as $k=>$v)$out.='<option value="'.esc_attr($k).'" '.selected($plan['billing']??'yearly',$k,false).'>'.esc_html($v).'</option>';
    $out.='</select></label></div>';
    $out.='<div class="bh-inline-fields"><label>Stripe Price ID<input type="text" name="bubbahub_settings[plans]['.esc_attr($id).'][stripe_price_id]" value="'.esc_attr($plan['stripe_price_id']??'').'" placeholder="price_..." /></label><label>Signup CTA<input type="text" name="bubbahub_settings[plans]['.esc_attr($id).'][cta]" value="'.esc_attr($plan['cta']??'Choose plan').'" /></label></div>';
    $out.='<h4>Features</h4><div class="bh-feature-checks">';
    foreach($feature_keys as $fk=>$fl){$out.='<label><input type="hidden" name="bubbahub_settings[plans]['.esc_attr($id).'][features]['.esc_attr($fk).']" value="0"><input type="checkbox" name="bubbahub_settings[plans]['.esc_attr($id).'][features]['.esc_attr($fk).']" value="1" '.checked(!empty($features[$fk]),true,false).'> '.esc_html($fl).'</label>';}
    $out.='</div></div></article>';return $out;
  }
  public static function settings(){register_setting('bubbahub','bubbahub_settings',['type'=>'array','sanitize_callback'=>[__CLASS__,'sanitize_settings']]);}
  public static function sanitize_settings($v){
    $submitted=is_array($v)?$v:[];
    $existing=get_option('bubbahub_settings',[]);
    $v=is_array($existing)?array_replace_recursive($existing,$submitted):$submitted;
    $v['plans']=is_array($v['plans']??null)?$v['plans']:[];
    foreach(BubbaHub::subscription_plans() as $id=>$default){$p=is_array($v['plans'][$id]??null)?$v['plans'][$id]:[];$v['plans'][$id]=['enabled'=>!empty($p['enabled']),'name'=>sanitize_text_field($p['name']??$default['name']),'description'=>sanitize_textarea_field($p['description']??$default['description']),'price'=>max(0,(float)($p['price']??$default['price'])),'currency'=>strtoupper(substr(sanitize_text_field($p['currency']??'GBP'),0,3)),'billing'=>in_array(($p['billing']??$default['billing']),['one_time','weekly','monthly','quarterly','yearly'],true)?$p['billing']:$default['billing'],'stripe_price_id'=>sanitize_text_field($p['stripe_price_id']??''),'cta'=>sanitize_text_field($p['cta']??$default['cta']),'features'=>array_map('boolval',(array)($p['features']??[]))];}
    foreach(['parent_enabled','leader_enabled','leader_approval'] as $k)$v['signup'][$k]=!empty($v['signup'][$k]);
    $v['signup']['parent_default_plan']=sanitize_key($v['signup']['parent_default_plan']??'parent-free');$v['signup']['leader_default_plan']=sanitize_key($v['signup']['leader_default_plan']??'leader-basic');
    foreach(['stripe','paypal','bank','free'] as $k)$v['payment_methods'][$k]=!empty($v['payment_methods'][$k]);
    return $v;
  }

  public static function settings_page(){
    if(!current_user_can('manage_bubbahub')) return;
    $s=get_option('bubbahub_settings',[]);
    $section=sanitize_key($_GET['section']??'directory');
    $sections=['directory'=>'Directory','search'=>'Search','users'=>'Users & Accounts','subscriptions'=>'Subscriptions & Pricing','monetization'=>'Payments','notifications'=>'Notifications','appearance'=>'Appearance','site'=>'Site & Pages','extensions'=>'Extensions','maintenance'=>'Maintenance'];
    if(!isset($sections[$section]))$section='directory';
    $cb=esc_url(rest_url('bubbahub/v1/auth/google/callback'));$fb=esc_url(rest_url('bubbahub/v1/auth/facebook/callback'));
    echo '<div class="wrap bh-settings-page"><div class="bh-settings-head"><div><div class="bh-builder-eyebrow">BubbaHub</div><h1>Settings</h1><p>Configure the directory, accounts, payments, notifications and integrations from one place.</p></div><button form="bh-settings-form" class="button button-primary">Save changes</button></div>';
    echo '<form method="post" action="options.php" id="bh-settings-form">';settings_fields('bubbahub');
    echo '<div class="bh-settings-shell"><aside class="bh-settings-nav">';
    foreach($sections as $key=>$label){$icons=['directory'=>'dashicons-admin-generic','search'=>'dashicons-search','users'=>'dashicons-admin-users','subscriptions'=>'dashicons-id-alt','monetization'=>'dashicons-money-alt','notifications'=>'dashicons-email','appearance'=>'dashicons-admin-appearance','site'=>'dashicons-admin-page','extensions'=>'dashicons-admin-plugins','maintenance'=>'dashicons-admin-tools'];echo '<a class="'.($section===$key?'active':'').'" href="'.esc_url(admin_url('admin.php?page=bubbahub-settings&section='.$key)).'"><span class="dashicons '.esc_attr($icons[$key]).'"></span>'.esc_html($label).'</a>';}
    echo '</aside><main class="bh-settings-content">';

    if($section==='directory'){
      echo '<section class="bh-card"><h2>Directory</h2><p class="description">Core BubbaHub directory behaviour.</p><div class="bh-setting"><label>Frontend page</label><input class="regular-text" name="bubbahub_settings[frontend_page]" value="'.esc_attr($s['frontend_page']??'').'" placeholder="Page ID or URL"></div><div class="bh-setting"><label>Default location</label><input class="regular-text" name="bubbahub_settings[location]" value="'.esc_attr($s['location']??'').'" placeholder="e.g. Torquay"></div><div class="bh-setting"><label>Directory Builder</label><div><a class="button" href="'.esc_url(admin_url('admin.php?page=bubbahub-directory-builder')).'">Open Directory Builder</a><p class="description">Configure Add Listing, Single Page, All Listing and Search layouts.</p></div></div></section>';
      echo '<section class="bh-card"><h2>Listing behaviour</h2><label class="bh-toggle-row"><input type="checkbox" name="bubbahub_settings[show_maps]" value="1" '.checked(($s['show_maps']??1),1,false).'><span><strong>Maps</strong><small>Show map/location information where coordinates are available.</small></span></label><label class="bh-toggle-row"><input type="checkbox" name="bubbahub_settings[show_reviews]" value="1" '.checked(($s['show_reviews']??1),1,false).'><span><strong>Reviews and ratings</strong><small>Enable the review-ready directory experience.</small></span></label></section>';
    }
    if($section==='search'){
      echo '<section class="bh-card"><h2>Search</h2><div class="bh-setting"><label>Default radius</label><select name="bubbahub_settings[search_radius]"><option value="5" '.selected($s['search_radius']??'10','5',false).'>5 miles</option><option value="10" '.selected($s['search_radius']??'10','10',false).'>10 miles</option><option value="25" '.selected($s['search_radius']??'10','25',false).'>25 miles</option><option value="50" '.selected($s['search_radius']??'10','50',false).'>50 miles</option></select></div><label class="bh-toggle-row"><input type="checkbox" name="bubbahub_settings[radius_search]" value="1" '.checked(($s['radius_search']??1),1,false).'><span><strong>Radius search</strong><small>Allow families to search by distance from their chosen location.</small></span></label><label class="bh-toggle-row"><input type="checkbox" name="bubbahub_settings[ajax_search]" value="1" '.checked(($s['ajax_search']??1),1,false).'><span><strong>AJAX search</strong><small>Update search results without a full page reload.</small></span></label></section>';
    }
    if($section==='users'){
      echo '<section class="bh-card"><h2>Users & Accounts</h2><label class="bh-toggle-row"><input type="checkbox" name="bubbahub_settings[leader_portal]" value="1" '.checked(($s['leader_portal']??1),1,false).'><span><strong>Leader Portal</strong><small>Use the frontend portal for eligible leaders.</small></span></label><label class="bh-toggle-row"><input type="checkbox" name="bubbahub_settings[leader_approval]" value="1" '.checked(($s['leader_approval']??1),1,false).'><span><strong>Require listing approval</strong><small>New leader listings can remain pending until an administrator approves them.</small></span></label><p class="description">Supported leader roles include BubbaHub Leader, Leader and Leader Pro.</p></section>';
    }
    if($section==='subscriptions'){
      $plans=BubbaHub::subscription_plans();
      $signup=(array)($s['signup']??[]);
      $payments=(array)($s['payment_methods']??[]);
      $features=BubbaHub::subscription_features();
      echo '<section class="bh-card bh-subscription-card"><div class="bh-section-title"><div><span class="bh-builder-eyebrow">Membership engine</span><h2>Subscriptions & Pricing</h2><p class="description">Create separate Parent and Leader membership products. The plans below are the source of truth for the pricing table, signup choices and future feature gating.</p></div><span class="bh-status-pill">Live configuration</span></div>';
      echo '<div class="bh-sub-tabs"><a href="#parent-plans">Parent plans</a><a href="#leader-plans">Leader plans</a><a href="#signup-rules">Signup</a><a href="#payment-methods">Payment options</a></div>';
      echo '<div class="bh-sub-grid" id="signup-rules"><div class="bh-sub-box"><h3>Parent signup</h3>'.self::subscription_checkbox('signup[parent_enabled]','Allow parent signup',!isset($signup['parent_enabled'])||!empty($signup['parent_enabled']),'Parents can create a BubbaHub account.').self::subscription_select('signup[parent_default_plan]','Default plan',$signup['parent_default_plan']??'parent-free',['parent-free'=>'Parent Free','parent-pro'=>'Parent Pro']).'</div><div class="bh-sub-box"><h3>Leader signup</h3>'.self::subscription_checkbox('signup[leader_enabled]','Allow leader signup',!isset($signup['leader_enabled'])||!empty($signup['leader_enabled']),'Leaders can choose a paid or free leader plan.').self::subscription_checkbox('signup[leader_approval]','Require leader approval',!isset($signup['leader_approval'])||!empty($signup['leader_approval']),'Require admin approval before a leader account becomes active.').self::subscription_select('signup[leader_default_plan]','Default plan',$signup['leader_default_plan']??'leader-basic',['leader-basic'=>'Leader Basic','leader-premium'=>'Leader Premium','leader-ultimate'=>'Leader Ultimate']).'</div></div>';
      echo '<h3 id="parent-plans" class="bh-anchor-heading">Parent pricing plans</h3><p class="description">Parents have their own plan family, separate from Leader pricing.</p><div class="bh-plan-editor-grid">';
      foreach(['parent-free','parent-pro'] as $id) echo self::subscription_plan_editor($id,$plans[$id],$features['parent']);
      echo '</div>';
      echo '<h3 id="leader-plans" class="bh-anchor-heading">Leader pricing plans</h3><p class="description">Leader tiers control listing, booking, promotion and portal features.</p><div class="bh-plan-editor-grid">';
      foreach(['leader-basic','leader-premium','leader-ultimate'] as $id) echo self::subscription_plan_editor($id,$plans[$id],$features['leader']);
      echo '</div>';
      echo '<div class="bh-sub-box" id="payment-methods"><h3>Payment options</h3><p class="description">Choose which payment methods can be offered by the pricing/checkout layer. Stripe is supported by the current payment architecture; PayPal requires a PayPal gateway integration before it can process payments.</p>'.self::subscription_checkbox('payment_methods[stripe]','Stripe Checkout',!isset($payments['stripe'])||!empty($payments['stripe']),'Use Stripe for one-off and recurring subscriptions.').self::subscription_checkbox('payment_methods[paypal]','PayPal',!empty($payments['paypal']),'Enable as a selectable payment method when a PayPal gateway is connected.').self::subscription_checkbox('payment_methods[bank]','Manual / bank transfer',!empty($payments['bank']),'Allow plans to be marked as payable manually.').self::subscription_checkbox('payment_methods[free]','Free checkout',!isset($payments['free'])||!empty($payments['free']),'Allow £0 plans to activate without a payment gateway.').'</div>';
      echo '<div class="bh-sub-box"><h3>Pricing table</h3><p class="description">The public pricing table is generated from these plan settings.</p><p><code>[bubbahub_pricing]</code></p><p><a class="button button-primary" target="_blank" href="'.esc_url(home_url('/pricing/')).'">View pricing page</a></p></div>';
      echo '</section>';
    }
    if($section==='monetization'){
      echo '<section class="bh-card"><h2>Monetization</h2><p>Membership and booking payments are managed through Stripe when credentials are configured.</p><div class="bh-plan-grid"><div><strong>Basic</strong><span>£10/year</span><small>Core directory</small></div><div><strong>Premium</strong><span>£25/year</span><small>Bookings, tickets, priority</small></div><div><strong>Ultimate</strong><span>£50/year</span><small>Unlimited, featured, enhanced</small></div></div><div class="bh-setting"><label>Stripe publishable key</label><input class="regular-text" name="bubbahub_settings[stripe_publishable_key]" value="'.esc_attr($s['stripe_publishable_key']??'').'"></div><div class="bh-setting"><label>Stripe secret key</label><input type="password" class="regular-text" name="bubbahub_settings[stripe_secret_key]" value="'.esc_attr($s['stripe_secret_key']??'').'"></div><label class="bh-toggle-row"><input type="checkbox" name="bubbahub_settings[stripe_test_mode]" value="1" '.checked(($s['stripe_test_mode']??1),1,false).'><span><strong>Stripe test mode</strong><small>Keep enabled while testing payments.</small></span></label></section>';
    }
    if($section==='notifications'){
      echo '<section class="bh-card"><h2>Notifications</h2><div class="bh-setting"><label>Admin email</label><input type="email" class="regular-text" name="bubbahub_settings[admin_email]" value="'.esc_attr($s['admin_email']??get_option('admin_email')).'"></div><label class="bh-toggle-row"><input type="checkbox" name="bubbahub_settings[booking_emails]" value="1" '.checked(($s['booking_emails']??1),1,false).'><span><strong>Booking emails</strong><small>Send booking confirmations and cancellation notifications.</small></span></label><label class="bh-toggle-row"><input type="checkbox" name="bubbahub_settings[leader_emails]" value="1" '.checked(($s['leader_emails']??1),1,false).'><span><strong>Leader notifications</strong><small>Notify leaders about new bookings and listing activity.</small></span></label></section>';
    }
    if($section==='appearance'){
      echo '<section class="bh-card"><h2>Appearance</h2><div class="bh-setting"><label>Primary colour</label><input type="text" class="regular-text" name="bubbahub_settings[primary_colour]" value="'.esc_attr($s['primary_colour']??'#e85d75').'" placeholder="#e85d75"></div><div class="bh-setting"><label>Card style</label><select name="bubbahub_settings[card_style]"><option value="rounded" '.selected($s['card_style']??'rounded','rounded',false).'>Rounded</option><option value="soft" '.selected($s['card_style']??'rounded','soft',false).'>Soft</option><option value="square" '.selected($s['card_style']??'rounded','square',false).'>Square</option></select></div><label class="bh-toggle-row"><input type="checkbox" name="bubbahub_settings[show_badges]" value="1" '.checked(($s['show_badges']??1),1,false).'><span><strong>Featured and verified badges</strong><small>Display directory status badges on cards and single listings.</small></span></label></section>';
    }
    if($section==='site'){
      echo '<section class="bh-card"><h2>Site & Pages</h2><p>Map BubbaHub features to normal WordPress pages.</p><p><a class="button" href="'.esc_url(admin_url('admin.php?page=bubbahub-pages')).'">Pages & Shortcodes</a></p><div class="bh-setting"><label>Directory page</label><input class="regular-text" name="bubbahub_settings[directory_page]" value="'.esc_attr($s['directory_page']??'').'" placeholder="Page ID or URL"></div></section>';
    }
    if($section==='extensions'){
      echo '<section class="bh-card"><h2>Extensions & Integrations</h2><div class="bh-setting"><label>Google Places API key</label><input type="password" class="regular-text" name="bubbahub_settings[google_places_key]" value="'.esc_attr($s['google_places_key']??'').'"><p class="description">Used for venue/address autocomplete and location-aware recommendations.</p></div><div class="bh-setting"><label>Google OAuth Client ID</label><input class="regular-text" name="bubbahub_settings[google_client_id]" value="'.esc_attr($s['google_client_id']??'').'" placeholder="Client ID"></div><div class="bh-setting"><label>Google OAuth Client secret</label><input type="password" class="regular-text" name="bubbahub_settings[google_client_secret]" value="'.esc_attr($s['google_client_secret']??'').'" placeholder="Client secret"></div><p class="description">Google callback: <code>'.esc_html($cb).'</code></p><div class="bh-setting"><label>Facebook App ID</label><input class="regular-text" name="bubbahub_settings[facebook_client_id]" value="'.esc_attr($s['facebook_client_id']??'').'" placeholder="App ID"></div><div class="bh-setting"><label>Facebook App secret</label><input type="password" class="regular-text" name="bubbahub_settings[facebook_client_secret]" value="'.esc_attr($s['facebook_client_secret']??'').'" placeholder="App secret"></div><p class="description">Facebook callback: <code>'.esc_html($fb).'</code></p></section>';
    }
    if($section==='maintenance'){
      echo '<section class="bh-card"><h2>Maintenance</h2><label class="bh-toggle-row"><input type="checkbox" name="bubbahub_settings[debug]" value="1" '.checked(($s['debug']??0),1,false).'><span><strong>Debug mode</strong><small>Enable extra diagnostics while troubleshooting.</small></span></label><p><strong>Database version:</strong> '.esc_html(get_option('bubbahub_db_version','unknown')).'</p><p><strong>Plugin version:</strong> '.esc_html(defined('BUBBAHUB_VERSION')?BUBBAHUB_VERSION:'').'</p></section>';
    }
    echo '</main></div></form><style>
    .bh-settings-page{max-width:none;margin-right:20px}.bh-settings-head{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;margin:24px 0}.bh-settings-head h1{margin:5px 0;font-size:26px}.bh-settings-head p{margin:0;color:#646970}.bh-settings-shell{display:grid;grid-template-columns:235px minmax(0,1fr);background:#fff;border:1px solid #dcdcde;border-radius:8px;overflow:hidden;min-height:600px}.bh-settings-nav{background:#f6f7f7;border-right:1px solid #dcdcde;padding:12px}.bh-settings-nav a{display:flex;align-items:center;gap:9px;padding:11px 12px;margin:2px 0;border-radius:6px;text-decoration:none;color:#50575e}.bh-settings-nav a:hover{background:#fff}.bh-settings-nav a.active{background:#fff;color:#2271b1;font-weight:600;box-shadow:0 1px 2px rgba(0,0,0,.04)}.bh-settings-content{padding:24px;background:#fafafa}.bh-card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:22px;max-width:980px}.bh-card+.bh-card{margin-top:18px}.bh-card h2{margin-top:0}.bh-setting{display:grid;grid-template-columns:220px 1fr;gap:18px;align-items:start;padding:17px 0;border-bottom:1px solid #eee}.bh-setting>label{font-weight:600}.bh-setting input,.bh-setting select{max-width:500px}.bh-toggle-row{display:flex;gap:12px;align-items:flex-start;padding:16px 0;border-bottom:1px solid #eee}.bh-toggle-row input{margin-top:3px}.bh-toggle-row small{display:block;color:#646970;margin-top:3px}.bh-plan-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin:20px 0}.bh-plan-grid>div{border:1px solid #dcdcde;border-radius:8px;padding:16px}.bh-plan-grid strong,.bh-plan-grid span,.bh-plan-grid small{display:block}.bh-plan-grid span{font-size:18px;font-weight:700;margin:6px 0}.bh-plan-grid small{color:#646970}@media(max-width:800px){.bh-settings-shell{grid-template-columns:1fr}.bh-settings-nav{border-right:0;border-bottom:1px solid #dcdcde;display:flex;overflow:auto}.bh-settings-nav a{white-space:nowrap}.bh-setting{grid-template-columns:1fr}.bh-plan-grid{grid-template-columns:1fr}}

      .bh-section-title{display:flex;justify-content:space-between;gap:20px;align-items:flex-start}.bh-status-pill{display:inline-flex;padding:7px 11px;border-radius:999px;background:#eaf8f4;color:#177b65;font-weight:600;font-size:12px}.bh-sub-tabs{display:flex;gap:8px;flex-wrap:wrap;margin:18px 0 24px;padding-bottom:12px;border-bottom:1px solid #e6ebf0}.bh-sub-tabs a{padding:8px 12px;border-radius:8px;background:#f4f7fa;text-decoration:none;color:#284a66}.bh-anchor-heading{scroll-margin-top:30px;margin-top:28px}.bh-sub-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.bh-sub-box{background:#fff;border:1px solid #e0e6ec;border-radius:12px;padding:18px;margin:18px 0;box-shadow:0 1px 2px rgba(25,56,87,.03)}.bh-sub-box h3{margin-top:0;color:#193d62}.bh-plan-editor-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}.bh-plan-editor{background:#fff;border:1px solid #dfe6ec;border-radius:12px;overflow:hidden}.bh-plan-editor-head{padding:16px 18px;background:linear-gradient(135deg,#f8fbff,#fff);border-bottom:1px solid #e7edf2;display:flex;justify-content:space-between;gap:10px}.bh-plan-editor-head h3{margin:3px 0 0}.bh-plan-type{font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#4c7a96}.bh-switch{font-size:12px;display:flex;gap:6px;align-items:center}.bh-plan-fields{padding:18px}.bh-plan-fields>label,.bh-plan-fields .bh-inline-fields label{display:block;font-weight:600;margin-bottom:12px}.bh-plan-fields input,.bh-plan-fields textarea,.bh-plan-fields select{display:block;width:100%;margin-top:5px}.bh-inline-fields{display:grid;grid-template-columns:1fr 1fr;gap:10px}.bh-inline-fields label:last-child{grid-column:1/-1}.bh-feature-checks{display:grid;grid-template-columns:1fr 1fr;gap:8px}.bh-feature-checks label{font-weight:400;padding:8px;background:#f7f9fb;border-radius:7px}.bh-sub-toggle{border-bottom:1px solid #edf0f2}.bh-sub-select{margin-top:4px}.bh-subscription-card{max-width:1200px}.bh-plan-editor input[type=number]{max-width:none}@media(max-width:1100px){.bh-plan-editor-grid{grid-template-columns:1fr}.bh-sub-grid{grid-template-columns:1fr}}@media(max-width:700px){.bh-inline-fields,.bh-feature-checks{grid-template-columns:1fr}.bh-inline-fields label:last-child{grid-column:auto}.bh-section-title{flex-direction:column}}
      .bh-settings-page{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.bh-settings-head{position:relative;padding-top:10px}.bh-settings-head:before{content:"";position:absolute;left:0;right:0;top:0;height:5px;background:linear-gradient(90deg,#49b6c8,#f29ab0,#f5cf76,#7ccfb1)}.bh-settings-shell{border-radius:12px;border-color:#dfe5ea;box-shadow:0 8px 24px rgba(25,56,87,.05)}.bh-settings-nav{background:#f8fafc;padding:14px}.bh-settings-nav a{border-radius:8px;color:#40566b}.bh-settings-nav a.active{color:#2271b1;background:#fff;box-shadow:0 2px 7px rgba(24,59,99,.08)}.bh-settings-content{background:#f7f9fb}.bh-settings-content .bh-card{border-radius:10px;border-color:#e0e6ec;box-shadow:0 1px 2px rgba(25,56,87,.03)}.bh-settings-content .bh-card h2{color:#193d62}.bh-settings-content input,.bh-settings-content select{border-radius:7px}
    </style></div>';
  }

}
