<?php
$load_addons = 'purchase_store';
require(__DIR__ . '/../../../system/config_addons.php');
require_once __DIR__ . '/../../../system/addon_menu_labels.php';
$psTemplateTitle = addonMenuLabelGet('purchase_store', 'متجر ترقيات المستوى والإعجابات');
if(!isMember($data) || !useWallet()){
    die();
}
$psSettings = psStoreSettings();
$psLevel = psStoreServiceEnabled('level', $psSettings) && (int)$data['user_rank'] >= psStoreServiceRank('level', $psSettings);
$psLikes = psStoreServiceEnabled('likes', $psSettings) && (int)$data['user_rank'] >= psStoreServiceRank('likes', $psSettings);
if(!$psLevel && !$psLikes){
    die();
}
$psLevelProducts = $psLevel ? psStoreAvailableProducts('level', $data['user_rank']) : array();
$psLikeProducts = $psLikes ? psStoreAvailableProducts('likes', $data['user_rank']) : array();
$psFirstService = $psLevel ? 'level' : 'likes';
$psRequestKey = bin2hex(random_bytes(32));
?>
<link rel="stylesheet" href="addons/purchase_store/files/purchase_store.css"/>
<div class="ps_shop" dir="rtl" data-request-key="<?php echo htmlspecialchars($psRequestKey, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="ps_hero">
        <div class="ps_hero_icon"><img src="addons/purchase_store/files/icon.svg" alt=""/></div>
        <div class="ps_hero_title"><?php echo htmlspecialchars($psTemplateTitle, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="ps_hero_text">اختر الخطة المناسبة، واشترِ لنفسك أو أهدها إلى صديقك</div>
    </div>
    <div class="ps_wallet_line">
        <span>رصيدك الحالي</span>
        <span class="ps_wallet_item"><img src="<?php echo goldIcon(); ?>" alt=""/> <?php echo number_format((int)$data['user_gold']); ?> ذهب</span>
        <span class="ps_wallet_item"><img src="<?php echo rubyIcon(); ?>" alt=""/> <?php echo number_format((int)$data['user_ruby']); ?> ياقوت</span>
    </div>
    <?php if($psLevel && $psLikes){ ?>
    <div class="ps_tabs">
        <button type="button" class="ps_tab <?php echo $psFirstService === 'level' ? 'active' : ''; ?>" onclick="psStoreTab('level', this)">شراء المستويات</button>
        <button type="button" class="ps_tab <?php echo $psFirstService === 'likes' ? 'active' : ''; ?>" onclick="psStoreTab('likes', this)">شراء الإعجابات</button>
    </div>
    <?php } ?>

    <?php if($psLevel){ ?>
    <section class="ps_products <?php echo $psFirstService === 'level' ? '' : 'ps_hidden'; ?>" id="ps_products_level">
        <div class="ps_section_title">خطط المستويات</div>
        <div class="ps_section_note">اختر إحدى الخطط لرفع مستوى حسابك أو حساب صديقك فوراً.</div>
        <?php if(empty($psLevelProducts)){ ?><div class="ps_empty">لا توجد خطط مستويات متاحة لرتبتك حالياً.</div><?php } else { foreach($psLevelProducts as $index => $p){ ?>
        <button type="button" class="ps_plan <?php echo $index === 0 ? 'selected' : ''; ?>" onclick="psSelectPlan(this)"
            data-service="level" data-id="<?php echo (int)$p['product_id']; ?>"
            data-title="<?php echo htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8'); ?>"
            data-quantity="<?php echo (int)$p['quantity']; ?>"
            data-self="<?php echo (int)$p['allow_self']; ?>" data-gift="<?php echo (int)$p['allow_gift']; ?>">
            <span class="ps_plan_name"><span class="ps_plan_star">★</span><?php echo htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="ps_plan_quantity">+<?php echo number_format((int)$p['quantity']); ?> مستوى</span>
            <span class="ps_plan_price"><img src="<?php echo htmlspecialchars(psStoreCurrencyIcon($p['currency']), ENT_QUOTES, 'UTF-8'); ?>" alt=""/><strong><?php echo number_format((int)$p['price']); ?></strong><small><?php echo psStoreCurrencyName($p['currency']); ?></small></span>
            <span class="ps_plan_check"><i class="fa fa-check"></i></span>
        </button>
        <?php }} ?>
    </section>
    <?php } ?>

    <?php if($psLikes){ ?>
    <section class="ps_products <?php echo $psFirstService === 'likes' ? '' : 'ps_hidden'; ?>" id="ps_products_likes">
        <div class="ps_section_title">خطط الإعجابات</div>
        <div class="ps_section_note">تضاف الإعجابات المشتراة إلى عداد إعجابات الملف الشخصي دون إنشاء سجلات إعجاب وهمية.</div>
        <?php if(empty($psLikeProducts)){ ?><div class="ps_empty">لا توجد خطط إعجابات متاحة لرتبتك حالياً. يمكن للإدارة إضافة الخطط وتسعيرها من إعدادات الإضافة.</div><?php } else { foreach($psLikeProducts as $index => $p){ ?>
        <button type="button" class="ps_plan ps_like_plan <?php echo $index === 0 ? 'selected' : ''; ?>" onclick="psSelectPlan(this)"
            data-service="likes" data-id="<?php echo (int)$p['product_id']; ?>"
            data-title="<?php echo htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8'); ?>"
            data-quantity="<?php echo (int)$p['quantity']; ?>"
            data-self="<?php echo (int)$p['allow_self']; ?>" data-gift="<?php echo (int)$p['allow_gift']; ?>">
            <span class="ps_plan_name"><span class="ps_plan_star">♥</span><?php echo htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="ps_plan_quantity">+<?php echo number_format((int)$p['quantity']); ?> إعجاب</span>
            <span class="ps_plan_price"><img src="<?php echo htmlspecialchars(psStoreCurrencyIcon($p['currency']), ENT_QUOTES, 'UTF-8'); ?>" alt=""/><strong><?php echo number_format((int)$p['price']); ?></strong><small><?php echo psStoreCurrencyName($p['currency']); ?></small></span>
            <span class="ps_plan_check"><i class="fa fa-check"></i></span>
        </button>
        <?php }} ?>
    </section>
    <?php } ?>

    <div class="ps_checkout" id="ps_checkout">
        <div class="ps_checkout_title">لمن تريد شراء هذه الخطة؟</div>
        <div class="ps_selected_summary" id="ps_selected_summary"></div>
        <div class="ps_checkout_actions">
            <button type="button" class="ps_buy_self" id="ps_buy_self" onclick="psStoreBuySelf()">شراء لنفسي</button>
            <button type="button" class="ps_buy_gift" id="ps_buy_gift" onclick="psStoreShowGift()">شراء لصديقي</button>
        </div>
        <div class="ps_gift_form ps_hidden" id="ps_gift_form">
            <label for="ps_target_name">اسم العضو الذي تريد إهداءه الخطة</label>
            <input id="ps_target_name" type="text" maxlength="60" placeholder="اكتب اسم المستخدم كما يظهر في الشات" autocomplete="off"/>
            <div class="ps_gift_form_actions">
                <button type="button" class="ps_buy_gift" id="ps_confirm_gift" onclick="psStoreConfirmGift()">تأكيد الشراء والإهداء</button>
                <button type="button" class="ps_cancel_gift" onclick="psStoreCancelGift()">إلغاء</button>
            </div>
        </div>
    </div>
    <div class="ps_gift_hint">يُخصم السعر من محفظتك بعد التأكد من نجاح إضافة المستويات أو الإعجابات إلى حساب المستفيد.</div>
</div>
<script data-cfasync="false">
var psSelectedPlan = null;
var psActiveService = '<?php echo $psFirstService; ?>';
var psRequestKey = $('.ps_shop').attr('data-request-key') || '';
psSelectPlan = function(button){
    var b = $(button);
    var service = b.attr('data-service');
    $('#ps_products_' + service + ' .ps_plan').removeClass('selected');
    b.addClass('selected');
    psActiveService = service;
    psSelectedPlan = {
        id: parseInt(b.attr('data-id'), 10),
        title: b.attr('data-title'),
        quantity: parseInt(b.attr('data-quantity'), 10),
        self: parseInt(b.attr('data-self'), 10),
        gift: parseInt(b.attr('data-gift'), 10),
        service: service
    };
    psStoreRefreshCheckout();
};
psStoreRefreshCheckout = function(){
    if(!psSelectedPlan){
        $('#ps_checkout').addClass('ps_hidden');
        return;
    }
    $('#ps_checkout').removeClass('ps_hidden');
    var unit = psSelectedPlan.service === 'level' ? 'مستوى' : 'إعجاب';
    $('#ps_selected_summary').text(psSelectedPlan.title + ' — +' + psSelectedPlan.quantity.toLocaleString() + ' ' + unit);
    $('#ps_buy_self').toggle(psSelectedPlan.self === 1);
    $('#ps_buy_gift').toggle(psSelectedPlan.gift === 1);
    if(psSelectedPlan.gift !== 1){
        psStoreCancelGift();
    }
};
psStoreTab = function(service, button){
    psActiveService = service;
    $('.ps_tab').removeClass('active');
    if(button){ $(button).addClass('active'); }
    $('#ps_products_level, #ps_products_likes').addClass('ps_hidden');
    $('#ps_products_' + service).removeClass('ps_hidden');
    psStoreCancelGift();
    var firstPlan = $('#ps_products_' + service + ' .ps_plan').first();
    if(firstPlan.length){
        psSelectPlan(firstPlan[0]);
    }
    else {
        psSelectedPlan = null;
        psStoreRefreshCheckout();
    }
};
psStoreShowGift = function(){
    if(!psSelectedPlan || psSelectedPlan.gift !== 1){ return; }
    $('#ps_gift_form').removeClass('ps_hidden');
    $('#ps_target_name').trigger('focus');
};
psStoreCancelGift = function(){
    $('#ps_gift_form').addClass('ps_hidden');
    $('#ps_target_name').val('');
};
psStoreBuySelf = function(){
    if(psSelectedPlan && psSelectedPlan.self === 1){ psStoreSubmitPurchase(0, ''); }
};
psStoreConfirmGift = function(){
    if(!psSelectedPlan || psSelectedPlan.gift !== 1){ return; }
    var target = $.trim($('#ps_target_name').val() || '');
    if(target === ''){
        callError('اكتب اسم العضو المستفيد أولاً');
        $('#ps_target_name').trigger('focus');
        return;
    }
    psStoreSubmitPurchase(1, target);
};
psStoreSubmitPurchase = function(gift, targetName){
    if(!psSelectedPlan || !psRequestKey){ callError(system.error); return; }
    var confirmText = gift ? 'هل تؤكد شراء ' + psSelectedPlan.title + ' وإهداءها إلى ' + targetName + '؟' : 'هل تؤكد شراء ' + psSelectedPlan.title + ' لحسابك؟';
    if(!confirm(confirmText)){ return; }
    var controls = $('#ps_checkout button');
    controls.prop('disabled', true).addClass('ps_loading');
    $.post('addons/purchase_store/system/action.php', {
        token: utk, purchase: 1, product_id: psSelectedPlan.id, gift: gift,
        target_name: targetName, request_key: psRequestKey
    }, function(r){
        if(r && r.code == 1){
            hideModal();
            callSuccess(r.emessage || 'تم الشراء بنجاح');
            setTimeout(function(){ window.location.reload(); }, 900);
        }
        else {
            callError((r && r.emessage) ? r.emessage : system.error);
            controls.prop('disabled', false).removeClass('ps_loading');
        }
    }, 'json').fail(function(){
        callError(system.error);
        controls.prop('disabled', false).removeClass('ps_loading');
    });
};
$(function(){
    var firstPlan = $('#ps_products_' + psActiveService + ' .ps_plan').first();
    if(firstPlan.length){ psSelectPlan(firstPlan[0]); }
    else { psStoreRefreshCheckout(); }
});
</script>
