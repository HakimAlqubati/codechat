<?php
$load_addons = 'DSG_Admin_User_Manager';
require_once('../../../system/config_addons.php');

if(!dsgCanAccessManager()){
    echo '<div class="modal_pad">ليست لديك صلاحية استخدام إدارة الأعضاء.</div>';
    exit;
}
if(!dsgAdminTableExists('boom_dsg_user_manager_logs')){
    echo '<div class="modal_pad">تحتاج قاعدة بيانات الإضافة إلى التحديث. شغّل ملف upgrade_v2.sql أولاً.</div>';
    exit;
}

$targetId = isset($_POST['target']) ? filter_var($_POST['target'], FILTER_VALIDATE_INT) : false;
if($targetId === false || $targetId <= 0){
    echo '<div class="modal_pad">رقم العضو غير صالح.</div>';
    exit;
}

$userStmt = $mysqli->prepare('SELECT user_id, user_name, user_tumb, user_rank, user_bot, user_delete, user_gold, user_ruby, user_level FROM boom_users WHERE user_id = ? LIMIT 1');
if(!$userStmt){
    echo '<div class="modal_pad">تعذر تحميل بيانات العضو.</div>';
    exit;
}
$userStmt->bind_param('i', $targetId);
$userStmt->execute();
$userResult = $userStmt->get_result();
$user = $userResult ? $userResult->fetch_assoc() : null;
$userStmt->close();

if(!$user){
    echo '<div class="modal_pad">العضو غير موجود.</div>';
    exit;
}
if(!dsgCanEditManagedUser($user)){
    echo '<div class="modal_pad">لا تملك صلاحية تعديل هذا العضو. يمكنك تعديل الأعضاء الأقل رتبة منك فقط.</div>';
    exit;
}

$walletEnabled = isset($setting['use_wallet']) && (int)$setting['use_wallet'] === 1;
$levelEnabled = isset($setting['use_level']) && (int)$setting['use_level'] === 1;
$likesEnabled = isset($setting['use_like']) && (int)$setting['use_like'] === 1 && dsgAdminTableExists('boom_dsg_admin_likes');

$adminLikes = $likesEnabled ? dsgGetAdminLikes($targetId) : '0';
$visibleLikes = $likesEnabled ? dsgGetVisibleLikeTotal($targetId) : 0;

$tabs = array();
if($walletEnabled){
    $tabs['gold'] = array('label' => 'الذهب', 'icon' => 'fa-coins', 'color' => '#d6a52b', 'value' => (string)$user['user_gold']);
    $tabs['ruby'] = array('label' => 'الياقوت', 'icon' => 'fa-gem', 'color' => '#d94a72', 'value' => (string)$user['user_ruby']);
}
if($levelEnabled){
    $tabs['level'] = array('label' => 'المستوى', 'icon' => 'fa-star', 'color' => '#4685d5', 'value' => (string)$user['user_level']);
}
if($likesEnabled){
    $tabs['likes'] = array('label' => 'الإعجابات الإضافية', 'icon' => 'fa-heart', 'color' => '#d94a72', 'value' => (string)$adminLikes);
}
$tabKeys = array_keys($tabs);
$firstTab = !empty($tabKeys) ? $tabKeys[0] : 'history';

$history = array();
if(dsgAdminTableExists('boom_dsg_user_manager_logs')){
    $historyStmt = $mysqli->prepare('SELECT l.log_id, l.admin_id, l.stat_key, l.operation, l.old_value, l.new_value, l.change_amount, l.reason, l.created_at, a.user_name AS admin_name FROM boom_dsg_user_manager_logs l LEFT JOIN boom_users a ON a.user_id = l.admin_id WHERE l.target_id = ? ORDER BY l.log_id DESC LIMIT 20');
    if($historyStmt){
        $historyStmt->bind_param('i', $targetId);
        $historyStmt->execute();
        $historyResult = $historyStmt->get_result();
        if($historyResult){
            while($entry = $historyResult->fetch_assoc()){
                $history[] = $entry;
            }
        }
        $historyStmt->close();
    }
}

function dsgH($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
function dsgStatLabel($key){
    $labels = array('gold' => 'الذهب', 'ruby' => 'الياقوت', 'level' => 'المستوى', 'likes' => 'الإعجابات الإضافية');
    return isset($labels[$key]) ? $labels[$key] : $key;
}
function dsgOperationLabel($operation){
    $labels = array('set' => 'تعيين', 'add' => 'إضافة', 'subtract' => 'خصم');
    return isset($labels[$operation]) ? $labels[$operation] : $operation;
}
?>
<div class="modal_pad dsg-manager-modal" dir="rtl">
    <div class="modal_title" style="display:flex;align-items:center;gap:10px;">
        <img src="<?php echo dsgH(myAvatar($user['user_tumb'])); ?>" alt="" style="width:38px;height:38px;border-radius:50%;object-fit:cover;">
        <span>إدارة بيانات العضو: <?php echo dsgH($user['user_name']); ?></span>
    </div>

    <?php if(empty($tabs)){ ?>
        <div class="setting_element">
            <p class="label">لا توجد أنظمة قابلة للإدارة حالياً.</p>
            <p class="text_small">فعّل المحفظة أو المستويات أو إعجابات الملفات الشخصية من إعدادات CodyChat.</p>
        </div>
    <?php } else { ?>
        <div class="tab_menu">
            <ul>
                <?php foreach($tabs as $key => $tab){ ?>
                    <li class="tab_menu_item dsg-manager-tab <?php echo $key === $firstTab ? 'tab_selected' : ''; ?>" data-tab="<?php echo dsgH($key); ?>">
                        <i class="fa <?php echo dsgH($tab['icon']); ?>" style="color:<?php echo dsgH($tab['color']); ?>;"></i>
                        <?php echo dsgH($tab['label']); ?>
                    </li>
                <?php } ?>
                <li class="tab_menu_item dsg-manager-tab" data-tab="history"><i class="fa fa-clock-rotate-left"></i> سجل التعديلات</li>
            </ul>
        </div>

        <div class="page_element">
            <?php foreach($tabs as $key => $tab){ ?>
                <div class="tab_zone dsg-stat-zone" id="dsg_<?php echo dsgH($key); ?>_tab" style="display:<?php echo $key === $firstTab ? 'block' : 'none'; ?>;">
                    <div class="setting_element">
                        <p class="label">الرصيد الحالي: <?php echo dsgH($tab['label']); ?></p>
                        <div style="font-size:25px;font-weight:700;color:<?php echo dsgH($tab['color']); ?>;margin:8px 0;">
                            <?php echo number_format((float)$tab['value']); ?>
                        </div>
                        <?php if($key === 'likes'){ ?>
                            <p class="text_small">هذا الرصيد خاص بالإعجابات الإضافية التي تمنحها الإدارة. لا يغيّر الإعجابات الطبيعية ولا الإعجابات المشتراة من المتجر.</p>
                            <p class="text_small">إجمالي الإعجابات الظاهر حالياً في الملف الشخصي: <strong><?php echo number_format((float)$visibleLikes); ?></strong></p>
                        <?php } ?>
                    </div>
                    <div class="setting_element">
                        <p class="label">طريقة التعديل</p>
                        <select id="dsg_<?php echo dsgH($key); ?>_method" class="selectit dsg-method" data-stat="<?php echo dsgH($key); ?>">
                            <option value="set">تعيين قيمة محددة</option>
                            <option value="adjust">إضافة أو خصم</option>
                        </select>
                    </div>
                    <div class="setting_element dsg-set-section" id="dsg_<?php echo dsgH($key); ?>_set_section">
                        <p class="label">القيمة الجديدة</p>
                        <input type="number" inputmode="numeric" step="1" min="<?php echo $key === 'level' ? '1' : '0'; ?>" id="dsg_<?php echo dsgH($key); ?>_set" class="full_input" value="<?php echo dsgH($tab['value']); ?>">
                    </div>
                    <div class="setting_element dsg-adjust-section" id="dsg_<?php echo dsgH($key); ?>_adjust_section" style="display:none;">
                        <p class="label">مقدار الإضافة أو الخصم</p>
                        <input type="number" inputmode="numeric" step="1" id="dsg_<?php echo dsgH($key); ?>_adjust" class="full_input" placeholder="مثال: 100 أو -50">
                        <p class="text_small">استخدم رقماً موجباً للإضافة وسالباً للخصم.</p>
                    </div>
                    <div class="setting_element">
                        <p class="label">سبب التعديل (اختياري)</p>
                        <textarea id="dsg_<?php echo dsgH($key); ?>_reason" class="full_input" rows="2" maxlength="255" placeholder="اكتب سبب التعديل ليظهر في إشعار العضو والسجل"></textarea>
                    </div>
                </div>
            <?php } ?>

            <div class="tab_zone" id="dsg_history_tab" style="display:none;">
                <div class="setting_element">
                    <p class="label">آخر 20 تعديلاً على هذا العضو</p>
                    <?php if(empty($history)){ ?>
                        <div class="text_small">لا توجد تعديلات مسجلة لهذا العضو حتى الآن.</div>
                    <?php } else { ?>
                        <div style="overflow-x:auto;">
                            <table class="table table-striped" style="width:100%;font-size:12px;">
                                <thead><tr><th>التاريخ</th><th>الإداري</th><th>الخاصية</th><th>القديم</th><th>الجديد</th><th>السبب</th></tr></thead>
                                <tbody>
                                <?php foreach($history as $entry){ ?>
                                    <tr>
                                        <td><?php echo dsgH(displayDate((int)$entry['created_at'])); ?></td>
                                        <td><?php echo dsgH($entry['admin_name'] ?: ('#' . $entry['admin_id'])); ?></td>
                                        <td><?php echo dsgH(dsgStatLabel($entry['stat_key'])); ?><br><small><?php echo dsgH(dsgOperationLabel($entry['operation'])); ?></small></td>
                                        <td><?php echo dsgH(number_format((float)$entry['old_value'])); ?></td>
                                        <td><?php echo dsgH(number_format((float)$entry['new_value'])); ?></td>
                                        <td><?php echo dsgH($entry['reason'] !== '' ? $entry['reason'] : '—'); ?></td>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>

        <input type="hidden" id="dsg_manager_user_id" value="<?php echo (int)$user['user_id']; ?>">
        <div class="modal_control dsg-save-controls">
            <button onclick="dsgSaveUserStat()" type="button" class="reg_button ok_btn tmargin10 dsg-save-button"><i class="fa fa-save"></i> حفظ التعديل</button>
            <button type="button" class="reg_button default_btn close_modal tmargin10">إغلاق</button>
        </div>
    <?php } ?>
</div>

<script data-cfasync="false" type="text/javascript">
(function($){
    $(document).off('click.dsgManagerTabs', '.dsg-manager-tab').on('click.dsgManagerTabs', '.dsg-manager-tab', function(){
        var tab = $(this).attr('data-tab');
        var $modal = $(this).closest('.dsg-manager-modal');
        $modal.find('.dsg-manager-tab').removeClass('tab_selected');
        $(this).addClass('tab_selected');
        $modal.find('.tab_zone').hide();
        $modal.find('#dsg_' + tab + '_tab').show();
        $modal.find('.dsg-save-controls').toggle(tab !== 'history');
        if(typeof selectIt === 'function'){
            selectIt();
        }
    });

    $(document).off('change.dsgManagerMethod', '.dsg-method').on('change.dsgManagerMethod', '.dsg-method', function(){
        var stat = $(this).attr('data-stat');
        var isSet = $(this).val() === 'set';
        $('#dsg_' + stat + '_set_section').toggle(isSet);
        $('#dsg_' + stat + '_adjust_section').toggle(!isSet);
    });
})(jQuery);

window.dsgSaveUserStat = function(){
    var $modal = $('.dsg-manager-modal');
    var $activeTab = $modal.find('.dsg-manager-tab.tab_selected');
    var stat = $activeTab.attr('data-tab');
    if(!stat || stat === 'history'){
        return;
    }
    var method = $('#dsg_' + stat + '_method').val();
    var value = method === 'set' ? $('#dsg_' + stat + '_set').val() : $('#dsg_' + stat + '_adjust').val();
    if(value === '' || !/^[+-]?\d+$/.test(String(value))){
        callError('أدخل رقماً صحيحاً بدون كسور.');
        return;
    }
    if(method === 'adjust' && Number(value) === 0){
        callError('أدخل مقداراً غير صفري للإضافة أو الخصم.');
        return;
    }

    var userId = $('#dsg_manager_user_id').val();
    var reason = $('#dsg_' + stat + '_reason').val() || '';
    var $button = $modal.find('.dsg-save-button');
    var original = $button.html();
    $button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> جارٍ الحفظ...');

    $.post('addons/DSG_Admin_User_Manager/system/action.php', {
        action: 'save_user_stat',
        user_id: userId,
        stat: stat,
        method: method,
        value: value,
        reason: reason,
        token: utk
    }, function(response){
        var result = response;
        try {
            if(typeof response === 'string'){
                result = JSON.parse(response);
            }
        } catch(e){
            callError('استجابة غير مفهومة من الخادم.');
            return;
        }
        if(result && result.code == 1){
            var successMessage = result.message || 'تم حفظ التعديل بنجاح.';
            $.post('addons/DSG_Admin_User_Manager/system/edituser_simple.php', {
                target: userId,
                token: utk
            }, function(markup){
                hideModal();
                showModal(markup, 560);
                callSuccess(successMessage);
            }).fail(function(){
                hideModal();
                callSuccess(successMessage);
            });
        } else {
            callError((result && (result.error || result.emessage)) || 'تعذر حفظ التعديل.');
        }
    }).fail(function(){
        callError('تعذر الاتصال بالخادم. لم يتم تأكيد حفظ التعديل.');
    }).always(function(){
        $button.prop('disabled', false).html(original);
    });
};
</script>
