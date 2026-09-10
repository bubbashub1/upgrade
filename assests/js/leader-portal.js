(function(){
  'use strict';
  const C=window.BubbaHubConfig||{};
  const root=document.getElementById('bubbahub-app');
  if(!root)return;
  const roles=(C.user&&C.user.roles)||[];
  const eligible=!!C.user && roles.some(r=>['administrator','bubbahub_leader','leader','leaderpro'].includes(r));
  if(!eligible)return;

  const esc=s=>String(s??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));
  async function api(path,opt={}){
    opt.headers=Object.assign({'Content-Type':'application/json'},opt.headers||{});
    if(C.nonce)opt.headers['X-WP-Nonce']=C.nonce;
    const r=await fetch(C.api+path,opt);
    let j={};try{j=await r.json()}catch(e){}
    if(!r.ok)throw new Error(j.message||'Request failed ('+r.status+')');
    return j;
  }

  const standard=[
    ['description','Description','textarea'],['tags','Tags','text'],['category','Category','text'],['is_featured','Featured','checkbox'],
    ['images','Images','text'],['address','Address','text'],['city','Town / City','text'],['region','Region / County','text'],['zip','Postcode','text'],
    ['manual_lat','Latitude','text'],['manual_lng','Longitude','text'],['timetable','Timetable','textarea'],['openinghours','Opening Hours','textarea'],
    ['website','Website','url'],['email','Email','email'],['facebook','Facebook','url'],['instagram','Instagram','url'],['is_free','Free','checkbox'],
    ['price','Price','text'],['term_time','Term Time','text'],['age_range','Age Range','text'],['session_length','Session Length','text'],['day','Day','text'],['sen','SEN Friendly','text']
  ];

  function fieldHtml(key,label,type,value){
    value=value??'';
    if(type==='checkbox')return `<label class="bh-leader-check"><input type="checkbox" data-field="${esc(key)}" ${value==='1'||value===1||value===true?'checked':''}> <span>${esc(label)}</span></label>`;
    const tag=type==='textarea'?'textarea':'input';
    const attrs=type==='url'?'type="url"':type==='email'?'type="email"':'type="text"';
    return `<label class="bh-leader-field"><span>${esc(label)}</span>${tag==='textarea'?`<textarea data-field="${esc(key)}" rows="4">${esc(value)}</textarea>`:`<input ${attrs} data-field="${esc(key)}" value="${esc(value)}">`}</label>`;
  }

  function editorShell(title,subtitle,post,type,isNew){
    const fields=[];
    const custom=post&&post.custom_fields?post.custom_fields:{};
    standard.forEach(([k,l,t])=>{
      if(isNew || custom[k] || ['description','category','address','city','website','email','price','age_range','timetable','openinghours'].includes(k))
        fields.push(fieldHtml(k,l,t,custom[k]?.value??''));
    });
    Object.keys(custom).forEach(k=>{
      if(standard.some(x=>x[0]===k))return;
      const f=custom[k]||{};fields.push(fieldHtml(k,f.label||k,f.type||'text',f.value??''));
    });
    const content=post?.raw_content||'';
    return `<div class="bh-leader-editor-wrap"><div class="bh-leader-editor-head"><div><span class="bh-eyebrow">${isNew?'Create listing':'Edit listing'}</span><h2>${esc(title)}</h2><p>${esc(subtitle)}</p></div><button type="button" class="bh-btn secondary" id="bh-leader-cancel">Cancel</button></div><form id="bh-leader-form"><div class="bh-leader-main"><section class="bh-leader-card"><label class="bh-leader-field"><span>Listing name *</span><input id="bh-leader-title" required value="${esc(post?.title||'')}"></label><label class="bh-leader-field"><span>Description</span><textarea id="bh-leader-content" rows="8">${esc(content)}</textarea></label><div class="bh-leader-grid">${fields.join('')}</div></section><aside class="bh-leader-side"><section class="bh-leader-card"><h3>Publishing</h3><label class="bh-leader-field"><span>Status</span><select id="bh-leader-status"><option value="draft" ${post?.status==='draft'||!post?'selected':''}>Draft</option><option value="pending" ${post?.status==='pending'?'selected':''}>Pending review</option><option value="publish" ${post?.status==='publish'?'selected':''}>Published</option></select></label><p class="bh-small">New listings are saved as drafts by default. Your administrator can approve or publish them.</p></section><section class="bh-leader-card"><h3>Google Sheets</h3><p class="bh-small" id="bh-google-message">${post?.google_writeback_enabled?'Changes will be written back to the linked Google Sheet.':'Google write-back is not currently enabled.'}</p></section></aside></div><div class="bh-leader-actions"><button class="bh-btn" type="submit" id="bh-leader-save">${isNew?'Create listing':'Save changes'}</button><span id="bh-leader-result" role="status"></span></div></form></div>`;
  }

  function collect(){
    const meta={};root.querySelectorAll('#bh-leader-form [data-field]').forEach(el=>{meta[el.dataset.field]=el.type==='checkbox'?(el.checked?'1':'0'):el.value;});
    return {title:root.querySelector('#bh-leader-title').value.trim(),content:root.querySelector('#bh-leader-content').value,status:root.querySelector('#bh-leader-status').value,meta};
  }

  async function openEditor(type,id){
    try{
      const post=id?await api(`leader/${type}/${id}`):null;
      root.innerHTML=editorShell(post?post.title:'New listing',type==='groups'?'Add or update your local group listing.':'Add or update your event.',post,type,id?false:true);
      root.querySelector('#bh-leader-cancel').onclick=()=>renderPortal();
      root.querySelector('#bh-leader-form').onsubmit=async e=>{
        e.preventDefault();const btn=root.querySelector('#bh-leader-save'),msg=root.querySelector('#bh-leader-result');btn.disabled=true;msg.textContent='Saving…';
        try{const data=collect();if(!data.title){throw new Error('Please enter a listing name.')}const out=await api(`leader/${type}${id?'/'+id:''}`,{method:id?'PUT':'POST',body:JSON.stringify(data)});msg.textContent=out.message||'Listing saved.';btn.textContent='Saved';setTimeout(()=>renderPortal(),700)}catch(err){msg.textContent=err.message;btn.disabled=false;}
      };
    }catch(e){root.innerHTML=`<div class="bh-main"><div class="bh-empty"><h2>Could not open listing</h2><p>${esc(e.message)}</p><button class="bh-btn" id="bh-retry">Back to Leader Portal</button></div></div>`;root.querySelector('#bh-retry').onclick=renderPortal;}
  }

  async function renderPortal(){
    root.innerHTML='<div class="bh-main"><div class="bh-empty"><p>Loading your listings…</p></div></div>';
    try{
      const groups=await api('leader/groups');
      let events=[];try{events=await api('leader/events')}catch(e){}
      root.innerHTML=`<div class="bh-main bh-leader bh-leader-portal"><div class="bh-pagehead"><div><div class="bh-eyebrow">Leader workspace</div><h1>Leader Portal</h1><p class="bh-small">Manage your BubbaHub listings from the front end.</p></div><button class="bh-btn" id="bh-add-listing">＋ Add New Listing</button></div><div class="bh-leader-notice" id="bh-leader-notice"></div><section class="bh-section"><div class="bh-section-title"><h2>Your groups</h2><span class="bh-small">${groups.length} listing${groups.length===1?'':'s'}</span></div><div class="bh-grid">${groups.length?groups.map(g=>`<article class="bh-card bh-leader-listing"><span class="bh-tag">Group</span><h3>${esc(g.title||'Untitled')}</h3><p class="bh-small">${esc(g.status||'draft')} ${g.google_id?' · Google linked':''}</p><button class="bh-btn secondary" data-edit-group="${g.id}">Edit listing</button></article>`).join(''):'<div class="bh-card"><h3>No groups yet</h3><p>Create your first listing using the button above.</p></div>'}</div></section><section class="bh-section"><div class="bh-section-title"><h2>Your events</h2><button class="bh-btn secondary" id="bh-add-event">＋ Add Event</button></div><div class="bh-grid">${events.length?events.map(g=>`<article class="bh-card bh-leader-listing"><span class="bh-tag">Event</span><h3>${esc(g.title||'Untitled')}</h3><p class="bh-small">${esc(g.status||'draft')}</p><button class="bh-btn secondary" data-edit-event="${g.id}">Edit event</button></article>`).join(''):'<div class="bh-card"><p>No events yet.</p></div>'}</div></section></div>`;
      root.querySelector('#bh-add-listing').onclick=()=>openEditor('groups',0);
      root.querySelector('#bh-add-event').onclick=()=>openEditor('events',0);
      root.querySelectorAll('[data-edit-group]').forEach(b=>b.onclick=()=>openEditor('groups',+b.dataset.editGroup));
      root.querySelectorAll('[data-edit-event]').forEach(b=>b.onclick=()=>openEditor('events',+b.dataset.editEvent));
    }catch(e){
      root.innerHTML=`<div class="bh-main"><div class="bh-empty"><h2>Leader access is not working</h2><p>${esc(e.message)}</p><p class="bh-small">The WordPress REST request was rejected. The plugin now supplies the REST nonce automatically, so if this remains, the account needs the BubbaHub leader capability.</p><button class="bh-btn" id="bh-leader-refresh">Refresh</button></div></div>`;
      root.querySelector('#bh-leader-refresh').onclick=renderPortal;
    }
  }

  function boot(){
    const page=(C.initialPage||'');
    if(page==='leader' || location.pathname.replace(/\/+$/,'').endsWith('/leader'))renderPortal();
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot);else boot();
})();
