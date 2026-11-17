<?php
$load_addons = 'disable_f12';
require(__DIR__ . '/../../../system/config_addons.php');

if (!canManageAddons()) {
    die();
}

if (isset($_POST['save_settings'])) {
    $allowed_ranks = array(0, 1, 50, 70, 80, 90, 100);
    $devtools_access = isset($_POST['devtools_access']) ? (int)$_POST['devtools_access'] : 0;
    $clipboard_enabled = isset($_POST['clipboard_enabled']) ? (int)$_POST['clipboard_enabled'] : 0;
    $clipboard_rank = isset($_POST['clipboard_rank']) ? (int)$_POST['clipboard_rank'] : 1;
    $clipboard_message = isset($_POST['clipboard_message']) ? trim($_POST['clipboard_message']) : '';

    if (!in_array($devtools_access, array(0, 1), true)
        || !in_array($clipboard_enabled, array(0, 1), true)
        || !in_array($clipboard_rank, $allowed_ranks, true)) {
        die('0');
    }

    if ($clipboard_message === '') {
        $clipboard_message = 'هذه العملية غير مسموحة لرتبتك.';
    }
    if (function_exists('mb_substr')) {
        $clipboard_message = mb_substr($clipboard_message, 0, 300, 'UTF-8');
    } else {
        $clipboard_message = substr($clipboard_message, 0, 900);
    }
    $clipboard_message = escape($clipboard_message);

    $saved = $mysqli->query("UPDATE boom_addons SET
        addons_access = '$devtools_access',
        custom1 = '$clipboard_rank',
        custom2 = '$clipboard_enabled',
        custom4 = '$clipboard_message'
        WHERE addons = 'disable_f12'");

    if ($saved) {
        redisUpdateAddons('disable_f12');
        echo '5';
    } else {
        echo '0';
    }
    die();
}

die();
?>
