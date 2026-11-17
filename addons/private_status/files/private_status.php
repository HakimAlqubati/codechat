<?php
if(!defined('BOOM')){
    die();
}
if(!isset($data['user_id']) || (int)($addons['custom1'] ?? 1) !== 1){
    return;
}
$ps_config = array(
    'typing' => (int)($addons['custom2'] ?? 1),
    'online' => (int)($addons['custom3'] ?? 1),
    'lastSeen' => (int)($addons['custom4'] ?? 1),
    'doubleCheck' => (int)($addons['custom5'] ?? 1),
    'blueRead' => (int)($addons['custom6'] ?? 1),
    'readLabel' => (int)($addons['custom7'] ?? 1),
    'language' => (isset($data['user_language']) && $data['user_language'] === 'English') ? 'en' : 'ar',
);
if(!$ps_config['typing'] && !$ps_config['online'] && !$ps_config['lastSeen'] && !$ps_config['doubleCheck'] && !$ps_config['blueRead'] && !$ps_config['readLabel']){
    return;
}
?>
<link rel="stylesheet" href="addons/private_status/files/private_status.css?v=1" />
<script data-cfasync="false">
window.CodyPrivateStatusConfig = <?php echo json_encode($ps_config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
</script>
<script data-cfasync="false" src="addons/private_status/files/private_status.js?v=1"></script>
