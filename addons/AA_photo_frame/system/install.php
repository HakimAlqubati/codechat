<?php
if (!defined('BOOM')) {
    die();
}

if (boomAllow(10)) {
    $ad = array(
        'name' => 'AA_photo_frame',
        'access' => 11,
    );
}

$mysqli->query("CREATE TABLE IF NOT EXISTS `photo_frames` (
    `frame_id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `frame_name` varchar(100) NOT NULL,
    PRIMARY KEY (`frame_id`),
    UNIQUE KEY `uniq_photo_frame_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Keep the latest personal selection if the previous table contained duplicates.
$mysqli->query("DELETE older FROM photo_frames older INNER JOIN photo_frames newer ON older.user_id = newer.user_id AND older.frame_id < newer.frame_id");
$hasUniqueUser = false;
$indexes = $mysqli->query("SHOW INDEX FROM photo_frames");
if ($indexes) {
    while ($index = $indexes->fetch_assoc()) {
        if ($index['Column_name'] === 'user_id' && (int)$index['Non_unique'] === 0) {
            $hasUniqueUser = true;
        }
    }
}
if (!$hasUniqueUser) {
    $mysqli->query("ALTER TABLE photo_frames ADD UNIQUE KEY uniq_photo_frame_user (user_id)");
}

$mysqli->query("CREATE TABLE IF NOT EXISTS `photo_frame_settings` (
    `setting_id` tinyint(3) NOT NULL,
    `frame_scale` smallint(5) NOT NULL DEFAULT 116,
    PRIMARY KEY (`setting_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$mysqli->query("INSERT IGNORE INTO photo_frame_settings (setting_id, frame_scale) VALUES (1, 116)");

$mysqli->query("CREATE TABLE IF NOT EXISTS `photo_frame_rank_rules` (
    `rule_id` int(11) NOT NULL AUTO_INCREMENT,
    `rank_id` int(11) NOT NULL,
    `frame_name` varchar(100) NOT NULL,
    `created_at` int(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (`rule_id`),
    UNIQUE KEY `uniq_photo_frame_rank` (`rank_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
?>
