<?php
$load_addons = 'chat_alerts';
require(__DIR__ . '/../../../system/config_addons.php');

$alerts = caVisibleAlerts(10);
caMarkSeen($alerts);

if(empty($alerts)){
    die();
}
?>
<div class="chat_alert_news_wrap">
    <div class="chat_alert_news_heading"><i class="fa fa-bullhorn"></i> إشعارات مهمة</div>
    <?php foreach($alerts as $alert){ echo caAlertCard($alert); } ?>
</div>
