<?php
/**
 * DSG Admin User Manager v2 - shared helpers for CodyChat.
 * The addon stores administrator-granted likes separately from organic profile likes
 * and from Purchase Store likes.
 */

function dsgAdminTableExists($tableName){
    global $mysqli;
    $allowed = array('boom_dsg_admin_likes', 'boom_dsg_user_manager_logs', 'boom_purchase_store_likes');
    if(!in_array($tableName, $allowed, true)){
        return false;
    }
    $stmt = $mysqli->prepare("SELECT COUNT(*) AS found FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    if(!$stmt){
        return false;
    }
    $stmt->bind_param('s', $tableName);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row && (int)$row['found'] > 0;
}

function dsgCanAccessManager(){
    global $addons;
    $requiredRank = isset($addons['addons_access']) ? (int)$addons['addons_access'] : 70;
    return boomAllow($requiredRank);
}

function dsgCanEditManagedUser($user){
    global $addons;
    if(empty($user) || !isset($user['user_id']) || (int)$user['user_id'] <= 0){
        return false;
    }
    if(!empty($user['user_delete']) || (int)$user['user_rank'] <= 0 || !empty($user['user_bot'])){
        return false;
    }
    $requiredRank = isset($addons['addons_access']) ? (int)$addons['addons_access'] : 70;
    if(!boomAllow($requiredRank)){
        return false;
    }
    // Use CodyChat's native hierarchy rules so staff cannot edit equal/higher ranks.
    return canEditUser($user, $requiredRank, 1) ? true : false;
}

function dsgGetAdminLikes($userId){
    global $mysqli;
    if(!dsgAdminTableExists('boom_dsg_admin_likes')){
        return 0;
    }
    $stmt = $mysqli->prepare('SELECT bonus_likes FROM boom_dsg_admin_likes WHERE user_id = ? LIMIT 1');
    if(!$stmt){
        return 0;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row ? (string)$row['bonus_likes'] : '0';
}

function dsgGetVisibleLikeTotal($userId){
    global $mysqli;
    $total = 0;
    $stmt = $mysqli->prepare('SELECT COUNT(id) AS total FROM boom_pro_like WHERE target = ?');
    if($stmt){
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $total += $row ? (int)$row['total'] : 0;
        $stmt->close();
    }
    if(dsgAdminTableExists('boom_purchase_store_likes')){
        $stmt = $mysqli->prepare('SELECT bonus_likes FROM boom_purchase_store_likes WHERE user_id = ? LIMIT 1');
        if($stmt){
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result ? $result->fetch_assoc() : null;
            $total += $row ? (int)$row['bonus_likes'] : 0;
            $stmt->close();
        }
    }
    $adminLikes = dsgGetAdminLikes($userId);
    $total += (int)$adminLikes;
    return $total;
}

function dsgSaveAddonAccess($rank){
    global $mysqli;
    $rank = (int)$rank;
    if($rank < 1 || $rank > 100){
        return false;
    }
    $stmt = $mysqli->prepare("UPDATE boom_addons SET addons_access = ? WHERE addons = 'DSG_Admin_User_Manager'");
    if(!$stmt){
        return false;
    }
    $stmt->bind_param('i', $rank);
    $ok = $stmt->execute();
    $stmt->close();
    if($ok && function_exists('redisUpdateAddons')){
        redisUpdateAddons('DSG_Admin_User_Manager');
    }
    return $ok;
}
?>
