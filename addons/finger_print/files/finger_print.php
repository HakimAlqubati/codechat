<?php if (boomAllow((int) $addons['addons_access'])) {
require_once BOOM_PATH . '/system/addon_menu_labels.php';
$fpMenuCemetery = addonMenuLabelGet('finger_print_cemetery', 'قائمة المقبرة');
$fpMenuJail = addonMenuLabelGet('finger_print_jail', 'قائمة السجن');
$fpMenuVpn = addonMenuLabelGet('finger_print_vpn', 'سجل VPN');
$fpMenuHistory = addonMenuLabelGet('finger_print_history', 'سجل العقوبات');
$fpMenuExceptions = addonMenuLabelGet('finger_print_exceptions', 'استثناءات المقبرة');
?>
<script data-cfasync="false" type="text/javascript">
$(document).ready(function(){
    appLeftMenu('ban', <?php echo json_encode($fpMenuCemetery, JSON_UNESCAPED_UNICODE); ?>, 'showFingerBanned();');
    appLeftMenu('lock', <?php echo json_encode($fpMenuJail, JSON_UNESCAPED_UNICODE); ?>, 'showFingerprintJail();');
    appLeftMenu('shield', <?php echo json_encode($fpMenuVpn, JSON_UNESCAPED_UNICODE); ?>, 'showFingerprintVpn();');
    appLeftMenu('history', <?php echo json_encode($fpMenuHistory, JSON_UNESCAPED_UNICODE); ?>, 'showFingerprintHistory();');
    appLeftMenu('user-check', <?php echo json_encode($fpMenuExceptions, JSON_UNESCAPED_UNICODE); ?>, 'showFingerprintExceptions();');
    $(".avstaff").append(
        "<div data='' onclick='banFingerPrint(this)' class='avset avitem avfinger'><span class='list_icon'><i class='fa fa-ban error'></i></span>إرسال إلى المقبرة</div>" +
        "<div data='' onclick='jailFingerPrint(this)' class='avset avitem avfinger'><span class='list_icon'><i class='fa fa-lock warning'></i></span>السجن بالبصمة</div>" +
        "<div data='' onclick='addFingerprintException(this)' class='avset avitem avfinger'><span class='list_icon'><i class='fa fa-user-check success'></i></span>استثناء الحساب من المقبرة</div>"
    );
});

function fpOpenList(url, height){
    $.post(url, {token: utk}, function(response){
        if(response == 0){ callSaved(system.error, 3); }
        else { overModal(response, height || 520); }
    });
}
showFingerBanned = function(){ fpOpenList('addons/finger_print/system/sw_finger_print.php', 520); };
showFingerprintJail = function(){ fpOpenList('addons/finger_print/system/sw_fp_jail.php', 520); };
showFingerprintVpn = function(){ fpOpenList('addons/finger_print/system/sw_fp_vpn.php', 520); };
showFingerprintHistory = function(){ fpOpenList('addons/finger_print/system/sw_fp_history.php', 520); };
showFingerprintExceptions = function(){ fpOpenList('addons/finger_print/system/sw_fp_exceptions.php', 520); };

fpFilterList = function(value){
    var needle = String(value || '').toLowerCase();
    $('#container_new .fp_item').each(function(){
        var haystack = $(this).text().toLowerCase();
        $(this).toggle(haystack.indexOf(needle) !== -1);
    });
};

banFingerPrint = function(source){
    var target = $(source).attr('data');
    if(!target){ return; }
    var reason = window.prompt('اكتب سبب إرسال العضو إلى المقبرة:', 'مخالفة متكررة');
    if(reason === null){ return; }
    if(!confirm('سيتم حظر بصمة هذا المتصفح ومنع الحسابات المرتبطة بها. هل تريد المتابعة؟')){ return; }
    $.post('addons/finger_print/system/action.php', {fp_ban_user: target, reason: reason, token: utk}, function(response){
        if(response == 1){ callSaved('تمت إضافة البصمة إلى المقبرة', 1); closeTrigger(); }
        else if(response == 100){ callSaved('العضو لم يسجل بصمة بعد', 3); }
        else if(response == 2){ callSaved('هذه البصمة موجودة مسبقاً في المقبرة', 3); }
        else { callSaved(system.error, 3); }
    });
};

jailFingerPrint = function(source){
    var target = $(source).attr('data');
    if(!target){ return; }
    var duration = window.prompt('مدة السجن بالدقائق. القيم: 10، 30، 60، 180، 360، 720، 1440، 4320، 10080، 43200. اكتب 0 للسجن الدائم:', '60');
    if(duration === null){ return; }
    duration = parseInt(duration, 10);
    if(isNaN(duration)){ callSaved('مدة غير صحيحة', 3); return; }
    var reason = window.prompt('سبب السجن:', 'مخالفة شروط الشات');
    if(reason === null){ return; }
    if(!confirm('سيتم نقل كل الحسابات التي تحمل هذه البصمة إلى روم السجن. هل تريد المتابعة؟')){ return; }
    $.post('addons/finger_print/system/action.php', {fp_jail_user: target, duration: duration, reason: reason, token: utk}, function(response){
        if(response == 1){ callSaved('تم إدخال البصمة إلى السجن', 1); closeTrigger(); }
        else if(response == 100){ callSaved('العضو لم يسجل بصمة بعد', 3); }
        else if(response == 101){ callSaved('هذه البصمة موجودة في المقبرة؛ المقبرة لها الأولوية', 3); }
        else { callSaved(system.error, 3); }
    });
};

fpCreateExceptionForUser = function(target, reason){
    if(!target){ return; }
    $.post('addons/finger_print/system/action.php', {fp_add_exception: target, reason: reason, token: utk}, function(response){
        if(response == 1){ callSaved('تم تسجيل الاستثناء للحساب المحدد', 1); closeTrigger(); }
        else if(response == 100){ callSaved('العضو لم يسجل بصمة بعد', 3); }
        else { callSaved(system.error, 3); }
    });
};
addFingerprintException = function(source){
    var target = $(source).attr('data');
    if(!target){ return; }
    var reason = window.prompt('سبب استثناء هذا الحساب من المقبرة فقط:', 'اشتباه تطابق بصمة غير صحيح');
    if(reason === null){ return; }
    if(!confirm('سيُستثنى هذا الحساب وحده من حظر المقبرة لهذه البصمة. بقية الحسابات ستبقى محظورة، ولا يتجاوز الاستثناء السجن. هل تريد المتابعة؟')){ return; }
    fpCreateExceptionForUser(target, reason);
};
addFingerprintExceptionById = function(){
    var target = window.prompt('أدخل رقم عضوية العضو المراد استثناؤه:');
    if(target === null){ return; }
    target = parseInt(target, 10);
    if(!target || target < 1){ callSaved('رقم العضوية غير صحيح', 3); return; }
    var reason = window.prompt('سبب الاستثناء:', 'اشتباه تطابق بصمة غير صحيح');
    if(reason === null){ return; }
    if(!confirm('سيُستثنى هذا الحساب وحده من المقبرة لهذه البصمة، ولن يتجاوز السجن. هل تريد المتابعة؟')){ return; }
    fpCreateExceptionForUser(target, reason);
};
removeFingerprintException = function(id){
    if(!confirm('هل تريد إلغاء استثناء هذا الحساب؟ سيعود تطبيق حظر المقبرة عليه عند تطابق البصمة.')){ return; }
    $.post('addons/finger_print/system/action.php', {fp_remove_exception: 1, exception_id: id, token: utk}, function(response){
        if(response == 1){ callSaved('تم إلغاء الاستثناء', 1); $('.exception' + id).fadeOut('fast'); }
        else { callSaved(system.error, 3); }
    });
};

delFingerUser = function(id){
    if(!confirm('هل تريد فك حظر هذه البصمة من المقبرة؟')){ return; }
    $.post('addons/finger_print/system/action.php', {fp_unban: 1, finger_id: id, token: utk}, function(response){
        if(response == 1){ callSaved('تم فك الحظر', 1); $('.finger' + id).fadeOut('fast'); }
        else { callSaved(system.error, 3); }
    });
};
releaseFingerprintJail = function(id){
    if(!confirm('هل تريد فك السجن عن هذه البصمة وإخراج الحسابات المرتبطة بها؟')){ return; }
    $.post('addons/finger_print/system/action.php', {fp_release_jail: 1, jail_id: id, token: utk}, function(response){
        if(response == 1){ callSaved('تم فك السجن', 1); $('.jail' + id).fadeOut('fast'); }
        else { callSaved(system.error, 3); }
    });
};
</script>
<?php } ?>
<script data-cfasync="false" src="addons/finger_print/system/fingerprint2.min.js"></script>
<script data-cfasync="false" type="text/javascript">
(function(){
    if(typeof Fingerprint2 === 'undefined' || typeof utk === 'undefined'){ return; }
    function identifyFingerprint(){
        try {
            new Fingerprint2().get(function(result){
                if(!result){ return; }
                $.ajax({
                    type: 'POST',
                    url: 'addons/finger_print/system/identify.php',
                    data: {token: utk, fingerprint: result},
                    dataType: 'json',
                    success: function(response){
                        if(!response || !response.status){ return; }
                        if(response.status === 'cemetery'){
                            document.body.innerHTML = '<div style="max-width:520px;margin:60px auto;padding:25px;text-align:center;background:#202327;color:#fff;border-radius:12px;font:16px/2 Arial"><h2 style="color:#ff6868">تم منع الدخول</h2><p>بصمة هذا المتصفح موجودة في المقبرة.</p><p>يرجى التواصل مع الإدارة عند الاعتقاد بوجود خطأ.</p></div>';
                            return;
                        }
                        if(response.status === 'jail'){
                            var currentRoom = (typeof user_room !== 'undefined') ? parseInt(user_room, 10) : 0;
                            if(currentRoom !== parseInt(response.room, 10)){
                                window.location.reload();
                            }
                        }
                    }
                });
            });
        } catch(e) {}
    }
    // Start as soon as the add-on script is loaded so the server learns the
    // browser fingerprint before the member has time to navigate elsewhere.
    if(document.readyState === 'loading'){
        document.addEventListener('DOMContentLoaded', identifyFingerprint, {once:true});
    }
    else {
        identifyFingerprint();
    }
})();
</script>
