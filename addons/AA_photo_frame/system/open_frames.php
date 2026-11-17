<?php
$load_addons = 'AA_photo_frame';
require_once('../../../system/config_addons.php');
require_once(__DIR__ . '/frame_helpers.php');
require_once __DIR__ . '/../../../system/addon_menu_labels.php';
$aaFramesTitle = addonMenuLabelGet('AA_photo_frame', 'متجر الإطارات');

if (!boomAllow((int)$addons['addons_access'])) {
    die();
}
$targetId = (int)$data['user_id'];
$locked = aaFrameLockedUser($targetId);
if ($locked) {
    echo '<div class="pad15 centered_element"><i class="fa fa-lock"></i><div class="tpad10">هذا الإطار مثبت على رتبتك ولا يمكن تغييره أو إزالته إلا من إدارة الإضافة.</div></div>';
    exit;
}
$current = '';
$currentResult = $mysqli->query("SELECT frame_name FROM photo_frames WHERE user_id = '$targetId' LIMIT 1");
if ($currentResult && $currentResult->num_rows) {
    $currentRow = $currentResult->fetch_assoc();
    $current = aaSafeFrame($currentRow['frame_name']) ?: '';
}
$frames = aaFrameFiles();
$scale = aaFrameScale();
?>
<div class="brow">
    <div class="bcell border_bottom">
        <div class="modal_top_menu">
            <div class="bcell_mid hpad15"><p class="label"><i class="fa fa-image bgrad27"></i> <?php echo htmlspecialchars($aaFramesTitle, ENT_QUOTES, 'UTF-8'); ?></p></div>
            <div class="modal_top_menu_empty"></div>
            <div class="cancel_modal cover_text modal_top_item"><i class="fa fa-times"></i></div>
        </div>
    </div>
</div>
<div class="pad10">
    <p class="label">اختر الإطار الذي تريد وضعه على صورتك الشخصية</p>
    <div class="aa-frame-gallery">
        <div class="gift-responsive aa-frame-choice" onclick="aaChoosePersonalFrame('')">
            <div class="gift-container"><div class="aa-frame-thumb"><i class="fa fa-ban"></i></div><div class="gift-desc">إزالة الإطار</div></div>
        </div>
        <?php foreach ($frames as $frame) { $url = aaFrameImageUrl($frame); ?>
        <div class="gift-responsive aa-frame-choice <?php echo $current === $frame ? 'aa-frame-selected' : ''; ?>" onclick="aaChoosePersonalFrame(<?php echo htmlspecialchars(json_encode($frame), ENT_QUOTES, 'UTF-8'); ?>)">
            <div class="gift-container"><div class="aa-frame-thumb"><img src="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy"></div><div class="gift-desc"><?php echo htmlspecialchars(pathinfo($frame, PATHINFO_FILENAME), ENT_QUOTES, 'UTF-8'); ?></div></div>
        </div>
        <?php } ?>
    </div>
</div>
<script data-cfasync="false" type="text/javascript">
window.aaChoosePersonalFrame = function(frame) {
    var payload = frame === '' ? { del_frame_target: 1, token: utk } : { set_photo_frame: frame, token: utk };
    $.post('addons/AA_photo_frame/system/action.php', payload, function(response) {
        response = $.trim(String(response));
        if (response === '1') {
            callSaved(system.saved, 1);
            if (window.AAFrameRefresh) window.AAFrameRefresh();
            if (typeof userReload === 'function') userReload(1);
            if (typeof hideModal === 'function') hideModal();
        } else if (response === 'LOCKED') {
            callSaved('هذا الإطار مثبت على رتبتك من الإدارة', 3);
        } else {
            callSaved(system.error, 3);
        }
    });
};
</script>
