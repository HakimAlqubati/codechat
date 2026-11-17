<?php
$load_addons = 'rank_control_blk';
require(__DIR__ . '/../../../system/config_addons.php');

header('Content-Type: application/json; charset=utf-8');

function rcReply($ok, $message){
    echo json_encode(['ok' => (bool)$ok, 'message' => (string)$message], JSON_UNESCAPED_UNICODE);
    exit;
}

function rcPrimaryGuard(){
    if(!rankControlIsActive() || !rankControlIsPrimaryOwner() || empty($GLOBALS['data']['user_rank']) || (int)$GLOBALS['data']['user_rank'] !== 100){
        rcReply(false, 'هذه العملية متاحة للمالك الرئيسي فقط.');
    }
}

function rcPasswordGuard($password){
    if(!is_string($password) || $password === '' || !userPassword($password)){
        rcReply(false, 'كلمة مرور المالك الرئيسي غير صحيحة.');
    }
}

function rcValidCustomRank($rank){
    $rank = (int)$rank;
    return ($rank >= 2 && $rank <= 95 && !in_array($rank, rankControlReservedNumbers(), true));
}

function rcRankAudit($action, $target, $old, $new, $details){
    rankControlWriteAudit($action, $target, $old, $new, $details);
}

$action = isset($_POST['action']) ? (string)$_POST['action'] : '';

if($action === 'save_rank'){
    rcPrimaryGuard();
    $rank = isset($_POST['rank_value']) && ctype_digit((string)$_POST['rank_value']) ? (int)$_POST['rank_value'] : 0;
    $name = trim(strip_tags((string)($_POST['rank_name'] ?? '')));
    $icon = basename((string)($_POST['rank_icon'] ?? 'user.svg'));
    $group = (string)($_POST['rank_group'] ?? 'member');
    if(!rcValidCustomRank($rank)){
        rcReply(false, 'اختر رقماً متاحاً من 2 إلى 95. الأرقام النظامية لا يمكن استخدامها لإنشاء رتبة جديدة.');
    }
    $nameLength = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
    if($name === '' || $nameLength > 240){
        rcReply(false, 'اكتب اسماً للرتبة لا يتجاوز 80 حرفاً.');
    }
    $iconPath = BOOM_PATH . '/default_images/rank/' . $icon;
    if(!preg_match('/^[a-zA-Z0-9_.-]+$/', $icon) || !is_file($iconPath)){
        rcReply(false, 'الأيقونة المختارة غير موجودة في مجلد أيقونات الرتب.');
    }
    $expectedGroup = $rank >= 70 ? 'staff' : 'member';
    if($group !== $expectedGroup){
        rcReply(false, $rank >= 70 ? 'الرتب من 70 إلى 95 تصنف إدارية في نظام الشات.' : 'الأرقام الأقل من 70 تصنف رتب أعضاء.');
    }
    $now = time();
    $actor = (int)$data['user_id'];
    $stmt = $mysqli->prepare("INSERT INTO rank_control_ranks (rank_value, rank_name, rank_icon, rank_group, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE rank_name = VALUES(rank_name), rank_icon = VALUES(rank_icon), rank_group = VALUES(rank_group), updated_at = VALUES(updated_at)");
    if(!$stmt){ rcReply(false, 'تعذر تجهيز حفظ الرتبة.'); }
    $stmt->bind_param('isssiii', $rank, $name, $icon, $group, $actor, $now, $now);
    $ok = $stmt->execute();
    $stmt->close();
    if(!$ok){ rcReply(false, 'تعذر حفظ الرتبة في قاعدة البيانات.'); }
    rcRankAudit('rank_save', 0, null, $rank, $name);
    rankControlCache(true);
    rcReply(true, 'تم حفظ الرتبة بنجاح.');
}

if($action === 'delete_rank'){
    rcPrimaryGuard();
    $rank = isset($_POST['rank_value']) && ctype_digit((string)$_POST['rank_value']) ? (int)$_POST['rank_value'] : 0;
    if(!rcValidCustomRank($rank)){
        rcReply(false, 'لا يمكن حذف رتبة نظامية أو رقم غير صالح.');
    }
    $count = $mysqli->query("SELECT COUNT(*) AS total FROM boom_users WHERE user_rank = '$rank'");
    $row = $count ? $count->fetch_assoc() : ['total' => 0];
    if((int)$row['total'] > 0){
        rcReply(false, 'لا يمكن حذف الرتبة لأن هناك أعضاء يستخدمونها. انقلهم إلى رتب أخرى أولاً.');
    }
    $mysqli->query("DELETE FROM rank_control_permissions WHERE rank_value = '$rank'");
    $mysqli->query("DELETE FROM rank_control_ranks WHERE rank_value = '$rank'");
    rcRankAudit('rank_delete', 0, $rank, null, 'Deleted unused custom rank');
    rankControlCache(true);
    rcReply(true, 'تم حذف الرتبة.');
}

if($action === 'set_permission'){
    rcPrimaryGuard();
    $rank = isset($_POST['rank_value']) && ctype_digit((string)$_POST['rank_value']) ? (int)$_POST['rank_value'] : 0;
    $enabled = isset($_POST['enabled']) && (string)$_POST['enabled'] === '1' ? 1 : 0;
    if($rank < 1 || $rank > 95 || $rank === 69 || !in_array($rank, rankList(), true)){
        rcReply(false, 'هذه الرتبة غير متاحة لتفويض الترقية.');
    }
    $actor = (int)$data['user_id'];
    $now = time();
    $stmt = $mysqli->prepare("INSERT INTO rank_control_permissions (rank_value, can_promote, updated_by, updated_at) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE can_promote = VALUES(can_promote), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)");
    if(!$stmt){ rcReply(false, 'تعذر حفظ صلاحية الترقية.'); }
    $stmt->bind_param('iiii', $rank, $enabled, $actor, $now);
    $ok = $stmt->execute();
    $stmt->close();
    if(!$ok){ rcReply(false, 'تعذر حفظ صلاحية الترقية.'); }
    rcRankAudit('promotion_permission', 0, null, $rank, $enabled ? 'Enabled promotion permission' : 'Disabled promotion permission');
    rankControlCache(true);
    rcReply(true, $enabled ? 'تم تفعيل صلاحية الترقية لهذه الرتبة.' : 'تم إيقاف صلاحية الترقية لهذه الرتبة.');
}

if($action === 'grant_owner'){
    rcPrimaryGuard();
    rcPasswordGuard((string)($_POST['owner_password'] ?? ''));
    $lookupType = (string)($_POST['lookup_type'] ?? '');
    $lookupValue = trim((string)($_POST['lookup_value'] ?? ''));
    $lookup = rankControlLookupUser($lookupType, $lookupValue);
    if($lookup['status'] === 'multiple'){
        rcReply(false, 'بيانات البحث تطابق أكثر من حساب. استخدم معرف الحساب (User ID) لتحديد شخص واحد بدقة.');
    }
    if($lookup['status'] !== 'ok' || empty($lookup['users'][0])){
        rcReply(false, 'لم يتم العثور على حساب مطابق. تحقق من المعرف أو البريد أو IP.');
    }
    $target = $lookup['users'][0];
    $targetId = (int)$target['user_id'];
    if($targetId === (int)$data['user_id']){
        rcReply(false, 'حساب المالك الرئيسي لا يحتاج إلى تعيين.');
    }
    if((int)$target['user_rank'] === 100){
        rcReply(false, 'هذا الحساب يحمل رتبة 100 بالفعل.');
    }
    if((int)$target['user_rank'] === 0 || (int)$target['user_bot'] > 0){
        rcReply(false, 'لا يمكن تعيين حساب ضيف أو بوت كمالك.');
    }
    $previousRank = (int)$target['user_rank'];
    $actor = (int)$data['user_id'];
    $now = time();
    $stmt = $mysqli->prepare("INSERT INTO rank_control_owners (user_id, previous_rank, granted_by, granted_at) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE previous_rank = VALUES(previous_rank), granted_by = VALUES(granted_by), granted_at = VALUES(granted_at)");
    if(!$stmt){ rcReply(false, 'تعذر تسجيل المالك الجديد.'); }
    $stmt->bind_param('iiii', $targetId, $previousRank, $actor, $now);
    $ok = $stmt->execute();
    $stmt->close();
    if(!$ok){ rcReply(false, 'تعذر حفظ تعيين المالك.'); }
    $fullUser = userDetails($targetId);
    userReset($fullUser, 100);
    rcRankAudit('owner_grant', $targetId, $previousRank, 100, 'Granted by the primary owner after password confirmation');
    if(function_exists('redisFlushAll')){ redisFlushAll(); }
    rcReply(true, 'تم تعيين ' . $target['user_name'] . ' مالكاً برتبة 100.');
}

if($action === 'revoke_owner'){
    rcPrimaryGuard();
    rcPasswordGuard((string)($_POST['owner_password'] ?? ''));
    $targetId = isset($_POST['target_id']) && ctype_digit((string)$_POST['target_id']) ? (int)$_POST['target_id'] : 0;
    $cache = rankControlCache();
    $primaryId = (int)($cache['settings']['primary_owner_id'] ?? 0);
    if($targetId <= 0 || $targetId === $primaryId){
        rcReply(false, 'لا يمكن سحب رتبة المالك الرئيسي.');
    }
    $stmt = $mysqli->prepare("SELECT previous_rank FROM rank_control_owners WHERE user_id = ? LIMIT 1");
    if(!$stmt){ rcReply(false, 'تعذر قراءة سجل المالك.'); }
    $stmt->bind_param('i', $targetId);
    $stmt->execute();
    $stmt->bind_result($previousRankValue);
    $hasOwnerRow = $stmt->fetch();
    $stmt->close();
    if(!$hasOwnerRow){
        rcReply(false, 'هذا الحساب ليس مالكاً مفوضاً من خلال الإضافة.');
    }
    $target = userDetails($targetId);
    if(empty($target) || (int)$target['user_rank'] !== 100){
        rcReply(false, 'الحساب لم يعد يحمل رتبة 100.');
    }
    $restoreRank = (int)$previousRankValue;
    if($restoreRank < 1 || $restoreRank > 95 || !validRank($restoreRank) || $restoreRank === 69){
        $restoreRank = 1;
    }
    userReset($target, $restoreRank);
    $mysqli->query("DELETE FROM rank_control_owners WHERE user_id = '$targetId'");
    rcRankAudit('owner_revoke', $targetId, 100, $restoreRank, 'Revoked by the primary owner');
    if(function_exists('redisFlushAll')){ redisFlushAll(); }
    rcReply(true, 'تم سحب رتبة المالك وإعادة الحساب إلى الرتبة ' . $restoreRank . '.');
}

rcReply(false, 'طلب غير معروف.');
?>
