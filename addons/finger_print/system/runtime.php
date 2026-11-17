<?php
/**
 * Runtime bridge for the Fingerprint Security add-on.
 * Included by CodyChat session entry points only when the add-on is installed.
 */
if (!function_exists('fpAddonIsActive')) {
    function fpAddonIsActive() {
        global $mysqli;
        static $active = null;
        if ($active !== null) { return $active; }
        if (!isset($mysqli) || !($mysqli instanceof mysqli)) { $active = false; return false; }
        $res = $mysqli->query("SELECT addons_id FROM boom_addons WHERE addons = 'finger_print' LIMIT 1");
        $active = ($res && $res->num_rows > 0);
        return $active;
    }
}

if (!function_exists('fpSettings')) {
    function fpSettings() {
        global $mysqli;
        static $settings = null;
        if ($settings !== null) { return $settings; }
        $settings = array('jail_room_id' => 0, 'vpn_monitor' => 1, 'vpn_log' => 1);
        if (isset($mysqli) && $mysqli instanceof mysqli) {
            $res = $mysqli->query("SELECT * FROM boom_fp_settings WHERE id = 1 LIMIT 1");
            if ($res && $res->num_rows > 0) {
                $settings = array_merge($settings, $res->fetch_assoc());
            }
        }
        return $settings;
    }
}

if (!function_exists('fpHistory')) {
    function fpHistory($eventType, $fingerprint, $targetUser, $targetName, $actorUser, $actorName, $ip, $details) {
        global $mysqli;
        if (!isset($mysqli) || !($mysqli instanceof mysqli)) { return false; }
        $stmt = $mysqli->prepare("INSERT INTO boom_fp_history (event_type,fingerprint,target_user,target_name,actor_user,actor_name,ip,details,event_date) VALUES (?,?,?,?,?,?,?,?,?)");
        if (!$stmt) { return false; }
        $date = time();
        $targetUser = (int) $targetUser;
        $actorUser = (int) $actorUser;
        $stmt->bind_param('ssisisssi', $eventType, $fingerprint, $targetUser, $targetName, $actorUser, $actorName, $ip, $details, $date);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('fpGetCemeteryRecord')) {
    function fpGetCemeteryRecord($fingerprint) {
        global $mysqli;
        $stmt = $mysqli->prepare("SELECT * FROM boom_permabanned WHERE parmakizleri = ? ORDER BY id DESC LIMIT 1");
        if (!$stmt) { return array(); }
        $stmt->bind_param('s', $fingerprint);
        $stmt->execute();
        $result = $stmt->get_result();
        $record = ($result && $result->num_rows > 0) ? $result->fetch_assoc() : array();
        $stmt->close();
        return $record;
    }
}


if (!function_exists('fpHasCemeteryException')) {
    function fpHasCemeteryException($userId, $fingerprint) {
        global $mysqli;
        $userId = (int) $userId;
        if ($userId < 1 || trim((string) $fingerprint) === '') { return false; }
        $stmt = $mysqli->prepare("SELECT id FROM boom_fp_exceptions WHERE user_id = ? AND fingerprint = ? AND active = 1 LIMIT 1");
        if (!$stmt) { return false; }
        $stmt->bind_param('is', $userId, $fingerprint);
        $stmt->execute();
        $result = $stmt->get_result();
        $found = ($result && $result->num_rows > 0);
        $stmt->close();
        return $found;
    }
}

if (!function_exists('fpGetActiveJail')) {
    function fpGetActiveJail($fingerprint) {
        global $mysqli;
        $stmt = $mysqli->prepare("SELECT * FROM boom_fp_jail WHERE fingerprint = ? AND active = 1 ORDER BY created_at DESC, id DESC LIMIT 1");
        if (!$stmt) { return array(); }
        $stmt->bind_param('s', $fingerprint);
        $stmt->execute();
        $result = $stmt->get_result();
        $record = ($result && $result->num_rows > 0) ? $result->fetch_assoc() : array();
        $stmt->close();
        return $record;
    }
}

if (!function_exists('fpEnsureJailRoom')) {
    function fpEnsureJailRoom() {
        global $mysqli, $setting;
        $options = fpSettings();
        $roomId = (int) $options['jail_room_id'];
        if ($roomId > 0) {
            $check = $mysqli->query("SELECT room_id FROM boom_rooms WHERE room_id = '$roomId' LIMIT 1");
            if ($check && $check->num_rows > 0) {
                // Keep the jail room restricted even if someone edits its access in the room manager.
                $mysqli->query("UPDATE boom_rooms SET access = 100, password = '', room_system = 1, description = 'غرفة مخصصة لتنفيذ عقوبات السجن بالبصمة' WHERE room_id = '$roomId'");
                return $roomId;
            }
        }
        $existing = $mysqli->query("SELECT room_id FROM boom_rooms WHERE room_name = 'سجن البصمة' ORDER BY room_id ASC LIMIT 1");
        if ($existing && $existing->num_rows > 0) {
            $roomId = (int) $existing->fetch_assoc()['room_id'];
            $mysqli->query("UPDATE boom_rooms SET access = 100, password = '', room_system = 1 WHERE room_id = '$roomId'");
        } else {
            $ownerId = isset($setting['system_id']) ? (int) $setting['system_id'] : 1;
            $now = time();
            $mysqli->query("INSERT INTO boom_rooms (room_name,access,description,password,room_system,room_creator,room_action) VALUES ('سجن البصمة',100,'غرفة مخصصة لتنفيذ عقوبات السجن بالبصمة','',1,'$ownerId','$now')");
            $roomId = (int) $mysqli->insert_id;
        }
        if ($roomId > 0) {
            $mysqli->query("UPDATE boom_fp_settings SET jail_room_id = '$roomId' WHERE id = 1");
        }
        return $roomId;
    }
}

if (!function_exists('fpMoveUserToRoom')) {
    function fpMoveUserToRoom($userId, $roomId) {
        global $mysqli, $data;
        $userId = (int) $userId;
        $roomId = (int) $roomId;
        if ($userId < 1 || $roomId < 1) { return false; }
        $room = $mysqli->query("SELECT room_id FROM boom_rooms WHERE room_id = '$roomId' LIMIT 1");
        if (!$room || $room->num_rows < 1) { return false; }
        $oldRoomId = 0;
        $oldRoomResult = $mysqli->query("SELECT user_roomid FROM boom_users WHERE user_id = '$userId' LIMIT 1");
        if ($oldRoomResult && $oldRoomResult->num_rows > 0) { $oldRoomId = (int) $oldRoomResult->fetch_assoc()['user_roomid']; }
        $now = time();
        $mysqli->query("UPDATE boom_users SET user_roomid = '$roomId', user_role = 0, room_mute = 0, user_move = '$now', last_action = '$now', user_action = user_action + 1 WHERE user_id = '$userId'");
        if (isset($data['user_id']) && (int) $data['user_id'] === $userId) {
            $data['user_roomid'] = $roomId;
            $data['user_role'] = 0;
            $data['room_mute'] = 0;
            $data['user_move'] = $now;
            $data['last_action'] = $now;
        }
        if (function_exists('redisUpdateUser')) { redisUpdateUser($userId); }
        if (function_exists('redisUpdateRoom')) {
            if ($oldRoomId > 0 && $oldRoomId !== $roomId) { redisUpdateRoom($oldRoomId); }
            redisUpdateRoom($roomId);
        }
        return true;
    }
}

if (!function_exists('fpMoveFingerprintAccounts')) {
    function fpMoveFingerprintAccounts($fingerprint, $roomId) {
        global $mysqli;
        $stmt = $mysqli->prepare("SELECT user_id FROM boom_users WHERE parmakizi = ?");
        if (!$stmt) { return; }
        $stmt->bind_param('s', $fingerprint);
        $stmt->execute();
        $result = $stmt->get_result();
        $ids = array();
        while ($result && ($row = $result->fetch_assoc())) { $ids[] = (int) $row['user_id']; }
        $stmt->close();
        foreach ($ids as $id) { fpMoveUserToRoom($id, $roomId); }
    }
}

if (!function_exists('fpReleaseFingerprintAccounts')) {
    function fpReleaseFingerprintAccounts($fingerprint, $jailRoomId) {
        global $mysqli, $data;
        $fingerprintSafe = $mysqli->real_escape_string($fingerprint);
        $jailRoomId = (int) $jailRoomId;
        $idsResult = $mysqli->query("SELECT user_id FROM boom_users WHERE parmakizi = '$fingerprintSafe' AND user_roomid = '$jailRoomId'");
        $ids = array();
        if ($idsResult) { while ($row = $idsResult->fetch_assoc()) { $ids[] = (int) $row['user_id']; } }
        $now = time();
        $mysqli->query("UPDATE boom_users SET user_roomid = 1, user_role = 0, room_mute = 0, user_move = '$now', last_action = '$now', user_action = user_action + 1 WHERE parmakizi = '$fingerprintSafe' AND user_roomid = '$jailRoomId'");
        if (isset($data['user_id']) && (int) $data['user_roomid'] === $jailRoomId && in_array((int) $data['user_id'], $ids, true)) {
            $data['user_roomid'] = 1;
            $data['user_role'] = 0;
            $data['room_mute'] = 0;
            $data['user_move'] = $now;
            $data['last_action'] = $now;
        }
        foreach ($ids as $id) { if (function_exists('redisUpdateUser')) { redisUpdateUser($id); } }
        if (function_exists('redisUpdateRoom')) { redisUpdateRoom(1); redisUpdateRoom($jailRoomId); }
    }
}

if (!function_exists('fpEvaluateFingerprint')) {
    function fpEvaluateFingerprint($fingerprint) {
        global $mysqli, $data;
        $fingerprint = trim((string) $fingerprint);
        $GLOBALS['fp_current_jail_active'] = false;
        $GLOBALS['fp_current_jail_room_id'] = 0;
        if ($fingerprint === '') { return 'unknown'; }

        $cemetery = fpGetCemeteryRecord($fingerprint);
        $currentUserId = isset($data['user_id']) ? (int) $data['user_id'] : 0;
        // An approved exception applies only to this account and this exact fingerprint.
        // It never exempts other accounts sharing the fingerprint and never overrides jail.
        if (!empty($cemetery) && !fpHasCemeteryException($currentUserId, $fingerprint)) { return 'cemetery'; }

        $jail = fpGetActiveJail($fingerprint);
        if (!empty($jail)) {
            $now = time();
            $expires = (int) $jail['expires_at'];
            $jailRoomId = fpEnsureJailRoom();
            if ($expires > 0 && $expires <= $now) {
                $jailId = (int) $jail['id'];
                $mysqli->query("UPDATE boom_fp_jail SET active = 0, released_at = '$now', release_reason = 'انتهاء مدة العقوبة' WHERE id = '$jailId' AND active = 1");
                fpHistory('jail_expired', $fingerprint, (int) $jail['target_user'], $jail['target_name'], 0, 'النظام', '', 'انتهت مدة السجن تلقائياً');
                fpReleaseFingerprintAccounts($fingerprint, $jailRoomId);
                return 'released';
            }
            if ($jailRoomId > 0 && isset($data['user_id'])) {
                $GLOBALS['fp_current_jail_active'] = true;
                $GLOBALS['fp_current_jail_room_id'] = $jailRoomId;
                if ((int) $data['user_roomid'] !== $jailRoomId) {
                    fpMoveUserToRoom((int) $data['user_id'], $jailRoomId);
                }
                return 'jail';
            }
        }
        return 'normal';
    }
}

if (!function_exists('fpRuntimeGuard')) {
    function fpRuntimeGuard() {
        global $data;
        if (!fpAddonIsActive() || empty($data) || empty($data['user_id'])) { return; }

        // CodyChat's native VPN gate can kick ordinary members. While this add-on is active,
        // the native per-user gate is bypassed for this request; VPN is monitoring-only.
        $data['uvpn'] = 0;
        $fingerprint = isset($data['parmakizi']) ? trim($data['parmakizi']) : '';
        if ($fingerprint === '') { return; }
        $state = fpEvaluateFingerprint($fingerprint);
        if ($state === 'cemetery') {
            http_response_code(403);
            echo '<div style="max-width:520px;margin:50px auto;padding:25px;text-align:center;font-family:Arial,sans-serif;line-height:1.9;border-radius:12px;background:#202327;color:#fff"><h2 style="color:#ff6868">تم منع الدخول</h2><p>بصمة هذا المتصفح موجودة في قائمة المقبرة.</p><p>يرجى التواصل مع إدارة الموقع عند الاعتقاد بوجود خطأ.</p></div>';
            exit;
        }
    }
}

if (!function_exists('fpIsCurrentUserJailed')) {
    function fpIsCurrentUserJailed() {
        return !empty($GLOBALS['fp_current_jail_active']);
    }
}

if (!function_exists('fpJailRoomId')) {
    function fpJailRoomId() {
        return isset($GLOBALS['fp_current_jail_room_id']) ? (int) $GLOBALS['fp_current_jail_room_id'] : 0;
    }
}

if (!function_exists('fpMayEnterJailRoom')) {
    function fpMayEnterJailRoom($targetRoom) {
        return fpIsCurrentUserJailed() && (int) $targetRoom === fpJailRoomId();
    }
}

if (!function_exists('fpShouldBlockRoomChange')) {
    function fpShouldBlockRoomChange($targetRoom) {
        return fpIsCurrentUserJailed() && (int) $targetRoom !== fpJailRoomId();
    }
}

if (!function_exists('fpBlockLeaveRoom')) {
    function fpBlockLeaveRoom() {
        global $data;
        if (!fpIsCurrentUserJailed()) { return false; }
        $roomId = fpJailRoomId();
        if ($roomId > 0 && (int) $data['user_roomid'] !== $roomId) {
            fpMoveUserToRoom((int) $data['user_id'], $roomId);
        }
        return true;
    }
}

if (!function_exists('fpLogVpnIfDetected')) {
    function fpLogVpnIfDetected($fingerprint, $user) {
        global $mysqli, $setting;
        $options = fpSettings();
        if (empty($options['vpn_monitor']) || empty($options['vpn_log'])) { return false; }
        if (empty($setting['vpn_key']) || !function_exists('checkVpn')) { return false; }
        $ip = getIp();
        if (!filter_var($ip, FILTER_VALIDATE_IP)) { return false; }

        // Reuse CodyChat's own proxycheck.io integration and boom_vpn cache.
        // The temporary setting is request-local; it does not change boom_setting.
        $oldUseVpn = isset($setting['use_vpn']) ? $setting['use_vpn'] : 0;
        $setting['use_vpn'] = 1;
        @checkVpn($ip);
        $setting['use_vpn'] = $oldUseVpn;

        $safeIp = $mysqli->real_escape_string($ip);
        $cached = $mysqli->query("SELECT vtype FROM boom_vpn WHERE vip = '$safeIp' LIMIT 1");
        if (!$cached || $cached->num_rows < 1) { return false; }
        $vpn = $cached->fetch_assoc();
        if ((int) $vpn['vtype'] !== 2) { return false; }

        $userId = (int) $user['user_id'];
        $name = $mysqli->real_escape_string($user['user_name']);
        $safeFingerprint = $mysqli->real_escape_string($fingerprint);
        $now = time();
        $mysqli->query("INSERT INTO boom_fp_vpn_log (fingerprint,user_id,user_name,ip,detection_type,hits,first_seen,last_seen)
            VALUES ('$safeFingerprint','$userId','$name','$safeIp','vpn_proxy',1,'$now','$now')
            ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen = VALUES(last_seen), fingerprint = VALUES(fingerprint), user_name = VALUES(user_name)");
        fpHistory('vpn_proxy_detected', $fingerprint, $userId, $user['user_name'], 0, 'النظام', $ip, 'تم رصد VPN أو Proxy؛ لم يتم منع الدخول');
        return true;
    }
}
?>
