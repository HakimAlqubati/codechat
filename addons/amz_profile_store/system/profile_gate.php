<?php
if(!defined('BOOM')){ die(); }

function amzStoreEnsureTables(){
    global $mysqli;
    static $ready = false;
    if($ready || !isset($mysqli) || !($mysqli instanceof mysqli)){ return; }
    $exists = $mysqli->query("SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('boom_amz_store_services','boom_amz_store_items','boom_amz_store_owned','boom_amz_store_entitlements','boom_amz_store_active','boom_amz_store_transactions')");
    if($exists){ $countRow=$exists->fetch_assoc(); if((int)$countRow['c']===6){ $ready=true; return; } }
    $queries = array(
        "CREATE TABLE IF NOT EXISTS boom_amz_store_services (
            service_key VARCHAR(24) NOT NULL PRIMARY KEY,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            free_rank INT NOT NULL DEFAULT 100,
            paid_rank INT NOT NULL DEFAULT 1,
            price_gold BIGINT NOT NULL DEFAULT 100,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS boom_amz_store_items (
            item_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            service_key VARCHAR(24) NOT NULL,
            title VARCHAR(120) NOT NULL,
            file_name VARCHAR(190) NOT NULL,
            price_gold BIGINT NOT NULL DEFAULT 0,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX(service_key), INDEX(enabled)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS boom_amz_store_owned (
            own_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            item_id INT UNSIGNED NOT NULL,
            paid_gold BIGINT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_item(user_id,item_id), INDEX(user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS boom_amz_store_entitlements (
            entitlement_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            service_key VARCHAR(24) NOT NULL,
            paid_gold BIGINT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_service(user_id,service_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS boom_amz_store_active (
            user_id INT NOT NULL,
            service_key VARCHAR(24) NOT NULL,
            item_id INT UNSIGNED NOT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY(user_id,service_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS boom_amz_store_transactions (
            txn_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            service_key VARCHAR(24) NOT NULL,
            item_id INT UNSIGNED NOT NULL DEFAULT 0,
            gold_paid BIGINT NOT NULL DEFAULT 0,
            balance_before BIGINT NOT NULL DEFAULT 0,
            balance_after BIGINT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX(user_id), INDEX(created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    foreach($queries as $q){ if(!$mysqli->query($q)){ error_log('AMZ Profile Store schema: ' . $mysqli->error); return; } }
    $defaults = array(
        array('background', 1, 100, 1, 100),
        array('frame', 1, 100, 1, 100),
        array('music', 1, 100, 1, 200),
    );
    $stmt = $mysqli->prepare("INSERT IGNORE INTO boom_amz_store_services (service_key,enabled,free_rank,paid_rank,price_gold) VALUES (?,?,?,?,?)");
    if($stmt){
        foreach($defaults as $d){ $k=$d[0]; $en=$d[1]; $fr=$d[2]; $pr=$d[3]; $pg=$d[4]; $stmt->bind_param('siiii', $k, $en, $fr, $pr, $pg); $stmt->execute(); }
        $stmt->close();
    }
    $dir = BOOM_PATH . '/upload/amz_profile_store';
    if(!is_dir($dir)){ @mkdir($dir, 0755, true); }
    if(is_dir($dir) && !file_exists($dir . '/.htaccess')){
        @file_put_contents($dir . '/.htaccess', "Options -Indexes\n<FilesMatch \"[.]php[0-9]*\">\nRequire all denied\n</FilesMatch>\n");
    }
    $ready = true;
}

function amzStoreSetting($service){
    global $mysqli;
    amzStoreEnsureTables();
    $service = preg_replace('/[^a-z_]/', '', (string)$service);
    $stmt = $mysqli->prepare("SELECT service_key,enabled,free_rank,paid_rank,price_gold FROM boom_amz_store_services WHERE service_key=? LIMIT 1");
    if(!$stmt){ return null; }
    $stmt->bind_param('s', $service); $stmt->execute();
    $stmt->bind_result($key, $enabled, $freeRank, $paidRank, $priceGold);
    $row = $stmt->fetch() ? array('service_key'=>$key,'enabled'=>$enabled,'free_rank'=>$freeRank,'paid_rank'=>$paidRank,'price_gold'=>$priceGold) : null;
    $stmt->close();
    return $row;
}
function amzStoreRank($user){ return isset($user['user_rank']) ? (int)$user['user_rank'] : 0; }
function amzStoreIsFree($service, $user){
    $s = amzStoreSetting($service);
    return $s && (int)$s['enabled'] === 1 && amzStoreRank($user) >= (int)$s['free_rank'];
}
function amzStoreCanBuy($service, $user){
    $s = amzStoreSetting($service);
    return $s && (int)$s['enabled'] === 1 && amzStoreRank($user) >= (int)$s['paid_rank'];
}
function amzStoreHasEntitlement($service, $userId){
    global $mysqli;
    amzStoreEnsureTables();
    $stmt = $mysqli->prepare("SELECT entitlement_id FROM boom_amz_store_entitlements WHERE user_id=? AND service_key=? LIMIT 1");
    if(!$stmt) return false;
    $stmt->bind_param('is', $userId, $service); $stmt->execute(); $stmt->store_result(); $yes=$stmt->num_rows>0; $stmt->close();
    return $yes;
}
function amzStoreCanUseMusic($user){
    $s = amzStoreSetting('music');
    if(!$s || (int)$s['enabled'] !== 1){ return false; }
    if(amzStoreRank($user) >= (int)$s['free_rank']){ return true; }
    return amzStoreHasEntitlement('music', (int)$user['user_id']);
}
function amzStoreActiveAsset($userId, $service){
    global $mysqli;
    amzStoreEnsureTables();
    $stmt = $mysqli->prepare("SELECT i.file_name FROM boom_amz_store_active a INNER JOIN boom_amz_store_items i ON i.item_id=a.item_id INNER JOIN boom_amz_store_services s ON s.service_key=a.service_key INNER JOIN boom_users u ON u.user_id=a.user_id LEFT JOIN boom_amz_store_owned o ON o.user_id=a.user_id AND o.item_id=a.item_id WHERE a.user_id=? AND a.service_key=? AND i.enabled=1 AND s.enabled=1 AND (u.user_rank>=s.free_rank OR o.own_id IS NOT NULL) LIMIT 1");
    if(!$stmt) return '';
    $stmt->bind_param('is', $userId, $service); $stmt->execute(); $stmt->bind_result($filename); $found=$stmt->fetch(); $stmt->close();
    return $found ? 'upload/amz_profile_store/' . rawurlencode($filename) : '';
}
function amzStoreCanUseService($service, $user){
    if(amzStoreIsFree($service,$user)) return true;
    if(!amzStoreCanBuy($service,$user)) return false;
    if($service === 'music') return amzStoreHasEntitlement('music',(int)$user['user_id']);
    return true;
}
?>
