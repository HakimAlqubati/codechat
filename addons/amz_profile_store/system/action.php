<?php
$load_addons = 'amz_profile_store';
require(__DIR__ . '/../../../system/config_addons.php');
require_once __DIR__ . '/profile_gate.php';
amzStoreEnsureTables();
header('Content-Type: application/json; charset=utf-8');
function amzJson($code,$message,$extra=array()){ echo json_encode(array_merge(array('code'=>$code,'message'=>$message),$extra),JSON_UNESCAPED_UNICODE); exit; }
function amzPostInt($key,$min=0,$max=2000000000){ if(!isset($_POST[$key]) || !is_numeric($_POST[$key])) return null; $v=(int)$_POST[$key]; return ($v<$min || $v>$max)?null:$v; }
function amzRequireAdmin(){ if(!canManageAddons()){ amzJson(0,'غير مصرح لك بتنفيذ هذا الإجراء.'); } }
$action = isset($_POST['action']) ? preg_replace('/[^a-z_]/','',$_POST['action']) : '';
$uid=(int)$data['user_id']; $rank=amzStoreRank($data);
if($action==='save_services'){
    amzRequireAdmin();
    foreach(array('background','frame','music') as $svc){
        $enabled=(isset($_POST[$svc.'_enabled']) && (int)$_POST[$svc.'_enabled']===1)?1:0;
        $free=amzPostInt($svc.'_free_rank',0,100); $paid=amzPostInt($svc.'_paid_rank',0,100); $price=amzPostInt($svc.'_price',0,2000000000);
        if($free===null || $paid===null || $price===null || $paid>$free){ amzJson(0,'تحقق من القيم: رتبة الشراء يجب أن تكون مساوية لرتبة المجانية أو أقل منها.'); }
        $stmt=$mysqli->prepare("UPDATE boom_amz_store_services SET enabled=?,free_rank=?,paid_rank=?,price_gold=? WHERE service_key=?");
        if(!$stmt){ amzJson(0,'تعذر حفظ الإعدادات.'); }
        $stmt->bind_param('iiiis',$enabled,$free,$paid,$price,$svc); $ok=$stmt->execute(); $stmt->close(); if(!$ok){amzJson(0,'حدث خطأ أثناء حفظ إعدادات الخدمة.');}
    }
    amzJson(1,'تم حفظ إعدادات الخدمات.');
}
if($action==='upload_item'){
    amzRequireAdmin();
    $service=isset($_POST['service'])?$_POST['service']:'';
    if(!in_array($service,array('background','frame'),true)){amzJson(0,'نوع العنصر غير صحيح.');}
    $title=trim((string)($_POST['title']??'')); $price=amzPostInt('price',0,2000000000); $titleLength=function_exists('mb_strlen')?mb_strlen($title,'UTF-8'):strlen($title);
    if($title==='' || $titleLength>120 || $price===null){amzJson(0,'أدخل اسماً صحيحاً وسعراً صالحاً.');}
    if(!isset($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])){amzJson(0,'اختر صورة للرفع.');}
    if((int)$_FILES['image']['size']>8*1024*1024){amzJson(0,'حجم الصورة يتجاوز 8 ميجابايت.');}
    if(!class_exists('finfo')){amzJson(0,'امتداد فحص أنواع الملفات غير متوفر في الاستضافة.');} $finfo=new finfo(FILEINFO_MIME_TYPE); $mime=$finfo->file($_FILES['image']['tmp_name']);
    $exts=array('image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp');
    if(!isset($exts[$mime]) || !@getimagesize($_FILES['image']['tmp_name'])){amzJson(0,'صيغة الصورة غير مدعومة.');}
    $dir=BOOM_PATH.'/upload/amz_profile_store'; if(!is_dir($dir) && !@mkdir($dir,0755,true)){amzJson(0,'تعذر إنشاء مجلد التخزين.');}
    $file='amz_'.$service.'_'.bin2hex(random_bytes(12)).'.'.$exts[$mime];
    if(!move_uploaded_file($_FILES['image']['tmp_name'],$dir.'/'.$file)){amzJson(0,'تعذر حفظ الصورة على الاستضافة.');}
    $stmt=$mysqli->prepare("INSERT INTO boom_amz_store_items (service_key,title,file_name,price_gold,enabled) VALUES (?,?,?,?,1)");
    if(!$stmt){@unlink($dir.'/'.$file);amzJson(0,'تعذر تسجيل العنصر.');}
    $stmt->bind_param('sssi',$service,$title,$file,$price);$ok=$stmt->execute();$stmt->close();
    if(!$ok){@unlink($dir.'/'.$file);amzJson(0,'تعذر تسجيل العنصر في قاعدة البيانات.');}
    amzJson(1,'تم رفع العنصر إلى المتجر.');
}

if($action==='update_item_price'){
    amzRequireAdmin(); $id=amzPostInt('item_id',1); $price=amzPostInt('price',0,2000000000);
    if($id===null || $price===null)amzJson(0,'السعر أو رقم العنصر غير صحيح.');
    $stmt=$mysqli->prepare("UPDATE boom_amz_store_items SET price_gold=? WHERE item_id=?");
    if(!$stmt)amzJson(0,'تعذر تحديث السعر.');
    $stmt->bind_param('ii',$price,$id);$ok=$stmt->execute();$stmt->close();
    if(!$ok)amzJson(0,'تعذر حفظ السعر.');
    amzJson(1,'تم تحديث سعر العنصر.');
}
if($action==='toggle_item'){
    amzRequireAdmin(); $id=amzPostInt('item_id',1); $enabled=amzPostInt('enabled',0,1);
    if($id===null || $enabled===null)amzJson(0,'بيانات غير صحيحة.');
    $stmt=$mysqli->prepare("UPDATE boom_amz_store_items SET enabled=? WHERE item_id=?");$stmt->bind_param('ii',$enabled,$id);$stmt->execute();$stmt->close();amzJson(1,'تم تحديث حالة العنصر.');
}
if($action==='buy_service' && isset($_POST['service']) && $_POST['service']==='music'){
    $s=amzStoreSetting('music'); if(!$s || !(int)$s['enabled'])amzJson(0,'خدمة الموسيقى متوقفة حالياً.');
    if($rank >= (int)$s['free_rank'])amzJson(1,'الموسيقى مجانية لرتبتك.');
    if($rank < (int)$s['paid_rank'])amzJson(0,'رتبتك لا تسمح بشراء هذه الخدمة.');
    if(amzStoreHasEntitlement('music',$uid))amzJson(1,'الخدمة مفعّلة مسبقاً لحسابك.');
    $price=max(0,(int)$s['price_gold']);
    $mysqli->begin_transaction();
    $q=$mysqli->prepare("SELECT user_gold FROM boom_users WHERE user_id=? FOR UPDATE");$q->bind_param('i',$uid);$q->execute();$q->bind_result($before);$has=$q->fetch();$q->close();
    if(!$has || (int)$before<$price){$mysqli->rollback();amzJson(0,'رصيد الذهب غير كافٍ.');}
    $after=(int)$before-$price;
    if($price>0){$u=$mysqli->prepare("UPDATE boom_users SET user_gold=? WHERE user_id=? AND user_gold>=?");$u->bind_param('iii',$after,$uid,$price);$ok=$u->execute() && $u->affected_rows===1;$u->close();if(!$ok){$mysqli->rollback();amzJson(0,'تعذر خصم الذهب. حاول مرة أخرى.');}}
    $e=$mysqli->prepare("INSERT INTO boom_amz_store_entitlements (user_id,service_key,paid_gold) VALUES (?,'music',?)");$e->bind_param('ii',$uid,$price);$ok=$e->execute();$e->close();
    if(!$ok){$mysqli->rollback();amzJson(0,'تعذر حفظ استحقاق الموسيقى.');}
    $t=$mysqli->prepare("INSERT INTO boom_amz_store_transactions (user_id,service_key,item_id,gold_paid,balance_before,balance_after) VALUES (?,'music',0,?,?,?)");$t->bind_param('iiii',$uid,$price,$before,$after);$t->execute();$t->close();
    $mysqli->commit(); if(function_exists('redisUpdateUser'))redisUpdateUser($uid); amzJson(1,'تم فتح خدمة الموسيقى بنجاح.');
}
if($action==='buy' || $action==='activate' || $action==='deactivate'){
    $id=amzPostInt('item_id',1); if($id===null)amzJson(0,'عنصر غير صالح.');
    if($action==='deactivate'){
       $q=$mysqli->prepare("DELETE FROM boom_amz_store_active WHERE user_id=? AND item_id=?");$q->bind_param('ii',$uid,$id);$q->execute();$q->close();amzJson(1,'تم إيقاف العنصر.');
    }
    $q=$mysqli->prepare("SELECT item_id,service_key,price_gold FROM boom_amz_store_items WHERE item_id=? AND enabled=1 LIMIT 1");$q->bind_param('i',$id);$q->execute();$q->bind_result($itemId,$service,$itemPrice);$found=$q->fetch();$q->close();
    if(!$found || !in_array($service,array('background','frame'),true))amzJson(0,'العنصر غير متاح.');
    $s=amzStoreSetting($service);if(!$s || !(int)$s['enabled'])amzJson(0,'هذه الخدمة متوقفة.');
    $free=$rank >= (int)$s['free_rank'];$canBuy=$rank >= (int)$s['paid_rank'];
    $ownedStmt=$mysqli->prepare("SELECT own_id FROM boom_amz_store_owned WHERE user_id=? AND item_id=? LIMIT 1");$ownedStmt->bind_param('ii',$uid,$id);$ownedStmt->execute();$ownedStmt->store_result();$owned=$ownedStmt->num_rows>0;$ownedStmt->close();
    if($action==='activate'){
       if(!$owned && !$free){amzJson(0,'يجب شراء العنصر أولاً.');}
       $a=$mysqli->prepare("INSERT INTO boom_amz_store_active (user_id,service_key,item_id) VALUES (?,?,?) ON DUPLICATE KEY UPDATE item_id=VALUES(item_id)");$a->bind_param('isi',$uid,$service,$id);$ok=$a->execute();$a->close();if(!$ok)amzJson(0,'تعذر تفعيل العنصر.');amzJson(1,'تم تفعيل العنصر.');
    }
    if($owned)amzJson(1,'هذا العنصر موجود في مقتنياتك بالفعل.');
    if(!$free && !$canBuy)amzJson(0,'رتبتك لا تسمح بشراء هذا العنصر.');
    $price=$free?0:max(0,(int)$itemPrice);
    $mysqli->begin_transaction();
    $bal=$mysqli->prepare("SELECT user_gold FROM boom_users WHERE user_id=? FOR UPDATE");$bal->bind_param('i',$uid);$bal->execute();$bal->bind_result($before);$has=$bal->fetch();$bal->close();
    if(!$has || (int)$before<$price){$mysqli->rollback();amzJson(0,'رصيد الذهب غير كافٍ.');}
    $after=(int)$before-$price;
    if($price>0){$u=$mysqli->prepare("UPDATE boom_users SET user_gold=? WHERE user_id=? AND user_gold>=?");$u->bind_param('iii',$after,$uid,$price);$ok=$u->execute() && $u->affected_rows===1;$u->close();if(!$ok){$mysqli->rollback();amzJson(0,'تعذر خصم الذهب. حاول مرة أخرى.');}}
    $o=$mysqli->prepare("INSERT INTO boom_amz_store_owned (user_id,item_id,paid_gold) VALUES (?,?,?)");$o->bind_param('iii',$uid,$id,$price);$ok=$o->execute();$o->close();
    if(!$ok){$mysqli->rollback();amzJson(0,'تعذر حفظ ملكية العنصر.');}
    $t=$mysqli->prepare("INSERT INTO boom_amz_store_transactions (user_id,service_key,item_id,gold_paid,balance_before,balance_after) VALUES (?,?,?,?,?,?)");$t->bind_param('isiiii',$uid,$service,$id,$price,$before,$after);$t->execute();$t->close();
    $mysqli->commit();if(function_exists('redisUpdateUser'))redisUpdateUser($uid);amzJson(1,'تم شراء العنصر وإضافته إلى مقتنياتك.');
}
amzJson(0,'طلب غير معروف.');
?>
