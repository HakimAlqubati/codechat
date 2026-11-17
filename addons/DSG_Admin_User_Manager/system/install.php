<?php
/** DSG Admin User Manager v2 installer. */
if(!defined('BOOM') || !isset($mysqli)){
    die();
}

$schema = array();
$schema[] = "CREATE TABLE IF NOT EXISTS boom_dsg_admin_likes (
    user_id INT UNSIGNED NOT NULL,
    bonus_likes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
$schema[] = "CREATE TABLE IF NOT EXISTS boom_dsg_user_manager_logs (
    log_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_id INT UNSIGNED NOT NULL,
    target_id INT UNSIGNED NOT NULL,
    stat_key VARCHAR(20) NOT NULL,
    operation VARCHAR(12) NOT NULL,
    old_value VARCHAR(32) NOT NULL,
    new_value VARCHAR(32) NOT NULL,
    change_amount VARCHAR(32) NOT NULL DEFAULT '0',
    reason VARCHAR(255) NOT NULL DEFAULT '',
    created_at INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (log_id),
    KEY target_history (target_id, created_at),
    KEY admin_history (admin_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

foreach($schema as $sql){
    if(!$mysqli->query($sql)){
        error_log('DSG Admin User Manager schema error: ' . $mysqli->error);
        die('DSG Admin User Manager: unable to create required database tables.');
    }
}

// CodyChat's handleAddonsActivation() reads this array and inserts the addon row itself.
// Do not insert boom_addons here, otherwise the addon can be registered twice.
$ad = array(
    'name' => 'DSG_Admin_User_Manager',
    'access' => 70,
    'load' => 1,
    'custom1' => '0',
    'custom2' => '',
    'custom3' => '',
    'custom4' => '',
    'custom5' => '',
    'custom6' => '',
    'custom7' => '',
    'custom8' => '',
    'custom9' => '',
    'custom10' => '',
);
?>
