<?php
/**
 * VIP Membership Store for CodyChat 10.
 * Rank choices are read live from rankList()/rankTitle(), which are also
 * used by Rank Control BLK. No static rank list is maintained here.
 */
function vipMembershipEnsureTables(){
    global $mysqli;
    static $ready = null;
    if($ready !== null){ return $ready; }
    $queries = array();
    $queries[] = "CREATE TABLE IF NOT EXISTS boom_vip_membership_offers (
        offer_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        title VARCHAR(100) NOT NULL,
        rank_value SMALLINT UNSIGNED NOT NULL,
        price INT UNSIGNED NOT NULL DEFAULT 0,
        currency TINYINT UNSIGNED NOT NULL DEFAULT 1,
        payment_mode TINYINT UNSIGNED NOT NULL DEFAULT 1,
        cash_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        cash_currency CHAR(3) NOT NULL DEFAULT 'SAR',
        duration_days INT UNSIGNED NOT NULL DEFAULT 30,
        enabled TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_at INT UNSIGNED NOT NULL DEFAULT 0,
        updated_at INT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (offer_id),
        KEY rank_value (rank_value),
        KEY enabled_order (enabled, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    $queries[] = "CREATE TABLE IF NOT EXISTS boom_vip_membership_settings (
        setting_id TINYINT UNSIGNED NOT NULL DEFAULT 1,
        whatsapp_number VARCHAR(24) NOT NULL DEFAULT '',
        whatsapp_enabled TINYINT(1) NOT NULL DEFAULT 1,
        updated_at INT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (setting_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    $queries[] = "CREATE TABLE IF NOT EXISTS boom_vip_memberships (
        user_id INT UNSIGNED NOT NULL,
        previous_rank SMALLINT UNSIGNED NOT NULL DEFAULT 1,
        rank_value SMALLINT UNSIGNED NOT NULL,
        expires_at BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 means lifetime',
        offer_id INT UNSIGNED NOT NULL DEFAULT 0,
        started_at INT UNSIGNED NOT NULL DEFAULT 0,
        updated_at INT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (user_id),
        KEY expires_at (expires_at),
        KEY rank_value (rank_value)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    $queries[] = "CREATE TABLE IF NOT EXISTS boom_vip_membership_orders (
        order_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        buyer_id INT UNSIGNED NOT NULL,
        offer_id INT UNSIGNED NOT NULL,
        rank_value SMALLINT UNSIGNED NOT NULL,
        price INT UNSIGNED NOT NULL DEFAULT 0,
        currency TINYINT UNSIGNED NOT NULL DEFAULT 1,
        duration_days INT UNSIGNED NOT NULL DEFAULT 0,
        previous_rank SMALLINT UNSIGNED NOT NULL DEFAULT 1,
        balance_before BIGINT UNSIGNED NOT NULL DEFAULT 0,
        balance_after BIGINT UNSIGNED NOT NULL DEFAULT 0,
        created_at INT UNSIGNED NOT NULL DEFAULT 0,
        request_key CHAR(64) NOT NULL,
        PRIMARY KEY (order_id),
        UNIQUE KEY unique_request_key (request_key),
        KEY buyer_id (buyer_id),
        KEY created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    foreach($queries as $sql){
        if(!$mysqli->query($sql)){
            $ready = false;
            return false;
        }
    }
    // Upgrade installations that created the wallet-only offers table earlier.
    $columns = array();
    $colQuery = $mysqli->query("SHOW COLUMNS FROM boom_vip_membership_offers");
    if(!$colQuery){ $ready = false; return false; }
    while($col = $colQuery->fetch_assoc()){ $columns[$col['Field']] = true; }
    $colQuery->free();
    $migrations = array(
        'payment_mode' => "ALTER TABLE boom_vip_membership_offers ADD payment_mode TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER currency",
        'cash_price' => "ALTER TABLE boom_vip_membership_offers ADD cash_price DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER payment_mode",
        'cash_currency' => "ALTER TABLE boom_vip_membership_offers ADD cash_currency CHAR(3) NOT NULL DEFAULT 'SAR' AFTER cash_price"
    );
    foreach($migrations as $column=>$sql){ if(!isset($columns[$column]) && !$mysqli->query($sql)){ $ready = false; return false; } }
    $mysqli->query("INSERT IGNORE INTO boom_vip_membership_settings (setting_id, whatsapp_number, whatsapp_enabled, updated_at) VALUES (1, '', 1, 0)");
    $ready = true;
    return true;
}

function vipMembershipSellableRanks(){
    $available = function_exists('rankList') ? rankList() : array();
    $ranks = array();
    foreach($available as $value){
        $rank = (int)$value;
        // Sales may include moderator/admin and custom staff ranks. The top owner rank (100),
        // the bot rank (69), guest/basic ranks, and system placeholders remain protected.
        if($rank <= 1 || $rank >= 100 || $rank === 69 || $rank === 999){ continue; }
        if(function_exists('validRank') && !validRank($rank)){ continue; }
        $ranks[$rank] = $rank;
    }
    krsort($ranks, SORT_NUMERIC);
    return array_values($ranks);
}

function vipMembershipIsSellableRank($rank){
    return in_array((int)$rank, vipMembershipSellableRanks(), true);
}

function vipMembershipRankName($rank){
    if(function_exists('rankTitle')){ return (string)rankTitle((int)$rank); }
    return 'رتبة ' . (int)$rank;
}

function vipMembershipDurationLabel($days){
    $days = (int)$days;
    if($days <= 0){ return 'للأبد'; }
    if($days === 7){ return 'أسبوع (7 أيام)'; }
    if($days === 30){ return 'شهر (30 يومًا)'; }
    if($days === 360){ return '360 يومًا'; }
    if($days === 1){ return 'يوم واحد'; }
    return number_format($days) . ' يومًا';
}

function vipMembershipCurrencyName($currency){
    return ((int)$currency === 2) ? 'الروبي' : 'الذهب';
}

function vipMembershipCurrencyIcon($currency){
    return ((int)$currency === 2) ? rubyIcon() : goldIcon();
}

function vipMembershipOffers($enabledOnly = false){
    global $mysqli;
    if(!vipMembershipEnsureTables()){ return array(); }
    $sql = "SELECT * FROM boom_vip_membership_offers" . ($enabledOnly ? " WHERE enabled = 1" : "") . " ORDER BY rank_value DESC, sort_order ASC, offer_id ASC";
    $q = $mysqli->query($sql);
    $list = array();
    if($q){
        while($row = $q->fetch_assoc()){
            if(!vipMembershipIsSellableRank((int)$row['rank_value'])){ continue; }
            $list[] = $row;
        }
    }
    return $list;
}


function vipMembershipSettings(){
    global $mysqli;
    if(!vipMembershipEnsureTables()){ return array('whatsapp_number'=>'','whatsapp_enabled'=>1); }
    $q = $mysqli->query("SELECT whatsapp_number, whatsapp_enabled FROM boom_vip_membership_settings WHERE setting_id = 1 LIMIT 1");
    $row = $q ? $q->fetch_assoc() : null;
    return $row ?: array('whatsapp_number'=>'','whatsapp_enabled'=>1);
}
function vipMembershipCleanWhatsApp($number){
    return preg_replace('/\D+/', '', (string)$number);
}

function vipMembershipGetUser($userId){
    global $mysqli;
    if(!vipMembershipEnsureTables()){ return null; }
    $stmt = $mysqli->prepare("SELECT * FROM boom_vip_memberships WHERE user_id = ? LIMIT 1");
    if(!$stmt){ return null; }
    $userId = (int)$userId;
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

/** Restore an expired member only if the account still carries the rank that this membership granted. */
function vipMembershipCleanUser($userId){
    global $mysqli, $data;
    if(!vipMembershipEnsureTables()){ return false; }
    $userId = (int)$userId;
    if($userId <= 0){ return false; }
    $stmt = $mysqli->prepare("SELECT previous_rank, rank_value, expires_at FROM boom_vip_memberships WHERE user_id = ? LIMIT 1");
    if(!$stmt){ return false; }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $membership = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if(empty($membership) || (int)$membership['expires_at'] === 0 || (int)$membership['expires_at'] > time()){
        return false;
    }
    $userStmt = $mysqli->prepare("SELECT * FROM boom_users WHERE user_id = ? LIMIT 1");
    if(!$userStmt){ return false; }
    $userStmt->bind_param('i', $userId);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    $user = $userResult ? $userResult->fetch_assoc() : null;
    $userStmt->close();
    if(!empty($user) && (int)$user['user_rank'] === (int)$membership['rank_value']){
        $previousRank = (int)$membership['previous_rank'];
        if(!function_exists('validRank') || !validRank($previousRank) || $previousRank >= 100){
            $previousRank = 1;
        }
        userReset($user, $previousRank);
        boomNotify('rank_change', array('target' => $userId, 'source' => 'rank_change', 'rank' => $previousRank));
        if(isset($data['user_id']) && (int)$data['user_id'] === $userId){
            $data['user_rank'] = $previousRank;
            $data['vip_end'] = 0;
        }
    }
    $del = $mysqli->prepare("DELETE FROM boom_vip_memberships WHERE user_id = ? AND expires_at > 0 AND expires_at <= ?");
    if($del){
        $now = time();
        $del->bind_param('ii', $userId, $now);
        $del->execute();
        $del->close();
    }
    return true;
}

function vipMembershipCleanExpired($limit = 100){
    global $mysqli;
    if(!vipMembershipEnsureTables()){ return 0; }
    $limit = max(1, min(500, (int)$limit));
    $now = time();
    $q = $mysqli->query("SELECT user_id FROM boom_vip_memberships WHERE expires_at > 0 AND expires_at <= '$now' ORDER BY expires_at ASC LIMIT $limit");
    $count = 0;
    if($q){
        while($row = $q->fetch_assoc()){
            if(vipMembershipCleanUser((int)$row['user_id'])){ $count++; }
        }
    }
    return $count;
}
?>
