<?php
require('../config_session.php');
require_once(BOOM_PATH . '/system/addon_page_appearance.php');
header('Content-Type: application/json; charset=utf-8');
function apaReply($ok, $message, $extra = array()){ $payload = array_merge(array('code'=>$ok ? 1 : 0, 'message'=>$message), is_array($extra) ? $extra : array()); echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); die(); }
if(!canManageAddons()){ apaReply(false, 'ليست لديك صلاحية إدارة الإضافات.'); }
if(!addonPageAppearanceEnsureSchema()){ apaReply(false, 'تعذر تجهيز جدول إعدادات المظهر.'); }
$current = addonPageAppearanceGet();
$action = isset($_POST['action']) ? (string)$_POST['action'] : '';
$uploadDir = rtrim(BOOM_PATH, '/\\') . '/upload/addon_page_appearance';
if(!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true)){ apaReply(false, 'تعذر إنشاء مجلد الخلفيات. تحقق من صلاحيات الكتابة.'); }
if($action === 'clear'){
    $uid=(int)$data['user_id']; $now=time();
    $stmt=$mysqli->prepare("INSERT INTO boom_addon_page_appearance (appearance_id,bg_file,tint_color,tint_opacity,bg_size,updated_by,updated_at) VALUES (1,'','#000000',0,'cover',?,?) ON DUPLICATE KEY UPDATE bg_file='',tint_color='#000000',tint_opacity=0,bg_size='cover',updated_by=VALUES(updated_by),updated_at=VALUES(updated_at)");
    if(!$stmt){ apaReply(false, 'تعذر حفظ الإعدادات.'); }
    $stmt->bind_param('ii',$uid,$now); $ok=$stmt->execute(); $stmt->close();
    if($ok && !empty($current['bg_file'])){ $old=basename($current['bg_file']); if(preg_match('/^apg_[a-f0-9]{32}\.(jpg|png|gif|webp)$/i',$old)){ @unlink($uploadDir.'/'.$old); } }
    apaReply($ok, $ok ? 'تمت إزالة الخلفية وإعادة إعدادات المظهر الافتراضية.' : 'تعذر حفظ الإعدادات.', $ok ? array('css'=>addonPageAppearanceCss()) : array());
}
if($action !== 'save'){ apaReply(false, 'طلب غير معروف.'); }
$color = isset($_POST['tint_color']) ? (string)$_POST['tint_color'] : '#000000';
if(!preg_match('/^#[0-9a-fA-F]{6}$/',$color)){ apaReply(false, 'لون التظليل غير صالح.'); }
$opacity = isset($_POST['tint_opacity']) ? (int)$_POST['tint_opacity'] : 18;
$opacity = max(0,min(90,$opacity));
$size = isset($_POST['bg_size']) ? (string)$_POST['bg_size'] : 'cover';
if(!in_array($size,array('cover','contain','100% 100%'),true)){ $size='cover'; }
$bgFile = (string)$current['bg_file'];
$newPath = '';
if(isset($_FILES['background']) && (int)$_FILES['background']['error'] !== UPLOAD_ERR_NO_FILE){
    $f=$_FILES['background'];
    if((int)$f['error'] !== UPLOAD_ERR_OK){ apaReply(false, 'فشل رفع الصورة. رمز الخطأ: '.(int)$f['error']); }
    if((int)$f['size'] < 1 || (int)$f['size'] > 5242880){ apaReply(false, 'حجم الصورة يجب ألا يتجاوز 5 ميجابايت.'); }
    if(!is_uploaded_file($f['tmp_name'])){ apaReply(false, 'ملف الصورة المرفوع غير صالح.'); }
    $info=@getimagesize($f['tmp_name']);
    if(!$info || empty($info['mime'])){ apaReply(false, 'الملف المختار ليس صورة صالحة.'); }
    $mime=strtolower($info['mime']);
    $extMap=array('image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp');
    if(!isset($extMap[$mime])){ apaReply(false, 'الصيغ المدعومة هي JPG وPNG وGIF وWEBP فقط.'); }
    if((int)$info[0] > 6000 || (int)$info[1] > 6000){ apaReply(false, 'أبعاد الصورة أكبر من الحد المسموح 6000 × 6000.'); }
    try{ $newName='apg_'.bin2hex(random_bytes(16)).'.'.$extMap[$mime]; }
    catch(Exception $e){ $newName='apg_'.md5(uniqid('',true).mt_rand()).'.'.$extMap[$mime]; }
    $newPath=$uploadDir.'/'.$newName;
    if(!move_uploaded_file($f['tmp_name'],$newPath)){ apaReply(false, 'تعذر حفظ الصورة. تحقق من صلاحيات مجلد upload.'); }
    $bgFile=$newName;
}
$uid=(int)$data['user_id']; $now=time();
$stmt=$mysqli->prepare("INSERT INTO boom_addon_page_appearance (appearance_id,bg_file,tint_color,tint_opacity,bg_size,updated_by,updated_at) VALUES (1,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE bg_file=VALUES(bg_file),tint_color=VALUES(tint_color),tint_opacity=VALUES(tint_opacity),bg_size=VALUES(bg_size),updated_by=VALUES(updated_by),updated_at=VALUES(updated_at)");
if(!$stmt){ if($newPath!==''){ @unlink($newPath); } apaReply(false, 'تعذر حفظ إعدادات الخلفية.'); }
$stmt->bind_param('ssisii',$bgFile,$color,$opacity,$size,$uid,$now);
$ok=$stmt->execute(); $stmt->close();
if(!$ok){ if($newPath!==''){ @unlink($newPath); } apaReply(false, 'لم يتم حفظ إعدادات الخلفية.'); }
if($newPath!=='' && !empty($current['bg_file'])){ $old=basename($current['bg_file']); if(preg_match('/^apg_[a-f0-9]{32}\.(jpg|png|gif|webp)$/i',$old)){ @unlink($uploadDir.'/'.$old); } }
apaReply(true, 'تم حفظ خلفية صفحات الإضافات والتلوين بنجاح.', array('css'=>addonPageAppearanceCss()));
?>
