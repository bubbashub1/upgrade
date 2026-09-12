(function(){
  'use strict';
  var config=window.BubbaHubConfig||{};
  var root=document.getElementById('bubbahub-app');
  if(!root) return;

  var cache=null;
  function esc(value){return String(value==null?'':value).replace(/[&<>\"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":'&#039;'}[c]});}
  function plain(value){return String(value==null?'':value).replace(/<[^>]*>/g,'').trim();}
  function getField(group, names){
    var fields=group&&group.custom_fields||{};
    var wanted=names.map(function(n){return n.toLowerCase()});
    var found='';
    Object.keys(fields).some(function(k){
      var item=fields[k]||{};
      var label=String(item.label||k).toLowerCase();
      if(wanted.indexOf(label)!==-1 && item.value!=='' && item.value!=null){found=item.value;return true;}
      return false;
    });
    return found;
  }
  function chips(group){
    var values=[];
    var location=getField(group,['town','city','location','area','region']);
    var age=getField(group,['age range','age_range','ages','age']);
    var price=getField(group,['price','cost','pricing']);
    var days=getField(group,['days','day','timetable','business hours']);
    if(location) values.push(['⌖ '+plain(location),'']);
    if(days) values.push(['◷ '+plain(days),'is-green']);
    if(age) values.push(['♧ '+plain(age),'is-blue']);
    if(price) values.push(['£ '+plain(price),'is-purple']);
    if(!values.length) return '';
    return '<div class="bh-card-meta">'+values.slice(0,4).map(function(v){return '<span class="bh-card-chip '+v[1]+'">'+esc(v[0])+'</span>'}).join('')+'</div>';
  }
  function apply(groups){
    if(!groups||!groups.length) return;
    var cards=root.querySelectorAll('.bh-section > .bh-grid > article.bh-card');
    Array.prototype.forEach.call(cards,function(card){
      if(card.dataset.bhPolished==='1') return;
      var title=plain((card.querySelector('h3')||{}).textContent||'');
      if(!title) return;
      var group=groups.find(function(g){return plain(g.title)===title});
      if(!group) return;
      card.dataset.bhPolished='1';
      var children=Array.prototype.slice.call(card.children);
      var inner=document.createElement('div');
      inner.className='bh-card-inner';
      children.forEach(function(child){inner.appendChild(child)});
      var image=plain(group.image||group.featured_image||'');
      if(image){
        var img=document.createElement('img');
        img.className='bh-card-image';
        img.src=image;
        img.alt='';
        img.loading='lazy';
        img.decoding='async';
        img.addEventListener('error',function(){img.remove();card.classList.remove('has-bh-image')},{once:true});
        card.insertBefore(img,card.firstChild);
        card.classList.add('has-bh-image');
      }
      var heading=inner.querySelector('h3');
      if(heading){
        var meta=document.createElement('div');
        meta.innerHTML=chips(group);
        if(meta.firstElementChild) heading.insertAdjacentElement('afterend',meta.firstElementChild);
      }
      card.appendChild(inner);
    });
  }
  function load(){
    if(cache){apply(cache);return}
    if(!config.api)return;
    fetch(config.api+'bootstrap',{headers:config.nonce?{'X-WP-Nonce':config.nonce}:{}})
      .then(function(r){return r.ok?r.json():null})
      .then(function(data){cache=data&&Array.isArray(data.groups)?data.groups:[];apply(cache)})
      .catch(function(){/* The base cards remain fully usable without the polish data. */});
  }
  var observer=new MutationObserver(function(){load()});
  observer.observe(root,{childList:true,subtree:true});
  load();
})();
