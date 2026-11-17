<?php
if(!defined('BOOM')){ die(); }
require_once BOOM_PATH . '/addons/vip/system/addons_function.php';
require_once BOOM_PATH . '/system/addon_menu_labels.php';
$vipMenuTitle = addonMenuLabelGet('vip', 'متجر العضويات');
if(isMember($data) && !isBot($data)){
    vipMembershipEnsureTables();
    vipMembershipCleanUser((int)$data['user_id']);
}
if(isMember($data) && !isBot($data) && (int)$data['user_rank'] < 100 && boomAllow($addons['addons_access'])){
?>
<script data-cfasync="false">
function vipMembershipExpiryCheck(){
    $.post('addons/vip/system/action.php',{token:utk,expire_current:1},function(r){
        var d=r; if(typeof r==='string'){ try{ d=JSON.parse(r); }catch(e){ d={}; } }
        if(d && d.rank_changed===1){ window.location.reload(); }
    },'json');
}
$(function(){
    vipMembershipExpiryCheck();
    if(typeof window.vipMembershipExpiryTimer === 'undefined'){ window.vipMembershipExpiryTimer = setInterval(vipMembershipExpiryCheck,120000); }
    if(typeof appStoreMenu === 'function' && $('#vip_membership_menu_item').length === 0){
        addonStoreMenu('vip', 20, 'addons/vip/files/icon.png', <?php echo json_encode($vipMenuTitle, JSON_UNESCAPED_UNICODE); ?>, 'openVipMembershipStore()');
        $('#addon_store_menu_vip').attr('id', 'vip_membership_menu_item');
        if(typeof leftMenuCheck === 'function'){ leftMenuCheck(); }
    }
});
openVipMembershipStore = function(){
    $.post('addons/vip/system/template.php',{token:utk},function(response){
        if(response && response.trim() !== ''){ showAddonFullPage(response,620,'large'); }
        else { callError('لا توجد عضويات متاحة للشراء حاليًا'); }
    }).fail(function(){ callError(system.error); });
};
</script>
<?php } ?>
