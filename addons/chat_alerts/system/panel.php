<?php
$load_addons = 'chat_alerts';
require(__DIR__ . '/../../../system/config_addons.php');

$alerts = caVisibleAlerts(25);
caMarkSeen($alerts);

ob_start();
?>
<div class="left_keep chat_alert_panel">
    <div class="pad20">
        <?php if(caCanPublish()){ ?>
        <div class="chat_alert_compose blist">
            <div class="chat_alert_section_title"><i class="fa fa-paper-plane"></i> إرسال إشعار جديد</div>
            <div class="setting_element">
                <p class="label">عنوان الإشعار (اختياري)</p>
                <input id="chat_alert_title" class="full_input" type="text" maxlength="160" placeholder="اكتب عنواناً مختصراً" />
            </div>
            <div class="setting_element">
                <p class="label">نص الإشعار</p>
                <textarea id="chat_alert_message" class="full_textarea" maxlength="3000" rows="5" placeholder="اكتب الإشعار هنا"></textarea>
            </div>
            <div class="setting_element">
                <p class="label">سيظهر الإشعار باسم</p>
                <select id="chat_alert_display" class="full_select">
                    <option value="1">اسم حسابي</option>
                    <option value="0">نظام الشات</option>
                </select>
            </div>
            <div class="setting_element">
                <p class="label">الجهة المستهدفة</p>
                <select id="chat_alert_target" class="full_select">
                    <option value="-1">الجميع، بما فيهم الزوار</option>
                    <option value="0">الزوار فقط</option>
                    <option value="1">الأعضاء</option>
                    <option value="50">أعضاء VIP</option>
                    <option value="70">المشرفون</option>
                    <option value="80">الإداريون</option>
                    <option value="90">المشرفون العامون</option>
                    <option value="100">المالك</option>
                </select>
            </div>
            <label class="chat_alert_check"><input id="chat_alert_private" type="checkbox" value="1" /> إرسال نسخة تلقائية إلى الخاص من حساب نظام الشات</label>
            <p class="sub_text chat_alert_hint">عند ترك الخيار دون تحديد، سيظهر الإشعار في مركز الإشعارات وصفحة الأخبار فقط.</p>
            <button id="send_chat_alert" type="button" class="theme_btn button full_button" onclick="sendChatAlertNow();"><i class="fa fa-paper-plane"></i> إرسال الإشعار</button>
        </div>
        <?php } ?>

        <div class="chat_alert_list_head">
            <div class="chat_alert_section_title"><i class="fa fa-bullhorn"></i> الإشعارات الموجهة إليك</div>
            <span class="sub_text">تظهر الإشعارات الجديدة هنا وفي أعلى صفحة الأخبار.</span>
        </div>
        <div id="chat_alert_center_list">
            <?php
            if(!empty($alerts)){
                foreach($alerts as $alert){
                    echo caAlertCard($alert);
                }
            }
            else {
                echo emptyZone('لا توجد إشعارات حالياً');
            }
            ?>
        </div>
    </div>
</div>
<script data-cfasync="false">
sendChatAlertNow = function(){
    var message = $('#chat_alert_message').val();
    if(!message || !message.trim()){
        callError('اكتب نص الإشعار أولاً');
        return false;
    }
    var $button = $('#send_chat_alert');
    if($button.data('busy')){
        return false;
    }
    $button.data('busy', 1).prop('disabled', true);
    $.post('addons/chat_alerts/system/action.php', {
        send_alert: 1,
        alert_title: $('#chat_alert_title').val(),
        alert_message: message,
        display_mode: $('#chat_alert_display').val(),
        target_rank: $('#chat_alert_target').val(),
        private_copy: $('#chat_alert_private').is(':checked') ? 1 : 0
    }, function(response){
        if(response && response.code == 1){
            var privateCount = parseInt(response.private_count, 10) || 0;
            var successText = 'تم إرسال الإشعار بنجاح';
            if($('#chat_alert_private').is(':checked')){
                successText += ' — أُرسلت نسخة خاصة إلى ' + privateCount + ' حساب';
            }
            callSuccess(successText);
            if(typeof refreshChatAlertCount === 'function'){
                refreshChatAlertCount();
            }
            $.post('addons/chat_alerts/system/panel.php', {}, function(panelResponse){
                if(panelResponse && panelResponse.code == 1){
                    showLeftPanel(panelResponse.content, 380, panelResponse.title || 'نظام الإشعارات');
                }
            }, 'json');
        }
        else {
            callError('تعذر إرسال الإشعار. تحقق من الصلاحية وحاول مجدداً.');
        }
        $button.data('busy', 0).prop('disabled', false);
    }, 'json').fail(function(){
        callError(system.error);
        $button.data('busy', 0).prop('disabled', false);
    });
    return false;
}
</script>
<?php
$res['content'] = ob_get_clean();
$res['title'] = 'نظام الإشعارات';
echo boomCode(1, $res);
?>
