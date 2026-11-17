<?php
if(!defined('BOOM')){ die(); }
global $data;
if(empty($data['user_id']) || (int)$data['user_rank'] !== 100){
    return;
}

if(!$mysqli->query("CREATE TABLE IF NOT EXISTS rank_control_settings (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    primary_owner_id INT UNSIGNED NOT NULL,
    created_at INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")){ return; }

if(!$mysqli->query("CREATE TABLE IF NOT EXISTS rank_control_ranks (
    rank_value TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    rank_name VARCHAR(80) NOT NULL,
    rank_icon VARCHAR(100) NOT NULL DEFAULT 'user.svg',
    rank_group ENUM('member','staff') NOT NULL DEFAULT 'member',
    created_by INT UNSIGNED NOT NULL DEFAULT 0,
    created_at INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")){ return; }

if(!$mysqli->query("CREATE TABLE IF NOT EXISTS rank_control_permissions (
    rank_value TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    can_promote TINYINT(1) NOT NULL DEFAULT 0,
    updated_by INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")){ return; }

if(!$mysqli->query("CREATE TABLE IF NOT EXISTS rank_control_owners (
    user_id INT UNSIGNED NOT NULL PRIMARY KEY,
    previous_rank SMALLINT NOT NULL DEFAULT 1,
    granted_by INT UNSIGNED NOT NULL DEFAULT 0,
    granted_at INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")){ return; }

if(!$mysqli->query("CREATE TABLE IF NOT EXISTS rank_control_audit (
    audit_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    actor_id INT UNSIGNED NOT NULL DEFAULT 0,
    target_id INT UNSIGNED NOT NULL DEFAULT 0,
    action_type VARCHAR(40) NOT NULL,
    old_rank SMALLINT NULL,
    new_rank SMALLINT NULL,
    details VARCHAR(500) NOT NULL DEFAULT '',
    created_at INT UNSIGNED NOT NULL DEFAULT 0,
    KEY idx_rank_control_created (created_at),
    KEY idx_rank_control_target (target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")){ return; }

$primaryOwner = (int)$data['user_id'];
$now = time();
if($primaryOwner <= 0){ return; }
if(!$mysqli->query("INSERT IGNORE INTO rank_control_settings (id, primary_owner_id, created_at) VALUES (1, '$primaryOwner', '$now')")){ return; }
$verifySettings = $mysqli->query("SELECT primary_owner_id FROM rank_control_settings WHERE id = 1 LIMIT 1");
if(!$verifySettings || $verifySettings->num_rows < 1){ return; }

$ad = array(
    'name' => 'rank_control_blk',
    'access' => 100,
    'custom1' => '1.0.2',
    'custom2' => '',
    'custom3' => '',
);
?>
