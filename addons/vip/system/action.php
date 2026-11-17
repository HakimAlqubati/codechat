<?php
$load_addons = 'vip';
require(__DIR__ . '/../../../system/config_addons.php');
header('Content-Type: application/json; charset=utf-8');

function vipStoreReply($ok, $message, $extra = array()){
    echo json_encode(array_merge(array('code' => $ok ? 1 : 0, 'emessage' => $message), $extra), JSON_UNESCAPED_UNICODE);
    exit;
}
function vipStorePostInt($key, $min, $max){
    if(!isset($_POST[$key]) || !is_scalar($_POST[$key]) || !preg_match('/^[0-9]+$/', (string)$_POST[$key])){ return false; }
    $value = (int)$_POST[$key];
    return ($value < $min || $value > $max) ? false : $value;
}
function vipStoreAdmin(){
    if(!canManageAddons()){ vipStoreReply(false, 'ليست لديك صلاحية إدارة متجر العضويات.'); }
}

if(!vipMembershipEnsureTables()){
    vipStoreReply(false, 'تعذر تجهيز جداول متجر العضويات.');
}

if(isset($_POST['save_offer'])){
    vipStoreAdmin();
    $id = vipStorePostInt('offer_id', 0, 2147483647);
    $rank = vipStorePostInt('rank_value', 2, 99);
    $price = vipStorePostInt('price', 0, 2000000000);
    $currency = vipStorePostInt('currency', 1, 2);
    $days = vipStorePostInt('duration_days', 0, 36500);
    $mode = vipStorePostInt('payment_mode', 1, 2);
    $cashPriceRaw = trim((string)($_POST['cash_price'] ?? '0'));
    $cashPrice = (is_numeric($cashPriceRaw) && (float)$cashPriceRaw >= 0 && (float)$cashPriceRaw <= 999999999.99) ? round((float)$cashPriceRaw, 2) : false;
    $cashCurrency = strtoupper(trim((string)($_POST['cash_currency'] ?? 'SAR')));
    if(!in_array($cashCurrency, array('SAR','USD'), true)){ $cashCurrency = false; }
    $enabled = vipStorePostInt('enabled', 0, 1);
    $sort = vipStorePostInt('sort_order', 0, 2147483647);
    $title = trim((string)($_POST['title'] ?? ''));
    $titleLength = function_exists('mb_strlen') ? mb_strlen($title, 'UTF-8') : (preg_match_all('/./us', $title, $titleChars) ?: strlen($title));
    if($id === false || $rank === false || $price === false || $currency === false || $days === false || $mode === false || $cashPrice === false || $cashCurrency === false || ($mode === 2 && $cashPrice <= 0) || $enabled === false || $sort === false || $title === '' || $titleLength > 100 || !vipMembershipIsSellableRank($rank)){
        vipStoreReply(false, 'تحقق من اسم العرض والرتبة والسعر والمدة. يجب اختيار رتبة عضو موجودة في الشات.');
    }
    $now = time();
    if($id > 0){
        $stmt = $mysqli->prepare("UPDATE boom_vip_membership_offers SET title = ?, rank_value = ?, price = ?, currency = ?, payment_mode = ?, cash_price = ?, cash_currency = ?, duration_days = ?, enabled = ?, sort_order = ?, updated_at = ? WHERE offer_id = ?");
        if(!$stmt){ vipStoreReply(false, 'تعذر تجهيز تعديل العرض.'); }
        $stmt->bind_param('siiiidsiiiii', $title, $rank, $price, $currency, $mode, $cashPrice, $cashCurrency, $days, $enabled, $sort, $now, $id);
    }
    else {
        $stmt = $mysqli->prepare("INSERT INTO boom_vip_membership_offers (title, rank_value, price, currency, payment_mode, cash_price, cash_currency, duration_days, enabled, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if(!$stmt){ vipStoreReply(false, 'تعذر تجهيز إنشاء العرض.'); }
        $stmt->bind_param('siiiidsiiiii', $title, $rank, $price, $currency, $mode, $cashPrice, $cashCurrency, $days, $enabled, $sort, $now, $now);
    }
    $ok = $stmt->execute();
    $stmt->close();
    if(!$ok){ vipStoreReply(false, 'تعذر حفظ عرض العضوية.'); }
    vipStoreReply(true, 'تم حفظ عرض العضوية.');
}


if(isset($_POST['save_contact_settings'])){
    vipStoreAdmin();
    $number = vipMembershipCleanWhatsApp((string)($_POST['whatsapp_number'] ?? ''));
    $enabled = vipStorePostInt('whatsapp_enabled', 0, 1);
    if($enabled === false || ($number !== '' && (strlen($number) < 8 || strlen($number) > 15))){
        vipStoreReply(false, 'أدخل رقم واتساب دوليًا من 8 إلى 15 رقمًا.');
    }
    $now = time();
    $stmt = $mysqli->prepare("INSERT INTO boom_vip_membership_settings (setting_id, whatsapp_number, whatsapp_enabled, updated_at) VALUES (1, ?, ?, ?) ON DUPLICATE KEY UPDATE whatsapp_number = VALUES(whatsapp_number), whatsapp_enabled = VALUES(whatsapp_enabled), updated_at = VALUES(updated_at)");
    if(!$stmt){ vipStoreReply(false, 'تعذر تجهيز إعدادات واتساب.'); }
    $stmt->bind_param('sii', $number, $enabled, $now);
    $ok = $stmt->execute(); $stmt->close();
    vipStoreReply($ok, $ok ? 'تم حفظ رقم واتساب وإعدادات التواصل.' : 'تعذر حفظ إعدادات واتساب.');
}

if(isset($_POST['delete_offer'])){
    vipStoreAdmin();
    $id = vipStorePostInt('offer_id', 1, 2147483647);
    if($id === false){ vipStoreReply(false, 'رقم العرض غير صحيح.'); }
    $stmt = $mysqli->prepare("DELETE FROM boom_vip_membership_offers WHERE offer_id = ?");
    if(!$stmt){ vipStoreReply(false, 'تعذر تجهيز حذف العرض.'); }
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    if(!$ok || $affected < 1){ vipStoreReply(false, 'العرض غير موجود أو تعذر حذفه.'); }
    vipStoreReply(true, 'تم حذف العرض.');
}

if(isset($_POST['expire_current'])){
    $beforeRank = (int)$data['user_rank'];
    vipMembershipCleanUser((int)$data['user_id']);
    $afterRank = isset($data['user_rank']) ? (int)$data['user_rank'] : $beforeRank;
    vipStoreReply(true, 'تم فحص حالة العضوية.', array('rank_changed' => ($beforeRank !== $afterRank ? 1 : 0), 'rank' => $afterRank));
}

if(isset($_POST['purchase'])){
    global $data;
    if(!isMember($data) || isBot($data) || isGuest($data) || !useWallet()){
        vipStoreReply(false, 'الشراء متاح للأعضاء عند تفعيل محفظة الشات.');
    }
    $offerId = vipStorePostInt('offer_id', 1, 2147483647);
    $requestKey = strtolower(trim((string)($_POST['request_key'] ?? '')));
    if($offerId === false || !preg_match('/^[a-f0-9]{64}$/', $requestKey)){
        vipStoreReply(false, 'بيانات طلب الشراء غير صحيحة.');
    }

    $buyerId = (int)$data['user_id'];
    // Expire this user's previous plan before calculating an extension or replacement.
    vipMembershipCleanUser($buyerId);

    if(!$mysqli->begin_transaction()){
        vipStoreReply(false, 'تعذر بدء عملية الشراء.');
    }

    $offerStmt = $mysqli->prepare("SELECT offer_id, title, rank_value, price, currency, payment_mode, duration_days, enabled FROM boom_vip_membership_offers WHERE offer_id = ? LIMIT 1 FOR UPDATE");
    if(!$offerStmt){ $mysqli->rollback(); vipStoreReply(false, 'تعذر قراءة العرض.'); }
    $offerStmt->bind_param('i', $offerId);
    $offerStmt->execute();
    $offerResult = $offerStmt->get_result();
    $offer = $offerResult ? $offerResult->fetch_assoc() : null;
    $offerStmt->close();
    if(empty($offer) || (int)$offer['enabled'] !== 1 || (int)$offer['payment_mode'] !== 1 || !vipMembershipIsSellableRank((int)$offer['rank_value'])){
        $mysqli->rollback();
        vipStoreReply(false, 'هذا العرض غير متاح أو أن الرتبة لم تعد موجودة في الشات.');
    }

    $userStmt = $mysqli->prepare("SELECT user_id, user_name, user_rank, user_bot, user_banned, user_delete, user_gold, user_ruby, user_sgold, user_sruby FROM boom_users WHERE user_id = ? LIMIT 1 FOR UPDATE");
    if(!$userStmt){ $mysqli->rollback(); vipStoreReply(false, 'تعذر قراءة حساب المشتري.'); }
    $userStmt->bind_param('i', $buyerId);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    $buyer = $userResult ? $userResult->fetch_assoc() : null;
    $userStmt->close();
    if(empty($buyer) || (int)$buyer['user_rank'] <= 0 || (int)$buyer['user_rank'] >= 100 || (int)$buyer['user_bot'] > 0 || (int)$buyer['user_banned'] > 0 || (int)$buyer['user_delete'] > 0){
        $mysqli->rollback();
        vipStoreReply(false, 'هذا الحساب غير مؤهل لشراء عضوية.');
    }

    $memberStmt = $mysqli->prepare("SELECT previous_rank, rank_value, expires_at, started_at FROM boom_vip_memberships WHERE user_id = ? LIMIT 1 FOR UPDATE");
    if(!$memberStmt){ $mysqli->rollback(); vipStoreReply(false, 'تعذر قراءة العضوية الحالية.'); }
    $memberStmt->bind_param('i', $buyerId);
    $memberStmt->execute();
    $memberResult = $memberStmt->get_result();
    $membership = $memberResult ? $memberResult->fetch_assoc() : null;
    $memberStmt->close();

    $now = time();
    $currentRank = (int)$buyer['user_rank'];
    $hasActiveMembership = !empty($membership) && ((int)$membership['expires_at'] === 0 || (int)$membership['expires_at'] > $now);
    if($hasActiveMembership && $currentRank !== (int)$membership['rank_value']){
        $mysqli->rollback();
        vipStoreReply(false, 'تغيرت رتبتك بعد شراء العضوية بواسطة إجراء آخر؛ راجع الإدارة قبل تمديدها.');
    }
    $targetRank = (int)$offer['rank_value'];
    if(!$hasActiveMembership && $targetRank < $currentRank){
        $mysqli->rollback();
        vipStoreReply(false, 'لا يمكن شراء رتبة أقل من رتبتك الحالية.');
    }
    if($hasActiveMembership && $targetRank < $currentRank){
        $mysqli->rollback();
        vipStoreReply(false, 'لا يمكن استبدال عضويتك الحالية برتبة أقل.');
    }

    $previousRank = $hasActiveMembership ? (int)$membership['previous_rank'] : $currentRank;
    if(!function_exists('validRank') || !validRank($previousRank) || $previousRank >= 100){ $previousRank = 1; }
    $durationDays = (int)$offer['duration_days'];
    $oldExpiry = $hasActiveMembership ? (int)$membership['expires_at'] : 0;
    if($durationDays === 0 || ($hasActiveMembership && $oldExpiry === 0)){
        $expiresAt = 0; // Lifetime stays lifetime.
    }
    else {
        $base = ($hasActiveMembership && $oldExpiry > $now) ? $oldExpiry : $now;
        $expiresAt = $base + ($durationDays * 86400);
    }

    $price = (int)$offer['price'];
    $currency = (int)$offer['currency'];
    $balanceColumn = ($currency === 2) ? 'user_ruby' : 'user_gold';
    $spentColumn = ($currency === 2) ? 'user_sruby' : 'user_sgold';
    $balanceBefore = (int)$buyer[$balanceColumn];
    if($balanceBefore < $price){
        $mysqli->rollback();
        vipStoreReply(false, 'رصيدك من ' . vipMembershipCurrencyName($currency) . ' غير كافٍ لإتمام الشراء.');
    }
    $balanceAfter = $balanceBefore - $price;
    $walletStmt = $mysqli->prepare("UPDATE boom_users SET $balanceColumn = $balanceColumn - ?, $spentColumn = LEAST(2147483647, $spentColumn + ?) WHERE user_id = ? AND $balanceColumn >= ?");
    if(!$walletStmt){ $mysqli->rollback(); vipStoreReply(false, 'تعذر تنفيذ خصم الرصيد.'); }
    $walletStmt->bind_param('iiii', $price, $price, $buyerId, $price);
    $walletOk = $walletStmt->execute() && $walletStmt->affected_rows === 1;
    $walletStmt->close();
    if(!$walletOk){ $mysqli->rollback(); vipStoreReply(false, 'لم يتم خصم الرصيد؛ تحقق من المحفظة وحاول مرة أخرى.'); }

    $vipEnd = 0;
    if($targetRank === 50){
        $vipEnd = ($expiresAt === 0) ? 2147483647 : min(2147483647, $expiresAt);
    }
    $rankChanged = ($currentRank !== $targetRank);
    $rankUpdate = $mysqli->prepare("UPDATE boom_users SET user_rank = ?, vip_end = ?, user_action = user_action + 1, pcount = pcount + 1, naction = naction + 1 WHERE user_id = ?");
    if(!$rankUpdate){ $mysqli->rollback(); vipStoreReply(false, 'تعذر تحديث رتبة العضوية.'); }
    $rankUpdate->bind_param('iii', $targetRank, $vipEnd, $buyerId);
    $rankOk = $rankUpdate->execute();
    $rankUpdate->close();
    if(!$rankOk){ $mysqli->rollback(); vipStoreReply(false, 'تعذر منح الرتبة؛ لم يتم اعتماد الشراء.'); }

    $startedAt = $hasActiveMembership ? (int)$membership['started_at'] : $now;
    $membershipStmt = $mysqli->prepare("INSERT INTO boom_vip_memberships (user_id, previous_rank, rank_value, expires_at, offer_id, started_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE previous_rank = VALUES(previous_rank), rank_value = VALUES(rank_value), expires_at = VALUES(expires_at), offer_id = VALUES(offer_id), started_at = VALUES(started_at), updated_at = VALUES(updated_at)");
    if(!$membershipStmt){ $mysqli->rollback(); vipStoreReply(false, 'تعذر تسجيل العضوية؛ لم يتم اعتماد الخصم.'); }
    $membershipStmt->bind_param('iiiiiii', $buyerId, $previousRank, $targetRank, $expiresAt, $offerId, $startedAt, $now);
    $membershipOk = $membershipStmt->execute();
    $membershipStmt->close();
    if(!$membershipOk){ $mysqli->rollback(); vipStoreReply(false, 'تعذر تسجيل العضوية؛ لم يتم اعتماد الخصم.'); }

    $orderStmt = $mysqli->prepare("INSERT INTO boom_vip_membership_orders (buyer_id, offer_id, rank_value, price, currency, duration_days, previous_rank, balance_before, balance_after, created_at, request_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if(!$orderStmt){ $mysqli->rollback(); vipStoreReply(false, 'تعذر تسجيل عملية الشراء.'); }
    $orderStmt->bind_param('iiiiiiiiiis', $buyerId, $offerId, $targetRank, $price, $currency, $durationDays, $previousRank, $balanceBefore, $balanceAfter, $now, $requestKey);
    $orderOk = $orderStmt->execute();
    $orderError = $orderStmt->errno;
    $orderStmt->close();
    if(!$orderOk){
        $mysqli->rollback();
        vipStoreReply(false, $orderError === 1062 ? 'تمت معالجة طلب الشراء هذا مسبقًا، ولم يُخصم رصيد جديد.' : 'تعذر تسجيل عملية الشراء؛ لم يتم اعتماد الخصم.');
    }

    if(!$mysqli->commit()){
        $mysqli->rollback();
        vipStoreReply(false, 'تعذر اعتماد عملية الشراء.');
    }
    if($rankChanged){
        clearNotifyAction($buyerId, 'rank_change');
        boomNotify('rank_change', array('target' => $buyerId, 'source' => 'rank_change', 'rank' => $targetRank));
    }
    redisUpdateUser($buyerId);
    $durationLabel = vipMembershipDurationLabel($durationDays);
    vipStoreReply(true, 'تم شراء العضوية بنجاح. رتبتك الآن: ' . vipMembershipRankName($targetRank) . ' · المدة: ' . $durationLabel, array('rank' => $targetRank, 'expires_at' => $expiresAt, 'balance_after' => $balanceAfter));
}

vipStoreReply(false, 'طلب غير صالح.');
?>
