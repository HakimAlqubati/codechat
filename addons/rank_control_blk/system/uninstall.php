<?php
// Keep Rank Control tables and rank metadata on uninstall to avoid orphaning
// users assigned to custom ranks. Remove the addon only after manually moving
// all custom-rank users back to standard ranks.
if(!defined('BOOM')){ die(); }
?>
