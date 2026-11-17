<?php
if (!defined('BOOM')) die();
$ad = array(
    'name'=>'Room_wall', 'access'=>1,
    'custom1'=>1, 'custom2'=>1, 'custom3'=>100,
    'custom4'=>1, 'custom5'=>100, 'custom6'=>1, 'custom7'=>100,
    'custom8'=>'', 'custom9'=>'', 'custom10'=>''
);

// Safe, repeatable installer: never drops existing CodyChat or addon data.
$mysqli->query("ALTER TABLE boom_gen ENGINE=InnoDB");
$mysqli->query("CREATE TABLE IF NOT EXISTS boom_gen (
  id INT NOT NULL AUTO_INCREMENT,
  news_poster INT NOT NULL DEFAULT 0,
  news_message MEDIUMTEXT NOT NULL,
  news_file VARCHAR(1000) NOT NULL DEFAULT '',
  news_file_type VARCHAR(20) NOT NULL DEFAULT '',
  news_date INT NOT NULL DEFAULT 1,
  static INT NOT NULL DEFAULT 0, expires_at INT NOT NULL DEFAULT 0,
  PRIMARY KEY(id), KEY news_date(news_date), KEY expires_at(expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
$mysqli->query("CREATE TABLE IF NOT EXISTS boom_gen_like (
  id INT NOT NULL AUTO_INCREMENT, uid INT NOT NULL DEFAULT 0, liked_uid INT NOT NULL DEFAULT 0,
  like_type TINYINT NOT NULL DEFAULT 1, like_post INT NOT NULL DEFAULT 1, like_date INT NOT NULL DEFAULT 0,
  PRIMARY KEY(id), KEY uid(uid), KEY liked_uid(liked_uid), KEY like_post(like_post), KEY like_date(like_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
$mysqli->query("CREATE TABLE IF NOT EXISTS boom_gen_reply (
  reply_id INT NOT NULL AUTO_INCREMENT, parent_id INT NOT NULL DEFAULT 0, reply_user INT NOT NULL DEFAULT 0,
  reply_date INT NOT NULL DEFAULT 0, reply_content VARCHAR(1000) NOT NULL DEFAULT '', reply_uid INT NOT NULL DEFAULT 0,
  PRIMARY KEY(reply_id), KEY parent_id(parent_id), KEY reply_user(reply_user), KEY reply_date(reply_date), KEY reply_uid(reply_uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$columns = array(
 'room_id'=>"ALTER TABLE boom_gen ADD room_id INT NOT NULL DEFAULT 0",
 'room_name'=>"ALTER TABLE boom_gen ADD room_name VARCHAR(100) NOT NULL DEFAULT ''",
 'edited_at'=>"ALTER TABLE boom_gen ADD edited_at INT NOT NULL DEFAULT 0",
 'source_type'=>"ALTER TABLE boom_gen ADD source_type VARCHAR(20) NOT NULL DEFAULT 'manual'",
 'source_id'=>"ALTER TABLE boom_gen ADD source_id INT NULL DEFAULT NULL",
 'expires_at'=>"ALTER TABLE boom_gen ADD expires_at INT NOT NULL DEFAULT 0"
);
foreach($columns as $column=>$sql){
    $check=$mysqli->query("SHOW COLUMNS FROM boom_gen LIKE '".$mysqli->real_escape_string($column)."'");
    if($check && $check->num_rows===0) $mysqli->query($sql);
}
$idx=$mysqli->query("SHOW INDEX FROM boom_gen WHERE Key_name='rw_source_unique'");
if($idx && $idx->num_rows===0) $mysqli->query("ALTER TABLE boom_gen ADD UNIQUE KEY rw_source_unique (source_type, source_id)");

$mysqli->query("CREATE TABLE IF NOT EXISTS boom_gen_settings (
 id TINYINT NOT NULL PRIMARY KEY,
 posts_enabled TINYINT NOT NULL DEFAULT 1, stories_enabled TINYINT NOT NULL DEFAULT 1,
 allow_post_text TINYINT NOT NULL DEFAULT 1, allow_post_images TINYINT NOT NULL DEFAULT 1, allow_post_videos TINYINT NOT NULL DEFAULT 1, allow_post_audio TINYINT NOT NULL DEFAULT 1,
 allow_story_text TINYINT NOT NULL DEFAULT 1, allow_story_images TINYINT NOT NULL DEFAULT 1, allow_story_videos TINYINT NOT NULL DEFAULT 1, allow_story_audio TINYINT NOT NULL DEFAULT 1,
 comments_enabled TINYINT NOT NULL DEFAULT 1, likes_enabled TINYINT NOT NULL DEFAULT 1,
 reactions_enabled TINYINT NOT NULL DEFAULT 1, views_enabled TINYINT NOT NULL DEFAULT 1,
 archive_enabled TINYINT NOT NULL DEFAULT 1, allow_friends_privacy TINYINT NOT NULL DEFAULT 1,
 allow_all_privacy TINYINT NOT NULL DEFAULT 1, show_viewers TINYINT NOT NULL DEFAULT 1,
 story_replies_enabled TINYINT NOT NULL DEFAULT 1,
 post_rank INT NOT NULL DEFAULT 1, story_rank INT NOT NULL DEFAULT 1, view_story_rank INT NOT NULL DEFAULT 1,
 comment_rank INT NOT NULL DEFAULT 1, like_rank INT NOT NULL DEFAULT 1,
 edit_own_rank INT NOT NULL DEFAULT 1, edit_others_rank INT NOT NULL DEFAULT 100,
 delete_own_rank INT NOT NULL DEFAULT 1, delete_others_rank INT NOT NULL DEFAULT 100,
 delete_comments_rank INT NOT NULL DEFAULT 100,
 max_file_mb INT NOT NULL DEFAULT 1, max_image_mb INT NOT NULL DEFAULT 1, max_video_mb INT NOT NULL DEFAULT 1,
 max_audio_mb INT NOT NULL DEFAULT 10, max_post_chars INT NOT NULL DEFAULT 100, max_story_chars INT NOT NULL DEFAULT 100,
 story_hours INT NOT NULL DEFAULT 24, post_expiry_days INT NOT NULL DEFAULT 0,
 max_posts_hour INT NOT NULL DEFAULT 10, max_video_seconds INT NOT NULL DEFAULT 60,
 max_audio_seconds INT NOT NULL DEFAULT 30, story_text_seconds INT NOT NULL DEFAULT 10, story_image_seconds INT NOT NULL DEFAULT 5,
 post_link_rank INT NOT NULL DEFAULT 1, story_link_rank INT NOT NULL DEFAULT 1,
 post_external_link_rank INT NOT NULL DEFAULT 100, story_external_link_rank INT NOT NULL DEFAULT 100, rw_v23_defaults_applied TINYINT NOT NULL DEFAULT 0, rw_v25_link_policy_applied TINYINT NOT NULL DEFAULT 0,
 gold_charges_enabled TINYINT NOT NULL DEFAULT 0,
 post_text_gold INT NOT NULL DEFAULT 10, post_audio_gold INT NOT NULL DEFAULT 20, post_image_gold INT NOT NULL DEFAULT 50, post_video_gold INT NOT NULL DEFAULT 100,
 story_text_gold INT NOT NULL DEFAULT 10, story_audio_gold INT NOT NULL DEFAULT 20, story_image_gold INT NOT NULL DEFAULT 50, story_video_gold INT NOT NULL DEFAULT 100,
 story_pin_rank INT NOT NULL DEFAULT 100, story_circle_size INT NOT NULL DEFAULT 56
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
$mysqli->query("INSERT IGNORE INTO boom_gen_settings (id) VALUES (1)");
$settingColumns = array(
 'story_replies_enabled'=>"ALTER TABLE boom_gen_settings ADD story_replies_enabled TINYINT NOT NULL DEFAULT 1",
 'post_expiry_days'=>"ALTER TABLE boom_gen_settings ADD post_expiry_days INT NOT NULL DEFAULT 0",
 'allow_post_text'=>"ALTER TABLE boom_gen_settings ADD allow_post_text TINYINT NOT NULL DEFAULT 1",
 'allow_post_images'=>"ALTER TABLE boom_gen_settings ADD allow_post_images TINYINT NOT NULL DEFAULT 1",
 'allow_post_videos'=>"ALTER TABLE boom_gen_settings ADD allow_post_videos TINYINT NOT NULL DEFAULT 1",
 'allow_post_audio'=>"ALTER TABLE boom_gen_settings ADD allow_post_audio TINYINT NOT NULL DEFAULT 1",
 'allow_story_text'=>"ALTER TABLE boom_gen_settings ADD allow_story_text TINYINT NOT NULL DEFAULT 1",
 'allow_story_images'=>"ALTER TABLE boom_gen_settings ADD allow_story_images TINYINT NOT NULL DEFAULT 1",
 'allow_story_videos'=>"ALTER TABLE boom_gen_settings ADD allow_story_videos TINYINT NOT NULL DEFAULT 1",
 'allow_story_audio'=>"ALTER TABLE boom_gen_settings ADD allow_story_audio TINYINT NOT NULL DEFAULT 1",
 'view_story_rank'=>"ALTER TABLE boom_gen_settings ADD view_story_rank INT NOT NULL DEFAULT 1",
 'max_post_chars'=>"ALTER TABLE boom_gen_settings ADD max_post_chars INT NOT NULL DEFAULT 100",
 'max_story_chars'=>"ALTER TABLE boom_gen_settings ADD max_story_chars INT NOT NULL DEFAULT 100",
 'story_text_seconds'=>"ALTER TABLE boom_gen_settings ADD story_text_seconds INT NOT NULL DEFAULT 10",
 'story_image_seconds'=>"ALTER TABLE boom_gen_settings ADD story_image_seconds INT NOT NULL DEFAULT 5",
 'post_link_rank'=>"ALTER TABLE boom_gen_settings ADD post_link_rank INT NOT NULL DEFAULT 1",
 'story_link_rank'=>"ALTER TABLE boom_gen_settings ADD story_link_rank INT NOT NULL DEFAULT 1",
 'post_external_link_rank'=>"ALTER TABLE boom_gen_settings ADD post_external_link_rank INT NOT NULL DEFAULT 100",
 'story_external_link_rank'=>"ALTER TABLE boom_gen_settings ADD story_external_link_rank INT NOT NULL DEFAULT 100",
 'rw_v25_link_policy_applied'=>"ALTER TABLE boom_gen_settings ADD rw_v25_link_policy_applied TINYINT NOT NULL DEFAULT 0",
 'rw_v23_defaults_applied'=>"ALTER TABLE boom_gen_settings ADD rw_v23_defaults_applied TINYINT NOT NULL DEFAULT 0",
 'gold_charges_enabled'=>"ALTER TABLE boom_gen_settings ADD gold_charges_enabled TINYINT NOT NULL DEFAULT 0",
 'post_text_gold'=>"ALTER TABLE boom_gen_settings ADD post_text_gold INT NOT NULL DEFAULT 10",
 'post_audio_gold'=>"ALTER TABLE boom_gen_settings ADD post_audio_gold INT NOT NULL DEFAULT 20",
 'post_image_gold'=>"ALTER TABLE boom_gen_settings ADD post_image_gold INT NOT NULL DEFAULT 50",
 'post_video_gold'=>"ALTER TABLE boom_gen_settings ADD post_video_gold INT NOT NULL DEFAULT 100",
 'story_text_gold'=>"ALTER TABLE boom_gen_settings ADD story_text_gold INT NOT NULL DEFAULT 10",
 'story_audio_gold'=>"ALTER TABLE boom_gen_settings ADD story_audio_gold INT NOT NULL DEFAULT 20",
 'story_image_gold'=>"ALTER TABLE boom_gen_settings ADD story_image_gold INT NOT NULL DEFAULT 50",
 'story_video_gold'=>"ALTER TABLE boom_gen_settings ADD story_video_gold INT NOT NULL DEFAULT 100",
 'story_pin_rank'=>"ALTER TABLE boom_gen_settings ADD story_pin_rank INT NOT NULL DEFAULT 100",
 'story_circle_size'=>"ALTER TABLE boom_gen_settings ADD story_circle_size INT NOT NULL DEFAULT 56"
);
foreach($settingColumns as $column=>$sql){$check=$mysqli->query("SHOW COLUMNS FROM boom_gen_settings LIKE '".$mysqli->real_escape_string($column)."'");if($check && $check->num_rows===0)$mysqli->query($sql);}
// One-time v2.3 default correction. Non-default values already chosen by the administrator remain intact.
$rw23m=$mysqli->query("SELECT rw_v23_defaults_applied FROM boom_gen_settings WHERE id=1 LIMIT 1");
$rw23done=0; if($rw23m && $rw23r=$rw23m->fetch_assoc()) $rw23done=(int)$rw23r['rw_v23_defaults_applied'];
if(!$rw23done){
    $mysqli->query("UPDATE boom_gen_settings SET max_image_mb=1 WHERE id=1 AND max_image_mb=5");
    $mysqli->query("UPDATE boom_gen_settings SET max_video_mb=1 WHERE id=1 AND max_video_mb=10");
    $mysqli->query("UPDATE boom_gen_settings SET max_audio_seconds=30 WHERE id=1 AND max_audio_seconds=60");
    $mysqli->query("UPDATE boom_gen_settings SET max_file_mb=1 WHERE id=1 AND max_file_mb=5");
    $mysqli->query("UPDATE boom_gen_settings SET rw_v23_defaults_applied=1 WHERE id=1");
}
// One-time v2.5 migration: make YouTube available from member rank by default;
// keep any non-default rank chosen by the administrator, and set external links to rank 100.
$rw25m=$mysqli->query("SELECT rw_v25_link_policy_applied FROM boom_gen_settings WHERE id=1 LIMIT 1");
$rw25done=0; if($rw25m && $rw25r=$rw25m->fetch_assoc()) $rw25done=(int)$rw25r['rw_v25_link_policy_applied'];
if(!$rw25done){
    $mysqli->query("UPDATE boom_gen_settings SET post_link_rank=1 WHERE id=1 AND post_link_rank=100");
    $mysqli->query("UPDATE boom_gen_settings SET story_link_rank=1 WHERE id=1 AND story_link_rank=100");
    $mysqli->query("UPDATE boom_gen_settings SET post_external_link_rank=100, story_external_link_rank=100 WHERE id=1");
    $mysqli->query("UPDATE boom_gen_settings SET rw_v25_link_policy_applied=1 WHERE id=1");
}
$mysqli->query("ALTER TABLE boom_gen MODIFY news_message MEDIUMTEXT NOT NULL");
$imageLimitCol=$mysqli->query("SHOW COLUMNS FROM boom_gen_settings LIKE 'max_image_mb'");
if($imageLimitCol && $imageLimitCol->num_rows===0){
    $mysqli->query("ALTER TABLE boom_gen_settings ADD max_image_mb INT NOT NULL DEFAULT 5");
    // Preserve the previous v2.1 image-size setting during the one-time migration.
    $mysqli->query("UPDATE boom_gen_settings SET max_image_mb=max_file_mb WHERE id=1");
}
$mysqli->query("CREATE TABLE IF NOT EXISTS boom_gen_story (
 id INT NOT NULL AUTO_INCREMENT, story_user INT NOT NULL DEFAULT 0,
 story_text MEDIUMTEXT NOT NULL, privacy VARCHAR(12) NOT NULL DEFAULT 'all',
 room_id INT NOT NULL DEFAULT 0, room_name VARCHAR(100) NOT NULL DEFAULT '',
 created_at INT NOT NULL DEFAULT 0, expires_at INT NOT NULL DEFAULT 0,
 archived TINYINT NOT NULL DEFAULT 0, story_pinned TINYINT NOT NULL DEFAULT 0, PRIMARY KEY(id), KEY story_user(story_user),
 KEY expires_at(expires_at), KEY created_at(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
$storyPinCol=$mysqli->query("SHOW COLUMNS FROM boom_gen_story LIKE 'story_pinned'");
if($storyPinCol && $storyPinCol->num_rows===0) $mysqli->query("ALTER TABLE boom_gen_story ADD story_pinned TINYINT NOT NULL DEFAULT 0");
$mysqli->query("ALTER TABLE boom_gen_story MODIFY story_text MEDIUMTEXT NOT NULL");
$mysqli->query("CREATE TABLE IF NOT EXISTS boom_gen_story_view (
 id INT NOT NULL AUTO_INCREMENT, story_id INT NOT NULL, viewer_id INT NOT NULL,
 viewed_at INT NOT NULL DEFAULT 0, PRIMARY KEY(id), UNIQUE KEY rw_story_view_unique(story_id,viewer_id),
 KEY viewer_id(viewer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
$mysqli->query("CREATE TABLE IF NOT EXISTS boom_gen_story_reaction (
 id INT NOT NULL AUTO_INCREMENT, story_id INT NOT NULL, user_id INT NOT NULL,
 reaction VARCHAR(16) NOT NULL DEFAULT 'like', created_at INT NOT NULL DEFAULT 0,
 PRIMARY KEY(id), UNIQUE KEY rw_story_reaction_unique(story_id,user_id), KEY user_id(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
$mysqli->query("CREATE TABLE IF NOT EXISTS boom_gen_story_dm (
 id INT NOT NULL AUTO_INCREMENT, story_id INT NOT NULL DEFAULT 0,
 sender_id INT NOT NULL DEFAULT 0, target_id INT NOT NULL DEFAULT 0,
 private_id INT NOT NULL DEFAULT 0, created_at INT NOT NULL DEFAULT 0,
 PRIMARY KEY(id), UNIQUE KEY rw_private_unique(private_id),
 KEY story_id(story_id), KEY sender_id(sender_id), KEY target_id(target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
$mysqli->query("CREATE TABLE IF NOT EXISTS boom_gen_charge (
 id INT NOT NULL AUTO_INCREMENT, request_key CHAR(32) NOT NULL,
 user_id INT NOT NULL DEFAULT 0, content_type VARCHAR(8) NOT NULL,
 content_id INT NOT NULL DEFAULT 0, content_kind VARCHAR(8) NOT NULL,
 gold_amount INT NOT NULL DEFAULT 0, created_at INT NOT NULL DEFAULT 0,
 PRIMARY KEY(id), UNIQUE KEY rw_request_key(request_key),
 UNIQUE KEY rw_content_unique(content_type,content_id), KEY user_id(user_id), KEY created_at(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$mysqli->query("CREATE TABLE IF NOT EXISTS boom_gen_media (
 id INT NOT NULL AUTO_INCREMENT, owner_type VARCHAR(10) NOT NULL,
 owner_id INT NOT NULL DEFAULT 0, media_path VARCHAR(255) NOT NULL,
 media_mime VARCHAR(100) NOT NULL, media_kind VARCHAR(10) NOT NULL,
 media_size INT NOT NULL DEFAULT 0, created_at INT NOT NULL DEFAULT 0,
 PRIMARY KEY(id), KEY owner_lookup(owner_type,owner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
$mysqli->query("CREATE TABLE IF NOT EXISTS boom_gen_upload (
 id INT NOT NULL AUTO_INCREMENT, upload_key VARCHAR(64) NOT NULL,
 file_user INT NOT NULL, file_path VARCHAR(255) NOT NULL,
 file_mime VARCHAR(100) NOT NULL, file_kind VARCHAR(10) NOT NULL,
 file_size INT NOT NULL DEFAULT 0, created_at INT NOT NULL DEFAULT 0,
 used_at INT NOT NULL DEFAULT 0, PRIMARY KEY(id), UNIQUE KEY upload_key(upload_key),
 KEY file_user(file_user), KEY created_at(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
$mysqli->query("CREATE TABLE IF NOT EXISTS boom_gen_import_log (
 id INT NOT NULL AUTO_INCREMENT, source_type VARCHAR(20) NOT NULL,
 source_id INT NOT NULL, imported_at INT NOT NULL DEFAULT 0,
 PRIMARY KEY(id), UNIQUE KEY source_unique(source_type,source_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
$mysqli->query("CREATE TABLE IF NOT EXISTS boom_gen_import_state (id TINYINT NOT NULL PRIMARY KEY, last_chat_id INT NOT NULL DEFAULT 0, updated_at INT NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
$mysqli->query("INSERT IGNORE INTO boom_gen_import_state (id,last_chat_id) VALUES (1,0)");

$userGen=$mysqli->query("SHOW COLUMNS FROM boom_users LIKE 'user_gen'");
if($userGen && $userGen->num_rows===0) $mysqli->query("ALTER TABLE boom_users ADD user_gen INT NOT NULL DEFAULT 0");

$parent = dirname(BOOM_PATH);
$docroot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
$realParent = realpath($parent);
$mediaDir = ($docroot && $realParent && strpos($realParent, $docroot) === 0) ? rtrim(sys_get_temp_dir(), '/\\\\').'/roomwall_'.substr(sha1(BOOM_PATH),0,12) : $parent.'/.roomwall_'.substr(sha1(BOOM_PATH),0,12);
if(!is_dir($mediaDir)) @mkdir($mediaDir,0755,true);
if(is_dir($mediaDir)){
    @file_put_contents($mediaDir.'/.htaccess', "Require all denied\nDeny from all\n");
    @file_put_contents($mediaDir.'/index.php', "<?php http_response_code(404); exit;\n");
}
?>
