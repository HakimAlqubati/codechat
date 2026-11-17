<?php
$load_addons = 'AA_photo_frame';
require_once('../../../system/config_addons.php');
require_once(__DIR__ . '/frame_helpers.php');

function aaFrameReply($value) {
    echo $value;
    exit;
}
function aaCanManageFrameSettings() {
    return canManageAddons();
}
function aaFrameRankIsAllowed($rank) {
    $rank = (int)$rank;
    return $rank > 0 && $rank !== 69 && function_exists('validRank') && validRank($rank);
}
function aaFrameUpdatePersonal($userId, $frameName) {
    global $mysqli;
    $userId = (int)$userId;
    $frameName = $mysqli->real_escape_string($frameName);
    return $mysqli->query("INSERT INTO photo_frames (user_id, frame_name) VALUES ('$userId', '$frameName') ON DUPLICATE KEY UPDATE frame_name = VALUES(frame_name)");
}
function aaFrameDeletePersonal($userId) {
    global $mysqli;
    $userId = (int)$userId;
    return $mysqli->query("DELETE FROM photo_frames WHERE user_id = '$userId'");
}

if (isset($_POST['save_frame_settings'])) {
    if (!aaCanManageFrameSettings()) {
        aaFrameReply('0');
    }
    $access = isset($_POST['set_addon_access']) ? (int)$_POST['set_addon_access'] : (int)$addons['addons_access'];
    $staffAccess = isset($_POST['set_addon_access_staff']) ? (int)$_POST['set_addon_access_staff'] : aaFrameStaffAccess($addons);
    $scale = isset($_POST['frame_scale']) ? (int)$_POST['frame_scale'] : aaFrameScale();
    if (!function_exists('validRank') || (!validRank($access) && $access !== (int)$addons['addons_access']) || $access < 0 || $access > 100 || !in_array($staffAccess, array(70, 80, 90, 100), true)) {
        aaFrameReply('0');
    }
    $scale = max(90, min(180, $scale));
    $access = (int)$access;
    $staffAccess = (int)$staffAccess;
    $addonSaved = $mysqli->query("UPDATE boom_addons SET addons_access = '$access', custom1 = '$staffAccess' WHERE addons = 'AA_photo_frame'");
    $scaleSaved = $mysqli->query("UPDATE photo_frame_settings SET frame_scale = '$scale' WHERE setting_id = 1");
    if (!$addonSaved || !$scaleSaved) {
        aaFrameReply('0');
    }
    redisUpdateAddons('AA_photo_frame');
    aaFrameReply('1');
}

if (isset($_POST['save_rank_frame'])) {
    if (!aaCanManageFrameSettings()) {
        aaFrameReply('0');
    }
    $rank = isset($_POST['rank_id']) ? (int)$_POST['rank_id'] : -1;
    $frame = aaSafeFrame($_POST['frame_name'] ?? '');
    if (!aaFrameRankIsAllowed($rank) || !$frame) {
        aaFrameReply('0');
    }
    $rank = (int)$rank;
    $frame = $mysqli->real_escape_string($frame);
    $now = time();
    $saved = $mysqli->query("INSERT INTO photo_frame_rank_rules (rank_id, frame_name, created_at) VALUES ('$rank', '$frame', '$now') ON DUPLICATE KEY UPDATE frame_name = VALUES(frame_name), created_at = VALUES(created_at)");
    aaFrameReply($saved ? '1' : '0');
}

if (isset($_POST['delete_rank_frame'])) {
    if (!aaCanManageFrameSettings()) {
        aaFrameReply('0');
    }
    $ruleId = isset($_POST['rule_id']) ? (int)$_POST['rule_id'] : 0;
    if ($ruleId < 1) {
        aaFrameReply('0');
    }
    $deleted = $mysqli->query("DELETE FROM photo_frame_rank_rules WHERE rule_id = '$ruleId'");
    aaFrameReply($deleted ? '1' : '0');
}

if (isset($_POST['set_photo_frame'])) {
    if (!boomAllow((int)$addons['addons_access'])) {
        aaFrameReply('0');
    }
    if (aaFrameLockedUser((int)$data['user_id'])) {
        aaFrameReply('LOCKED');
    }
    $frame = aaSafeFrame($_POST['set_photo_frame']);
    if (!$frame) {
        aaFrameReply('0');
    }
    aaFrameReply(aaFrameUpdatePersonal((int)$data['user_id'], $frame) ? '1' : '0');
}

if (isset($_POST['del_frame_target'])) {
    if (!boomAllow((int)$addons['addons_access'])) {
        aaFrameReply('0');
    }
    if (aaFrameLockedUser((int)$data['user_id'])) {
        aaFrameReply('LOCKED');
    }
    aaFrameReply(aaFrameDeletePersonal((int)$data['user_id']) ? '1' : '0');
}

if (isset($_POST['set_photo_frame_staff']) || isset($_POST['del_frame_target_staff'])) {
    $staffAccess = aaFrameStaffAccess($addons);
    if (!boomAllow($staffAccess)) {
        aaFrameReply('0');
    }
    $target = isset($_POST['frame_target_staff']) ? (int)$_POST['frame_target_staff'] : (int)($_POST['del_frame_target_staff'] ?? 0);
    $targetRank = $target > 0 ? aaFrameUserRank($target) : false;
    if ($target < 1 || $targetRank === false || (int)$data['user_rank'] <= (int)$targetRank) {
        aaFrameReply('0');
    }
    if (aaFrameLockedUser($target)) {
        aaFrameReply('LOCKED');
    }
    if (isset($_POST['set_photo_frame_staff'])) {
        $frame = aaSafeFrame($_POST['set_photo_frame_staff']);
        if (!$frame) {
            aaFrameReply('0');
        }
        aaFrameReply(aaFrameUpdatePersonal($target, $frame) ? '1' : '0');
    }
    aaFrameReply(aaFrameDeletePersonal($target) ? '1' : '0');
}

aaFrameReply('0');
?>
