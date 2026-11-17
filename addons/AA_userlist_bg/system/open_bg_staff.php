<?php
$load_addons = 'AA_userlist_bg';
require_once('../../../system/config_addons.php');
$target = (int)($_POST['target'] ?? 0);
if(!aaListBgEnsureSchema() || $target <= 0 || !boomAllow($addons['custom1'])){ die(); }
$targetUser = userDetails($target);
if(!aaListBgCanManageTarget($targetUser)){ die(); }
$user = getMyListBg($target);
?>
<div class="brow">
    <div class="bcell border_bottom">
        <div class="modal_top_menu">
            <div class="bcell_mid hpad15"><p class="label"><i class="fa fa-image bgrad24"></i> <?php echo $lang['list_bg']; ?></p></div>
            <div class="modal_top_menu_empty"></div>
            <div class="cancel_modal cover_text modal_top_item"><i class="fa fa-times"></i></div>
        </div>
    </div>
</div>
<div class="pad10">
    <p class="label">تغيير الخلفية الشخصية لهذا العضو</p>
    <?php if($user['bg_file'] != ''){ ?>
        <div class="aa_ulbg_preview" style="background-image:url('addons/AA_userlist_bg/files/background/<?php echo htmlspecialchars($user['bg_file'], ENT_QUOTES, 'UTF-8'); ?>');"></div>
    <?php } ?>
    <input id="aa_ulbg_file_staff" class="full_input" type="file" accept="image/png,image/jpeg,image/gif,image/webp">
    <button style="width:100%;margin:10px 0 0 0;" onclick="aaUploadStaffListBg(<?php echo $target; ?>);" class="reg_button theme_btn"><i class="fa fa-upload"></i> حفظ الخلفية</button>
    <button style="width:100%;margin:10px 0 0 0;" onclick="aaDeleteStaffListBg(<?php echo $target; ?>);" class="reg_button delete_btn"><i class="fa fa-trash"></i> حذف خلفية العضو</button>
    <p class="sub_text">خلفية الرتبة المخصصة لها أولوية الظهور على الخلفية الشخصية.</p>
</div>
<script data-cfasync="false" type="text/javascript">
window.aaUploadStaffListBg = function(id){
    var file = $('#aa_ulbg_file_staff')[0].files[0];
    if(!file){ callSaved(aaListBgError, 3); return; }
    var form = new FormData();
    form.append('file', file); form.append('target_upload_bg', id); form.append('token', utk);
    $.ajax({url:'addons/AA_userlist_bg/system/action.php', type:'post', data:form, contentType:false, processData:false, cache:false, timeout:20000, dataType:'json'})
    .done(function(response){
        if(response && response.code == 1){
            aaListBgSetPersonal(id, response.file); aaListBgRefresh(true); aaListBgApplyAll();
            callSaved(aaListBgSaved, 1); hideModal();
        } else { callSaved(response && response.code == 3 ? 'حجم الصورة أكبر من 2 ميجابايت' : aaListBgError, 3); }
    }).fail(function(){ callSaved(aaListBgError, 3); });
};
window.aaDeleteStaffListBg = function(id){
    $.post('addons/AA_userlist_bg/system/action.php', {target_delete_bg:id, token:utk}, function(response){
        if(response && response.code == 1){
            aaListBgSetPersonal(id, ''); aaListBgRefresh(true); aaListBgApplyAll();
            callSaved(aaListBgRemoved, 1); hideModal();
        } else { callSaved(aaListBgError, 3); }
    }, 'json');
};
</script>
