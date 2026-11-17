<?php
$load_addons = 'vip';
require(__DIR__ . '/../../../system/config_addons.php');
require_once __DIR__ . '/../../../system/addon_menu_labels.php';
if(!canManageAddons()){ die(); }
if(!vipMembershipEnsureTables()){ die('تعذر تجهيز جداول متجر العضويات.'); }
$vipOffers = vipMembershipOffers(false);
$vipRanks = vipMembershipSellableRanks();
$vipContact = vipMembershipSettings();
?>
<?php echo elementTitle('إدارة متجر العضويات VIP', 'loadLob(\'admin/setting_addons.php\');'); ?>

<style>
.vm_admin{padding:12px;direction:rtl}.vm_card{background:var(--bcolor,#fff);border:1px solid rgba(120,120,120,.22);border-radius:11px;padding:13px;margin-bottom:13px}.vm_grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px}.vm_field{display:flex;flex-direction:column;gap:6px;margin-bottom:9px}.vm_field label{font-size:12px;font-weight:700}.vm_field input,.vm_field select{width:100%;min-height:39px;border:1px solid rgba(130,130,130,.45);border-radius:7px;padding:8px;background:transparent;color:inherit}.vm_btn{border:0;border-radius:7px;padding:9px 13px;margin:3px;cursor:pointer;font-weight:700}.vm_primary{background:#7353b8;color:#fff}.vm_danger{background:#b23b45;color:#fff}.vm_muted{background:#777;color:#fff}.vm_offer{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:11px 0;border-bottom:1px solid rgba(120,120,120,.18)}.vm_offer:last-child{border-bottom:0}.vm_small{font-size:11px;opacity:.72}.vm_badge{display:inline-block;border-radius:20px;background:rgba(115,83,184,.12);padding:3px 8px;font-size:11px}.vm_orders{padding:9px 0;border-bottom:1px solid rgba(120,120,120,.15)}
</style>
<div class="page_full vm_admin">
    <?php echo addonMenuLabelSettingsBlock('vip', 'متجر العضويات', 'يظهر الاسم داخل قائمة المتاجر الجانبية.'); ?>
    <div class="vm_card">
        <div class="bold bpad10">بيانات التواصل لشراء العضويات النقدية</div>
        <div class="vm_grid">
            <div class="vm_field"><label for="vm_whatsapp">رقم واتساب مع مفتاح الدولة</label><input id="vm_whatsapp" type="text" inputmode="numeric" maxlength="24" dir="ltr" value="<?php echo htmlspecialchars($vipContact['whatsapp_number'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="9665XXXXXXXX"/><span class="vm_small">اكتب الأرقام الدولية فقط، مثل 9665XXXXXXXX دون + أو مسافات.</span></div>
            <div class="vm_field"><label for="vm_whatsapp_enabled">زر التواصل واتساب</label><select id="vm_whatsapp_enabled"><option value="1" <?php echo ((int)$vipContact['whatsapp_enabled']===1?'selected':''); ?>>مفعل</option><option value="0" <?php echo ((int)$vipContact['whatsapp_enabled']!==1?'selected':''); ?>>معطل</option></select></div>
        </div>
        <button type="button" class="vm_btn vm_primary" onclick="vmSaveContactSettings()">حفظ بيانات التواصل</button>
    </div>
    <div class="vm_card">
        <div class="bold bpad10">إنشاء أو تعديل عرض عضوية</div>
        <input type="hidden" id="vm_offer_id" value="0"/>
        <div class="vm_grid">
            <div class="vm_field"><label for="vm_title">اسم العرض</label><input id="vm_title" maxlength="100" placeholder="مثال: عضوية مميزة"/></div>
            <div class="vm_field"><label for="vm_rank">الرتبة من رتب الشات</label><select id="vm_rank"><option value="">اختر الرتبة</option><?php foreach($vipRanks as $rank){ ?><option value="<?php echo (int)$rank; ?>"><?php echo htmlspecialchars(vipMembershipRankName($rank), ENT_QUOTES, 'UTF-8'); ?> (<?php echo (int)$rank; ?>)</option><?php } ?></select></div>
            <div class="vm_field"><label for="vm_payment_mode">طريقة البيع</label><select id="vm_payment_mode" onchange="vmTogglePaymentFields()"><option value="1">شراء من رصيد الشات (ذهب/روبي)</option><option value="2">شراء نقدي بالتواصل عبر واتساب</option></select></div>
            <div class="vm_field vm_wallet_field"><label for="vm_price">سعر المحفظة</label><input id="vm_price" type="number" min="0" max="2000000000" step="1" value="0"/></div>
            <div class="vm_field vm_wallet_field"><label for="vm_currency">عملة المحفظة</label><select id="vm_currency"><option value="1">الذهب</option><option value="2">الروبي</option></select></div>
            <div class="vm_field vm_cash_field" style="display:none"><label for="vm_cash_price">السعر النقدي</label><input id="vm_cash_price" type="number" min="0.01" max="999999999.99" step="0.01" value="0.00"/></div>
            <div class="vm_field vm_cash_field" style="display:none"><label for="vm_cash_currency">العملة النقدية</label><select id="vm_cash_currency"><option value="SAR">ريال سعودي (SAR)</option><option value="USD">دولار أمريكي (USD)</option></select></div>
            <div class="vm_field"><label for="vm_duration">مدة العضوية بالأيام</label><input id="vm_duration" type="number" min="0" max="36500" step="1" value="30"/><span class="vm_small">7 = أسبوع، 30 = شهر، 90 أو 360 أو أي عدد. اكتب 0 للعضوية الدائمة.</span></div>
            <div class="vm_field"><label for="vm_sort">ترتيب العرض داخل الرتبة</label><input id="vm_sort" type="number" min="0" max="2147483647" value="0"/></div>
        </div>
        <div class="vm_field"><label><input type="checkbox" id="vm_enabled" checked/> العرض متاح للشراء</label></div>
        <button type="button" class="vm_btn vm_primary" onclick="vmSaveOffer()"><i class="fa fa-save"></i> حفظ العرض</button>
        <button type="button" class="vm_btn vm_muted" onclick="vmResetForm()">عرض جديد</button>
        <div class="vm_small tpad10">تُقرأ الرتب مباشرة من rankList()، بما فيها الرتب الجديدة من إضافة التحكم بالرتب. رتب المشرفين والإدارة والرتب المخصصة الأقل من 100 متاحة للبيع. رتبة المالك 100 محمية ولا يمكن بيعها.</div>
    </div>
    <div class="vm_card">
        <div class="bold bpad10">عروض متجر العضويات — الأقوى أولاً</div>
        <?php if(empty($vipOffers)){ ?><div class="centered_element sub_text pad15">لا توجد عروض بعد. أنشئ أول عرض من الأعلى.</div><?php } else { foreach($vipOffers as $offer){ ?>
            <div class="vm_offer">
                <div style="min-width:0;flex:1">
                    <div class="bold"><?php echo htmlspecialchars($offer['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="vm_small">الرتبة: <?php echo htmlspecialchars(vipMembershipRankName((int)$offer['rank_value']), ENT_QUOTES, 'UTF-8'); ?> (#<?php echo (int)$offer['rank_value']; ?>) · <?php if((int)$offer['payment_mode']===2){ echo number_format((float)$offer['cash_price'],2).' '.htmlspecialchars($offer['cash_currency'],ENT_QUOTES,'UTF-8').' عبر واتساب'; } else { echo number_format((int)$offer['price']).' '.vipMembershipCurrencyName($offer['currency']); } ?> · <?php echo vipMembershipDurationLabel($offer['duration_days']); ?> · <?php echo ((int)$offer['enabled'] ? 'متاح' : 'متوقف'); ?></div>
                </div>
                <div style="white-space:nowrap">
                    <button type="button" class="vm_btn vm_muted" onclick="vmEditOffer(this)" data-id="<?php echo (int)$offer['offer_id']; ?>" data-title="<?php echo htmlspecialchars($offer['title'], ENT_QUOTES, 'UTF-8'); ?>" data-rank="<?php echo (int)$offer['rank_value']; ?>" data-price="<?php echo (int)$offer['price']; ?>" data-currency="<?php echo (int)$offer['currency']; ?>" data-mode="<?php echo (int)$offer['payment_mode']; ?>" data-cash-price="<?php echo htmlspecialchars((string)$offer['cash_price'], ENT_QUOTES, 'UTF-8'); ?>" data-cash-currency="<?php echo htmlspecialchars($offer['cash_currency'], ENT_QUOTES, 'UTF-8'); ?>" data-duration="<?php echo (int)$offer['duration_days']; ?>" data-enabled="<?php echo (int)$offer['enabled']; ?>" data-sort="<?php echo (int)$offer['sort_order']; ?>">تعديل</button>
                    <button type="button" class="vm_btn vm_danger" onclick="vmDeleteOffer(<?php echo (int)$offer['offer_id']; ?>)">حذف</button>
                </div>
            </div>
        <?php }} ?>
    </div>
    <div class="vm_card">
        <div class="bold bpad10">آخر عمليات الشراء من الذهب والروبي</div>
        <?php $orders = $mysqli->query("SELECT o.*, u.user_name FROM boom_vip_membership_orders o LEFT JOIN boom_users u ON u.user_id = o.buyer_id ORDER BY o.order_id DESC LIMIT 40"); if($orders && $orders->num_rows > 0){ while($order = $orders->fetch_assoc()){ ?>
            <div class="vm_orders"><div class="bold"><?php echo htmlspecialchars($order['user_name'] ?? 'عضو غير موجود', ENT_QUOTES, 'UTF-8'); ?> — <?php echo htmlspecialchars(vipMembershipRankName((int)$order['rank_value']), ENT_QUOTES, 'UTF-8'); ?></div><div class="vm_small"><?php echo number_format((int)$order['price']); ?> <?php echo vipMembershipCurrencyName($order['currency']); ?> · <?php echo vipMembershipDurationLabel($order['duration_days']); ?> · الرتبة السابقة: <?php echo (int)$order['previous_rank']; ?> · <?php echo date('Y-m-d H:i', (int)$order['created_at']); ?></div></div>
        <?php }} else { ?><div class="sub_text centered_element pad10">لا توجد عمليات شراء من المحفظة حتى الآن.</div><?php } ?>
    </div>
</div>
<script data-cfasync="false">
function vmPost(payload, done){ payload.token = utk; $.post('addons/vip/system/action.php', payload, function(r){ var d=r; if(typeof r==='string'){ try{ d=JSON.parse(r); }catch(e){ d={code:0,emessage:'استجابة غير مفهومة من الخادم'}; } } if(done){ done(d); } }, 'json').fail(function(){ callError(system.error); }); }
function vmTogglePaymentFields(){var cash=$('#vm_payment_mode').val()==='2';$('.vm_wallet_field').toggle(!cash);$('.vm_cash_field').toggle(cash);}
function vmSaveOffer(){vmPost({save_offer:1,offer_id:$('#vm_offer_id').val(),title:$('#vm_title').val(),rank_value:$('#vm_rank').val(),payment_mode:$('#vm_payment_mode').val(),price:$('#vm_price').val(),currency:$('#vm_currency').val(),cash_price:$('#vm_cash_price').val(),cash_currency:$('#vm_cash_currency').val(),duration_days:$('#vm_duration').val(),enabled:$('#vm_enabled').is(':checked')?1:0,sort_order:$('#vm_sort').val()},function(r){if(r.code==1){callSuccess(r.emessage);configAddons('vip');}else{callError(r.emessage||system.error);}});}
function vmSaveContactSettings(){vmPost({save_contact_settings:1,whatsapp_number:$('#vm_whatsapp').val(),whatsapp_enabled:$('#vm_whatsapp_enabled').val()},function(r){if(r.code==1){callSuccess(r.emessage);}else{callError(r.emessage||system.error);}});}
function vmResetForm(){$('#vm_offer_id').val(0);$('#vm_title').val('');$('#vm_rank').val('');$('#vm_payment_mode').val('1');$('#vm_price').val(0);$('#vm_currency').val('1');$('#vm_cash_price').val('0.00');$('#vm_cash_currency').val('SAR');$('#vm_duration').val(30);$('#vm_enabled').prop('checked',true);$('#vm_sort').val(0);vmTogglePaymentFields();}
function vmEditOffer(btn){var b=$(btn);$('#vm_offer_id').val(b.attr('data-id'));$('#vm_title').val(b.attr('data-title'));$('#vm_rank').val(b.attr('data-rank'));$('#vm_payment_mode').val(b.attr('data-mode'));$('#vm_price').val(b.attr('data-price'));$('#vm_currency').val(b.attr('data-currency'));$('#vm_cash_price').val(b.attr('data-cash-price'));$('#vm_cash_currency').val(b.attr('data-cash-currency'));$('#vm_duration').val(b.attr('data-duration'));$('#vm_enabled').prop('checked',b.attr('data-enabled')==='1');$('#vm_sort').val(b.attr('data-sort'));vmTogglePaymentFields();document.getElementById('vm_offer_id').scrollIntoView({behavior:'smooth',block:'center'});}
function vmDeleteOffer(id){if(!confirm('هل تريد حذف عرض العضوية؟'))return;vmPost({delete_offer:1,offer_id:id},function(r){if(r.code==1){callSuccess(r.emessage);configAddons('vip');}else{callError(r.emessage||system.error);}});}
$(function(){vmTogglePaymentFields();});
</script>
