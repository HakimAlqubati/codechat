(function($){
'use strict';
var rwPostUploadKey='',rwStoryUploadKey='',rwPostRequestKey='',rwStoryRequestKey='',rwPostBusy=false,rwStoryBusy=false,rwStories=[],rwStoryIndex=0,rwStoryOwner=0,rwRecorders={},rwStoryOwnerOrder=[],rwSeenMarked={},rwAutoTimer=null,rwAutoEnd=0,rwAutoRemaining=0,rwPausedMedia=null,rwStoryPaused=false,rwPressHoldTimer=null;
function rwPost(data,done,fail){data=data||{};data.token=utk;$.ajax({url:'addons/Room_wall/system/action.php',type:'POST',data:data,dataType:'json',success:done,error:function(xhr){if(typeof fail==='function')fail(xhr);else if(typeof callError==='function')callError(system.error);}});}
function rwMakeRequestKey(){var bytes=new Uint8Array(16);if(window.crypto&&window.crypto.getRandomValues){window.crypto.getRandomValues(bytes);}else{for(var i=0;i<16;i++)bytes[i]=Math.floor(Math.random()*256);}return Array.prototype.map.call(bytes,function(x){return ('0'+x.toString(16)).slice(-2);}).join('');}
function rwClientKind(file){
 var mime=(file.type||'').toLowerCase(),name=(file.name||'').toLowerCase();
 if(mime.indexOf('image/')===0)return 'image';if(mime.indexOf('video/')===0)return 'video';if(mime.indexOf('audio/')===0)return 'audio';
 var ext=(name.match(/\.([a-z0-9]+)$/)||[])[1]||'';
 if(['jpg','jpeg','png','gif','webp'].indexOf(ext)!==-1)return 'image';
 if(['mp4','webm','ogv','mov'].indexOf(ext)!==-1)return 'video';
 if(['mp3','wav','oga','ogg','m4a','aac'].indexOf(ext)!==-1)return 'audio';
 return '';
}
function rwUpload(file,target,done){
 var kind=rwClientKind(file),limit=kind==='image'?Number(window.rwMaxImageMB||1):(kind==='video'?Number(window.rwMaxVideoMB||1):(kind==='audio'?Number(window.rwMaxAudioMB||10):0));
 if(!limit){$('#rw_'+target+'_upload_status').text('نوع الملف غير مدعوم.');return;}
 if(file.size>limit*1024*1024){$('#rw_'+target+'_upload_status').text('حجم الملف يتجاوز الحد المسموح ('+limit+' MB).');return;}
 var form=new FormData();form.append('file',file);form.append('token',utk);form.append('target',target);
 $('#rw_'+target+'_upload_status').text('جارٍ رفع الملف...');
 $.ajax({url:'addons/Room_wall/system/upload_media.php',type:'POST',data:form,dataType:'json',contentType:false,processData:false,success:function(r){
  if(r&&r.ok){if(target==='post')rwPostUploadKey=r.key;else rwStoryUploadKey=r.key;$('#rw_'+target+'_upload_status').text('تم رفع الملف وتجهيزه للنشر.');if(done)done(r);}
  else{$('#rw_'+target+'_upload_status').text((r&&r.error)||'فشل رفع الملف.');}
 },error:function(){$('#rw_'+target+'_upload_status').text('تعذر رفع الملف.');}});
}
function rwCheckDuration(file,cb){
 var kind=rwClientKind(file),isVideo=kind==='video',isAudio=kind==='audio';if(!isVideo&&!isAudio){cb(true);return;}
 var url=URL.createObjectURL(file),v=document.createElement(isVideo?'video':'audio');v.preload='metadata';v.onloadedmetadata=function(){URL.revokeObjectURL(url);var max=isVideo?Number(window.rwMaxVideoSeconds||60):Number(window.rwMaxAudioSeconds||30);cb(Number.isFinite(Number(v.duration))&&Number(v.duration)>0&&Number(v.duration)<=max);};v.onerror=function(){URL.revokeObjectURL(url);cb(false);};v.src=url;
}
$(document).on('change','#rw_post_file',function(){var f=this.files&&this.files[0];if(!f)return;rwCheckDuration(f,function(ok){if(!ok){$('#rw_post_upload_status').text('تعذر التحقق من مدة الملف أو أنها تتجاوز الحد المسموح.');return;}rwUpload(f,'post');});});
$(document).on('change','#rw_story_file',function(){var f=this.files&&this.files[0];if(!f)return;rwCheckDuration(f,function(ok){if(!ok){$('#rw_story_upload_status').text('تعذر التحقق من مدة الملف أو أنها تتجاوز الحد المسموح.');return;}rwUpload(f,'story');});});
function rwCountChars(selector,counter){var el=$(selector),max=Number(el.attr('data-rw-max')||el.attr('maxlength')||0),value=el.val()||'',chars=Array.from(value),len=chars.length;if(max>0&&len>max){value=chars.slice(0,max).join('');el.val(value);len=max;}$(counter).text(len).toggleClass('rw-char-over',max>0&&len>max);}
$(document).on('input','#rw_post_text',function(){rwCountChars('#rw_post_text','#rw_post_char_count');});
$(document).on('input','#rw_story_text',function(){rwCountChars('#rw_story_text','#rw_story_char_count');});
window.rwCreatePost=function(){var text=$('#rw_post_text').val()||'';if(rwPostBusy)return;if(!text.trim()&&!rwPostUploadKey){$('#rw_post_upload_status').text('اكتب نصاً أو أرفق ملفاً.');return;}rwPostBusy=true;rwPostRequestKey=rwPostRequestKey||rwMakeRequestKey();$('#rw_post_upload_status').text('جارٍ نشر المنشور والتحقق من رسوم الذهب...');rwPost({rw_action:'create_post',content:text,upload_key:rwPostUploadKey,request_key:rwPostRequestKey},function(r){rwPostBusy=false;if(r&&r.ok){$('#container_news .empty_zone').remove();if(!r.id||!$('#boom_gen'+r.id).length)$('#container_news').prepend(r.html);$('#rw_post_text').val('').css('height','60px');$('#rw_post_file').val('');rwPostUploadKey='';rwPostRequestKey='';$('#rw_post_upload_status').text(Number(r.charged)>0?'تم النشر وخصم '+r.charged+' ذهب. رصيدك: '+Number(r.balance||0):'تم النشر دون خصم ذهب.');}else $('#rw_post_upload_status').text((r&&r.error)||'تعذر نشر المنشور.');},function(){rwPostBusy=false;$('#rw_post_upload_status').text('انقطع الاتصال. اضغط نشر مرة أخرى لإعادة نفس العملية دون خصم مكرر.');});};
window.rwCreateStory=function(){var text=$('#rw_story_text').val()||'';if(rwStoryBusy)return;if(!text.trim()&&!rwStoryUploadKey){$('#rw_story_upload_status').text('اكتب قصة أو أرفق ملفاً.');return;}rwStoryBusy=true;rwStoryRequestKey=rwStoryRequestKey||rwMakeRequestKey();$('#rw_story_upload_status').text('جارٍ نشر القصة والتحقق من رسوم الذهب...');rwPost({rw_action:'create_story',content:text,upload_key:rwStoryUploadKey,request_key:rwStoryRequestKey,privacy:$('#rw_story_privacy').val()||'all'},function(r){rwStoryBusy=false;if(r&&r.ok){$('#rw_story_text').val('');$('#rw_story_file').val('');rwStoryUploadKey='';rwStoryRequestKey='';$('#rw_story_upload_status').text(Number(r.charged)>0?'تم نشر القصة وخصم '+r.charged+' ذهب. رصيدك: '+Number(r.balance||0):'تم نشر القصة دون خصم ذهب.');if(typeof getSwNews==='function')getSwNews();}else $('#rw_story_upload_status').text((r&&r.error)||'تعذر نشر القصة.');},function(){rwStoryBusy=false;$('#rw_story_upload_status').text('انقطع الاتصال. اضغط نشر القصة مرة أخرى لإعادة نفس العملية دون خصم مكرر.');});};
window.rwToggleRecord=function(target){
 if(!navigator.mediaDevices||!navigator.mediaDevices.getUserMedia||typeof MediaRecorder==='undefined'){alert('المتصفح لا يدعم تسجيل الصوت.');return;}
 if(rwRecorders[target]){rwStopRecord(target);return;}
 navigator.mediaDevices.getUserMedia({audio:true}).then(function(stream){
  var chunks=[],rec;try{rec=new MediaRecorder(stream);}catch(e){stream.getTracks().forEach(function(t){t.stop();});alert('تعذر بدء تسجيل الصوت على هذا المتصفح.');return;}
  rwRecorders[target]={rec:rec,stream:stream,chunks:chunks,started:Date.now()};rec.ondataavailable=function(e){if(e.data&&e.data.size)chunks.push(e.data);};rec.onstop=function(){if(rwRecorders[target]&&rwRecorders[target].timer)clearTimeout(rwRecorders[target].timer);stream.getTracks().forEach(function(t){t.stop();});rwRecorders[target]=null;$('#rw_'+target+'_record').removeClass('hidden');$('#rw_'+target+'_record_stop').addClass('hidden');var blob=new Blob(chunks,{type:rec.mimeType||'audio/webm'});if(blob.size){var file=new File([blob],'voice-message.webm',{type:blob.type||'audio/webm'});rwUpload(file,target);}else $('#rw_'+target+'_upload_status').text('التسجيل فارغ.');};rec.start();rwRecorders[target].timer=window.setTimeout(function(){rwStopRecord(target);},Number(window.rwMaxAudioSeconds||30)*1000);$('#rw_'+target+'_record').addClass('hidden');$('#rw_'+target+'_record_stop').removeClass('hidden');$('#rw_'+target+'_upload_status').text('جارٍ التسجيل... اضغط إيقاف عند الانتهاء.');
 }).catch(function(){alert('يلزم السماح باستخدام الميكروفون لتسجيل الرسالة الصوتية.');});
};
window.rwStopRecord=function(target){var x=rwRecorders[target];if(x&&x.rec&&x.rec.state!=='inactive')x.rec.stop();};
window.rwEditPost=function(id,button){var box=$('#boom_gen'+id),old=box.find('.post_content').text().trim();var next=window.prompt('عدّل نص المنشور:',old);if(next===null)return;rwPost({rw_action:'edit_post',id:id,content:next},function(r){if(r&&r.ok)box.replaceWith(r.html);else alert((r&&r.error)||'تعذر تعديل المنشور.');});};
window.rwDeletePost=function(id){if(!window.confirm('هل أنت متأكد من حذف هذا المنشور؟'))return;rwPost({rw_action:'delete_post',id:id},function(r){if(r&&r.ok)$('#boom_gen'+id).remove();else alert((r&&r.error)||'تعذر حذف المنشور.');});};
window.rwReportPost=function(id){var reason=window.prompt('اكتب سبب الإبلاغ (اختياري):','');if(reason===null)return;rwPost({rw_report_post:id,reason:reason},function(r){if(r&&r.ok)alert('تم إرسال البلاغ.');else alert((r&&r.error)||'تعذر إرسال البلاغ.');});};
window.openPostOptionssw=function(item){$(item).children('.post_menu').toggle();};
window.viewSxLikes=function(id){$.post('addons/Room_wall/system/box/sw_likes.php',{id:id,token:utk},function(r){if(r&&r!=='0')showModal(r,400);});};
window.swNewsLike=function(id,type){rwPost({like_news:id,like_type:type},function(r){if(r&&r.code===1)$('.swNewsLike'+id).html(r.data);});};
window.sxReply=function(id,item){var value=$(item).val();if(!value.trim())return;rwPost({content:value,reply_news:id},function(r){if(r&&r.code===1){$('.ncmtbox'+id).prepend(r.data);$('.ncmtboxwrap'+id).show();$(item).val('');$('#nrepcounts'+id).text(r.total).parent().removeClass('hidden');}});};
window.loadSxComment=function(item,id){var wrap=$('.ncmtboxwrap'+id);if($(item).attr('data')==='1'){wrap.toggle();return;}$(item).attr('data','1');rwPost({load_news_comment:1,id:id},function(r){if(r&&r.code===1){$('.ncmtbox'+id).html(r.reply==='0'?'':r.reply);$('.ncmb'+id).show();if(r.more) $('.nmorebox'+id).html(r.more);wrap.show();}});};
window.moreSxComment=function(t,id){var offset=$('.ncmtbox'+id).children().last().attr('data');rwPost({load_news_reply:1,current:offset,id:id},function(r){if(r&&r!=='0')$('.ncmtbox'+id).append(r);else $('.nmorebox'+id).empty();});};
window.nrepcounts=function(id,c){$('#nrepcounts'+id).text(c);if(c>0)$('#nrepcounts'+id).parent().removeClass('hidden');else $('#nrepcounts'+id).parent().addClass('hidden');};
window.moreNewsgen=function(){var last=$('#container_news .gen_box').last().attr('data');if(!last)return;var btn=$('.load_more_news button');btn.prop('disabled',true);rwPost({more_news:last},function(html){if(!html||html==='0')$('.load_more_news').remove();else{$('#container_news').append(html);if($(html).filter('.gen_box').length<10)$('.load_more_news').remove();else btn.prop('disabled',false);}});};
window.deleteSwReply=function(id){rwPost({delete_news_reply:id},function(r){if(r&&r.code===1){$('#swnreply'+id).remove();nrepcounts(r.gen,r.total);}});};
window.openDeleteSwPost=function(type,id){if(type==='gen')rwDeletePost(id);else if(type==='gen_reply')deleteSwReply(id);};
window.deleteSw=function(id){rwDeletePost(id);};
window.makeStaticNewsw=function(id,pin){rwPost({mstatic:id,pin:pin},function(r){if(r==='1'||r===1){if(typeof getSwNews==='function')getSwNews();}});};
$(document).on('submit','.sx_reply_form',function(e){e.preventDefault();var input=$(this).find('input').first();sxReply($(this).attr('data-id'),input);});

function rwOwners(){
 var ids=[];
 $('.rw-story-user[data-user][data-has-stories="1"]').each(function(){
  var id=Number($(this).attr('data-user'));
  if(id && ids.indexOf(id)===-1)ids.push(id);
 });
 return ids;
}
function rwRefreshStoryStrip(html){
 if(!html)return;
 var $new=$(html),$old=$('#rw_story_strip_container');
 if($old.length)$old.replaceWith($new);
 else $('.rw-wall-root > .pad20').prepend($new);
}
function rwOpenStoryComposer(){
 var $target=$('#rw_story_text');
 if(!$target.length){$target=$('#rw_story_file');}
 if($target.length){
  var node=$target.get(0);
  if(node.scrollIntoView)node.scrollIntoView({behavior:'smooth',block:'center'});
  if(node.tagName==='TEXTAREA')node.focus();
  else $target.closest('.rw-story-compose').addClass('rw-compose-highlight');
 } else if(typeof callError==='function')callError('النشر غير متاح لرتبتك أو أن القصص معطلة.');
}
window.rwOpenStoryComposer=rwOpenStoryComposer;
window.rwOpenOrComposeOwnStories=function(button){
 var $button=$(button),has=Number($button.attr('data-has-stories'))===1;
 if(has)window.rwOpenStories(Number($button.attr('data-user')));
 else rwOpenStoryComposer();
};
function rwFormatTime(ts){try{return new Date(ts*1000).toLocaleString('ar',{month:'short',day:'numeric',hour:'2-digit',minute:'2-digit'});}catch(e){return '';}}
function rwEscape(text){return $('<div>').text(text||'').html();}
function rwMarkStorySeen(item){
 if(!item||rwStories.archive)return;
 var id=Number(item.id);if(!id||rwSeenMarked[id])return;
 rwSeenMarked[id]=true;
 rwPost({rw_action:'story_view',id:id},function(r){
  if(r&&r.ok){
   if(Number(item.user_id)!==Number(window.rwUserId)){item.views=Number(item.views||0)+1;if(rwStories.views_enabled&&rwStories.show_viewers)$('#rw_story_views').text(item.views+' مشاهدة');}
   if(r.story_strip)rwRefreshStoryStrip(r.story_strip);
  }else if(r&&!r.ok)delete rwSeenMarked[id];
 });
}
function rwClearStoryTimer(){if(rwAutoTimer){clearTimeout(rwAutoTimer);rwAutoTimer=null;}rwAutoEnd=0;rwAutoRemaining=0;rwPausedMedia=null;rwStoryPaused=false;$('.rw-story-viewer').removeClass('rw-is-paused');}
function rwStartStoryTimer(seconds){
 rwClearStoryTimer();var ms=Math.max(1000,Number(seconds||5)*1000);rwAutoRemaining=ms;rwAutoEnd=Date.now()+ms;
 var bar=$('#rw_story_progress span.active i');bar.css({'animation':'none','width':'0%'});if(bar.length){bar[0].offsetWidth;bar.css({'animation':'rwStoryFill '+ms+'ms linear forwards','animationPlayState':'running'});}
 rwAutoTimer=setTimeout(function(){rwAutoTimer=null;rwMoveStory(1);},ms);
}
function rwPauseCurrentStory(){
 if(rwStoryPaused)return;rwStoryPaused=true;$('.rw-story-viewer').addClass('rw-is-paused');
 if(rwAutoTimer){rwAutoRemaining=Math.max(0,rwAutoEnd-Date.now());clearTimeout(rwAutoTimer);rwAutoTimer=null;$('#rw_story_progress span.active i').css('animation-play-state','paused');}
 var media=$('#rw_story_body').find('video,audio').get(0);if(media&&!media.paused){rwPausedMedia=media;media.pause();}
}
function rwResumeCurrentStory(){
 if(!rwStoryPaused)return;rwStoryPaused=false;$('.rw-story-viewer').removeClass('rw-is-paused');
 if(rwAutoRemaining>0){var ms=rwAutoRemaining;rwAutoEnd=Date.now()+ms;$('#rw_story_progress span.active i').css('animation-play-state','running');rwAutoTimer=setTimeout(function(){rwAutoTimer=null;rwMoveStory(1);},ms);}
 if(rwPausedMedia){var m=rwPausedMedia;rwPausedMedia=null;var play=m.play();if(play&&play.catch)play.catch(function(){});}
}
function rwStoryLinkify(text,allowExternal,allowYoutube){
 var raw=String(text||''),pattern=/(?:[a-z][a-z0-9+.-]*:[^\s<>"']+|(?:(?:www\.)?(?:(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}|(?:[0-9]{1,3}\.){3}[0-9]{1,3})(?::[0-9]{1,5})?(?:\/[^\s<>"']*)?(?:\?[^\s<>"']*)?(?:#[^\s<>"']*)?))/gi,last=0,out='',m;
 while((m=pattern.exec(raw))!==null){if(raw.charAt(m.index-1)==='@')continue;out+=rwEscape(raw.slice(last,m.index));var url=m[0],clean=url.replace(/[.,!?;:،؛)\]}]+$/,''),suffix=url.slice(clean.length),href=/^[a-z][a-z0-9+.-]*:/i.test(clean)?clean:'https://'+clean,host='',safeYoutube=false,safeHttp=false;try{var parsed=new URL(href);host=parsed.hostname.toLowerCase();safeHttp=(parsed.protocol==='https:'||parsed.protocol==='http:')&&!parsed.username&&!parsed.password&&!!host;safeYoutube=safeHttp&&['youtube.com','www.youtube.com','m.youtube.com','music.youtube.com','youtu.be','www.youtu.be'].indexOf(host)!==-1;}catch(e){}
  if(safeYoutube&&allowYoutube){out+='<a class="rw-youtube-link" href="'+rwEscape(href)+'" target="_blank" rel="noopener noreferrer">'+rwEscape(clean)+'</a>'+rwEscape(suffix);}
  else if(safeHttp&&!safeYoutube&&allowExternal){out+='<a class="rw-external-link" href="'+rwEscape(href)+'" target="_blank" rel="noopener noreferrer nofollow">'+rwEscape(clean)+'</a>'+rwEscape(suffix);}
  else{out+='<span class="rw-blocked-link">'+(safeYoutube?'رابط YouTube غير متاح لهذه الرتبة':'رابط خارجي محظور حسب صلاحيات الإضافة')+'</span>'+rwEscape(suffix);}
  last=m.index+url.length;
 }
 out+=rwEscape(raw.slice(last));return out.replace(/\n/g,'<br>');
}
function rwRenderStory(){
 var item=rwStories[rwStoryIndex];if(!item)return;rwClearStoryTimer();
 $('#rw_story_username').text(rwStories.user.name);$('#rw_story_avatar').attr('src',rwStories.user.avatar);$('#rw_story_time').text(rwFormatTime(item.created_at));
 var media=item.media||[],html='',hasVisual=false,mediaKind='';
 if(media.length){
  media.forEach(function(m){
   mediaKind=m.kind;hasVisual=true;
   if(m.kind==='image')html+='<div class="rw-story-stage"><img class="rw-story-image" src="'+rwEscape(m.url)+'" alt=""></div>';
   else if(m.kind==='video')html+='<div class="rw-story-stage"><video class="rw-story-video" controls autoplay playsinline preload="metadata" src="'+rwEscape(m.url)+'"></video></div>';
   else if(m.kind==='audio')html+='<div class="rw-story-audio-stage"><audio controls autoplay preload="metadata" src="'+rwEscape(m.url)+'"></audio></div>';
  });
 }
 if(item.text){
  var longText=Array.from(String(item.text)).length>180;
  if(hasVisual)html+='<div class="rw-story-caption'+(longText?' rw-caption-clamped':'')+'"><div class="rw-story-caption-text">'+rwStoryLinkify(item.text,Number(item.external_links_allowed)===1,Number(item.youtube_links_allowed)===1)+'</div>'+(longText?'<button type="button" class="rw-story-more" onclick="rwToggleStoryCaption(this)">المزيد</button>':'')+'</div>';
  else html='<div class="rw-story-text-only'+(longText?' rw-caption-clamped':'')+'"><div class="rw-story-caption-text">'+rwStoryLinkify(item.text,Number(item.external_links_allowed)===1,Number(item.youtube_links_allowed)===1)+'</div>'+(longText?'<button type="button" class="rw-story-more" onclick="rwToggleStoryCaption(this)">المزيد</button>':'')+'</div>';
 }
 if(!html)html='<div class="rw-story-text-only">قصة بدون نص</div>';
 $('#rw_story_body').html(html);
 var progress='';rwStories.forEach(function(x,i){progress+='<span class="'+(i<rwStoryIndex?'done':(i===rwStoryIndex?'active':''))+'"><i></i></span>';});$('#rw_story_progress').html(progress);
 var rem=Math.max(0,Number(item.expires_at)-Math.floor(Date.now()/1000));
 var exp=rwStories.archive?'من الأرشيف':('متبقي '+Math.floor(rem/3600)+' س '+Math.floor((rem%3600)/60)+' د');$('#rw_story_expiry').text(exp);
 var isOwner=Number(item.user_id)===Number(window.rwUserId);
 var canShowViewers=!!rwStories.views_enabled && !!rwStories.show_viewers;
 $('#rw_story_views').text(canShowViewers ? (Number(item.views||0)+' مشاهدة') : 'عرض التفاعلات');
 $('#rw_story_viewers_btn').find('i').toggleClass('fa-eye',canShowViewers).toggleClass('fa-heart',!canShowViewers);
 $('#rw_story_viewers_btn').toggle(isOwner && (canShowViewers || !!rwStories.reactions_enabled)).toggleClass('rw-viewers-eye',true);
 $('#rw_story_delete').toggle(isOwner);$('#rw_story_pin').toggle(!!rwStories.can_pin&&!rwStories.archive).text(Number(item.pinned)===1?'إلغاء التثبيت':'تثبيت القصة');
 $('.rw-story-reactions').toggle(!rwStories.archive && !!rwStories.reactions_enabled && (isOwner || !!rwStories.story_react_allowed));
 var $reply=$('#rw_story_reply_area'),$form=$reply.find('.rw-story-reply-form'),$notice=$('#rw_story_reply_notice');
 if(!isOwner&&!rwStories.archive&&rwStories.private_reply_enabled){
  $reply.show();
  if(rwStories.private_reply_allowed){$notice.text('سيصل ردك إلى الرسائل الخاصة مع معاينة مصغرة للقصة.').css('color','#bbf7d0');$form.show();}
  else{$notice.text(rwStories.private_reply_message||'لا يمكن الرد على هذه القصة عبر الخاص.').css('color','#fca5a5');$form.hide();}
 } else {$reply.hide();$form.show();$notice.text('');}
 $('#rw_story_reply_text').val('');$('#rw_story_reply_status').text('');
 if(mediaKind==='video'||mediaKind==='audio'){
  $('#rw_story_body').find('video,audio').off('ended.rwStory').on('ended.rwStory',function(){rwMoveStory(1);});
 }else if(mediaKind==='image')rwStartStoryTimer(Number(rwStories.story_image_seconds||window.rwImageStorySeconds||5));
 else rwStartStoryTimer(Number(rwStories.story_text_seconds||window.rwTextStorySeconds||10));
}
window.rwToggleStoryCaption=function(button){var box=$(button).closest('.rw-story-caption,.rw-story-text-only'),expanding=box.hasClass('rw-caption-clamped');if(expanding)rwPauseCurrentStory();box.toggleClass('rw-caption-clamped');$(button).text(box.hasClass('rw-caption-clamped')?'المزيد':'أقل');if(!expanding)rwResumeCurrentStory();};
function rwLoadStories(owner,keepOrder,startAtEnd){
 rwStoryOwner=Number(owner);
 rwPost({rw_action:'get_stories',user_id:rwStoryOwner},function(r){
  if(!r||!r.ok){if(r&&r.error)alert(r.error);return;}
  rwStories=r.items||[];rwStories.user=r.user||{name:'',avatar:''};rwStories.archive=false;
  rwStories.views_enabled=!!r.views_enabled;rwStories.reactions_enabled=!!r.reactions_enabled;rwStories.show_viewers=!!r.show_viewers;rwStories.can_pin=!!r.can_pin;
  rwStories.story_text_seconds=Number(r.story_text_seconds||window.rwTextStorySeconds||10);rwStories.story_image_seconds=Number(r.story_image_seconds||window.rwImageStorySeconds||5);
  rwStories.private_reply_enabled=!!r.private_reply_enabled;rwStories.private_reply_allowed=!!r.private_reply_allowed;rwStories.private_reply_message=r.private_reply_message||'';rwStories.story_react_allowed=!!r.story_react_allowed;
  if(r.story_strip)rwRefreshStoryStrip(r.story_strip);
  rwStoryIndex=startAtEnd?Math.max(0,rwStories.length-1):0;$('#rw_story_modal').css('display','flex');rwRenderStory();
 });
}
window.rwOpenStories=function(owner){
 var owners=rwOwners();
 if(owners.indexOf(Number(owner))===-1)owners.push(Number(owner));
 rwStoryOwnerOrder=owners.slice();
 rwLoadStories(owner,false);
};
window.rwOpenArchive=function(){
 rwStoryOwner=Number(window.rwUserId);
 rwPost({rw_action:'get_stories',user_id:window.rwUserId,archive:1},function(r){
  if(!r||!r.ok){alert((r&&r.error)||'الأرشيف فارغ.');return;}
  rwStories=r.items||[];rwStories.user=r.user||{name:'',avatar:''};rwStories.archive=true;
  rwStories.views_enabled=!!r.views_enabled;rwStories.reactions_enabled=!!r.reactions_enabled;rwStories.show_viewers=!!r.show_viewers;rwStories.can_pin=false;
  rwStories.private_reply_enabled=false;rwStories.private_reply_allowed=false;rwStoryIndex=0;$('#rw_story_modal').css('display','flex');rwRenderStory();
 });
};
window.rwCloseStories=function(){rwClearStoryTimer();$('#rw_story_modal').hide();$('#rw_story_body').find('video,audio').each(function(){this.pause();});};
window.rwMoveStory=function(delta){
 if(delta>0 && rwStories.length && rwStories[rwStoryIndex])rwMarkStorySeen(rwStories[rwStoryIndex]);
 rwClearStoryTimer();var ni=rwStoryIndex+delta;
 if(ni>=0&&ni<rwStories.length){rwStoryIndex=ni;rwRenderStory();return;}
 if(rwStories.archive){if(delta>0)rwCloseStories();return;}
 var owners=rwStoryOwnerOrder.length?rwStoryOwnerOrder:rwOwners(),at=owners.indexOf(rwStoryOwner),next=owners[at+delta];
 if(next)rwLoadStories(next,true,delta<0);else if(delta>0)rwCloseStories();
};
window.rwSendStoryReply=function(){
 var item=rwStories[rwStoryIndex],$input=$('#rw_story_reply_text'),$status=$('#rw_story_reply_status'),$button=$('#rw_story_reply_area').find('button');
 if(!item||!rwStories.private_reply_allowed)return;
 var value=($input.val()||'').trim();if(!value){$status.text('اكتب ردك أولاً.');return;}
 $button.prop('disabled',true);$status.text('جارٍ إرسال الرد إلى الخاص...');
 rwPost({rw_action:'story_reply',id:item.id,reply:value},function(r){
  $button.prop('disabled',false);
  if(r&&r.ok){$input.val('');$status.text(r.message||'تم إرسال ردك إلى الخاص مع معاينة القصة.').css('color','#bbf7d0');}
  else{$status.text((r&&r.error)||'تعذر إرسال الرد.').css('color','#fca5a5');}
 });
};
window.rwReactStory=function(kind){var item=rwStories[rwStoryIndex];if(!item)return;if(Number(item.user_id)!==Number(window.rwUserId)&&!rwStories.story_react_allowed){$('#rw_story_reply_status').text('لا يمكن التفاعل لأن إعدادات الخاص لدى صاحب القصة لا تسمح بذلك.');return;}rwPost({rw_action:'story_react',id:item.id,reaction:kind},function(r){if(!r||!r.ok)alert((r&&r.error)||'تعذر تسجيل التفاعل.');});};
window.rwShowViewers=function(){var item=rwStories[rwStoryIndex];if(!item)return;rwPost({rw_action:'story_views',id:item.id},function(r){if(r&&r.ok)showModal('<div class="pad10"><h3>مشاهدو القصة</h3>'+r.html+'</div>',380);else alert((r&&r.error)||'تعذر عرض المشاهدين.');});};
window.rwToggleStoryPin=function(){var item=rwStories[rwStoryIndex];if(!item||!rwStories.can_pin||rwStories.archive)return;var next=Number(item.pinned)===1?0:1;var $btn=$('#rw_story_pin');$btn.prop('disabled',true);rwPost({rw_action:'pin_story',id:item.id,pin:next},function(r){$btn.prop('disabled',false);if(r&&r.ok){item.pinned=Number(r.pinned);$btn.text(item.pinned?'إلغاء التثبيت':'تثبيت القصة');if(r.story_strip)rwRefreshStoryStrip(r.story_strip);}else alert((r&&r.error)||'تعذر تحديث تثبيت القصة.');},function(){$btn.prop('disabled',false);alert('تعذر الاتصال بالخادم.');});};
window.rwDeleteCurrentStory=function(){
 var item=rwStories[rwStoryIndex];if(!item||!confirm('هل تريد حذف هذه القصة؟'))return;
 rwPost({rw_action:'delete_story',id:item.id},function(r){
  if(r&&r.ok){delete rwSeenMarked[Number(item.id)];rwStories.splice(rwStoryIndex,1);if(rwStories.length){rwStoryIndex=Math.min(rwStoryIndex,rwStories.length-1);rwRenderStory();}else rwCloseStories();if(typeof getSwNews==='function')getSwNews();}
  else alert((r&&r.error)||'تعذر حذف القصة.');
 });
};
$(document).on('pointerdown.rwStoryPause','.rw-story-viewer',function(e){
 var $target=$(e.target);if($target.closest('button,a,input').length)return;
 if($target.is('audio'))return;
 if($target.is('video')){var rect=e.target.getBoundingClientRect();if(e.clientY>rect.bottom-48)return;if(rwPressHoldTimer)clearTimeout(rwPressHoldTimer);rwPressHoldTimer=window.setTimeout(function(){rwPressHoldTimer=null;rwPauseCurrentStory();},250);return;}
 rwPauseCurrentStory();
});
$(document).on('pointerup.rwStoryPause pointercancel.rwStoryPause pointerleave.rwStoryPause','.rw-story-viewer',function(){if(rwPressHoldTimer){clearTimeout(rwPressHoldTimer);rwPressHoldTimer=null;}rwResumeCurrentStory();});
$(document).on('keydown',function(e){if($('#rw_story_modal').is(':visible')){if(e.key==='Escape')rwCloseStories();if(e.key==='ArrowRight')rwMoveStory(1);if(e.key==='ArrowLeft')rwMoveStory(-1);}});
})(jQuery);
