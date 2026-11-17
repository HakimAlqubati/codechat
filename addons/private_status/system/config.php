<?php
$load_addons = 'private_status';
require(__DIR__ . '/../../../system/config_addons.php');
if(!canManageAddons()){
    die();
}

$mysqli->query("CREATE TABLE IF NOT EXISTS `boom_private_typing` (
    `user_id` INT NOT NULL,
    `target_id` INT NOT NULL,
    `updated_at` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`user_id`, `target_id`),
    KEY `target_updated` (`target_id`, `updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$enabled = (int)($addons['custom1'] ?? 1);
$typing = (int)($addons['custom2'] ?? 1);
$online = (int)($addons['custom3'] ?? 1);
$lastSeen = (int)($addons['custom4'] ?? 1);
$doubleCheck = (int)($addons['custom5'] ?? 1);
$blueRead = (int)($addons['custom6'] ?? 1);
$readLabel = (int)($addons['custom7'] ?? 1);
$scope = (($addons['custom8'] ?? 'all') === 'friends') ? 'friends' : 'all';
$minRank = (int)($addons['custom9'] ?? 0);
$psRankOptions = '';
foreach(rankList() as $rankValue){
    if($rankValue == botRank() || $rankValue == 999){
        continue;
    }
    $psRankOptions .= rankOption($minRank, $rankValue);
}
?>
<?php echo elementTitle('حالات الدردشة الخاصة', 'loadLob(\'admin/setting_addons.php\');'); ?>
<div class="page_full">
    <div class="page_element">
        <div class="config_section">
            <div class="setting_element">
                <p class="label">تشغيل الإضافة بالكامل</p>
                <select id="ps_enabled">
                    <option value="1" <?php echo selCurrent($enabled, 1); ?>>مفعلة</option>
                    <option value="0" <?php echo selCurrent($enabled, 0); ?>>معطلة</option>
                </select>
            </div>
            <div class="setting_element">
                <p class="label">تفعيل مؤشر «يكتب الآن» المتحرك</p>
                <select id="ps_typing">
                    <option value="1" <?php echo selCurrent($typing, 1); ?>>مفعل</option>
                    <option value="0" <?php echo selCurrent($typing, 0); ?>>معطل</option>
                </select>
            </div>
            <div class="setting_element">
                <p class="label">تفعيل حالة «متصل الآن»</p>
                <select id="ps_online">
                    <option value="1" <?php echo selCurrent($online, 1); ?>>مفعلة</option>
                    <option value="0" <?php echo selCurrent($online, 0); ?>>معطلة</option>
                </select>
            </div>
            <div class="setting_element">
                <p class="label">تفعيل عرض «آخر ظهور» عند عدم الاتصال</p>
                <select id="ps_last_seen">
                    <option value="1" <?php echo selCurrent($lastSeen, 1); ?>>مفعل</option>
                    <option value="0" <?php echo selCurrent($lastSeen, 0); ?>>معطل</option>
                </select>
            </div>
            <div class="setting_element">
                <p class="label">تفعيل علامتي الصح عند التسليم</p>
                <select id="ps_double_check">
                    <option value="1" <?php echo selCurrent($doubleCheck, 1); ?>>مفعل</option>
                    <option value="0" <?php echo selCurrent($doubleCheck, 0); ?>>معطل</option>
                </select>
            </div>
            <div class="setting_element">
                <p class="label">تفعيل الصحين باللون الأزرق عند القراءة</p>
                <select id="ps_blue_read">
                    <option value="1" <?php echo selCurrent($blueRead, 1); ?>>مفعل</option>
                    <option value="0" <?php echo selCurrent($blueRead, 0); ?>>معطل</option>
                </select>
            </div>
            <div class="setting_element">
                <p class="label">إظهار كلمة «مقروءة» بجانب الصحين</p>
                <select id="ps_read_label">
                    <option value="1" <?php echo selCurrent($readLabel, 1); ?>>مفعل</option>
                    <option value="0" <?php echo selCurrent($readLabel, 0); ?>>معطل</option>
                </select>
            </div>
        </div>

        <div class="config_section">
            <div class="setting_element">
                <p class="label">من يستطيع رؤية حالة الاتصال وآخر ظهور؟</p>
                <select id="ps_scope">
                    <option value="all" <?php echo selCurrent($scope, 'all'); ?>>عام: جميع الرتب المسموح لها</option>
                    <option value="friends" <?php echo selCurrent($scope, 'friends'); ?>>الأصدقاء فقط</option>
                </select>
                <p class="sub_text">عند اختيار الأصدقاء فقط، يجب أن تكون الصداقة مقبولة من الطرفين.</p>
            </div>
            <div class="setting_element">
                <p class="label">أقل رتبة تستطيع رؤية متصل الآن وآخر ظهور</p>
                <select id="ps_min_rank">
                    <?php echo $psRankOptions; ?>
                </select>
                <p class="sub_text">الرتبة المختارة وما فوقها تستطيع رؤية الحالة، مع مراعاة خيار الأصدقاء فقط.</p>
            </div>
            <button type="button" class="reg_button theme_btn tmargin10" onclick="savePrivateStatusSettings();">
                <i class="fa fa-floppy-o"></i> حفظ الإعدادات
            </button>
        </div>

        <div class="config_section">
            <p class="text_small">ملاحظات التشغيل</p>
            <ul>
                <li>يظهر اسم الحالة تحت اسم العضو في أعلى نافذة الخاص: «متصل الآن» أو «آخر ظهور منذ…».</li>
                <li>يظهر مؤشر «يكتب…» أسفل الدردشة في الجهة اليمنى دون صورة العضو.</li>
                <li>تظهر علامة واحدة للإرسال، وعلامتان للتسليم، وعلامتان زرقاوان عند فتح المحادثة وقراءة الرسائل.</li>
                <li>تُستخدم حقول الحالة الموجودة في جدول الرسائل الخاص، ولا يتم استبدال سجل المحادثات أو حذف الرسائل.</li>
            </ul>
        </div>
    </div>
</div>
<script data-cfasync="false">
function savePrivateStatusSettings(){
    $.post('addons/private_status/system/action.php', {
        save_private_status: 1,
        ps_enabled: $('#ps_enabled').val(),
        ps_typing: $('#ps_typing').val(),
        ps_online: $('#ps_online').val(),
        ps_last_seen: $('#ps_last_seen').val(),
        ps_double_check: $('#ps_double_check').val(),
        ps_blue_read: $('#ps_blue_read').val(),
        ps_read_label: $('#ps_read_label').val(),
        ps_scope: $('#ps_scope').val(),
        ps_min_rank: $('#ps_min_rank').val(),
        token: (typeof utk !== 'undefined' ? utk : '')
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
