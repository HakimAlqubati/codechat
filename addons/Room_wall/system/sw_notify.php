<?php
$load_addons = 'Room_wall';
require_once('../../../system/config_addons.php');
$notify_adv = checkAdvNewsNotify();
$gen = $data['naction'];
	echo json_encode( array(
	"notify"=> $notify_adv,
	), JSON_UNESCAPED_UNICODE);
	
?>