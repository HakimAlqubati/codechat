<?php
if(!defined('BOOM')){ die(); }
$load_addons = 'vip';
require_once __DIR__ . '/addons_function.php';
if(!vipMembershipEnsureTables()){
    echo boomError('error');
    exit;
}
$ad = array(
    'name' => 'vip',
    'access' => 1,
    'max' => 1,
    'load' => 0,
    'custom1' => '2.0.0',
    'custom2' => '',
    'custom3' => '',
);
?>
