<?php
$load_addons = 'finger_print';
require_once('../../../system/config_session.php');
require_once(__DIR__ . '/runtime.php');
header('Content-Type: application/json; charset=utf-8');

if (!fpAddonIsActive() || empty($data['user_id']) || !isset($_POST['fingerprint'])) {
    echo json_encode(array('status' => 'error'));
    exit;
}

$fingerprint = strtolower(trim((string) $_POST['fingerprint']));
if (!preg_match('/^[a-f0-9]{16,128}$/', $fingerprint)) {
    echo json_encode(array('status' => 'invalid'));
    exit;
}

$stmt = $mysqli->prepare("UPDATE boom_users SET parmakizi = ? WHERE user_id = ?");
if (!$stmt) {
    echo json_encode(array('status' => 'error'));
    exit;
}
$userId = (int) $data['user_id'];
$stmt->bind_param('si', $fingerprint, $userId);
$updated = $stmt->execute();
$stmt->close();
if (!$updated) {
    echo json_encode(array('status' => 'error'));
    exit;
}
// userDetails/getUserSession may be served from CodyChat's Redis cache.
if (function_exists('redisUpdateUser')) { redisUpdateUser($userId); }
$data['parmakizi'] = $fingerprint;
$state = fpEvaluateFingerprint($fingerprint);
if ($state === 'cemetery') {
    echo json_encode(array('status' => 'cemetery', 'message' => 'بصمة هذا المتصفح موجودة في المقبرة'));
    exit;
}
if ($state === 'jail') {
    echo json_encode(array('status' => 'jail', 'room' => fpJailRoomId(), 'message' => 'تم نقلك إلى روم السجن'));
    exit;
}

$vpnDetected = fpLogVpnIfDetected($fingerprint, $data);
fpHistory('fingerprint_seen', $fingerprint, $userId, $data['user_name'], 0, 'النظام', getIp(), 'تم تحديث بصمة المتصفح');
echo json_encode(array(
    'status' => 'normal',
    'vpn' => $vpnDetected ? 1 : 0,
    'message' => $vpnDetected ? 'تم رصد VPN/Proxy وتسجيله دون منع الدخول' : 'ok'
));
exit;
?>
