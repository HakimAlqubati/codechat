<?php
$load_addons = 'finger_print';
require_once('../../../system/config_addons.php');
require_once(__DIR__ . '/runtime.php');

function fpActionFail($message = '0') { echo $message; exit; }
function fpCanManageTarget($user) {
    global $data, $addons;
    if (empty($user) || !boomAllow((int) $addons['addons_access'])) { return false; }
    if ((int) $user['user_id'] === (int) $data['user_id']) { return false; }
    return isGreater($user['user_rank']);
}
function fpSafeText($value, $max = 500) {
    $value = trim(strip_tags((string) $value));
    if (function_exists('mb_substr')) { return mb_substr($value, 0, $max, 'UTF-8'); }
    return substr($value, 0, $max);
}

if (isset($_POST['fp_save_settings'])) {
    if (!boomAllow(100)) { fpActionFail(); }
    $access = isset($_POST['fp_access']) ? (int) $_POST['fp_access'] : 80;
    $validRanks = array(0,1,50,69,70,80,90,100);
    if (!in_array($access, $validRanks, true)) { fpActionFail(); }
    $vpnMonitor = !empty($_POST['fp_vpn_monitor']) ? 1 : 0;
    $vpnLog = !empty($_POST['fp_vpn_log']) ? 1 : 0;
    $mysqli->query("UPDATE boom_addons SET addons_access = '$access' WHERE addons = 'finger_print'");
    $stmt = $mysqli->prepare("UPDATE boom_fp_settings SET vpn_monitor = ?, vpn_log = ? WHERE id = 1");
    if (!$stmt) { fpActionFail(); }
    $stmt->bind_param('ii', $vpnMonitor, $vpnLog);
    $ok = $stmt->execute();
    $stmt->close();
    if (function_exists('redisFlushAll')) { redisFlushAll(); }
    echo $ok ? '1' : '0';
    exit;
}

if (isset($_POST['fp_ban_user'])) {
    $targetId = (int) $_POST['fp_ban_user'];
    $user = userDetails($targetId);
    if (!fpCanManageTarget($user)) { fpActionFail(); }
    $fingerprint = isset($user['parmakizi']) ? trim($user['parmakizi']) : '';
    if ($fingerprint === '') { fpActionFail('100'); }
    $reason = fpSafeText(isset($_POST['reason']) ? $_POST['reason'] : '', 500);
    $fpSafe = $mysqli->real_escape_string($fingerprint);
    $existing = $mysqli->query("SELECT id FROM boom_permabanned WHERE parmakizleri = '$fpSafe' LIMIT 1");
    if ($existing && $existing->num_rows > 0) { fpActionFail('2'); }

    $stmt = $mysqli->prepare("INSERT INTO boom_permabanned (user_name,ip,ban_user,ban_hunter,parmakizleri,reason,ban_date) VALUES (?,?,?,?,?,?,?)");
    if (!$stmt) { fpActionFail(); }
    $targetName = $user['user_name'];
    $ip = $user['user_ip'];
    $hunter = $data['user_name'];
    $actorId = (int) $data['user_id'];
    $date = time();
    $stmt->bind_param('ssisssi', $targetName, $ip, $targetId, $hunter, $fingerprint, $reason, $date);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) { fpActionFail(); }
    // A new cemetery decision overrides any older account-specific exceptions for this hash.
    $clearExceptions = $mysqli->prepare("DELETE FROM boom_fp_exceptions WHERE fingerprint = ?");
    if ($clearExceptions) { $clearExceptions->bind_param('s', $fingerprint); $clearExceptions->execute(); $clearExceptions->close(); }
    fpHistory('cemetery_add', $fingerprint, $targetId, $targetName, $actorId, $hunter, $ip, $reason);
    echo '1';
    exit;
}

// Backwards-compatible request names from the original add-on.
if (isset($_POST['target_fing_user'])) {
    $_POST['fp_ban_user'] = $_POST['target_fing_user'];
    $targetId = (int) $_POST['fp_ban_user'];
    $user = userDetails($targetId);
    if (!fpCanManageTarget($user)) { fpActionFail(); }
    $fingerprint = isset($user['parmakizi']) ? trim($user['parmakizi']) : '';
    if ($fingerprint === '') { fpActionFail('100'); }
    $safe = $mysqli->real_escape_string($fingerprint);
    $exists = $mysqli->query("SELECT id FROM boom_permabanned WHERE parmakizleri = '$safe' LIMIT 1");
    if (!$exists || $exists->num_rows < 1) {
        $name = $user['user_name']; $ip = $user['user_ip']; $hunter = $data['user_name']; $date = time();
        $stmt = $mysqli->prepare("INSERT INTO boom_permabanned (user_name,ip,ban_user,ban_hunter,parmakizleri,ban_date) VALUES (?,?,?,?,?,?)");
        if (!$stmt) { fpActionFail(); }
        $stmt->bind_param('ssissi', $name, $ip, $targetId, $hunter, $fingerprint, $date);
        $stmt->execute(); $stmt->close();
        $clearExceptions = $mysqli->prepare("DELETE FROM boom_fp_exceptions WHERE fingerprint = ?");
        if ($clearExceptions) { $clearExceptions->bind_param('s', $fingerprint); $clearExceptions->execute(); $clearExceptions->close(); }
        fpHistory('cemetery_add', $fingerprint, $targetId, $name, (int) $data['user_id'], $hunter, $ip, 'حظر بالبصمة');
    }
    echo '1'; exit;
}

if (isset($_POST['fp_unban'], $_POST['finger_id'])) {
    if (!boomAllow((int) $addons['addons_access'])) { fpActionFail(); }
    $fingerId = (int) $_POST['finger_id'];
    $stmt = $mysqli->prepare("SELECT * FROM boom_permabanned WHERE id = ? LIMIT 1");
    if (!$stmt) { fpActionFail(); }
    $stmt->bind_param('i', $fingerId); $stmt->execute(); $res = $stmt->get_result();
    $record = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : array(); $stmt->close();
    if (empty($record)) { fpActionFail(); }

    // One fingerprint is one sanction: remove duplicate legacy rows together.
    $fingerprint = $record['parmakizleri'];
    $allStmt = $mysqli->prepare("SELECT ban_user, ban_date FROM boom_permabanned WHERE parmakizleri = ?");
    if (!$allStmt) { fpActionFail(); }
    $allStmt->bind_param('s', $fingerprint); $allStmt->execute(); $allRows = $allStmt->get_result();
    $legacyUsers = array();
    while ($allRows && ($legacy = $allRows->fetch_assoc())) {
        if ((int) $legacy['ban_date'] === 0 && (int) $legacy['ban_user'] > 0) { $legacyUsers[] = (int) $legacy['ban_user']; }
    }
    $allStmt->close();

    $deleteStmt = $mysqli->prepare("DELETE FROM boom_permabanned WHERE parmakizleri = ?");
    $deleteStmt->bind_param('s', $fingerprint); $ok = $deleteStmt->execute(); $deleteStmt->close();
    // Restore CodyChat's own ban state only for records produced by the legacy add-on.
    foreach (array_unique($legacyUsers) as $legacyUser) {
        $legacyData = userDetails($legacyUser);
        if (!empty($legacyData) && function_exists('systemUnban')) {
            systemUnban($legacyData);
        } else {
            $mysqli->query("UPDATE boom_users SET user_banned = 0, ban_msg = '', user_action = user_action + 1 WHERE user_id = '$legacyUser'");
            $mysqli->query("DELETE FROM boom_banned WHERE ban_user = '$legacyUser'");
            if (function_exists('redisUpdateUser')) { redisUpdateUser($legacyUser); }
        }
    }
    fpHistory('cemetery_remove', $fingerprint, (int) $record['ban_user'], $record['user_name'], (int) $data['user_id'], $data['user_name'], $record['ip'], 'فك الحظر وحذف جميع سجلات البصمة من المقبرة');
    echo $ok ? '1' : '0'; exit;
}

if (isset($_POST['fp_add_exception'])) {
    $targetId = (int) $_POST['fp_add_exception'];
    $user = userDetails($targetId);
    if (!fpCanManageTarget($user)) { fpActionFail(); }
    $fingerprint = isset($user['parmakizi']) ? trim($user['parmakizi']) : '';
    if ($fingerprint === '') { fpActionFail('100'); }
    $reason = fpSafeText(isset($_POST['reason']) ? $_POST['reason'] : '', 500);
    $actorId = (int) $data['user_id'];
    $actorName = $data['user_name'];
    $createdAt = time();
    $stmt = $mysqli->prepare("INSERT INTO boom_fp_exceptions (user_id,user_name,fingerprint,reason,created_by,created_by_name,created_at,active) VALUES (?,?,?,?,?,?,?,1) ON DUPLICATE KEY UPDATE user_name=VALUES(user_name), reason=VALUES(reason), created_by=VALUES(created_by), created_by_name=VALUES(created_by_name), created_at=VALUES(created_at), active=1");
    if (!$stmt) { fpActionFail(); }
    $targetName = $user['user_name'];
    $stmt->bind_param('isssisi', $targetId, $targetName, $fingerprint, $reason, $actorId, $actorName, $createdAt);
    $ok = $stmt->execute(); $stmt->close();
    if (!$ok) { fpActionFail(); }
    fpHistory('cemetery_exception_add', $fingerprint, $targetId, $targetName, $actorId, $actorName, $user['user_ip'], $reason);
    echo '1'; exit;
}

if (isset($_POST['fp_remove_exception'], $_POST['exception_id'])) {
    if (!boomAllow((int) $addons['addons_access'])) { fpActionFail(); }
    $exceptionId = (int) $_POST['exception_id'];
    $stmt = $mysqli->prepare("SELECT * FROM boom_fp_exceptions WHERE id = ? LIMIT 1");
    if (!$stmt) { fpActionFail(); }
    $stmt->bind_param('i', $exceptionId); $stmt->execute(); $res = $stmt->get_result();
    $exception = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : array(); $stmt->close();
    if (empty($exception)) { fpActionFail(); }
    $stmt = $mysqli->prepare("DELETE FROM boom_fp_exceptions WHERE id = ?");
    $stmt->bind_param('i', $exceptionId); $ok = $stmt->execute(); $stmt->close();
    fpHistory('cemetery_exception_remove', $exception['fingerprint'], (int) $exception['user_id'], $exception['user_name'], (int) $data['user_id'], $data['user_name'], '', 'تم إلغاء استثناء الحساب من المقبرة');
    echo $ok ? '1' : '0'; exit;
}

if (isset($_POST['fp_jail_user'])) {
    $targetId = (int) $_POST['fp_jail_user'];
    $user = userDetails($targetId);
    if (!fpCanManageTarget($user)) { fpActionFail(); }
    $fingerprint = isset($user['parmakizi']) ? trim($user['parmakizi']) : '';
    if ($fingerprint === '') { fpActionFail('100'); }
    $fpSafe = $mysqli->real_escape_string($fingerprint);
    $cemetery = $mysqli->query("SELECT id FROM boom_permabanned WHERE parmakizleri = '$fpSafe' LIMIT 1");
    if ($cemetery && $cemetery->num_rows > 0 && !fpHasCemeteryException($targetId, $fingerprint)) { fpActionFail('101'); }
    $duration = isset($_POST['duration']) ? (int) $_POST['duration'] : 60;
    $allowedDurations = array(0,10,30,60,180,360,720,1440,4320,10080,43200);
    if (!in_array($duration, $allowedDurations, true)) { fpActionFail(); }
    $reason = fpSafeText(isset($_POST['reason']) ? $_POST['reason'] : '', 500);
    $roomId = fpEnsureJailRoom();
    if ($roomId < 1) { fpActionFail(); }
    $now = time();
    $expires = ($duration === 0) ? 0 : $now + ($duration * 60);
    $mysqli->query("UPDATE boom_fp_jail SET active = 0, released_at = '$now', release_reason = 'استبدلت بعقوبة أحدث' WHERE fingerprint = '$fpSafe' AND active = 1");
    $stmt = $mysqli->prepare("INSERT INTO boom_fp_jail (fingerprint,target_user,target_name,reason,created_by,created_by_name,created_at,expires_at,active) VALUES (?,?,?,?,?,?,?,?,1)");
    if (!$stmt) { fpActionFail(); }
    $actorId = (int) $data['user_id']; $actorName = $data['user_name']; $targetName = $user['user_name'];
    $stmt->bind_param('sissisii', $fingerprint, $targetId, $targetName, $reason, $actorId, $actorName, $now, $expires);
    $ok = $stmt->execute(); $stmt->close();
    if (!$ok) { fpActionFail(); }
    fpMoveFingerprintAccounts($fingerprint, $roomId);
    fpHistory('jail_add', $fingerprint, $targetId, $targetName, $actorId, $actorName, $user['user_ip'], $reason . ' | مدة بالدقائق: ' . $duration);
    echo '1'; exit;
}

if (isset($_POST['fp_release_jail'], $_POST['jail_id'])) {
    if (!boomAllow((int) $addons['addons_access'])) { fpActionFail(); }
    $jailId = (int) $_POST['jail_id'];
    $stmt = $mysqli->prepare("SELECT * FROM boom_fp_jail WHERE id = ? AND active = 1 LIMIT 1");
    if (!$stmt) { fpActionFail(); }
    $stmt->bind_param('i', $jailId); $stmt->execute(); $res = $stmt->get_result();
    $record = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : array(); $stmt->close();
    if (empty($record)) { fpActionFail(); }
    $now = time(); $actorId = (int) $data['user_id']; $actorName = $data['user_name'];
    $stmt = $mysqli->prepare("UPDATE boom_fp_jail SET active = 0, released_by = ?, released_by_name = ?, released_at = ?, release_reason = 'فك يدوي بواسطة الإدارة' WHERE fingerprint = ? AND active = 1");
    $fingerprint = $record['fingerprint'];
    $stmt->bind_param('isis', $actorId, $actorName, $now, $fingerprint); $ok = $stmt->execute(); $stmt->close();
    fpReleaseFingerprintAccounts($record['fingerprint'], fpEnsureJailRoom());
    fpHistory('jail_release', $record['fingerprint'], (int) $record['target_user'], $record['target_name'], $actorId, $actorName, '', 'تم فك السجن يدوياً');
    echo $ok ? '1' : '0'; exit;
}

fpActionFail();
?>
