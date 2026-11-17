<?php
$load_addons = 'AA_userlist_bg';
require_once('../../../system/config_addons.php');
if(!aaListBgEnsureSchema()){
    echo json_encode(array('code'=>0)); die();
}

function aaListBgResponse($data){
    echo json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    die();
}
function aaListBgAdminOnly(){
    if(!canManageAddons()){
        aaListBgResponse(array('code'=>0, 'message'=>'denied'));
    }
}
function aaListBgDeletePersonal($target){
    global $mysqli;
    $target = (int)$target;
    if($target <= 0){ return false; }
    $old = aaListBgGetUserLegacy($target);
    $stmt = $mysqli->prepare("DELETE FROM userlist_bg WHERE user_id = ?");
    if(!$stmt){ return false; }
    $stmt->bind_param('i', $target);
    $ok = $stmt->execute();
    $stmt->close();
    if($ok){ aaListBgDeleteStoredFile($old); }
    return $ok;
}
function aaListBgSavePersonal($target){
    global $mysqli;
    $target = (int)$target;
    if($target <= 0 || !isset($_FILES['file'])){ return array('code'=>0); }
    $upload = aaListBgUploadImage($_FILES['file'], 2097152);
    if(!$upload['ok']){ return array('code'=>$upload['code']); }
    $newFile = $upload['file'];
    $oldFile = aaListBgGetUserLegacy($target);
    $now = time();
    $stmt = $mysqli->prepare("INSERT INTO userlist_bg (user_id, bg_file, bg_date) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE bg_file = VALUES(bg_file), bg_date = VALUES(bg_date)");
    if(!$stmt){ aaListBgDeleteStoredFile($newFile); return array('code'=>0); }
    $stmt->bind_param('isi', $target, $newFile, $now);
    $ok = $stmt->execute();
    $stmt->close();
    if(!$ok){ aaListBgDeleteStoredFile($newFile); return array('code'=>0); }
    if($oldFile !== '' && $oldFile !== $newFile){ aaListBgDeleteStoredFile($oldFile); }
    return array('code'=>1, 'target'=>$target, 'file'=>$newFile);
}

/* Addon access rules */
if(isset($_POST['set_addon_access'], $_POST['set_addon_access_staff'])){
    aaListBgAdminOnly();
    $rank = (int)$_POST['set_addon_access'];
    $staff = (int)$_POST['set_addon_access_staff'];
    if(!in_array($rank, rankList(), true) || !in_array($staff, rankList(), true)){
        aaListBgResponse(array('code'=>0));
    }
    $stmt = $mysqli->prepare("UPDATE boom_addons SET addons_access = ?, custom1 = ? WHERE addons = 'AA_userlist_bg'");
    if(!$stmt){ aaListBgResponse(array('code'=>0)); }
    $stmt->bind_param('ii', $rank, $staff);
    $ok = $stmt->execute();
    $stmt->close();
    redisUpdateAddons('AA_userlist_bg');
    aaListBgResponse(array('code'=>$ok ? 1 : 0));
}

/* Admin library upload */
if(isset($_POST['upload_asset'])){
    aaListBgAdminOnly();
    if(!isset($_FILES['file'])){ aaListBgResponse(array('code'=>0)); }
    $upload = aaListBgUploadImage($_FILES['file'], 5242880);
    if(!$upload['ok']){ aaListBgResponse(array('code'=>$upload['code'])); }
    $title = trim(strip_tags((string)($_POST['title'] ?? '')));
    if(function_exists('mb_substr')){ $title = mb_substr($title, 0, 120, 'UTF-8'); } else { $title = substr($title, 0, 120); }
    if($title === ''){ $title = 'خلفية جديدة'; }
    $file = $upload['file'];
    $created = time();
    $stmt = $mysqli->prepare("INSERT INTO userlist_bg_assets (bg_file, bg_title, bg_price, bg_active, bg_created) VALUES (?, ?, 0, 0, ?)");
    if(!$stmt){ aaListBgDeleteStoredFile($file); aaListBgResponse(array('code'=>0)); }
    $stmt->bind_param('ssi', $file, $title, $created);
    $ok = $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();
    if(!$ok){ aaListBgDeleteStoredFile($file); aaListBgResponse(array('code'=>0)); }
    aaListBgResponse(array('code'=>1, 'bg_id'=>(int)$id));
}

/* Save an asset's distribution: one rank OR a gold price. */
if(isset($_POST['save_asset'])){
    aaListBgAdminOnly();
    $bgId = (int)($_POST['bg_id'] ?? 0);
    $price = (int)($_POST['price'] ?? 0);
    $title = trim(strip_tags((string)($_POST['title'] ?? '')));
    $active = isset($_POST['active']) && (int)$_POST['active'] === 1 ? 1 : 0;
    $mode = (string)($_POST['mode'] ?? 'gold');
    $rankKey = (string)($_POST['rank_key'] ?? 'none');
    if($bgId <= 0 || $price < 0 || $price > 100000000 || !in_array($mode, array('gold','rank'), true)){ aaListBgResponse(array('code'=>0)); }
    if(function_exists('mb_substr')){ $title = mb_substr($title, 0, 120, 'UTF-8'); } else { $title = substr($title, 0, 120); }
    if($title === ''){ $title = 'خلفية'; }

    $check = $mysqli->prepare("SELECT bg_id FROM userlist_bg_assets WHERE bg_id = ? LIMIT 1");
    if(!$check){ aaListBgResponse(array('code'=>0)); }
    $check->bind_param('i', $bgId); $check->execute(); $check->store_result();
    $exists = $check->num_rows > 0; $check->close();
    if(!$exists){ aaListBgResponse(array('code'=>0)); }

    $rankType = ''; $rankValue = 0;
    if($mode === 'rank'){
        $parts = explode(':', $rankKey, 2);
        if(count($parts) !== 2 || !aaListBgValidRank($parts[0], $parts[1])){ aaListBgResponse(array('code'=>0)); }
        $rankType = $parts[0]; $rankValue = (int)$parts[1];
    }

    $mysqli->begin_transaction();
    $stmt = $mysqli->prepare("UPDATE userlist_bg_assets SET bg_title = ?, bg_price = ?, bg_active = ? WHERE bg_id = ?");
    if(!$stmt){ $mysqli->rollback(); aaListBgResponse(array('code'=>0)); }
    $finalPrice = ($rankType !== '') ? 0 : $price;
    $stmt->bind_param('siii', $title, $finalPrice, $active, $bgId);
    $ok = $stmt->execute(); $stmt->close();
    if($ok && $rankType !== ''){
        $stmt = $mysqli->prepare("DELETE FROM userlist_bg_active WHERE bg_id = ? AND user_id NOT IN (SELECT user_id FROM userlist_bg_owned WHERE bg_id = ?)");
        if($stmt){ $stmt->bind_param('ii', $bgId, $bgId); $ok = $stmt->execute(); $stmt->close(); }
        else { $ok = false; }
    }
    if($ok){
        $stmt = $mysqli->prepare("DELETE FROM userlist_bg_rank WHERE bg_id = ?");
        if($stmt){ $stmt->bind_param('i', $bgId); $ok = $stmt->execute(); $stmt->close(); }
        else { $ok = false; }
    }
    if($ok && $rankType !== ''){
        /* A rank can have one active background. Hide the previous assignment
           from the public store before replacing it; its existing owners keep it. */
        $stmt = $mysqli->prepare("SELECT bg_id FROM userlist_bg_rank WHERE rank_type = ? AND rank_value = ? LIMIT 1");
        $oldRankBgId = 0;
        if($stmt){
            $stmt->bind_param('si', $rankType, $rankValue); $stmt->execute();
            $stmt->bind_result($oldRankBgId); $stmt->fetch(); $stmt->close();
        } else { $ok = false; }
        if($ok && (int)$oldRankBgId > 0 && (int)$oldRankBgId !== $bgId){
            $stmt = $mysqli->prepare("UPDATE userlist_bg_assets SET bg_active = 0 WHERE bg_id = ?");
            if($stmt){ $stmt->bind_param('i', $oldRankBgId); $ok = $stmt->execute(); $stmt->close(); }
            else { $ok = false; }
        }
        if($ok){
            $stmt = $mysqli->prepare("DELETE FROM userlist_bg_rank WHERE rank_type = ? AND rank_value = ?");
            if($stmt){ $stmt->bind_param('si', $rankType, $rankValue); $ok = $stmt->execute(); $stmt->close(); }
            else { $ok = false; }
        }
        if($ok){
            $now = time();
            $stmt = $mysqli->prepare("INSERT INTO userlist_bg_rank (bg_id, rank_type, rank_value, rank_date) VALUES (?, ?, ?, ?)");
            if($stmt){ $stmt->bind_param('isii', $bgId, $rankType, $rankValue, $now); $ok = $stmt->execute(); $stmt->close(); }
            else { $ok = false; }
        }
    }
    if($ok){ $mysqli->commit(); }
    else { $mysqli->rollback(); }
    aaListBgResponse(array('code'=>$ok ? 1 : 0, 'mode'=>$mode));
}

/* Delete a library item without touching legacy individual uploads. */
if(isset($_POST['delete_asset'])){
    aaListBgAdminOnly();
    $bgId = (int)$_POST['delete_asset'];
    $stmt = $mysqli->prepare("SELECT bg_file FROM userlist_bg_assets WHERE bg_id = ? LIMIT 1");
    if(!$stmt){ aaListBgResponse(array('code'=>0)); }
    $stmt->bind_param('i', $bgId); $stmt->execute(); $stmt->bind_result($file);
    $found = $stmt->fetch(); $stmt->close();
    if(!$found){ aaListBgResponse(array('code'=>0)); }
    $ownedStmt = $mysqli->prepare("SELECT owned_id FROM userlist_bg_owned WHERE bg_id = ? LIMIT 1");
    if(!$ownedStmt){ aaListBgResponse(array('code'=>0)); }
    $ownedStmt->bind_param('i', $bgId); $ownedStmt->execute(); $ownedStmt->store_result();
    $hasOwners = $ownedStmt->num_rows > 0; $ownedStmt->close();
    if($hasOwners){ aaListBgResponse(array('code'=>6)); }
    $mysqli->begin_transaction();
    $ok = true;
    foreach(array('userlist_bg_rank','userlist_bg_owned','userlist_bg_active') as $table){
        $stmt = $mysqli->prepare("DELETE FROM `$table` WHERE bg_id = ?");
        if(!$stmt){ $ok = false; break; }
        $stmt->bind_param('i', $bgId); $ok = $stmt->execute(); $stmt->close();
        if(!$ok){ break; }
    }
    if($ok){
        $stmt = $mysqli->prepare("DELETE FROM userlist_bg_assets WHERE bg_id = ?");
        if($stmt){ $stmt->bind_param('i', $bgId); $ok = $stmt->execute(); $stmt->close(); } else { $ok = false; }
    }
    if($ok){ $mysqli->commit(); aaListBgDeleteStoredFile($file); }
    else { $mysqli->rollback(); }
    aaListBgResponse(array('code'=>$ok ? 1 : 0));
}

/* Existing staff member-specific upload/delete. */
if(isset($_POST['target_upload_bg'])){
    if(!boomAllow($addons['custom1'])){ aaListBgResponse(array('code'=>0)); }
    $target = (int)$_POST['target_upload_bg'];
    $targetUser = userDetails($target);
    if(!aaListBgCanManageTarget($targetUser)){ aaListBgResponse(array('code'=>0)); }
    aaListBgResponse(aaListBgSavePersonal($target));
}
if(isset($_POST['target_delete_bg'])){
    if(!boomAllow($addons['custom1'])){ aaListBgResponse(array('code'=>0)); }
    $target = (int)$_POST['target_delete_bg'];
    $targetUser = userDetails($target);
    if(!aaListBgCanManageTarget($targetUser)){ aaListBgResponse(array('code'=>0)); }
    if(aaListBgDeletePersonal($target)){ aaListBgResponse(array('code'=>1, 'target'=>$target)); }
    aaListBgResponse(array('code'=>0));
}

/* Existing member-specific upload/delete. */
if(isset($_POST['upload_my_bg'])){
    if(!boomAllow($addons['addons_access'])){ aaListBgResponse(array('code'=>0)); }
    aaListBgResponse(aaListBgSavePersonal((int)$data['user_id']));
}
if(isset($_POST['delete_my_bg'])){
    if(!boomAllow($addons['addons_access'])){ aaListBgResponse(array('code'=>0)); }
    if(aaListBgDeletePersonal((int)$data['user_id'])){ aaListBgResponse(array('code'=>1, 'target'=>(int)$data['user_id'])); }
    aaListBgResponse(array('code'=>0));
}

/* Activate a free or already purchased library background. */
if(isset($_POST['activate_asset'])){
    if(!boomAllow($addons['addons_access'])){ aaListBgResponse(array('code'=>0)); }
    $bgId = (int)$_POST['activate_asset'];
    $stmt = $mysqli->prepare("SELECT bg_price, bg_active FROM userlist_bg_assets WHERE bg_id = ? LIMIT 1");
    if(!$stmt){ aaListBgResponse(array('code'=>0)); }
    $stmt->bind_param('i', $bgId); $stmt->execute(); $stmt->bind_result($price, $assetActive);
    $found = $stmt->fetch(); $stmt->close();
    if(!$found){ aaListBgResponse(array('code'=>0)); }
    $isOwned = aaListBgOwned($data['user_id'], $bgId);
    if((int)$assetActive !== 1 && !$isOwned){ aaListBgResponse(array('code'=>0)); }
    if((int)$price > 0 && !$isOwned){ aaListBgResponse(array('code'=>4)); }
    $userId = (int)$data['user_id']; $now = time();
    $stmt = $mysqli->prepare("INSERT INTO userlist_bg_active (user_id, bg_id, updated_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE bg_id = VALUES(bg_id), updated_at = VALUES(updated_at)");
    if(!$stmt){ aaListBgResponse(array('code'=>0)); }
    $stmt->bind_param('iii', $userId, $bgId, $now); $ok = $stmt->execute(); $stmt->close();
    aaListBgResponse(array('code'=>$ok ? 1 : 0, 'bg_id'=>$bgId));
}

/* Buy with Codychat's real gold wallet; the deduction and ownership insert are atomic. */
if(isset($_POST['buy_asset'])){
    if(!boomAllow($addons['addons_access']) || !useWallet()){
        aaListBgResponse(array('code'=>0));
    }
    $bgId = (int)$_POST['buy_asset'];
    $expectedPrice = isset($_POST['price']) ? (int)$_POST['price'] : -1;
    if($expectedPrice < 1){ aaListBgResponse(array('code'=>0)); }
    $userId = (int)$data['user_id'];
    $mysqli->begin_transaction();
    $stmt = $mysqli->prepare("SELECT bg_price FROM userlist_bg_assets WHERE bg_id = ? AND bg_active = 1 LIMIT 1 FOR UPDATE");
    if(!$stmt){ $mysqli->rollback(); aaListBgResponse(array('code'=>0)); }
    $stmt->bind_param('i', $bgId); $stmt->execute(); $stmt->bind_result($price);
    $found = $stmt->fetch(); $stmt->close();
    if(!$found || (int)$price <= 0){ $mysqli->rollback(); aaListBgResponse(array('code'=>0)); }
    if((int)$price !== $expectedPrice){ $mysqli->rollback(); aaListBgResponse(array('code'=>7)); }

    $stmt = $mysqli->prepare("SELECT owned_id FROM userlist_bg_owned WHERE user_id = ? AND bg_id = ? LIMIT 1 FOR UPDATE");
    if(!$stmt){ $mysqli->rollback(); aaListBgResponse(array('code'=>0)); }
    $stmt->bind_param('ii', $userId, $bgId); $stmt->execute(); $stmt->store_result();
    $alreadyOwned = $stmt->num_rows > 0; $stmt->close();
    if($alreadyOwned){ $mysqli->rollback(); aaListBgResponse(array('code'=>2)); }

    $price = (int)$price;
    $stmt = $mysqli->prepare("UPDATE boom_users SET user_gold = user_gold - ?, user_sgold = user_sgold + ? WHERE user_id = ? AND user_gold >= ?");
    if(!$stmt){ $mysqli->rollback(); aaListBgResponse(array('code'=>0)); }
    $stmt->bind_param('iiii', $price, $price, $userId, $price); $ok = $stmt->execute() && $stmt->affected_rows === 1; $stmt->close();
    if(!$ok){ $mysqli->rollback(); aaListBgResponse(array('code'=>5)); }

    $now = time();
    $stmt = $mysqli->prepare("INSERT INTO userlist_bg_owned (user_id, bg_id, bought_at) VALUES (?, ?, ?)");
    if(!$stmt){ $mysqli->rollback(); aaListBgResponse(array('code'=>0)); }
    $stmt->bind_param('iii', $userId, $bgId, $now); $ok = $stmt->execute(); $stmt->close();
    if(!$ok){ $mysqli->rollback(); aaListBgResponse(array('code'=>0)); }

    $stmt = $mysqli->prepare("INSERT INTO userlist_bg_active (user_id, bg_id, updated_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE bg_id = VALUES(bg_id), updated_at = VALUES(updated_at)");
    if(!$stmt){ $mysqli->rollback(); aaListBgResponse(array('code'=>0)); }
    $stmt->bind_param('iii', $userId, $bgId, $now); $ok = $stmt->execute(); $stmt->close();
    if(!$ok){ $mysqli->rollback(); aaListBgResponse(array('code'=>0)); }

    if(!$mysqli->commit()){
        $mysqli->rollback();
        aaListBgResponse(array('code'=>0));
    }
    redisUpdateUser($userId);
    $balance = 0;
    $balanceStmt = $mysqli->prepare("SELECT user_gold FROM boom_users WHERE user_id = ? LIMIT 1");
    if($balanceStmt){
        $balanceStmt->bind_param('i', $userId); $balanceStmt->execute();
        $balanceStmt->bind_result($balance); $balanceStmt->fetch(); $balanceStmt->close();
    }
    aaListBgResponse(array('code'=>1, 'balance'=>(int)$balance, 'bg_id'=>$bgId));
}

if(isset($_POST['deactivate_asset'])){
    if(!boomAllow($addons['addons_access'])){ aaListBgResponse(array('code'=>0)); }
    $userId = (int)$data['user_id'];
    $stmt = $mysqli->prepare("DELETE FROM userlist_bg_active WHERE user_id = ?");
    if(!$stmt){ aaListBgResponse(array('code'=>0)); }
    $stmt->bind_param('i', $userId); $ok = $stmt->execute(); $stmt->close();
    aaListBgResponse(array('code'=>$ok ? 1 : 0));
}

aaListBgResponse(array('code'=>0));
?>
