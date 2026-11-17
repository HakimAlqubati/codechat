<?php
/* Common, permission-checked handler for editable add-on menu labels and official bot names. */
$load_addons = 'purchase_store';
require_once __DIR__ . '/config_addons.php';
header('Content-Type: application/json; charset=utf-8');

function addonLabelReply($ok, $message){
    echo json_encode(array('code' => $ok ? 1 : 0, 'message' => $message), JSON_UNESCAPED_UNICODE);
    exit;
}
if(!canManageAddons()){
    addonLabelReply(false, 'ليست لديك صلاحية إدارة الإضافات.');
}
require_once __DIR__ . '/addon_menu_labels.php';
$action = (string)($_POST['action'] ?? '');

if($action === 'save_menu_label'){
    $allowed = array(
        'purchase_store', 'vip', 'amz_profile_store', 'AA_photo_frame',
        'AA_userlist_bg', 'quizbot', 'chat_alerts', 'Room_wall',
        'finger_print_cemetery', 'finger_print_jail', 'finger_print_vpn', 'finger_print_history', 'finger_print_exceptions'
    );
    $addon = preg_replace('/[^A-Za-z0-9_]/', '', (string)($_POST['addon'] ?? ''));
    $label = trim(strip_tags((string)($_POST['label'] ?? '')));
    $length = function_exists('mb_strlen') ? mb_strlen($label, 'UTF-8') : strlen($label);
    if(!in_array($addon, $allowed, true) || $label === '' || $length > 120){
        addonLabelReply(false, 'اكتب اسمًا صحيحًا لا يتجاوز 120 حرفًا.');
    }
    if(!addonMenuLabelEnsureSchema()){
        addonLabelReply(false, 'تعذر تجهيز جدول أسماء القوائم.');
    }
    $uid = (int)$data['user_id'];
    $now = time();
    $stmt = $mysqli->prepare('INSERT INTO boom_addon_menu_labels (addon_name, menu_label, updated_by, updated_at) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE menu_label = VALUES(menu_label), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)');
    if(!$stmt){ addonLabelReply(false, 'تعذر تجهيز حفظ الاسم.'); }
    $stmt->bind_param('ssii', $addon, $label, $uid, $now);
    $ok = $stmt->execute();
    $stmt->close();
    if(!$ok){ addonLabelReply(false, 'تعذر حفظ الاسم.'); }
    if(function_exists('redisUpdateAddons')){ redisUpdateAddons($addon); }
    addonLabelReply(true, 'تم حفظ الاسم. سيظهر الاسم الجديد عند تحديث القائمة.');
}

if($action === 'save_bot_name'){
    $addon = preg_replace('/[^A-Za-z0-9_]/', '', (string)($_POST['addon'] ?? ''));
    if(!in_array($addon, array('bobby', 'quizbot'), true)){
        addonLabelReply(false, 'البوت غير معتمد لتغيير الاسم.');
    }
    $name = trim(strip_tags((string)($_POST['name'] ?? '')));
    $length = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
    if($name === '' || $length < 2 || $length > 32 || preg_match('/[<>\r\n\t]/u', $name)){
        addonLabelReply(false, 'اسم البوت يجب أن يكون بين حرفين و32 حرفًا.');
    }
    $botStmt = $mysqli->prepare('SELECT bot_id FROM boom_addons WHERE addons = ? LIMIT 1');
    if(!$botStmt){ addonLabelReply(false, 'تعذر قراءة إعداد البوت.'); }
    $botStmt->bind_param('s', $addon);
    $botStmt->execute();
    $botStmt->bind_result($botId);
    $found = $botStmt->fetch();
    $botStmt->close();
    $botId = (int)$botId;
    if(!$found || $botId <= 0){ addonLabelReply(false, 'لم يتم العثور على حساب البوت. ثبّت الإضافة أولاً.'); }
    $dup = $mysqli->prepare('SELECT user_id FROM boom_users WHERE user_name = ? AND user_id <> ? LIMIT 1');
    if(!$dup){ addonLabelReply(false, 'تعذر التحقق من الاسم.'); }
    $dup->bind_param('si', $name, $botId);
    $dup->execute();
    $dup->store_result();
    $exists = $dup->num_rows > 0;
    $dup->close();
    if($exists){ addonLabelReply(false, 'هذا الاسم مستخدم من حساب آخر. اختر اسمًا مختلفًا.'); }
    $botCheck = $mysqli->prepare('SELECT user_bot FROM boom_users WHERE user_id = ? LIMIT 1');
    if(!$botCheck){ addonLabelReply(false, 'تعذر التحقق من حساب البوت.'); }
    $botCheck->bind_param('i', $botId);
    $botCheck->execute();
    $botCheck->bind_result($botType);
    $botExists = $botCheck->fetch();
    $botCheck->close();
    if(!$botExists || (int)$botType <= 0){ addonLabelReply(false, 'الحساب المرتبط بالإضافة ليس حساب بوت صالحًا.'); }

    if(!$mysqli->begin_transaction()){ addonLabelReply(false, 'تعذر بدء عملية التحديث.'); }
    $userUpdate = $mysqli->prepare('UPDATE boom_users SET user_name = ? WHERE user_id = ? AND user_bot > 0');
    $addonUpdate = $mysqli->prepare('UPDATE boom_addons SET bot_name = ? WHERE addons = ?');
    if(!$userUpdate || !$addonUpdate){
        if($userUpdate){ $userUpdate->close(); }
        if($addonUpdate){ $addonUpdate->close(); }
        $mysqli->rollback();
        addonLabelReply(false, 'تعذر تجهيز تحديث اسم البوت.');
    }
    $userUpdate->bind_param('si', $name, $botId);
    $okUser = $userUpdate->execute();
    $userUpdate->close();
    $addonUpdate->bind_param('ss', $name, $addon);
    $okAddon = $addonUpdate->execute();
    $addonUpdate->close();
    if(!$okUser || !$okAddon || !$mysqli->commit()){
        $mysqli->rollback();
        addonLabelReply(false, 'لم يتم حفظ اسم البوت.');
    }
    if(function_exists('redisUpdateUser')){ redisUpdateUser($botId); }
    if(function_exists('redisUpdateAddons')){ redisUpdateAddons($addon); }
    addonLabelReply(true, 'تم تغيير اسم البوت في الحساب الرسمي وبيانات الإضافة.');
}

addonLabelReply(false, 'طلب غير معروف.');
?>
