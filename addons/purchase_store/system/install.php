<?php
if(!defined('BOOM') || !isset($mysqli)){
    die();
}

$tables = array();
$tables[] = "CREATE TABLE IF NOT EXISTS boom_purchase_store_settings (
    id TINYINT UNSIGNED NOT NULL,
    level_enabled TINYINT(1) NOT NULL DEFAULT 1,
    level_min_rank SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    likes_enabled TINYINT(1) NOT NULL DEFAULT 0,
    likes_min_rank SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
$tables[] = "CREATE TABLE IF NOT EXISTS boom_purchase_store_products (
    product_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    service VARCHAR(12) NOT NULL,
    title VARCHAR(100) NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    price INT UNSIGNED NOT NULL,
    currency TINYINT UNSIGNED NOT NULL DEFAULT 1,
    min_rank SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    allow_self TINYINT(1) NOT NULL DEFAULT 1,
    allow_gift TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (product_id),
    KEY service_enabled_order (service, enabled, sort_order),
    KEY min_rank (min_rank)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
$tables[] = "CREATE TABLE IF NOT EXISTS boom_purchase_store_likes (
    user_id INT UNSIGNED NOT NULL,
    bonus_likes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
$tables[] = "CREATE TABLE IF NOT EXISTS boom_purchase_store_orders (
    order_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    buyer_id INT UNSIGNED NOT NULL,
    recipient_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    service VARCHAR(12) NOT NULL,
    product_title VARCHAR(100) NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    price INT UNSIGNED NOT NULL,
    currency TINYINT UNSIGNED NOT NULL,
    created_at INT UNSIGNED NOT NULL,
    request_key CHAR(64) NULL DEFAULT NULL,
    PRIMARY KEY (order_id),
    UNIQUE KEY unique_request_key (request_key),
    KEY buyer_id (buyer_id),
    KEY recipient_id (recipient_id),
    KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

foreach($tables as $sql){
    if(!$mysqli->query($sql)){
        echo boomError('error');
        exit;
    }
}

// Safe schema upgrade for installations created by an earlier preview build.
$columnCheck = $mysqli->query("SHOW COLUMNS FROM boom_purchase_store_orders LIKE 'request_key'");
if($columnCheck && $columnCheck->num_rows === 0){
    if(!$mysqli->query("ALTER TABLE boom_purchase_store_orders ADD request_key CHAR(64) NULL DEFAULT NULL")){
        echo boomError('error');
        exit;
    }
}
$indexCheck = $mysqli->query("SHOW INDEX FROM boom_purchase_store_orders WHERE Key_name = 'unique_request_key'");
if($indexCheck && $indexCheck->num_rows === 0){
    if(!$mysqli->query("ALTER TABLE boom_purchase_store_orders ADD UNIQUE KEY unique_request_key (request_key)")){
        echo boomError('error');
        exit;
    }
}

$mysqli->query("INSERT IGNORE INTO boom_purchase_store_settings (id, level_enabled, level_min_rank, likes_enabled, likes_min_rank) VALUES (1, 1, 1, 0, 1)");

$count = $mysqli->query("SELECT COUNT(*) AS total FROM boom_purchase_store_products WHERE service = 'level'");
if($count){
    $row = $count->fetch_assoc();
    if((int)$row['total'] === 0){
        $now = time();
        $seed = array(
            array('مستوى (1)', 1, 30000, 0),
            array('مستوى (5)', 5, 140000, 1),
            array('مستوى (10)', 10, 270000, 2),
            array('مستوى (50)', 50, 1250000, 3),
            array('مستوى (100)', 100, 2400000, 4),
        );
        $stmt = $mysqli->prepare("INSERT INTO boom_purchase_store_products (service, title, quantity, price, currency, min_rank, enabled, allow_self, allow_gift, sort_order, created_at) VALUES ('level', ?, ?, ?, 1, 1, 1, 1, 1, ?, ?)");
        if($stmt){
            foreach($seed as $item){
                $title = $item[0];
                $quantity = $item[1];
                $price = $item[2];
                $order = $item[3];
                $stmt->bind_param('siiii', $title, $quantity, $price, $order, $now);
                $stmt->execute();
            }
            $stmt->close();
        }
    }
}

$ad = array(
    'name' => 'purchase_store',
    'access' => 1,
    'load' => 0,
    'custom1' => '',
    'custom2' => '',
    'custom3' => '',
);
?>
