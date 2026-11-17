<?php
require("../config_session.php");
if(!isset($_POST['get_emo'],$_POST['type'])){die();}
$emo=(string)$_POST['get_emo'];$panel=(int)$_POST['type'];
if($panel!==1&&$panel!==2){die();}
if($emo==='base_emo')$folder='';else{$folder=$emo; if(!preg_match('/^[a-zA-Z0-9_-]{1,48}$/',$folder))die();}
if($folder!==''&&function_exists('emojiManagerCategoryAllowed')&&!emojiManagerCategoryAllowed($folder)){http_response_code(403);die();}
$base=realpath(BOOM_PATH.'/emoticon');$path=realpath($base.($folder!==''?'/'.$folder:''));if(!$path||strpos($path,$base)!==0||!is_dir($path))die();
$act=$panel===1?'memot':'pemot';if($folder==='large_emoticons'){$wrap='large_emoticon';}elseif(stripos($folder,'sticker')!==false){$wrap='sticker';}elseif(stripos($folder,'custom')!==false){$wrap='custom_emo';}else{$wrap='emoticon';}$supported=array('png','gif','webp','svg');$out='';
foreach(scandir($path) as $file){if($file==='.'||$file==='..')continue;$ext=strtolower(pathinfo($file,PATHINFO_EXTENSION));if(!in_array($ext,$supported))continue;$slug=pathinfo($file,PATHINFO_FILENAME);if(!preg_match('/^[a-zA-Z0-9_-]{1,80}$/',$slug))continue;$src='emoticon/'.($folder!==''?$folder.'/':'').rawurlencode($file);$label=function_exists('emojiManagerItemLabel')?emojiManagerItemLabel($folder,$file):$slug;$out.='<div class="'.$wrap.'"><img class="'.$act.'" data=":'.$slug.':" title="'.htmlspecialchars($label,ENT_QUOTES,'UTF-8').'" src="'.$src.'" alt=""></div>';}
echo $out;die();
?>