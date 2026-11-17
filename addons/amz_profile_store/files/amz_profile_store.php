<?php
if(!defined('BOOM')){ die(); }
require_once BOOM_PATH . '/addons/amz_profile_store/system/profile_gate.php';
require_once BOOM_PATH . '/system/addon_menu_labels.php';
$amzMenuTitle = addonMenuLabelGet('amz_profile_store', 'الملف الشخصي');
amzStoreEnsureTables();
if(function_exists('canManageAddons') && canManageAddons()){
    require_once BOOM_PATH . '/addons/amz_profile_store/system/patch.php';
    amzStoreApplyCorePatches();
}
if(!empty($data) && !empty($data['user_id'])){
    $any = false;
    foreach(array('background','frame','music') as $svc){ $s=amzStoreSetting($svc); if($s && (int)$s['enabled']===1){ $any=true; break; } }
    if($any){
        echo '<link rel="stylesheet" type="text/css" href="addons/amz_profile_store/files/amz_profile_store.css?v=1.0.0">';
        echo '<script data-cfasync="false">';
        echo 'window.amzProfileStoreOpen=function(){ $.post("addons/amz_profile_store/system/store.php",{token:utk},function(r){showAddonFullPage(r,760,"large");},"html").fail(function(){callError(system.error);}); };';
        echo '$(function(){if(typeof addonStoreMenu==="function"){addonStoreMenu("amz_profile_store",30,"addons/amz_profile_store/files/icon.png",'.json_encode($amzMenuTitle, JSON_UNESCAPED_UNICODE).',"amzProfileStoreOpen()");}});';
        echo '</script>';
        echo '<script src="addons/amz_profile_store/files/amz_profile_store.js?v=1.0.0"></script>';
    }
}
?>
