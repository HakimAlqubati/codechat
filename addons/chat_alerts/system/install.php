<?php
if(!defined('BOOM')){
    die();
}

/* Create only the add-on's own tables. No existing Codychat table is altered. */
$alertsTable = "CREATE TABLE IF NOT EXISTS boom_chat_alerts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sender_id INT UNSIGNED NOT NULL DEFAULT 0,
    sender_name VARCHAR(100) NOT NULL DEFAULT '',
    display_mode TINYINT UNSIGNED NOT NULL DEFAULT 0,
    target_rank SMALLINT NOT NULL DEFAULT -1,
    private_copy TINYINT UNSIGNED NOT NULL DEFAULT 0,
    title VARCHAR(160) NOT NULL DEFAULT '',
    message TEXT NOT NULL,
    created_at INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY target_created (target_rank, created_at),
    KEY sender_created (sender_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$seenTable = "CREATE TABLE IF NOT EXISTS boom_chat_alerts_seen (
    alert_id BIGINT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    seen_at INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (alert_id, user_id),
    KEY user_seen (user_id, seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if(!isset($mysqli) || !$mysqli->query($alertsTable) || !$mysqli->query($seenTable)){
    die('Chat Alerts database setup failed.');
}

$ad = array(
    'name' => 'chat_alerts',
    'access' => 70,
    'custom1' => '',
    'custom2' => '',
    'custom3' => '',
);
?>
