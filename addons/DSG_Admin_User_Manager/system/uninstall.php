<?php
/**
 * Remove the addon registration only. Admin-granted likes and audit history are
 * intentionally preserved to prevent accidental loss of account data.
 */
if(!defined('BOOM')){
    die();
}
$mysqli->query("DELETE FROM boom_addons WHERE addons = 'DSG_Admin_User_Manager'");
if(function_exists('redisUpdateAddons')){
    redisUpdateAddons('DSG_Admin_User_Manager');
}
?>
