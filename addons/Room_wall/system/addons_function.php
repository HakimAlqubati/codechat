<?php
/**
 * Room_wall v2.5 - CodyChat 10 helpers.
 * Kept namespaced with rw* to avoid collisions with CodyChat or other addons.
 */
function rwSettings(){
    global $mysqli;
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    $defaults = array(
        'posts_enabled'=>1, 'stories_enabled'=>1, 'allow_post_text'=>1, 'allow_post_images'=>1, 'allow_post_videos'=>1, 'allow_post_audio'=>1, 'allow_story_text'=>1, 'allow_story_images'=>1, 'allow_story_videos'=>1, 'allow_story_audio'=>1, 'comments_enabled'=>1,
        'likes_enabled'=>1, 'reactions_enabled'=>1, 'views_enabled'=>1,
        'archive_enabled'=>1, 'allow_friends_privacy'=>1, 'allow_all_privacy'=>1, 'story_replies_enabled'=>1,
        'post_rank'=>1, 'story_rank'=>1, 'view_story_rank'=>1, 'comment_rank'=>1, 'like_rank'=>1,
        'edit_own_rank'=>1, 'edit_others_rank'=>100, 'delete_own_rank'=>1,
        'delete_others_rank'=>100, 'delete_comments_rank'=>100,
        'post_link_rank'=>1, 'story_link_rank'=>1,
        'post_external_link_rank'=>100, 'story_external_link_rank'=>100,
        'max_file_mb'=>1, 'max_image_mb'=>1, 'max_video_mb'=>1, 'max_audio_mb'=>10,
        'max_post_chars'=>100, 'max_story_chars'=>100,
        'story_hours'=>24, 'post_expiry_days'=>0, 'max_posts_hour'=>10, 'max_video_seconds'=>60,
        'max_audio_seconds'=>30, 'story_text_seconds'=>10, 'story_image_seconds'=>5, 'show_viewers'=>1,
        'gold_charges_enabled'=>0, 'post_text_gold'=>10, 'post_image_gold'=>50, 'post_video_gold'=>100, 'post_audio_gold'=>20,
        'story_text_gold'=>10, 'story_image_gold'=>50, 'story_video_gold'=>100, 'story_audio_gold'=>20,
        'story_pin_rank'=>100, 'story_circle_size'=>56
    );
    $result = $mysqli->query("SELECT * FROM boom_gen_settings WHERE id = 1 LIMIT 1");
    if ($result && $result->num_rows) $cfg = array_merge($defaults, $result->fetch_assoc());
    else $cfg = $defaults;
    return $cfg;
}
function rwEnsureSchema(){
    global $mysqli;
    $needsInstall=false;
    $tables=array('boom_gen_settings','boom_gen','boom_gen_story','boom_gen_story_view','boom_gen_story_reaction','boom_gen_media','boom_gen_upload','boom_gen_import_log','boom_gen_import_state','boom_gen_story_dm','boom_gen_charge');
    foreach($tables as $table){
        $q=$mysqli->query("SHOW TABLES LIKE '".$mysqli->real_escape_string($table)."'");
        if(!$q || !$q->num_rows){$needsInstall=true;break;}
    }
    if(!$needsInstall){
        $checks=array(
            array('boom_gen_settings','view_story_rank'),
            array('boom_gen_settings','max_image_mb'),
            array('boom_gen_settings','story_replies_enabled'),
            array('boom_gen_settings','post_expiry_days'),
            array('boom_gen_settings','max_post_chars'),
            array('boom_gen_settings','max_story_chars'),
            array('boom_gen_settings','story_text_seconds'),
            array('boom_gen_settings','story_image_seconds'),
            array('boom_gen_settings','post_link_rank'),
            array('boom_gen_settings','story_link_rank'),
            array('boom_gen_settings','post_external_link_rank'),
            array('boom_gen_settings','story_external_link_rank'),
            array('boom_gen_settings','rw_v25_link_policy_applied'),
            array('boom_gen_settings','rw_v23_defaults_applied'),
            array('boom_gen_settings','gold_charges_enabled'),
            array('boom_gen_settings','post_text_gold'),
            array('boom_gen_settings','post_audio_gold'),
            array('boom_gen_settings','post_image_gold'),
            array('boom_gen_settings','post_video_gold'),
            array('boom_gen_settings','story_text_gold'),
            array('boom_gen_settings','story_audio_gold'),
            array('boom_gen_settings','story_image_gold'),
            array('boom_gen_settings','story_video_gold'),
            array('boom_gen_settings','story_pin_rank'),
            array('boom_gen_settings','story_circle_size'),
            array('boom_gen_story','story_pinned'),
            array('boom_gen_charge','request_key'),
            array('boom_gen','expires_at')
        );
        foreach($checks as $check){
            $q=$mysqli->query("SHOW COLUMNS FROM `".$check[0]."` LIKE '".$mysqli->real_escape_string($check[1])."'");
            if(!$q || !$q->num_rows){$needsInstall=true;break;}
        }
    }
    if($needsInstall) require_once(__DIR__.'/install.php');
}
function rwRankAllowed($rank){
    return boomAllow((int)$rank);
}
function rwAddonAllowed(){
    global $addons;
    return !empty($addons) && boomAllow((int)$addons['addons_access']);
}
function rwMediaDir(){
    $parent = dirname(BOOM_PATH);
    $docroot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $realParent = realpath($parent);
    if ($docroot && $realParent && strpos($realParent, $docroot) === 0) {
        return rtrim(sys_get_temp_dir(), '/\\\\') . '/roomwall_' . substr(sha1(BOOM_PATH), 0, 12);
    }
    return $parent . '/.roomwall_' . substr(sha1(BOOM_PATH), 0, 12);
}
function rwCanPost(){
    $s = rwSettings();
    return rwAddonAllowed() && !empty($s['posts_enabled']) && rwRankAllowed($s['post_rank']) && canPostAdvNews();
}
function rwCanStory(){
    $s = rwSettings();
    return rwAddonAllowed() && !empty($s['stories_enabled']) && rwRankAllowed($s['story_rank']);
}
function rwCanEditPost($post){
    global $data;
    $s = rwSettings();
    if ((int)$post['news_poster'] === (int)$data['user_id']) return rwRankAllowed($s['edit_own_rank']);
    return canManageAddons() || rwRankAllowed($s['edit_others_rank']);
}
function rwCanDeletePost($post){
    global $data;
    $s = rwSettings();
    if ((int)$post['news_poster'] === (int)$data['user_id']) return rwRankAllowed($s['delete_own_rank']);
    return canManageAddons() || rwRankAllowed($s['delete_others_rank']);
}
function rwCanViewStory($story){
    global $data;
    $s = rwSettings();
    if (!rwRankAllowed($s['view_story_rank'])) return false;
    if ((int)$story['story_user'] === (int)$data['user_id']) return true;
    if ($story['privacy'] === 'all' && !empty($s['allow_all_privacy'])) return true;
    if ($story['privacy'] === 'friends' && !empty($s['allow_friends_privacy']) && function_exists('userIsFriend') && userIsFriend((int)$story['story_user'])) return true;
    return false;
}
function rwIsAdmin(){
    return canManageAddons();
}
function rwCanPinStory(){
    $s=rwSettings();
    return canManageAddons() || rwRankAllowed((int)$s['story_pin_rank']);
}
function rwEscHtml($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
function rwNormalizeHttpUrl($url){
    $url=trim((string)$url);
    if($url==='') return '';
    if(!preg_match('~^[a-z][a-z0-9+.-]*://~i',$url)) $url='https://'.ltrim($url,'/');
    $parts=@parse_url($url);
    if(!is_array($parts) || empty($parts['host'])) return '';
    $scheme=isset($parts['scheme'])?strtolower($parts['scheme']):'';
    if(!in_array($scheme,array('http','https'),true)) return '';
    if(isset($parts['user']) || isset($parts['pass'])) return '';
    $host=strtolower(rtrim($parts['host'],'.'));
    if($host==='' || preg_match('/[\s\\\\\/]/',$host)) return '';
    return $url;
}
function rwIsHttpExternalUrl($url){
    return rwNormalizeHttpUrl($url)!=='';
}
function rwIsYoutubeUrl($url){
    $normalized=rwNormalizeHttpUrl($url);
    if($normalized==='') return false;
    $parts=@parse_url($normalized);
    if(!is_array($parts) || empty($parts['host'])) return false;
    $host=strtolower(rtrim($parts['host'],'.'));
    $allowed=array('youtube.com','www.youtube.com','m.youtube.com','music.youtube.com','youtu.be','www.youtu.be');
    return in_array($host,$allowed,true);
}
function rwRankValueAllowed($userRank,$requiredRank){
    return (int)$userRank >= max(1,(int)$requiredRank);
}
function rwCanPublishYoutubeLink($type){
    global $data;
    $s=rwSettings();
    $key=($type==='story')?'story_link_rank':'post_link_rank';
    return rwRankValueAllowed(isset($data['user_rank'])?$data['user_rank']:0,$s[$key]);
}
function rwCanPublishExternalLink($type){
    global $data;
    $s=rwSettings();
    $key=($type==='story')?'story_external_link_rank':'post_external_link_rank';
    return rwRankValueAllowed(isset($data['user_rank'])?$data['user_rank']:0,$s[$key]);
}
function rwCanAuthorUseExternalLinks($authorRank,$type){
    $s=rwSettings();
    $key=($type==='story')?'story_external_link_rank':'post_external_link_rank';
    return rwRankValueAllowed($authorRank,$s[$key]);
}
function rwCanAuthorUseYoutubeLinks($authorRank,$type){
    $s=rwSettings();
    $key=($type==='story')?'story_link_rank':'post_link_rank';
    return rwRankValueAllowed($authorRank,$s[$key]);
}
function rwTextLinks($text){
    $text=html_entity_decode((string)$text,ENT_QUOTES,'UTF-8');
    $pattern='~(?<![\\pL\\pN@])((?:(?:[a-z][a-z0-9+.-]*):[^\\s<>"\x27]+)|(?:(?:www\\.)?(?:(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\\.)+[a-z\\pL]{2,}|(?:[0-9]{1,3}\\.){3}[0-9]{1,3})(?::[0-9]{1,5})?(?:/[^\\s<>"\x27]*)?(?:\\?[^\\s<>"\x27]*)?(?:#[^\\s<>"\x27]*)?))~iu';
    if(!preg_match_all($pattern,$text,$matches)) return array();
    $links=array();
    foreach($matches[1] as $candidate){$candidate=rtrim($candidate,".,!?;:)]}،؛");if($candidate!=='')$links[]=$candidate;}
    return array_values(array_unique($links));
}
function rwLinkPolicyError($text,$type){
    foreach(rwTextLinks($text) as $link){
        if(rwIsYoutubeUrl($link)){
            if(!rwCanPublishYoutubeLink($type)) return 'رتبتك لا تسمح بإضافة روابط YouTube في هذا القسم.';
            continue;
        }
        if(!rwIsHttpExternalUrl($link)) return 'نوع الرابط غير مدعوم. استخدم رابط HTTP أو HTTPS صالحاً.';
        if(!rwCanPublishExternalLink($type)) return 'الروابط الخارجية متاحة للرتب التي تحددها الإدارة فقط؛ يمكنك استخدام روابط YouTube.';
    }
    return '';
}
function rwMaskLinksForAuthor($text,$authorRank,$type,&$masked=false){
    $raw=(string)$text;
    $pattern='~(?<![\\pL\\pN@])((?:(?:[a-z][a-z0-9+.-]*):[^\\s<>"\x27]+)|(?:(?:www\\.)?(?:(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\\.)+[a-z\\pL]{2,}|(?:[0-9]{1,3}\\.){3}[0-9]{1,3})(?::[0-9]{1,5})?(?:/[^\\s<>"\x27]*)?(?:\\?[^\\s<>"\x27]*)?(?:#[^\\s<>"\x27]*)?))~iu';
    $result=preg_replace_callback($pattern,function($m) use ($authorRank,$type){
        $url=rtrim($m[1],".,!?;:)]}،؛");
        $suffix=substr($m[1],strlen($url));
        if(rwIsYoutubeUrl($url)){
            if(!rwCanAuthorUseYoutubeLinks($authorRank,$type)) return 'رابط YouTube غير متاح لهذه الرتبة'.$suffix;
            return rwNormalizeHttpUrl($url).$suffix;
        }
        if(rwIsHttpExternalUrl($url) && rwCanAuthorUseExternalLinks($authorRank,$type)) return rwNormalizeHttpUrl($url).$suffix;
        return 'رابط خارجي محظور حسب صلاحيات الإضافة'.$suffix;
    },$raw);
    $masked=($result!==$raw);
    return $result;
}
function rwRenderPostText($user,$text,&$masked=false){
    $authorRank=is_array($user)&&isset($user['user_rank'])?(int)$user['user_rank']:0;
    $safe=rwMaskLinksForAuthor($text,$authorRank,'post',$masked);
    $html=boomPostIt($user,$safe);
    // CodyChat's boomPostIt skips linkification for members without allow_direct.
    // Room_wall's separate link policy must still render permitted URLs as links.
    if(function_exists('userCanDirect') && !userCanDirect($user) && function_exists('linkingLink')){
        $html=linkingLink($html);
    }
    return $html;
}
function rwPendingUploadInfo($key,$uid){
    global $mysqli;
    $key=preg_replace('/[^a-f0-9]/','',(string)$key);
    if(strlen($key)!==32) return array();
    $uid=(int)$uid;$now=time();
    $q=$mysqli->query("SELECT id,file_kind,file_user,file_path,file_mime,file_size FROM boom_gen_upload WHERE upload_key='$key' AND file_user='$uid' AND used_at=0 AND created_at>='".($now-7200)."' LIMIT 1");
    return ($q&&$q->num_rows)?$q->fetch_assoc():array();
}
function rwGoldPrice($ownerType,$contentKind){
    $s=rwSettings();
    if(empty($s['gold_charges_enabled'])) return 0;
    $ownerType=($ownerType==='story')?'story':'post';
    $allowed=array('text','image','video','audio');
    if(!in_array($contentKind,$allowed,true)) $contentKind='text';
    $key=$ownerType.'_'.$contentKind.'_gold';
    return max(0,(int)($s[$key]??0));
}
function rwGoldPriceLine($ownerType){
    $s=rwSettings();$ownerType=($ownerType==='story')?'story':'post';
    $labels=array('text'=>'النص','audio'=>'الصوت','image'=>'الصورة','video'=>'الفيديو');
    $html='<div class="rw-gold-prices"><span>'.(!empty($s['gold_charges_enabled'])?'تكلفة النشر':'التسعيرة المحددة (الخصم معطل)').':</span>';
    foreach($labels as $kind=>$label){
        $field=$ownerType.'_'.$kind.'_gold';
        $price=max(0,(int)($s[$field]??0));
        $html.='<span>'.rwEscHtml($label).' '.number_format($price).' ذهب</span>';
    }
    $html.='<small>عند إرفاق وسيط تُحسب تكلفة نوع الوسيط فقط، والنص المرافق مشمول.</small></div>';
    return $html;
}
function rwRequestKey($value){
    $value=strtolower(trim((string)$value));
    return preg_match('/^[a-f0-9]{32}$/',$value) ? $value : '';
}
function rwFindChargeRequest($key,$uid,$ownerType){
    global $mysqli;
    $key=escape($key);$uid=(int)$uid;$ownerType=($ownerType==='story')?'story':'post';
    $q=$mysqli->query("SELECT content_id,content_type,user_id,gold_amount FROM boom_gen_charge WHERE request_key='$key' LIMIT 1");
    if(!$q||!$q->num_rows) return array();
    $r=$q->fetch_assoc();
    if((int)$r['user_id']!==$uid || $r['content_type']!==$ownerType) return array('invalid'=>1);
    return $r;
}
function rwSaveGoldCharge($key,$uid,$ownerType,$contentId,$contentKind,$amount){
    global $mysqli;
    $key=escape($key);$uid=(int)$uid;$contentId=(int)$contentId;$amount=max(0,(int)$amount);
    $ownerType=($ownerType==='story')?'story':'post';
    $contentKind=in_array($contentKind,array('text','image','video','audio'),true)?$contentKind:'text';
    if($amount>0){
        global $setting;
        if(empty($setting['use_wallet'])) return 'محفظة الذهب معطلة في إعدادات CodyChat؛ لم يتم النشر أو الخصم.';
        $ok=$mysqli->query("UPDATE boom_users SET user_gold=user_gold-'$amount', user_sgold=user_sgold+'$amount' WHERE user_id='$uid' AND user_gold>='$amount'");
        if(!$ok || $mysqli->affected_rows!==1) return 'رصيد الذهب غير كافٍ. تكلفة هذه الخدمة '.number_format($amount).' ذهب.';
    }
    $ok=$mysqli->query("INSERT INTO boom_gen_charge (request_key,user_id,content_type,content_id,content_kind,gold_amount,created_at) VALUES ('$key','$uid','$ownerType','$contentId','$contentKind','$amount','".time()."')");
    if(!$ok) return 'تعذر تسجيل عملية النشر؛ أعد المحاولة بنفس الطلب لتجنب الخصم المكرر.';
    return '';
}
function rwGetMedia($ownerType, $ownerId){
    global $mysqli;
    $ownerId = (int)$ownerId;
    $ownerType = ($ownerType === 'story') ? 'story' : 'post';
    $out = array();
    $q = $mysqli->query("SELECT id, media_kind, media_mime, media_size FROM boom_gen_media WHERE owner_type = '$ownerType' AND owner_id = '$ownerId' ORDER BY id ASC");
    if ($q) while ($r = $q->fetch_assoc()) $out[] = $r;
    return $out;
}
function rwRenderMedia($ownerType, $ownerId){
    $html = '';
    foreach (rwGetMedia($ownerType, $ownerId) as $m){
        $url = 'addons/Room_wall/system/media.php?id='.(int)$m['id'];
        if ($m['media_kind'] === 'image') $html .= '<div class="rw-media"><img loading="lazy" src="'.$url.'" alt="" /></div>';
        elseif ($m['media_kind'] === 'video') $html .= '<div class="rw-media"><video controls playsinline preload="metadata"><source src="'.$url.'" type="'.rwEscHtml($m['media_mime']).'"></video></div>';
        elseif ($m['media_kind'] === 'audio') $html .= '<div class="rw-audio"><audio controls preload="metadata"><source src="'.$url.'" type="'.rwEscHtml($m['media_mime']).'"></audio></div>';
    }
    return $html;
}
function rwStoriesStrip(){
    global $mysqli, $data;
    $s = rwSettings();
    if (empty($s['stories_enabled']) || !rwRankAllowed($s['view_story_rank'])) return '';
    $uid = (int)$data['user_id'];
    $now = time();

    // Always show the current member's circle, even before their first story.
    $selfCount = 0; $selfUnseen = 0; $selfPinned = 0;
    $selfQ = $mysqli->query("SELECT COUNT(st.id) AS story_count,
        SUM(CASE WHEN sv.id IS NULL THEN 1 ELSE 0 END) AS unseen_count, MAX(st.story_pinned) AS pinned_count
        FROM boom_gen_story st
        LEFT JOIN boom_gen_story_view sv ON sv.story_id = st.id AND sv.viewer_id = '$uid'
        WHERE st.story_user = '$uid' AND st.expires_at > '$now' AND st.archived = 0");
    if ($selfQ && $self = $selfQ->fetch_assoc()) {
        $selfCount = (int)$self['story_count'];
        $selfUnseen = (int)$self['unseen_count'];
        $selfPinned = (int)$self['pinned_count'];
    }
    $selfClass = ($selfUnseen > 0) ? ' rw-unseen' : ' rw-seen';
    $selfAvatar = rwEscHtml(myAvatar($data['user_tumb']));
    $selfName = rwEscHtml($data['user_name']);
    $html = '<div id="rw_story_strip_container"><div class="rw-stories-wrap"><div class="rw-stories-heading"><strong>القصص</strong><span>قصتك وقصص الأعضاء</span></div><div class="rw-stories-strip">';
    $html .= '<div class="rw-story-user-wrap rw-own-story"><button type="button" class="rw-story-user'.$selfClass.($selfPinned?' rw-has-pinned':'').'" data-user="'.$uid.'" data-has-stories="'.($selfCount>0?1:0).'" onclick="rwOpenOrComposeOwnStories(this)"><span class="rw-story-ring"><img loading="lazy" src="'.$selfAvatar.'" alt=""></span><span class="rw-story-name">'.$selfName.'</span></button>';
    if (rwCanStory()) $html .= '<button type="button" class="rw-story-add" onclick="rwOpenStoryComposer()" aria-label="إضافة قصة">+</button>';
    $html .= '</div>';

    $privacyParts = array();
    if (!empty($s['allow_all_privacy'])) $privacyParts[] = "st.privacy = 'all'";
    if (!empty($s['allow_friends_privacy'])) $privacyParts[] = "(st.privacy = 'friends' AND EXISTS (SELECT 1 FROM boom_friends f WHERE f.hunter = '$uid' AND f.target = st.story_user AND f.fstatus = 3))";
    $privacySql = $privacyParts ? implode(' OR ', $privacyParts) : '1=0';
    $q = $mysqli->query("SELECT u.user_id,u.user_name,u.user_tumb,
        MAX(st.created_at) AS latest_story, MAX(st.story_pinned) AS has_pinned,
        SUM(CASE WHEN sv.id IS NULL THEN 1 ELSE 0 END) AS unseen_count,
        COUNT(st.id) AS story_count
        FROM boom_gen_story st
        INNER JOIN boom_users u ON u.user_id = st.story_user
        LEFT JOIN boom_gen_story_view sv ON sv.story_id = st.id AND sv.viewer_id = '$uid'
        WHERE st.story_user <> '$uid' AND st.expires_at > '$now' AND st.archived = 0
          AND ($privacySql)
        GROUP BY u.user_id,u.user_name,u.user_tumb
        ORDER BY has_pinned DESC,(SUM(CASE WHEN sv.id IS NULL THEN 1 ELSE 0 END) > 0) DESC, latest_story DESC
        LIMIT 80");
    if ($q && $q->num_rows){
        while ($r = $q->fetch_assoc()){
            $seen = ((int)$r['unseen_count'] > 0) ? ' rw-unseen' : ' rw-seen';
            $html .= '<button type="button" class="rw-story-user'.$seen.(!empty($r['has_pinned'])?' rw-has-pinned':'').'" data-user="'.(int)$r['user_id'].'" data-has-stories="1" onclick="rwOpenStories('.(int)$r['user_id'].')"><span class="rw-story-ring"><img loading="lazy" src="'.rwEscHtml(myAvatar($r['user_tumb'])).'" alt=""></span><span class="rw-story-name">'.rwEscHtml($r['user_name']).'</span></button>';
        }
    } else {
        $html .= '<div class="rw-stories-empty">لا توجد قصص أخرى متاحة حالياً</div>';
    }
    $html .= '</div></div><div class="rw-story-divider"></div></div>';
    return $html;
}

/** Expire a bounded batch of posts and remove only their dependent addon data/files. */
function rwCleanExpiredPosts(){
    global $mysqli;
    static $ran = false;
    if ($ran) return;
    $ran = true;
    $now = time();
    $q = $mysqli->query("SELECT id,source_type FROM boom_gen WHERE expires_at > 0 AND expires_at <= '$now' ORDER BY expires_at ASC LIMIT 100");
    if (!$q) return;
    while ($r = $q->fetch_assoc()){
        $id = (int)$r['id'];
        rwDeleteMediaOwner('post', $id);
        // Imported chat attachments point to the original upload/chat file; never delete source assets.
        if ($r['source_type'] !== 'chat' && function_exists('removeRelatedFile')) removeRelatedFile($id, 'news');
        $mysqli->query("DELETE FROM boom_gen_reply WHERE parent_id = '$id'");
        $mysqli->query("DELETE FROM boom_gen_like WHERE like_post = '$id'");
        $mysqli->query("DELETE FROM boom_gen WHERE id = '$id'");
    }
}
function rwRoomSnapshot($roomId){
    global $mysqli;
    $roomId = (int)$roomId;
    $q = $mysqli->query("SELECT room_name FROM boom_rooms WHERE room_id = '$roomId' LIMIT 1");
    if ($q && $q->num_rows) { $r=$q->fetch_assoc(); return $r['room_name']; }
    return '';
}
function rwDeleteMediaOwner($ownerType, $ownerId){
    global $mysqli;
    $ownerType = ($ownerType === 'story') ? 'story' : 'post';
    $ownerId = (int)$ownerId;
    $q = $mysqli->query("SELECT id,media_path FROM boom_gen_media WHERE owner_type='$ownerType' AND owner_id='$ownerId'");
    if ($q){
        while($r=$q->fetch_assoc()){
            $path = rwMediaDir().'/'.basename($r['media_path']);
            if (is_file($path)) @unlink($path);
        }
    }
    $mysqli->query("DELETE FROM boom_gen_media WHERE owner_type='$ownerType' AND owner_id='$ownerId'");
}
function newsReplyCountgen($id){
    global $mysqli;
    $id=(int)$id;
    $q=$mysqli->query("SELECT COUNT(*) AS total FROM boom_gen_reply WHERE parent_id='$id'");
    if($q && $r=$q->fetch_assoc()) return (int)$r['total'];
    return 0;
}
function showNewsPostgen($id){
    global $mysqli, $data;
    $id=(int)$id;
    $out='';
    $q=$mysqli->query("SELECT g.*,u.*, (SELECT COUNT(*) FROM boom_gen_reply WHERE parent_id=g.id) AS reply_count,
        (SELECT like_type FROM boom_gen_like WHERE uid='".(int)$data['user_id']."' AND like_post=g.id LIMIT 1) AS liked
        FROM boom_gen g INNER JOIN boom_users u ON u.user_id=g.news_poster WHERE g.id='$id' LIMIT 1");
    if($q && $q->num_rows) $out.=genTemplate('element/gen',$q->fetch_assoc());
    return $out;
}
function getLikesgen($post, $liked, $type){
    global $mysqli;
    if($type !== 'gen') return '';
    $post=(int)$post;
    $r=array('like_post'=>$post,'like_count'=>0,'dislike_count'=>0,'love_count'=>0,'fun_count'=>0,'liked'=>'','disliked'=>'','loved'=>'','funned'=>'');
    if((int)$liked===1)$r['liked']=' liked'; elseif((int)$liked===2)$r['disliked']=' liked'; elseif((int)$liked===3)$r['loved']=' liked'; elseif((int)$liked===4)$r['funned']=' liked';
    $q=$mysqli->query("SELECT like_type,COUNT(*) AS total FROM boom_gen_like WHERE like_post='$post' GROUP BY like_type");
    if($q) while($x=$q->fetch_assoc()){
        if((int)$x['like_type']===1)$r['like_count']=(int)$x['total'];
        if((int)$x['like_type']===2)$r['dislike_count']=(int)$x['total'];
        if((int)$x['like_type']===3)$r['love_count']=(int)$x['total'];
        if((int)$x['like_type']===4)$r['fun_count']=(int)$x['total'];
    }
    return genTemplate('element/likes_gen',$r);
}
function genTemplate($getpage, $boom = ''){
    global $data, $lang, $mysqli, $cody;
    $page=BOOM_PATH.'/addons/Room_wall/system/'.$getpage.'.php';
    if(!is_file($page)) return '';
    ob_start(); require($page); return ob_get_clean();
}
function canPostAdvNews(){ global $addons; return !empty($addons) && boomAllow($addons['custom1']); }
function canReplyAdvNews(){ global $addons; $s=rwSettings(); return !empty($s['comments_enabled']) && boomAllow($addons['custom2']) && boomAllow($s['comment_rank']); }
function canpinpost(){ global $addons; return !empty($addons) && boomAllow($addons['custom7']); }
function canDelPostAdvNews($post=array()){
    global $addons, $data;
    if(!empty($post) && isset($post['news_poster']) && (int)$post['news_poster']===(int)$data['user_id']) return rwCanDeletePost($post);
    return !empty($addons) && boomAllow($addons['custom3']);
}
function canLikeAdvNews(){ global $addons; $s=rwSettings(); return !empty($s['likes_enabled']) && boomAllow($addons['custom4']) && boomAllow($s['like_rank']); }
function canDelAdvNewsReply($reply=array()){
    global $addons,$data;
    if(!empty($reply) && isset($reply['reply_user']) && (int)$reply['reply_user']===(int)$data['user_id']) return true;
    $s=rwSettings();
    return !empty($addons) && boomAllow($addons['custom5']) && boomAllow($s['delete_comments_rank']);
}
function checkAdvNewsNotify(){
    global $mysqli,$data;
    $last=isset($data['user_gen'])?(int)$data['user_gen']:0;
    $q=$mysqli->query("SELECT COUNT(*) AS total FROM boom_gen WHERE news_date > '$last'");
    if($q && $r=$q->fetch_assoc()) return (int)$r['total'];
    return 0;
}
function countRoomPostsWall(){ return checkAdvNewsNotify(); }
?>
