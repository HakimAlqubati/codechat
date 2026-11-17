<?php
$load_addons = 'DSG_Admin_User_Manager';
require_once('../../../system/config_addons.php');

// Only the platform's addon managers may change the addon's access setting.
if(isset($_POST['set_DSG_Admin_User_Manager_access'])){
    if(!canManageAddons()){
        echo boomCode(0, array('error' => 'غير مصرح لك بتغيير إعدادات الإضافة.'));
        exit;
    }
    $rank = filter_var($_POST['set_DSG_Admin_User_Manager_access'], FILTER_VALIDATE_INT);
    if($rank === false || $rank < 1 || $rank > 100 || !dsgSaveAddonAccess($rank)){
        echo boomCode(0, array('error' => 'تعذر حفظ إعدادات الصلاحيات.'));
        exit;
    }
    echo boomCode(1, array('message' => 'تم حفظ إعدادات الصلاحيات بنجاح.'));
    exit;
}

if(!isset($_POST['action']) || $_POST['action'] !== 'save_user_stat'){
    echo boomCode(0, array('error' => 'طلب غير صالح.'));
    exit;
}

if(!dsgCanAccessManager()){
    echo boomCode(0, array('error' => 'ليست لديك صلاحية استخدام إدارة الأعضاء.'));
    exit;
}
if(!dsgAdminTableExists('boom_dsg_user_manager_logs')){
    echo boomCode(0, array('error' => 'قاعدة بيانات الإضافة تحتاج إلى التحديث. شغّل upgrade_v2.sql أولاً.'));
    exit;
}

$userId = isset($_POST['user_id']) ? filter_var($_POST['user_id'], FILTER_VALIDATE_INT) : false;
$stat = isset($_POST['stat']) ? (string)$_POST['stat'] : '';
$method = isset($_POST['method']) ? (string)$_POST['method'] : '';
$valueRaw = isset($_POST['value']) ? trim((string)$_POST['value']) : '';
$reason = isset($_POST['reason']) ? trim(strip_tags((string)$_POST['reason'])) : '';
$reason = mb_substr($reason, 0, 255, 'UTF-8');

if($userId === false || $userId <= 0){
    echo boomCode(0, array('error' => 'رقم العضو غير صالح.'));
    exit;
}
if(!in_array($stat, array('gold', 'ruby', 'level', 'likes'), true)){
    echo boomCode(0, array('error' => 'نوع البيانات المطلوب تعديله غير صالح.'));
    exit;
}
if(!in_array($method, array('set', 'adjust'), true)){
    echo boomCode(0, array('error' => 'طريقة التعديل غير صالحة.'));
    exit;
}
if($valueRaw === '' || !preg_match('/^[+-]?[0-9]{1,19}$/D', $valueRaw)){
    echo boomCode(0, array('error' => 'أدخل رقماً صحيحاً بدون كسور.'));
    exit;
}

// Keep values inside JavaScript's exact integer range as the modal uses number inputs.
$digitsOnly = ltrim($valueRaw, '+-');
$normalizedDigits = ltrim($digitsOnly, '0');
if($normalizedDigits === ''){
    $normalizedDigits = '0';
}
$maxSafeInteger = '9007199254740991';
if(strlen($normalizedDigits) > strlen($maxSafeInteger) || (strlen($normalizedDigits) === strlen($maxSafeInteger) && strcmp($normalizedDigits, $maxSafeInteger) > 0)){
    echo boomCode(0, array('error' => 'القيمة أكبر من النطاق الرقمي المسموح.'));
    exit;
}
$requestedValue = (int)$valueRaw;

$moduleEnabled = false;
if($stat === 'gold' || $stat === 'ruby'){
    $moduleEnabled = isset($setting['use_wallet']) && (int)$setting['use_wallet'] === 1;
} elseif($stat === 'level'){
    $moduleEnabled = isset($setting['use_level']) && (int)$setting['use_level'] === 1;
} elseif($stat === 'likes'){
    $moduleEnabled = isset($setting['use_like']) && (int)$setting['use_like'] === 1 && dsgAdminTableExists('boom_dsg_admin_likes');
}
if(!$moduleEnabled){
    echo boomCode(0, array('error' => 'النظام المرتبط بهذه الخاصية غير مفعّل أو لم تكتمل تهيئته.'));
    exit;
}

if(!$mysqli->begin_transaction()){
    echo boomCode(0, array('error' => 'تعذر بدء عملية التعديل. حاول مرة أخرى.'));
    exit;
}

try {
    // Lock the target row and re-check the target's live rank and status.
    $targetStmt = $mysqli->prepare('SELECT user_id, user_name, user_language, user_rank, user_bot, user_delete, user_gold, user_ruby, user_level FROM boom_users WHERE user_id = ? LIMIT 1 FOR UPDATE');
    if(!$targetStmt){
        throw new Exception('تعذر قراءة بيانات العضو.');
    }
    $targetStmt->bind_param('i', $userId);
    if(!$targetStmt->execute()){
        $targetStmt->close();
        throw new Exception('تعذر قراءة بيانات العضو.');
    }
    $targetResult = $targetStmt->get_result();
    $target = $targetResult ? $targetResult->fetch_assoc() : null;
    $targetStmt->close();

    if(!$target){
        throw new Exception('العضو غير موجود.');
    }
    if(!dsgCanEditManagedUser($target)){
        throw new Exception('لا تملك صلاحية تعديل هذا العضو. لا يمكن تعديل حساب برتبة مساوية أو أعلى من رتبتك أو حساب إداري محمي.');
    }

    $statLabels = array(
        'gold' => array('ar' => 'الذهب', 'en' => 'gold'),
        'ruby' => array('ar' => 'الياقوت', 'en' => 'ruby'),
        'level' => array('ar' => 'المستوى', 'en' => 'level'),
        'likes' => array('ar' => 'الإعجابات الإضافية', 'en' => 'administrator bonus likes'),
    );

    if($stat === 'likes'){
        // These are administrator-granted bonus likes, kept separate from both
        // organic likes (boom_pro_like) and purchased likes (Purchase Store).
        $insertLike = $mysqli->prepare('INSERT IGNORE INTO boom_dsg_admin_likes (user_id, bonus_likes) VALUES (?, 0)');
        if(!$insertLike){
            throw new Exception('تعذر تهيئة رصيد الإعجابات الإضافية.');
        }
        $insertLike->bind_param('i', $userId);
        if(!$insertLike->execute()){
            $insertLike->close();
            throw new Exception('تعذر تهيئة رصيد الإعجابات الإضافية.');
        }
        $insertLike->close();

        $likeStmt = $mysqli->prepare('SELECT bonus_likes FROM boom_dsg_admin_likes WHERE user_id = ? LIMIT 1 FOR UPDATE');
        if(!$likeStmt){
            throw new Exception('تعذر قراءة رصيد الإعجابات الإضافية.');
        }
        $likeStmt->bind_param('i', $userId);
        $likeStmt->execute();
        $likeResult = $likeStmt->get_result();
        $likeRow = $likeResult ? $likeResult->fetch_assoc() : null;
        $likeStmt->close();
        $oldValue = $likeRow ? (int)$likeRow['bonus_likes'] : 0;
        $maxValue = PHP_INT_MAX;
    } else {
        $column = array('gold' => 'user_gold', 'ruby' => 'user_ruby', 'level' => 'user_level')[$stat];
        $oldValue = (int)$target[$column];
        $maxValue = 2147483647; // CodyChat stores these fields as signed INT.
    }

    if($method === 'set'){
        $newValue = $requestedValue;
        if($newValue < 0){
            throw new Exception('لا يمكن تعيين قيمة سالبة.');
        }
    } else {
        if($requestedValue === 0){
            throw new Exception('أدخل مقداراً غير صفري للإضافة أو الخصم.');
        }
        if(($requestedValue > 0 && $oldValue > $maxValue - $requestedValue) || ($requestedValue < 0 && $oldValue < -$requestedValue)){
            throw new Exception('القيمة الناتجة خارج النطاق المسموح أو ستجعل الرصيد سالباً.');
        }
        $newValue = $oldValue + $requestedValue;
    }

    if($stat === 'level' && $newValue < 1){
        throw new Exception('يجب ألا يقل المستوى عن 1.');
    }
    if($newValue < 0 || $newValue > $maxValue){
        throw new Exception('القيمة الناتجة خارج النطاق المسموح.');
    }
    if($newValue === $oldValue){
        $mysqli->commit();
        echo boomCode(1, array('message' => 'القيمة لم تتغير؛ لم يتم إنشاء سجل تعديل جديد.'));
        exit;
    }

    if($stat === 'likes'){
        $newString = (string)$newValue;
        $updateStmt = $mysqli->prepare('UPDATE boom_dsg_admin_likes SET bonus_likes = ? WHERE user_id = ?');
        if(!$updateStmt){
            throw new Exception('تعذر حفظ الإعجابات الإضافية.');
        }
        $updateStmt->bind_param('si', $newString, $userId);
    } else {
        $updateStmt = $mysqli->prepare("UPDATE boom_users SET {$column} = ? WHERE user_id = ?");
        if(!$updateStmt){
            throw new Exception('تعذر تجهيز تحديث بيانات العضو.');
        }
        $updateStmt->bind_param('ii', $newValue, $userId);
    }
    if(!$updateStmt->execute()){
        $updateStmt->close();
        throw new Exception('فشل حفظ التعديل في قاعدة البيانات.');
    }
    $updateStmt->close();

    $changeAmount = $newValue - $oldValue;
    $operation = ($method === 'set') ? 'set' : ($changeAmount > 0 ? 'add' : 'subtract');
    $oldString = (string)$oldValue;
    $newString = (string)$newValue;
    $changeString = (string)$changeAmount;
    $createdAt = time();
    $logStmt = $mysqli->prepare('INSERT INTO boom_dsg_user_manager_logs (admin_id, target_id, stat_key, operation, old_value, new_value, change_amount, reason, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    if(!$logStmt){
        throw new Exception('تعذر إنشاء سجل التعديل. تم إلغاء العملية لحماية سجل الإدارة.');
    }
    $adminId = (int)$data['user_id'];
    $logStmt->bind_param('iissssssi', $adminId, $userId, $stat, $operation, $oldString, $newString, $changeString, $reason, $createdAt);
    if(!$logStmt->execute()){
        $logStmt->close();
        throw new Exception('تعذر حفظ سجل التعديل. تم إلغاء العملية.');
    }
    $logStmt->close();

    if(!$mysqli->commit()){
        throw new Exception('تعذر اعتماد العملية في قاعدة البيانات.');
    }
} catch (Exception $e) {
    $mysqli->rollback();
    echo boomCode(0, array('error' => $e->getMessage()));
    exit;
}

// Refresh CodyChat's user caches only after the database transaction commits.
if(function_exists('redisUpdateUser')){
    redisUpdateUser($userId);
}
if(function_exists('redisUpdateChatUser')){
    redisUpdateChatUser($userId);
}

// Use CodyChat's native notification table and counter.
$arabicTarget = isset($target['user_language']) && stripos($target['user_language'], 'Arabic') === 0;
$label = $statLabels[$stat][$arabicTarget ? 'ar' : 'en'];
$oldDisplay = number_format($oldValue);
$newDisplay = number_format($newValue);
$reasonText = ($reason !== '') ? $reason : ($arabicTarget ? 'لم يحدد' : 'not specified');
if($arabicTarget){
    $notifyText = 'تم تعديل ' . $label . ' في حسابك من ' . $oldDisplay . ' إلى ' . $newDisplay . '. السبب: ' . $reasonText;
} else {
    $notifyText = 'Your ' . $label . ' were updated from ' . $oldDisplay . ' to ' . $newDisplay . '. Reason: ' . $reasonText;
}
$notifyIcon = array('gold' => 'gold', 'ruby' => 'ruby', 'level' => 'level', 'likes' => 'plike')[$stat];
if(function_exists('boomNotify')){
    boomNotify('custom', array(
        'hunter' => (int)$data['user_id'],
        'target' => $userId,
        'source' => 'dsg_admin_user_manager',
        'sourceid' => $userId,
        'custom' => escape($notifyText),
        'icon' => $notifyIcon
    ));
}

$message = $arabicTarget
    ? 'تم تحديث ' . $label . ' للعضو ' . $target['user_name'] . ' بنجاح. القيمة السابقة: ' . $oldDisplay . '، القيمة الجديدة: ' . $newDisplay . '.'
    : 'Updated ' . $target['user_name'] . "'s " . $label . ' successfully. Previous value: ' . $oldDisplay . ', new value: ' . $newDisplay . '.';

echo boomCode(1, array('message' => $message, 'old_value' => $oldString, 'new_value' => $newString));
?>
