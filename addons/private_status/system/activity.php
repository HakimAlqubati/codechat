<?php
$load_addons = 'private_status';
require(__DIR__ . '/../../../system/config_session.php');
$addons = addonsDetails('private_status');
if(empty($addons)){ die(); }
session_write_close();

header('Content-Type: application/json; charset=utf-8');

function psReply($payload){
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    die();
}

function psPostedIds($field, $max = 100){
    if(!isset($_POST[$field])){
        return array();
    }
    $ids = array();
    foreach(explode(',', (string)$_POST[$field]) as $rawId){
        if(ctype_digit($rawId)){
            $id = (int)$rawId;
            if($id > 0){
                $ids[$id] = $id;
            }
        }
        if(count($ids) >= $max){
            break;
        }
    }
    return array_values($ids);
}

function psConfig($addons){
    return array(
        'enabled' => (int)($addons['custom1'] ?? 1),
        'typing' => (int)($addons['custom2'] ?? 1),
        'online' => (int)($addons['custom3'] ?? 1),
        'last_seen' => (int)($addons['custom4'] ?? 1),
        'double_check' => (int)($addons['custom5'] ?? 1),
        'blue_read' => (int)($addons['custom6'] ?? 1),
        'read_label' => (int)($addons['custom7'] ?? 1),
        'scope' => (($addons['custom8'] ?? 'all') === 'friends') ? 'friends' : 'all',
        'min_rank' => (int)($addons['custom9'] ?? 0),
    );
}

function psCanSeePresence($targetId, $cfg){
    global $mysqli, $data;
    if((int)$data['user_rank'] < (int)$cfg['min_rank']){
        return false;
    }
    if($cfg['scope'] === 'friends'){
        $viewer = (int)$data['user_id'];
        $targetId = (int)$targetId;
        $friend = $mysqli->query("SELECT 1 FROM boom_friends WHERE ((hunter = '$viewer' AND target = '$targetId') OR (hunter = '$targetId' AND target = '$viewer')) AND fstatus = 3 LIMIT 1");
        if(!$friend || $friend->num_rows < 1){
            return false;
        }
    }
    return true;
}

function psPresence($targetId, $cfg){
    global $mysqli, $data, $lang;
    $empty = array('visible' => 0, 'state' => '', 'text' => '');
    if($targetId < 1 || $targetId === (int)$data['user_id'] || (!$cfg['online'] && !$cfg['last_seen'])){
        return $empty;
    }
    if(!psCanSeePresence($targetId, $cfg)){
        return $empty;
    }
    $query = $mysqli->query("SELECT user_id, user_rank, last_action, user_status, user_ghost, user_bot FROM boom_users WHERE user_id = '" . (int)$targetId . "' LIMIT 1");
    if(!$query || $query->num_rows < 1){
        return $empty;
    }
    $peer = $query->fetch_assoc();
    if(((int)$peer['user_status'] === 99 || (int)$peer['user_ghost'] > time()) && !canViewInvisible()){
        return $empty;
    }
    $online = ((int)$peer['last_action'] >= getDelay() || (int)$peer['user_bot'] === 1);
    if($online && $cfg['online']){
        return array(
            'visible' => 1,
            'state' => 'online',
            'text' => (isset($data['user_language']) && $data['user_language'] === 'English') ? 'Online now' : 'متصل الآن',
        );
    }
    if(!$online && $cfg['last_seen'] && (int)$peer['last_action'] > 0){
        $ago = boomTimeAgo((int)$peer['last_action']);
        $prefix = (isset($data['user_language']) && $data['user_language'] === 'English') ? 'Last seen ' : 'آخر ظهور ';
        return array('visible' => 1, 'state' => 'last_seen', 'text' => $prefix . $ago);
    }
    return $empty;
}

if(!isset($data['user_id']) || !isset($_POST['ps_action'])){
    psReply(array('ok' => 0));
}

$cfg = psConfig($addons);
if(!$cfg['enabled']){
    psReply(array('ok' => 0));
}

$action = (string)$_POST['ps_action'];
$viewerId = (int)$data['user_id'];
$targetId = isset($_POST['target']) ? (int)$_POST['target'] : 0;
$typingTable = $mysqli->query("SHOW TABLES LIKE 'boom_private_typing'");
$hasTypingTable = ($typingTable && $typingTable->num_rows > 0);

if($action === 'typing' || $action === 'typing_stop'){
    if(!$cfg['typing'] || !$hasTypingTable || $targetId < 1 || $targetId === $viewerId){
        psReply(array('ok' => 0));
    }
    if($action === 'typing_stop'){
        $mysqli->query("DELETE FROM boom_private_typing WHERE user_id = '$viewerId' AND target_id = '$targetId'");
        psReply(array('ok' => 1));
    }
    $peer = userRelationDetails($targetId);
    if(empty($peer) || !canSendPrivate($peer)){
        $mysqli->query("DELETE FROM boom_private_typing WHERE user_id = '$viewerId' AND target_id = '$targetId'");
        psReply(array('ok' => 0));
    }
    $now = time();
    $mysqli->query("INSERT INTO boom_private_typing (user_id, target_id, updated_at) VALUES ('$viewerId', '$targetId', '$now') ON DUPLICATE KEY UPDATE updated_at = '$now'");
    $mysqli->query("DELETE FROM boom_private_typing WHERE updated_at < '" . ($now - 60) . "'");
    psReply(array('ok' => 1));
}

if($action !== 'pulse'){
    psReply(array('ok' => 0));
}

// A logged-in browser session confirms delivery. This uses the existing private-message status field.
if($cfg['double_check'] || $cfg['blue_read'] || $cfg['read_label']){
    $systemId = isset($setting['system_id']) ? (int)$setting['system_id'] : 0;
    $mysqli->query("UPDATE boom_private SET status = 1 WHERE target = '$viewerId' AND hunter != '$systemId' AND status = 0");
}

$readOpen = isset($_POST['read_open']) ? (int)$_POST['read_open'] : 0;
if($targetId > 0 && $targetId !== $viewerId && $readOpen === 1 && ($cfg['blue_read'] || $cfg['read_label'])){
    // Mark only incoming messages currently loaded in this private conversation as read.
    $readIds = psPostedIds('read_ids', 100);
    if(!empty($readIds)){
        $idList = implode(',', $readIds);
        $mysqli->query("UPDATE boom_private SET view = 1 WHERE id IN ($idList) AND hunter = '$targetId' AND target = '$viewerId' AND view = 0");
    }
}

$typing = 0;
if($cfg['typing'] && $targetId > 0 && $hasTypingTable){
    $peer = userRelationDetails($targetId);
    if(!empty($peer) && canSendPrivate($peer)){
        $now = time();
        $getTyping = $mysqli->query("SELECT updated_at FROM boom_private_typing WHERE user_id = '$targetId' AND target_id = '$viewerId' LIMIT 1");
        if($getTyping && $getTyping->num_rows > 0){
            $rowTyping = $getTyping->fetch_assoc();
            if((int)$rowTyping['updated_at'] >= $now - 10){
                $typing = 1;
            }
        }
    }
}

$presence = psPresence($targetId, $cfg);
$messages = array();
$sentIds = psPostedIds('sent_ids', 100);
if($targetId > 0 && !empty($sentIds) && ($cfg['double_check'] || $cfg['blue_read'] || $cfg['read_label'])){
    $sentIdList = implode(',', $sentIds);
    $getStatus = $mysqli->query("SELECT id, status, view FROM boom_private WHERE id IN ($sentIdList) AND hunter = '$viewerId' AND target = '$targetId' ORDER BY id DESC");
    if($getStatus && $getStatus->num_rows > 0){
        while($rowStatus = $getStatus->fetch_assoc()){
            $messages[] = array(
                'id' => (int)$rowStatus['id'],
                'status' => (int)$rowStatus['status'],
                'view' => (int)$rowStatus['view'],
            );
        }
    }
}

psReply(array(
    'ok' => 1,
    'presence' => $presence,
    'typing' => $typing,
    'messages' => $messages,
));
?>
