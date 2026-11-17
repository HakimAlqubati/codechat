<?php
require("session.php");
require("database.php");
require("controller.php");
require("function.php");
require("function_all.php");
require('function_sranking.php');
require('settings_loader.php');
require('function_redis.php');

if(checkRateLimit()){
	die();
}

mysqli_report(MYSQLI_REPORT_OFF);
$mysqli = @new mysqli(BOOM_DHOST, BOOM_DUSER, BOOM_DPASS, BOOM_DNAME);
if (mysqli_connect_errno() || BOOM_INSTALL != 1) {
	if(BOOM_INSTALL != 1){
		$chat_install = 2;
	}
	else{
		$chat_install = 3;
	}
}
else{
	$chat_install = 1;
	if(isset($_COOKIE[BOOM_PREFIX . 'userid'], $_COOKIE[BOOM_PREFIX . 'utk'])){
		$ident = escape($_COOKIE[BOOM_PREFIX . 'userid'], true);
		$pass = escape($_COOKIE[BOOM_PREFIX . 'utk']);
		$data = getUserSession($ident, $pass);
		if(empty($data)){
			clearUserSession();
		}
		else if(!validSession()){
			$data = [];
			clearUserSession();
		}
		if(!empty($data) && file_exists(BOOM_PATH . '/addons/finger_print/system/runtime.php')){
			require_once(BOOM_PATH . '/addons/finger_print/system/runtime.php');
			fpRuntimeGuard();
		}
	}
	define('BOOM_LANG', getLanguage());
	require("language/" . BOOM_LANG . "/language.php");
	require("language/" . BOOM_LANG . "/response.php");
}
if($chat_install == 1){
	date_default_timezone_set("{$setting['timezone']}");
}
else {
	date_default_timezone_set("America/Toronto");
}
?>