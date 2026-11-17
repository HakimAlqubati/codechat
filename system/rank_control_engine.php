<?php
/**
 * Rank Control BLK - CodyChat 10 integration layer.
 * The module is intentionally passive until the addon is installed.
 */

function rankControlCache($refresh = false){
    static $cache = null;
    global $mysqli;
    if($refresh){
        $cache = null;
    }
    if($cache !== null){
        return $cache;
    }
    $emptyState = [
        'tables' => false,
        'ranks' => [],
        'permissions' => [],
        'settings' => [],
        'active' => false,
    ];
    if(!isset($mysqli) || !($mysqli instanceof mysqli)){
        $cache = null;
        return $emptyState;
    }
    try{
        $check = $mysqli->query("SHOW TABLES LIKE 'rank_control_ranks'");
        if(!$check || $check->num_rows < 1){
            $cache = $emptyState;
            return $cache;
        }
        $cache = $emptyState;
        $cache['tables'] = true;
        $ranks = $mysqli->query("SELECT rank_value, rank_name, rank_icon, rank_group FROM rank_control_ranks ORDER BY rank_value ASC");
        if($ranks){
            while($row = $ranks->fetch_assoc()){
                $cache['ranks'][(int)$row['rank_value']] = $row;
            }
        }
        $permissions = $mysqli->query("SELECT rank_value, can_promote FROM rank_control_permissions");
        if($permissions){
            while($row = $permissions->fetch_assoc()){
                $cache['permissions'][(int)$row['rank_value']] = (int)$row['can_promote'];
            }
        }
        $settings = $mysqli->query("SELECT primary_owner_id FROM rank_control_settings WHERE id = 1 LIMIT 1");
        if($settings && $settings->num_rows > 0){
            $cache['settings'] = $settings->fetch_assoc();
        }
        $addon = $mysqli->query("SELECT addons_id FROM boom_addons WHERE addons = 'rank_control_blk' LIMIT 1");
        $cache['active'] = ($addon && $addon->num_rows > 0);
        return $cache;
    }
    catch(\Throwable $e){
        $cache = null;
        return $emptyState;
    }
}

function rankControlTablesReady(){
    $cache = rankControlCache();
    return !empty($cache['tables']);
}

function rankControlIsActive(){
    $cache = rankControlCache();
    return !empty($cache['tables']) && !empty($cache['active']);
}

function rankControlGetCustomRank($rank){
    $cache = rankControlCache();
    $rank = (int)$rank;
    return $cache['ranks'][$rank] ?? null;
}

function rankControlCustomRankNumbers(){
    $cache = rankControlCache();
    return array_map('intval', array_keys($cache['ranks']));
}

function rankControlCanPromoteFlag($rank){
    $rank = (int)$rank;
    if($rank === 100){
        return true;
    }
    $cache = rankControlCache();
    return !empty($cache['permissions'][$rank]);
}

function rankControlIsPrimaryOwner(){
    global $data;
    if(!rankControlTablesReady() || empty($data['user_id'])){
        return false;
    }
    $cache = rankControlCache();
    return !empty($cache['settings']['primary_owner_id']) && (int)$cache['settings']['primary_owner_id'] === (int)$data['user_id'];
}

function rankControlReservedNumbers(){
    // Core CodyChat numbers that must retain their original purpose.
    return [1, 50, 69, 70, 80, 90];
}

function rankControlCanManageTarget($user){
    global $data;
    if(!rankControlIsActive()){
        return null;
    }
    if(empty($user) || empty($data['user_id']) || empty($data['user_rank'])){
        return false;
    }
    $actorId = (int)$data['user_id'];
    $actorRank = (int)$data['user_rank'];
    $targetId = (int)$user['user_id'];
    $targetRank = (int)$user['user_rank'];

    if($actorId === $targetId || isBot($data) || isGuest($data)){
        return false;
    }
    if($targetRank === 0 || $targetRank === 100 || isGuest($user) || isOwner($user)){
        return false;
    }
    if(isPaidVip($user) && $actorRank !== 100){
        return false;
    }
    if($actorRank === 100){
        return $targetRank < 100;
    }
    if($actorRank < 1 || $actorRank > 95 || $targetRank >= $actorRank){
        return false;
    }
    return rankControlCanPromoteFlag($actorRank);
}

function rankControlCanPromoteTo($user, $newRank){
    global $data;
    if(!rankControlIsActive() || !rankControlCanManageTarget($user)){
        return false;
    }
    $newRank = (int)$newRank;
    if($newRank <= 0 || $newRank >= 100 || $newRank === 999 || !validRank($newRank)){
        return false;
    }
    if($newRank === botRank() && !isBot($user)){
        return false;
    }
    $actorRank = (int)$data['user_rank'];
    if($actorRank === 100){
        return $newRank <= 95;
    }
    return $newRank < $actorRank;
}

/**
 * Return only rank numbers that are actually defined by CodyChat.
 * rankList() is the single source of truth: it contains native CodyChat
 * ranks and custom ranks stored by Rank Control BLK. This function never
 * fills gaps or generates a numeric range.
 */
function rankControlExistingRanksDescending(){
    if(!function_exists('rankList')){
        return [];
    }
    $definedRanks = rankList();
    if(!is_array($definedRanks)){
        return [];
    }

    $existing = [];
    foreach($definedRanks as $value){
        if(!is_numeric($value)){
            continue;
        }
        $rank = (int)$value;
        if($rank >= 1 && $rank <= 95){
            $existing[$rank] = $rank;
        }
    }

    $existing = array_values($existing);
    rsort($existing, SORT_NUMERIC);
    return $existing;
}

function rankControlAllowedRanksForTarget($user){
    $allowed = [];
    if(!rankControlIsActive() || !rankControlCanManageTarget($user)){
        return $allowed;
    }

    // Only explicitly defined native/custom ranks are candidates. No gaps
    // in the 1–95 range are displayed unless a rank is actually registered.
    $availableRanks = rankControlExistingRanksDescending();

    foreach($availableRanks as $rank){
        // Only ordinary ranks are selectable here. Rank 100 is owner-only;
        // 0 and 999 are system placeholders. Bot rank 69 is conditionally
        // allowed by rankControlCanPromoteTo() only for an actual bot target.
        if($rank < 1 || $rank > 95){
            continue;
        }
        if(rankControlCanPromoteTo($user, $rank)){
            $allowed[] = $rank;
        }
    }
    return $allowed;
}

function rankControlWriteAudit($action, $targetId = 0, $oldRank = null, $newRank = null, $details = ''){
    global $mysqli, $data;
    if(!rankControlTablesReady()){
        return false;
    }
    $actorId = isset($data['user_id']) ? (int)$data['user_id'] : 0;
    $targetId = (int)$targetId;
    $oldRank = is_null($oldRank) ? null : (int)$oldRank;
    $newRank = is_null($newRank) ? null : (int)$newRank;
    $action = substr((string)$action, 0, 40);
    $details = substr((string)$details, 0, 500);
    $stmt = $mysqli->prepare("INSERT INTO rank_control_audit (actor_id, target_id, action_type, old_rank, new_rank, details, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if(!$stmt){
        return false;
    }
    $created = time();
    $stmt->bind_param('iisiisi', $actorId, $targetId, $action, $oldRank, $newRank, $details, $created);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function rankControlApplyRankChange($user, $newRank){
    global $mysqli;
    $newRank = (int)$newRank;
    if(!rankControlIsActive() || empty($user) || !rankControlCanPromoteTo($user, $newRank)){
        return false;
    }
    $oldRank = (int)$user['user_rank'];
    if($oldRank === $newRank){
        return true;
    }
    $targetId = (int)$user['user_id'];
    userReset($user, $newRank);
    boomNotify('rank_change', ['target' => $targetId, 'source' => 'rank_change', 'rank' => $newRank]);
    if(isStaff($user)){
        $mysqli->query("UPDATE boom_users SET room_mute = '0', user_private = 1, user_mute = 0, user_regmute = 0 WHERE user_id = '$targetId'");
        $mysqli->query("DELETE FROM boom_room_action WHERE action_user = '$targetId'");
        $mysqli->query("DELETE FROM boom_ignore WHERE ignored = '$targetId'");
    }
    boomConsole('change_rank', ['target' => $targetId, 'rank' => $newRank]);
    rankControlWriteAudit('rank_change', $targetId, $oldRank, $newRank, 'Rank promoted or changed through the rank selector');
    redisUpdateUser($targetId);
    return true;
}

function rankControlLookupUser($type, $value){
    global $mysqli;
    $type = (string)$type;
    $value = trim((string)$value);
    if($value === '' || strlen($value) > 190){
        return ['status' => 'invalid', 'users' => []];
    }
    if($type === 'id'){
        if(!ctype_digit($value)){
            return ['status' => 'invalid', 'users' => []];
        }
        $stmt = $mysqli->prepare("SELECT user_id, user_name, user_email, user_ip, user_rank, user_bot FROM boom_users WHERE user_id = ? LIMIT 2");
        if(!$stmt){ return ['status' => 'error', 'users' => []]; }
        $id = (int)$value;
        $stmt->bind_param('i', $id);
    }
    else if($type === 'email'){
        $stmt = $mysqli->prepare("SELECT user_id, user_name, user_email, user_ip, user_rank, user_bot FROM boom_users WHERE user_email = ? LIMIT 2");
        if(!$stmt){ return ['status' => 'error', 'users' => []]; }
        $stmt->bind_param('s', $value);
    }
    else if($type === 'ip'){
        $stmt = $mysqli->prepare("SELECT user_id, user_name, user_email, user_ip, user_rank, user_bot FROM boom_users WHERE user_ip = ? ORDER BY user_id DESC LIMIT 3");
        if(!$stmt){ return ['status' => 'error', 'users' => []]; }
        $stmt->bind_param('s', $value);
    }
    else{
        return ['status' => 'invalid', 'users' => []];
    }
    if(!$stmt || !$stmt->execute()){
        if($stmt){ $stmt->close(); }
        return ['status' => 'error', 'users' => []];
    }
    $users = [];
    $stmt->bind_result($userId, $userName, $userEmail, $userIp, $userRank, $userBot);
    while($stmt->fetch()){
        $users[] = [
            'user_id' => $userId,
            'user_name' => $userName,
            'user_email' => $userEmail,
            'user_ip' => $userIp,
            'user_rank' => $userRank,
            'user_bot' => $userBot,
        ];
    }
    $stmt->close();
    if(count($users) === 0){
        return ['status' => 'not_found', 'users' => []];
    }
    if(count($users) > 1){
        return ['status' => 'multiple', 'users' => $users];
    }
    return ['status' => 'ok', 'users' => $users];
}

?>
