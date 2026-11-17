<?php
$load_addons = 'DSG_Admin_User_Manager';
require_once('../../../system/config_addons.php');
if(!canManageAddons()){
    die();
}
include(addonsLang('DSG_Admin_User_Manager'));
$walletEnabled = isset($setting['use_wallet']) && (int)$setting['use_wallet'] === 1;
$levelEnabled = isset($setting['use_level']) && (int)$setting['use_level'] === 1;
$likesEnabled = isset($setting['use_like']) && (int)$setting['use_like'] === 1;
$likesTableReady = dsgAdminTableExists('boom_dsg_admin_likes');
$logsTableReady = dsgAdminTableExists('boom_dsg_user_manager_logs');
?>
<div class="page_full" dir="rtl">
    <?php echo elementTitle($addons['addons'], 'loadLob(\'admin/setting_addons.php\');'); ?>
    <div class="page_element">
        <div class="setting_element">
            <p class="label">حالة الأنظمة المرتبطة</p>
            <div class="text_small">المحفظة: <?php echo $walletEnabled ? '<span class="success">مفعّلة</span>' : '<span class="error">متوقفة</span>'; ?></div>
            <div class="text_small">المستويات: <?php echo $levelEnabled ? '<span class="success">مفعّلة</span>' : '<span class="error">متوقفة</span>'; ?></div>
            <div class="text_small">إعجابات الملفات الشخصية: <?php echo $likesEnabled ? '<span class="success">مفعّلة</span>' : '<span class="error">متوقفة</span>'; ?></div>
            <div class="text_small">جدول الإعجابات الإدارية: <?php echo $likesTableReady ? '<span class="success">جاهز</span>' : '<span class="error">غير موجود؛ شغّل upgrade_v2.sql</span>'; ?></div>
            <div class="text_small">سجل التعديلات: <?php echo $logsTableReady ? '<span class="success">جاهز</span>' : '<span class="error">غير موجود؛ شغّل upgrade_v2.sql</span>'; ?></div>
            <p class="text_small">الإعجابات التي تمنحها الإدارة تحفظ بشكل مستقل عن الإعجابات الطبيعية وعن الإعجابات المشتراة من متجر المشتريات.</p>
        </div>

        <div class="setting_element">
            <p class="label">أقل رتبة لاستخدام إدارة الأعضاء</p>
            <select id="set_DSG_Admin_User_Manager_access" class="selectit">
                <?php echo listRank($addons['addons_access']); ?>
            </select>
        </div>
        <div class="setting_element">
            <button onclick="saveDSGAdminManagerSettings();" type="button" class="reg_button theme_btn"><i class="fa fa-save"></i> حفظ الإعدادات</button>
        </div>
        <div class="setting_element">
            <p class="text_small">تُطبّق قواعد CodyChat الأصلية: لا يستطيع الموظف تعديل حساب برتبة مساوية أو أعلى من رتبته، ولا يمكن تعديل الحسابات الضيف أو الآلية أو المحمية.</p>
        </div>
    </div>
</div>
<script data-cfasync="false" type="text/javascript">
window.saveDSGAdminManagerSettings = function(){
    $.post('addons/DSG_Admin_User_Manager/system/action.php', {
        set_DSG_Admin_User_Manager_access: $('#set_DSG_Admin_User_Manager_access').val(),
        token: utk
    }, function(response){
        var result = response;
        try { if(typeof response === 'string'){ result = JSON.parse(response); } } catch(e) { result = null; }
        if(result && result.code == 1){
            callSuccess(result.message || 'تم حفظ الإعدادات.');
        } else {
            callError((result && result.error) || 'تعذر حفظ الإعدادات.');
        }
    }).fail(function(){ callError('تعذر الاتصال بالخادم.'); });
};
</script>
