<?php
/* CodyChat addon installer. This file is included by system_save.php with $mysqli available. */
if(!isset($mysqli) || !($mysqli instanceof mysqli)){
    $ad = array('name' => 'voice_mic', 'access' => 0, 'custom1' => '');
    return;
}

$queries = array();
$queries[] = "CREATE TABLE IF NOT EXISTS `boom_voice_mic_settings` (
  `id` TINYINT UNSIGNED NOT NULL,
  `enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `pause_until` INT UNSIGNED NOT NULL DEFAULT 0,
  `pause_message` VARCHAR(500) NOT NULL DEFAULT '',
  `gold_mode` TINYINT(1) NOT NULL DEFAULT 0,
  `gold_cost` INT UNSIGNED NOT NULL DEFAULT 0,
  `speaker_gold_mode` TINYINT(1) NOT NULL DEFAULT 0,
  `speaker_gold_cost` INT UNSIGNED NOT NULL DEFAULT 0,
  `listener_gold_mode` TINYINT(1) NOT NULL DEFAULT 0,
  `listener_gold_cost` INT UNSIGNED NOT NULL DEFAULT 0,
  `allow_listeners` TINYINT(1) NOT NULL DEFAULT 1,
  `require_speaker_approval` TINYINT(1) NOT NULL DEFAULT 0,
  `allow_room_staff` TINYINT(1) NOT NULL DEFAULT 1,
  `default_room_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `max_speakers` SMALLINT UNSIGNED NOT NULL DEFAULT 10,
  `max_listeners` SMALLINT UNSIGNED NOT NULL DEFAULT 50,
  `bar_height` SMALLINT UNSIGNED NOT NULL DEFAULT 74,
  `avatar_size` SMALLINT UNSIGNED NOT NULL DEFAULT 32,
  `show_names` TINYINT(1) NOT NULL DEFAULT 1,
  `show_ranks` TINYINT(1) NOT NULL DEFAULT 1,
  `min_speaker_rank` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `min_listener_rank` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `manage_rank` SMALLINT UNSIGNED NOT NULL DEFAULT 70,
  `ban_rank` SMALLINT UNSIGNED NOT NULL DEFAULT 80,
  `pause_rank` SMALLINT UNSIGNED NOT NULL DEFAULT 90,
  `speaker_color` VARCHAR(20) NOT NULL DEFAULT '#28b66f',
  `listener_color` VARCHAR(20) NOT NULL DEFAULT '#368ee8',
  `divider_color` VARCHAR(20) NOT NULL DEFAULT '#8b929c',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
$queries[] = "CREATE TABLE IF NOT EXISTS `boom_voice_mic_rooms` (
  `room_id` INT UNSIGNED NOT NULL,
  `enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `updated_by` INT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`room_id`),
  KEY `enabled` (`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
$queries[] = "CREATE TABLE IF NOT EXISTS `boom_voice_mic_members` (
  `room_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `mic_role` ENUM('speaker','listener') NOT NULL DEFAULT 'listener',
  `mic_muted` TINYINT(1) NOT NULL DEFAULT 1,
  `admin_muted` TINYINT(1) NOT NULL DEFAULT 0,
  `payment_confirmed` TINYINT(1) NOT NULL DEFAULT 1,
  `paid_gold` INT UNSIGNED NOT NULL DEFAULT 0,
  `billing_rate` INT UNSIGNED NOT NULL DEFAULT 0,
  `billed_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
  `next_bill_at` INT UNSIGNED NOT NULL DEFAULT 0,
  `billing_started_at` INT UNSIGNED NOT NULL DEFAULT 0,
  `joined_at` INT UNSIGNED NOT NULL DEFAULT 0,
  `last_seen` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`room_id`,`user_id`),
  KEY `room_seen` (`room_id`,`last_seen`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
$queries[] = "CREATE TABLE IF NOT EXISTS `boom_voice_mic_bans` (
  `room_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `expires_at` INT UNSIGNED NOT NULL DEFAULT 0,
  `reason` VARCHAR(500) NOT NULL DEFAULT '',
  `created_by` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`room_id`,`user_id`),
  KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
$queries[] = "CREATE TABLE IF NOT EXISTS `boom_voice_mic_mutes` (
  `room_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `reason` VARCHAR(500) NOT NULL DEFAULT '',
  `created_by` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`room_id`,`user_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
$queries[] = "CREATE TABLE IF NOT EXISTS `boom_voice_mic_requests` (
  `room_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `status` TINYINT NOT NULL DEFAULT 0,
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`room_id`,`user_id`),
  KEY `room_status` (`room_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
$queries[] = "CREATE TABLE IF NOT EXISTS `boom_voice_mic_logs` (
  `log_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `room_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `actor_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `target_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `action` VARCHAR(40) NOT NULL DEFAULT '',
  `details` VARCHAR(500) NOT NULL DEFAULT '',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`log_id`),
  KEY `room_date` (`room_id`,`created_at`),
  KEY `target_id` (`target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

$ok = true;
foreach($queries as $sql){
    if(!$mysqli->query($sql)){
        $ok = false;
        error_log('Voice Mic install error: ' . $mysqli->error);
    }
}
// Forward-compatible migration from the earlier preview build of Voice Mic.
$columnMigrations = array(
    array('boom_voice_mic_members','admin_muted',"TINYINT(1) NOT NULL DEFAULT 0 AFTER `mic_muted`"),
    array('boom_voice_mic_members','payment_confirmed',"TINYINT(1) NOT NULL DEFAULT 1 AFTER `admin_muted`"),
    array('boom_voice_mic_members','paid_gold',"INT UNSIGNED NOT NULL DEFAULT 0 AFTER `payment_confirmed`"),
    array('boom_voice_mic_members','billing_rate',"INT UNSIGNED NOT NULL DEFAULT 0 AFTER `paid_gold`"),
    array('boom_voice_mic_members','billed_minutes',"INT UNSIGNED NOT NULL DEFAULT 0 AFTER `billing_rate`"),
    array('boom_voice_mic_members','next_bill_at',"INT UNSIGNED NOT NULL DEFAULT 0 AFTER `billed_minutes`"),
    array('boom_voice_mic_members','billing_started_at',"INT UNSIGNED NOT NULL DEFAULT 0 AFTER `next_bill_at`"),
    array('boom_voice_mic_settings','gold_mode',"TINYINT(1) NOT NULL DEFAULT 0 AFTER `pause_message`"),
    array('boom_voice_mic_settings','gold_cost',"INT UNSIGNED NOT NULL DEFAULT 0 AFTER `gold_mode`"),
    array('boom_voice_mic_settings','speaker_gold_mode',"TINYINT(1) NOT NULL DEFAULT 0 AFTER `gold_cost`"),
    array('boom_voice_mic_settings','speaker_gold_cost',"INT UNSIGNED NOT NULL DEFAULT 0 AFTER `speaker_gold_mode`"),
    array('boom_voice_mic_settings','listener_gold_mode',"TINYINT(1) NOT NULL DEFAULT 0 AFTER `speaker_gold_cost`"),
    array('boom_voice_mic_settings','listener_gold_cost',"INT UNSIGNED NOT NULL DEFAULT 0 AFTER `listener_gold_mode`"),
    array('boom_voice_mic_rooms','enabled',"TINYINT(1) NOT NULL DEFAULT 0 AFTER `room_id`"),
    array('boom_voice_mic_rooms','updated_by',"INT UNSIGNED NOT NULL DEFAULT 0 AFTER `enabled`")
);
$newFeeColumnsAdded = false;
foreach($columnMigrations as $migration){
    $table=$migration[0]; $column=$migration[1]; $definition=$migration[2];
    $check=$mysqli->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
    if($check && !$check->num_rows){
        if($table === 'boom_voice_mic_settings' && in_array($column,array('speaker_gold_mode','speaker_gold_cost','listener_gold_mode','listener_gold_cost'),true)){ $newFeeColumnsAdded = true; }
        if(!$mysqli->query("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}")){
            $ok=false;
            error_log('Voice Mic migration error: ' . $mysqli->error);
        }
    } elseif(!$check){
        $ok=false;
        error_log('Voice Mic migration inspection error: ' . $mysqli->error);
    }
}
// The preview build used a UNIQUE live_room column. This add-on identifies rooms by room_id now.
$oldIndex=$mysqli->query("SHOW INDEX FROM `boom_voice_mic_rooms` WHERE Key_name='live_room'");
if($oldIndex && $oldIndex->num_rows){
    if(!$mysqli->query("ALTER TABLE `boom_voice_mic_rooms` DROP INDEX `live_room`")){
        $ok=false;
        error_log('Voice Mic index migration error: ' . $mysqli->error);
    }
}
if($newFeeColumnsAdded){
    // Preserve the previous shared fee for both modes on existing installations.
    $mysqli->query("UPDATE boom_voice_mic_settings SET speaker_gold_mode=gold_mode,speaker_gold_cost=gold_cost,listener_gold_mode=gold_mode,listener_gold_cost=gold_cost WHERE id=1");
}
$seed=$mysqli->query("INSERT IGNORE INTO `boom_voice_mic_settings` (`id`) VALUES (1)");
if(!$seed){ $ok=false; error_log('Voice Mic settings seed error: ' . $mysqli->error); }

$ad = $ok ? array(
    'name' => 'voice_mic',
    'access' => 0,
    'custom1' => '1',
    'custom2' => '74',
    'custom3' => '32'
) : array();
?>
