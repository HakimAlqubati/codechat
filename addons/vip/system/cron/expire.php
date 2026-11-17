<?php
$load_addons = 'vip';
require(__DIR__ . '/../../../../system/config_cron.php');
if(function_exists('vipMembershipCleanExpired')){
    vipMembershipCleanExpired(300);
}
?>
