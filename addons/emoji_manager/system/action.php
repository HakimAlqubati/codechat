<?php
$load_addons = 'emoji_manager';
require('../../../system/config_addons.php');
header('Content-Type: application/json; charset=utf-8');
if(!canManageAddons()){ http_response_code(403); echo json_encode(array('ok'=>false,'message'=>'لا تملك صلاحية إدارة الإضافة')); exit; }
require_once __DIR__ . '/runtime.php';
$base = realpath(BOOM_PATH . '/emoticon'); $iconBase = BOOM_PATH . '/emoticon_icon';
function emj_out($ok,$message,$extra=array()){ echo json_encode(array_merge(array('ok'=>$ok,'message'=>$message),$extra),JSON_UNESCAPED_UNICODE); exit; }
function emj_slug($s){ $s=trim((string)$s); $s=@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s); $s=strtolower(preg_replace('/[^a-zA-Z0-9_-]+/','_', $s)); $s=trim($s,'_-'); if($s==='')$s='group_'.substr(bin2hex(random_bytes(5)),0,8); return substr($s,0,40); }
function emj_read($name,$fallback=array()){ $p=__DIR__.'/../data/'.$name.'.json'; $v=@json_decode((string)@file_get_contents($p),true); return is_array($v)?$v:$fallback; }
function emj_write($name,$data){ $p=__DIR__.'/../data/'.$name.'.json'; $tmp=$p.'.tmp'; if(file_put_contents($tmp,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX)===false)return false; return rename($tmp,$p); }
function emj_folder($slug){ global $base; if($slug==='')return $base; if(!preg_match('/^[a-zA-Z0-9_-]{1,48}$/',$slug))return false; return $base.'/'.$slug; }
function emj_count($folder){ $p=emj_folder($folder); if(!$p||!is_dir($p))return 0; $n=0; foreach(scandir($p) as $f){if($f==='.'||$f==='..')continue;if(is_file($p.'/'.$f)&&in_array(strtolower(pathinfo($f,PATHINFO_EXTENSION)),array('png','gif','webp','svg'))) $n++;}return $n; }
if(isset($_POST['apply_integration']) || isset($_POST['restore_integration'])){
 $restore=isset($_POST['restore_integration']);
 $map=array(
  'system/function_all.php'=>array('system__function_all.php.original','system__function_all.php'),
  'system/action/emoticon.php'=>array('system__action__emoticon.php.original','system__action__emoticon.php'),
  'css/main.css'=>array('css__main.css.original','css__main.css')
 );
 $done=array();$errors=array();
 foreach($map as $target=>$pair){$current=BOOM_PATH.'/'.$target;$original=__DIR__.'/../backup/'.$pair[0];$patched=__DIR__.'/../integration/'.$pair[1];if(!is_file($current)||!is_file($original)||!is_file($patched)){$errors[]=$target.' missing';continue;}$ch=hash_file('sha256',$current);$oh=hash_file('sha256',$original);$ph=hash_file('sha256',$patched);$expected=$restore?$ph:$oh;$already=$restore?$oh:$ph;if(hash_equals($already,$ch)){$done[]=$target.' unchanged';continue;}if(!hash_equals($expected,$ch)){$errors[]=$target.' modified outside Emoji Manager; skipped';continue;}$source=$restore?$original:$patched;$tmp=$current.'.emoji_manager.tmp';if(!copy($source,$tmp)||!rename($tmp,$current)){$errors[]=$target.' write failed';@unlink($tmp);continue;}$done[]=$target.' updated';}
 if(function_exists('opcache_reset'))@opcache_reset();if(function_exists('boomCacheUpdate'))@boomCacheUpdate();if(function_exists('redisFlushAll'))@redisFlushAll();
 emj_out(count($errors)===0,($restore?'استعادة الربط: ':'تطبيق الربط: ').implode('، ',$done).(count($errors)?' | أخطاء: '.implode('، ',$errors):''),array('files'=>$done,'errors'=>$errors));
}
if(isset($_POST['list_categories'])){
 $cats=emj_read('categories',array()); $out=array();
 $out[]=array('slug'=>'base_emo','label'=>'الرئيسية','rank'=>0,'count'=>emj_count(''),'icon'=>'emoticon_icon/base_emo.png');
 foreach(glob($base.'/*',GLOB_ONLYDIR)?:array() as $dir){$slug=basename($dir);$m=$cats[$slug]??array('label'=>$slug,'rank'=>0);$out[]=array('slug'=>$slug,'label'=>$m['label']??$slug,'rank'=>(int)($m['rank']??0),'count'=>emj_count($slug),'icon'=>'emoticon_icon/'.$slug.'.png');}
 emj_out(true,'',array('categories'=>$out));
}
if(isset($_POST['list_items'])){
 $folder=(string)($_POST['folder']??'*'); $folders=$folder==='*'?array_merge(array(''),array_map('basename',glob($base.'/*',GLOB_ONLYDIR)?:array())):array($folder); $items=emj_read('items',array());$out=array();
 foreach($folders as $f){$p=emj_folder($f);if(!$p||!is_dir($p))continue;foreach(scandir($p) as $file){if($file==='.'||$file==='..'||!in_array(strtolower(pathinfo($file,PATHINFO_EXTENSION)),array('png','gif','webp','svg')))continue;$key=($f===''?'':$f.'/').$file;$meta=$items[$key]??array();$out[]=array('key'=>$key,'file'=>$file,'label'=>$meta['label']??pathinfo($file,PATHINFO_FILENAME),'url'=>'emoticon/'.($f===''?'':$f.'/').rawurlencode($file));}}
 emj_out(true,'',array('items'=>$out));
}
if(isset($_POST['create_category'])){
 $label=trim((string)($_POST['label']??''));$rank=(int)($_POST['rank']??0);if($label===''||mb_strlen($label)>60||$rank<0||$rank>100)emj_out(false,'اسم القسم أو الرتبة غير صالح');
 $slug=emj_slug($label);$dir=emj_folder($slug);if(file_exists($dir))$slug.='_'.substr(bin2hex(random_bytes(3)),0,6);$dir=emj_folder($slug);if(!mkdir($dir,0755,true))emj_out(false,'تعذر إنشاء مجلد القسم');
 $cats=emj_read('categories',array());$cats[$slug]=array('label'=>$label,'rank'=>$rank);if(!emj_write('categories',$cats))emj_out(false,'تعذر حفظ بيانات القسم');
 if(isset($_FILES['icon'])&&$_FILES['icon']['error']===UPLOAD_ERR_OK){$tmp=$_FILES['icon']['tmp_name'];$info=@getimagesize($tmp);if($info&&$info[2]===IMAGETYPE_PNG)move_uploaded_file($tmp,$iconBase.'/'.$slug.'.png');}
 else {if(is_file($iconBase.'/base_emo.png'))copy($iconBase.'/base_emo.png',$iconBase.'/'.$slug.'.png');}
 emj_out(true,'تم إنشاء القسم',array('slug'=>$slug));
}
if(isset($_POST['create_large'])){
 $slug='large_emoticons';$dir=emj_folder($slug);if(!is_dir($dir)&&!mkdir($dir,0755,true))emj_out(false,'تعذر إنشاء قسم الرموز الكبيرة');$cats=emj_read('categories',array());$cats[$slug]=array('label'=>'الإيموجيات الكبيرة','rank'=>0);emj_write('categories',$cats);if(!is_file($iconBase.'/'.$slug.'.png')&&is_file($iconBase.'/base_emo.png'))copy($iconBase.'/base_emo.png',$iconBase.'/'.$slug.'.png');emj_out(true,'تم تجهيز قسم الإيموجيات الكبيرة');
}
if(isset($_POST['change_icon'])){
 $slug=(string)($_POST['slug']??'');if(!preg_match('/^[a-zA-Z0-9_-]{1,48}$/',$slug)||!is_dir(emj_folder($slug)))emj_out(false,'القسم غير صالح');if(!isset($_FILES['icon'])||$_FILES['icon']['error']!==UPLOAD_ERR_OK)emj_out(false,'لم يتم استلام الأيقونة');$tmp=$_FILES['icon']['tmp_name'];$info=@getimagesize($tmp);$fi=new finfo(FILEINFO_MIME_TYPE);if(!$info||$info[2]!==IMAGETYPE_PNG||$fi->file($tmp)!=='image/png')emj_out(false,'الأيقونة يجب أن تكون PNG سليمة');if(!move_uploaded_file($tmp,$iconBase.'/'.$slug.'.png'))emj_out(false,'تعذر حفظ الأيقونة');emj_out(true,'تم تحديث أيقونة القسم');
}
if(isset($_POST['edit_category'])){
 $slug=(string)($_POST['slug']??'');if($slug==='base_emo'||!preg_match('/^[a-zA-Z0-9_-]{1,48}$/',$slug)||!is_dir(emj_folder($slug)))emj_out(false,'القسم غير صالح');$label=trim((string)($_POST['label']??''));$rank=(int)($_POST['rank']??0);if($label===''||mb_strlen($label)>60||$rank<0||$rank>100)emj_out(false,'البيانات غير صالحة');$cats=emj_read('categories',array());$cats[$slug]=array('label'=>$label,'rank'=>$rank);emj_out(emj_write('categories',$cats),'تم حفظ بيانات القسم');
}
if(isset($_POST['delete_category'])){
 $slug=(string)($_POST['slug']??'');if($slug==='base_emo'||!preg_match('/^[a-zA-Z0-9_-]{1,48}$/',$slug))emj_out(false,'لا يمكن حذف هذا القسم');$dir=emj_folder($slug);if(!$dir||!is_dir($dir))emj_out(false,'القسم غير موجود');$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $f){$f->isDir()?@rmdir($f->getPathname()):@unlink($f->getPathname());}@rmdir($dir);@unlink($iconBase.'/'.$slug.'.png');$cats=emj_read('categories',array());unset($cats[$slug]);emj_write('categories',$cats);$items=emj_read('items',array());foreach(array_keys($items) as $k)if(strpos($k,$slug.'/')===0)unset($items[$k]);emj_write('items',$items);emj_out(true,'تم حذف القسم ورموزه');
}
if(isset($_POST['rename_item'])){
 $key=(string)($_POST['key']??'');$label=trim((string)($_POST['label']??''));if($label===''||mb_strlen($label)>80||strpos($key,'..')!==false||substr_count($key,'/')>1)emj_out(false,'الاسم أو المسار غير صالح');$parts=explode('/',$key);$file=array_pop($parts);$folder=$parts[0]??'';$p=emj_folder($folder);if(!$p||!is_file($p.'/'.$file))emj_out(false,'الرمز غير موجود');$items=emj_read('items',array());$items[$key]=array('label'=>$label);emj_out(emj_write('items',$items),'تم تعديل اسم العرض');
}
if(isset($_POST['delete_items'])){
 $keys=json_decode((string)($_POST['keys']??''),true);if(!is_array($keys)||count($keys)<1||count($keys)>500)emj_out(false,'قائمة الحذف غير صالحة');$items=emj_read('items',array());$deleted=0;
 foreach($keys as $key){if(!is_string($key)||strpos($key,'..')!==false||substr_count($key,'/')>1)continue;$parts=explode('/',$key);$file=array_pop($parts);$folder=$parts[0]??'';$p=emj_folder($folder);if(!$p||!is_file($p.'/'.$file))continue;if(!in_array(strtolower(pathinfo($file,PATHINFO_EXTENSION)),array('png','gif','webp','svg')))continue;if(@unlink($p.'/'.$file)){$deleted++;unset($items[$key]);}}
 emj_write('items',$items);emj_out(true,'تم حذف '.$deleted.' رمز نهائياً',array('deleted'=>$deleted));
}
if(isset($_POST['upload_items'])){
 $folder=(string)($_POST['folder']??'');$dest=emj_folder($folder);if(!$dest||!is_dir($dest))emj_out(false,'القسم المحدد غير موجود');if(!isset($_FILES['emoji_files']))emj_out(false,'لم يتم اختيار ملفات');$names=$_FILES['emoji_files']['name'];$tmp=$_FILES['emoji_files']['tmp_name'];$errs=$_FILES['emoji_files']['error'];if(!is_array($names)||count($names)>50)emj_out(false,'الحد الأعلى 50 ملفاً في العملية الواحدة');$label=trim((string)($_POST['label']??''));$items=emj_read('items',array());$done=0;$failed=0;
 for($i=0;$i<count($names);$i++){if($errs[$i]!==UPLOAD_ERR_OK||!is_uploaded_file($tmp[$i])){$failed++;continue;}$ext=strtolower(pathinfo($names[$i],PATHINFO_EXTENSION));if(!in_array($ext,array('png','gif','webp'))){$failed++;continue;}$fi=new finfo(FILEINFO_MIME_TYPE);$mime=$fi->file($tmp[$i]);$allowed=array('png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp');if(($allowed[$ext]??'')!==$mime){$failed++;continue;}$info=@getimagesize($tmp[$i]);if(!$info||($info['mime']??'')!==$mime||$info[0]<1||$info[1]<1||$info[0]>4096||$info[1]>4096){$failed++;continue;}
  $stem=substr(bin2hex(random_bytes(8)),0,16);$target=$dest.'/'.$stem.'.'.$ext;if(!move_uploaded_file($tmp[$i],$target)){$failed++;continue;}$key=($folder===''?'':$folder.'/').$stem.'.'.$ext;$items[$key]=array('label'=>$label!==''&&count($names)===1?$label:pathinfo($names[$i],PATHINFO_FILENAME));$done++;
 }
 emj_write('items',$items);emj_out($done>0,'تم رفع '.$done.' ملف، وتعذر رفع '.$failed,array('uploaded'=>$done,'failed'=>$failed));
}
emj_out(false,'طلب غير معروف');
?>