<?php
require_once BOOM_PATH . '/addons/purchase_store/system/addons_function.php';
require_once BOOM_PATH . '/system/addon_menu_labels.php';
$psMenuTitle = addonMenuLabelGet('purchase_store', 'متجر ترقيات المستوى والإعجابات');
$psFileSettings = psStoreSettings();
$psShowStore = isMember($data) && useWallet() && (
    (psStoreServiceEnabled('level', $psFileSettings) && (int)$data['user_rank'] >= psStoreServiceRank('level', $psFileSettings)) ||
    (psStoreServiceEnabled('likes', $psFileSettings) && (int)$data['user_rank'] >= psStoreServiceRank('likes', $psFileSettings))
);
if($psShowStore && boomAllow($addons['addons_access'])){
?>
<link rel="stylesheet" href="addons/purchase_store/files/purchase_store.css"/>
<script data-cfasync="false">
$(function(){
    if(typeof appStoreMenu === 'function' && $('#addon_store_menu_purchase_store').length === 0){
        addonStoreMenu('purchase_store', 10, 'addons/purchase_store/files/icon.svg', <?php echo json_encode($psMenuTitle, JSON_UNESCAPED_UNICODE); ?>, 'openPurchaseStore()');
        $('#addon_store_menu_purchase_store').attr('id', 'purchase_store_menu_item');
        if(typeof leftMenuCheck === 'function'){
            leftMenuCheck();
        }
    }
});
openPurchaseStore = function(){
    $.post('addons/purchase_store/system/template.php', {token: utk}, function(response){
        if(response && response.trim() !== ''){
            showAddonFullPage(response, 620, 'large');
        }
        else {
            callError('لا توجد خدمة شراء متاحة لرتبتك حالياً');
        }
    }).fail(function(){ callError(system.error); });
};
</script>
<?php } ?>
