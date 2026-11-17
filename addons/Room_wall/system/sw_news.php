<?php
$load_addons='Room_wall';
require_once('../../../system/config_addons.php');
if(empty($data)||!boomAllow($addons['addons_access'])) die();
rwEnsureSchema();
$s=rwSettings();
$expireNow=time();
rwCleanExpiredPosts();
if(!empty($s['archive_enabled'])) $mysqli->query("UPDATE boom_gen_story SET archived=1 WHERE expires_at<='$expireNow' AND archived=0");
else $mysqli->query("UPDATE boom_gen_story SET archived=2 WHERE expires_at<='$expireNow' AND archived=0");
$content='';$count=0;$max_news=10;
$get=$mysqli->query("SELECT g.*,u.*, (SELECT COUNT(*) FROM boom_gen_reply WHERE parent_id=g.id) AS reply_count,
 (SELECT like_type FROM boom_gen_like WHERE uid='".(int)$data['user_id']."' AND like_post=g.id LIMIT 1) AS liked
 FROM boom_gen g INNER JOIN boom_users u ON u.user_id=g.news_poster
 WHERE g.expires_at=0 OR g.expires_at>'$expireNow'
 ORDER BY g.static DESC,g.news_date DESC,g.id DESC LIMIT $max_news");
if($get){while($row=$get->fetch_assoc()){$content.=genTemplate('element/gen',$row);}}
$countQ=$mysqli->query("SELECT COUNT(*) AS total FROM boom_gen WHERE expires_at=0 OR expires_at>'$expireNow'");if($countQ&&$c=$countQ->fetch_assoc())$count=(int)$c['total'];
$stories=rwStoriesStrip();
$mysqli->query("UPDATE boom_users SET user_gen='".time()."' WHERE user_id='".(int)$data['user_id']."'");
if(function_exists('redisUpdateUser')) redisUpdateUser((int)$data['user_id']);
ob_start();
?>
<div class="left_keep rw-wall-root" style="--rw-story-circle-size: <?php echo max(40,min(100,(int)$s['story_circle_size'])); ?>px;">
 <div class="pad20">
  <?php echo $stories; ?>
  <div class="rw-compose-tabs">
   <?php if(rwCanStory()){ ?>
   <div class="rw-story-compose">
    <div class="rw-compose-title"><i class="fa fa-circle-plus"></i> إضافة قصة</div>
    <?php if(!empty($s['allow_story_text'])){ ?><textarea id="rw_story_text" data-rw-max="<?php echo (int)$s['max_story_chars']; ?>" class="full_textarea" placeholder="اكتب قصة نصية أو أضف نصاً مع الصورة والفيديو والصوت..."></textarea><div class="rw-char-counter"><span id="rw_story_char_count">0</span> / <?php echo (int)$s['max_story_chars']; ?> حرف</div><?php } ?>
    <div class="rw-compose-tools">
     <button type="button" class="small_button" onclick="rwOpenArchive()"><i class="fa fa-archive"></i> أرشيفي</button>
     <label class="rw-file-button"><i class="fa fa-paperclip"></i> صورة / فيديو / صوت<input id="rw_story_file" type="file" accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm,video/ogg,audio/mpeg,audio/ogg,audio/wav,audio/webm,audio/mp4,audio/aac"></label>
     <select id="rw_story_privacy" class="rw-privacy"><?php if(!empty($s['allow_all_privacy'])){ ?><option value="all">الجميع</option><?php } ?><?php if(!empty($s['allow_friends_privacy'])){ ?><option value="friends" <?php echo empty($s['allow_all_privacy'])?'selected':''; ?>>الأصدقاء فقط</option><?php } ?><?php if(empty($s['allow_all_privacy']) && empty($s['allow_friends_privacy'])){ ?><option value="" disabled selected>النشر متوقف</option><?php } ?></select>
     <button type="button" class="theme_btn small_button" onclick="rwCreateStory()">نشر القصة</button>
    </div>
    <?php echo rwGoldPriceLine('story'); ?>
    <div id="rw_story_upload_status" class="text_xsmall sub_date"></div>
    <div class="rw-record-controls"><button type="button" class="small_button" onclick="rwToggleRecord('story')" id="rw_story_record"><i class="fa fa-microphone"></i> تسجيل صوت</button><button type="button" class="small_button hidden" onclick="rwStopRecord('story')" id="rw_story_record_stop">إيقاف التسجيل</button></div>
   </div>
   <?php } ?>
  </div>
  <div class="rw-section-title"><strong>اليوميات والمنشورات</strong><span>منشورات عامة على مستوى الشات</span></div>
  <?php if(rwCanPost()){ ?>
  <div class="add_post_container rw-post-composer">
   <div id="add_wall_form">
    <div class="post_input_container">
     <?php if(!empty($s['allow_post_text'])){ ?><textarea id="rw_post_text" data-rw-max="<?php echo (int)$s['max_post_chars']; ?>" onkeyup="textArea(this,60);" spellcheck="false" placeholder="ماذا تريد أن تشارك؟" class="full_textarea"></textarea><?php } ?>
     <div class="rw-char-counter"><span id="rw_post_char_count">0</span> / <?php echo (int)$s['max_post_chars']; ?> حرف</div>
     <div class="rw-compose-tools">
      <label class="rw-file-button"><i class="fa fa-paperclip"></i> إرفاق صورة أو فيديو أو صوت<input id="rw_post_file" type="file" accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm,video/ogg,audio/mpeg,audio/ogg,audio/wav,audio/webm,audio/mp4,audio/aac"></label>
      <button type="button" class="small_button" onclick="rwToggleRecord('post')"><i class="fa fa-microphone"></i> تسجيل صوت</button>
      <button type="button" class="small_button hidden" id="rw_post_record_stop" onclick="rwStopRecord('post')">إيقاف التسجيل</button>
      <button type="button" class="theme_btn small_button" onclick="rwCreatePost()"><i class="fa fa-paper-plane"></i> نشر</button>
     </div>
     <?php echo rwGoldPriceLine('post'); ?>
     <div id="rw_post_upload_status" class="text_xsmall sub_date"></div>
    </div>
   </div>
  </div>
  <?php } ?>
  <div id="container_news"><?php echo $content!==''?$content:emptyZone($lang['no_news']); ?></div>
  <?php if($count>$max_news){ ?><div class="load_more_news centered_element"><button onclick="moreNewsgen(this);" class="theme_btn small_button rounded_button load_more"><i class="fa fa-plus-circle"></i> <?php echo $lang['load_more']; ?></button></div><?php } ?>
 </div>
</div>
<div id="rw_story_modal" class="rw-modal" style="display:none" role="dialog" aria-modal="true">
 <div class="rw-story-viewer">
  <button type="button" class="rw-close" onclick="rwCloseStories()" aria-label="إغلاق">×</button>
  <div id="rw_story_progress" class="rw-story-progress"></div>
  <div class="rw-story-head"><img id="rw_story_avatar" src="" alt=""><div><strong id="rw_story_username"></strong><small id="rw_story_time"></small></div><button type="button" id="rw_story_pin" class="rw-story-pin" onclick="rwToggleStoryPin()" style="display:none">تثبيت</button><button type="button" id="rw_story_delete" class="rw-story-delete" onclick="rwDeleteCurrentStory()" style="display:none">حذف</button></div>
  <div id="rw_story_body" class="rw-story-body"></div>
  <div class="rw-story-foot"><div class="rw-story-reactions"><button onclick="rwReactStory('like')">👍</button><button onclick="rwReactStory('love')">❤️</button><button onclick="rwReactStory('laugh')">😂</button><button onclick="rwReactStory('wow')">😮</button><button onclick="rwReactStory('sad')">😢</button></div><div class="rw-story-meta"><button id="rw_story_viewers_btn" onclick="rwShowViewers()" style="display:none"><i class="fa fa-eye"></i> <span id="rw_story_views">0 مشاهدة</span></button><span id="rw_story_expiry"></span></div><div id="rw_story_reply_area" class="rw-story-reply-area" style="display:none"><div id="rw_story_reply_notice" class="rw-story-reply-notice"></div><div class="rw-story-reply-form"><input id="rw_story_reply_text" type="text" maxlength="1000" placeholder="رد على القصة برسالة خاصة..."><button type="button" onclick="rwSendStoryReply()">إرسال</button></div><small id="rw_story_reply_status"></small></div></div>
  <button type="button" class="rw-story-prev" onclick="rwMoveStory(-1)" aria-label="السابق">‹</button><button type="button" class="rw-story-next" onclick="rwMoveStory(1)" aria-label="التالي">›</button>
 </div>
</div>
<?php
$res['content']=ob_get_clean();$res['title']='اليوميات والقصص';$res['rwUserId']=(int)$data['user_id'];$res['max_video_seconds']=(int)$s['max_video_seconds'];$res['max_audio_seconds']=(int)$s['max_audio_seconds'];$res['max_image_mb']=(int)$s['max_image_mb'];$res['max_video_mb']=(int)$s['max_video_mb'];$res['max_audio_mb']=(int)$s['max_audio_mb'];$res['max_post_chars']=(int)$s['max_post_chars'];$res['max_story_chars']=(int)$s['max_story_chars'];$res['story_text_seconds']=(int)$s['story_text_seconds'];$res['story_image_seconds']=(int)$s['story_image_seconds'];echo boomCode(1,$res);
?>
