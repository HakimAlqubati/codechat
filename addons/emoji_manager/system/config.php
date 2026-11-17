<?php
$load_addons = 'emoji_manager';
require('../../../system/config_addons.php');
if(!canManageAddons()){ die(); }
require_once __DIR__ . '/runtime.php';
$cats = emojiManagerReadJson('categories', array());
$rootCount = 0; foreach(glob(BOOM_PATH . '/emoticon/*') ?: array() as $f){ if(is_file($f) && in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), array('png','gif','webp'))) $rootCount++; }
?>
<div class="page_full"><div class="page_element">
 <div class="config_section">
  <h3>ربط الشات</h3>
  <p class="sub_text">يلزم تطبيق ملفات الربط حتى تظهر المجموعات الجديدة وتعمل أحجام الرموز الكبيرة ودعم WebP في نافذة الاختيار. تتم مقارنة بصمات الملفات قبل الاستبدال لتجنب الكتابة فوق تعديلاتك.</p>
  <button class="reg_button theme_btn" type="button" onclick="emojiApplyIntegration()">تطبيق ربط Emoji Manager مع CodyChat</button>
  <button class="default_btn" type="button" onclick="emojiRestoreIntegration()">استعادة ملفات الشات الأصلية</button>
 </div>
 <div class="config_section">
  <h3>إدارة الرموز التعبيرية</h3>
  <p class="sub_text">تتحكم هذه الإضافة في ملفات الرموز الأصلية داخل مجلدات CodyChat. الحذف نهائي للملف المحدد.</p>
  <div class="setting_element"><label class="label">القسم المستهدف</label><select id="em_cat"><option value="">الرئيسية (<?php echo $rootCount; ?>)</option><?php foreach($cats as $slug=>$meta){ if($slug==='base_emo') continue; ?><option value="<?php echo htmlspecialchars($slug,ENT_QUOTES); ?>"><?php echo htmlspecialchars($meta['label'] ?? $slug); ?><?php echo $slug==='large_emoticons'?' — كبير ×4':''; ?></option><?php } ?></select></div>
  <div class="setting_element"><label class="label">اسم الإيموجي عند الرفع أو التعديل</label><input id="em_label" type="text" maxlength="80" placeholder="مثال: ضحكة أو Love أو 100" /></div>
  <div class="setting_element"><label class="label">رفع صورة/صور</label><input id="em_files" type="file" accept=".png,.gif,.webp,image/png,image/gif,image/webp" multiple /><p class="sub_text">يدعم PNG وGIF المتحرك وWebP. يتم حفظ GIF كما هو دون تحويل.</p></div>
  <button class="reg_button theme_btn" type="button" onclick="emojiUploadFiles()">رفع الملفات إلى القسم المحدد</button>
 </div>
 <div class="config_section">
  <h3>إنشاء قسم جديد</h3>
  <div class="setting_element"><label class="label">اسم القسم بالعربية أو الإنجليزية</label><input id="em_new_label" type="text" maxlength="60" placeholder="مثال: تعبيرات مضحكة" /></div>
  <div class="setting_element"><label class="label">رتبة الاستخدام الأدنى (0 للجميع)</label><select id="em_new_rank"><?php echo listRank(0); ?></select></div>
  <div class="setting_element"><label class="label">أيقونة القسم PNG (اختياري)</label><input id="em_new_icon" type="file" accept="image/png" /></div>
  <button class="reg_button theme_btn" type="button" onclick="emojiCreateCategory()">إنشاء القسم</button>
  <button class="reg_button default_btn" type="button" onclick="emojiCreateLarge()">إنشاء/تجهيز قسم الإيموجيات الكبيرة</button>
 </div>
 <div class="config_section">
  <h3>الأقسام الموجودة</h3><div id="em_categories"></div>
 </div>
 <div class="config_section">
  <h3>إدارة الملفات</h3>
  <div class="setting_element"><label class="label">تصفية حسب القسم</label><select id="em_list_cat" onchange="emojiLoadItems()"><option value="*">كل الأقسام</option><option value="">الرئيسية</option><?php foreach($cats as $slug=>$meta){ if($slug==='base_emo') continue; ?><option value="<?php echo htmlspecialchars($slug,ENT_QUOTES); ?>"><?php echo htmlspecialchars($meta['label'] ?? $slug); ?></option><?php } ?></select></div>
  <div class="setting_element"><button class="default_btn" type="button" onclick="emojiLoadItems()">تحديث القائمة</button> <button class="delete_btn" type="button" onclick="emojiDeleteSelected()">حذف المحدد</button> <button class="default_btn" type="button" onclick="emojiSelectAll()">تحديد الكل</button></div>
  <div id="em_items" class="emoji-manager-grid">جارٍ تحميل الرموز...</div>
 </div>
 <div id="em_status" class="sub_text"></div>
</div></div>
<style>
.emoji-manager-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px;margin-top:12px}.em-manager-card{border:1px solid #8885;border-radius:8px;padding:8px;min-width:0}.em-manager-card img{height:48px;max-width:100%;object-fit:contain;display:block;margin:auto}.em-manager-card input{margin:6px 0}.em-manager-card .em-label{width:100%;box-sizing:border-box;margin:5px 0}.em-manager-actions{display:flex;gap:5px;flex-wrap:wrap}.em-manager-actions button{font-size:12px;padding:5px}.em-cat-row{display:flex;gap:8px;align-items:center;padding:8px;border-bottom:1px solid #8884}.em-cat-row img{width:32px;height:32px;object-fit:contain}
</style>
<script>
(function(){
 const endpoint='addons/emoji_manager/system/action.php';
 function status(t){$('#em_status').text(t)}
 function post(data,done){data.token=utk;$.post(endpoint,data,function(r){if(typeof r==='string'){try{r=JSON.parse(r)}catch(e){status(r);return}}if(r&&r.message)status(r.message);if(done)done(r)},'json').fail(function(){status('تعذر الاتصال بالخادم')})}
 window.emojiApplyIntegration=function(){if(!confirm('سيتم تعديل ثلاثة ملفات ربط محددة فقط بعد التحقق من نسخها الأصلية. هل تريد المتابعة؟'))return;post({apply_integration:1},function(r){if(r&&r.ok)status(r.message)})};
 window.emojiRestoreIntegration=function(){if(!confirm('سيتم استعادة الملفات الأصلية من النسخ الاحتياطية المرفقة. هل تريد المتابعة؟'))return;post({restore_integration:1},function(r){if(r)status(r.message)})};
 window.emojiLoadItems=function(){post({list_items:1,folder:$('#em_list_cat').val()},function(r){if(!r||!r.items)return;let h='';r.items.forEach(function(x){h+='<div class="em-manager-card"><label><input class="em-pick" type="checkbox" value="'+ $('<div>').text(x.key).html() +'"> تحديد</label><img src="'+x.url+'?v='+Date.now()+'" alt=""><input class="em-label" maxlength="80" value="'+ $('<div>').text(x.label).html() +'" data-key="'+ $('<div>').text(x.key).html() +'"><div class="em-manager-actions"><button type="button" onclick="emojiRename(this)">حفظ الاسم</button></div><small>'+x.file+'</small></div>'});$('#em_items').html(h||'لا توجد رموز في هذا القسم')})};
 window.emojiRename=function(btn){let i=$(btn).closest('.em-manager-card').find('.em-label');post({rename_item:1,key:i.data('key'),label:i.val()},function(){emojiLoadItems()})};
 window.emojiSelectAll=function(){let a=$('.em-pick');let all=a.length===a.filter(':checked').length;a.prop('checked',!all)};
 window.emojiDeleteSelected=function(){let keys=$('.em-pick:checked').map(function(){return this.value}).get();if(!keys.length)return status('حدد رمزاً واحداً على الأقل');if(!confirm('تأكيد حذف '+keys.length+' رمز؟ الحذف نهائي وقد يؤثر على الرسائل القديمة التي تستخدم هذه الرموز.'))return;post({delete_items:1,keys:JSON.stringify(keys)},function(){emojiLoadItems()})};
 window.emojiUploadFiles=function(){let files=$('#em_files')[0].files;if(!files.length)return status('اختر ملفات أولاً');let fd=new FormData();fd.append('upload_items',1);fd.append('token',utk);fd.append('folder',$('#em_cat').val());fd.append('label',$('#em_label').val());for(let i=0;i<files.length;i++)fd.append('emoji_files[]',files[i]);$.ajax({url:endpoint,method:'POST',data:fd,processData:false,contentType:false,dataType:'json'}).done(function(r){status(r.message||'اكتمل الرفع');$('#em_files').val('');emojiLoadItems()}).fail(function(){status('فشل الرفع؛ تحقق من حدود الرفع في PHP')})};
 window.emojiCreateCategory=function(){let fd=new FormData();fd.append('create_category',1);fd.append('token',utk);fd.append('label',$('#em_new_label').val());fd.append('rank',$('#em_new_rank').val());let f=$('#em_new_icon')[0].files[0];if(f)fd.append('icon',f);$.ajax({url:endpoint,method:'POST',data:fd,processData:false,contentType:false,dataType:'json'}).done(function(r){status(r.message||'تم');if(r.ok)location.reload()}).fail(function(){status('تعذر إنشاء القسم')})};
 window.emojiCreateLarge=function(){post({create_large:1},function(r){if(r&&r.ok)location.reload()})};
 function loadCats(){post({list_categories:1},function(r){if(!r||!r.categories)return;let h='';r.categories.forEach(function(c){h+='<div class="em-cat-row"><img src="'+c.icon+'?v='+Date.now()+'"><span style="flex:1">'+$('<div>').text(c.label).html()+' ('+c.count+')</span><button type="button" onclick="emojiEditCategory(\''+c.slug+'\',\''+$('<div>').text(c.label).html().replace(/'/g,'\\\'')+'\','+c.rank+')">تعديل</button>'+(c.slug!=='base_emo'?'<button type="button" onclick="emojiChangeIcon(\''+c.slug+'\')">تغيير الأيقونة</button><button class="delete_btn" type="button" onclick="emojiDeleteCategory(\''+c.slug+'\')">حذف القسم</button>':'')+'</div>'});$('#em_categories').html(h)})}
 window.emojiEditCategory=function(slug,label,rank){let n=prompt('اسم القسم',label);if(n===null)return;let r=prompt('أقل رتبة مسموحة (0 للجميع)',rank);if(r===null)return;post({edit_category:1,slug:slug,label:n,rank:r},function(){loadCats();emojiLoadItems()})};
 window.emojiChangeIcon=function(slug){let f=document.createElement('input');f.type='file';f.accept='image/png';f.onchange=function(){if(!f.files.length)return;let fd=new FormData();fd.append('change_icon',1);fd.append('token',utk);fd.append('slug',slug);fd.append('icon',f.files[0]);$.ajax({url:endpoint,method:'POST',data:fd,processData:false,contentType:false,dataType:'json'}).done(function(r){status(r.message||'تم');loadCats()}).fail(function(){status('فشل تحديث الأيقونة')})};f.click()};
 window.emojiDeleteCategory=function(slug){if(!confirm('سيتم حذف القسم وجميع الرموز بداخله نهائياً. هل تريد المتابعة؟'))return;post({delete_category:1,slug:slug},function(r){if(r&&r.ok)location.reload()})};
 $(function(){loadCats();emojiLoadItems()});
})();
</script>
