<?php
$load_addons = 'purchase_store';
require(__DIR__ . '/../../../system/config_addons.php');

function psReply($ok, $message, $extra = array()){
    $payload = array_merge(array('code' => ($ok ? 1 : 0), 'emessage' => $message), $extra);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function psInt($value, $min, $max){
    if(!is_scalar($value) || !preg_match('/^[0-9]+$/', (string)$value)){
        return false;
    }
    $n = (int)$value;
    if($n < $min || $n > $max){
        return false;
    }
    return $n;
}

function psValidRankValue($rank){
    $allowed = array(1, 50, 70, 80, 90, 100);
    return in_array((int)$rank, $allowed, true);
}

function psAdminSaveSettings(){
    global $mysqli;
    if(!canManageAddons()){
        psReply(false, 'ليست لديك صلاحية إدارة الإضافة');
    }
    $levelEnabled = psInt($_POST['level_enabled'] ?? null, 0, 1);
    $levelRank = psInt($_POST['level_min_rank'] ?? null, 1, 100);
    $likesEnabled = psInt($_POST['likes_enabled'] ?? null, 0, 1);
    $likesRank = psInt($_POST['likes_min_rank'] ?? null, 1, 100);
    if($levelEnabled === false || $levelRank === false || $likesEnabled === false || $likesRank === false || !psValidRankValue($levelRank) || !psValidRankValue($likesRank)){
        psReply(false, 'تحقق من خيارات الرتب والتفعيل');
    }
    $stmt = $mysqli->prepare("UPDATE boom_purchase_store_settings SET level_enabled = ?, level_min_rank = ?, likes_enabled = ?, likes_min_rank = ? WHERE id = 1");
    if(!$stmt){ psReply(false, 'تعذر تجهيز حفظ الإعدادات'); }
    $stmt->bind_param('iiii', $levelEnabled, $levelRank, $likesEnabled, $likesRank);
    $ok = $stmt->execute();
    $stmt->close();
    if(!$ok){ psReply(false, 'تعذر حفظ الإعدادات'); }
    redisUpdateAddons('purchase_store');
    psReply(true, 'تم حفظ إعدادات الخدمات بنجاح');
}

function psAdminSaveProduct(){
    global $mysqli;
    if(!canManageAddons()){
        psReply(false, 'ليست لديك صلاحية إدارة الباقات');
    }
    $id = psInt($_POST['product_id'] ?? '0', 0, 2147483647);
    $service = (string)($_POST['service'] ?? '');
    $title = trim((string)($_POST['title'] ?? ''));
    $quantity = psInt($_POST['quantity'] ?? null, 1, 2147483647);
    $price = psInt($_POST['price'] ?? null, 1, 2147483647);
    $currency = psInt($_POST['currency'] ?? null, 1, 2);
    $rank = psInt($_POST['min_rank'] ?? null, 1, 100);
    $sort = psInt($_POST['sort_order'] ?? '0', 0, 2147483647);
    $enabled = psInt($_POST['enabled'] ?? null, 0, 1);
    $allowSelf = psInt($_POST['allow_self'] ?? null, 0, 1);
    $allowGift = psInt($_POST['allow_gift'] ?? null, 0, 1);
    if(($id === false) || !in_array($service, array('level','likes'), true) || $title === '' || strlen($title) > 400 || $quantity === false || $price === false || $currency === false || $rank === false || !psValidRankValue($rank) || $sort === false || $enabled === false || $allowSelf === false || $allowGift === false){
        psReply(false, 'بيانات الباقة غير صحيحة');
    }
    if($allowSelf === 0 && $allowGift === 0){
        psReply(false, 'يجب السماح بالشراء للنفس أو الإهداء على الأقل');
    }
    if($id > 0){
        $stmt = $mysqli->prepare("UPDATE boom_purchase_store_products SET service = ?, title = ?, quantity = ?, price = ?, currency = ?, min_rank = ?, enabled = ?, allow_self = ?, allow_gift = ?, sort_order = ? WHERE product_id = ?");
        if(!$stmt){ psReply(false, 'تعذر تجهيز تعديل الباقة'); }
        $stmt->bind_param('ssiiiiiiiii', $service, $title, $quantity, $price, $currency, $rank, $enabled, $allowSelf, $allowGift, $sort, $id);
    }
    else {
        $now = time();
        $stmt = $mysqli->prepare("INSERT INTO boom_purchase_store_products (service, title, quantity, price, currency, min_rank, enabled, allow_self, allow_gift, sort_order, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if(!$stmt){ psReply(false, 'تعذر تجهيز إنشاء الباقة'); }
        $stmt->bind_param('ssiiiiiiiii', $service, $title, $quantity, $price, $currency, $rank, $enabled, $allowSelf, $allowGift, $sort, $now);
    }
    $ok = $stmt->execute();
    $stmt->close();
    if(!$ok){ psReply(false, 'تعذر حفظ الباقة'); }
    psReply(true, 'تم حفظ الباقة بنجاح');
}

function psAdminDeleteProduct(){
    global $mysqli;
    if(!canManageAddons()){
        psReply(false, 'ليست لديك صلاحية حذف الباقات');
    }
    $id = psInt($_POST['product_id'] ?? null, 1, 2147483647);
    if($id === false){ psReply(false, 'معرّف الباقة غير صحيح'); }
    $stmt = $mysqli->prepare("DELETE FROM boom_purchase_store_products WHERE product_id = ?");
    if(!$stmt){ psReply(false, 'تعذر حذف الباقة'); }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    if($affected < 1){ psReply(false, 'الباقة غير موجودة'); }
    psReply(true, 'تم حذف الباقة');
}

function psPurchaseProduct(){
    global $mysqli, $data;
    if(!isMember($data) || !useWallet()){
        psReply(false, 'الشراء متاح للأعضاء عند تفعيل نظام المحفظة');
    }
    $productId = psInt($_POST['product_id'] ?? null, 1, 2147483647);
    $gift = psInt($_POST['gift'] ?? '0', 0, 1);
    $requestKey = strtolower(trim((string)($_POST['request_key'] ?? '')));
    if($productId === false || $gift === false || !preg_match('/^[a-f0-9]{64}$/', $requestKey)){ psReply(false, 'بيانات الشراء غير صحيحة'); }

    // A unique request key prevents a repeated click or replay from charging twice.
    $requestCheck = $mysqli->prepare("SELECT order_id FROM boom_purchase_store_orders WHERE request_key = ? LIMIT 1");
    if(!$requestCheck){ psReply(false, 'تعذر التحقق من طلب الشراء'); }
    $requestCheck->bind_param('s', $requestKey);
    $requestCheck->execute();
    $requestResult = $requestCheck->get_result();
    $alreadyProcessed = ($requestResult && $requestResult->num_rows > 0);
    $requestCheck->close();
    if($alreadyProcessed){ psReply(false, 'تمت معالجة طلب الشراء هذا مسبقاً؛ لم يتم خصم رصيد جديد'); }

    $stmt = $mysqli->prepare("SELECT * FROM boom_purchase_store_products WHERE product_id = ? AND enabled = 1 LIMIT 1");
    if(!$stmt){ psReply(false, 'تعذر قراءة الباقة'); }
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    if(empty($product)){ psReply(false, 'هذه الباقة غير متاحة حالياً'); }

    $settings = psStoreSettings();
    $service = $product['service'];
    if(!in_array($service, array('level','likes'), true) || !psStoreServiceEnabled($service, $settings)){
        psReply(false, 'هذه الخدمة متوقفة حالياً');
    }
    $serviceRank = psStoreServiceRank($service, $settings);
    if((int)$data['user_rank'] < $serviceRank || (int)$data['user_rank'] < (int)$product['min_rank']){
        psReply(false, 'رتبتك لا تسمح بشراء هذه الباقة');
    }
    if($service === 'level' && !useLevel()){
        psReply(false, 'يجب تفعيل نظام المستويات الأصلي أولاً');
    }
    if($service === 'likes' && !useLike()){
        psReply(false, 'يجب تفعيل نظام الإعجابات الأصلي أولاً');
    }
    if($gift && (int)$product['allow_gift'] !== 1){
        psReply(false, 'إهداء هذه الباقة غير مسموح');
    }
    if(!$gift && (int)$product['allow_self'] !== 1){
        psReply(false, 'الشراء للنفس غير مسموح لهذه الباقة');
    }

    $recipient = array();
    if($gift){
        $targetName = trim((string)($_POST['target_name'] ?? ''));
        if($targetName === '' || strlen($targetName) > 240){
            psReply(false, 'اكتب اسم العضو الذي تريد إهداءه الباقة');
        }
        $targetStmt = $mysqli->prepare("SELECT user_id, user_name, user_rank, user_bot, user_banned, user_delete, user_level FROM boom_users WHERE user_name = ? LIMIT 1");
        if(!$targetStmt){ psReply(false, 'تعذر البحث عن العضو'); }
        $targetStmt->bind_param('s', $targetName);
        $targetStmt->execute();
        $targetResult = $targetStmt->get_result();
        $recipient = $targetResult ? $targetResult->fetch_assoc() : array();
        $targetStmt->close();
        if(empty($recipient) || (int)$recipient['user_rank'] < 1 || (int)$recipient['user_bot'] > 0 || (int)$recipient['user_banned'] > 0 || (int)$recipient['user_delete'] > 0){
            psReply(false, 'لم يتم العثور على عضو صالح بهذا الاسم');
        }
        if((int)$recipient['user_id'] === (int)$data['user_id']){
            psReply(false, 'هذا حسابك؛ استخدم زر الشراء لنفسك');
        }
    }
    else {
        $recipient = array(
            'user_id' => (int)$data['user_id'],
            'user_name' => $data['user_name'],
            'user_level' => (int)$data['user_level'],
        );
    }

    $quantity = (int)$product['quantity'];
    $price = (int)$product['price'];
    $currency = (int)$product['currency'];
    $targetId = (int)$recipient['user_id'];
    if($service === 'level'){
        if((int)$recipient['user_level'] > (2147483647 - $quantity)){
            psReply(false, 'لا يمكن تجاوز الحد الأعلى الذي يدعمه حقل المستوى في قاعدة البيانات');
        }
    }
    if($service === 'likes'){
        $likeCheck = $mysqli->prepare("SELECT bonus_likes FROM boom_purchase_store_likes WHERE user_id = ? LIMIT 1");
        if(!$likeCheck){ psReply(false, 'تعذر قراءة رصيد الإعجابات المشتراة'); }
        $likeCheck->bind_param('i', $targetId);
        $likeCheck->execute();
        $likeResult = $likeCheck->get_result();
        $likeRow = $likeResult ? $likeResult->fetch_assoc() : null;
        $likeCheck->close();
        if(!empty($likeRow) && (float)$likeRow['bonus_likes'] + $quantity > 18446744073709551615){
            psReply(false, 'تجاوز رصيد الإعجابات الحد المدعوم');
        }
    }

    $balanceColumn = ($currency === 2) ? 'user_ruby' : 'user_gold';
    $spentColumn = ($currency === 2) ? 'user_sruby' : 'user_sgold';
    $mysqli->begin_transaction();
    $wallet = $mysqli->prepare("UPDATE boom_users SET $balanceColumn = $balanceColumn - ?, $spentColumn = LEAST(2147483647, $spentColumn + ?) WHERE user_id = ? AND $balanceColumn >= ?");
    if(!$wallet){
        $mysqli->rollback();
        psReply(false, 'تعذر تنفيذ الدفع');
    }
    $buyerId = (int)$data['user_id'];
    $wallet->bind_param('iiii', $price, $price, $buyerId, $price);
    $walletOk = $wallet->execute();
    $paid = $wallet->affected_rows;
    $wallet->close();
    if(!$walletOk || $paid !== 1){
        $mysqli->rollback();
        psReply(false, 'رصيدك غير كافٍ لإتمام عملية الشراء');
    }

    $effectOk = false;
    if($service === 'level'){
        $maxLevel = 2147483647 - $quantity;
        $levelStmt = $mysqli->prepare("UPDATE boom_users SET user_level = user_level + ? WHERE user_id = ? AND user_level <= ?");
        if($levelStmt){
            $levelStmt->bind_param('iii', $quantity, $targetId, $maxLevel);
            $effectOk = $levelStmt->execute() && $levelStmt->affected_rows === 1;
            $levelStmt->close();
        }
        if($effectOk){
            $expStmt = $mysqli->prepare("INSERT IGNORE INTO boom_exp (uid) VALUES (?)");
            if($expStmt){
                $expStmt->bind_param('i', $targetId);
                $effectOk = $expStmt->execute();
                $expStmt->close();
            }
        }
    }
    else {
        $likeStmt = $mysqli->prepare("INSERT INTO boom_purchase_store_likes (user_id, bonus_likes) VALUES (?, ?) ON DUPLICATE KEY UPDATE bonus_likes = bonus_likes + VALUES(bonus_likes)");
        if($likeStmt){
            $likeStmt->bind_param('ii', $targetId, $quantity);
            $effectOk = $likeStmt->execute();
            $likeStmt->close();
        }
    }

    if(!$effectOk){
        $mysqli->rollback();
        psReply(false, 'تعذر إضافة الميزة للمستفيد؛ لم يتم خصم الرصيد');
    }

    $title = (string)$product['title'];
    $created = time();
    $order = $mysqli->prepare("INSERT INTO boom_purchase_store_orders (buyer_id, recipient_id, product_id, service, product_title, quantity, price, currency, created_at, request_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if(!$order){
        $mysqli->rollback();
        psReply(false, 'تعذر تسجيل العملية؛ لم يتم اعتماد الخصم');
    }
    $order->bind_param('iiissiiiis', $buyerId, $targetId, $productId, $service, $title, $quantity, $price, $currency, $created, $requestKey);
    $orderOk = $order->execute();
    $order->close();
    if(!$orderOk){
        $duplicateRequest = ($order->errno === 1062);
        $mysqli->rollback();
        psReply(false, $duplicateRequest ? 'سبق تنفيذ طلب الشراء هذا؛ لم يتم خصم رصيد جديد' : 'تعذر تسجيل العملية؛ لم يتم اعتماد الخصم');
    }

    if(!$mysqli->commit()){
        $mysqli->rollback();
        psReply(false, 'تعذر اعتماد عملية الشراء');
    }

    redisUpdateUser($buyerId);
    redisUpdateUser($targetId);
    $serviceLabel = ($service === 'level') ? 'مستوى' : 'إعجاب';
    $recipientLabel = (string)$recipient['user_name'];
    $message = $gift ? 'تم إهداء ' . number_format($quantity) . ' ' . $serviceLabel . ' إلى ' . $recipientLabel . ' بنجاح' : 'تمت إضافة ' . number_format($quantity) . ' ' . $serviceLabel . ' إلى حسابك بنجاح';
    psReply(true, $message, array('service' => $service, 'quantity' => $quantity, 'recipient_id' => $targetId, 'currency' => $currency));
}

if(isset($_POST['save_settings'])){ psAdminSaveSettings(); }
if(isset($_POST['save_product'])){ psAdminSaveProduct(); }
if(isset($_POST['delete_product'])){ psAdminDeleteProduct(); }
if(isset($_POST['purchase'])){ psPurchaseProduct(); }
psReply(false, 'طلب غير صالح');
?>
