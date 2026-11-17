<?php
$load_addons = 'chat_alerts';
require('../../../system/config_addons.php');
require_once __DIR__ . '/../../../system/addon_menu_labels.php';

if(!canManageAddons()){
    die();
}
?>
<?php echo elementTitle('إعدادات نظام الإشعارات', 'loadLob(\'admin/setting_addons.php\');'); ?>
<?php echo addonMenuLabelSettingsBlock('chat_alerts', 'نظام الإشعارات', 'يظهر اختصار الإضافة في القائمة الجانبية الرئيسية.'); ?>

<div class="page_full">
    <div class="page_element">
        <div class="config_section">
            <div class="setting_element">
                <p class="label">أقل رتبة مسموح لها بنشر الإشعارات</p>
                <select id="set_chat_alert_access" class="full_select">
                    <?php echo caRankOptions($addons['addons_access']); ?>
                </select>
                <p class="sub_text">كل رتبة أعلى من الرتبة المختارة تستطيع النشر أيضاً. اختيار الزائر يسمح لجميع الحسابات المسجلة باستخدام الإضافة.</p>
            </div>
            <button id="save_chat_alert_settings" onclick="saveChatAlertSettings();" type="button" class="tmargin10 reg_button theme_btn"><i class="fa fa-floppy-o"></i> <?php echo $lang['save']; ?></button>
        </div>
        <div class="config_section">
            <div class="setting_element">
                <p class="label">طريقة عمل الإضافة</p>
                <p class="sub_text">تظهر الإشعارات الموجهة للأعضاء في أعلى صفحة الأخبار بتصميم مميز. ويمكن اختيار إرسال نسخة تلقائية إلى الخاص من حساب النظام عند نشر كل إشعار.</p>
                <p class="sub_text">لا يتم تعديل رتبة أي عضو أو صلاحيات الغرف أو نظام الأخبار والإشعارات الأصلي.</p>
            </div>
        </div>
    </div>
</div>
<script data-cfasync="false">
saveChatAlertSettings = function(){
    $.post('addons/chat_alerts/system/action.php', {
        save_settings: 1,
        set_chat_alert_access: $('#set_chat_alert_access').val()
    }, function(response){
        if(response == 5){
            callSuccess(system.saved);
        }
        else {
            callError(system.error);
        }
    });
}
</script>
