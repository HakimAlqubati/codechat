<?php
$load_addons = 'amz_profile_store';
require('../../../system/config_addons.php');
require_once __DIR__ . '/../../../system/addon_menu_labels.php';
require_once __DIR__ . '/profile_gate.php';
require_once __DIR__ . '/patch.php';
if(!canManageAddons()){ die(); }
amzStoreEnsureTables();
$patchReport = amzStoreApplyCorePatches();
$labels = array('background'=>'خلفيات الملف الشخصي','frame'=>'إطارات الصور','music'=>'موسيقى الملف الشخصي');
?>
<?php echo elementTitle('AMZ Profile Store – متجر الملف الشخصي', 'loadL(\'admin/setting_addons.php\');'); ?>
<?php echo addonMenuLabelSettingsBlock('amz_profile_store', 'الملف الشخصي', 'يظهر كخيار ثالث تحت تصنيف المتاجر بعد متجر الترقيات ومتجر العضويات.'); ?>

<div class="page_full"><div class="page_element">
 <div class="config_section">
  <div class="setting_element"><p class="label">موقع المتجر</p><div class="sub_text">يظهر في القائمة الجانبية اليسرى كمدخل مستقل، ولا يضيف تبويباً أو يغيّر ترتيب الملف الشخصي.</div></div>
  <div class="setting_element"><p class="label">ربط CodyChat 10</p>
   <?php if($patchReport['function_all'] && $patchReport['profile']){ ?><div class="success_text">تم ربط بوابة الموسيقى وبيانات الخلفية والإطار بنجاح.</div><?php } else { ?><div class="sub_text" style="color:#d98a22">لم يكتمل الربط التلقائي. تحقق من صلاحيات الكتابة وراجع رسائل الربط أدناه.</div><?php } ?>
   <?php if(!empty($patchReport['errors'])){ foreach($patchReport['errors'] as $err){ ?><div class="sub_text" style="color:#d98a22">• <?php echo htmlspecialchars($err,ENT_QUOTES,'UTF-8'); ?></div><?php } } ?>
   <div class="sub_text">قبل أي تعديل أنشأ النظام نسخة احتياطية داخل addons/amz_profile_store/system/backup. تبقى أدوات الملف الشخصي الأصلية كما هي؛ يضاف فقط ربط صلاحية الموسيقى وسمتا الخلفية والإطار.</div>
  </div>
 </div>
 <div class="config_section">
  <div class="setting_element"><p class="label">صلاحيات وتسعير كل خدمة</p><div class="sub_text">اختر الرتبة التي تحصل على الخدمة مجاناً، وأقل رتبة يسمح لها بالشراء. السعر بالذهب ويُدخل يدوياً.</div></div>
  <?php foreach($labels as $key=>$label){ $s=amzStoreSetting($key); ?>
  <div class="setting_element amz-admin-service" style="padding:12px;border:1px solid rgba(128,128,128,.18);border-radius:10px;margin-bottom:10px">
   <label class="label"><input type="checkbox" id="amz_<?php echo $key; ?>_enabled" <?php echo ((int)$s['enabled']===1?'checked':''); ?>> تفعيل <?php echo $label; ?></label>
   <div class="setting_element"><p class="label">مجاني ابتداءً من رتبة</p><select id="amz_<?php echo $key; ?>_free_rank"><?php echo listRankMember((int)$s['free_rank']); ?></select></div>
   <div class="setting_element"><p class="label">السماح بالشراء ابتداءً من رتبة</p><select id="amz_<?php echo $key; ?>_paid_rank"><?php echo listRankMember((int)$s['paid_rank']); ?></select></div>
   <div class="setting_element"><p class="label"><?php echo $key==='music'?'سعر فتح خدمة الموسيقى بالذهب':'السعر الافتراضي للعناصر الجديدة بالذهب'; ?></p><input type="number" min="0" max="2000000000" id="amz_<?php echo $key; ?>_price" value="<?php echo (int)$s['price_gold']; ?>" class="full_input"></div>
  </div>
  <?php } ?>
  <button type="button" class="reg_button theme_btn" onclick="amzSaveServices()"><i class="fa fa-floppy-o"></i> حفظ إعدادات الخدمات</button>
 </div>
 <div class="config_section">
  <div class="setting_element"><p class="label">إضافة خلفية أو إطار جديد</p><div class="sub_text">الصيغ المدعومة: JPG وPNG وGIF وWebP. الحد الأقصى 8 ميجابايت. سعر كل عنصر يمكن تغييره يدوياً.</div></div>
  <div class="setting_element"><p class="label">نوع العنصر</p><select id="amz_item_service"><option value="background" data-default-price="<?php echo (int)amzStoreSetting('background')['price_gold']; ?>">خلفية الملف الشخصي</option><option value="frame" data-default-price="<?php echo (int)amzStoreSetting('frame')['price_gold']; ?>">إطار صورة العضو</option></select></div>
  <div class="setting_element"><p class="label">اسم العنصر</p><input type="text" id="amz_item_title" maxlength="120" class="full_input" placeholder="مثلاً: خلفية RGB الملكية"></div>
  <div class="setting_element"><p class="label">السعر بالذهب (0 = مجاني لمن تسمح رتبته)</p><input type="number" id="amz_item_price" min="0" max="2000000000" value="<?php echo (int)amzStoreSetting('background')['price_gold']; ?>" class="full_input"></div>
  <div class="setting_element"><p class="label">ملف الصورة</p><input type="file" id="amz_item_image" accept="image/png,image/jpeg,image/gif,image/webp" class="full_input"></div>
  <button type="button" class="reg_button theme_btn" onclick="amzUploadItem()"><i class="fa fa-upload"></i> رفع العنصر إلى المتجر</button>
 </div>
 <div class="config_section">
  <div class="setting_element"><p class="label">إدارة عناصر المتجر</p></div>
  <?php $res=$mysqli->query("SELECT * FROM boom_amz_store_items ORDER BY item_id DESC LIMIT 100"); if($res && $res->num_rows){ while($it=$res->fetch_assoc()){ ?>
   <div class="setting_element" style="display:flex;align-items:center;gap:10px;border-bottom:1px solid rgba(128,128,128,.15);padding:8px 0">
    <img src="../../upload/amz_profile_store/<?php echo rawurlencode($it['file_name']); ?>" style="width:54px;height:48px;object-fit:cover;border-radius:7px">
    <div style="flex:1;min-width:0"><b><?php echo htmlspecialchars($it['title'],ENT_QUOTES,'UTF-8'); ?></b><div class="sub_text"><?php echo $it['service_key']==='frame'?'إطار':'خلفية'; ?></div><input type="number" min="0" max="2000000000" id="amz_item_saved_price_<?php echo (int)$it['item_id']; ?>" value="<?php echo (int)$it['price_gold']; ?>" style="width:100px;max-width:100%;margin-top:5px" aria-label="سعر العنصر بالذهب"></div>
    <div style="display:flex;flex-direction:column;gap:5px"><button class="reg_button theme_btn" type="button" onclick="amzUpdateItemPrice(<?php echo (int)$it['item_id']; ?>)">حفظ السعر</button><button class="reg_button <?php echo ((int)$it['enabled']?'delete_btn':'theme_btn'); ?>" type="button" onclick="amzToggleItem(<?php echo (int)$it['item_id']; ?>,<?php echo (int)!$it['enabled']; ?>)"><?php echo ((int)$it['enabled']?'إيقاف':'تفعيل'); ?></button></div>
   </div>
  <?php }} else { ?><div class="sub_text">لم تُرفع عناصر بعد.</div><?php } ?>
 </div>
 <div class="config_section"><div class="sub_text">مهم: خصم الذهب يتم من حقل CodyChat الأصلي user_gold داخل معاملة قاعدة بيانات، ويُسجل في سجل المشتريات. لا تُنشأ محفظة ذهب جديدة. الموسيقى تستخدم مشغّل CodyChat الأصلي user_pmusic؛ السعر يمنح العضو استحقاقاً دائماً ولا ينشئ حقلاً موسيقياً مكرراً.</div></div>
</div></div>
<script data-cfasync="false">
function amzAdminPost(data,done){data.token=utk;$.post('addons/amz_profile_store/system/action.php',data,function(r){if(r&&r.code===1){callSuccess(r.message);if(done)done();}else{callError((r&&r.message)||system.error);}},'json').fail(function(){callError(system.error);});}
function amzSaveServices(){var d={action:'save_services'};['background','frame','music'].forEach(function(k){d[k+'_enabled']=$('#amz_'+k+'_enabled').is(':checked')?1:0;d[k+'_free_rank']=$('#amz_'+k+'_free_rank').val();d[k+'_paid_rank']=$('#amz_'+k+'_paid_rank').val();d[k+'_price']=$('#amz_'+k+'_price').val();});amzAdminPost(d,function(){configAddons('amz_profile_store');});}
$('#amz_item_service').on('change',function(){var p=$(this).find(':selected').attr('data-default-price')||0;$('#amz_item_price').val(p);});
function amzUploadItem(){var f=$('#amz_item_image')[0].files[0];if(!f){callError('اختر ملف صورة أولاً');return;}var d=new FormData();d.append('token',utk);d.append('action','upload_item');d.append('service',$('#amz_item_service').val());d.append('title',$('#amz_item_title').val());d.append('price',$('#amz_item_price').val());d.append('image',f);$.ajax({url:'addons/amz_profile_store/system/action.php',type:'POST',data:d,processData:false,contentType:false,dataType:'json',success:function(r){if(r&&r.code===1){callSuccess(r.message);configAddons('amz_profile_store');}else{callError((r&&r.message)||system.error);}},error:function(){callError(system.error);}});}
function amzToggleItem(id,state){amzAdminPost({action:'toggle_item',item_id:id,enabled:state},function(){configAddons('amz_profile_store');});}
function amzUpdateItemPrice(id){amzAdminPost({action:'update_item_price',item_id:id,price:$('#amz_item_saved_price_'+id).val()},function(){configAddons('amz_profile_store');});}
</script>
