<?php
if(!defined('BOOM')){
    die();
}
if(isset($mysqli) && $mysqli instanceof mysqli){
    // Only remove transient typing state. Private messages and their status fields are preserved.
    $mysqli->query("DROP TABLE IF EXISTS `boom_private_typing`");
}
?>
