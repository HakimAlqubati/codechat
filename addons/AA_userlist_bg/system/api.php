<?php
$load_addons = 'AA_userlist_bg';
require_once('../../../system/config_addons.php');
$rawIds = isset($_POST['ids']) ? json_decode($_POST['ids'], true) : array();
if(!is_array($rawIds)){ $rawIds = array(); }
$ids = array();
foreach($rawIds as $id){
    $id = (int)$id;
    if($id > 0){ $ids[$id] = $id; }
    if(count($ids) >= 500){ break; }
}
$ids = array_values($ids);

$users = array();
$personal = array();
$active = array();
$assets = array();
$rankBg = array();
$allowedIds = array();
if(count($ids)){
    $in = implode(',', $ids);
    $roomId = (int)$data['user_roomid'];
    $q = $mysqli->query("SELECT user_id, user_rank, user_role FROM boom_users WHERE user_id IN ($in) AND (user_roomid = '$roomId' OR user_bot = 1)");
    if($q){ while($r = $q->fetch_assoc()){
        $uid = (int)$r['user_id'];
        $allowedIds[] = $uid;
        $users[] = array('user_id'=>$uid, 'user_rank'=>(int)$r['user_rank'], 'user_role'=>(int)$r['user_role']);
    } $q->free(); }
    if(count($allowedIds)){
        $allowedIn = implode(',', $allowedIds);
        $q = $mysqli->query("SELECT user_id, bg_file FROM userlist_bg WHERE user_id IN ($allowedIn)");
        if($q){ while($r = $q->fetch_assoc()){ $personal[] = array('user_id'=>(int)$r['user_id'], 'bg_file'=>(string)$r['bg_file']); } $q->free(); }
        $q = $mysqli->query("SELECT user_id, bg_id FROM userlist_bg_active WHERE user_id IN ($allowedIn)");
        if($q){ while($r = $q->fetch_assoc()){ $active[] = array('user_id'=>(int)$r['user_id'], 'bg_id'=>(int)$r['bg_id']); } $q->free(); }
    }
}
$assetIds = count($allowedIds) ? implode(',', $allowedIds) : '0';
$q = $mysqli->query("SELECT DISTINCT a.bg_id, a.bg_file FROM userlist_bg_assets a WHERE a.bg_active = 1 OR a.bg_id IN (SELECT o.bg_id FROM userlist_bg_owned o WHERE o.user_id IN ($assetIds))");
if($q){ while($r = $q->fetch_assoc()){ $assets[] = array('bg_id'=>(int)$r['bg_id'], 'bg_file'=>(string)$r['bg_file']); } $q->free(); }
$q = $mysqli->query("SELECT r.rank_type, r.rank_value, r.bg_id, a.bg_file FROM userlist_bg_rank r INNER JOIN userlist_bg_assets a ON a.bg_id = r.bg_id WHERE a.bg_active = 1");
if($q){ while($r = $q->fetch_assoc()){ $rankBg[] = array('rank_type'=>(string)$r['rank_type'], 'rank_value'=>(int)$r['rank_value'], 'bg_id'=>(int)$r['bg_id'], 'bg_file'=>(string)$r['bg_file']); } $q->free(); }

echo json_encode(array('ok'=>1, 'users'=>$users, 'personal'=>$personal, 'active'=>$active, 'assets'=>$assets, 'rankBg'=>$rankBg), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
die();
?>
