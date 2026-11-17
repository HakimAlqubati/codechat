<?php
$load_addons = 'purchase_store';
require(__DIR__ . '/../../../system/config_addons.php');
require_once __DIR__ . '/../../../system/addon_menu_labels.php';
if(!canManageAddons()){
    die();
}
$psSettings = psStoreSettings();
$psProducts = psStoreAllProducts();
?>
<?php echo elementTitle('إدارة متجر المستويات والإعجابات', 'loadLob(\'admin/setting_addons.php\');'); ?>
<?php echo addonMenuLabelSettingsBlock('purchase_store', 'متجر ترقيات المستوى والإعجابات', 'يظهر هذا الاسم تحت تصنيف المتاجر في القائمة الجانبية.'); ?>

<link rel="stylesheet" href="addons/purchase_store/files/purchase_store.css"/>
<div class="page_full">
    <div class="page_element">
        <div class="config_section ps_admin_section">
            <div class="bold bpad10">التحكم العام بالخدمات</div>
            <div class="setting_element">
                <p class="label">تفعيل شراء المستويات</p>
                <select id="ps_level_enabled"><?php echo onOff($psSettings['level_enabled']); ?></select>
                <p class="sub_text text_small">يشترط أن يكون نظام المستويات الأصلي مفعلاً في إعدادات الشات.</p>
            </div>
            <div class="setting_element">
                <p class="label">أقل رتبة لفتح خدمة شراء المستويات</p>
                <select id="ps_level_rank"><?php echo listRankMember($psSettings['level_min_rank']); ?></select>
            </div>
            <div class="setting_element">
                <p class="label">تفعيل شراء الإعجابات</p>
                <select id="ps_likes_enabled"><?php echo onOff($psSettings['likes_enabled']); ?></select>
                <p class="sub_text text_small">الإعجابات المشتراة تضاف إلى عداد إعجابات الملف الشخصي دون إنشاء سجلات إعجاب وهمية.</p>
            </div>
            <div class="setting_element">
                <p class="label">أقل رتبة لفتح خدمة شراء الإعجابات</p>
                <select id="ps_likes_rank"><?php echo listRankMember($psSettings['likes_min_rank']); ?></select>
            </div>
            <button type="button" class="reg_button theme_btn tmargin10" onclick="psSaveSettings();"><i class="fa fa-save"></i> حفظ إعدادات الخدمات</button>
        </div>

        <div class="config_section ps_admin_section">
            <div class="bold bpad10">إضافة أو تعديل باقة شراء</div>
            <input type="hidden" id="ps_product_id" value="0"/>
            <div class="setting_element">
                <p class="label">نوع الخدمة</p>
                <select id="ps_service">
                    <option value="level">شراء مستويات</option>
                    <option value="likes">شراء إعجابات</option>
                </select>
            </div>
            <div class="setting_element">
                <p class="label">اسم الباقة</p>
                <input id="ps_title" class="full_input" type="text" maxlength="100" placeholder="مثال: باقة 100 مستوى"/>
            </div>
            <div class="setting_element">
                <p class="label">الكمية التي يحصل عليها العضو</p>
                <input id="ps_quantity" class="full_input" type="number" min="1" max="2147483647" step="1" placeholder="مثال: 100"/>
            </div>
            <div class="setting_element">
                <p class="label">سعر الباقة</p>
                <input id="ps_price" class="full_input" type="number" min="1" max="2147483647" step="1" placeholder="مثال: 2400000"/>
            </div>
            <div class="setting_element">
                <p class="label">عملة الدفع</p>
                <select id="ps_currency">
                    <option value="1">الذهب</option>
                    <option value="2">الياقوت</option>
                </select>
            </div>
            <div class="setting_element">
                <p class="label">أقل رتبة مسموح لها بشراء هذه الباقة وما فوقها</p>
                <select id="ps_min_rank"><?php echo listRankMember(1); ?></select>
            </div>
            <div class="setting_element">
                <p class="label">ترتيب ظهور الباقة</p>
                <input id="ps_sort_order" class="full_input" type="number" step="1" value="0"/>
            </div>
            <div class="ps_admin_checks">
                <label><input type="checkbox" id="ps_enabled" checked/> الباقة مفعلة</label>
                <label><input type="checkbox" id="ps_allow_self" checked/> السماح بالشراء للنفس</label>
                <label><input type="checkbox" id="ps_allow_gift" checked/> السماح بشراء الباقة لصديق</label>
            </div>
            <div class="ps_admin_buttons">
                <button type="button" class="reg_button theme_btn" onclick="psSaveProduct();"><i class="fa fa-save"></i> حفظ الباقة</button>
                <button type="button" class="reg_button default_btn" onclick="psResetProduct();">باقة جديدة</button>
            </div>
            <p class="sub_text text_small tmargin10">الكمية هي مقدار الزيادة التي تضاف فوراً؛ مثال: باقة 100 مستوى ترفع مستوى العضو الحالي بمقدار 100، وليست تعيين المستوى النهائي إلى 100.</p>
        </div>

        <div class="config_section ps_admin_section">
            <div class="bold bpad10">الباقات الحالية</div>
            <div class="ps_admin_products">
                <?php if(empty($psProducts)){ ?>
                    <div class="pad_box centered_element sub_text">لا توجد باقات حتى الآن.</div>
                <?php } else { foreach($psProducts as $p){ ?>
                    <div class="ps_admin_product">
                        <div class="ps_admin_product_info">
                            <div class="bold"><?php echo htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="text_small sub_text">
                                <?php echo ($p['service'] === 'level' ? 'مستويات: ' : 'إعجابات: ') . number_format((int)$p['quantity']); ?>
                                · <?php echo number_format((int)$p['price']) . ' ' . psStoreCurrencyName($p['currency']); ?>
                                · رتبة <?php echo htmlspecialchars(rankTitle((int)$p['min_rank']), ENT_QUOTES, 'UTF-8'); ?>
                                · <?php echo ((int)$p['enabled'] ? 'مفعلة' : 'متوقفة'); ?>
                            </div>
                        </div>
                        <div class="ps_admin_product_actions">
                            <button type="button" class="button default_btn" onclick="psEditProduct(this);"
                                data-id="<?php echo (int)$p['product_id']; ?>"
                                data-service="<?php echo $p['service']; ?>"
                                data-title="<?php echo htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-quantity="<?php echo (int)$p['quantity']; ?>"
                                data-price="<?php echo (int)$p['price']; ?>"
                                data-currency="<?php echo (int)$p['currency']; ?>"
                                data-rank="<?php echo (int)$p['min_rank']; ?>"
                                data-enabled="<?php echo (int)$p['enabled']; ?>"
                                data-self="<?php echo (int)$p['allow_self']; ?>"
                                data-gift="<?php echo (int)$p['allow_gift']; ?>"
                                data-sort="<?php echo (int)$p['sort_order']; ?>">تعديل</button>
                            <button type="button" class="button delete_btn" onclick="psDeleteProduct(<?php echo (int)$p['product_id']; ?>);">حذف</button>
                        </div>
                    </div>
                <?php }} ?>
            </div>
        </div>

        <div class="config_section ps_admin_section">
            <div class="bold bpad10">آخر عمليات الشراء</div>
            <?php
            $psOrders = $mysqli->query("SELECT o.*, b.user_name AS buyer_name, r.user_name AS recipient_name FROM boom_purchase_store_orders o LEFT JOIN boom_users b ON b.user_id = o.buyer_id LEFT JOIN boom_users r ON r.user_id = o.recipient_id ORDER BY o.order_id DESC LIMIT 30");
            if($psOrders && $psOrders->num_rows > 0){
                while($o = $psOrders->fetch_assoc()){
                    $serviceLabel = $o['service'] === 'level' ? 'مستويات' : 'إعجابات';
                    echo '<div class="ps_admin_order"><div class="bold">' . htmlspecialchars($o['product_title'], ENT_QUOTES, 'UTF-8') . '</div><div class="text_small sub_text">المشتري: ' . htmlspecialchars((string)$o['buyer_name'], ENT_QUOTES, 'UTF-8') . ' · المستفيد: ' . htmlspecialchars((string)$o['recipient_name'], ENT_QUOTES, 'UTF-8') . ' · ' . number_format((int)$o['quantity']) . ' ' . $serviceLabel . ' · ' . number_format((int)$o['price']) . ' ' . psStoreCurrencyName($o['currency']) . ' · ' . date('Y-m-d H:i', (int)$o['created_at']) . '</div></div>';
                }
            } else {
                echo '<div class="pad_box centered_element sub_text">لا توجد عمليات شراء بعد.</div>';
            }
            ?>
        </div>
    </div>
</div>
<script data-cfasync="false">
psSaveSettings = function(){
    $.post('addons/purchase_store/system/action.php', {
        token: utk, save_settings: 1,
        level_enabled: $('#ps_level_enabled').val(),
        level_min_rank: $('#ps_level_rank').val(),
        likes_enabled: $('#ps_likes_enabled').val(),
        likes_min_rank: $('#ps_likes_rank').val()
    }, function(r){
        if(r.code == 1){ callSuccess(r.emessage || 'تم حفظ الإعدادات'); }
        else { callError(r.emessage || system.error); }
    }, 'json').fail(function(){ callError(system.error); });
};
psResetProduct = function(){
    $('#ps_product_id').val(0); $('#ps_service').val('level'); $('#ps_title').val('');
    $('#ps_quantity').val(''); $('#ps_price').val(''); $('#ps_currency').val('1');
    $('#ps_min_rank').val('1'); $('#ps_sort_order').val(0);
    $('#ps_enabled').prop('checked', true); $('#ps_allow_self').prop('checked', true); $('#ps_allow_gift').prop('checked', true);
};
psEditProduct = function(btn){
    var b = $(btn);
    $('#ps_product_id').val(b.attr('data-id')); $('#ps_service').val(b.attr('data-service'));
    $('#ps_title').val(b.attr('data-title')); $('#ps_quantity').val(b.attr('data-quantity'));
    $('#ps_price').val(b.attr('data-price')); $('#ps_currency').val(b.attr('data-currency'));
    $('#ps_min_rank').val(b.attr('data-rank')); $('#ps_sort_order').val(b.attr('data-sort'));
    $('#ps_enabled').prop('checked', b.attr('data-enabled') == '1');
    $('#ps_allow_self').prop('checked', b.attr('data-self') == '1');
    $('#ps_allow_gift').prop('checked', b.attr('data-gift') == '1');
    $('html, body').animate({scrollTop: $('#ps_product_id').offset().top - 80}, 250);
};
psSaveProduct = function(){
    var title = $('#ps_title').val().trim();
    var quantity = $('#ps_quantity').val();
    var price = $('#ps_price').val();
    if(title == '' || !/^\\d+$/.test(quantity) || parseInt(quantity, 10) < 1 || !/^\\d+$/.test(price) || parseInt(price, 10) < 1){
        callError('أكمل اسم الباقة والكمية والسعر بأرقام صحيحة'); return;
    }
    $.post('addons/purchase_store/system/action.php', {
        token: utk, save_product: 1,
        product_id: $('#ps_product_id').val(), service: $('#ps_service').val(),
        title: title, quantity: quantity, price: price, currency: $('#ps_currency').val(),
        min_rank: $('#ps_min_rank').val(), sort_order: $('#ps_sort_order').val(),
        enabled: $('#ps_enabled').is(':checked') ? 1 : 0,
        allow_self: $('#ps_allow_self').is(':checked') ? 1 : 0,
        allow_gift: $('#ps_allow_gift').is(':checked') ? 1 : 0
    }, function(r){
        if(r.code == 1){ callSuccess(r.emessage || 'تم حفظ الباقة'); configAddons('purchase_store'); }
        else { callError(r.emessage || system.error); }
    }, 'json').fail(function(){ callError(system.error); });
};
psDeleteProduct = function(id){
    if(!confirm('هل تريد حذف هذه الباقة؟')) return;
    $.post('addons/purchase_store/system/action.php', {token: utk, delete_product: 1, product_id: id}, function(r){
        if(r.code == 1){ callSuccess(r.emessage || 'تم حذف الباقة'); configAddons('purchase_store'); }
        else { callError(r.emessage || system.error); }
    }, 'json').fail(function(){ callError(system.error); });
};
</script>
