<?php
$load_addons = 'AA_userlist_bg';
require_once('../../../system/config_addons.php');
require_once __DIR__ . '/../../../system/addon_menu_labels.php';
$aaBgStoreTitle = addonMenuLabelGet('AA_userlist_bg', 'متجر خلفية العضو في المتصلين');
if(!aaListBgEnsureSchema() || !boomAllow($addons['addons_access'])){
    die();
}
$userId = (int)$data['user_id'];
$user = getMyListBg($userId);
$activeId = 0;
$stmt = $mysqli->prepare("SELECT bg_id FROM userlist_bg_active WHERE user_id = ? LIMIT 1");
if($stmt){ $stmt->bind_param('i', $userId); $stmt->execute(); $stmt->bind_result($activeId); $stmt->fetch(); $stmt->close(); }
$owned = array();
$stmt = $mysqli->prepare("SELECT bg_id FROM userlist_bg_owned WHERE user_id = ?");
if($stmt){ $stmt->bind_param('i', $userId); $stmt->execute(); $stmt->bind_result($ownedId); while($stmt->fetch()){ $owned[(int)$ownedId] = true; } $stmt->close(); }
$assets = array();
$q = $mysqli->query("SELECT a.bg_id, a.bg_file, a.bg_title, a.bg_price
    FROM userlist_bg_assets a
    LEFT JOIN userlist_bg_rank r ON r.bg_id = a.bg_id
    WHERE (a.bg_active = 1 OR EXISTS (SELECT 1 FROM userlist_bg_owned o WHERE o.user_id = $userId AND o.bg_id = a.bg_id))
      AND (r.bg_id IS NULL OR EXISTS (SELECT 1 FROM userlist_bg_owned o2 WHERE o2.user_id = $userId AND o2.bg_id = a.bg_id))
    ORDER BY a.bg_created DESC, a.bg_id DESC");
if($q){ while($row = $q->fetch_assoc()){ $assets[] = $row; } $q->free(); }
?>
<div class="brow">
    <div class="bcell border_bottom">
        <div class="modal_top_menu">
            <div class="bcell_mid hpad15"><p class="label"><i class="fa fa-image bgrad24"></i> <?php echo htmlspecialchars($aaBgStoreTitle, ENT_QUOTES, 'UTF-8'); ?></p></div>
            <div class="modal_top_menu_empty"></div>
            <div class="cancel_modal cover_text modal_top_item"><i class="fa fa-times"></i></div>
        </div>
    </div>
</div>
<div class="pad10 aaulbg-member">
    <div class="aaulbg-modal-section">
        <p class="label">مكتبة الخلفيات</p>
        <p class="sub_text">اختر خلفية مجانية أو اشترِ خلفية بالذهب. عند الشراء تُضاف إلى مكتبتك وتُفعّل مباشرة.</p>
        <?php if(!count($assets)){ ?><div class="sub_text">لا توجد خلفيات متاحة حالياً.</div><?php } ?>
        <div class="aaulbg-member-grid">
        <?php foreach($assets as $asset){
            $bgId = (int)$asset['bg_id'];
            $price = (int)$asset['bg_price'];
            $has = isset($owned[$bgId]);
            $isActive = (int)$activeId === $bgId;
        ?>
            <div class="aaulbg-member-card">
                <div class="aaulbg-member-preview" style="background-image:url('addons/AA_userlist_bg/files/background/<?php echo htmlspecialchars($asset['bg_file'], ENT_QUOTES, 'UTF-8'); ?>');"></div>
                <div class="aaulbg-member-title"><?php echo htmlspecialchars($asset['bg_title'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="aaulbg-member-price">
                    <?php if($price > 0){ ?><img src="default_images/icons/gold.svg" alt="gold"/> <?php echo number_format($price); ?> ذهب<?php } else { ?>مجانية<?php } ?>
                </div>
                <?php if($isActive){ ?>
                    <button type="button" class="reg_button default_btn" disabled>الخلفية مفعلة</button>
                <?php } elseif($has || $price === 0){ ?>
                    <button type="button" class="reg_button theme_btn" onclick="aaActivateLibraryBg(<?php echo $bgId; ?>);">تفعيل الخلفية</button>
                <?php } elseif(useWallet()){ ?>
                    <button type="button" class="reg_button theme_btn" onclick="aaBuyLibraryBg(<?php echo $bgId; ?>, <?php echo $price; ?>);">شراء بالذهب</button>
                <?php } else { ?>
                    <button type="button" class="reg_button default_btn" disabled>المحفظة غير مفعلة</button>
                <?php } ?>
            </div>
        <?php } ?>
        </div>
        <?php if((int)$activeId > 0){ ?>
            <button type="button" class="reg_button delete_btn" style="width:100%;margin-top:10px" onclick="aaDeactivateLibraryBg();">إزالة خلفية المكتبة</button>
        <?php } ?>
    </div>

    <div class="aaulbg-modal-section">
        <p class="label">رفع خلفية شخصية</p>
        <?php if($user['bg_file'] != ''){ ?>
            <div class="aa_ulbg_preview" style="background-image:url('addons/AA_userlist_bg/files/background/<?php echo htmlspecialchars($user['bg_file'], ENT_QUOTES, 'UTF-8'); ?>');"></div>
        <?php } ?>
        <p class="sub_text">هذه الخلفية الشخصية تظهر إذا لم تكن لرتبتك خلفية مخصصة ولم تختر خلفية من المكتبة.</p>
        <input id="aa_ulbg_file" class="full_input" type="file" accept="image/png,image/jpeg,image/gif,image/webp">
        <button style="width:100%;margin:10px 0 0 0;" onclick="aaUploadMyListBg();" class="reg_button theme_btn"><i class="fa fa-upload"></i> حفظ خلفيتي</button>
        <button style="width:100%;margin:10px 0 0 0;" onclick="aaDeleteMyListBg();" class="reg_button delete_btn"><i class="fa fa-trash"></i> حذف خلفيتي الشخصية</button>
    </div>
</div>
<style>
.aaulbg-member .aaulbg-modal-section{padding:10px 0 14px;border-bottom:1px solid rgba(127,127,127,.2);margin-bottom:12px}
.aaulbg-member-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.aaulbg-member-card{min-width:0;border:1px solid rgba(127,127,127,.25);border-radius:8px;padding:8px;background:rgba(127,127,127,.04)}
.aaulbg-member-preview{width:100%;height:85px;background-size:cover;background-position:center;background-repeat:no-repeat;border-radius:6px}
.aaulbg-member-title{font-weight:700;margin-top:7px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.aaulbg-member-price{display:flex;align-items:center;gap:5px;font-size:12px;margin:5px 0 8px}
.aaulbg-member-price img{width:16px;height:16px;object-fit:contain}
.aaulbg-member-card button{width:100%;margin:0}
.aaulbg-member .sub_text{font-size:12px;line-height:1.6}
@media(max-width:360px){.aaulbg-member-grid{grid-template-columns:1fr}}
</style>
<script data-cfasync="false" type="text/javascript">
window.aaUploadMyListBg = function(){
    var file = $('#aa_ulbg_file')[0].files[0];
    if(!file){ callSaved(aaListBgError, 3); return; }
    var form = new FormData();
    form.append('file', file); form.append('upload_my_bg', 1); form.append('token', utk);
    $.ajax({url:'addons/AA_userlist_bg/system/action.php', type:'post', data:form, contentType:false, processData:false, cache:false, timeout:20000, dataType:'json'})
    .done(function(response){
        if(response && response.code == 1){
            aaListBgSetPersonal(user_id, response.file);
            aaListBgRefresh(true); aaListBgApplyAll();
            callSaved(aaListBgSaved, 1); hideModal();
        } else { callSaved(response && response.code == 3 ? 'حجم الصورة أكبر من 2 ميجابايت' : aaListBgError, 3); }
    }).fail(function(){ callSaved(aaListBgError, 3); });
};
window.aaDeleteMyListBg = function(){
    $.post('addons/AA_userlist_bg/system/action.php', {delete_my_bg:1, token:utk}, function(response){
        if(response && response.code == 1){ aaListBgSetPersonal(user_id, ''); aaListBgRefresh(true); aaListBgApplyAll(); callSaved(aaListBgRemoved, 1); hideModal(); }
        else { callSaved(aaListBgError, 3); }
    }, 'json');
};
window.aaActivateLibraryBg = function(id){
    $.post('addons/AA_userlist_bg/system/action.php', {activate_asset:id, token:utk}, function(response){
        if(response && response.code == 1){ aaListBgRefresh(true); callSaved('تم تفعيل الخلفية', 1); hideModal(); }
        else { callSaved('لا يمكن تفعيل هذه الخلفية أو أنها غير مملوكة لك', 3); }
    }, 'json');
};
window.aaBuyLibraryBg = function(id, price){
    if(!confirm('شراء هذه الخلفية مقابل '+price+' ذهب وتفعيلها الآن؟')){ return; }
    $.post('addons/AA_userlist_bg/system/action.php', {buy_asset:id, price:price, token:utk}, function(response){
        if(response && response.code == 1){ aaListBgRefresh(true); callSaved('تم الشراء وتفعيل الخلفية بنجاح. رصيدك المتبقي: '+response.balance+' ذهب', 1); hideModal(); }
        else if(response && response.code == 5){ callSaved('رصيدك من الذهب غير كافٍ', 3); }
        else if(response && response.code == 2){ callSaved('هذه الخلفية مملوكة لك بالفعل؛ افتحها من مكتبتك لتفعيلها', 3); }
        else if(response && response.code == 7){ callSaved('تغير سعر الخلفية. أعد فتح المكتبة لمراجعة السعر الجديد.', 3); }
        else { callSaved('تعذر شراء الخلفية', 3); }
    }, 'json');
};
window.aaDeactivateLibraryBg = function(){
    $.post('addons/AA_userlist_bg/system/action.php', {deactivate_asset:1, token:utk}, function(response){
        if(response && response.code == 1){ aaListBgRefresh(true); callSaved('تمت إزالة خلفية المكتبة', 1); hideModal(); }
        else { callSaved(aaListBgError, 3); }
    }, 'json');
};
</script>
