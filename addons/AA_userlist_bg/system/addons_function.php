<?php
/**
 * AA_userlist_bg - compatibility helpers for Codychat 10.
 * Database migrations are idempotent so an updated addon can be installed
 * over an existing installation without removing members' old backgrounds.
 */
function aaListBgEnsureSchema(){
    global $mysqli;
    if(!($mysqli instanceof mysqli)){
        return false;
    }

    $queries = array(
        "CREATE TABLE IF NOT EXISTS `userlist_bg_assets` (
            `bg_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `bg_file` varchar(190) NOT NULL,
            `bg_title` varchar(120) NOT NULL DEFAULT '',
            `bg_price` int(10) unsigned NOT NULL DEFAULT 0,
            `bg_active` tinyint(1) NOT NULL DEFAULT 0,
            `bg_created` int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (`bg_id`),
            UNIQUE KEY `bg_file` (`bg_file`),
            KEY `bg_active` (`bg_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        "CREATE TABLE IF NOT EXISTS `userlist_bg_rank` (
            `rank_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `bg_id` int(11) unsigned NOT NULL,
            `rank_type` varchar(12) NOT NULL,
            `rank_value` int(11) NOT NULL,
            `rank_date` int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (`rank_id`),
            UNIQUE KEY `one_bg_per_rank` (`rank_type`,`rank_value`),
            UNIQUE KEY `one_rank_per_bg` (`bg_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        "CREATE TABLE IF NOT EXISTS `userlist_bg_owned` (
            `owned_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `bg_id` int(11) unsigned NOT NULL,
            `bought_at` int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (`owned_id`),
            UNIQUE KEY `user_background` (`user_id`,`bg_id`),
            KEY `bg_id` (`bg_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        "CREATE TABLE IF NOT EXISTS `userlist_bg_active` (
            `user_id` int(11) NOT NULL,
            `bg_id` int(11) unsigned NOT NULL,
            `updated_at` int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (`user_id`),
            KEY `bg_id` (`bg_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    foreach($queries as $sql){
        if(!$mysqli->query($sql)){
            return false;
        }
    }
    return true;
}


function getMyListBg($id){
    global $mysqli;
    $id = (int)$id;
    $bg = array('bg_file' => '');
    $stmt = $mysqli->prepare("SELECT bg_file FROM userlist_bg WHERE user_id = ? LIMIT 1");
    if($stmt){
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->bind_result($file);
        if($stmt->fetch()){ $bg['bg_file'] = (string)$file; }
        $stmt->close();
    }
    return $bg;
}

/* Staff may edit only a different member with a strictly lower system rank. */
function aaListBgCanManageTarget($targetUser){
    global $data;
    if(!is_array($targetUser) || empty($targetUser['user_id']) || empty($data['user_id'])){
        return false;
    }
    if((int)$targetUser['user_id'] === (int)$data['user_id']){
        return false;
    }
    return (int)$data['user_rank'] >= 70 && (int)$targetUser['user_rank'] < (int)$data['user_rank'];
}

function aaListBgDir(){
    return BOOM_PATH . '/addons/AA_userlist_bg/files/background/';
}

function aaListBgValidFile($file){
    return is_string($file) && preg_match('/^ulbg_[0-9]+_[0-9]+(?:_[0-9]+)?\.(jpg|jpeg|png|gif|webp)$/i', $file);
}

function aaListBgRankOptions(){
    global $lang;
    $out = array();
    foreach(rankList() as $rank){
        if($rank == 69 || $rank == 999){
            continue;
        }
        $out[] = array('type'=>'system', 'value'=>(int)$rank, 'label'=>rankTitle($rank));
    }
    foreach(roomRankList() as $rank){
        if($rank <= 0 || $rank == 9){
            continue;
        }
        $out[] = array('type'=>'room', 'value'=>(int)$rank, 'label'=>roomRankTitle($rank));
    }
    return $out;
}

function aaListBgValidRank($type, $value){
    $value = (int)$value;
    if($type === 'system'){
        return in_array($value, rankList(), true) && $value !== botRank() && $value !== 999;
    }
    if($type === 'room'){
        return in_array($value, roomRankList(), true) && $value > 0 && $value !== 9;
    }
    return false;
}

function aaListBgOwned($userId, $bgId){
    global $mysqli;
    $userId = (int)$userId;
    $bgId = (int)$bgId;
    $stmt = $mysqli->prepare("SELECT owned_id FROM userlist_bg_owned WHERE user_id = ? AND bg_id = ? LIMIT 1");
    if(!$stmt){ return false; }
    $stmt->bind_param('ii', $userId, $bgId);
    $stmt->execute();
    $stmt->store_result();
    $found = $stmt->num_rows > 0;
    $stmt->close();
    return $found;
}

function aaListBgGetUserLegacy($userId){
    global $mysqli;
    $userId = (int)$userId;
    $stmt = $mysqli->prepare("SELECT bg_file FROM userlist_bg WHERE user_id = ? LIMIT 1");
    if(!$stmt){ return ''; }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->bind_result($file);
    $result = $stmt->fetch() ? (string)$file : '';
    $stmt->close();
    return $result;
}

function aaListBgUploadImage($file, $maxBytes = 5242880){
    if(!isset($file['error'], $file['size'], $file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK || $file['size'] <= 0 || $file['size'] > $maxBytes){
        return array('ok'=>false, 'code'=>3);
    }
    if(!is_uploaded_file($file['tmp_name'])){
        return array('ok'=>false, 'code'=>0);
    }
    $image = @getimagesize($file['tmp_name']);
    if(!$image || empty($image['mime']) || $image[0] < 1 || $image[1] < 1 || $image[0] > 4096 || $image[1] > 4096){
        return array('ok'=>false, 'code'=>0);
    }
    $mimeToExt = array(
        'image/jpeg'=>'jpg',
        'image/png'=>'png',
        'image/gif'=>'gif',
        'image/webp'=>'webp'
    );
    $mime = $image['mime'];
    if(!isset($mimeToExt[$mime])){
        return array('ok'=>false, 'code'=>0);
    }
    if(function_exists('finfo_open')){
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detected = $finfo ? finfo_file($finfo, $file['tmp_name']) : $mime;
        if($finfo){ finfo_close($finfo); }
        if($detected !== $mime){
            return array('ok'=>false, 'code'=>0);
        }
    }
    $dir = aaListBgDir();
    if(!is_dir($dir) && !@mkdir($dir, 0755, true)){
        return array('ok'=>false, 'code'=>0);
    }
    if(!is_writable($dir)){
        return array('ok'=>false, 'code'=>0);
    }
    try {
        $suffix = bin2hex(random_bytes(4));
    }
    catch(Exception $e){
        $suffix = (string)mt_rand(100000,999999);
    }
    $numericSuffix = preg_replace('/[^0-9]/', '', $suffix);
    if($numericSuffix === ''){ $numericSuffix = (string)mt_rand(100000, 999999); }
    $name = 'ulbg_' . (int)(isset($GLOBALS['data']['user_id']) ? $GLOBALS['data']['user_id'] : 0) . '_' . time() . '_' . $numericSuffix . '.' . $mimeToExt[$mime];
    $target = $dir . $name;
    if(!move_uploaded_file($file['tmp_name'], $target)){
        return array('ok'=>false, 'code'=>0);
    }
    @chmod($target, 0644);
    return array('ok'=>true, 'file'=>$name, 'width'=>(int)$image[0], 'height'=>(int)$image[1]);
}

function aaListBgDeleteStoredFile($file){
    if(!aaListBgValidFile($file)){
        return;
    }
    $path = aaListBgDir() . basename($file);
    if(is_file($path)){
        @unlink($path);
    }
}
?>
