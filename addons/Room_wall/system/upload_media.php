<?php
$load_addons='Room_wall';
require_once('../../../system/config_addons.php');
header('Content-Type: application/json; charset=utf-8');
rwEnsureSchema();
function rwUploadReply($ok,$payload=array()){echo json_encode(array_merge(array('ok'=>$ok),$payload),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
if(empty($data)||!rwAddonAllowed()) rwUploadReply(false,array('error'=>'يلزم تسجيل الدخول وامتلاك صلاحية استخدام الإضافة.'));
if(!isset($_FILES['file']) || !is_array($_FILES['file'])){
 $length=isset($_SERVER['CONTENT_LENGTH'])?(int)$_SERVER['CONTENT_LENGTH']:0;
 rwUploadReply(false,array('error'=>$length>0?'تجاوز الطلب حد post_max_size في إعدادات PHP على الاستضافة.':'لم يتم استلام ملف صالح.'));
}
if(!is_uploaded_file($_FILES['file']['tmp_name'])) rwUploadReply(false,array('error'=>'تعذر استلام الملف من PHP؛ تحقق من upload_max_filesize ومساحة الرفع المؤقتة.'));
$s=rwSettings();$file=$_FILES['file'];
if($file['error']!==UPLOAD_ERR_OK){
 $uploadErrors=array(UPLOAD_ERR_INI_SIZE=>'حجم الملف يتجاوز upload_max_filesize المحدد في PHP.',UPLOAD_ERR_FORM_SIZE=>'حجم الملف يتجاوز الحد المرسل من النموذج.',UPLOAD_ERR_PARTIAL=>'لم يكتمل رفع الملف، حاول مرة أخرى.',UPLOAD_ERR_NO_FILE=>'لم يتم اختيار ملف.',UPLOAD_ERR_NO_TMP_DIR=>'مجلد الرفع المؤقت غير متاح في الاستضافة.',UPLOAD_ERR_CANT_WRITE=>'تعذر حفظ الملف على القرص؛ تحقق من مساحة التخزين وصلاحيات الكتابة.',UPLOAD_ERR_EXTENSION=>'امتداد PHP أوقف عملية رفع الملف.');
 rwUploadReply(false,array('error'=>isset($uploadErrors[$file['error']])?$uploadErrors[$file['error']]:'فشل رفع الملف.'));
}
$size=(int)$file['size'];if($size<1)rwUploadReply(false,array('error'=>'الملف فارغ.'));
$finfo=function_exists('finfo_open')?finfo_open(FILEINFO_MIME_TYPE):false;
$mime=$finfo?finfo_file($finfo,$file['tmp_name']):'';if($finfo)finfo_close($finfo);
$images=array('image/jpeg','image/png','image/gif','image/webp');
$videos=array('video/mp4','video/webm','video/ogg','video/quicktime');
$audios=array('audio/mpeg','audio/mp3','audio/wav','audio/x-wav','audio/ogg','application/ogg','audio/mp4','audio/aac','audio/webm');
$kind='';$max=0;$target=isset($_POST['target'])&&$_POST['target']==='story'?'story':'post';
if(($target==='story' && !rwCanStory()) || ($target==='post' && !rwCanPost())) rwUploadReply(false,array('error'=>'لا تملك صلاحية رفع مرفقات لهذا النوع من المحتوى.'));
if(in_array($mime,$images,true)){$kind='image';$max=(int)$s['max_image_mb']*1024*1024;if(!@getimagesize($file['tmp_name']))rwUploadReply(false,array('error'=>'محتوى الصورة غير صالح.'));}
elseif(in_array($mime,$videos,true)){$kind='video';$max=(int)$s['max_video_mb']*1024*1024;}
elseif(in_array($mime,$audios,true)){$kind='audio';$max=(int)$s['max_audio_mb']*1024*1024;}
else rwUploadReply(false,array('error'=>'نوع الملف غير مسموح. الأنواع المدعومة: الصور والفيديو والصوت.'));
if($target==='story' && empty($s['allow_story_'.$kind])) rwUploadReply(false,array('error'=>'هذا النوع من الوسائط معطل للقصص من الإدارة.'));
if($target==='post' && empty($s['allow_post_'.$kind])) rwUploadReply(false,array('error'=>'هذا النوع من الوسائط معطل لليوميات من الإدارة.'));
if($size>$max)rwUploadReply(false,array('error'=>'حجم الملف أكبر من الحد الذي حددته الإدارة.'));
// Enforce audio/video duration on the server. If ffprobe is unavailable, fail closed rather than trusting browser metadata.
$maxSeconds=($kind==='video')?(int)$s['max_video_seconds']:(($kind==='audio')?(int)$s['max_audio_seconds']:0);
if($maxSeconds>0 && ($kind==='video' || $kind==='audio')){
 $disabled=array_map('trim',explode(',',(string)ini_get('disable_functions')));
 if(!function_exists('shell_exec') || in_array('shell_exec',$disabled,true)) rwUploadReply(false,array('error'=>'لا يستطيع الخادم التحقق من مدة الوسائط حالياً. اطلب تفعيل ffprobe من الاستضافة.'));
 $ffprobe=trim((string)@shell_exec('command -v ffprobe 2>/dev/null'));
 if($ffprobe==='') rwUploadReply(false,array('error'=>'يلزم تثبيت ffprobe على الخادم للتحقق من مدة الفيديو والصوت.'));
 $cmd=escapeshellarg($ffprobe).' -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 '.escapeshellarg($file['tmp_name']).' 2>/dev/null';
 $duration=trim((string)@shell_exec($cmd));
 if(!is_numeric($duration)) rwUploadReply(false,array('error'=>'تعذر قراءة مدة الملف. تأكد من سلامة صيغة الفيديو أو الصوت.'));
 if((float)$duration>$maxSeconds) rwUploadReply(false,array('error'=>'مدة الملف تتجاوز الحد الذي حددته الإدارة ('.$maxSeconds.' ثانية).'));
}
$dir=rwMediaDir();if(!is_dir($dir)&&!@mkdir($dir,0755,true))rwUploadReply(false,array('error'=>'تعذر تجهيز مجلد الوسائط.'));
@file_put_contents($dir.'/.htaccess',"Require all denied\nDeny from all\n");@file_put_contents($dir.'/index.php',"<?php http_response_code(404); exit;\n");
$ext='bin';$map=array('image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp','video/mp4'=>'mp4','video/webm'=>'webm','video/ogg'=>'ogv','application/ogg'=>'ogg','video/quicktime'=>'mov','audio/mpeg'=>'mp3','audio/mp3'=>'mp3','audio/wav'=>'wav','audio/x-wav'=>'wav','audio/ogg'=>'ogg','audio/mp4'=>'m4a','audio/aac'=>'aac','audio/webm'=>'webm');
if(isset($map[$mime]))$ext=$map[$mime];
$key=bin2hex(random_bytes(16));$name=$key.'.'.$ext;
if(!move_uploaded_file($file['tmp_name'],$dir.'/'.$name))rwUploadReply(false,array('error'=>'تعذر حفظ الملف.'));
$uid=(int)$data['user_id'];$now=time();$safeMime=escape($mime);$safeName=escape($name);
$ok=$mysqli->query("INSERT INTO boom_gen_upload (upload_key,file_user,file_path,file_mime,file_kind,file_size,created_at) VALUES ('$key','$uid','$safeName','$safeMime','$kind','$size','$now')");
if(!$ok){@unlink($dir.'/'.$name);rwUploadReply(false,array('error'=>'تعذر تسجيل الملف في قاعدة البيانات.'));}
rwUploadReply(true,array('key'=>$key,'kind'=>$kind,'mime'=>$mime,'size'=>$size));
?>
