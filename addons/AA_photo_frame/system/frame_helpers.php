<?php
if (!defined('BOOM')) {
    die();
}

if (!function_exists('aaFrameFiles')) {
    function aaFrameFiles() {
        $dir = dirname(__DIR__) . '/files/frame';
        $files = array();
        foreach (glob($dir . '/*') ?: array() as $path) {
            if (!is_file($path)) {
                continue;
            }
            $name = basename($path);
            if ($name !== 'em.png' && preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]{0,95}\.(?:png|jpe?g|gif|webp)\z/i', $name)) {
                $files[] = $name;
            }
        }
        natcasesort($files);
        return array_values($files);
    }
}

if (!function_exists('aaSafeFrame')) {
    function aaSafeFrame($name) {
        $name = basename((string)$name);
        if ($name === '' || $name === 'em.png' || !preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]{0,95}\.(?:png|jpe?g|gif|webp)\z/i', $name)) {
            return false;
        }
        $path = dirname(__DIR__) . '/files/frame/' . $name;
        return is_file($path) ? $name : false;
    }
}

if (!function_exists('aaFrameScale')) {
    function aaFrameScale() {
        global $mysqli;
        $scale = 116;
        $result = $mysqli->query("SELECT frame_scale FROM photo_frame_settings WHERE setting_id = 1 LIMIT 1");
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $scale = (int)$row['frame_scale'];
        }
        return max(90, min(180, $scale));
    }
}

if (!function_exists('aaFrameRankRule')) {
    function aaFrameRankRule($rank) {
        global $mysqli;
        $rank = (int)$rank;
        $result = $mysqli->query("SELECT rule_id, rank_id, frame_name FROM photo_frame_rank_rules WHERE rank_id = '$rank' LIMIT 1");
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        return false;
    }
}

if (!function_exists('aaFrameUserRank')) {
    function aaFrameUserRank($userId) {
        global $mysqli;
        $userId = (int)$userId;
        $result = $mysqli->query("SELECT user_rank FROM boom_users WHERE user_id = '$userId' LIMIT 1");
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return (int)$row['user_rank'];
        }
        return false;
    }
}

if (!function_exists('aaFrameLockedUser')) {
    function aaFrameLockedUser($userId) {
        $rank = aaFrameUserRank($userId);
        if ($rank === false) {
            return false;
        }
        return aaFrameRankRule($rank);
    }
}

if (!function_exists('aaFrameImageUrl')) {
    function aaFrameImageUrl($name) {
        $safe = aaSafeFrame($name);
        if (!$safe) {
            return '';
        }
        return 'addons/AA_photo_frame/files/frame/' . rawurlencode($safe);
    }
}

if (!function_exists('aaEnsureFrameSchema')) {
    function aaEnsureFrameSchema() {
        global $mysqli;
        $mysqli->query("CREATE TABLE IF NOT EXISTS `photo_frames` (
            `frame_id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `frame_name` varchar(100) NOT NULL,
            PRIMARY KEY (`frame_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
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
            $mysqli->query("DELETE older FROM photo_frames older INNER JOIN photo_frames newer ON older.user_id = newer.user_id AND older.frame_id < newer.frame_id");
            $mysqli->query("ALTER TABLE photo_frames ADD UNIQUE KEY uniq_photo_frame_user (user_id)");
        }
    }
}

if (!function_exists('aaFrameStaffAccess')) {
    function aaFrameStaffAccess($addonData = null) {
        if ($addonData === null) {
            global $addons;
            $addonData = $addons;
        }
        $rank = isset($addonData['custom1']) ? (int)$addonData['custom1'] : 70;
        return in_array($rank, array(70, 80, 90, 100), true) ? $rank : 70;
    }
}
?>
