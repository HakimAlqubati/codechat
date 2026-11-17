<?php
$load_addons = 'disable_f12';
require('../../../system/config_addons.php');

if (!canManageAddons()) {
    die();
}

$clipboard_rank = isset($addons['custom1']) && $addons['custom1'] !== '' ? (int)$addons['custom1'] : 1;
$clipboard_enabled = isset($addons['custom2']) && $addons['custom2'] !== '' ? (int)$addons['custom2'] : 1;
$devtools_enabled = isset($addons['addons_access']) && (int)$addons['addons_access'] !== 0 ? 1 : 0;
$deny_message = isset($addons['custom4']) ? html_entity_decode($addons['custom4'], ENT_QUOTES, 'UTF-8') : 'هذه العملية غير مسموحة لرتبتك.';
$valid_clipboard_ranks = array(0, 1, 50, 70, 80, 90, 100);
if (!in_array($clipboard_rank, $valid_clipboard_ranks, true)) {
    $clipboard_rank = 1;
}
?>
<?php echo elementTitle($addons['addons'], 'loadLob(\'admin/setting_addons.php\');'); ?>
<div class="page_full">
    <div class="page_element">
        <div class="config_section">
            <div class="setting_element">
                <p class="label">كشف أدوات المطوّر (F12 / DevTools)</p>
                <select id="set_devtools_access">
                    <?php echo onOff($devtools_enabled); ?>
                </select>
                <p class="sub_text">هذه الحماية مستقلة عن صلاحيات النسخ واللصق.</p>
            </div>

            <div class="setting_element">
                <p class="label">تفعيل حماية النسخ واللصق</p>
                <select id="set_clipboard_enabled">
                    <?php echo onOff($clipboard_enabled); ?>
                </select>
            </div>

            <div class="setting_element">
                <p class="label">أقل رتبة مسموح لها بالنسخ واللصق</p>
                <select id="set_clipboard_rank">
                    <?php foreach ($valid_clipboard_ranks as $rank) { ?>
                        <option value="<?php echo (int)$rank; ?>" <?php echo $clipboard_rank === $rank ? 'selected' : ''; ?>><?php echo htmlspecialchars(rankTitle($rank), ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php } ?>
                </select>
                <p class="sub_text">الرتبة المختارة والرتب الأعلى منها مسموح لها. الرتب الأقل منها ممنوعة، بما فيها الزائر عند اختيار رتبة أعلى منه.</p>
            </div>

            <div class="setting_element">
                <p class="label">رسالة منع النسخ واللصق</p>
                <input id="set_clipboard_message" class="full_input" maxlength="300" type="text" value="<?php echo htmlspecialchars($deny_message, ENT_QUOTES, 'UTF-8'); ?>" />
            </div>

            <div class="setting_element">
                <p class="label">نطاق الحماية</p>
                <p class="sub_text">تُطبق الصلاحية نفسها على الدردشة العامة والخاصة، وحائط الأصدقاء والمنشورات والتعليقات، والملفات الشخصية، وأسماء الأعضاء وبطاقاتهم، والحالات والمعلومات، وأي محتوى ديناميكي يظهر داخل صفحة الشات.</p>
            </div>

            <button onclick="saveDisableF12Settings();" type="button" class="tmargin10 reg_button theme_btn"><i class="fa fa-floppy-o"></i> <?php echo $lang['save']; ?></button>
        </div>
        <div class="config_section">
            <script data-cfasync="false" type="text/javascript">
                saveDisableF12Settings = function(){
                    $.post('addons/disable_f12/system/action.php', {
                        save_settings: 1,
                        devtools_access: $('#set_devtools_access').val(),
                        clipboard_enabled: $('#set_clipboard_enabled').val(),
                        clipboard_rank: $('#set_clipboard_rank').val(),
                        clipboard_message: $('#set_clipboard_message').val(),
                        token: utk
                    }, function(response) {
                        if(response == 5){
                            callSuccess(system.saved);
                        }
                        else{
                            callError(system.error);
                        }
                    }).fail(function(){
                        callError(system.error);
                    });
                }
            </script>
        </div>
    </div>
</div>
