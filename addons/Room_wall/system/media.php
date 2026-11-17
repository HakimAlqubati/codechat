<?php
require_once('../../../system/config.php');
if(empty($data)||empty($mysqli)){http_response_code(403);exit;}
$addons=addonsDetails('Room_wall');
if(empty($addons)||!boomAllow($addons['addons_access'])){http_response_code(404);exit;}
require_once(__DIR__.'/addons_function.php');
rwEnsureSchema();
$id=isset($_GET['id'])?(int)$_GET['id']:0;if($id<1){http_response_code(404);exit;}
$q=$mysqli->query("SELECT * FROM boom_gen_media WHERE id='$id' LIMIT 1");
if(!$q||!$q->num_rows){http_response_code(404);exit;}$m=$q->fetch_assoc();
$allowed=false;
if($m['owner_type']==='post'){
    $p=$mysqli->query("SELECT id FROM boom_gen WHERE id='".(int)$m['owner_id']."' LIMIT 1");$allowed=($p&&$p->num_rows>0);
}
elseif($m['owner_type']==='story'){
    $storyId=(int)$m['owner_id'];
    $st=$mysqli->query("SELECT * FROM boom_gen_story WHERE id='$storyId' LIMIT 1");
    if($st&&$st->num_rows){
        $story=$st->fetch_assoc();
        $active=((int)$story['expires_at']>time() && (int)$story['archived']===0);
        $allowed=$active && rwCanViewStory($story);
        if(!$allowed && (int)$story['story_user']===(int)$data['user_id'] && (int)$story['archived']===1 && !empty(rwSettings()['archive_enabled']))$allowed=true;
        // A story preview already delivered in a CodyChat private message remains available only to the two participants.
        if(!$allowed){
            $uid=(int)$data['user_id'];
            $dm=$mysqli->query("SELECT id FROM boom_gen_story_dm WHERE story_id='$storyId' AND (sender_id='$uid' OR target_id='$uid') LIMIT 1");
            $allowed=($dm && $dm->num_rows>0);
        }
    }
}
if(!$allowed){http_response_code(404);exit;}
$path=rwMediaDir().'/'.basename($m['media_path']);
if(!is_file($path)){http_response_code(404);exit;}
$size=filesize($path);$mime=$m['media_mime'];
header('Content-Type: '.$mime);header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store, max-age=0');header('Accept-Ranges: bytes');
$start=0;$end=$size-1;$status=200;
if(isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/',$_SERVER['HTTP_RANGE'],$matches)){
    if($matches[1]==='' && $matches[2]===''){}else{
      $start=($matches[1]==='')?max(0,$size-(int)$matches[2]):(int)$matches[1];
      $end=($matches[2]==='')?$end:min($end,(int)$matches[2]);
      if($start>$end || $start>=$size){header('Content-Range: bytes */'.$size);http_response_code(416);exit;}
      $status=206;
    }
}
if($status===206){http_response_code(206);header("Content-Range: bytes $start-$end/$size");}
header('Content-Length: '.($end-$start+1));
$fp=fopen($path,'rb');if(!$fp)exit;fseek($fp,$start);$remaining=$end-$start+1;
while($remaining>0 && !feof($fp)){$chunk=fread($fp,min(8192,$remaining));if($chunk===false)break;echo $chunk;$remaining-=strlen($chunk);if(connection_aborted())break;}
fclose($fp);exit;
?>
