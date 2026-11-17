<?php
$load_addons = 'AA_photo_frame';
require_once('../../../system/config_addons.php');
require_once(__DIR__ . '/frame_helpers.php');

$staffAccess = aaFrameStaffAccess($addons);
if (!boomAllow($staffAccess)) {
    die();
}
$targetId = isset($_POST['target']) ? (int)$_POST['target'] : 0;
if ($targetId < 1) {
    die();
}
$user = userDetails($targetId);
if (empty($user) || (int)$data['user_rank'] <= (int)$user['user_rank']) {
    die();
}
$locked = aaFrameLockedUser($targetId);
$current = '';
$currentResult = $mysqli->query("SELECT frame_name FROM photo_frames WHERE user_id = '$targetId' LIMIT 1");
if ($currentResult && $currentResult->num_rows) {
    $currentRow = $currentResult->fetch_assoc();
    $current = aaSafeFrame($currentRow['frame_name']) ?: '';
}
$frames = aaFrameFiles();
?>
<div class="brow">
    <div class="bcell border_bottom">
        <div class="modal_top_menu">
            <div class="bcell_mid hpad15"><p class="label"><i class="fa fa-image bgrad27"></i> إدارة إطار العضو: <?php echo htmlspecialchars($user['user_name'], ENT_QUOTES, 'UTF-8'); ?></p></div>
            <div class="modal_top_menu_empty"></div>
            <div class="cancel_modal cover_text modal_top_item"><i class="fa fa-times"></i></div>
        </div>
    </div>
</div>
<div class="pad10">
    <div class="found_user_frame" data="<?php echo $targetId; ?>"></div>
    <?php if ($locked) { ?>
        <div class="centered_element pad15"><i class="fa fa-lock"></i><div class="tpad10">هذا العضو لديه إطار إجباري مرتبط برتبته. لا يمكن تغييره من هنا؛ ألغِ تثبيت الرتبة أولاً من إدارة الإضافة.</div></div>
    <?php } else { ?>
        <p class="label">اختر إطاراً للعضو أو أزل إطاره الشخصي</p>
        <div class="aa-frame-gallery">
            <div class="gift-responsive aa-frame-choice" onclick="aaChooseStaffFrame('')"><div class="gift-container"><div class="aa-frame-thumb"><i class="fa fa-ban"></i></div><div class="gift-desc">إزالة الإطار</div></div></div>
            <?php foreach ($frames as $frame) { $url = aaFrameImageUrl($frame); ?>
            <div class="gift-responsive aa-frame-choice <?php echo $current === $frame ? 'aa-frame-selected' : ''; ?>" onclick="aaChooseStaffFrame(<?php echo htmlspecialchars(json_encode($frame), ENT_QUOTES, 'UTF-8'); ?>)">
                <div class="gift-container"><div class="aa-frame-thumb"><img src="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy"></div><div class="gift-desc"><?php echo htmlspecialchars(pathinfo($frame, PATHINFO_FILENAME), ENT_QUOTES, 'UTF-8'); ?></div></div>
            </div>
            <?php } ?>
        </div>
    <?php } ?>
</div>
<script data-cfasync="false" type="text/javascript">
window.aaChooseStaffFrame = function(frame) {
    var target = $('.found_user_frame').attr('data');
    var payload = { frame_target_staff: target, token: utk };
    if (frame === '') payload.del_frame_target_staff = target;
    else payload.set_photo_frame_staff = frame;
    $.post('addons/AA_photo_frame/system/action.php', payload, function(response) {
        response = $.trim(String(response));
        if (response === '1') {
            callSaved(system.saved, 1);
            if (window.AAFrameRefresh) window.AAFrameRefresh();
            if (typeof userReload === 'function') userReload(1);
            if (typeof hideModal === 'function') hideModal();
        } else if (response === 'LOCKED') {
            callSaved('لا يمكن تغيير إطار إجباري مثبت على رتبة العضو', 3);
        } else {
            callSaved(system.error, 3);
        }
    });
};
</script>
