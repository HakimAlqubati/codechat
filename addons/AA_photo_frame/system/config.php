<?php
$load_addons = 'AA_photo_frame';
require_once('../../../system/config_addons.php');
require_once __DIR__ . '/../../../system/addon_menu_labels.php';
if (!canManageAddons()) {
    die();
}
require_once(__DIR__ . '/frame_helpers.php');
aaEnsureFrameSchema();
$staffAccess = aaFrameStaffAccess($addons);
$scale = aaFrameScale();
$frames = aaFrameFiles();
$rules = array();
$ruleQuery = $mysqli->query("SELECT rule_id, rank_id, frame_name FROM photo_frame_rank_rules ORDER BY rank_id DESC");
if ($ruleQuery) {
    while ($rule = $ruleQuery->fetch_assoc()) {
        $rule['frame_name'] = aaSafeFrame($rule['frame_name']) ?: '';
        $rules[] = $rule;
    }
}
?>
<style>
.aa-frame-admin .aa-frame-slider-line{display:flex;align-items:center;gap:10px;direction:rtl}
.aa-frame-admin .aa-frame-slider-line input[type=range]{flex:1;min-width:80px;accent-color:var(--theme-color,#7c3aed)}
.aa-frame-admin .aa-frame-size-preview-wrap{min-height:175px;display:flex;align-items:center;justify-content:center;padding:18px}
.aa-frame-admin .aa-frame-size-preview{width:100px;height:100px;position:relative;border-radius:50%;overflow:visible}
.aa-frame-admin .aa-frame-size-avatar{display:block;width:100%;height:100%;border-radius:50%;object-fit:cover}
.aa-frame-admin .aa-frame-size-preview>.aa-frame-preview-frame{position:absolute;top:50%;left:50%;width:116%;height:116%;transform:translate(-50%,-50%);object-fit:contain;pointer-events:none}
.aa-frame-admin .aa-frame-rules-list{margin-top:15px}
.aa-frame-admin .aa-frame-rule-row{gap:10px;align-items:center}
.aa-frame-admin .aa-frame-rule-row .sub_list_name small{display:block;opacity:.7;font-size:11px}
.aa-frame-admin .aa-frame-locations{line-height:2}
</style>
<?php echo elementTitle($addons['addons'], 'loadLob(\'admin/setting_addons.php\');'); ?>
<?php echo addonMenuLabelSettingsBlock('AA_photo_frame', 'متجر الإطارات', 'يظهر تحت تصنيف المتاجر بعد الملف الشخصي.'); ?>

<div class="page_full aa-frame-admin">
    <div class="page_element">
        <div class="pad15">
            <div class="setting_element">
                <p class="label">أقل رتبة يسمح لها باختيار إطار شخصي</p>
                <select id="aa_frame_access">
                    <?php if ((int)$addons['addons_access'] === 11) { ?><option value="11" selected>الرتبة 11 فأعلى (القيمة الحالية)</option><?php } ?>
                    <?php echo listRank((int)$addons['addons_access']); ?>
                </select>
            </div>
            <div class="setting_element">
                <p class="label">أقل رتبة يسمح لها بتغيير إطارات الأعضاء</p>
                <select id="aa_frame_staff_access">
                    <?php foreach (rankList() as $rankValue) { if ($rankValue >= 70 && $rankValue <= 100) { ?>
                    <option value="<?php echo (int)$rankValue; ?>" <?php echo selCurrent($staffAccess, $rankValue); ?>><?php echo htmlspecialchars(rankTitle($rankValue), ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php }} ?>
                </select>
            </div>
            <div class="setting_element">
                <p class="label">حجم الإطار فوق صورة العضو: <strong><span id="aa_frame_scale_value"><?php echo $scale; ?></span>%</strong></p>
                <div class="aa-frame-slider-line">
                    <span>أصغر</span>
                    <input type="range" id="aa_frame_scale" min="90" max="180" step="1" value="<?php echo $scale; ?>" oninput="aaPreviewFrameScale(this.value)">
                    <span>أكبر</span>
                </div>
                <div class="aa-frame-size-preview-wrap">
                    <div class="aa-frame-size-preview" id="aa_frame_size_preview">
                        <img class="aa-frame-size-avatar" src="default_images/avatar/default_avatar.png" alt="صورة تجريبية للعضو">
                        <img class="aa-frame-preview-frame" src="addons/AA_photo_frame/files/frame/crown-frame.png" alt="">
                    </div>
                </div>
                <p class="sub_text">يُطبّق الحجم على كل مواضع عرض الإطارات، ويمكن تكبيره أو تصغيره بسحب المؤشر الأفقي.</p>
            </div>
            <button onclick="aaSaveFrameSettings();" type="button" class="clear_top reg_button theme_btn"><i class="fa fa-floppy-o"></i> حفظ الإعدادات</button>
        </div>
        <div class="divider"></div>
        <div class="pad15">
            <h3 class="label">تثبيت إطار إجباري على رتبة</h3>
            <p class="sub_text">سيظهر الإطار المختار لكل عضو يحمل الرتبة. لا يستطيع العضو تغييره أو إزالته، وتعود إطاراته الشخصية بعد إلغاء قاعدة التثبيت.</p>
            <div class="setting_element">
                <p class="label">الرتبة</p>
                <select id="aa_frame_rank">
                    <?php foreach (rankList() as $rankValue) { if ($rankValue > 0 && $rankValue < 999 && $rankValue != botRank()) { ?>
                    <option value="<?php echo (int)$rankValue; ?>"><?php echo htmlspecialchars(rankTitle($rankValue), ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php }} ?>
                </select>
            </div>
            <div class="setting_element">
                <p class="label">الإطار الإجباري</p>
                <select id="aa_frame_rank_file">
                    <?php foreach ($frames as $frame) { ?>
                    <option value="<?php echo htmlspecialchars($frame, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(pathinfo($frame, PATHINFO_FILENAME), ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php } ?>
                </select>
            </div>
            <button onclick="aaSaveRankFrame();" type="button" class="clear_top reg_button theme_btn"><i class="fa fa-lock"></i> تثبيت الإطار على الرتبة</button>
            <div class="aa-frame-rules-list" id="aa_frame_rules_list">
                <?php if (empty($rules)) { ?>
                    <div class="sub_list_item">لا توجد إطارات إجبارية مثبتة حالياً.</div>
                <?php } else { foreach ($rules as $rule) { ?>
                    <div class="sub_list_item aa-frame-rule-row" data-rule="<?php echo (int)$rule['rule_id']; ?>">
                        <div class="sub_list_avatar"><img src="<?php echo htmlspecialchars(aaFrameImageUrl($rule['frame_name']), ENT_QUOTES, 'UTF-8'); ?>" alt=""></div>
                        <div class="sub_list_name addons_name"><?php echo htmlspecialchars(rankTitle((int)$rule['rank_id']), ENT_QUOTES, 'UTF-8'); ?><small><?php echo htmlspecialchars($rule['frame_name'], ENT_QUOTES, 'UTF-8'); ?></small></div>
                        <button type="button" class="button delete_btn" onclick="aaDeleteRankFrame(<?php echo (int)$rule['rule_id']; ?>, this)"><i class="fa fa-trash"></i> إلغاء التثبيت</button>
                    </div>
                <?php }} ?>
            </div>
        </div>
        <div class="divider"></div>
        <div class="pad15">
            <h3 class="label">أماكن ظهور الإطار</h3>
            <div class="aa-frame-locations">العام · المتصلون · الأخبار والتعليقات · حائط الأصدقاء والتعليقات · الملف الشخصي · التصنيفات والمتصدرون</div>
            <p class="sub_text">تظهر الإطارات تلقائياً في هذه المواضع عند وجود معرف العضو في قالب العرض.</p>
        </div>
    </div>
</div>
<script data-cfasync="false" type="text/javascript">
function aaPreviewFrameScale(value) {
    value = Math.max(90, Math.min(180, parseInt(value, 10) || 116));
    $('#aa_frame_scale_value').text(value);
    $('#aa_frame_size_preview .aa-frame-preview-frame').css({width: value + '%', height: value + '%'});
}
function aaFramePost(data, done) {
    data.token = utk;
    $.post('addons/AA_photo_frame/system/action.php', data, function(response) {
        response = $.trim(String(response));
        if (response === '1') {
            callSaved(system.saved, 1);
            if (typeof done === 'function') done();
        } else {
            callSaved(system.error, 3);
        }
    });
}
function aaSaveFrameSettings() {
    aaFramePost({
        save_frame_settings: 1,
        set_addon_access: $('#aa_frame_access').val(),
        set_addon_access_staff: $('#aa_frame_staff_access').val(),
        frame_scale: $('#aa_frame_scale').val()
    }, function() { loadLob('addons/AA_photo_frame/system/config.php'); });
}
function aaSaveRankFrame() {
    aaFramePost({
        save_rank_frame: 1,
        rank_id: $('#aa_frame_rank').val(),
        frame_name: $('#aa_frame_rank_file').val()
    }, function() { loadLob('addons/AA_photo_frame/system/config.php'); });
}
function aaDeleteRankFrame(ruleId, button) {
    aaFramePost({delete_rank_frame: 1, rule_id: ruleId}, function() {
        $(button).closest('.aa-frame-rule-row').remove();
        if ($('#aa_frame_rules_list .aa-frame-rule-row').length === 0) {
            $('#aa_frame_rules_list').html('<div class="sub_list_item">لا توجد إطارات إجبارية مثبتة حالياً.</div>');
        }
    });
}
aaPreviewFrameScale($('#aa_frame_scale').val());
</script>
