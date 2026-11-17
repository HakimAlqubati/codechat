<?php
$load_addons = 'private_status';
require(__DIR__ . '/../../../system/config_addons.php');

if(!canManageAddons() || !isset($_POST['save_private_status']) || (int)$_POST['save_private_status'] !== 1){
    die();
}

$required = array(
    'ps_enabled', 'ps_typing', 'ps_online', 'ps_last_seen',
    'ps_double_check', 'ps_blue_read', 'ps_read_label',
    'ps_scope', 'ps_min_rank'
);
foreach($required as $field){
    if(!isset($_POST[$field])){
        die();
    }
}

$enabled = (int)$_POST['ps_enabled'];
$typing = (int)$_POST['ps_typing'];
$online = (int)$_POST['ps_online'];
$lastSeen = (int)$_POST['ps_last_seen'];
$doubleCheck = (int)$_POST['ps_double_check'];
$blueRead = (int)$_POST['ps_blue_read'];
$readLabel = (int)$_POST['ps_read_label'];
$scope = (string)$_POST['ps_scope'];
$minRank = (int)$_POST['ps_min_rank'];

$flags = array($enabled, $typing, $online, $lastSeen, $doubleCheck, $blueRead, $readLabel);
foreach($flags as $flag){
    if($flag !== 0 && $flag !== 1){
        die();
    }
}
if(!in_array($scope, array('all', 'friends'), true) || !validRank($minRank)){
    die();
}

$stmt = $mysqli->prepare("UPDATE boom_addons SET addons_access = 0, custom1 = ?, custom2 = ?, custom3 = ?, custom4 = ?, custom5 = ?, custom6 = ?, custom7 = ?, custom8 = ?, custom9 = ? WHERE addons = 'private_status'");
if(!$stmt){
    echo 0;
    die();
}
$stmt->bind_param('iiiiiiisi', $enabled, $typing, $online, $lastSeen, $doubleCheck, $blueRead, $readLabel, $scope, $minRank);
$ok = $stmt->execute();
$stmt->close();
if($ok){
    redisUpdateAddons('private_status');
    echo 5;
}
else {
    echo 0;
}
?>
