<?php
if(!function_exists('caAllowedRanks')){
    function caAllowedRanks(){
        return array(0, 1, 50, 70, 80, 90, 100);
    }
}


if(!function_exists('caRankOptions')){
    function caRankOptions($current){
        $html = '';
        foreach(caAllowedRanks() as $rank){
            $selected = ((int)$current === (int)$rank) ? ' selected="selected"' : '';
            $html .= '<option value="' . (int)$rank . '"' . $selected . '>' . caHtml(rankTitle($rank)) . '</option>';
        }
        return $html;
    }
}

if(!function_exists('caCanPublish')){
    function caCanPublish(){
        global $addons;
        return isset($addons['addons_access']) && boomAllow((int)$addons['addons_access']);
    }
}

if(!function_exists('caHtml')){
    function caHtml($value){
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if(!function_exists('caVisibleAlerts')){
    function caVisibleAlerts($limit = 20){
        global $mysqli, $data;
        $rank = (int)$data['user_rank'];
        $limit = max(1, min(50, (int)$limit));
        $result = $mysqli->query("SELECT * FROM boom_chat_alerts WHERE target_rank = -1 OR target_rank = '$rank' ORDER BY created_at DESC, id DESC LIMIT $limit");
        $alerts = array();
        if($result){
            while($row = $result->fetch_assoc()){
                $alerts[] = $row;
            }
        }
        return $alerts;
    }
}

if(!function_exists('caMarkSeen')){
    function caMarkSeen($alerts){
        global $mysqli, $data;
        if(empty($alerts)){
            return;
        }
        $uid = (int)$data['user_id'];
        $seenAt = time();
        foreach($alerts as $alert){
            $id = (int)$alert['id'];
            $mysqli->query("INSERT IGNORE INTO boom_chat_alerts_seen (alert_id, user_id, seen_at) VALUES ('$id', '$uid', '$seenAt')");
        }
    }
}

if(!function_exists('caUnreadCount')){
    function caUnreadCount(){
        global $mysqli, $data;
        $uid = (int)$data['user_id'];
        $rank = (int)$data['user_rank'];
        $result = $mysqli->query("SELECT COUNT(*) AS total FROM boom_chat_alerts a LEFT JOIN boom_chat_alerts_seen s ON s.alert_id = a.id AND s.user_id = '$uid' WHERE (a.target_rank = -1 OR a.target_rank = '$rank') AND s.alert_id IS NULL");
        if($result){
            $row = $result->fetch_assoc();
            return (int)$row['total'];
        }
        return 0;
    }
}

if(!function_exists('caAlertCard')){
    function caAlertCard($alert){
        global $lang;
        $sender = ((int)$alert['display_mode'] === 1) ? $alert['sender_name'] : 'النظام';
        $title = trim($alert['title']);
        $message = nl2br(caHtml($alert['message']));
        $date = function_exists('displayDate') ? displayDate($alert['created_at']) : date('Y-m-d H:i', (int)$alert['created_at']);
        $out = '<article class="chat_alert_card blist">';
        $out .= '<div class="chat_alert_card_top"><span class="chat_alert_tag"><i class="fa fa-bullhorn"></i> إشعار مهم</span><span class="chat_alert_date">' . caHtml($date) . '</span></div>';
        $out .= '<div class="chat_alert_sender">' . caHtml($sender) . '</div>';
        if($title !== ''){
            $out .= '<div class="chat_alert_title">' . caHtml($title) . '</div>';
        }
        $out .= '<div class="chat_alert_message">' . $message . '</div>';
        $out .= '</article>';
        return $out;
    }
}
?>
