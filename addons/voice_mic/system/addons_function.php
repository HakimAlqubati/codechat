<?php
if(!defined('BOOM')){ die(); }


function vmic_ensure_fee_schema(){
    global $mysqli;
    static $done = false;
    if($done){ return; }
    $done = true;
    $check=$mysqli->query("SHOW COLUMNS FROM `boom_voice_mic_settings`");
    if(!$check){ error_log('Voice Mic fee schema check failed: '.$mysqli->error); return; }
    $columns=array();
    while($row=$check->fetch_assoc()){ $columns[$row['Field']]=true; }
    $migrations=array(
        'speaker_gold_mode'=>"TINYINT(1) NOT NULL DEFAULT 0 AFTER `gold_cost`",
        'speaker_gold_cost'=>"INT UNSIGNED NOT NULL DEFAULT 0 AFTER `speaker_gold_mode`",
        'listener_gold_mode'=>"TINYINT(1) NOT NULL DEFAULT 0 AFTER `speaker_gold_cost`",
        'listener_gold_cost'=>"INT UNSIGNED NOT NULL DEFAULT 0 AFTER `listener_gold_mode`"
    );
    $added=false;
    foreach($migrations as $column=>$definition){
        if(!isset($columns[$column])){
            if(!$mysqli->query("ALTER TABLE `boom_voice_mic_settings` ADD COLUMN `{$column}` {$definition}")){
                error_log('Voice Mic fee migration failed for '.$column.': '.$mysqli->error);
                return;
            }
            $added=true;
        }
    }
    if($added){
        // Preserve the earlier single-rate configuration for both participant modes.
        if(!$mysqli->query("UPDATE boom_voice_mic_settings SET speaker_gold_mode=gold_mode,speaker_gold_cost=gold_cost,listener_gold_mode=gold_mode,listener_gold_cost=gold_cost WHERE id=1")){
            error_log('Voice Mic legacy fee migration failed: '.$mysqli->error);
        }
    }
}

function vmic_settings(){
    global $mysqli;
    $result = $mysqli->query("SELECT * FROM boom_voice_mic_settings WHERE id = 1 LIMIT 1");
    if($result && $result->num_rows){ return $result->fetch_assoc(); }
    return array(
        'enabled'=>1,'pause_until'=>0,'pause_message'=>'','gold_mode'=>0,'gold_cost'=>0,'speaker_gold_mode'=>0,'speaker_gold_cost'=>0,'listener_gold_mode'=>0,'listener_gold_cost'=>0,'allow_listeners'=>1,
        'require_speaker_approval'=>0,'allow_room_staff'=>1,'default_room_enabled'=>0,
        'max_speakers'=>10,'max_listeners'=>50,'bar_height'=>74,'avatar_size'=>32,
        'show_names'=>1,'show_ranks'=>1,'min_speaker_rank'=>0,'min_listener_rank'=>0,
        'manage_rank'=>70,'ban_rank'=>80,'pause_rank'=>90,
        'speaker_color'=>'#28b66f','listener_color'=>'#368ee8','divider_color'=>'#8b929c'
    );
}


function vmic_fee_for_role($settings, $role){
    $modeKey = $role === 'speaker' ? 'speaker_gold_mode' : 'listener_gold_mode';
    $costKey = $role === 'speaker' ? 'speaker_gold_cost' : 'listener_gold_cost';
    // Legacy fallback is retained only for installations that have not yet run the new migration.
    $mode = isset($settings[$modeKey]) ? (int)$settings[$modeKey] : (int)($settings['gold_mode'] ?? 0);
    $cost = isset($settings[$costKey]) ? (int)$settings[$costKey] : (int)($settings['gold_cost'] ?? 0);
    if($mode !== 1){ return 0; }
    return max(0,$cost);
}

function vmic_room_selected($roomId, $settings = null){
    global $mysqli;
    $roomId = (int)$roomId;
    if($roomId < 1){ return false; }
    if($settings === null){ $settings = vmic_settings(); }
    $room = $mysqli->query("SELECT room_id FROM boom_rooms WHERE room_id = {$roomId} LIMIT 1");
    if(!$room || !$room->num_rows){ return false; }
    $check = $mysqli->query("SELECT enabled FROM boom_voice_mic_rooms WHERE room_id = {$roomId} LIMIT 1");
    if($check && $check->num_rows){
        $row = $check->fetch_assoc();
        return (int)$row['enabled'] === 1;
    }
    return (int)$settings['default_room_enabled'] === 1;
}

function vmic_room_enabled($roomId, $settings = null){
    if($settings === null){ $settings = vmic_settings(); }
    if((int)$settings['enabled'] !== 1 || (int)$settings['pause_until'] > time()){ return false; }
    return vmic_room_selected($roomId, $settings);
}

function vmic_rank_allowed($user, $minRank){
    if(!isset($user['user_rank'])){ return false; }
    $rank = (int)$user['user_rank'];
    $minimum = (int)$minRank;
    // CodyChat uses rank 999 as the special 'nobody' role, not as a super-rank.
    if($rank === 999 || $minimum >= 999){ return false; }
    return $rank >= $minimum;
}

function vmic_can_manage_room($user, $settings, $minRank = null, $allowRoomStaff = true){
    if(!isset($user['user_rank']) || (int)$user['user_rank'] === 999){ return false; }
    $threshold = $minRank === null ? (int)$settings['manage_rank'] : (int)$minRank;
    if($threshold < 999 && (int)$user['user_rank'] >= $threshold){ return true; }
    if($allowRoomStaff && !empty($settings['allow_room_staff']) && isset($user['user_role']) && in_array((int)$user['user_role'], array(4,5,6), true)){ return true; }
    return false;
}

function vmic_live_room_name($roomId){
    $domain = defined('BOOM_DOMAIN') ? trim((string)BOOM_DOMAIN) : '';
    // Some exported CodyChat archives have an empty BOOM_DOMAIN until installation.
    // Use the current host as a stable per-site namespace in that case.
    if($domain === ''){ $domain = isset($_SERVER['HTTP_HOST']) ? strtolower((string)$_SERVER['HTTP_HOST']) : 'codychat'; }
    return 'cody_mic_' . (int)$roomId . '_' . substr(hash('sha256', $domain . ':' . (int)$roomId), 0, 16);
}

function vmic_livekit_service(){
    global $setting;
    require_once BOOM_PATH . '/system/livekit/autoload.php';
    $host = trim((string)$setting['live_url']);
    $host = preg_replace('#^wss://#i', 'https://', $host);
    $host = preg_replace('#^ws://#i', 'http://', $host);
    return new \Agence104\LiveKit\RoomServiceClient($host, $setting['live_appid'], $setting['live_secret']);
}

function vmic_log($roomId, $actorId, $targetId, $action, $details = ''){
    global $mysqli;
    $stmt = $mysqli->prepare("INSERT INTO boom_voice_mic_logs (room_id,actor_id,target_id,action,details,created_at) VALUES (?,?,?,?,?,?)");
    if($stmt){
        $roomId=(int)$roomId; $actorId=(int)$actorId; $targetId=(int)$targetId; $action=(string)$action; $details=function_exists('mb_substr') ? mb_substr((string)$details,0,500) : substr((string)$details,0,500); $now=time();
        $stmt->bind_param('iiissi',$roomId,$actorId,$targetId,$action,$details,$now);
        $stmt->execute(); $stmt->close();
    }
}

function vmic_remove_live_participant($roomId, $userId){
    try { vmic_livekit_service()->removeParticipant(vmic_live_room_name($roomId), (string)(int)$userId); return true; }
    catch(Throwable $e){ error_log('Voice Mic remove participant: ' . $e->getMessage()); return false; }
}

function vmic_set_live_publish($roomId, $userId, $canPublish){
    try {
        require_once BOOM_PATH . '/system/livekit/autoload.php';
        $permission = new \Livekit\ParticipantPermission();
        $permission->setCanSubscribe(true);
        $permission->setCanPublish((bool)$canPublish);
        $permission->setCanPublishData(false);
        $permission->setCanPublishSources($canPublish ? array(\Livekit\TrackSource::MICROPHONE) : array());
        vmic_livekit_service()->updateParticipant(vmic_live_room_name($roomId), (string)(int)$userId, null, $permission);
        return true;
    } catch(Throwable $e){
        error_log('Voice Mic permission update: ' . $e->getMessage());
        return false;
    }
}

function vmic_mute_live_track($roomId, $userId, $muted){
    try {
        $service = vmic_livekit_service();
        $participant = $service->getParticipant(vmic_live_room_name($roomId), (string)(int)$userId);
        foreach($participant->getTracks() as $track){
            if((int)$track->getSource() === \Livekit\TrackSource::MICROPHONE){
                $service->mutePublishedTrack(vmic_live_room_name($roomId), (string)(int)$userId, $track->getSid(), (bool)$muted);
                return true;
            }
        }
    } catch(Throwable $e){
        // A participant may not have published a microphone track yet.
        error_log('Voice Mic track mute: ' . $e->getMessage());
    }
    return false;
}

vmic_ensure_fee_schema();
?>