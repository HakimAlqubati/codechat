<?php
$load_addons = 'Room_wall';
require_once('../../../system/config_addons.php');
if (empty($data) || empty($addons) || (!boomAllow($addons['addons_access']) && !canManageAddons())) die();
rwEnsureSchema();

function rwReplyJson($payload){
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function rwIntPost($key,$default=0){ return isset($_POST[$key]) ? (int)$_POST[$key] : $default; }
function rwTextPost($key,$max=3000){
    $v=isset($_POST[$key]) ? clearBreak($_POST[$key]) : '';
    $v=trimContent($v);
    if(function_exists('wordFilter')) $v=wordFilter($v);
    if(function_exists('mb_substr')) $v=mb_substr($v,0,$max,'UTF-8'); else $v=substr($v,0,$max);
    return escape($v);
}
function rwInputCharCount($key){
    $v=isset($_POST[$key]) ? (string)clearBreak($_POST[$key]) : '';
    if(function_exists('mb_strlen')) return mb_strlen($v,'UTF-8');
    return strlen($v);
}
function rwGetPost($id){
    global $mysqli;
    $id=(int)$id;
    $q=$mysqli->query("SELECT * FROM boom_gen WHERE id='$id' LIMIT 1");
    return ($q && $q->num_rows) ? $q->fetch_assoc() : array();
}
function rwStoreUpload($key,$ownerType,$ownerId){
    global $mysqli,$data;
    $key=preg_replace('/[^a-f0-9]/','',(string)$key);
    if(strlen($key)!==32) return false;
    $uid=(int)$data['user_id']; $now=time(); $ownerId=(int)$ownerId;
    $q=$mysqli->query("SELECT * FROM boom_gen_upload WHERE upload_key='$key' AND file_user='$uid' AND used_at=0 AND created_at>'".($now-7200)."' LIMIT 1");
    if(!$q || !$q->num_rows) return false;
    $u=$q->fetch_assoc();
    $path=basename($u['file_path']);
    $ins=$mysqli->query("INSERT INTO boom_gen_media (owner_type,owner_id,media_path,media_mime,media_kind,media_size,created_at) VALUES ('".($ownerType==='story'?'story':'post')."','$ownerId','".escape($path)."','".escape($u['file_mime'])."','".escape($u['file_kind'])."','".(int)$u['file_size']."','$now')");
    if(!$ins) return false;
    $mysqli->query("UPDATE boom_gen_upload SET used_at='$now' WHERE id='".(int)$u['id']."' AND file_user='$uid'");
    return true;
}
function rwGetStory($id){
    global $mysqli;
    $id=(int)$id;
    $q=$mysqli->query("SELECT * FROM boom_gen_story WHERE id='$id' LIMIT 1");
    return ($q && $q->num_rows) ? $q->fetch_assoc() : array();
}
function rwCleanExpired(){
    global $mysqli;
    $s=rwSettings(); $now=time();
    if(!empty($s['archive_enabled'])) $mysqli->query("UPDATE boom_gen_story SET archived=1 WHERE expires_at<='$now' AND archived=0");
    else $mysqli->query("UPDATE boom_gen_story SET archived=2 WHERE expires_at<='$now' AND archived=0");
    // Remove abandoned uploads older than 48 hours.
    $old=$mysqli->query("SELECT id,file_path FROM boom_gen_upload WHERE used_at=0 AND created_at<'".($now-172800)."'");
    if($old){ while($r=$old->fetch_assoc()){ $f=rwMediaDir().'/'.basename($r['file_path']); if(is_file($f)) @unlink($f); } }
    $mysqli->query("DELETE FROM boom_gen_upload WHERE used_at=0 AND created_at<'".($now-172800)."'");
}
function rwLoadReplies($postId,$offset=0,$limit=10){
    global $mysqli;
    $postId=(int)$postId; $offset=(int)$offset; $limit=(int)$limit;
    $sql="SELECT r.*,u.* FROM boom_gen_reply r INNER JOIN boom_users u ON u.user_id=r.reply_user WHERE r.parent_id='$postId'";
    if($offset>0) $sql.=" AND r.reply_id<'$offset'";
    $sql.=" ORDER BY r.reply_id DESC LIMIT $limit";
    $q=$mysqli->query($sql); $html='';
    if($q) while($r=$q->fetch_assoc()) $html.=genTemplate('element/gen_reply',$r);
    return $html;
}

rwCleanExpired();
rwCleanExpiredPosts();

// Admin content search and moderation.
if(isset($_POST['rw_action']) && $_POST['rw_action']==='admin_content'){
    if(!canManageAddons()) rwReplyJson(array('ok'=>false,'error'=>'غير مصرح لك.'));
    $raw=isset($_POST['q'])?trim($_POST['q']):''; $qstr=escape($raw); $where='';
    if($raw!==''){
        if(ctype_digit($raw)) $where="WHERE g.news_poster='".(int)$raw."' OR u.user_name LIKE '%$qstr%'";
        else $where="WHERE u.user_name LIKE '%$qstr%'";
    }
    $html='<div class="rw-admin-content-list"><h4>المنشورات</h4>';
    $posts=$mysqli->query("SELECT g.id,g.news_message,g.news_date,g.room_name,g.news_poster,u.user_name FROM boom_gen g INNER JOIN boom_users u ON u.user_id=g.news_poster $where ORDER BY g.news_date DESC LIMIT 50");
    if($posts && $posts->num_rows){while($p=$posts->fetch_assoc()){
      $html .= "<div class=\"rw-admin-row\"><div><b>".rwEscHtml($p['user_name'])."</b> <small>#".(int)$p['news_poster']." · ".rwEscHtml(displayDate($p['news_date']))."</small><p>".rwEscHtml(function_exists('mb_substr')?mb_substr(strip_tags($p['news_message']),0,140,'UTF-8'):substr(strip_tags($p['news_message']),0,140))."</p><small>الروم: ".rwEscHtml($p['room_name']?:'غير محدد')."</small></div><button type=\"button\" class=\"small_button delete_btn\" onclick=\"rwAdminDelete('post',".(int)$p['id'].")\">حذف</button></div>";
    }} else $html.='<div class="empty_zone">لا توجد منشورات مطابقة.</div>';
    $html.='<h4 class="tpad15">القصص</h4>';
    $storyWhere=''; if($raw!==''){if(ctype_digit($raw))$storyWhere="WHERE s.story_user='".(int)$raw."' OR u.user_name LIKE '%$qstr%'";else $storyWhere="WHERE u.user_name LIKE '%$qstr%'";}
    $stories=$mysqli->query("SELECT s.id,s.story_text,s.created_at,s.expires_at,s.privacy,s.room_name,s.story_user,u.user_name FROM boom_gen_story s INNER JOIN boom_users u ON u.user_id=s.story_user $storyWhere ORDER BY s.created_at DESC LIMIT 50");
    if($stories && $stories->num_rows){while($st=$stories->fetch_assoc()){
      $html .= "<div class=\"rw-admin-row\"><div><b>".rwEscHtml($st['user_name'])."</b> <small>#".(int)$st['story_user']." · ".rwEscHtml(displayDate($st['created_at']))."</small><p>".rwEscHtml(function_exists('mb_substr')?mb_substr(strip_tags($st['story_text']),0,140,'UTF-8'):substr(strip_tags($st['story_text']),0,140))."</p><small>الروم: ".rwEscHtml($st['room_name']?:'غير محدد')." · الخصوصية: ".($st['privacy']==='friends'?'الأصدقاء':'الجميع')."</small></div><button type=\"button\" class=\"small_button delete_btn\" onclick=\"rwAdminDelete('story',".(int)$st['id'].")\">حذف</button></div>";
    }} else $html.='<div class="empty_zone">لا توجد قصص مطابقة.</div>';
    $html.='</div>';
    rwReplyJson(array('ok'=>true,'html'=>$html));
}
if(isset($_POST['rw_action']) && $_POST['rw_action']==='admin_delete_content'){
    if(!canManageAddons()) rwReplyJson(array('ok'=>false,'error'=>'غير مصرح لك.'));
    $id=rwIntPost('id');$type=isset($_POST['type'])?$_POST['type']:'';
    if($type==='post'){
      $p=rwGetPost($id);if(empty($p))rwReplyJson(array('ok'=>false,'error'=>'المنشور غير موجود.'));
      rwDeleteMediaOwner('post',$id);if($p['source_type']!=='chat' && function_exists('removeRelatedFile')) removeRelatedFile($id,'news');$mysqli->query("DELETE FROM boom_gen_reply WHERE parent_id='$id'");$mysqli->query("DELETE FROM boom_gen_like WHERE like_post='$id'");$mysqli->query("DELETE FROM boom_gen WHERE id='$id'");
    }elseif($type==='story'){
      $st=rwGetStory($id);if(empty($st))rwReplyJson(array('ok'=>false,'error'=>'القصة غير موجودة.'));
      rwDeleteMediaOwner('story',$id);$mysqli->query("DELETE FROM boom_gen_story_view WHERE story_id='$id'");$mysqli->query("DELETE FROM boom_gen_story_reaction WHERE story_id='$id'");$mysqli->query("DELETE FROM boom_gen_story_dm WHERE story_id='$id'");$mysqli->query("DELETE FROM boom_gen_story WHERE id='$id'");
    }else rwReplyJson(array('ok'=>false,'error'=>'نوع غير صالح.'));
    rwReplyJson(array('ok'=>true));
}

// Settings are writable only by CodyChat addon managers. config_addons.php validates session + CSRF token.
if(isset($_POST['rw_action']) && $_POST['rw_action']==='save_settings'){
    if(!canManageAddons()) rwReplyJson(array('ok'=>false,'error'=>'غير مصرح لك بإدارة الإضافة.'));
    $bools=array('posts_enabled','stories_enabled','allow_post_text','allow_post_images','allow_post_videos','allow_post_audio','allow_story_text','allow_story_images','allow_story_videos','allow_story_audio','comments_enabled','likes_enabled','reactions_enabled','story_replies_enabled','views_enabled','show_viewers','archive_enabled','allow_all_privacy','allow_friends_privacy','gold_charges_enabled');
    $ranks=array('post_rank','story_rank','view_story_rank','comment_rank','like_rank','edit_own_rank','edit_others_rank','delete_own_rank','delete_others_rank','delete_comments_rank','post_link_rank','story_link_rank','post_external_link_rank','story_external_link_rank','story_pin_rank');
    $limits=array('max_image_mb'=>array(1,100),'max_video_mb'=>array(1,100),'max_audio_mb'=>array(1,100),'story_hours'=>array(1,168),'post_expiry_days'=>array(0,365),'max_posts_hour'=>array(1,100),'max_video_seconds'=>array(5,3600),'max_audio_seconds'=>array(30,18000),'max_post_chars'=>array(100,20000),'max_story_chars'=>array(100,20000),'story_text_seconds'=>array(1,60),'story_image_seconds'=>array(1,60),'story_circle_size'=>array(40,100));
    $prices=array('post_text_gold','post_audio_gold','post_image_gold','post_video_gold','story_text_gold','story_audio_gold','story_image_gold','story_video_gold');
    $values=array();
    foreach($bools as $k) $values[$k]=isset($_POST[$k]) ? (int)!!$_POST[$k] : 0;
    foreach($ranks as $k) $values[$k]=isset($_POST[$k]) ? max(1,min(100,(int)$_POST[$k])) : (strpos($k,'_link_rank')!==false?(strpos($k,'external')!==false?100:1):($k==='story_pin_rank'?100:1));
    foreach($limits as $k=>$range){
        $v=isset($_POST[$k])?(int)$_POST[$k]:$range[0];
        $v=max($range[0],min($range[1],$v));
        if($k==='max_audio_seconds' && $v!==30) $v=max(60,min(18000,(int)(round($v/60)*60)));
        if($k==='max_post_chars' || $k==='max_story_chars') $v=max(100,min(20000,(int)(round($v/100)*100)));
        if($k==='story_circle_size') $v=max(40,min(100,(int)(round($v/2)*2)));
        $values[$k]=$v;
    }
    foreach($prices as $k) $values[$k]=isset($_POST[$k])?max(0,min(100000,(int)$_POST[$k])):0;
    if(!empty($values['gold_charges_enabled']) && (!function_exists('useWallet') || !useWallet())) rwReplyJson(array('ok'=>false,'error'=>'لا يمكن تفعيل رسوم الذهب قبل تفعيل محفظة الذهب من إعدادات CodyChat.'));
    $values['max_file_mb']=$values['max_image_mb']; // legacy v2.1 mirror
    $set=array(); foreach($values as $k=>$v) $set[]="`$k`='$v'";
    if(!$mysqli->query("UPDATE boom_gen_settings SET ".implode(',',$set)." WHERE id=1")) rwReplyJson(array('ok'=>false,'error'=>'فشل حفظ الإعدادات في قاعدة البيانات.'));
    rwReplyJson(array('ok'=>true));
}

// Repeatable, resumable import. Only unprotected public chat logs are eligible.
if(isset($_POST['rw_action']) && $_POST['rw_action']==='import_public_chats'){
    if(!canManageAddons()) rwReplyJson(array('ok'=>false,'error'=>'غير مصرح لك بالاستيراد.'));
    $state=$mysqli->query("SELECT last_chat_id FROM boom_gen_import_state WHERE id=1 LIMIT 1");
    $cursor=0; if($state && $sr=$state->fetch_assoc()) $cursor=(int)$sr['last_chat_id'];
    $sql="SELECT c.post_id,c.user_id,c.post_date,c.post_message,c.post_roomid,c.file,
                 u.user_id AS source_user_id,r.room_name,
                 (SELECT file_name FROM boom_upload up WHERE up.file_zone='chat' AND up.relative_post=c.post_id ORDER BY up.id ASC LIMIT 1) AS chat_file,
                 (SELECT file_type FROM boom_upload up2 WHERE up2.file_zone='chat' AND up2.relative_post=c.post_id ORDER BY up2.id ASC LIMIT 1) AS chat_file_type
          FROM boom_chat c
          INNER JOIN boom_users u ON u.user_id=c.user_id
          INNER JOIN boom_rooms r ON r.room_id=c.post_roomid
          WHERE c.post_id>'$cursor' AND c.type='public__message' AND c.syslog=0 AND c.pghost=0
            AND c.log_rank=999 AND r.access=0 AND r.password=''
          ORDER BY c.post_id ASC LIMIT 250";
    $q=$mysqli->query($sql);
    if(!$q) rwReplyJson(array('ok'=>false,'error'=>'تعذر قراءة رسائل الرومات. تحقق من بنية قاعدة البيانات.'));
    $imported=0; $last=$cursor; $processed=(int)$q->num_rows; $now=time();
    while($row=$q->fetch_assoc()){
        $last=(int)$row['post_id'];
        $message=escape((string)$row['post_message']);
        $filePath=''; $fileType='';
        if(!empty($row['file']) && !empty($row['chat_file'])){
            $fn=basename($row['chat_file']);
            $candidate=BOOM_PATH.'/upload/chat/'.$fn;
            if(is_file($candidate)) { $filePath='upload/chat/'.$fn; $fileType=substr((string)$row['chat_file_type'],0,20); }
        }
        if(trim(strip_tags((string)$row['post_message']))==='' && $filePath==='') continue;
        $user=(int)$row['source_user_id']; $room=(int)$row['post_roomid'];
        $roomName=escape((string)$row['room_name']); $date=max(1,(int)$row['post_date']);
        $expiryDays=(int)rwSettings()['post_expiry_days']; $postExpires=$expiryDays>0?($date+($expiryDays*86400)):0;
        $insert=$mysqli->query("INSERT IGNORE INTO boom_gen (news_poster,news_message,news_file,news_file_type,news_date,room_id,room_name,source_type,source_id,expires_at)
            VALUES ('$user','$message','".escape($filePath)."','".escape($fileType)."','$date','$room','$roomName','chat','$last','$postExpires')");
        if($insert && $mysqli->affected_rows>0){ $imported++; $mysqli->query("INSERT IGNORE INTO boom_gen_import_log (source_type,source_id,imported_at) VALUES ('chat','$last','$now')"); }
    }
    if($last>$cursor) $mysqli->query("UPDATE boom_gen_import_state SET last_chat_id='$last',updated_at='$now' WHERE id=1");
    $done=($q->num_rows<250);
    rwReplyJson(array('ok'=>true,'imported'=>$imported,'processed'=>$processed,'skipped'=>max(0,$processed-$imported),'cursor'=>$last,'done'=>$done));
}

// Create a post with atomic wallet debit and request-key idempotency.
if(isset($_POST['rw_action']) && $_POST['rw_action']==='create_post'){
    global $setting;
    if(!rwCanPost() || postBlocked()) rwReplyJson(array('ok'=>false,'error'=>'لا تملك صلاحية النشر حالياً.'));
    $s=rwSettings(); $uid=(int)$data['user_id'];
    $requestKey=rwRequestKey(isset($_POST['request_key'])?$_POST['request_key']:'');
    if($requestKey==='') rwReplyJson(array('ok'=>false,'error'=>'رمز العملية غير صالح. أعد المحاولة.'));
    $existing=rwFindChargeRequest($requestKey,$uid,'post');
    if(!empty($existing)){
        if(!empty($existing['invalid'])) rwReplyJson(array('ok'=>false,'error'=>'رمز العملية مستخدم لطلب آخر.'));
        $existingId=(int)$existing['content_id']; $existingPost=rwGetPost($existingId);
        if(!empty($existingPost) && (int)$existingPost['news_poster']===$uid) rwReplyJson(array('ok'=>true,'duplicate'=>1,'html'=>showNewsPostgen($existingId),'id'=>$existingId,'charged'=>(int)$existing['gold_amount']));
        rwReplyJson(array('ok'=>false,'error'=>'تمت معالجة الطلب السابق لكن المحتوى لم يعد موجودًا.'));
    }
    $count=$mysqli->query("SELECT COUNT(*) AS total FROM boom_gen WHERE news_poster='$uid' AND news_date>='".(time()-3600)."'");
    if($count){ $cr=$count->fetch_assoc(); if($cr && (int)$cr['total'] >= (int)$s['max_posts_hour']) rwReplyJson(array('ok'=>false,'error'=>'وصلت إلى الحد المسموح من المنشورات خلال الساعة.')); }
    if(rwInputCharCount('content')>(int)$s['max_post_chars']) rwReplyJson(array('ok'=>false,'error'=>'تجاوز النص الحد المسموح: '.(int)$s['max_post_chars'].' حرف.'));
    $rawContent=isset($_POST['content'])?$_POST['content']:'';
    $linkError=rwLinkPolicyError($rawContent,'post'); if($linkError!=='') rwReplyJson(array('ok'=>false,'error'=>$linkError));
    $content=rwTextPost('content',(int)$s['max_post_chars']); $rawUploadKey=isset($_POST['upload_key'])?(string)$_POST['upload_key']:''; $key=rwRequestKey($rawUploadKey);
    if(trim($rawUploadKey)!=='' && $key==='') rwReplyJson(array('ok'=>false,'error'=>'معرّف الملف المرفق غير صالح؛ أعد رفع الملف.'));
    if(trim(strip_tags($content))!=='' && empty($s['allow_post_text'])) rwReplyJson(array('ok'=>false,'error'=>'النصوص معطلة من الإدارة.'));
    $pending=array(); $contentKind='text';
    if($key!==''){
        $pending=rwPendingUploadInfo($key,$uid);
        if(empty($pending)) rwReplyJson(array('ok'=>false,'error'=>'انتهت صلاحية الملف المرفق. أعد رفعه.'));
        $contentKind=in_array($pending['file_kind'],array('image','video','audio'),true)?$pending['file_kind']:'';
        if($contentKind==='') rwReplyJson(array('ok'=>false,'error'=>'نوع الملف المرفق غير مدعوم.'));
        $toggle='allow_post_'.($contentKind==='image'?'images':($contentKind==='video'?'videos':'audio'));
        if(empty($s[$toggle])) rwReplyJson(array('ok'=>false,'error'=>'هذا النوع من المرفقات معطل من الإدارة.'));
    }
    if(trim(strip_tags($content))==='' && $key==='') rwReplyJson(array('ok'=>false,'error'=>'اكتب نصاً أو أرفق ملفاً.'));
    $price=rwGoldPrice('post',$contentKind);
    $room=(int)$data['user_roomid']; $roomName=escape(rwRoomSnapshot($room)); $now=time(); $expiryDays=(int)$s['post_expiry_days']; $postExpires=$expiryDays>0?($now+($expiryDays*86400)):0;
    if(!$mysqli->begin_transaction()) rwReplyJson(array('ok'=>false,'error'=>'تعذر بدء عملية النشر الآمنة.'));
    $insert=$mysqli->query("INSERT INTO boom_gen (news_poster,news_message,news_date,room_id,room_name,source_type,expires_at) VALUES ('$uid','$content','$now','$room','$roomName','manual','$postExpires')");
    if(!$insert){$mysqli->rollback();rwReplyJson(array('ok'=>false,'error'=>'تعذر حفظ المنشور.'));}
    $id=(int)$mysqli->insert_id;
    if($key!=='' && !rwStoreUpload($key,'post',$id)){$mysqli->rollback();rwReplyJson(array('ok'=>false,'error'=>'تعذر ربط الملف المرفق؛ لم يتم خصم الذهب.'));}
    $chargeError=rwSaveGoldCharge($requestKey,$uid,'post',$id,$contentKind,$price);
    if($chargeError!==''){$mysqli->rollback();rwReplyJson(array('ok'=>false,'error'=>$chargeError));}
    if(!$mysqli->commit()){$mysqli->rollback();rwReplyJson(array('ok'=>false,'error'=>'تعذر تأكيد المنشور والخصم. أعد المحاولة بنفس الطلب.'));}
    if(function_exists('redisUpdateUser')) redisUpdateUser($uid);
    $bal=$mysqli->query("SELECT user_gold FROM boom_users WHERE user_id='$uid' LIMIT 1");$balance=0;if($bal&&$br=$bal->fetch_assoc())$balance=(int)$br['user_gold'];
    rwReplyJson(array('ok'=>true,'html'=>showNewsPostgen($id),'id'=>$id,'charged'=>$price,'balance'=>$balance));
}

// Edit in place. Existing likes, replies and reactions are intentionally untouched.
if(isset($_POST['rw_action']) && $_POST['rw_action']==='edit_post'){
    $id=rwIntPost('id'); $post=rwGetPost($id);
    if(empty($post) || !rwCanEditPost($post)) rwReplyJson(array('ok'=>false,'error'=>'غير مصرح بتعديل هذا المنشور.'));
    $s=rwSettings();
    if(rwInputCharCount('content')>(int)$s['max_post_chars']) rwReplyJson(array('ok'=>false,'error'=>'تجاوز النص الحد المسموح: '.(int)$s['max_post_chars'].' حرف.'));
    $linkError=rwLinkPolicyError(isset($_POST['content'])?$_POST['content']:'','post'); if($linkError!=='') rwReplyJson(array('ok'=>false,'error'=>$linkError));
    $content=rwTextPost('content',(int)$s['max_post_chars']); $key=isset($_POST['upload_key'])?$_POST['upload_key']:'';
    if(trim(strip_tags($content))==='' && empty($post['news_file']) && $key==='') rwReplyJson(array('ok'=>false,'error'=>'لا يمكن حفظ منشور فارغ.'));
    $now=time();
    if(!$mysqli->query("UPDATE boom_gen SET news_message='$content',edited_at='$now' WHERE id='$id'")) rwReplyJson(array('ok'=>false,'error'=>'فشل تحديث المنشور.'));
    if($key!=='' && !rwStoreUpload($key,'post',$id)) rwReplyJson(array('ok'=>false,'error'=>'تعذر ربط الملف المرفق.'));
    rwReplyJson(array('ok'=>true,'html'=>showNewsPostgen($id),'id'=>$id));
}

// Delete post and its dependent data; only related media files are removed.
if(isset($_POST['rw_action']) && $_POST['rw_action']==='delete_post'){
    $id=rwIntPost('id'); $post=rwGetPost($id);
    if(empty($post) || !rwCanDeletePost($post)) rwReplyJson(array('ok'=>false,'error'=>'غير مصرح بحذف هذا المنشور.'));
    rwDeleteMediaOwner('post',$id); if($post['source_type']!=='chat' && function_exists('removeRelatedFile')) removeRelatedFile($id,'news');
    $mysqli->query("DELETE FROM boom_gen_reply WHERE parent_id='$id'");
    $mysqli->query("DELETE FROM boom_gen_like WHERE like_post='$id'");
    $mysqli->query("DELETE FROM boom_gen WHERE id='$id'");
    updateAllNotify();
    rwReplyJson(array('ok'=>true,'id'=>$id));
}

// Stories: global feed with server-side privacy filtering and atomic gold charge.
if(isset($_POST['rw_action']) && $_POST['rw_action']==='create_story'){
    if(!rwCanStory() || postBlocked()) rwReplyJson(array('ok'=>false,'error'=>'لا تملك صلاحية نشر القصص.'));
    $s=rwSettings(); $uid=(int)$data['user_id'];
    $requestKey=rwRequestKey(isset($_POST['request_key'])?$_POST['request_key']:'');
    if($requestKey==='') rwReplyJson(array('ok'=>false,'error'=>'رمز العملية غير صالح. أعد المحاولة.'));
    $existing=rwFindChargeRequest($requestKey,$uid,'story');
    if(!empty($existing)){
        if(!empty($existing['invalid'])) rwReplyJson(array('ok'=>false,'error'=>'رمز العملية مستخدم لطلب آخر.'));
        $existingId=(int)$existing['content_id']; $existingStory=rwGetStory($existingId);
        if(!empty($existingStory) && (int)$existingStory['story_user']===$uid) rwReplyJson(array('ok'=>true,'duplicate'=>1,'id'=>$existingId,'charged'=>(int)$existing['gold_amount'],'stories'=>rwStoriesStrip()));
        rwReplyJson(array('ok'=>false,'error'=>'تمت معالجة الطلب السابق لكن القصة لم تعد موجودة.'));
    }
    if(rwInputCharCount('content')>(int)$s['max_story_chars']) rwReplyJson(array('ok'=>false,'error'=>'تجاوز نص القصة الحد المسموح: '.(int)$s['max_story_chars'].' حرف.'));
    $rawText=isset($_POST['content'])?$_POST['content']:'';
    $linkError=rwLinkPolicyError($rawText,'story'); if($linkError!=='') rwReplyJson(array('ok'=>false,'error'=>$linkError));
    $text=rwTextPost('content',(int)$s['max_story_chars']); $rawUploadKey=isset($_POST['upload_key'])?(string)$_POST['upload_key']:''; $key=rwRequestKey($rawUploadKey);
    if(trim($rawUploadKey)!=='' && $key==='') rwReplyJson(array('ok'=>false,'error'=>'معرّف الملف المرفق غير صالح؛ أعد رفع الملف.'));
    if(trim(strip_tags($text))!=='' && empty($s['allow_story_text'])) rwReplyJson(array('ok'=>false,'error'=>'النصوص في القصص معطلة من الإدارة.'));
    $pending=array(); $contentKind='text';
    if($key!==''){
        $pending=rwPendingUploadInfo($key,$uid);
        if(empty($pending)) rwReplyJson(array('ok'=>false,'error'=>'انتهت صلاحية الملف المرفق. أعد رفعه.'));
        $contentKind=in_array($pending['file_kind'],array('image','video','audio'),true)?$pending['file_kind']:'';
        if($contentKind==='') rwReplyJson(array('ok'=>false,'error'=>'نوع الملف المرفق غير مدعوم.'));
        $toggle='allow_story_'.($contentKind==='image'?'images':($contentKind==='video'?'videos':'audio'));
        if(empty($s[$toggle])) rwReplyJson(array('ok'=>false,'error'=>'هذا النوع من مرفقات القصص معطل من الإدارة.'));
    }
    if(trim(strip_tags($text))==='' && $key==='') rwReplyJson(array('ok'=>false,'error'=>'أضف نصاً أو وسائط للقصة.'));
    $privacy=isset($_POST['privacy'])?$_POST['privacy']:'all';
    if($privacy==='friends' && empty($s['allow_friends_privacy'])) rwReplyJson(array('ok'=>false,'error'=>'خيار الأصدقاء غير متاح.'));
    if($privacy!=='friends') $privacy='all';
    if($privacy==='all' && empty($s['allow_all_privacy'])) rwReplyJson(array('ok'=>false,'error'=>'النشر للجميع معطل من الإدارة.'));
    $price=rwGoldPrice('story',$contentKind);
    $room=(int)$data['user_roomid']; $roomName=escape(rwRoomSnapshot($room)); $now=time(); $hours=(int)$s['story_hours']; $expires=$now+($hours*3600);
    if(!$mysqli->begin_transaction()) rwReplyJson(array('ok'=>false,'error'=>'تعذر بدء عملية نشر القصة الآمنة.'));
    $insert=$mysqli->query("INSERT INTO boom_gen_story (story_user,story_text,privacy,room_id,room_name,created_at,expires_at) VALUES ('$uid','$text','".escape($privacy)."','$room','$roomName','$now','$expires')");
    if(!$insert){$mysqli->rollback();rwReplyJson(array('ok'=>false,'error'=>'تعذر حفظ القصة.'));}
    $id=(int)$mysqli->insert_id;
    if($key!=='' && !rwStoreUpload($key,'story',$id)){$mysqli->rollback();rwReplyJson(array('ok'=>false,'error'=>'تعذر ربط ملف القصة؛ لم يتم خصم الذهب.'));}
    $chargeError=rwSaveGoldCharge($requestKey,$uid,'story',$id,$contentKind,$price);
    if($chargeError!==''){$mysqli->rollback();rwReplyJson(array('ok'=>false,'error'=>$chargeError));}
    if(!$mysqli->commit()){$mysqli->rollback();rwReplyJson(array('ok'=>false,'error'=>'تعذر تأكيد القصة والخصم. أعد المحاولة بنفس الطلب.'));}
    if(function_exists('redisUpdateUser')) redisUpdateUser($uid);
    $bal=$mysqli->query("SELECT user_gold FROM boom_users WHERE user_id='$uid' LIMIT 1");$balance=0;if($bal&&$br=$bal->fetch_assoc())$balance=(int)$br['user_gold'];
    rwReplyJson(array('ok'=>true,'id'=>$id,'charged'=>$price,'balance'=>$balance,'stories'=>rwStoriesStrip()));
}

if(isset($_POST['rw_action']) && $_POST['rw_action']==='get_stories'){
    $s=rwSettings();
    $owner=rwIntPost('user_id'); $uid=(int)$data['user_id'];
    $isArchive=isset($_POST['archive']) && (int)$_POST['archive']===1;
    $where="story_user='$owner'";
    if($isArchive){
        if($owner!==$uid || empty($s['archive_enabled'])) rwReplyJson(array('ok'=>false,'error'=>'الأرشيف غير متاح.'));
        $where.=" AND archived=1";
    } else {
        $where.=" AND expires_at>'".time()."' AND archived=0";
    }
    $q=$mysqli->query("SELECT * FROM boom_gen_story WHERE $where ORDER BY story_pinned DESC,created_at ASC LIMIT 50");
    $items=array();
    if($q){
        while($story=$q->fetch_assoc()){
            if(!rwCanViewStory($story)) continue;
            $sid=(int)$story['id'];
            $media=rwGetMedia('story',$sid); $mediaData=array();
            foreach($media as $m) $mediaData[]=array('id'=>(int)$m['id'],'kind'=>$m['media_kind'],'mime'=>$m['media_mime'],'url'=>'addons/Room_wall/system/media.php?id='.(int)$m['id']);
            $v=$mysqli->query("SELECT COUNT(*) AS total FROM boom_gen_story_view WHERE story_id='$sid' AND viewer_id<>'".(int)$story['story_user']."'");
            $views=0; if(!empty($s['views_enabled']) && $v && $vr=$v->fetch_assoc()) $views=(int)$vr['total'];
            $rx=$mysqli->query("SELECT reaction FROM boom_gen_story_reaction WHERE story_id='$sid' AND user_id='$uid' LIMIT 1");
            $myReaction=''; if($rx && $rr=$rx->fetch_assoc()) $myReaction=$rr['reaction'];
            $storyText=html_entity_decode((string)$story['story_text'],ENT_QUOTES,'UTF-8');
            $items[]=array('id'=>$sid,'user_id'=>(int)$story['story_user'],'text'=>$storyText,'privacy'=>$story['privacy'],'created_at'=>(int)$story['created_at'],'expires_at'=>(int)$story['expires_at'],'views'=>$views,'my_reaction'=>$myReaction,'pinned'=>(int)$story['story_pinned'],'media'=>$mediaData);
        }
    }
    if(empty($items)) rwReplyJson(array('ok'=>false,'error'=>'لا توجد قصص متاحة لهذا العضو.'));
    $uq=$mysqli->query("SELECT user_name,user_tumb,user_rank FROM boom_users WHERE user_id='$owner' LIMIT 1");
    $user=array('name'=>'','avatar'=>'','rank'=>0,'external_links_allowed'=>0,'youtube_links_allowed'=>0);
    if($uq && $ur=$uq->fetch_assoc()){$user['name']=$ur['user_name'];$user['avatar']=myAvatar($ur['user_tumb']);$user['rank']=(int)$ur['user_rank'];$user['external_links_allowed']=rwCanAuthorUseExternalLinks($user['rank'],'story')?1:0;$user['youtube_links_allowed']=rwCanAuthorUseYoutubeLinks($user['rank'],'story')?1:0;}
    foreach($items as &$rwStoryItem){$rwStoryItem['external_links_allowed']=$user['external_links_allowed'];$rwStoryItem['youtube_links_allowed']=$user['youtube_links_allowed'];}unset($rwStoryItem);

    $replyAllowed=false; $replyMessage=''; $reactionAllowed=false;
    if($owner!==$uid && !$isArchive){
        $recipient=userRelationDetails($owner);
        if(!empty($recipient)){
            $privateOpen=((int)$recipient['user_private']!==0 && (int)$data['user_private']!==0 && !privateBlocked() && canSendPrivate($recipient));
            $reactionAllowed=$privateOpen;
            if(!empty($s['story_replies_enabled']) && rwRankAllowed($s['comment_rank'])){
                if((int)$recipient['user_private']===0) $replyMessage='هذا العضو أغلق الرسائل الخاصة، لا يمكن الرد على قصته.';
                elseif((int)$data['user_private']===0) $replyMessage='إعدادات الخصوصية لحسابك تمنع إرسال الرسائل الخاصة.';
                elseif($privateOpen) $replyAllowed=true;
                else $replyMessage='لا يمكن إرسال رد خاص لهذا العضو بسبب إعدادات الخصوصية أو قيود التواصل.';
            } elseif(empty($s['story_replies_enabled'])) $replyMessage='الرد على القصص معطل من الإدارة.';
            else $replyMessage='رتبتك لا تسمح بالرد على القصص.';
        } else $replyMessage='تعذر العثور على حساب صاحب القصة.';
    }

    // Return the refreshed strip so viewed stories move behind unseen ones and their rings turn gray immediately.
    $strip=rwStoriesStrip();
    rwReplyJson(array('ok'=>true,'user'=>$user,'items'=>$items,'archive'=>$isArchive?1:0,
        'views_enabled'=>(int)$s['views_enabled'],'reactions_enabled'=>(int)$s['reactions_enabled'],
        'show_viewers'=>(int)$s['show_viewers'],'can_pin'=>rwCanPinStory()?1:0,'story_text_seconds'=>(int)$s['story_text_seconds'],'story_image_seconds'=>(int)$s['story_image_seconds'],
        'private_reply_enabled'=>(int)$s['story_replies_enabled'],
        'private_reply_allowed'=>$replyAllowed?1:0,'private_reply_message'=>$replyMessage,'story_react_allowed'=>$reactionAllowed?1:0,'story_strip'=>$strip));
}
if(isset($_POST['rw_action']) && $_POST['rw_action']==='pin_story'){
    if(!rwCanPinStory()) rwReplyJson(array('ok'=>false,'error'=>'رتبتك لا تسمح بتثبيت القصص.'));
    $id=rwIntPost('id'); $pin=rwIntPost('pin')?1:0; $story=rwGetStory($id);
    if(empty($story) || (int)$story['expires_at']<=time() || (int)$story['archived']!==0) rwReplyJson(array('ok'=>false,'error'=>'لا يمكن تثبيت قصة منتهية أو غير موجودة.'));
    if(!$mysqli->query("UPDATE boom_gen_story SET story_pinned='$pin' WHERE id='$id'")) rwReplyJson(array('ok'=>false,'error'=>'تعذر تحديث حالة تثبيت القصة.'));
    rwReplyJson(array('ok'=>true,'pinned'=>$pin,'story_strip'=>rwStoriesStrip()));
}
if(isset($_POST['rw_action']) && $_POST['rw_action']==='story_view'){
    $id=rwIntPost('id'); $story=rwGetStory($id); $uid=(int)$data['user_id'];
    if(empty($story) || (int)$story['expires_at']<=time() || (int)$story['archived']!==0 || !rwCanViewStory($story)) rwReplyJson(array('ok'=>false,'error'=>'القصة غير متاحة للمشاهدة.'));
    $mysqli->query("INSERT IGNORE INTO boom_gen_story_view (story_id,viewer_id,viewed_at) VALUES ('$id','$uid','".time()."')");
    rwReplyJson(array('ok'=>true,'story_strip'=>rwStoriesStrip()));
}
if(isset($_POST['rw_action']) && $_POST['rw_action']==='story_reply'){
    $s=rwSettings();
    if(empty($s['story_replies_enabled']) || !rwRankAllowed($s['comment_rank'])) rwReplyJson(array('ok'=>false,'error'=>'الرد على القصص غير متاح لرتبتك أو أنه معطل من الإدارة.'));
    if(privateBlocked()) rwReplyJson(array('ok'=>false,'error'=>'لا يمكنك إرسال رسائل خاصة حالياً.'));
    $id=rwIntPost('id'); $story=rwGetStory($id); $uid=(int)$data['user_id'];
    if(empty($story) || (int)$story['expires_at']<=time() || (int)$story['archived']!==0 || !rwCanViewStory($story)) rwReplyJson(array('ok'=>false,'error'=>'القصة انتهت أو لا تملك صلاحية مشاهدتها.'));
    $owner=(int)$story['story_user'];
    if($owner===$uid) rwReplyJson(array('ok'=>false,'error'=>'لا يمكنك إرسال رد خاص إلى نفسك.'));
    $recipient=userRelationDetails($owner);
    if(empty($recipient)) rwReplyJson(array('ok'=>false,'error'=>'صاحب القصة غير متاح.'));
    if((int)$recipient['user_private']===0) rwReplyJson(array('ok'=>false,'error'=>'هذا العضو أغلق الرسائل الخاصة، لا يمكن الرد على قصته.'));
    if((int)$data['user_private']===0) rwReplyJson(array('ok'=>false,'error'=>'إعدادات الخصوصية لحسابك تمنع إرسال الرسائل الخاصة.'));
    if(!canSendPrivate($recipient)) rwReplyJson(array('ok'=>false,'error'=>'لا يمكنك مراسلة هذا العضو وفق إعدادات الخصوصية أو قيود التواصل.'));
    $replyRaw=isset($_POST['reply'])?clearBreak($_POST['reply']):'';
    $replyRaw=trimContent($replyRaw);
    if(function_exists('wordFilter')) $replyRaw=wordFilter($replyRaw);
    if(function_exists('mb_substr')) $replyRaw=mb_substr($replyRaw,0,1000,'UTF-8'); else $replyRaw=substr($replyRaw,0,1000);
    if(trim(strip_tags($replyRaw))==='') rwReplyJson(array('ok'=>false,'error'=>'اكتب ردك على القصة أولاً.'));
    $replyHtml=nl2br(htmlspecialchars($replyRaw,ENT_QUOTES,'UTF-8'));

    $preview='<div style="padding:7px;border:1px solid #b9a2e8;border-radius:9px;background:#f7f3ff;color:#35234f;max-width:220px"><small>رد على قصتك</small><br><strong>قصة من اليوميات</strong>';
    $storyMedia=rwGetMedia('story',$id);
    if(!empty($storyMedia)){
        $first=$storyMedia[0]; $previewUrl='addons/Room_wall/system/media.php?id='.(int)$first['id'];
        if($first['media_kind']==='image') $preview.='<br><a href="'.$previewUrl.'"><img src="'.$previewUrl.'" alt="صورة القصة" style="display:block;width:84px;height:84px;object-fit:cover;border-radius:7px;margin-top:5px"></a>';
        elseif($first['media_kind']==='video') $preview.='<br><a href="'.$previewUrl.'">🎬 مشاهدة فيديو القصة</a>';
        elseif($first['media_kind']==='audio') $preview.='<br><a href="'.$previewUrl.'">🎙 تشغيل صوت القصة</a>';
    } elseif(trim(strip_tags($story['story_text']))!==''){
        $snippet=html_entity_decode(strip_tags($story['story_text']),ENT_QUOTES,'UTF-8');
        if(function_exists('mb_substr')) $snippet=mb_substr($snippet,0,100,'UTF-8'); else $snippet=substr($snippet,0,100);
        $preview.='<br><span>'.htmlspecialchars($snippet,ENT_QUOTES,'UTF-8').'</span>';
    }
    $preview.='</div><br><strong>الرد:</strong><br>'.$replyHtml;
    $privateContent=softEscape($preview);
    $privateLog=userPostPrivate($recipient,$privateContent);
    if(empty($privateLog) || empty($privateLog['log_id'])) rwReplyJson(array('ok'=>false,'error'=>'تعذر إرسال الرد إلى الخاص.'));
    $privateId=(int)$privateLog['log_id']; $now=time();
    $mysqli->query("INSERT IGNORE INTO boom_gen_story_dm (story_id,sender_id,target_id,private_id,created_at) VALUES ('$id','$uid','$owner','$privateId','$now')");
    rwReplyJson(array('ok'=>true,'message'=>'تم إرسال ردك إلى الرسائل الخاصة.','private_id'=>$privateId));
}
if(isset($_POST['rw_action']) && $_POST['rw_action']==='story_react'){
    $s=rwSettings(); if(empty($s['reactions_enabled']) || !rwRankAllowed($s['like_rank'])) rwReplyJson(array('ok'=>false,'error'=>'التفاعلات معطلة.'));
    $id=rwIntPost('id'); $story=rwGetStory($id); if(empty($story)||(int)$story['expires_at']<=time()||(int)$story['archived']!==0||!rwCanViewStory($story)) rwReplyJson(array('ok'=>false,'error'=>'القصة غير متاحة.'));
    if((int)$story['story_user']!==(int)$data['user_id']){
        $reactionTarget=userRelationDetails((int)$story['story_user']);
        if(empty($reactionTarget) || (int)$reactionTarget['user_private']===0 || (int)$data['user_private']===0 || privateBlocked() || !canSendPrivate($reactionTarget)) rwReplyJson(array('ok'=>false,'error'=>'لا يمكن التفاعل؛ صاحب القصة أغلق الخاص أو أن إعدادات الخصوصية تمنع التواصل.'));
    }
    $reaction=isset($_POST['reaction'])?$_POST['reaction']:'like'; $valid=array('like','love','laugh','wow','sad'); if(!in_array($reaction,$valid,true))$reaction='like';
    $uid=(int)$data['user_id']; $old=$mysqli->query("SELECT id,reaction FROM boom_gen_story_reaction WHERE story_id='$id' AND user_id='$uid' LIMIT 1");
    if($old && $old->num_rows){$o=$old->fetch_assoc();if($o['reaction']===$reaction)$mysqli->query("DELETE FROM boom_gen_story_reaction WHERE id='".(int)$o['id']."'");else $mysqli->query("UPDATE boom_gen_story_reaction SET reaction='".escape($reaction)."',created_at='".time()."' WHERE id='".(int)$o['id']."'");}
    else $mysqli->query("INSERT IGNORE INTO boom_gen_story_reaction (story_id,user_id,reaction,created_at) VALUES ('$id','$uid','".escape($reaction)."','".time()."')");
    rwReplyJson(array('ok'=>true));
}
if(isset($_POST['rw_action']) && $_POST['rw_action']==='story_views'){
    $id=rwIntPost('id');$story=rwGetStory($id);$s=rwSettings();
    if(empty($story)||(int)$story['story_user']!==(int)$data['user_id']||(empty($s['views_enabled'])||empty($s['show_viewers'])) && empty($s['reactions_enabled'])) rwReplyJson(array('ok'=>false,'error'=>'قوائم المشاهدات والتفاعلات غير متاحة.'));
    $html='';
    if(!empty($s['views_enabled']) && !empty($s['show_viewers'])){
      $q=$mysqli->query("SELECT u.user_name,u.user_tumb,v.viewed_at FROM boom_gen_story_view v INNER JOIN boom_users u ON u.user_id=v.viewer_id WHERE v.story_id='$id' AND v.viewer_id<>'".(int)$story['story_user']."' ORDER BY v.viewed_at DESC LIMIT 200");
      $html.='<h4><i class="fa fa-eye"></i> المشاهدون</h4>'; if($q && $q->num_rows){while($r=$q->fetch_assoc())$html.='<div class="rw-viewer"><img src="'.rwEscHtml(myAvatar($r['user_tumb'])).'" alt=""><div class="rw-viewer-info"><strong>'.rwEscHtml($r['user_name']).'</strong><small>وقت المشاهدة: '.rwEscHtml(displayDate($r['viewed_at'])).'</small></div></div>';}else $html.='<div class="text_small">لا توجد مشاهدات بعد.</div>';
    }
    if(!empty($s['reactions_enabled'])){
      $rxq=$mysqli->query("SELECT u.user_name,u.user_tumb,r.reaction,r.created_at FROM boom_gen_story_reaction r INNER JOIN boom_users u ON u.user_id=r.user_id WHERE r.story_id='$id' ORDER BY r.created_at DESC LIMIT 200");
      $html.='<h4 class="tpad15">المتفاعلون</h4>'; if($rxq && $rxq->num_rows){while($rx=$rxq->fetch_assoc()){$emoji=array('like'=>'👍','love'=>'❤️','laugh'=>'😂','wow'=>'😮','sad'=>'😢');$html.='<div class="rw-viewer"><img src="'.rwEscHtml(myAvatar($rx['user_tumb'])).'" alt=""><div class="rw-viewer-info"><strong>'.rwEscHtml($rx['user_name']).'</strong><small>التفاعل: '.(isset($emoji[$rx['reaction']])?$emoji[$rx['reaction']]:'').'</small></div></div>';}}else $html.='<div class="text_small">لا توجد تفاعلات بعد.</div>';
    }
    rwReplyJson(array('ok'=>true,'html'=>$html));
}
if(isset($_POST['rw_action']) && $_POST['rw_action']==='delete_story'){
    $id=rwIntPost('id');$story=rwGetStory($id);
    if(empty($story)||((int)$story['story_user']!==(int)$data['user_id'] && !canManageAddons())) rwReplyJson(array('ok'=>false,'error'=>'غير مصرح بحذف القصة.'));
    rwDeleteMediaOwner('story',$id);$mysqli->query("DELETE FROM boom_gen_story_view WHERE story_id='$id'");$mysqli->query("DELETE FROM boom_gen_story_reaction WHERE story_id='$id'");$mysqli->query("DELETE FROM boom_gen_story_dm WHERE story_id='$id'");$mysqli->query("DELETE FROM boom_gen_story WHERE id='$id'");
    rwReplyJson(array('ok'=>true));
}

// Existing post interactions.
if(isset($_POST['add_news'],$_POST['post_file'])){
    if(!rwCanPost() || postBlocked()) die('0');
    $legacySettings=rwSettings();
    // The legacy CodyChat uploader has no idempotent charge transaction. Disable this route
    // while gold fees are active so it cannot bypass the new, atomic publish-and-debit path.
    if(!empty($legacySettings['gold_charges_enabled'])) die('0');
    if(rwInputCharCount('add_news')>(int)$legacySettings['max_post_chars']) die('0');
    if(rwYoutubeLinkError(isset($_POST['add_news'])?$_POST['add_news']:'','post')!=='') die('0');
    $content=rwTextPost('add_news',(int)$legacySettings['max_post_chars']);$fileKey=escape($_POST['post_file']);$filePath='';$fileType='';$ok=0;
    if(trim(strip_tags($content))!=='' && empty($legacySettings['allow_post_text'])) die('0');
    if($fileKey!==''){
        $f=$mysqli->query("SELECT * FROM boom_upload WHERE file_key='$fileKey' AND file_user='".(int)$data['user_id']."' AND file_complete=0 LIMIT 1");
        if($f && $f->num_rows){$fr=$f->fetch_assoc();$kind=(strpos($fr['file_type'],'video')!==false?'videos':(strpos($fr['file_type'],'audio')!==false?'audio':'images'));if(!empty(rwSettings()['allow_post_'.$kind])){$filePath='upload/news/'.basename($fr['file_name']);$fileType=substr($fr['file_type'],0,20);$ok=1;}}
    }
    if(trim(strip_tags($content))==='' && !$ok) die('0');
    $room=(int)$data['user_roomid'];$roomName=escape(rwRoomSnapshot($room));$now=time();$expiryDays=(int)rwSettings()['post_expiry_days'];$postExpires=$expiryDays>0?($now+($expiryDays*86400)):0;
    $mysqli->query("INSERT INTO boom_gen (news_poster,news_message,news_file,news_file_type,news_date,room_id,room_name,source_type,expires_at) VALUES ('".(int)$data['user_id']."','$content','".escape($filePath)."','".escape($fileType)."','$now','$room','$roomName','manual','$postExpires')");
    $id=(int)$mysqli->insert_id;if($ok)$mysqli->query("UPDATE boom_upload SET file_complete=1,relative_post='$id' WHERE file_key='$fileKey' AND file_user='".(int)$data['user_id']."'");
    echo showNewsPostgen($id);exit;
}
if(isset($_POST['more_news'])){
    $genId=(int)$_POST['more_news'];$html='';
    $cursor=$mysqli->query("SELECT static,news_date,id FROM boom_gen WHERE id='$genId' LIMIT 1");
    if(!$cursor || !$cursor->num_rows){echo '0';exit;}
    $last=$cursor->fetch_assoc();$pin=(int)$last['static'];$date=(int)$last['news_date'];
    $q=$mysqli->query("SELECT g.*,u.*, (SELECT COUNT(*) FROM boom_gen_reply WHERE parent_id=g.id) AS reply_count,
      (SELECT like_type FROM boom_gen_like WHERE uid='".(int)$data['user_id']."' AND like_post=g.id LIMIT 1) AS liked
      FROM boom_gen g INNER JOIN boom_users u ON u.user_id=g.news_poster
      WHERE (g.expires_at=0 OR g.expires_at>'".time()."') AND (g.static<'$pin' OR (g.static='$pin' AND (g.news_date<'$date' OR (g.news_date='$date' AND g.id<'$genId'))))
      ORDER BY g.static DESC,g.news_date DESC,g.id DESC LIMIT 10");
    if($q)while($r=$q->fetch_assoc())$html.=genTemplate('element/gen',$r);
    echo $html!==''?$html:'0';exit;
}
if(isset($_POST['like_news'],$_POST['like_type'])){
    if(!canLikeAdvNews() || !boomAllow(1)) rwReplyJson(array('code'=>0));
    $id=rwIntPost('like_news');$type=rwIntPost('like_type');if($type<1||$type>4)rwReplyJson(array('code'=>0));
    $p=rwGetPost($id);if(empty($p))rwReplyJson(array('code'=>0));
    $uid=(int)$data['user_id'];$old=$mysqli->query("SELECT id,like_type FROM boom_gen_like WHERE like_post='$id' AND uid='$uid' LIMIT 1");
    if($old && $old->num_rows){$o=$old->fetch_assoc();if((int)$o['like_type']===$type)$mysqli->query("DELETE FROM boom_gen_like WHERE id='".(int)$o['id']."'");else $mysqli->query("UPDATE boom_gen_like SET like_type='$type',liked_uid='".(int)$p['news_poster']."',like_date='".time()."' WHERE id='".(int)$o['id']."'");}
    else $mysqli->query("INSERT INTO boom_gen_like (uid,liked_uid,like_type,like_post,like_date) VALUES ('$uid','".(int)$p['news_poster']."','$type','$id','".time()."')");
    $liked=0;$check=$mysqli->query("SELECT like_type FROM boom_gen_like WHERE uid='$uid' AND like_post='$id' LIMIT 1");if($check&&$v=$check->fetch_assoc())$liked=(int)$v['like_type'];
    rwReplyJson(array('code'=>1,'data'=>getLikesgen($id,$liked,'gen')));
}
if(isset($_POST['content'],$_POST['reply_news'])){
    if(postBlocked()||!canReplyAdvNews())rwReplyJson(array('code'=>0));
    $id=rwIntPost('reply_news');$post=rwGetPost($id);if(empty($post))rwReplyJson(array('code'=>0));
    $content=rwTextPost('content',1000);if(trim(strip_tags($content))==='')rwReplyJson(array('code'=>0));
    $uid=(int)$data['user_id'];$now=time();$mysqli->query("INSERT INTO boom_gen_reply (parent_id,reply_date,reply_user,reply_content,reply_uid) VALUES ('$id','$now','$uid','$content','".(int)$post['news_poster']."')");
    $rid=(int)$mysqli->insert_id;$q=$mysqli->query("SELECT r.*,u.* FROM boom_gen_reply r INNER JOIN boom_users u ON u.user_id=r.reply_user WHERE r.reply_id='$rid' LIMIT 1");
    $html='';if($q && $r=$q->fetch_assoc())$html=genTemplate('element/gen_reply',$r);
    rwReplyJson(array('code'=>1,'data'=>$html,'total'=>newsReplyCountgen($id)));
}
if(isset($_POST['load_news_comment'],$_POST['id'])){
    $id=rwIntPost('id');if(empty(rwGetPost($id)))rwReplyJson(array('code'=>0,'reply'=>'0','more'=>0));
    $html=rwLoadReplies($id,0,10);$c=newsReplyCountgen($id);$more=$c>10?'<a onclick="moreSxComment(this,'.$id.')" class="theme_color text_small more_comment">عرض المزيد من التعليقات</a>':0;
    rwReplyJson(array('code'=>1,'reply'=>$html?:'0','more'=>$more));
}
if(isset($_POST['load_news_reply'],$_POST['current'],$_POST['id'])){
    $id=rwIntPost('id');echo rwLoadReplies($id,rwIntPost('current'),20)?:'0';exit;
}
if(isset($_POST['delete_news_reply'])){
    $id=rwIntPost('delete_news_reply');$q=$mysqli->query("SELECT * FROM boom_gen_reply WHERE reply_id='$id' LIMIT 1");
    if(!$q||!$q->num_rows)rwReplyJson(array('code'=>0));$r=$q->fetch_assoc();
    $allowed=((int)$r['reply_user']===(int)$data['user_id']) || boomAllow(rwSettings()['delete_comments_rank']) || canManageAddons();
    if(!$allowed)rwReplyJson(array('code'=>0));
    $mysqli->query("DELETE FROM boom_gen_reply WHERE reply_id='$id'");rwReplyJson(array('code'=>1,'reply'=>$id,'gen'=>(int)$r['parent_id'],'total'=>newsReplyCountgen($r['parent_id'])));
}
if(isset($_POST['remove_news'])){
    $id=rwIntPost('remove_news');$p=rwGetPost($id);if(empty($p)||!rwCanDeletePost($p))die('1');
    rwDeleteMediaOwner('post',$id);if($p['source_type']!=='chat' && function_exists('removeRelatedFile')) removeRelatedFile($id,'news');$mysqli->query("DELETE FROM boom_gen_reply WHERE parent_id='$id'");$mysqli->query("DELETE FROM boom_gen_like WHERE like_post='$id'");$mysqli->query("DELETE FROM boom_gen WHERE id='$id'");updateAllNotify();echo 'boom_gen'.$id;exit;
}
if(isset($_POST['mstatic'],$_POST['pin'])){
    if(!canpinpost())die('0');$id=rwIntPost('mstatic');$pin=rwIntPost('pin')?1:0;$p=rwGetPost($id);if(empty($p))die('0');
    $mysqli->query("UPDATE boom_gen SET static='$pin' WHERE id='$id'");echo '1';exit;
}


die();
?>
