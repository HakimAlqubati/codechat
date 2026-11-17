(function(){
 'use strict';
 if(window.amzProfileStoreBound) return; window.amzProfileStoreBound=true;
 function applyProfileStoreAssets(root){
   root=root||document;
   var profile=root.querySelector ? root.querySelector('.profile_background[data-amz-profile-bg]') : null;
   if(profile){
     var bg=profile.getAttribute('data-amz-profile-bg')||'';
     var host=document.getElementById('large_modal_content');
     if(host){
       if(bg){host.classList.add('amz-profile-store-bg');host.style.backgroundImage='url("'+bg.replace(/["\\]/g,'')+'")';profile.style.backgroundImage='none';host.style.backgroundSize='cover';host.style.backgroundPosition='center';host.style.backgroundAttachment='local';host.style.backgroundRepeat='no-repeat';}
       else{host.classList.remove('amz-profile-store-bg');host.style.backgroundImage='';profile.style.backgroundImage='';}
     }
   }
   var avatar=root.querySelector ? root.querySelector('#proav[data-amz-profile-frame]') : null;
   if(avatar){var frame=avatar.getAttribute('data-amz-profile-frame')||'';if(frame){avatar.style.setProperty('--amz-profile-frame','url("'+frame.replace(/["\\]/g,'')+'")');}else{avatar.style.removeProperty('--amz-profile-frame');}}
 }
 var observer=new MutationObserver(function(){applyProfileStoreAssets(document);});
 $(function(){applyProfileStoreAssets(document);var target=document.getElementById('large_modal_content');if(target)observer.observe(target,{childList:true,subtree:true,attributes:true,attributeFilter:['data-amz-profile-bg','data-amz-profile-frame']});});
 window.amzStoreAction=function(action,id){
   $.post('addons/amz_profile_store/system/action.php',{token:utk,action:action,item_id:id},function(r){
      if(r && r.code===1){callSuccess(r.message||'تم بنجاح');window.amzProfileStoreOpen();}
      else{callError((r&&r.message)||system.error);}
   },'json').fail(function(){callError(system.error);});
 };
 window.amzStoreSelectTab=function(tab){$('.amz-store-tab').removeClass('active');$('.amz-store-tab[data-tab="'+tab+'"]').addClass('active');$('.amz-store-panel').hide();$('#amz-store-panel-'+tab).show();};
 window.amzStoreServicePurchase=function(service){
   $.post('addons/amz_profile_store/system/action.php',{token:utk,action:'buy_service',service:service},function(r){if(r&&r.code===1){callSuccess(r.message||'تم الشراء');window.amzProfileStoreOpen();}else{callError((r&&r.message)||system.error);}},'json').fail(function(){callError(system.error);});
 };
})();
