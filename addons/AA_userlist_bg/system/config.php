<?php
$load_addons = 'AA_userlist_bg';
require('../../../system/config_addons.php');
require_once __DIR__ . '/../../../system/addon_menu_labels.php';
if(!canManageAddons() || !aaListBgEnsureSchema()){
    die();
}

$assets = array();
$q = $mysqli->query("SELECT a.bg_id, a.bg_file, a.bg_title, a.bg_price, a.bg_active, r.rank_type, r.rank_value
    FROM userlist_bg_assets a
    LEFT JOIN userlist_bg_rank r ON r.bg_id = a.bg_id
    ORDER BY a.bg_created DESC, a.bg_id DESC");
if($q){ while($row = $q->fetch_assoc()){ $assets[] = $row; } $q->free(); }
$rankOptions = aaListBgRankOptions();
?>
<?php echo elementTitle('إدارة مكتبة خلفيات المتصلين', 'loadLob(\'admin/setting_addons.php\');'); ?>
<?php echo addonMenuLabelSettingsBlock('AA_userlist_bg', 'متجر خلفية العضو في المتصلين', 'يظهر تحت تصنيف المتاجر بعد متجر الإطارات.'); ?>

<div class="page_full aaulbg-admin">
    <div class="page_element">
        <div class="config_section">
            <div class="setting_element">
                <p class="label">من يمكنه استخدام خلفيات القائمة الشخصية</p>
                <select id="set_addon_access"><?php echo listRank($addons['addons_access']); ?></select>
            </div>
            <div class="setting_element">
                <p class="label">رتبة الإدارة المسموح لها بتغيير خلفيات الأعضاء</p>
                <select id="set_addon_access_staff"><?php echo listRank($addons['custom1']); ?></select>
            </div>
            <button onclick="aaSaveAccess();" type="button" class="reg_button theme_btn"><i class="fa fa-floppy-o"></i> حفظ الصلاحيات</button>
        </div>

        <div class="config_section">
            <h3 class="label">إضافة خلفية جديدة إلى المكتبة</h3>
            <p class="sub_text">ارفع الصورة أولاً. بعد رفعها افتح إعداداتها لاختيار: تثبيتها على رتبة، أو تحديد سعرها بالذهب.</p>
            <div class="setting_element">
                <label class="label" for="aa_bg_new_title">اسم الخلفية</label>
                <input id="aa_bg_new_title" class="full_input" type="text" maxlength="120" placeholder="مثال: خلفية الملوك" />
            </div>
            <div class="setting_element">
                <label class="label" for="aa_bg_new_file">ملف الصورة</label>
                <input id="aa_bg_new_file" class="full_input" type="file" accept="image/png,image/jpeg,image/gif,image/webp" />
            </div>
            <p class="sub_text">الحد الأعلى 5 ميجابايت، والأبعاد القصوى 4096 × 4096. الصور المتحركة GIF وWEBP مدعومة.</p>
            <button type="button" onclick="aaUploadAsset();" class="reg_button theme_btn"><i class="fa fa-upload"></i> رفع إلى المكتبة</button>
        </div>

        <div class="config_section">
            <h3 class="label">مكتبة الخلفيات (<?php echo count($assets); ?>)</h3>
            <?php if(!count($assets)){ ?>
                <div class="sub_text">لا توجد خلفيات مرفوعة حتى الآن.</div>
            <?php } ?>
            <?php foreach($assets as $asset){
                $selectedRank = 'none';
                if($asset['rank_type'] !== null && $asset['rank_type'] !== ''){
                    $selectedRank = $asset['rank_type'] . ':' . (int)$asset['rank_value'];
                }
                $selectedMode = $selectedRank === 'none' ? 'gold' : 'rank';
            ?>
                <div class="aaulbg-asset" data-bg-id="<?php echo (int)$asset['bg_id']; ?>">
                    <div class="aaulbg-asset-head">
                        <div class="aaulbg-asset-preview" onclick="aaToggleAsset(this);" title="اضغط لفتح إعدادات الخلفية" style="background-image:url('addons/AA_userlist_bg/files/background/<?php echo htmlspecialchars($asset['bg_file'], ENT_QUOTES, 'UTF-8'); ?>');"></div>
                        <div class="aaulbg-asset-caption"><?php echo htmlspecialchars($asset['bg_title'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <button type="button" class="reg_button default_btn" onclick="aaToggleAsset(this);">إعدادات هذه الخلفية</button>
                    </div>
                    <div class="aaulbg-asset-settings" style="display:none">
                        <div class="setting_element">
                            <label class="label">اسم الخلفية</label>
                            <input class="full_input aa-bg-title" type="text" maxlength="120" value="<?php echo htmlspecialchars($asset['bg_title'], ENT_QUOTES, 'UTF-8'); ?>" />
                        </div>
                        <div class="setting_element">
                            <label class="label">اختر طريقة استخدام الخلفية</label>
                            <select class="full_input aa-bg-mode">
                                <option value="gold" <?php echo $selectedMode === 'gold' ? 'selected' : ''; ?>>تحديد قيمتها بالذهب</option>
                                <option value="rank" <?php echo $selectedMode === 'rank' ? 'selected' : ''; ?>>تثبيتها على رتبة محددة</option>
                            </select>
                        </div>
                        <div class="setting_element aa-bg-rank-wrap" <?php echo $selectedMode !== 'rank' ? 'style="display:none"' : ''; ?>>
                            <label class="label">اختر الرتبة التي ستظهر لها الخلفية تلقائياً</label>
                            <select class="full_input aa-bg-rank">
                                <option value="none">اختر رتبة</option>
                                <optgroup label="الرتب العامة">
                                <?php foreach($rankOptions as $option){ if($option['type'] !== 'system'){ continue; } $key = $option['type'].':'.$option['value']; ?>
                                    <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedRank === $key ? 'selected' : ''; ?>><?php echo htmlspecialchars($option['label'], ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php } ?>
                                </optgroup>
                                <optgroup label="رتب الغرف">
                                <?php foreach($rankOptions as $option){ if($option['type'] !== 'room'){ continue; } $key = $option['type'].':'.$option['value']; ?>
                                    <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedRank === $key ? 'selected' : ''; ?>><?php echo htmlspecialchars($option['label'], ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php } ?>
                                </optgroup>
                            </select>
                        </div>
                        <div class="setting_element aa-bg-price-wrap" <?php echo $selectedMode !== 'gold' ? 'style="display:none"' : ''; ?>>
                            <label class="label">سعر الخلفية بالذهب (اكتب 0 لتكون مجانية)</label>
                            <input class="full_input aa-bg-price" type="number" min="0" max="100000000" step="1" value="<?php echo (int)$asset['bg_price']; ?>" />
                        </div>
                        <div class="setting_element">
                            <label class="label">حالة الخلفية</label>
                            <select class="full_input aa-bg-active">
                                <option value="1" <?php echo (int)$asset['bg_active'] === 1 ? 'selected' : ''; ?>>مفعلة</option>
                                <option value="0" <?php echo (int)$asset['bg_active'] !== 1 ? 'selected' : ''; ?>>موقوفة</option>
                            </select>
                        </div>
                        <div class="sub_text aa-bg-mode-note">
                            <?php if($selectedRank !== 'none'){ ?>هذه الخلفية تظهر تلقائياً للأعضاء الذين يحملون الرتبة المختارة، ولا تباع بالذهب.<?php }
                            else { ?>يمكن للأعضاء تفعيلها مجاناً أو شراؤها بالسعر المحدد من مكتبة الخلفيات.<?php } ?>
                        </div>
                        <div class="aaulbg-actions">
                            <button type="button" class="reg_button theme_btn" onclick="aaSaveAsset(<?php echo (int)$asset['bg_id']; ?>);"><i class="fa fa-floppy-o"></i> حفظ إعدادات الخلفية</button>
                            <button type="button" class="reg_button delete_btn" onclick="aaDeleteAsset(<?php echo (int)$asset['bg_id']; ?>);"><i class="fa fa-trash"></i> حذف</button>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</div>
<style>
.aaulbg-admin .aaulbg-asset{display:flex;gap:14px;align-items:flex-start;padding:14px;margin:12px 0;border:1px solid rgba(127,127,127,.25);border-radius:10px;background:rgba(127,127,127,.04)}
.aaulbg-admin .aaulbg-asset-head{width:180px;min-width:180px}.aaulbg-admin .aaulbg-asset-preview{width:100%;height:110px;border-radius:8px;background-position:center;background-size:cover;background-repeat:no-repeat;border:1px solid rgba(127,127,127,.25);cursor:pointer}.aaulbg-admin .aaulbg-asset-caption{font-weight:700;margin:7px 0;overflow-wrap:anywhere}.aaulbg-admin .aaulbg-asset-head button{width:100%}
.aaulbg-admin .aaulbg-asset-settings{flex:1;min-width:0}
.aaulbg-admin .aaulbg-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}
.aaulbg-admin .sub_text{margin:6px 0 10px}
@media(max-width:600px){.aaulbg-admin .aaulbg-asset{display:block}.aaulbg-admin .aaulbg-asset-head{width:100%;margin-bottom:12px}.aaulbg-admin .aaulbg-asset-preview{height:130px}}
</style>
<script data-cfasync="false" type="text/javascript">
(function(){
    function aaReloadConfig(){ configAddons('AA_userlist_bg'); }
    window.aaSaveAccess = function(){
        $.post('addons/AA_userlist_bg/system/action.php', {
            set_addon_access: $('#set_addon_access').val(),
            set_addon_access_staff: $('#set_addon_access_staff').val(),
            token: utk
        }, function(r){
            if(r && r.code === 1){ callSaved('تم حفظ الصلاحيات', 1); }
            else { callSaved('تعذر حفظ الصلاحيات', 3); }
        }, 'json');
    };
    window.aaUploadAsset = function(){
        var input = document.getElementById('aa_bg_new_file');
        if(!input || !input.files || !input.files[0]){ callSaved('اختر صورة أولاً', 3); return; }
        var form = new FormData();
        form.append('file', input.files[0]);
        form.append('title', $('#aa_bg_new_title').val());
        form.append('upload_asset', 1);
        form.append('token', utk);
        $.ajax({url:'addons/AA_userlist_bg/system/action.php', type:'post', data:form, contentType:false, processData:false, dataType:'json', timeout:30000})
        .done(function(r){ if(r && r.code === 1){ callSaved('تم رفع الخلفية. اختر طريقة إتاحتها من إعداداتها.', 1); aaReloadConfig(); } else { callSaved(r && r.code === 3 ? 'حجم الصورة أكبر من الحد المسموح' : 'تعذر رفع الصورة', 3); } })
        .fail(function(){ callSaved('تعذر الاتصال بالخادم أثناء رفع الصورة', 3); });
    };
    window.aaToggleAsset = function(source){
        var card = $(source).closest('.aaulbg-asset');
        card.find('.aaulbg-asset-settings').stop(true, true).slideToggle(160);
    };
    $('.aa-bg-mode').off('change.aaulbg').on('change.aaulbg', function(){
        var card = $(this).closest('.aaulbg-asset');
        var isRank = $(this).val() === 'rank';
        card.find('.aa-bg-rank-wrap').toggle(isRank);
        card.find('.aa-bg-price-wrap').toggle(!isRank);
        card.find('.aa-bg-mode-note').text(isRank ? 'هذه الخلفية ستظهر تلقائياً للأعضاء الذين يحملون الرتبة المختارة، ولن تباع بالذهب.' : 'يمكن للأعضاء تفعيلها مجاناً أو شراؤها بالسعر المحدد من مكتبة الخلفيات.');
    });
    window.aaSaveAsset = function(id){
        var card = $('.aaulbg-asset[data-bg-id="'+id+'"]');
        $.post('addons/AA_userlist_bg/system/action.php', {
            save_asset: 1, bg_id:id,
            title:card.find('.aa-bg-title').val(),
            mode:card.find('.aa-bg-mode').val(),
            rank_key:card.find('.aa-bg-rank').val(),
            price:card.find('.aa-bg-price').val() || 0,
            active:card.find('.aa-bg-active').val(),
            token:utk
        }, function(r){
            if(r && r.code === 1){ callSaved('تم حفظ إعدادات الخلفية', 1); aaReloadConfig(); }
            else { callSaved('تعذر حفظ إعدادات الخلفية. تحقق من الرتبة والسعر.', 3); }
        }, 'json');
    };
    window.aaDeleteAsset = function(id){
        if(!confirm('هل تريد حذف هذه الخلفية نهائياً؟ لا يمكن حذف خلفية سبق شراؤها؛ يمكنك إيقافها بدلاً من ذلك.')){ return; }
        $.post('addons/AA_userlist_bg/system/action.php', {delete_asset:id, token:utk}, function(r){
            if(r && r.code === 1){ callSaved('تم حذف الخلفية', 1); aaReloadConfig(); }
            else if(r && r.code === 6){ callSaved('لا يمكن حذف خلفية اشتراها أعضاء. أوقفها بدلاً من حذفها حتى لا تضيع مشترياتهم.', 3); }
            else { callSaved('تعذر حذف الخلفية', 3); }
        }, 'json');
    };
})();
</script>
