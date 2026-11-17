-- DSG Admin User Manager v2 database upgrade.
-- Run once in the CodyChat database when upgrading an already-installed v1 addon.
-- New installations create these tables automatically through system/install.php.

CREATE TABLE IF NOT EXISTS boom_dsg_admin_likes (
    user_id INT UNSIGNED NOT NULL,
    bonus_likes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS boom_dsg_user_manager_logs (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
