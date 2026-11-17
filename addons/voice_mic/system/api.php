<?php
$load_addons = 'voice_mic';
require_once __DIR__ . '/../../../system/config_addons.php';
header('Content-Type: application/json; charset=utf-8');

function vmic_reply($ok, $message = '', $data = array()){
    echo json_encode(array('code' => $ok ? 1 : 0, 'message' => (string)$message, 'data' => $data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function vmic_live_ready(){
    global $setting;
    return !empty($setting['live_url']) && !empty($setting['live_appid']) && !empty($setting['live_secret']);
}
function vmic_charge_gold($userId, $amount){
    global $mysqli;
    $userId=(int)$userId; $amount=(int)$amount;
    if($amount<1){ return array('ok'=>false,'message'=>'قيمة الذهب المحددة للدقيقة غير صحيحة','balance'=>0); }
    if(!function_exists('useWallet') || !useWallet()){
        return array('ok'=>false,'message'=>'الدفع بالدقيقة يتطلب محفظة الذهب، لكنها غير مفعّلة حالياً. يرجى التواصل مع الإدارة.','balance'=>0);
    }
    // Atomic balance check and debit; prevents concurrent join requests from spending
    // the same balance twice. user_sgold tracks gold spent, matching CodyChat removeGold().
    $stmt=$mysqli->prepare("UPDATE boom_users SET user_gold=user_gold-?, user_sgold=user_sgold+? WHERE user_id=? AND user_gold>=?");
    if(!$stmt){ return array('ok'=>false,'message'=>'تعذر تجهيز خصم الذهب؛ لم يتم خصم أي مبلغ','balance'=>0); }
    $stmt->bind_param('iiii',$amount,$amount,$userId,$amount);
    $ok=$stmt->execute();
    $affected=$stmt->affected_rows;
    $stmt->close();
    if(!$ok){ return array('ok'=>false,'message'=>'تعذر تنفيذ خصم الذهب؛ لم يتم تأكيد تجديد أو دخول جلسة المايك','balance'=>0); }
    if($affected!==1){
        $balance=0;
        $q=$mysqli->query("SELECT user_gold FROM boom_users WHERE user_id={$userId} LIMIT 1");
        if($q && $q->num_rows){ $balance=(int)$q->fetch_assoc()['user_gold']; }
        return array('ok'=>false,'message'=>'رصيد الذهب غير كافٍ. الرسوم المطلوبة '.$amount.' ذهب، ورصيدك الحالي '.$balance.' ذهب.','balance'=>$balance);
    }
    return array('ok'=>true,'message'=>'','balance'=>null);
}
function vmic_bill_due_minutes($roomId, $userId, $now){
    global $mysqli;
    $roomId=(int)$roomId; $userId=(int)$userId; $now=(int)$now;
    if(!$mysqli->begin_transaction()){
        return array('ok'=>false,'message'=>'تعذر تحديث فاتورة المايك؛ أعد المحاولة','disconnect'=>false,'charged_gold'=>0,'charged_minutes'=>0);
    }
    $q=$mysqli->query("SELECT * FROM boom_voice_mic_members WHERE room_id={$roomId} AND user_id={$userId} LIMIT 1 FOR UPDATE");
    if(!$q || !$q->num_rows){
        $mysqli->rollback();
        return array('ok'=>false,'message'=>'لم تعد عضواً في المايك','disconnect'=>true,'charged_gold'=>0,'charged_minutes'=>0);
    }
    $member=$q->fetch_assoc();
    if((int)$member['payment_confirmed']!==1 || (int)$member['last_seen'] < ($now-40)){
        $mysqli->rollback();
        return array('ok'=>false,'message'=>'انتهت جلسة المايك؛ أعد الدخول','disconnect'=>true,'charged_gold'=>0,'charged_minutes'=>0);
    }
    $rate=(int)($member['billing_rate']??0);
    $nextBill=(int)($member['next_bill_at']??0);
    $minutesDue=0;
    $amountDue=0;
    if($rate>0 && $nextBill>0 && $now >= $nextBill){
        $minutesDue=(int)floor(($now-$nextBill)/60)+1;
        // An active membership normally checks in every 10 seconds. Refuse an
        // implausibly large backlog instead of overflowing or charging blindly.
        if($minutesDue<1 || $minutesDue>2){
            $mysqli->rollback();
            return array('ok'=>false,'message'=>'توقفت مزامنة جلسة المايك مدة طويلة؛ أعد الدخول لتجديدها','disconnect'=>true,'charged_gold'=>0,'charged_minutes'=>0);
        }
        $amountDue=$rate*$minutesDue;
        if($amountDue<1 || $amountDue>1000000000){
            $mysqli->rollback();
            return array('ok'=>false,'message'=>'تعذر حساب رسوم المايك؛ أعد الدخول','disconnect'=>true,'charged_gold'=>0,'charged_minutes'=>0);
        }
        $charge=vmic_charge_gold($userId,$amountDue);
        if(empty($charge['ok'])){
            $mysqli->rollback();
            return array('ok'=>false,'message'=>'رصيد الذهب لا يكفي لتجديد الدقيقة التالية. '.$charge['message'],'disconnect'=>true,'charged_gold'=>0,'charged_minutes'=>0,'balance'=>(int)$charge['balance'],'required'=>$amountDue);
        }
    }
    $newNextBill=$nextBill;
    if($minutesDue>0){ $newNextBill=$nextBill+(60*$minutesDue); }
    $stmt=$mysqli->prepare("UPDATE boom_voice_mic_members SET last_seen=?,paid_gold=paid_gold+?,billed_minutes=billed_minutes+?,next_bill_at=? WHERE room_id=? AND user_id=?");
    if(!$stmt){
        $mysqli->rollback();
        return array('ok'=>false,'message'=>'تعذر حفظ وقت جلسة المايك؛ أعد المحاولة','disconnect'=>false,'charged_gold'=>0,'charged_minutes'=>0);
    }
    $stmt->bind_param('iiiiii',$now,$amountDue,$minutesDue,$newNextBill,$roomId,$userId);
    $saved=$stmt->execute(); $stmt->close();
    if(!$saved || !$mysqli->commit()){
        $mysqli->rollback();
        return array('ok'=>false,'message'=>'تعذر تأكيد تجديد جلسة المايك','disconnect'=>false,'charged_gold'=>0,'charged_minutes'=>0);
    }
    $balance=null;
    if(function_exists('redisUpdateUser')){ redisUpdateUser($userId); }
    if($minutesDue>0){
        $b=$mysqli->query("SELECT user_gold FROM boom_users WHERE user_id={$userId} LIMIT 1");
        if($b && $b->num_rows){ $balance=(int)$b->fetch_assoc()['user_gold']; }
        vmic_log($roomId,$userId,$userId,'gold_minute_charge','خصم '.$amountDue.' ذهب مقابل '.$minutesDue.' دقيقة إضافية في المايك');
    }
    return array('ok'=>true,'message'=>'','disconnect'=>false,'charged_gold'=>$amountDue,'charged_minutes'=>$minutesDue,'balance'=>$balance,'next_bill_at'=>$newNextBill,'billing_rate'=>$rate);
}


function vmic_switch_billing_rate($roomId,$userId,$targetRole,$now,$member=null){
    global $mysqli;
    // Caller must hold a DB transaction. This keeps the debit and the role/rate update atomic.
    if($member===null){
        $q=$mysqli->query("SELECT * FROM boom_voice_mic_members WHERE room_id=".(int)$roomId." AND user_id=".(int)$userId." LIMIT 1 FOR UPDATE");
        if(!$q || !$q->num_rows){ return array('ok'=>false,'message'=>'جلسة المايك غير موجودة'); }
        $member=$q->fetch_assoc();
    }
    $settings=vmic_settings();
    $newRate=vmic_fee_for_role($settings,$targetRole);
    $oldRate=(int)($member['billing_rate']??0);
    $next=(int)($member['next_bill_at']??0);
    $paid=(int)($member['paid_gold']??0);
    $minutes=(int)($member['billed_minutes']??0);
    $started=(int)($member['billing_started_at']??0);
    $charged=0;
    if($newRate>0 && $oldRate===0 && $next===0){
        $charge=vmic_charge_gold($userId,$newRate);
        if(empty($charge['ok'])){ return array('ok'=>false,'message'=>'التحويل إلى هذا الوضع يتطلب '.$newRate.' ذهب للدقيقة الأولى. '.$charge['message']); }
        $charged=$newRate;
        $paid+=$newRate; $minutes++; $started=$now; $next=$now+60;
    } elseif($newRate===0){
        // A free mode does not carry over an already-running paid-minute timer.
        $next=0; $started=0;
    }
    $stmt=$mysqli->prepare("UPDATE boom_voice_mic_members SET billing_rate=?,paid_gold=?,billed_minutes=?,next_bill_at=?,billing_started_at=?,last_seen=? WHERE room_id=? AND user_id=?");
    if(!$stmt){ return array('ok'=>false,'message'=>'تعذر تحديث سعر الدقيقة لهذا الوضع'); }
    $stmt->bind_param('iiiiiiii',$newRate,$paid,$minutes,$next,$started,$now,$roomId,$userId);
    $ok=$stmt->execute(); $stmt->close();
    if(!$ok){ return array('ok'=>false,'message'=>'تعذر حفظ سعر الوضع الجديد'); }
    return array('ok'=>true,'rate'=>$newRate,'charged'=>$charged,'next_bill_at'=>$next);
}

function vmic_live_token($roomId, $user, $role){
    global $setting;
    require_once BOOM_PATH . '/system/livekit/autoload.php';
    $roomName = vmic_live_room_name($roomId);
    $options = new \Agence104\LiveKit\AccessTokenOptions(array(
        'identity' => (string)(int)$user['user_id'],
        'name' => (string)$user['user_name'],
        'ttl' => 90,
        'metadata' => json_encode(array('room_id'=>(int)$roomId, 'mode'=>$role), JSON_UNESCAPED_UNICODE)
    ));
    $grant = new \Agence104\LiveKit\VideoGrant();
    $grant->setRoomJoin(true);
    $grant->setRoomName($roomName);
    // Join credentials are initially media-locked. confirm_join grants subscribe/publish
    // rights only after the wallet charge is confirmed (or the mode is free).
    $grant->setCanPublish(false);
    $grant->setCanSubscribe(false);
    $grant->setCanPublishData(false);
    $grant->setCanPublishSources(array());
    return (new \Agence104\LiveKit\AccessToken($setting['live_appid'], $setting['live_secret']))->init($options)->setGrant($grant)->toJwt();
}
function vmic_is_banned($roomId, $userId){
    global $mysqli;
    $roomId=(int)$roomId; $userId=(int)$userId; $now=time();
    $q=$mysqli->query("SELECT expires_at,reason FROM boom_voice_mic_bans WHERE room_id={$roomId} AND user_id={$userId} LIMIT 1");
    if(!$q || !$q->num_rows){ return false; }
    $ban=$q->fetch_assoc();
    if((int)$ban['expires_at'] > 0 && (int)$ban['expires_at'] <= $now){
        $mysqli->query("DELETE FROM boom_voice_mic_bans WHERE room_id={$roomId} AND user_id={$userId}");
        return false;
    }
    return $ban;
}
function vmic_speaking_mute($roomId, $userId){
    global $mysqli;
    $q=$mysqli->query("SELECT reason,created_by,created_at FROM boom_voice_mic_mutes WHERE room_id=".(int)$roomId." AND user_id=".(int)$userId." LIMIT 1");
    return ($q && $q->num_rows) ? $q->fetch_assoc() : false;
}
function vmic_member($roomId, $userId){
    global $mysqli;
    $q=$mysqli->query("SELECT * FROM boom_voice_mic_members WHERE room_id=".(int)$roomId." AND user_id=".(int)$userId." LIMIT 1");
    return ($q && $q->num_rows) ? $q->fetch_assoc() : array();
}
function vmic_count($roomId, $role){
    global $mysqli;
    $cutoff=time()-40;
    $role=$mysqli->real_escape_string($role);
    $q=$mysqli->query("SELECT COUNT(*) AS total FROM boom_voice_mic_members WHERE room_id=".(int)$roomId." AND mic_role='{$role}' AND last_seen >= {$cutoff}");
    $r=$q ? $q->fetch_assoc() : array('total'=>0);
    return (int)$r['total'];
}
function vmic_log_request($roomId, $userId, $status){
    global $mysqli;
    $now=time();
    $stmt=$mysqli->prepare("INSERT INTO boom_voice_mic_requests (room_id,user_id,status,created_at,updated_at) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status), created_at=VALUES(created_at), updated_at=VALUES(updated_at)");
    if($stmt){ $roomId=(int)$roomId; $userId=(int)$userId; $stmt->bind_param('iiiii',$roomId,$userId,$status,$now,$now); $stmt->execute(); $stmt->close(); }
}
function vmic_permission_for_participant($roomId, $userId, $canPublish, $canSubscribe = true){
    require_once BOOM_PATH . '/system/livekit/autoload.php';
    $permission = new \Livekit\ParticipantPermission();
    $permission->setCanSubscribe((bool)$canSubscribe);
    $permission->setCanPublish((bool)$canPublish);
    $permission->setCanPublishData(false);
    $permission->setCanPublishSources($canPublish ? array(\Livekit\TrackSource::MICROPHONE) : array());
    vmic_livekit_service()->updateParticipant(vmic_live_room_name($roomId), (string)(int)$userId, null, $permission);
}
function vmic_target_is_valid($roomId, $targetId){
    global $mysqli;
    $q=$mysqli->query("SELECT u.user_id,u.user_rank,u.user_role FROM boom_users u INNER JOIN boom_voice_mic_members m ON m.user_id=u.user_id WHERE u.user_id=".(int)$targetId." AND m.room_id=".(int)$roomId." AND u.user_roomid=".(int)$roomId." LIMIT 1");
    return ($q && $q->num_rows) ? $q->fetch_assoc() : array();
}
function vmic_admin_action($roomId, $actor, $settings, $action, $targetId){
    global $mysqli;
    $actorId=(int)$actor['user_id']; $targetId=(int)$targetId;
    if($targetId < 1 || $targetId === $actorId){ vmic_reply(false, 'لا يمكن تنفيذ هذا الأمر على حسابك'); }
    $threshold = ($action === 'ban' || $action === 'unban') ? (int)$settings['ban_rank'] : (int)$settings['manage_rank'];
    if(!vmic_can_manage_room($actor,$settings,$threshold)){ vmic_reply(false, 'لا تملك صلاحية تنفيذ هذا الأمر'); }
    $target=vmic_target_is_valid($roomId,$targetId);
    if(!$target){ vmic_reply(false, 'العضو غير موجود في المايك أو غادر الغرفة'); }
    $actorRank=(int)$actor['user_rank'];
    $globalManager = $actorRank !== 999 && $threshold < 999 && $actorRank >= $threshold;
    $roomManager = !empty($settings['allow_room_staff']) && in_array((int)$actor['user_role'],array(4,5,6),true);
    if($globalManager && (int)$target['user_rank'] >= $actorRank){ vmic_reply(false, 'لا يمكنك إدارة عضو برتبة مساوية أو أعلى من رتبتك'); }
    if(!$globalManager && $roomManager){
        if((int)$target['user_rank'] >= (int)$settings['manage_rank']){ vmic_reply(false, 'لا يمكنك إدارة عضو يحمل رتبة إدارية أعلى من صلاحية مشرف الغرفة'); }
        if((int)$target['user_role'] >= (int)$actor['user_role']){ vmic_reply(false, 'لا يمكنك إدارة مشرف غرفة برتبة مساوية أو أعلى من رتبتك'); }
    }
    $member=vmic_member($roomId,$targetId);
    $roomName=vmic_live_room_name($roomId);
    $now=time();

    if($action === 'mute' || $action === 'unmute'){
        $mute = $action === 'mute' ? 1 : 0;
        $canPublish = !$mute && $member['mic_role']==='speaker' && !(int)$member['mic_muted'];
        try { vmic_permission_for_participant($roomId,$targetId,$canPublish); }
        catch(Throwable $e){ error_log('Voice Mic admin permission: '.$e->getMessage()); vmic_reply(false,'تعذر تحديث صلاحية الصوت لدى الخادم؛ لم يتم اعتماد الأمر'); }

        if($mute){
            $reason=isset($_POST['reason']) ? trim((string)$_POST['reason']) : '';
            if(function_exists('mb_substr')){ $reason=mb_substr($reason,0,500); } else { $reason=substr($reason,0,500); }
            $muteStmt=$mysqli->prepare("INSERT INTO boom_voice_mic_mutes (room_id,user_id,reason,created_by,created_at) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE reason=VALUES(reason),created_by=VALUES(created_by),created_at=VALUES(created_at)");
            if(!$muteStmt){ vmic_reply(false,'تعذر حفظ التقييد الصوتي'); }
            $muteStmt->bind_param('iisii',$roomId,$targetId,$reason,$actorId,$now); $muteSaved=$muteStmt->execute(); $muteStmt->close();
            if(!$muteSaved){ vmic_reply(false,'تعذر حفظ التقييد الصوتي'); }
        }

        if($mute){
            // Administrative mute always closes the member's microphone locally too.
            // Unmuting later removes the restriction but does not turn the microphone on automatically.
            $stmt=$mysqli->prepare("UPDATE boom_voice_mic_members SET admin_muted=1,mic_muted=1,last_seen=? WHERE room_id=? AND user_id=?");
            if(!$stmt){ vmic_reply(false,'تعذر حفظ حالة الكتم الإداري'); }
            $stmt->bind_param('iii',$now,$roomId,$targetId);
        } else {
            $stmt=$mysqli->prepare("UPDATE boom_voice_mic_members SET admin_muted=0,last_seen=? WHERE room_id=? AND user_id=?");
            if(!$stmt){ vmic_reply(false,'تعذر حفظ حالة الكتم الإداري'); }
            $stmt->bind_param('iii',$now,$roomId,$targetId);
        }
        $saved=$stmt->execute(); $stmt->close();
        if(!$saved){
            // Fail closed if the mute state cannot be committed.
            if($mute){ try { vmic_permission_for_participant($roomId,$targetId,false); } catch(Throwable $ignored) {} }
            else { try { vmic_permission_for_participant($roomId,$targetId,false); } catch(Throwable $ignored) {} }
            vmic_reply(false,'تعذر حفظ حالة الكتم الإداري');
        }
        if(!$mute){
            $deleted=$mysqli->query("DELETE FROM boom_voice_mic_mutes WHERE room_id={$roomId} AND user_id={$targetId}");
            if(!$deleted){
                $mysqli->query("UPDATE boom_voice_mic_members SET admin_muted=1 WHERE room_id={$roomId} AND user_id={$targetId}");
                try { vmic_permission_for_participant($roomId,$targetId,false); } catch(Throwable $ignored) {}
                vmic_reply(false,'تعذر إلغاء التقييد من قاعدة البيانات؛ أُبقي الكتم للحماية');
            }
        }
        vmic_mute_live_track($roomId,$targetId,(bool)$mute);
        vmic_log($roomId,$actorId,$targetId,$action,$mute ? 'كتم إداري محفوظ' : 'إلغاء الكتم الإداري');
        vmic_reply(true,$mute ? 'تم كتم العضو ومنعه من التحدث حتى إلغاء الكتم' : 'تم إلغاء الكتم الإداري');
    }

    if($action === 'listener'){
        $settle=vmic_bill_due_minutes($roomId,$targetId,$now);
        if(empty($settle['ok'])){ vmic_reply(false,'تعذر إتمام التحويل: '.$settle['message']); }
        if(!$mysqli->begin_transaction()){ vmic_reply(false,'تعذر بدء تحويل العضو إلى مستمع'); }
        $memberQuery=$mysqli->query("SELECT * FROM boom_voice_mic_members WHERE room_id={$roomId} AND user_id={$targetId} LIMIT 1 FOR UPDATE");
        if(!$memberQuery || !$memberQuery->num_rows){ $mysqli->rollback(); vmic_reply(false,'انتهت جلسة العضو قبل التحويل'); }
        $member=$memberQuery->fetch_assoc();
        $rateChange=vmic_switch_billing_rate($roomId,$targetId,'listener',$now,$member);
        if(empty($rateChange['ok'])){ $mysqli->rollback(); vmic_reply(false,$rateChange['message']); }
        try { vmic_permission_for_participant($roomId,$targetId,false); }
        catch(Throwable $e){ $mysqli->rollback(); try { vmic_permission_for_participant($roomId,$targetId,$member['mic_role']==='speaker'); } catch(Throwable $ignored){} vmic_reply(false,'تعذر تغيير صلاحية العضو في خادم الصوت'); }
        vmic_mute_live_track($roomId,$targetId,true);
        $stmt=$mysqli->prepare("UPDATE boom_voice_mic_members SET mic_role='listener',mic_muted=1,last_seen=? WHERE room_id=? AND user_id=?");
        if(!$stmt){ $mysqli->rollback(); if($member['mic_role']==='speaker'){ try { vmic_permission_for_participant($roomId,$targetId,true); } catch(Throwable $ignored) {} } vmic_reply(false,'تعذر حفظ دور العضو'); }
        $stmt->bind_param('iii',$now,$roomId,$targetId); $saved=$stmt->execute(); $stmt->close();
        if(!$saved || !$mysqli->commit()){ $mysqli->rollback(); if($member['mic_role']==='speaker'){ try { vmic_permission_for_participant($roomId,$targetId,true); } catch(Throwable $ignored) {} } vmic_reply(false,'تعذر حفظ دور العضو'); }
        if(!empty($rateChange['charged']) && function_exists('redisUpdateUser')){ redisUpdateUser($targetId); }
        vmic_log($roomId,$actorId,$targetId,'listener','تحويل العضو إلى مستمع؛ سعر الدقيقة الجديد '.$rateChange['rate'].' ذهب');
        vmic_reply(true,'تم تحويل العضو إلى مستمع');
    }

    if($action === 'kick' || $action === 'ban'){
        $banMinutes = isset($_POST['duration']) ? max(0,min(525600,(int)$_POST['duration'])) : 60;
        $reason = isset($_POST['reason']) ? trim((string)$_POST['reason']) : '';
        if(function_exists('mb_substr')){ $reason=mb_substr($reason,0,500); } else { $reason=substr($reason,0,500); }
        if($action === 'ban'){
            $expires = $banMinutes === 0 ? 0 : $now + ($banMinutes * 60);
            $stmt=$mysqli->prepare("INSERT INTO boom_voice_mic_bans (room_id,user_id,expires_at,reason,created_by,created_at) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE expires_at=VALUES(expires_at),reason=VALUES(reason),created_by=VALUES(created_by),created_at=VALUES(created_at)");
            if(!$stmt){ vmic_reply(false,'تعذر تسجيل التقييد في قاعدة البيانات'); }
            $stmt->bind_param('iiisii',$roomId,$targetId,$expires,$reason,$actorId,$now); $banSaved=$stmt->execute(); $stmt->close();
            if(!$banSaved){ vmic_reply(false,'تعذر تسجيل التقييد في قاعدة البيانات'); }
        }
        try { vmic_livekit_service()->removeParticipant($roomName,(string)$targetId); } catch(Throwable $e){ /* already disconnected is acceptable */ }
        $mysqli->query("DELETE FROM boom_voice_mic_members WHERE room_id={$roomId} AND user_id={$targetId}");
        $mysqli->query("DELETE FROM boom_voice_mic_requests WHERE room_id={$roomId} AND user_id={$targetId}");
        vmic_log($roomId,$actorId,$targetId,$action,$action==='ban' ? ('مدة التقييد بالدقائق: '.($banMinutes===0?'دائم':$banMinutes).'; '.$reason) : 'تنزيل من المكالمة');
        vmic_reply(true,$action==='ban' ? 'تم تقييد العضو ومنعه من دخول المايك' : 'تم تنزيل العضو من المايك');
    }

    if($action === 'approve' || $action === 'reject'){
        $pendingCheck=$mysqli->query("SELECT status FROM boom_voice_mic_requests WHERE room_id={$roomId} AND user_id={$targetId} LIMIT 1");
        if(!$pendingCheck || !$pendingCheck->num_rows || (int)$pendingCheck->fetch_assoc()['status'] !== 0){ vmic_reply(false,'لا يوجد طلب تحدث معلّق لهذا العضو'); }
        if(!vmic_can_manage_room($actor,$settings,(int)$settings['manage_rank'])){ vmic_reply(false,'لا تملك صلاحية الموافقة على طلبات التحدث'); }
        if($action === 'approve'){
            if(vmic_speaking_mute($roomId,$targetId)){ vmic_reply(false,'هذا العضو مقيّد من التحدث ولا يمكن الموافقة على طلبه حتى إلغاء الكتم الإداري'); }
            if(!vmic_rank_allowed(array('user_rank'=>(int)$target['user_rank']),(int)$settings['min_speaker_rank'])){ vmic_reply(false,'رتبة هذا العضو لا تسمح له بالتحدث'); }
            $settle=vmic_bill_due_minutes($roomId,$targetId,$now);
            if(empty($settle['ok'])){ vmic_reply(false,$settle['message']); }
            if(!$mysqli->begin_transaction()){ vmic_reply(false,'تعذر بدء اعتماد طلب التحدث'); }
            $capacityLock=$mysqli->query("SELECT id FROM boom_voice_mic_settings WHERE id=1 FOR UPDATE");
            if(!$capacityLock){ $mysqli->rollback(); vmic_reply(false,'تعذر التحقق من عدد المتحدثين'); }
            if(vmic_count($roomId,'speaker') >= (int)$settings['max_speakers']){ $mysqli->rollback(); vmic_reply(false,'وصلت الغرفة إلى الحد الأقصى للمتحدثين'); }
            $memberQuery=$mysqli->query("SELECT * FROM boom_voice_mic_members WHERE room_id={$roomId} AND user_id={$targetId} LIMIT 1 FOR UPDATE");
            if(!$memberQuery || !$memberQuery->num_rows){ $mysqli->rollback(); vmic_reply(false,'انتهت جلسة العضو قبل اعتماد طلبه'); }
            $targetMember=$memberQuery->fetch_assoc();
            $rateChange=vmic_switch_billing_rate($roomId,$targetId,'speaker',$now,$targetMember);
            if(empty($rateChange['ok'])){ $mysqli->rollback(); vmic_reply(false,$rateChange['message']); }
            try { vmic_permission_for_participant($roomId,$targetId,true); } catch(Throwable $e){ $mysqli->rollback(); vmic_reply(false,'تعذر منح صلاحية الميكروفون'); }
            $stmt=$mysqli->prepare("UPDATE boom_voice_mic_members SET mic_role='speaker',mic_muted=1,admin_muted=0,last_seen=? WHERE room_id=? AND user_id=?");
            if(!$stmt){ $mysqli->rollback(); try { vmic_permission_for_participant($roomId,$targetId,false); } catch(Throwable $ignored) {} vmic_reply(false,'تعذر حفظ دور المتحدث'); }
            $stmt->bind_param('iii',$now,$roomId,$targetId); $saved=$stmt->execute(); $stmt->close();
            if(!$saved){ $mysqli->rollback(); try { vmic_permission_for_participant($roomId,$targetId,false); } catch(Throwable $ignored) {} vmic_reply(false,'تعذر حفظ دور المتحدث'); }
            if(!$mysqli->commit()){ $mysqli->rollback(); try { vmic_permission_for_participant($roomId,$targetId,false); } catch(Throwable $ignored) {} vmic_reply(false,'تعذر تأكيد صلاحية المتحدث'); }
            if(!empty($rateChange['charged']) && function_exists('redisUpdateUser')){ redisUpdateUser($targetId); }
            vmic_log_request($roomId,$targetId,1);
            vmic_log($roomId,$actorId,$targetId,'approve','الموافقة على طلب التحدث');
            vmic_reply(true,'تمت الموافقة؛ أصبح العضو متصلاً ويمكنه فتح الميكروفون');
        } else {
            vmic_log_request($roomId,$targetId,2);
            vmic_log($roomId,$actorId,$targetId,'reject','رفض طلب التحدث');
            vmic_reply(true,'تم رفض طلب التحدث');
        }
    }
    vmic_reply(false,'أمر إداري غير معروف');
}

if(!boomLogged() || !isset($addons) || !boomAllow($addons['addons_access'])){ vmic_reply(false,'ليس لديك صلاحية استخدام المايك'); }
$action = isset($_POST['action']) ? trim((string)$_POST['action']) : '';
$roomId = (int)$data['user_roomid'];
$userId = (int)$data['user_id'];
$postedRoom = isset($_POST['room_id']) ? (int)$_POST['room_id'] : $roomId;
$settings = vmic_settings();
$now = time();

if($postedRoom !== $roomId){
    if(in_array($action,array('heartbeat','leave'),true) && $postedRoom > 0){
        $mysqli->query("DELETE FROM boom_voice_mic_members WHERE room_id={$postedRoom} AND user_id={$userId}");
        try { vmic_livekit_service()->removeParticipant(vmic_live_room_name($postedRoom),(string)$userId); } catch(Throwable $e){}
        vmic_reply($action==='leave','تم إنهاء جلسة الغرفة السابقة');
    }
    vmic_reply(false,'تغيرت غرفة الدردشة؛ أعد الدخول إلى المايك في الغرفة الجديدة');
}

if($action === 'state'){
    if(!vmic_room_selected($roomId,$settings)){
        // When a room is removed or disabled, clear any old audio participants as well.
        $oldMembers=$mysqli->query("SELECT user_id FROM boom_voice_mic_members WHERE room_id={$roomId}");
        if($oldMembers){ while($old=$oldMembers->fetch_assoc()){ try { vmic_livekit_service()->removeParticipant(vmic_live_room_name($roomId),(string)(int)$old['user_id']); } catch(Throwable $e){} } }
        $mysqli->query("DELETE FROM boom_voice_mic_members WHERE room_id={$roomId}");
        $mysqli->query("DELETE FROM boom_voice_mic_requests WHERE room_id={$roomId}");
        vmic_reply(true,'',array('available'=>false,'visible'=>false,'reason'=>'','speakers'=>array(),'listeners'=>array(),'total'=>0));
    }
    if(!vmic_room_enabled($roomId,$settings)){
        $oldMembers=$mysqli->query("SELECT user_id FROM boom_voice_mic_members WHERE room_id={$roomId}");
        if($oldMembers){ while($old=$oldMembers->fetch_assoc()){ try { vmic_livekit_service()->removeParticipant(vmic_live_room_name($roomId),(string)(int)$old['user_id']); } catch(Throwable $e){} } }
        $mysqli->query("DELETE FROM boom_voice_mic_members WHERE room_id={$roomId}");
        $mysqli->query("DELETE FROM boom_voice_mic_requests WHERE room_id={$roomId}");
        $reason=((int)$settings['pause_until']>$now ? $settings['pause_message'] : 'المايك متوقف حالياً');
        vmic_reply(true,'',array('available'=>false,'visible'=>true,'reason'=>$reason,'speakers'=>array(),'listeners'=>array(),'total'=>0));
    }
    $expiry=$now-40;
    // Remove stale participants in bounded batches. Delete only rows that were
    // actually attempted, so a room with more than 50 stale clients is cleaned
    // over successive polls instead of leaving ghost LiveKit participants behind.
    $staleIds=array();
    $stale=$mysqli->query("SELECT user_id FROM boom_voice_mic_members WHERE room_id={$roomId} AND last_seen < {$expiry} LIMIT 50");
    if($stale){
        while($old=$stale->fetch_assoc()){
            $staleId=(int)$old['user_id'];
            $staleIds[]=$staleId;
            try { vmic_livekit_service()->removeParticipant(vmic_live_room_name($roomId),(string)$staleId); } catch(Throwable $e){}
        }
    }
    if(count($staleIds)){
        $staleList=implode(',',array_map('intval',$staleIds));
        $mysqli->query("DELETE FROM boom_voice_mic_members WHERE room_id={$roomId} AND user_id IN ({$staleList}) AND last_seen < {$expiry}");
    }
    $mysqli->query("DELETE r FROM boom_voice_mic_requests r LEFT JOIN boom_voice_mic_members m ON m.room_id=r.room_id AND m.user_id=r.user_id WHERE r.room_id={$roomId} AND (m.user_id IS NULL OR r.created_at < ".($now-900).")");
    $members=array();
    $q=$mysqli->query("SELECT m.user_id,m.mic_role,m.mic_muted,m.admin_muted,u.user_name,u.user_rank,u.user_role,u.user_tumb
        FROM boom_voice_mic_members m INNER JOIN boom_users u ON u.user_id=m.user_id
        WHERE m.room_id={$roomId} AND m.last_seen >= {$expiry} AND m.payment_confirmed=1 AND u.user_status <> 99 AND u.user_roomid={$roomId}
        ORDER BY CASE WHEN m.mic_role='speaker' THEN 0 ELSE 1 END,m.joined_at ASC");
    if($q){ while($u=$q->fetch_assoc()){
        $rankLabel=((int)$u['user_role']>=4 && (int)$u['user_role']<9) ? roomRankTitle((int)$u['user_role']) : rankTitle((int)$u['user_rank']);
        $members[]=array('user_id'=>(int)$u['user_id'],'user_name'=>$u['user_name'],'avatar'=>myAvatar($u['user_tumb']),'rank'=>$rankLabel,'system_rank'=>(int)$u['user_rank'],'role'=>$u['mic_role'],'muted'=>(bool)$u['mic_muted'],'admin_muted'=>(bool)$u['admin_muted']);
    }}
    $speakers=array_values(array_filter($members,function($m){return $m['role']==='speaker';}));
    $listeners=array_values(array_filter($members,function($m){return $m['role']==='listener';}));
    $self=vmic_member($roomId,$userId);
    $pending=array();
    $canManage=vmic_can_manage_room($data,$settings);
    $canBan=vmic_can_manage_room($data,$settings,(int)$settings['ban_rank']);
    if($canManage){
        $rq=$mysqli->query("SELECT r.user_id,u.user_name,u.user_tumb,u.user_rank FROM boom_voice_mic_requests r INNER JOIN boom_users u ON u.user_id=r.user_id WHERE r.room_id={$roomId} AND r.status=0 AND u.user_roomid={$roomId} ORDER BY r.created_at ASC LIMIT 30");
        if($rq){ while($r=$rq->fetch_assoc()){ $pending[]=array('user_id'=>(int)$r['user_id'],'user_name'=>$r['user_name'],'avatar'=>myAvatar($r['user_tumb']),'rank'=>rankTitle((int)$r['user_rank'])); }}
    }
    vmic_reply(true,'',array(
        'available'=>true,'room_id'=>$roomId,'speakers'=>$speakers,'listeners'=>$listeners,
        'speaker_count'=>count($speakers),'listener_count'=>count($listeners),'total'=>count($members),
        'self'=>$self,'can_manage'=>$canManage,'can_ban'=>$canBan,'pending_requests'=>$pending,
        'bar_height'=>(int)$settings['bar_height'],'avatar_size'=>(int)$settings['avatar_size'],
        'show_names'=>(int)$settings['show_names'],'show_ranks'=>(int)$settings['show_ranks'],
        'speaker_gold_mode'=>(int)$settings['speaker_gold_mode'],'speaker_gold_cost'=>(int)$settings['speaker_gold_cost'],'listener_gold_mode'=>(int)$settings['listener_gold_mode'],'listener_gold_cost'=>(int)$settings['listener_gold_cost'],'speaker_gold_per_minute'=>vmic_fee_for_role($settings,'speaker'),'listener_gold_per_minute'=>vmic_fee_for_role($settings,'listener'),'require_speaker_approval'=>(int)$settings['require_speaker_approval'],
        'wallet_enabled'=>(function_exists('useWallet') && useWallet()) ? 1 : 0,
        'speaker_color'=>$settings['speaker_color'],'listener_color'=>$settings['listener_color'],'divider_color'=>$settings['divider_color']
    ));
}

if($action === 'join'){
    if(!vmic_room_enabled($roomId,$settings)){ vmic_reply(false,'المايك غير متاح في هذه الغرفة'); }
    if(!vmic_live_ready()){ vmic_reply(false,'خدمة LiveKit غير مهيأة؛ أدخل بياناتها في إعدادات مكالمات الشات'); }
    if((int)$data['user_status']===99 || isBot($data)){ vmic_reply(false,'هذا الحساب لا يستطيع دخول المايك'); }
    $mode=isset($_POST['mode']) && $_POST['mode']==='speaker' ? 'speaker' : 'listener';
    if($mode==='listener' && !(int)$settings['allow_listeners']){ vmic_reply(false,'الاستماع غير متاح حالياً'); }
    $minRank=$mode==='speaker' ? (int)$settings['min_speaker_rank'] : (int)$settings['min_listener_rank'];
    if(!vmic_rank_allowed($data,$minRank)){ vmic_reply(false,'رتبتك لا تسمح بدخول هذا الوضع'); }
    $ban=vmic_is_banned($roomId,$userId);
    if($ban){ vmic_reply(false,'أنت مقيّد من دخول المايك'.(!empty($ban['reason']) ? ': '.$ban['reason'] : '')); }
    $speakingMute=vmic_speaking_mute($roomId,$userId);
    if($mode==='speaker' && $speakingMute){ vmic_reply(false,'تم تقييدك من التحدث في هذا الروم؛ يمكنك الدخول كمستمع فقط'); }
    // Serialize entry, capacity and payment decisions. Re-read membership inside
    // the transaction so retrying the same live session does not charge twice.
    if(!$mysqli->begin_transaction()) { vmic_reply(false,'تعذر بدء جلسة دخول المايك'); }
    $capacityLock=$mysqli->query("SELECT id FROM boom_voice_mic_settings WHERE id=1 FOR UPDATE");
    if(!$capacityLock){ $mysqli->rollback(); vmic_reply(false,'تعذر التحقق من سعة غرفة المايك'); }
    $existingQuery=$mysqli->query("SELECT * FROM boom_voice_mic_members WHERE room_id={$roomId} AND user_id={$userId} LIMIT 1 FOR UPDATE");
    if(!$existingQuery){ $mysqli->rollback(); vmic_reply(false,'تعذر التحقق من عضوية المايك'); }
    $existing=$existingQuery->num_rows ? $existingQuery->fetch_assoc() : array();
    $activeExisting=!empty($existing) && (int)$existing['last_seen'] >= ($now-40);
    if(!$activeExisting || $existing['mic_role'] !== $mode){
        if($mode==='speaker' && vmic_count($roomId,'speaker') >= (int)$settings['max_speakers']){ $mysqli->rollback(); vmic_reply(false,'اكتمل عدد المتصلين بالمايك'); }
        if($mode==='listener' && vmic_count($roomId,'listener') >= (int)$settings['max_listeners']){ $mysqli->rollback(); vmic_reply(false,'اكتمل عدد المستمعين'); }
    }
    $actualMode=$mode;
    $pending=false;
    if($mode==='speaker' && (int)$settings['require_speaker_approval'] && !vmic_can_manage_room($data,$settings)){
        if(vmic_count($roomId,'listener') >= (int)$settings['max_listeners']){ $mysqli->rollback(); vmic_reply(false,'اكتمل عدد المستمعين'); }
        $actualMode='listener'; $pending=true;
    }
    // One active session per account and room; the newest login replaces a stale tab.
    try { vmic_livekit_service()->removeParticipant(vmic_live_room_name($roomId),(string)$userId); } catch(Throwable $e){}
    $adminMuted=$speakingMute ? 1 : 0;
    if($activeExisting){
        // Reconnect or re-entering the same active session: retain payment status.
        $stmt=$mysqli->prepare("UPDATE boom_voice_mic_members SET mic_role=?,mic_muted=1,admin_muted=?,last_seen=? WHERE room_id=? AND user_id=?");
        if(!$stmt){ $mysqli->rollback(); vmic_reply(false,'تعذر تحديث العضوية الصوتية'); }
        $stmt->bind_param('siiii',$actualMode,$adminMuted,$now,$roomId,$userId);
    } else {
        // New session starts pending and receives no media permissions until confirm_join.
        $stmt=$mysqli->prepare("INSERT INTO boom_voice_mic_members (room_id,user_id,mic_role,mic_muted,admin_muted,payment_confirmed,paid_gold,billing_rate,billed_minutes,next_bill_at,billing_started_at,joined_at,last_seen) VALUES (?,?,?,1,?,0,0,0,0,0,0,?,?) ON DUPLICATE KEY UPDATE mic_role=VALUES(mic_role),mic_muted=1,admin_muted=VALUES(admin_muted),payment_confirmed=0,paid_gold=0,billing_rate=0,billed_minutes=0,next_bill_at=0,billing_started_at=0,joined_at=VALUES(joined_at),last_seen=VALUES(last_seen)");
        if(!$stmt){ $mysqli->rollback(); vmic_reply(false,'تعذر تسجيل العضوية الصوتية'); }
        $stmt->bind_param('iisiii',$roomId,$userId,$actualMode,$adminMuted,$now,$now);
    }
    $saved=$stmt->execute(); $stmt->close();
    if(!$saved){ $mysqli->rollback(); vmic_reply(false,'تعذر تسجيل العضوية الصوتية'); }
    try { $token=vmic_live_token($roomId,$data,$actualMode); }
    catch(Throwable $e){ $mysqli->rollback(); error_log('Voice Mic token: '.$e->getMessage()); vmic_reply(false,'تعذر إنشاء تصريح الاتصال الصوتي'); }
    if(!$mysqli->commit()){ $mysqli->rollback(); vmic_reply(false,'تعذر تأكيد جلسة المايك'); }
    if($pending){ vmic_log_request($roomId,$userId,0); }
    vmic_log($roomId,$userId,$userId,$actualMode==='speaker'?'join_speaker':'join_listener',$pending?'طلب موافقة على التحدث':'طلب دخول المايك');
    vmic_reply(true,$pending?'اتصلت بالمايك كمستمع مؤقتاً؛ بانتظار موافقة التحدث':'تم تجهيز جلسة المايك؛ جارٍ تأكيد الدخول',array('url'=>$setting['live_url'],'token'=>$token,'role'=>$actualMode,'pending'=>$pending,'room_id'=>$roomId));
}

if($action === 'confirm_join'){
    if(!vmic_room_enabled($roomId,$settings)){ vmic_reply(false,'المايك غير متاح في هذه الغرفة'); }
    if(!$mysqli->begin_transaction()){ vmic_reply(false,'تعذر تأكيد دخول المايك'); }
    $memberQuery=$mysqli->query("SELECT * FROM boom_voice_mic_members WHERE room_id={$roomId} AND user_id={$userId} LIMIT 1 FOR UPDATE");
    if(!$memberQuery || !$memberQuery->num_rows){ $mysqli->rollback(); vmic_reply(false,'انتهت عضويتك في المايك؛ أعد الدخول'); }
    $member=$memberQuery->fetch_assoc();
    if((int)$member['last_seen'] < ($now-40)){ $mysqli->rollback(); vmic_reply(false,'انتهت مهلة جلسة المايك؛ أعد الدخول'); }
    if((int)$member['payment_confirmed']===1){
        // Reconnection to an already-paid active session is free, but this fresh
        // LiveKit participant still needs its media permissions restored.
        $canPublish=$member['mic_role']==='speaker' && !(int)$member['admin_muted'] && !vmic_speaking_mute($roomId,$userId);
        if(!vmic_set_live_publish($roomId,$userId,$canPublish)){
            $mysqli->rollback();
            try { vmic_permission_for_participant($roomId,$userId,false,false); } catch(Throwable $ignored) {}
            vmic_reply(false,'تعذر استعادة صلاحيات الصوت لجلسة المايك');
        }
        if(!$mysqli->commit()){ $mysqli->rollback(); vmic_reply(false,'تعذر تأكيد الجلسة'); }
        vmic_reply(true,'الدخول مؤكد مسبقاً',array('gold_charged'=>0,'already_confirmed'=>true));
    }
    $chargedGold=0;
    $billingRate=0;
    $billedMinutes=0;
    $nextBillAt=0;
    $billingStartedAt=0;
    $billingRate=vmic_fee_for_role($settings,$member['mic_role']);
    if($billingRate>0){
        if($billingRate<1){ $mysqli->rollback(); vmic_reply(false,'سعر الدقيقة غير مضبوط؛ أبلغ الإدارة'); }
        $charge=vmic_charge_gold($userId,$billingRate);
        if(empty($charge['ok'])){ $mysqli->rollback(); vmic_reply(false,$charge['message'],array('gold_per_minute'=>$billingRate,'gold_balance'=>(int)$charge['balance'])); }
        $chargedGold=$billingRate;
        $billedMinutes=1;
        $billingStartedAt=$now;
        $nextBillAt=$now+60;
    }
    // Grant subscription and the appropriate publisher permission only after the charge succeeds.
    if(!vmic_set_live_publish($roomId,$userId,$member['mic_role']==='speaker' && !(int)$member['admin_muted'] && !vmic_speaking_mute($roomId,$userId))){
        $mysqli->rollback();
        try { vmic_permission_for_participant($roomId,$userId,false,false); } catch(Throwable $ignored) {}
        vmic_reply(false,'تعذر تفعيل صلاحيات الصوت؛ لم يتم اعتماد خصم الذهب');
    }
    $stmt=$mysqli->prepare("UPDATE boom_voice_mic_members SET payment_confirmed=1,paid_gold=?,billing_rate=?,billed_minutes=?,next_bill_at=?,billing_started_at=?,last_seen=? WHERE room_id=? AND user_id=?");
    if(!$stmt){ $mysqli->rollback(); try { vmic_permission_for_participant($roomId,$userId,false,false); } catch(Throwable $ignored) {} vmic_reply(false,'تعذر حفظ تأكيد الدفع'); }
    $stmt->bind_param('iiiiiiii',$chargedGold,$billingRate,$billedMinutes,$nextBillAt,$billingStartedAt,$now,$roomId,$userId); $saved=$stmt->execute(); $stmt->close();
    if(!$saved){ $mysqli->rollback(); try { vmic_permission_for_participant($roomId,$userId,false,false); } catch(Throwable $ignored) {} vmic_reply(false,'تعذر حفظ تأكيد الدفع'); }
    if(!$mysqli->commit()){
        $mysqli->rollback();
        try { vmic_permission_for_participant($roomId,$userId,false,false); } catch(Throwable $ignored) {}
        vmic_reply(false,'تعذر إتمام عملية الدفع وتأكيد الجلسة');
    }
    if($chargedGold>0){
        if(function_exists('redisUpdateUser')){ redisUpdateUser($userId); }
        vmic_log($roomId,$userId,$userId,'gold_minute_charge','خصم '.$chargedGold.' ذهب مقابل الدقيقة الأولى من المايك؛ السعر '.$billingRate.' ذهب/دقيقة؛ الوضع '.($member['mic_role']==='speaker'?'المتصل':'المستمع'));
    }
    vmic_log($roomId,$userId,$userId,'entry_confirmed',$chargedGold>0?'تم تأكيد الدخول بعد خصم '.$chargedGold.' ذهب':'تم تأكيد دخول مجاني');
    vmic_reply(true,$chargedGold>0?'تم خصم '.$chargedGold.' ذهب مقابل الدقيقة الأولى':'تم تأكيد دخول المايك مجاناً',array('gold_charged'=>$chargedGold,'gold_per_minute'=>$billingRate,'billed_minutes'=>$billedMinutes,'next_bill_at'=>$nextBillAt));
}

if($action === 'heartbeat'){
    if(!vmic_room_enabled($roomId,$settings)){
        $mysqli->query("DELETE FROM boom_voice_mic_members WHERE room_id={$roomId} AND user_id={$userId}");
        try { vmic_livekit_service()->removeParticipant(vmic_live_room_name($roomId),(string)$userId); } catch(Throwable $e){}
        vmic_reply(false,'تم إيقاف المايك في هذه الغرفة');
    }
    if(vmic_is_banned($roomId,$userId)){
        $mysqli->query("DELETE FROM boom_voice_mic_members WHERE room_id={$roomId} AND user_id={$userId}");
        try { vmic_livekit_service()->removeParticipant(vmic_live_room_name($roomId),(string)$userId); } catch(Throwable $e){}
        vmic_reply(false,'تم تقييدك من المايك');
    }
    $billing=vmic_bill_due_minutes($roomId,$userId,$now);
    if(empty($billing['ok'])){
        if(!empty($billing['disconnect'])){
            if(isset($billing['required'])){ vmic_log($roomId,$userId,$userId,'billing_failed','تم إخراج العضو لعدم كفاية الذهب لتجديد الدقيقة؛ المطلوب '.$billing['required'].' ذهب والمتوفر '.(int)($billing['balance']??0)); }
            $mysqli->query("DELETE FROM boom_voice_mic_members WHERE room_id={$roomId} AND user_id={$userId}");
            try { vmic_livekit_service()->removeParticipant(vmic_live_room_name($roomId),(string)$userId); } catch(Throwable $e){}
        }
        vmic_reply(false,$billing['message'],array('balance'=>$billing['balance']??null,'required'=>$billing['required']??0));
    }
    vmic_reply(true,'',array('charged_gold'=>(int)$billing['charged_gold'],'charged_minutes'=>(int)$billing['charged_minutes'],'balance'=>$billing['balance'],'billing_rate'=>(int)$billing['billing_rate'],'next_bill_at'=>(int)$billing['next_bill_at']));
}

if($action === 'leave'){
    $leaveBilling=vmic_bill_due_minutes($roomId,$userId,$now);
    $mysqli->query("DELETE FROM boom_voice_mic_members WHERE room_id={$roomId} AND user_id={$userId}");
    $mysqli->query("DELETE FROM boom_voice_mic_requests WHERE room_id={$roomId} AND user_id={$userId}");
    try { vmic_livekit_service()->removeParticipant(vmic_live_room_name($roomId),(string)$userId); } catch(Throwable $e){}
    vmic_log($roomId,$userId,$userId,'leave','مغادرة المايك');
    $leaveMessage='تم الخروج من المايك';
    if(!empty($leaveBilling['charged_gold'])){ $leaveMessage.='؛ تم خصم '.$leaveBilling['charged_gold'].' ذهب عن الدقيقة المكتملة'; }
    vmic_reply(true,$leaveMessage,array('charged_gold'=>(int)($leaveBilling['charged_gold']??0),'charged_minutes'=>(int)($leaveBilling['charged_minutes']??0)));
}

if($action === 'change_role'){
    $targetRole=isset($_POST['role']) && $_POST['role']==='speaker' ? 'speaker' : 'listener';
    $member=vmic_member($roomId,$userId);
    if(!$member){ vmic_reply(false,'يجب دخول المايك أولاً'); }
    if($member['mic_role']===$targetRole){ vmic_reply(true,'أنت بالفعل في هذا الوضع',array('role'=>$targetRole,'charged_gold'=>0)); }
    if($targetRole==='listener' && !(int)$settings['allow_listeners']){ vmic_reply(false,'وضع الاستماع غير متاح حالياً'); }
    if($targetRole==='speaker'){
        if(!vmic_rank_allowed($data,(int)$settings['min_speaker_rank'])){ vmic_reply(false,'رتبتك لا تسمح بالتحدث'); }
        if((int)$member['admin_muted'] || vmic_speaking_mute($roomId,$userId)){ vmic_reply(false,'قام المشرف بتقييد صوتك؛ لا يمكنك العودة إلى الاتصال حتى يرفع التقييد'); }
        $ban=vmic_is_banned($roomId,$userId); if($ban){ vmic_reply(false,'أنت مقيّد من دخول وضع المتحدث'); }
        if((int)$settings['require_speaker_approval'] && !vmic_can_manage_room($data,$settings)){
            vmic_log_request($roomId,$userId,0);
            vmic_reply(true,'تم إرسال طلب التحدث للمشرف',array('pending'=>true));
        }
    }
    // Settle any completed minute at the old role's price before changing the rate.
    $settle=vmic_bill_due_minutes($roomId,$userId,$now);
    if(empty($settle['ok'])){
        if(!empty($settle['disconnect'])){ $mysqli->query("DELETE FROM boom_voice_mic_members WHERE room_id={$roomId} AND user_id={$userId}"); try { vmic_remove_live_participant($roomId,$userId); } catch(Throwable $ignored){} }
        vmic_reply(false,$settle['message']);
    }
    if(!$mysqli->begin_transaction()){ vmic_reply(false,'تعذر بدء تغيير وضع المايك'); }
    $capacityLock=$mysqli->query("SELECT id FROM boom_voice_mic_settings WHERE id=1 FOR UPDATE");
    if(!$capacityLock){ $mysqli->rollback(); vmic_reply(false,'تعذر التحقق من سعة غرفة المايك'); }
    $memberQuery=$mysqli->query("SELECT * FROM boom_voice_mic_members WHERE room_id={$roomId} AND user_id={$userId} LIMIT 1 FOR UPDATE");
    if(!$memberQuery || !$memberQuery->num_rows){ $mysqli->rollback(); vmic_reply(false,'انتهت عضويتك في المايك؛ أعد الدخول'); }
    $member=$memberQuery->fetch_assoc();
    if($targetRole==='speaker' && $member['mic_role']!=='speaker' && vmic_count($roomId,'speaker') >= (int)$settings['max_speakers']){ $mysqli->rollback(); vmic_reply(false,'اكتمل عدد المتصلين بالمايك'); }
    $rateChange=vmic_switch_billing_rate($roomId,$userId,$targetRole,$now,$member);
    if(empty($rateChange['ok'])){ $mysqli->rollback(); vmic_reply(false,$rateChange['message']); }
    try { vmic_permission_for_participant($roomId,$userId,$targetRole==='speaker'); }
    catch(Throwable $e){ $mysqli->rollback(); try { vmic_permission_for_participant($roomId,$userId,$member['mic_role']==='speaker'); } catch(Throwable $ignored){} vmic_reply(false,'تعذر تغيير صلاحيات الاتصال لدى خادم الصوت'); }
    if($targetRole==='listener'){
        vmic_mute_live_track($roomId,$userId,true);
        $stmt=$mysqli->prepare("UPDATE boom_voice_mic_members SET mic_role='listener',mic_muted=1,last_seen=? WHERE room_id=? AND user_id=?");
    } else {
        $stmt=$mysqli->prepare("UPDATE boom_voice_mic_members SET mic_role='speaker',mic_muted=1,admin_muted=0,last_seen=? WHERE room_id=? AND user_id=?");
    }
    if(!$stmt){ $mysqli->rollback(); try { vmic_permission_for_participant($roomId,$userId,$member['mic_role']==='speaker'); } catch(Throwable $ignored){} vmic_reply(false,'تعذر حفظ وضع المايك الجديد'); }
    $stmt->bind_param('iii',$now,$roomId,$userId); $saved=$stmt->execute(); $stmt->close();
    if(!$saved || !$mysqli->commit()){
        $mysqli->rollback();
        try { vmic_permission_for_participant($roomId,$userId,$member['mic_role']==='speaker'); } catch(Throwable $ignored){}
        vmic_reply(false,'تعذر تأكيد تغيير وضع المايك');
    }
    if(!empty($rateChange['charged']) && function_exists('redisUpdateUser')){ redisUpdateUser($userId); }
    vmic_log($roomId,$userId,$userId,$targetRole==='speaker'?'self_speaker':'self_listener',$targetRole==='speaker'?'التحويل إلى متصل':'التحويل إلى مستمع');
    vmic_reply(true,$targetRole==='speaker'?'تم تحويلك إلى متصل؛ افتح الميكروفون عند الاستعداد':'أصبحت مستمعاً',array('role'=>$targetRole,'charged_gold'=>(int)$rateChange['charged'],'billing_rate'=>(int)$rateChange['rate'],'next_bill_at'=>(int)$rateChange['next_bill_at']));
}

if($action === 'mute_self'){
    $member=vmic_member($roomId,$userId);
    if(!$member || $member['mic_role']!=='speaker'){ vmic_reply(false,'هذه الوظيفة للمتصلين فقط'); }
    $muted=isset($_POST['muted']) && (int)$_POST['muted']===1 ? 1 : 0;
    if(!$muted && ((int)$member['admin_muted'] || vmic_speaking_mute($roomId,$userId))){ vmic_reply(false,'قام المشرف بتقييد صوتك؛ لا يمكنك فتح الميكروفون حتى يرفع التقييد'); }
    try { vmic_permission_for_participant($roomId,$userId,(!$muted && !(int)$member['admin_muted'])); }
    catch(Throwable $e){ vmic_reply(false,'تعذر تحديث صلاحية الميكروفون لدى خادم الصوت'); }
    $stmt=$mysqli->prepare("UPDATE boom_voice_mic_members SET mic_muted=?,last_seen=? WHERE room_id=? AND user_id=?");
    if($stmt){ $stmt->bind_param('iiii',$muted,$now,$roomId,$userId); $ok=$stmt->execute(); $stmt->close(); } else { $ok=false; }
    if(!$ok){ try { vmic_permission_for_participant($roomId,$userId,false); } catch(Throwable $ignored) {} vmic_reply(false,'تعذر حفظ حالة الميكروفون'); }
    vmic_log($roomId,$userId,$userId,$muted?'self_mute':'self_unmute','تغيير حالة ميكروفون العضو');
    vmic_reply(true,'',array('muted'=>(bool)$muted));
}

if($action === 'admin'){
    $adminAction=isset($_POST['admin_action']) ? trim((string)$_POST['admin_action']) : '';
    $targetId=isset($_POST['target_id']) ? (int)$_POST['target_id'] : 0;
    vmic_admin_action($roomId,$data,$settings,$adminAction,$targetId);
}

vmic_reply(false,'طلب غير معروف');
?>
