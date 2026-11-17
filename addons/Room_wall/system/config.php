<?php
$load_addons = 'Room_wall';
require_once('../../../system/config_addons.php');
require_once __DIR__ . '/../../../system/addon_menu_labels.php';
if (!canManageAddons()) die();
rwEnsureSchema();
$s = rwSettings();
function rwSettingSelect($id, $value, $min=1, $max=100){
    $v=(int)$value; $html='<select id="'.$id.'">';
    for($i=$min;$i<=$max;$i++) $html.='<option value="'.$i.'"'.($i===$v?' selected':'').'>'.$i.'</option>';
    return $html.'</select>';
}
function rwToggle($id,$label,$value){
    return '<div class="setting_element"><p class="label">'.htmlspecialchars($label,ENT_QUOTES,'UTF-8').'</p><select id="'.$id.'"><option value="1"'.((int)$value===1?' selected':'').'>مفعل</option><option value="0"'.((int)$value===0?' selected':'').'>معطل</option></select></div>';
}
function rwOptionsSelect($id,$value,$options){
    $html='<select id="'.htmlspecialchars($id,ENT_QUOTES,'UTF-8').'">';
    foreach($options as $v=>$label){$html.='<option value="'.(int)$v.'"'.((int)$v===(int)$value?' selected':'').'>'.htmlspecialchars((string)$label,ENT_QUOTES,'UTF-8').'</option>';}
    return $html.'</select>';
}
function rwRangeOptions($start,$end,$step=1,$suffix=''){
    $out=array(); for($i=$start;$i<=$end;$i+=$step)$out[$i]=$i.' '.$suffix; return $out;
}
function rwAudioDurationOptions($selected){
    $out=array(30=>'30 ثانية');
    for($m=1;$m<=300;$m++)$out[$m*60]=$m.' دقيقة'.($m===1?'':'');
    return rwOptionsSelect('rw_max_audio_seconds',$selected,$out);
}
?>
<?php echo elementTitle($addons['addons'], 'loadL(\'admin/setting_addons.php\');'); ?>
<?php echo addonMenuLabelSettingsBlock('Room_wall', 'اليوميات والقصص', 'يظهر اسم الإضافة في رأس اليوميات/اختصار الشريط الجانبي.'); ?>
<div class="page_full">
  <div class="page_element">
    <div class="pad15">
      <h3 class="bpad10">إعدادات اليوميات والقصص</h3>
      <div class="rw-admin-grid">
        <?php echo rwToggle('rw_posts_enabled','تفعيل اليوميات',$s['posts_enabled']); ?>
        <?php echo rwToggle('rw_stories_enabled','تفعيل القصص',$s['stories_enabled']); ?>
        <?php echo rwToggle('rw_allow_post_text','السماح بالنصوص في اليوميات',$s['allow_post_text']); ?>
        <?php echo rwToggle('rw_allow_post_images','السماح بالصور في اليوميات',$s['allow_post_images']); ?>
        <?php echo rwToggle('rw_allow_post_videos','السماح بالفيديو في اليوميات',$s['allow_post_videos']); ?>
        <?php echo rwToggle('rw_allow_post_audio','السماح بالصوت في اليوميات',$s['allow_post_audio']); ?>
        <?php echo rwToggle('rw_allow_story_text','السماح بالنصوص في القصص',$s['allow_story_text']); ?>
        <?php echo rwToggle('rw_allow_story_images','السماح بالصور في القصص',$s['allow_story_images']); ?>
        <?php echo rwToggle('rw_allow_story_videos','السماح بالفيديو في القصص',$s['allow_story_videos']); ?>
        <?php echo rwToggle('rw_allow_story_audio','السماح بالصوت في القصص',$s['allow_story_audio']); ?>
        <?php echo rwToggle('rw_comments_enabled','السماح بالتعليقات',$s['comments_enabled']); ?>
        <?php echo rwToggle('rw_likes_enabled','السماح بالإعجابات',$s['likes_enabled']); ?>
        <?php echo rwToggle('rw_reactions_enabled','السماح بتفاعلات القصص',$s['reactions_enabled']); ?>
        <?php echo rwToggle('rw_story_replies_enabled','السماح بالرد على القصص عبر الخاص',$s['story_replies_enabled']); ?>
        <?php echo rwToggle('rw_views_enabled','تسجيل المشاهدات',$s['views_enabled']); ?>
        <?php echo rwToggle('rw_show_viewers','إظهار قائمة المشاهدين لصاحب القصة',$s['show_viewers']); ?>
        <?php echo rwToggle('rw_archive_enabled','أرشفة قصص صاحب الحساب بعد انتهائها',$s['archive_enabled']); ?>
        <?php echo rwToggle('rw_allow_all_privacy','السماح بخيار الجميع',$s['allow_all_privacy']); ?>
        <?php echo rwToggle('rw_allow_friends_privacy','السماح بخيار الأصدقاء فقط',$s['allow_friends_privacy']); ?>
      </div>
      <h3 class="bpad10 tpad15">صلاحيات الرتب</h3>
      <div class="rw-admin-grid">
        <div class="setting_element"><p class="label">أدنى رتبة لنشر اليوميات</p><?php echo rwSettingSelect('rw_post_rank',$s['post_rank']); ?></div>
        <div class="setting_element"><p class="label">أدنى رتبة لنشر القصص</p><?php echo rwSettingSelect('rw_story_rank',$s['story_rank']); ?></div>
        <div class="setting_element"><p class="label">أدنى رتبة لمشاهدة القصص</p><?php echo rwSettingSelect('rw_view_story_rank',$s['view_story_rank']); ?></div>
        <div class="setting_element"><p class="label">أدنى رتبة لتثبيت القصص</p><?php echo rwSettingSelect('rw_story_pin_rank',$s['story_pin_rank']); ?></div>
        <div class="setting_element"><p class="label">أدنى رتبة للتعليق</p><?php echo rwSettingSelect('rw_comment_rank',$s['comment_rank']); ?></div>
        <div class="setting_element"><p class="label">أدنى رتبة للتفاعل</p><?php echo rwSettingSelect('rw_like_rank',$s['like_rank']); ?></div>
        <h4 class="bpad5">صلاحيات الروابط داخل اليوميات والقصص فقط</h4>
        <div class="setting_element"><p class="label">أدنى رتبة لوضع رابط YouTube في اليوميات</p><?php echo rwSettingSelect('rw_post_link_rank',$s['post_link_rank']); ?></div>
        <div class="setting_element"><p class="label">أدنى رتبة لوضع رابط خارجي في اليوميات</p><?php echo rwSettingSelect('rw_post_external_link_rank',$s['post_external_link_rank']); ?></div>
        <div class="setting_element"><p class="label">أدنى رتبة لوضع رابط YouTube في القصص</p><?php echo rwSettingSelect('rw_story_link_rank',$s['story_link_rank']); ?></div>
        <div class="setting_element"><p class="label">أدنى رتبة لوضع رابط خارجي في القصص</p><?php echo rwSettingSelect('rw_story_external_link_rank',$s['story_external_link_rank']); ?></div>
        <p class="text_small sub_date">سياسة الروابط هذه تخص اليوميات والقصص فقط ولا تغيّر رسائل الرومات أو أي جزء أساسي من CodyChat. YouTube له صلاحية مستقلة، والروابط الخارجية HTTP/HTTPS لها صلاحية مستقلة، والافتراضي أن الروابط الخارجية للرتبة 100 فأعلى.</p>
        <div class="setting_element"><p class="label">أدنى رتبة لتعديل المنشور الشخصي</p><?php echo rwSettingSelect('rw_edit_own_rank',$s['edit_own_rank']); ?></div>
        <div class="setting_element"><p class="label">أدنى رتبة لتعديل منشورات الآخرين</p><?php echo rwSettingSelect('rw_edit_others_rank',$s['edit_others_rank']); ?></div>
        <div class="setting_element"><p class="label">أدنى رتبة لحذف المنشورات الشخصية</p><?php echo rwSettingSelect('rw_delete_own_rank',$s['delete_own_rank']); ?></div>
        <div class="setting_element"><p class="label">أدنى رتبة لحذف منشورات الآخرين</p><?php echo rwSettingSelect('rw_delete_others_rank',$s['delete_others_rank']); ?></div>
        <div class="setting_element"><p class="label">أدنى رتبة لحذف تعليقات الآخرين</p><?php echo rwSettingSelect('rw_delete_comments_rank',$s['delete_comments_rank']); ?></div>
      </div>
      <h3 class="bpad10 tpad15">حدود الرفع والنصوص ومدد العرض</h3>
      <p class="text_small sub_date">تُطبّق الحدود التالية على اليوميات والقصص معًا، ويجري التحقق منها في الخادم. القيم الافتراضية: صورة 1MB، فيديو 1MB، وصوت 30 ثانية. حد الصوت بالحجم مستقل ويمكن تغييره. لا تتجاوز الإضافة حدود PHP في الاستضافة.</p>
      <p class="text_xsmall sub_date">حدود PHP الحالية: upload_max_filesize = <?php echo htmlspecialchars((string)ini_get('upload_max_filesize'),ENT_QUOTES,'UTF-8'); ?>، post_max_size = <?php echo htmlspecialchars((string)ini_get('post_max_size'),ENT_QUOTES,'UTF-8'); ?>. يلزم توفر ffprobe على الخادم لفرض مدة الصوت والفيديو من الخادم.</p>
      <div class="rw-admin-grid">
        <div class="setting_element"><p class="label">أقصى حجم للصورة</p><?php echo rwOptionsSelect('rw_max_image_mb',$s['max_image_mb'],rwRangeOptions(1,100,1,'MB')); ?></div>
        <div class="setting_element"><p class="label">أقصى حجم للفيديو</p><?php echo rwOptionsSelect('rw_max_video_mb',$s['max_video_mb'],rwRangeOptions(1,100,1,'MB')); ?></div>
        <div class="setting_element"><p class="label">أقصى حجم للصوت</p><?php echo rwOptionsSelect('rw_max_audio_mb',$s['max_audio_mb'],rwRangeOptions(1,100,1,'MB')); ?></div>
        <div class="setting_element"><p class="label">أقصى مدة للصوت (من 30 ثانية إلى 5 ساعات)</p><?php echo rwAudioDurationOptions($s['max_audio_seconds']); ?></div>
        <div class="setting_element"><p class="label">أقصى مدة للفيديو (ثانية)</p><input id="rw_max_video_seconds" type="number" min="5" max="3600" value="<?php echo (int)$s['max_video_seconds']; ?>"></div>
        <div class="setting_element"><p class="label">عدد أحرف منشور اليوميات</p><?php echo rwOptionsSelect('rw_max_post_chars',$s['max_post_chars'],rwRangeOptions(100,20000,100,'حرف')); ?></div>
        <div class="setting_element"><p class="label">عدد أحرف القصة</p><?php echo rwOptionsSelect('rw_max_story_chars',$s['max_story_chars'],rwRangeOptions(100,20000,100,'حرف')); ?></div>
        <div class="setting_element"><p class="label">مدة عرض القصة النصية (ثانية)</p><?php echo rwOptionsSelect('rw_story_text_seconds',$s['story_text_seconds'],rwRangeOptions(1,60,1,'ثانية')); ?></div>
        <div class="setting_element"><p class="label">مدة عرض صورة القصة (ثانية)</p><?php echo rwOptionsSelect('rw_story_image_seconds',$s['story_image_seconds'],rwRangeOptions(1,60,1,'ثانية')); ?></div>
        <div class="setting_element"><p class="label">مدة بقاء القصة (بالساعات)</p><input id="rw_story_hours" type="number" min="1" max="168" value="<?php echo (int)$s['story_hours']; ?>"><small>الافتراضي 24 ساعة.</small></div>
        <div class="setting_element"><p class="label">انتهاء المنشورات بعد (أيام)</p><input id="rw_post_expiry_days" type="number" min="0" max="365" value="<?php echo (int)$s['post_expiry_days']; ?>"><small>0 = لا تنتهي المنشورات تلقائيًا.</small></div>
        <div class="setting_element"><p class="label">الحد الأقصى للمنشورات في الساعة لكل عضو</p><input id="rw_max_posts_hour" type="number" min="1" max="100" value="<?php echo (int)$s['max_posts_hour']; ?>"></div>
        <div class="setting_element rw-circle-size-control"><p class="label">حجم دوائر القصص</p><input id="rw_story_circle_size" type="range" min="40" max="100" step="2" value="<?php echo (int)$s['story_circle_size']; ?>"><div class="rw-range-caption"><span>صغير</span><strong id="rw_story_circle_size_value"><?php echo (int)$s['story_circle_size']; ?> px</strong><span>كبير</span></div></div>
      </div>
      <h3 class="bpad10 tpad15">نظام رسوم الذهب للنشر</h3>
      <p class="text_small sub_date">الخصم معطل افتراضيًا. فعّله بعد تحديد الأسعار والتأكد من تفعيل محفظة الذهب في CodyChat. تُحسب رسوم نوع المحتوى المرفق فقط، والنص المصاحب له مشمول.</p>
      <div class="rw-admin-grid">
        <?php echo rwToggle('rw_gold_charges_enabled','تفعيل خصم الذهب عند النشر',$s['gold_charges_enabled']); ?>
        <?php foreach(array('post_text_gold'=>'رسوم نص اليوميات','post_audio_gold'=>'رسوم صوت اليوميات','post_image_gold'=>'رسوم صورة اليوميات','post_video_gold'=>'رسوم فيديو اليوميات','story_text_gold'=>'رسوم نص القصة','story_audio_gold'=>'رسوم صوت القصة','story_image_gold'=>'رسوم صورة القصة','story_video_gold'=>'رسوم فيديو القصة') as $goldKey=>$goldLabel){ ?><div class="setting_element"><p class="label"><?php echo $goldLabel; ?></p><input id="rw_<?php echo $goldKey; ?>" type="number" min="0" max="100000" step="1" value="<?php echo (int)$s[$goldKey]; ?>"><small>ذهب · 0 = مجاني</small></div><?php } ?>
      </div>
      <div class="tpad15">
        <button class="reg_button theme_btn" type="button" onclick="rwSaveAdminSettings()"><i class="fa fa-save"></i> حفظ الإعدادات</button>
        <span id="rw_admin_status" class="text_small"></span>
      </div>
      <hr class="tpad15 bpad15">
      <h3 class="bpad10">إدارة المنشورات والقصص</h3>
      <div class="rw-admin-grid"><div class="setting_element"><p class="label">ابحث باسم العضو أو رقمه</p><input id="rw_content_query" type="text" maxlength="60" placeholder="اسم العضو أو User ID"></div><div class="setting_element"><p class="label"> </p><button type="button" class="reg_button theme_btn" onclick="rwLoadContent()">بحث وعرض المحتوى</button></div></div>
      <div id="rw_content_results" class="tpad10"></div>
      <hr class="tpad15 bpad15">
      <h3 class="bpad10">استيراد منشورات الرومات إلى اليوميات العامة</h3>
      <p class="text_small">يستورد الرسائل العامة فقط من الرومات المفتوحة، مع اسم الروم الأصلي واسم العضو وتاريخ الرسالة. لا يستورد الخاص أو الرسائل المخفية بالرتبة أو رسائل النظام أو محتوى الغرف المحمية. لا يحذف أي شيء من المصدر، ويمنع التكرار عند إعادة التشغيل.</p>
      <button class="reg_button theme_btn" type="button" id="rw_import_btn" onclick="rwStartImport()"><i class="fa fa-cloud-download"></i> بدء / متابعة الاستيراد</button>
      <div id="rw_import_status" class="text_small tpad10" aria-live="polite"></div>
      <p class="text_xsmall sub_date tpad10">ملاحظة: منشورات الحائط الخاصة بالأعضاء (boom_post) لا تُحوّل تلقائياً إلى منشورات عامة حفاظاً على خصوصيتها.</p>
    </div>
  </div>
</div>
<style>
.rw-admin-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px}.rw-admin-grid .setting_element{padding:10px;background:rgba(128,90,213,.045);border-radius:8px}.rw-admin-grid input,.rw-admin-grid select{width:100%;max-width:100%}#rw_admin_status{margin-inline-start:10px}.rw-admin-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px;margin:6px 0;border-radius:9px;background:rgba(124,58,237,.045);border:1px solid rgba(124,58,237,.10)}.rw-admin-row p{margin:5px 0}.rw-admin-row small{opacity:.68}.rw-admin-row button{flex:0 0 auto}.rw-circle-size-control input[type=range]{padding:0;accent-color:#7c3aed}.rw-range-caption{display:flex;align-items:center;justify-content:space-between;font-size:10px;opacity:.72}.rw-range-caption strong{font-size:12px;opacity:1}.rw-admin-grid input[type=number]{direction:ltr}
</style>
<script>
function rwAdminPost(data,done){data.token=utk;$.ajax({url:'addons/Room_wall/system/action.php',type:'POST',data:data,dataType:'json',success:done,error:function(){ $('#rw_admin_status,#rw_import_status').text('تعذر الاتصال بالخادم.');}});}
function rwSaveAdminSettings(){
 var ids=['posts_enabled','stories_enabled','gold_charges_enabled','allow_post_text','allow_post_images','allow_post_videos','allow_post_audio','allow_story_text','allow_story_images','allow_story_videos','allow_story_audio','comments_enabled','likes_enabled','reactions_enabled','story_replies_enabled','views_enabled','show_viewers','archive_enabled','allow_all_privacy','allow_friends_privacy','post_rank','story_rank','view_story_rank','comment_rank','like_rank','post_link_rank','post_external_link_rank','story_link_rank','story_external_link_rank','story_pin_rank','edit_own_rank','edit_others_rank','delete_own_rank','delete_others_rank','delete_comments_rank','max_image_mb','max_video_mb','max_audio_mb','max_audio_seconds','max_video_seconds','max_post_chars','max_story_chars','story_text_seconds','story_image_seconds','story_hours','post_expiry_days','max_posts_hour','story_circle_size','post_text_gold','post_audio_gold','post_image_gold','post_video_gold','story_text_gold','story_audio_gold','story_image_gold','story_video_gold'];
 var d={rw_action:'save_settings'};ids.forEach(function(k){d[k]=$('#rw_'+k).val();});
 rwAdminPost(d,function(r){$('#rw_admin_status').text(r.ok?'تم حفظ الإعدادات.':(r.error||'تعذر الحفظ.'));});
}
$(document).on('input change','#rw_story_circle_size',function(){ $('#rw_story_circle_size_value').text(this.value+' px'); });
function rwLoadContent(){rwAdminPost({rw_action:'admin_content',q:$('#rw_content_query').val()||''},function(r){$('#rw_content_results').html(r&&r.ok?r.html:(r&&r.error)||'تعذر تحميل المحتوى.');});}
function rwAdminDelete(type,id){if(!confirm('هل تريد حذف هذا المحتوى نهائياً؟'))return;rwAdminPost({rw_action:'admin_delete_content',type:type,id:id},function(r){if(r&&r.ok)rwLoadContent();else alert((r&&r.error)||'تعذر الحذف.');});}
function rwStartImport(){
 var btn=$('#rw_import_btn');btn.prop('disabled',true);var cursor=0,total=0,skipped=0,rounds=0;
 function next(){rounds++;rwAdminPost({rw_action:'import_public_chats',cursor:cursor},function(r){
   if(!r.ok){$('#rw_import_status').text(r.error||'توقف الاستيراد.');btn.prop('disabled',false);return;}
   cursor=r.cursor||cursor;total+=Number(r.imported||0);skipped+=Number(r.skipped||0);
   $('#rw_import_status').text('تم استيراد '+total+' منشوراً حتى الآن...');
   if(r.done){$('#rw_import_status').text('اكتمل الاستيراد. المنشورات المستوردة: '+total+'؛ المتجاوزة أو المكررة: '+skipped+'.');btn.prop('disabled',false);}
   else if(rounds>=100){$('#rw_import_status').text('توقف مؤقتاً بعد 100 دفعة. المستورد حتى الآن: '+total+'؛ المتجاوز أو المكرر: '+skipped+'. اضغط متابعة لاستكمال الباقي.');btn.prop('disabled',false);}
   else setTimeout(next,150);
 });}
 next();
}
</script>
