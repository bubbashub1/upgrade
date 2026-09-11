(function(){
'use strict';
function markReady(){
  var root=document.getElementById('bubbahub-app');
  if(!root)return false;
  var enhanced=root.querySelector('#bhDirectoryEnhanced');
  var grid=enhanced&&enhanced.querySelector('#bhListingGrid');
  if(!grid)return false;
  /* Only reveal after the directory controller has actually painted its cards. */
  var hasCards=!!grid.querySelector('.bh-directory-card');
  var hasEmpty=!!grid.querySelector('.bh-empty-search');
  var hasMap=!!grid.querySelector('.bh-directory-map-layout');
  if(hasCards||hasEmpty||hasMap){grid.classList.add('bh-directory-ready');return true;}
  return false;
}
function start(){
  if(markReady())return;
  var root=document.getElementById('bubbahub-app');
  if(!root)return;
  var observer=new MutationObserver(function(){if(markReady())observer.disconnect();});
  observer.observe(root,{childList:true,subtree:true});
  setTimeout(function(){observer.disconnect();markReady();},10000);
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start);else start();
})();
