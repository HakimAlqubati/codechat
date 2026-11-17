<?php
$load_addons = 'voice_mic';
require_once __DIR__ . '/../../../system/config_addons.php';
header('Content-Type: application/json; charset=utf-8');

function vmic_admin_reply($ok,$message='',$data=array()){
    echo json_encode(array('code'=>$ok?1:0,'message'=>(string)$message,'data'=>$data),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function vmic_admin_disconnect_room($roomId){
    global $mysqli;
    $q=$mysqli->query("SELECT user_id FROM boom_voice_mic_members WHERE room_id=".(int)$roomId);
    if($q){ while($r=$q->fetch_assoc()){ try { vmic_remove_live_participant($roomId,(int)$r['user_id']); } catch(Throwable $e){} } }
    $mysqli->query("DELETE FROM boom_voice_mic_members WHERE room_id=".(int)$roomId);
    $mysqli->query("DELETE FROM boom_voice_mic_requests WHERE room_id=".(int)$roomId);
}
function vmic_admin_disconnect_all(){
    global $mysqli;
    $q=$mysqli->query("SELECT DISTINCT room_id FROM boom_voice_mic_members");
    if($q){ while($r=$q->fetch_assoc()){ vmic_admin_disconnect_room((int)$r['room_id']); } }
}
if(!canManageAddons() || !isset($addons) || !isset($_POST['action'])){ vmic_admin_reply(false,'لا تملك صلاحية إدارة الإضافة'); }
$action=trim((string)$_POST['action']);

if($action==='save_settings'){
    $raw=isset($_POST['settings']) ? json_decode((string)$_POST['settings'],true) : null;
    $rooms=isset($_POST['rooms']) ? json_decode((string)$_POST['rooms'],true) : null;
    if(!is_array($raw) || !is_array($rooms)){ vmic_admin_reply(false,'بيانات الإعدادات غير صالحة'); }
    $bools=array('enabled','allow_listeners','require_speaker_approval','allow_room_staff','default_room_enabled','show_names','show_ranks');
    $values=array();
    foreach($bools as $key){ $values[$key]=!empty($raw[$key])?1:0; }
    $values['speaker_gold_mode']=isset($raw['speaker_gold_mode']) && (int)$raw['speaker_gold_mode']===1 ? 1 : 0;
    $values['speaker_gold_cost']=max(0,min(1000000,(int)($raw['speaker_gold_cost']??0)));
    $values['listener_gold_mode']=isset($raw['listener_gold_mode']) && (int)$raw['listener_gold_mode']===1 ? 1 : 0;
    $values['listener_gold_cost']=max(0,min(1000000,(int)($raw['listener_gold_cost']??0)));
    if($values['speaker_gold_mode']===1 || $values['listener_gold_mode']===1){
        if(!function_exists('useWallet') || !useWallet()){ vmic_admin_reply(false,'فعّل محفظة الذهب من إعدادات CodyChat أولاً قبل تفعيل رسوم المايك'); }
        if($values['speaker_gold_mode']===1 && $values['speaker_gold_cost']<1){ vmic_admin_reply(false,'اكتب سعر دقيقة الاتصال الجماعي، ولا يقل عن عملة ذهبية واحدة'); }
        if($values['listener_gold_mode']===1 && $values['listener_gold_cost']<1){ vmic_admin_reply(false,'اكتب سعر دقيقة الاستماع، ولا يقل عن عملة ذهبية واحدة'); }
    }
    // Preserve the old shared fields for compatibility with a previous build, while new billing uses per-role fields.
    $values['gold_mode']=$values['speaker_gold_mode'];
    $values['gold_cost']=$values['speaker_gold_cost'];
    $values['max_speakers']=max(1,min(100,(int)($raw['max_speakers']??10)));
    $values['max_listeners']=max(1,min(500,(int)($raw['max_listeners']??50)));
    $values['bar_height']=max(58,min(100,(int)($raw['bar_height']??74)));
    $values['avatar_size']=max(24,min(44,(int)($raw['avatar_size']??32)));
    foreach(array('min_speaker_rank','min_listener_rank','manage_rank','ban_rank','pause_rank') as $key){
        $rank=(int)($raw[$key]??0);
        $values[$key]=in_array($rank,array(0,1,50,70,80,90,100,999),true)?$rank:0;
    }
    foreach(array('speaker_color','listener_color','divider_color') as $key){
        $color=(string)($raw[$key]??'');
        if(!preg_match('/^#[0-9a-fA-F]{6}$/',$color)){ vmic_admin_reply(false,'صيغة لون غير صحيحة'); }
        $values[$key]=$color;
    }
    $stmt=$mysqli->prepare("UPDATE boom_voice_mic_settings SET enabled=?,allow_listeners=?,require_speaker_approval=?,allow_room_staff=?,default_room_enabled=?,max_speakers=?,max_listeners=?,bar_height=?,avatar_size=?,show_names=?,show_ranks=?,min_speaker_rank=?,min_listener_rank=?,manage_rank=?,ban_rank=?,pause_rank=?,gold_mode=?,gold_cost=?,speaker_gold_mode=?,speaker_gold_cost=?,listener_gold_mode=?,listener_gold_cost=?,speaker_color=?,listener_color=?,divider_color=?,updated_at=? WHERE id=1");
    if(!$stmt){ vmic_admin_reply(false,'تعذر تجهيز حفظ الإعدادات'); }
    $now=time();
    $stmt->bind_param(str_repeat('i',22).'sssi',
        $values['enabled'],$values['allow_listeners'],$values['require_speaker_approval'],$values['allow_room_staff'],$values['default_room_enabled'],
        $values['max_speakers'],$values['max_listeners'],$values['bar_height'],$values['avatar_size'],$values['show_names'],$values['show_ranks'],
        $values['min_speaker_rank'],$values['min_listener_rank'],$values['manage_rank'],$values['ban_rank'],$values['pause_rank'],$values['gold_mode'],$values['gold_cost'],$values['speaker_gold_mode'],$values['speaker_gold_cost'],$values['listener_gold_mode'],$values['listener_gold_cost'],
        $values['speaker_color'],$values['listener_color'],$values['divider_color'],$now
    );
    $ok=$stmt->execute(); $stmt->close();
    if(!$ok){ vmic_admin_reply(false,'تعذر حفظ الإعدادات'); }

    $validRooms=array();
    $q=$mysqli->query("SELECT room_id FROM boom_rooms WHERE room_id > 0");
    if(!$q){ vmic_admin_reply(false,'تعذر قراءة قائمة غرف الشات'); }
    while($r=$q->fetch_assoc()){ $validRooms[]=(int)$r['room_id']; }
    $selected=array();
    foreach($rooms as $id){ $id=(int)$id; if($id>0 && in_array($id,$validRooms,true)){ $selected[$id]=true; } }
    $roomStmt=$mysqli->prepare("INSERT INTO boom_voice_mic_rooms (room_id,enabled,updated_by,updated_at) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),updated_by=VALUES(updated_by),updated_at=VALUES(updated_at)");
    if(!$roomStmt){ vmic_admin_reply(false,'تعذر تجهيز حفظ إعدادات الغرف'); }
    $adminId=(int)$data['user_id'];
    foreach($validRooms as $id){
        $enabled=isset($selected[$id])?1:0;
        $roomStmt->bind_param('iiii',$id,$enabled,$adminId,$now);
        if(!$roomStmt->execute()){ $roomStmt->close(); vmic_admin_reply(false,'تعذر حفظ إعدادات إحدى الغرف'); }
        if(!$enabled){ vmic_admin_disconnect_room($id); }
    }
    $roomStmt->close();
    if(!$values['enabled']){ vmic_admin_disconnect_all(); }
    vmic_log(0,(int)$data['user_id'],0,'settings_update','تحديث الإعدادات العامة والغرف والصلاحيات والتصميم');
    vmic_admin_reply(true,'تم حفظ إعدادات المايك والغرف');
}

if($action==='pause'){
    $adminSettings=vmic_settings();
    if(!vmic_can_manage_room($data,$adminSettings,(int)$adminSettings['pause_rank'],false)){ vmic_admin_reply(false,'رتبتك لا تسمح بإيقاف خدمة المايك'); }
    $minutes=isset($_POST['minutes'])?max(0,min(525600,(int)$_POST['minutes'])):60;
    $message=trim((string)($_POST['message']??''));
    if(function_exists('mb_substr')){ $message=mb_substr($message,0,500); } else { $message=substr($message,0,500); }
    $until=$minutes===0 ? 2147483647 : min(2147483647,time()+($minutes*60));
    $stmt=$mysqli->prepare("UPDATE boom_voice_mic_settings SET pause_until=?,pause_message=?,updated_at=? WHERE id=1");
    if(!$stmt){ vmic_admin_reply(false,'تعذر إيقاف الخدمة'); }
    $now=time(); $stmt->bind_param('isi',$until,$message,$now); $ok=$stmt->execute(); $stmt->close();
    if(!$ok){ vmic_admin_reply(false,'تعذر إيقاف الخدمة'); }
    vmic_admin_disconnect_all();
    vmic_log(0,(int)$data['user_id'],0,'service_pause','إيقاف الخدمة: '.$message.'; المدة بالدقائق: '.$minutes);
    vmic_admin_reply(true,'تم إيقاف المايك وإخراج المشاركين من الجلسات الصوتية');
}

if($action==='resume'){
    $adminSettings=vmic_settings();
    if(!vmic_can_manage_room($data,$adminSettings,(int)$adminSettings['pause_rank'],false)){ vmic_admin_reply(false,'رتبتك لا تسمح بإعادة تفعيل خدمة المايك'); }
    $now=time();
    if(!$mysqli->query("UPDATE boom_voice_mic_settings SET pause_until=0,pause_message='',updated_at={$now} WHERE id=1")){ vmic_admin_reply(false,'تعذر إعادة تفعيل الخدمة'); }
    vmic_log(0,(int)$data['user_id'],0,'service_resume','إعادة تفعيل خدمة المايك');
    vmic_admin_reply(true,'تمت إعادة تفعيل المايك');
}

if($action==='unmute_member'){
    $roomId=(int)($_POST['room_id']??0); $userId=(int)($_POST['user_id']??0);
    if($roomId<1 || $userId<1){ vmic_admin_reply(false,'بيانات العضو غير صحيحة'); }
    $memberQ=$mysqli->query("SELECT mic_role,mic_muted FROM boom_voice_mic_members WHERE room_id={$roomId} AND user_id={$userId} LIMIT 1");
    if($memberQ && $memberQ->num_rows){
        $member=$memberQ->fetch_assoc();
        $canPublish = $member['mic_role']==='speaker' && !(int)$member['mic_muted'];
        if(!vmic_set_live_publish($roomId,$userId,$canPublish)){ vmic_admin_reply(false,'تعذر تحديث صلاحية الصوت لدى الخادم'); }
        $updated=$mysqli->query("UPDATE boom_voice_mic_members SET admin_muted=0 WHERE room_id={$roomId} AND user_id={$userId}");
        if(!$updated){ try { vmic_set_live_publish($roomId,$userId,false); } catch(Throwable $ignored) {} vmic_admin_reply(false,'تعذر تحديث حالة العضو'); }
        if($canPublish){ vmic_mute_live_track($roomId,$userId,false); }
    }
    $deleted=$mysqli->query("DELETE FROM boom_voice_mic_mutes WHERE room_id={$roomId} AND user_id={$userId}");
    if(!$deleted){ vmic_admin_reply(false,'تعذر حذف الكتم الإداري'); }
    vmic_log($roomId,(int)$data['user_id'],$userId,'unmute_member','إلغاء الكتم الإداري المحفوظ');
    vmic_admin_reply(true,'تم إلغاء الكتم الإداري؛ أصبح بإمكان العضو طلب التحدث مجدداً');
}

if($action==='unban'){
    $roomId=(int)($_POST['room_id']??0); $userId=(int)($_POST['user_id']??0);
    if($roomId<1 || $userId<1){ vmic_admin_reply(false,'بيانات التقييد غير صحيحة'); }
    if(!$mysqli->query("DELETE FROM boom_voice_mic_bans WHERE room_id={$roomId} AND user_id={$userId}")){ vmic_admin_reply(false,'تعذر إلغاء التقييد'); }
    vmic_log($roomId,(int)$data['user_id'],$userId,'unban','إلغاء تقييد من لوحة إدارة الإضافة');
    vmic_admin_reply(true,'تم إلغاء التقييد');
}

vmic_admin_reply(false,'طلب إداري غير معروف');
?>
