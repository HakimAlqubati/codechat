<?php
if (!defined('BOOM')) { die(); }

// Move any occupants out before removing the add-on's dedicated prison room.
$settingsResult = $mysqli->query("SELECT jail_room_id FROM boom_fp_settings WHERE id = 1");
if ($settingsResult && $settingsResult->num_rows > 0) {
    $jailRoom = (int) $settingsResult->fetch_assoc()['jail_room_id'];
    if ($jailRoom > 0) {
        $mysqli->query("UPDATE boom_users SET user_roomid = 1, user_role = 0, user_action = user_action + 1 WHERE user_roomid = '$jailRoom'");
        $mysqli->query("DELETE FROM boom_room_action WHERE action_room = '$jailRoom'");
        $mysqli->query("DELETE FROM boom_room_staff WHERE room_id = '$jailRoom'");
        $mysqli->query("DELETE FROM boom_rooms WHERE room_id = '$jailRoom'");
    }
}
$mysqli->query("DROP TABLE IF EXISTS boom_fp_exceptions");
$mysqli->query("DROP TABLE IF EXISTS boom_fp_vpn_log");
$mysqli->query("DROP TABLE IF EXISTS boom_fp_history");
$mysqli->query("DROP TABLE IF EXISTS boom_fp_jail");
$mysqli->query("DROP TABLE IF EXISTS boom_fp_settings");
$mysqli->query("DROP TABLE IF EXISTS boom_permabanned");
$column = $mysqli->query("SHOW COLUMNS FROM boom_users LIKE 'parmakizi'");
if ($column && $column->num_rows > 0) {
    $mysqli->query("ALTER TABLE boom_users DROP COLUMN parmakizi");
}
// Clear cached user/room data after removing the fingerprint column and jail room.
if (function_exists('redisFlushAll')) { redisFlushAll(); }
?>
