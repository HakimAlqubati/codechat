CREATE TABLE IF NOT EXISTS rank_control_settings (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    primary_owner_id INT UNSIGNED NOT NULL,
    created_at INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rank_control_ranks (
    rank_value TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    rank_name VARCHAR(80) NOT NULL,
    rank_icon VARCHAR(100) NOT NULL DEFAULT 'user.svg',
    rank_group ENUM('member','staff') NOT NULL DEFAULT 'member',
    created_by INT UNSIGNED NOT NULL DEFAULT 0,
    created_at INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rank_control_permissions (
    rank_value TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    can_promote TINYINT(1) NOT NULL DEFAULT 0,
    updated_by INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rank_control_owners (
    user_id INT UNSIGNED NOT NULL PRIMARY KEY,
    previous_rank SMALLINT NOT NULL DEFAULT 1,
    granted_by INT UNSIGNED NOT NULL DEFAULT 0,
    granted_at INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rank_control_audit (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
