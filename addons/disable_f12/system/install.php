<?php
if (!defined('BOOM')) {
    die();
}

// Keep the developer-tools detector available, and enable clipboard
// protection by default. Rank 1 means members and higher ranks are allowed;
// guests (rank 0) are restricted until the administrator changes the limit.
$ad = array(
    'name' => 'disable_f12',
    'access' => 1,
    'custom1' => 1,       // Minimum rank allowed to use clipboard actions.
    'custom2' => 1,       // Clipboard protection enabled.
    'custom3' => 0,       // Reserved for future compatibility.
    'custom4' => 'هذه العملية غير مسموحة لرتبتك.',
);
?>
