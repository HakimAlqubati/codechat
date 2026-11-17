<?php
$load_addons = 'finger_print';
require_once('../../../system/config_addons.php');
if (!boomAllow(100)) { echo '0'; exit; }

// Re-run the idempotent installer so existing installations of the original
// fingerprint add-on receive the new jail, history, VPN and exception tables
// without deleting their existing cemetery records.
ob_start();
require(__DIR__ . '/install.php');
ob_end_clean();

$requiredTables = array('boom_fp_settings','boom_fp_jail','boom_fp_history','boom_fp_vpn_log','boom_fp_exceptions','boom_permabanned');
$allReady = true;
foreach ($requiredTables as $table) {
    $safeTable = $mysqli->real_escape_string($table);
    $check = $mysqli->query("SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '$safeTable'");
    if (!$check || (int)$check->fetch_assoc()['c'] < 1) { $allReady = false; break; }
}
$column = $mysqli->query("SHOW COLUMNS FROM boom_users LIKE 'parmakizi'");
if (!$column || $column->num_rows < 1) { $allReady = false; }
$room = $mysqli->query("SELECT jail_room_id FROM boom_fp_settings WHERE id = 1 LIMIT 1");
if (!$room || $room->num_rows < 1 || (int)$room->fetch_assoc()['jail_room_id'] < 1) { $allReady = false; }
if (function_exists('redisFlushAll')) { redisFlushAll(); }
echo $allReady ? '1' : '0';
exit;
?>
