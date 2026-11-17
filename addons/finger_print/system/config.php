<?php
$load_addons = 'finger_print';
require_once('../../../system/config_addons.php');
require_once __DIR__ . '/../../../system/addon_menu_labels.php';
require_once(__DIR__ . '/runtime.php');
if (!boomAllow(100)) { die(); }

$fpSettings = fpSettings();
$roomId = (int) $fpSettings['jail_room_id'];
$roomName = 'غير محدد';
if ($roomId > 0) {
    $roomResult = $mysqli->query("SELECT room_name FROM boom_rooms WHERE room_id = '$roomId' LIMIT 1");
    if ($roomResult && $roomResult->num_rows > 0) { $roomName = $roomResult->fetch_assoc()['room_name']; }
}
$cemeteryCount = 0; $jailCount = 0; $vpnCount = 0; $exceptionCount = 0; $todayEvents = 0; $monthSanctions = 0;
$todayStart = strtotime('today');
$monthStart = strtotime(date('Y-m-01'));
$r = $mysqli->query("SELECT COUNT(*) AS c FROM boom_permabanned"); if ($r) { $cemeteryCount = (int) $r->fetch_assoc()['c']; }
$r = $mysqli->query("SELECT COUNT(*) AS c FROM boom_fp_jail WHERE active = 1 AND (expires_at = 0 OR expires_at > " . time() . ")"); if ($r) { $jailCount = (int) $r->fetch_assoc()['c']; }
$r = $mysqli->query("SELECT COUNT(*) AS c FROM boom_fp_vpn_log"); if ($r) { $vpnCount = (int) $r->fetch_assoc()['c']; }
$r = $mysqli->query("SELECT COUNT(*) AS c FROM boom_fp_exceptions WHERE active = 1"); if ($r) { $exceptionCount = (int) $r->fetch_assoc()['c']; }
$r = $mysqli->query("SELECT COUNT(*) AS c FROM boom_fp_history WHERE event_date >= '$todayStart'"); if ($r) { $todayEvents = (int) $r->fetch_assoc()['c']; }
$r = $mysqli->query("SELECT COUNT(*) AS c FROM boom_fp_history WHERE event_date >= '$monthStart' AND event_type IN ('cemetery_add','jail_add')"); if ($r) { $monthSanctions = (int) $r->fetch_assoc()['c']; }
$vpnKeyReady = !empty($setting['vpn_key']);
?>
<style>
.fp_admin_wrap{padding:12px}.fp_cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:16px}.fp_card{background:var(--chatbox2,#25282d);border:1px solid rgba(255,255,255,.08);border-radius:10px;padding:14px}.fp_card strong{display:block;font-size:22px;margin-top:5px}.fp_note{padding:10px 12px;border-radius:8px;background:rgba(59,130,246,.10);margin:10px 0;line-height:1.8}.fp_field{margin:12px 0}.fp_field select{max-width:100%;min-width:180px}.fp_btn{margin-top:12px}.fp_muted{opacity:.75;font-size:12px}
</style>
<?php echo elementTitle('إدارة المقبرة والسجن', 'loadLob(\'admin/setting_addons.php\');'); ?>
<div class="fp_menu_names">
<?php echo addonMenuLabelSettingsBlock('finger_print_cemetery', 'قائمة المقبرة', 'اسم اختصار المقبرة في القائمة الجانبية.'); ?>
<?php echo addonMenuLabelSettingsBlock('finger_print_jail', 'قائمة السجن', 'اسم اختصار السجن في القائمة الجانبية.'); ?>
<?php echo addonMenuLabelSettingsBlock('finger_print_vpn', 'سجل VPN', 'اسم اختصار سجل VPN في القائمة الجانبية.'); ?>
<?php echo addonMenuLabelSettingsBlock('finger_print_history', 'سجل العقوبات', 'اسم اختصار سجل العقوبات في القائمة الجانبية.'); ?>
<?php echo addonMenuLabelSettingsBlock('finger_print_exceptions', 'استثناءات المقبرة', 'اسم اختصار الاستثناءات في القائمة الجانبية.'); ?>
</div>

<div class="page_full fp_admin_wrap">
    <div class="fp_cards">
        <div class="fp_card"><span>سجلات المقبرة</span><strong><?php echo $cemeteryCount; ?></strong></div>
        <div class="fp_card"><span>عقوبات السجن النشطة</span><strong><?php echo $jailCount; ?></strong></div>
        <div class="fp_card"><span>استثناءات الحسابات</span><strong><?php echo $exceptionCount; ?></strong></div>
        <div class="fp_card"><span>سجلات VPN/Proxy</span><strong><?php echo $vpnCount; ?></strong></div>
        <div class="fp_card"><span>أحداث اليوم</span><strong><?php echo $todayEvents; ?></strong></div>
        <div class="fp_card"><span>عقوبات هذا الشهر</span><strong><?php echo $monthSanctions; ?></strong></div>
    </div>
    <div class="config_section">
        <div class="setting_element fp_field">
            <p class="label">أقل رتبة تستطيع استخدام المقبرة والسجن</p>
            <select id="fp_access"><?php echo listRank($addons['addons_access']); ?></select>
        </div>
        <div class="setting_element fp_field">
            <p class="label">مراقبة VPN وProxy</p>
            <select id="fp_vpn_monitor"><option value="1" <?php echo ((int)$fpSettings['vpn_monitor'] === 1 ? 'selected' : ''); ?>>مفعلة – تسجيل فقط</option><option value="0" <?php echo ((int)$fpSettings['vpn_monitor'] === 0 ? 'selected' : ''); ?>>متوقفة</option></select>
            <div class="fp_muted">المستخدم الذي يتصل عبر VPN يدخل بشكل طبيعي؛ لا يوجد منع أو روم مراجعة.</div>
        </div>
        <div class="setting_element fp_field">
            <p class="label">تسجيل محاولات VPN/Proxy</p>
            <select id="fp_vpn_log"><option value="1" <?php echo ((int)$fpSettings['vpn_log'] === 1 ? 'selected' : ''); ?>>تسجيل</option><option value="0" <?php echo ((int)$fpSettings['vpn_log'] === 0 ? 'selected' : ''); ?>>عدم التسجيل</option></select>
        </div>
        <div class="fp_note">
            <b>روم السجن:</b> <?php echo htmlspecialchars($roomName, ENT_QUOTES, 'UTF-8'); ?> (ID: <?php echo $roomId; ?>).<br>
            الروم مقيد للرتبة 100 والمسجونين أصحاب البصمة المطابقة فقط.<br>
            <b>مصدر VPN:</b> <?php echo $vpnKeyReady ? 'مفتاح ProxyCheck موجود في إعدادات حماية CodyChat.' : 'لا يوجد مفتاح VPN في إعدادات CodyChat؛ سيظل دخول VPN مسموحاً لكن لن تتوفر عملية التصنيف الخارجية.'; ?>
        </div>
        <button onclick="fpSaveSettings();" type="button" class="clear_top reg_button theme_btn fp_btn"><i class="fa fa-floppy-o"></i> حفظ الإعدادات</button>
        <button onclick="fpUpgradeDatabase();" type="button" class="clear_top reg_button theme_btn fp_btn"><i class="fa fa-database"></i> تهيئة / ترقية قاعدة بيانات الإضافة</button>
        <div class="fp_muted">استخدم الترقية عند تحديث إضافة المقبرة القديمة؛ تُنشئ الجداول الجديدة وتحافظ على سجلات المقبرة الحالية.</div>
    </div>
    <div class="fp_note" style="margin-top:18px">
        <b>قواعد الأولوية:</b> المقبرة تمنع البصمة المحظورة، ثم السجن يحصر البصمة المسجونة في روم السجن، وبعد ذلك يدخل مستخدم VPN العادي إلى الشات بصورة طبيعية. لا تُستخدم عناوين IP وحدها لإصدار عقوبة.
    </div>
</div>
<script>

fpUpgradeDatabase = function(){
    if(!confirm('سيتم إنشاء جداول السجن والسجل وVPN والاستثناءات وتحديث بنية البصمة مع الحفاظ على سجلات المقبرة الحالية. متابعة؟')){ return; }
    $.post('addons/finger_print/system/upgrade.php', {token: utk}, function(response){
        if(response == 1){ callSaved('تمت تهيئة وترقية قاعدة بيانات الإضافة بنجاح', 1); window.location.reload(); }
        else { callSaved('تعذرت الترقية؛ راجع صلاحيات قاعدة البيانات وسجل الأخطاء', 3); }
    });
}

fpSaveSettings = function(){
    $.post('addons/finger_print/system/action.php', {
        token: utk,
        fp_save_settings: 1,
        fp_access: $('#fp_access').val(),
        fp_vpn_monitor: $('#fp_vpn_monitor').val(),
        fp_vpn_log: $('#fp_vpn_log').val()
    }, function(response){
        if(response == 1){ callSaved('تم حفظ إعدادات الحماية', 1); }
        else { callSaved(system.error, 3); }
    });
}
</script>
