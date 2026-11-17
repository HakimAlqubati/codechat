<?php
$bbfv = boomFileVersion();
$devtools_enabled = isset($addons['addons_access']) && (int)$addons['addons_access'] !== 0;
$clipboard_enabled = isset($addons['custom2']) && $addons['custom2'] !== '' ? (int)$addons['custom2'] === 1 : true;
$clipboard_min_rank = isset($addons['custom1']) && $addons['custom1'] !== '' ? (int)$addons['custom1'] : 1;
$deny_message = isset($addons['custom4']) && $addons['custom4'] !== '' ? html_entity_decode($addons['custom4'], ENT_QUOTES, 'UTF-8') : 'هذه العملية غير مسموحة لرتبتك.';
$current_rank = isset($data['user_rank']) ? (int)$data['user_rank'] : 0;
?>

<?php if ($devtools_enabled) { ?>
<script data-cfasync="false" src="addons/disable_f12/files/data.js<?php echo $bbfv; ?>"></script>
<script data-cfasync="false" src="addons/disable_f12/files/custom.js<?php echo $bbfv; ?>"></script>
<script data-cfasync="false">
(function(){
    if (!window.devtoolsDetector || typeof window.devtoolsDetector.addListener !== 'function') {
        return;
    }
    window.devtoolsDetector.addListener(function(isOpen){
        if (isOpen) {
            window.location.href = 'addons/disable_f12/system/error.php';
        }
    });
    window.devtoolsDetector.launch();
})();
</script>
<?php } ?>

<?php if ($clipboard_enabled && $current_rank < $clipboard_min_rank) { ?>
<script data-cfasync="false">
window.disableF12ClipboardConfig = <?php echo json_encode(array(
    'enabled' => true,
    'userRank' => $current_rank,
    'minimumRank' => $clipboard_min_rank,
    'message' => $deny_message,
), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
</script>
<script data-cfasync="false" src="addons/disable_f12/files/clipboard-protection.js<?php echo $bbfv; ?>"></script>
<?php } ?>
