<?php
$load_addons = 'rank_control_blk';
require(__DIR__ . '/../../../system/config_addons.php');

if(!rankControlIsPrimaryOwner()){
    die();
}

function rcHtml($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$rankIcons = [];
$iconDir = BOOM_PATH . '/default_images/rank';
if(is_dir($iconDir)){
    foreach(glob($iconDir . '/*') as $iconPath){
        $ext = strtolower(pathinfo($iconPath, PATHINFO_EXTENSION));
        if(in_array($ext, ['svg','png','jpg','jpeg','webp'], true)){
            $rankIcons[] = basename($iconPath);
        }
    }
}

$rankRows = [];
foreach(rankList() as $rankValue){
    $rankValue = (int)$rankValue;
    if($rankValue < 1 || $rankValue > 95){
        continue;
    }
    $meta = rankControlGetCustomRank($rankValue);
    $rankRows[] = [
        'value' => $rankValue,
        'name' => $meta['rank_name'] ?? rankTitle($rankValue),
        'icon' => $meta['rank_icon'] ?? rankIcon($rankValue),
        'group' => $meta['rank_group'] ?? ($rankValue >= 70 ? 'staff' : 'member'),
        'custom' => !empty($meta),
        'can_promote' => rankControlCanPromoteFlag($rankValue),
    ];
}

$owners = $mysqli->query("SELECT o.user_id, o.previous_rank, o.granted_at, u.user_name FROM rank_control_owners o LEFT JOIN boom_users u ON u.user_id = o.user_id ORDER BY o.granted_at DESC");
$auditRows = $mysqli->query("SELECT a.*, u.user_name AS target_name FROM rank_control_audit a LEFT JOIN boom_users u ON u.user_id = a.target_id ORDER BY a.audit_id DESC LIMIT 25");
$primaryId = (int)(rankControlCache()['settings']['primary_owner_id'] ?? 0);
$primaryUser = $primaryId ? userDetails($primaryId) : [];
?>
<style>
.rc_wrap{padding:12px;direction:rtl;color:var(--tcolor,#333)}.rc_card{background:var(--bcolor,#fff);border:1px solid rgba(120,120,120,.25);border-radius:10px;padding:14px;margin:0 0 14px}.rc_title{font-size:17px;font-weight:800;margin:0 0 12px}.rc_sub{font-size:12px;opacity:.72;margin:5px 0 12px}.rc_grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px}.rc_field{display:flex;flex-direction:column;gap:5px;margin-bottom:9px}.rc_field label{font-size:12px;font-weight:700}.rc_field input,.rc_field select{width:100%;min-height:38px;border:1px solid #aaa5;border-radius:7px;padding:7px;background:transparent;color:inherit}.rc_btn{border:0;border-radius:7px;padding:9px 13px;cursor:pointer;font-weight:700;margin:3px}.rc_primary{background:var(--主,#7353b8);background:#7353b8;color:#fff}.rc_danger{background:#b23b45;color:#fff}.rc_muted{background:#777;color:#fff}.rc_table{width:100%;border-collapse:collapse;font-size:13px}.rc_table th,.rc_table td{padding:9px 6px;border-bottom:1px solid rgba(120,120,120,.2);text-align:right;vertical-align:middle}.rc_table th{font-size:12px;opacity:.75}.rc_icon{width:23px;height:23px;object-fit:contain;vertical-align:middle;margin-left:5px}.rc_switch{width:18px;height:18px;vertical-align:middle}.rc_msg{padding:10px;border-radius:7px;margin:8px 0;display:none}.rc_ok{background:#1987541c;color:#198754}.rc_err{background:#c0392b1c;color:#c0392b}.rc_small{font-size:11px;opacity:.7}.rc_actions{white-space:nowrap}
</style>
<div class="rc_wrap">
    <div class="rc_card">
        <div class="rc_title">Rank Control BLK – إدارة الرتب والصلاحيات</div>
        <div class="rc_sub">المالك الرئيسي فقط يستطيع إدارة هذا القسم ومنح رتبة 100. نطاق الرتب العادية من 1 إلى 95، والرتبة 100 محجوزة للمالك.</div>
        <div id="rc_message" class="rc_msg"></div>
        <div class="rc_grid">
            <div><b>المالك الرئيسي</b><div><?php echo rcHtml($primaryUser['user_name'] ?? 'غير معروف'); ?> <span class="rc_small">(#<?php echo $primaryId; ?>)</span></div></div>
            <div><b>عدد الرتب المعرفة</b><div><?php echo count($rankRows); ?></div></div>
            <div><b>عدد المالكين المفوضين</b><div><?php echo $owners ? $owners->num_rows : 0; ?></div></div>
        </div>
    </div>

    <div class="rc_card">
        <div class="rc_title">إنشاء رتبة أو تعديلها</div>
        <div class="rc_sub">الأرقام المحجوزة للنظام: 1 للعضو، 50 لـVIP، 69 للبوت، 70 و80 و90 للرتب الإدارية الأصلية. لا يمكن إنشاء رتبة جديدة بهذه الأرقام.</div>
        <div class="rc_grid">
            <div class="rc_field"><label for="rc_rank_value">رقم الرتبة (2–95)</label><input id="rc_rank_value" type="number" min="2" max="95" step="1" placeholder="مثال: 95"></div>
            <div class="rc_field"><label for="rc_rank_name">اسم الرتبة</label><input id="rc_rank_name" maxlength="80" placeholder="اسم الرتبة"></div>
            <div class="rc_field"><label for="rc_rank_icon">أيقونة الرتبة</label><select id="rc_rank_icon"><?php foreach($rankIcons as $icon){ ?><option value="<?php echo rcHtml($icon); ?>"><?php echo rcHtml($icon); ?></option><?php } ?></select></div>
            <div class="rc_field"><label for="rc_rank_group">تصنيف الرتبة</label><select id="rc_rank_group"><option value="member">عضو مميز (أقل من 70)</option><option value="staff">إدارية (70 فأعلى)</option></select></div>
        </div>
        <button type="button" class="rc_btn rc_primary" onclick="rcSaveRank()">حفظ الرتبة</button>
        <button type="button" class="rc_btn rc_muted" onclick="rcClearRankForm()">تفريغ الحقول</button>
    </div>

    <div class="rc_card">
        <div class="rc_title">قائمة الرتب وصلاحيات الترقية</div>
        <div class="rc_sub">عند تفعيل الصلاحية، يستطيع صاحب الرتبة ترقية أي عضو رتبته أقل منه إلى أي رتبة أقل من رتبته. لا يمكنه تعيين رتبة 100.</div>
        <div style="overflow-x:auto">
            <table class="rc_table">
                <thead><tr><th>القوة</th><th>الرتبة</th><th>التصنيف</th><th>الأيقونة</th><th>الترقية</th><th>إدارة</th></tr></thead>
                <tbody>
                <?php foreach($rankRows as $row){
                    $reserved = in_array($row['value'], rankControlReservedNumbers(), true);
                    $isBotRank = $row['value'] === 69;
                ?>
                    <tr>
                        <td><b><?php echo (int)$row['value']; ?></b></td>
                        <td><?php echo rcHtml($row['name']); ?><?php if($reserved){ ?> <span class="rc_small">(نظامية)</span><?php } ?></td>
                        <td><?php echo $row['value'] >= 70 ? 'إدارية' : ($isBotRank ? 'بوت' : 'عضوية'); ?></td>
                        <td><img class="rc_icon" src="default_images/rank/<?php echo rcHtml($row['icon']); ?>" alt=""></td>
                        <td><input class="rc_switch" type="checkbox" <?php echo $row['can_promote'] ? 'checked' : ''; ?> <?php echo $isBotRank ? 'disabled' : ''; ?> onchange="rcSetPermission(<?php echo (int)$row['value']; ?>, this.checked)"></td>
                        <td class="rc_actions">
                            <?php if($row['custom']){ ?>
                                <button type="button" class="rc_btn rc_muted" onclick="rcEditRank(<?php echo (int)$row['value']; ?>, <?php echo json_encode($row['name'], JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE); ?>, <?php echo json_encode($row['icon']); ?>, '<?php echo rcHtml($row['group']); ?>')">تعديل</button>
                                <button type="button" class="rc_btn rc_danger" onclick="rcDeleteRank(<?php echo (int)$row['value']; ?>)">حذف</button>
                            <?php } else { ?><span class="rc_small">محفوظة في النظام</span><?php } ?>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="rc_card">
        <div class="rc_title">إدارة المالكين – رتبة 100</div>
        <div class="rc_sub">يمكن للمالك الرئيسي تعيين أكثر من مالك. البحث بالـIP وسيلة للعثور على الحساب فقط؛ إذا ظهر أكثر من حساب بنفس IP يجب استخدام معرف الحساب أو بريده الإلكتروني. تعيين 100 يتطلب كلمة مرور المالك الرئيسي.</div>
        <div class="rc_grid">
            <div class="rc_field"><label for="rc_lookup_type">طريقة البحث</label><select id="rc_lookup_type"><option value="id">معرف الحساب (User ID)</option><option value="email">البريد الإلكتروني</option><option value="ip">عنوان IP</option></select></div>
            <div class="rc_field"><label for="rc_lookup_value">المعرف أو البريد أو IP</label><input id="rc_lookup_value" maxlength="190" placeholder="أدخل بيانات الحساب"></div>
            <div class="rc_field"><label for="rc_owner_password">كلمة مرور المالك الرئيسي</label><input id="rc_owner_password" type="password" maxlength="100" placeholder="تأكيد كلمة المرور"></div>
        </div>
        <button type="button" class="rc_btn rc_primary" onclick="rcGrantOwner()">تعيين مالك برتبة 100</button>
        <div class="rc_sub">لن يتم تعيين الرتبة إلى حسابات متعددة عند البحث بـIP. في هذه الحالة استخدم معرف المستخدم أو البريد الإلكتروني لتحديد الحساب بدقة.</div>
        <div style="overflow-x:auto">
            <table class="rc_table">
                <thead><tr><th>المالك المفوض</th><th>المعرف</th><th>الرتبة السابقة</th><th>تاريخ التعيين</th><th>إجراء</th></tr></thead>
                <tbody>
                <?php if($owners && $owners->num_rows > 0){ while($owner = $owners->fetch_assoc()){ ?>
                    <tr>
                        <td><?php echo rcHtml($owner['user_name'] ?? 'حساب غير موجود'); ?></td>
                        <td><?php echo (int)$owner['user_id']; ?></td>
                        <td><?php echo (int)$owner['previous_rank']; ?></td>
                        <td><?php echo date('Y-m-d H:i', (int)$owner['granted_at']); ?></td>
                        <td><button class="rc_btn rc_danger" type="button" onclick="rcRevokeOwner(<?php echo (int)$owner['user_id']; ?>)">سحب رتبة المالك</button></td>
                    </tr>
                <?php }} else { ?><tr><td colspan="5">لا يوجد مالكون مفوضون حالياً.</td></tr><?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="rc_card">
        <div class="rc_title">سجل العمليات الأخير</div>
        <div style="overflow-x:auto">
            <table class="rc_table"><thead><tr><th>العملية</th><th>الحساب</th><th>من</th><th>إلى</th><th>التفاصيل</th><th>التاريخ</th></tr></thead><tbody>
            <?php if($auditRows && $auditRows->num_rows > 0){ while($audit = $auditRows->fetch_assoc()){ ?>
                <tr><td><?php echo rcHtml($audit['action_type']); ?></td><td><?php echo rcHtml($audit['target_name'] ?? ''); ?> (#<?php echo (int)$audit['target_id']; ?>)</td><td><?php echo is_null($audit['old_rank']) ? '-' : (int)$audit['old_rank']; ?></td><td><?php echo is_null($audit['new_rank']) ? '-' : (int)$audit['new_rank']; ?></td><td><?php echo rcHtml($audit['details']); ?></td><td><?php echo date('Y-m-d H:i', (int)$audit['created_at']); ?></td></tr>
            <?php }} else { ?><tr><td colspan="6">لا توجد عمليات مسجلة حتى الآن.</td></tr><?php } ?>
            </tbody></table>
        </div>
    </div>
</div>
<script>
function rcMessage(ok, message){
    var box = document.getElementById('rc_message');
    box.className = 'rc_msg ' + (ok ? 'rc_ok' : 'rc_err');
    box.textContent = message || (ok ? 'تم الحفظ.' : 'تعذر تنفيذ العملية.');
    box.style.display = 'block';
}
function rcPost(payload, callback){
    payload.token = typeof utk !== 'undefined' ? utk : '';
    $.post('addons/rank_control_blk/system/action.php', payload, function(response){
        var data = response;
        if(typeof response === 'string'){
            try { data = JSON.parse(response); } catch(e) { data = {ok:false, message:'استجابة غير مفهومة من الخادم.'}; }
        }
        if(callback){ callback(data); }
    }).fail(function(){ rcMessage(false, 'تعذر الاتصال بالخادم.'); });
}
function rcSaveRank(){
    rcPost({action:'save_rank', rank_value:$('#rc_rank_value').val(), rank_name:$('#rc_rank_name').val(), rank_icon:$('#rc_rank_icon').val(), rank_group:$('#rc_rank_group').val()}, function(r){ rcMessage(!!r.ok, r.message); if(r.ok){ configAddons('rank_control_blk'); }});
}
function rcSetPermission(rank, enabled){
    rcPost({action:'set_permission', rank_value:rank, enabled:enabled ? 1 : 0}, function(r){ rcMessage(!!r.ok, r.message); if(!r.ok){ configAddons('rank_control_blk'); }});
}
function rcEditRank(rank, name, icon, group){
    $('#rc_rank_value').val(rank).prop('readonly', true);
    $('#rc_rank_name').val(name);
    $('#rc_rank_icon').val(icon);
    $('#rc_rank_group').val(group);
    document.getElementById('rc_rank_value').scrollIntoView({behavior:'smooth',block:'center'});
}
function rcClearRankForm(){
    $('#rc_rank_value').val('').prop('readonly', false);
    $('#rc_rank_name').val('');
    $('#rc_rank_group').val('member');
}
function rcDeleteRank(rank){
    if(!confirm('هل تريد حذف الرتبة رقم ' + rank + '؟ يجب ألا يكون عليها أعضاء.')) return;
    rcPost({action:'delete_rank', rank_value:rank}, function(r){ rcMessage(!!r.ok, r.message); if(r.ok){ configAddons('rank_control_blk'); }});
}
function rcGrantOwner(){
    var pass = $('#rc_owner_password').val();
    if(!pass){ rcMessage(false, 'أدخل كلمة مرور المالك الرئيسي للتأكيد.'); return; }
    rcPost({action:'grant_owner', lookup_type:$('#rc_lookup_type').val(), lookup_value:$('#rc_lookup_value').val(), owner_password:pass}, function(r){ rcMessage(!!r.ok, r.message); if(r.ok){ configAddons('rank_control_blk'); }});
}
function rcRevokeOwner(id){
    var pass = prompt('أدخل كلمة مرور المالك الرئيسي لسحب رتبة 100:');
    if(pass === null || pass === '') return;
    rcPost({action:'revoke_owner', target_id:id, owner_password:pass}, function(r){ rcMessage(!!r.ok, r.message); if(r.ok){ configAddons('rank_control_blk'); }});
}
</script>
