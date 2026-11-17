<?php
if(!defined('EMOJI_MANAGER_RUNTIME')){
 define('EMOJI_MANAGER_RUNTIME', 1);
 function emojiManagerReadJson($name, $fallback=array()){
  $file = BOOM_PATH . '/addons/emoji_manager/data/' . $name . '.json';
  if(!is_file($file)){ return $fallback; }
  $raw = @file_get_contents($file); $data = json_decode((string)$raw, true);
  return is_array($data) ? $data : $fallback;
 }
 function emojiManagerCategoryMeta($slug){
  $all = emojiManagerReadJson('categories', array());
  return isset($all[$slug]) && is_array($all[$slug]) ? $all[$slug] : array('label'=>$slug,'rank'=>0);
 }
 function emojiManagerCategoryAllowed($slug){
  if($slug === '' || $slug === 'base_emo'){ return true; }
  $meta = emojiManagerCategoryMeta($slug); $rank = isset($meta['rank']) ? (int)$meta['rank'] : 0;
  return $rank <= 0 || (function_exists('boomAllow') && boomAllow($rank));
 }
 function emojiManagerCategoryLabel($slug){
  $m = emojiManagerCategoryMeta($slug); return isset($m['label']) && $m['label'] !== '' ? $m['label'] : $slug;
 }
 function emojiManagerItemLabel($folder, $file){
  $items = emojiManagerReadJson('items', array()); $key = ($folder === '' ? '' : $folder . '/') . $file;
  return isset($items[$key]['label']) && $items[$key]['label'] !== '' ? $items[$key]['label'] : pathinfo($file, PATHINFO_FILENAME);
 }
}
?>