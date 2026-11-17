<?php
$load_addons = 'chat_alerts';
require(__DIR__ . '/../../../system/config_addons.php');

if(isset($_POST['save_settings'])){
    if(!canManageAddons()){
        die();
    }
    $access = isset($_POST['set_chat_alert_access']) ? (int)$_POST['set_chat_alert_access'] : -1;
    if(!in_array($access, caAllowedRanks(), true)){
        die();
    }
    if($mysqli->query("UPDATE boom_addons SET addons_access = '$access' WHERE addons = 'chat_alerts'")){
        redisUpdateAddons('chat_alerts');
        echo 5;
    }
    die();
}

if(isset($_POST['send_alert'])){
    if(!caCanPublish()){
        echo boomError('cannot_action');
        die();
    }

    $title = isset($_POST['alert_title']) ? trim($_POST['alert_title']) : '';
    $message = isset($_POST['alert_message']) ? trim($_POST['alert_message']) : '';
    $targetRank = isset($_POST['target_rank']) ? (int)$_POST['target_rank'] : -999;
    $displayMode = isset($_POST['display_mode']) ? (int)$_POST['display_mode'] : 0;
    $privateCopy = isset($_POST['private_copy']) ? (int)$_POST['private_copy'] : 0;

    $messageLength = function_exists('mb_strlen') ? mb_strlen($message, 'UTF-8') : (function_exists('iconv_strlen') ? iconv_strlen($message, 'UTF-8') : strlen($message));
    $titleLength = function_exists('mb_strlen') ? mb_strlen($title, 'UTF-8') : (function_exists('iconv_strlen') ? iconv_strlen($title, 'UTF-8') : strlen($title));
    if($message === '' || $messageLength > 3000 || $titleLength > 160){
        echo boomError('error');
        die();
    }
    if($title === '' && $message === ''){
        echo boomError('error');
        die();
    }
    if($targetRank !== -1 && !in_array($targetRank, caAllowedRanks(), true)){
        echo boomError('error');
        die();
    }
    if(!in_array($displayMode, array(0, 1), true) || !in_array($privateCopy, array(0, 1), true)){
        echo boomError('error');
        die();
    }

    $uid = (int)$data['user_id'];
    $now = time();
    $cooldown = $mysqli->query("SELECT id FROM boom_chat_alerts WHERE sender_id = '$uid' AND created_at > '" . ($now - 8) . "' LIMIT 1");
    if($cooldown && $cooldown->num_rows > 0){
        echo boomError('flood');
        die();
    }

    $safeTitle = $mysqli->real_escape_string($title);
    $safeMessage = $mysqli->real_escape_string($message);
    $safeName = $mysqli->real_escape_string($data['user_name']);
    $mysqli->query("INSERT INTO boom_chat_alerts (sender_id, sender_name, display_mode, target_rank, private_copy, title, message, created_at) VALUES ('$uid', '$safeName', '$displayMode', '$targetRank', '$privateCopy', '$safeTitle', '$safeMessage', '$now')");
    if($mysqli->insert_id < 1){
        echo boomError('error');
        die();
    }

    $sentPrivate = 0;
    if($privateCopy === 1){
        $systemId = isset($setting['system_id']) ? (int)$setting['system_id'] : 0;
        if($systemId > 0){
            if($targetRank === -1){
                $recipients = $mysqli->query("SELECT user_id FROM boom_users WHERE user_rank <> 69 AND user_id <> '$systemId' AND user_banned = 0 AND user_kick < '$now'");
            }
            else {
                $recipients = $mysqli->query("SELECT user_id FROM boom_users WHERE user_rank = '$targetRank' AND user_rank <> 69 AND user_id <> '$systemId' AND user_banned = 0 AND user_kick < '$now'");
            }
            if($recipients){
                $privateBody = '<div class="chat_alert_private"><strong>إشعار من الشات</strong>';
                if($title !== ''){
                    $privateBody .= '<br><b>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</b>';
                }
                $privateBody .= '<br>' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</div>';
                $privateBody = $mysqli->real_escape_string($privateBody);
                while($recipient = $recipients->fetch_assoc()){
                    systemPostPrivate((int)$recipient['user_id'], $privateBody);
                    $sentPrivate++;
                }
            }
        }
    }

    echo boomCode(1, array('private_count' => $sentPrivate));
    die();
}

echo boomError('error');
?>
