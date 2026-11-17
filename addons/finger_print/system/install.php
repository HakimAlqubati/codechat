<?php
if (!defined('BOOM')) { die(); }
global $setting;

$ad = array(
    'name' => 'finger_print',
    'access' => 80,
    'load' => 0,
);

$mysqli->query("CREATE TABLE IF NOT EXISTS `boom_permabanned` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `ban_hunter` varchar(100) NOT NULL DEFAULT '0',
    `user_name` varchar(60) NOT NULL DEFAULT '',
    `ip` varchar(100) NOT NULL DEFAULT '',
    `ban_user` int(11) NOT NULL DEFAULT '0',
    `parmakizleri` varchar(128) NOT NULL DEFAULT '',
    `reason` varchar(500) NOT NULL DEFAULT '',
    `ban_date` int(11) NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    KEY `parmakizleri` (`parmakizleri`),
    KEY `ban_user` (`ban_user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$columns = array();
$columnResult = $mysqli->query("SHOW COLUMNS FROM `boom_users`");
if ($columnResult) {
    while ($column = $columnResult->fetch_assoc()) { $columns[$column['Field']] = true; }
}
if (!isset($columns['parmakizi'])) {
    $mysqli->query("ALTER TABLE `boom_users` ADD `parmakizi` varchar(128) DEFAULT NULL");
}

$legacyColumns = array();
$legacyResult = $mysqli->query("SHOW COLUMNS FROM `boom_permabanned`");
if ($legacyResult) {
    while ($column = $legacyResult->fetch_assoc()) { $legacyColumns[$column['Field']] = true; }
}
if (!isset($legacyColumns['reason'])) {
    $mysqli->query("ALTER TABLE `boom_permabanned` ADD `reason` varchar(500) NOT NULL DEFAULT ''");
}
if (!isset($legacyColumns['ban_date'])) {
    $mysqli->query("ALTER TABLE `boom_permabanned` ADD `ban_date` int(11) NOT NULL DEFAULT '0'");
}
if (isset($legacyColumns['parmakizleri'])) {
    // Keep all legacy cemetery records and widen the fingerprint field for future browser hashes.
    $mysqli->query("ALTER TABLE `boom_permabanned` MODIFY `parmakizleri` varchar(128) NOT NULL DEFAULT ''");
}

// Add lookup indexes only when they are not already present.
$hasUserFingerprintIndex = false;
$userIndexResult = $mysqli->query("SHOW INDEX FROM `boom_users`");
if ($userIndexResult) {
    while ($index = $userIndexResult->fetch_assoc()) { if ($index['Column_name'] === 'parmakizi') { $hasUserFingerprintIndex = true; } }
}
if (!$hasUserFingerprintIndex) {
    $mysqli->query("ALTER TABLE `boom_users` ADD INDEX `idx_parmakizi` (`parmakizi`)");
}
$hasCemeteryFingerprintIndex = false;
$cemeteryIndexResult = $mysqli->query("SHOW INDEX FROM `boom_permabanned`");
if ($cemeteryIndexResult) {
    while ($index = $cemeteryIndexResult->fetch_assoc()) { if ($index['Column_name'] === 'parmakizleri') { $hasCemeteryFingerprintIndex = true; } }
}
if (!$hasCemeteryFingerprintIndex) {
    $mysqli->query("ALTER TABLE `boom_permabanned` ADD INDEX `idx_parmakizleri` (`parmakizleri`)");
}

$mysqli->query("CREATE TABLE IF NOT EXISTS `boom_fp_settings` (
    `id` int(11) NOT NULL,
    `jail_room_id` int(11) NOT NULL DEFAULT '0',
    `vpn_monitor` tinyint(1) NOT NULL DEFAULT '1',
    `vpn_log` tinyint(1) NOT NULL DEFAULT '1',
    `created_at` int(11) NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$mysqli->query("CREATE TABLE IF NOT EXISTS `boom_fp_jail` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `fingerprint` varchar(128) NOT NULL DEFAULT '',
    `target_user` int(11) NOT NULL DEFAULT '0',
    `target_name` varchar(60) NOT NULL DEFAULT '',
    `reason` varchar(500) NOT NULL DEFAULT '',
    `created_by` int(11) NOT NULL DEFAULT '0',
    `created_by_name` varchar(60) NOT NULL DEFAULT '',
    `created_at` int(11) NOT NULL DEFAULT '0',
    `expires_at` int(11) NOT NULL DEFAULT '0',
    `active` tinyint(1) NOT NULL DEFAULT '1',
    `released_by` int(11) NOT NULL DEFAULT '0',
    `released_by_name` varchar(60) NOT NULL DEFAULT '',
    `released_at` int(11) NOT NULL DEFAULT '0',
    `release_reason` varchar(200) NOT NULL DEFAULT '',
    PRIMARY KEY (`id`),
    KEY `fp_active` (`fingerprint`,`active`),
    KEY `expires_at` (`expires_at`),
    KEY `target_user` (`target_user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$mysqli->query("CREATE TABLE IF NOT EXISTS `boom_fp_history` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `event_type` varchar(40) NOT NULL DEFAULT '',
    `fingerprint` varchar(128) NOT NULL DEFAULT '',
    `target_user` int(11) NOT NULL DEFAULT '0',
    `target_name` varchar(60) NOT NULL DEFAULT '',
    `actor_user` int(11) NOT NULL DEFAULT '0',
    `actor_name` varchar(60) NOT NULL DEFAULT '',
    `ip` varchar(45) NOT NULL DEFAULT '',
    `details` varchar(1000) NOT NULL DEFAULT '',
    `event_date` int(11) NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    KEY `event_type` (`event_type`),
    KEY `event_date` (`event_date`),
    KEY `fingerprint` (`fingerprint`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$mysqli->query("CREATE TABLE IF NOT EXISTS `boom_fp_vpn_log` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `fingerprint` varchar(128) NOT NULL DEFAULT '',
    `user_id` int(11) NOT NULL DEFAULT '0',
    `user_name` varchar(60) NOT NULL DEFAULT '',
    `ip` varchar(45) NOT NULL DEFAULT '',
    `detection_type` varchar(32) NOT NULL DEFAULT 'vpn_proxy',
    `hits` int(11) NOT NULL DEFAULT '1',
    `first_seen` int(11) NOT NULL DEFAULT '0',
    `last_seen` int(11) NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    UNIQUE KEY `user_ip_type` (`user_id`,`ip`,`detection_type`),
    KEY `last_seen` (`last_seen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$mysqli->query("CREATE TABLE IF NOT EXISTS `boom_fp_exceptions` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL DEFAULT '0',
    `user_name` varchar(60) NOT NULL DEFAULT '',
    `fingerprint` varchar(128) NOT NULL DEFAULT '',
    `reason` varchar(500) NOT NULL DEFAULT '',
    `created_by` int(11) NOT NULL DEFAULT '0',
    `created_by_name` varchar(60) NOT NULL DEFAULT '',
    `created_at` int(11) NOT NULL DEFAULT '0',
    `active` tinyint(1) NOT NULL DEFAULT '1',
    PRIMARY KEY (`id`),
    UNIQUE KEY `user_fingerprint` (`user_id`,`fingerprint`),
    KEY `fingerprint` (`fingerprint`),
    KEY `active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$mysqli->query("INSERT IGNORE INTO `boom_fp_settings` (`id`,`created_at`) VALUES (1,'" . time() . "')");

// Create a dedicated restricted room once. Only rank 100 and active prisoners can enter it.
$roomName = 'سجن البصمة';
$room = $mysqli->query("SELECT room_id FROM boom_rooms WHERE room_name = 'سجن البصمة' ORDER BY room_id ASC LIMIT 1");
if ($room && $room->num_rows > 0) {
    $roomId = (int) $room->fetch_assoc()['room_id'];
    $mysqli->query("UPDATE boom_rooms SET access = 100, password = '', description = 'غرفة مخصصة لتنفيذ عقوبات السجن بالبصمة', room_system = 1 WHERE room_id = '$roomId'");
} else {
    $ownerId = isset($setting['system_id']) ? (int) $setting['system_id'] : 1;
    $created = time();
    $mysqli->query("INSERT INTO boom_rooms (room_name, access, description, password, room_system, room_creator, room_action) VALUES ('سجن البصمة', 100, 'غرفة مخصصة لتنفيذ عقوبات السجن بالبصمة', '', 1, '$ownerId', '$created')");
    $roomId = (int) $mysqli->insert_id;
}
if ($roomId > 0) {
    $mysqli->query("UPDATE boom_fp_settings SET jail_room_id = '$roomId' WHERE id = 1");
}
// The new parmakizi column must be visible to sessions previously cached by Redis.
if (function_exists('redisFlushAll')) { redisFlushAll(); }
?>
