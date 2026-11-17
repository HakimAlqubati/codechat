<?php
if(!defined('BOOM')){
    die();
}
$ad = array(
    'name' => 'private_status',
    'access' => 0,
    'custom1' => 1, // master enable
    'custom2' => 1, // typing indicator
    'custom3' => 1, // online now
    'custom4' => 1, // last seen
    'custom5' => 1, // delivered double check
    'custom6' => 1, // blue read checks
    'custom7' => 1, // read label
    'custom8' => 'all', // presence visibility: all or friends
    'custom9' => 0, // minimum viewer rank
);
if(isset($mysqli) && $mysqli instanceof mysqli){
    $mysqli->query("CREATE TABLE IF NOT EXISTS `boom_private_typing` (
        `user_id` INT NOT NULL,
        `target_id` INT NOT NULL,
        `updated_at` INT NOT NULL DEFAULT 0,
        PRIMARY KEY (`user_id`, `target_id`),
        KEY `target_updated` (`target_id`, `updated_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
?>
