<?php
$load_addons = 'vip';
require(__DIR__ . '/../../../system/config_addons.php');
require_once __DIR__ . '/../../../system/addon_menu_labels.php';
$vipStoreTitle = addonMenuLabelGet('vip', 'متجر العضويات');
if(!isMember($data) || isBot($data) || (int)$data['user_rank'] >= 100){ die(); }
vipMembershipCleanUser((int)$data['user_id']);
$offers = vipMembershipOffers(true);
$currentMembership = vipMembershipGetUser((int)$data['user_id']);
$vipContact = vipMembershipSettings();
$waNumber = vipMembershipCleanWhatsApp($vipContact['whatsapp_number'] ?? '');
$cashContactEnabled = ((int)($vipContact['whatsapp_enabled'] ?? 0) === 1 && $waNumber !== '');
?>
<link rel="stylesheet" href="addons/vip/files/vip.css"/>
<div class="vm_store" dir="rtl">
    <div class="vm_store_head"><div class="vm_store_icon"><img src="addons/vip/files/icon.png" alt="VIP"/></div><div><div class="vm_store_title"><?php echo htmlspecialchars($vipStoreTitle, ENT_QUOTES, 'UTF-8'); ?></div><div class="vm_store_sub">اختر رتبتك وطريقة الحصول عليها</div></div></div>
    <?php if(useWallet()){ ?><div class="vm_wallet"><span><img src="<?php echo goldIcon(); ?>" alt=""/> <?php echo number_format((int)$data['user_gold']); ?> ذهب</span><span><img src="<?php echo rubyIcon(); ?>" alt=""/> <?php echo number_format((int)$data['user_ruby']); ?> روبي</span></div><?php } ?>
    <?php if(!empty($currentMembership)){ ?><div class="vm_current"><b>عضويتك الحالية:</b> <?php echo htmlspecialchars(vipMembershipRankName((int)$currentMembership['rank_value']), ENT_QUOTES, 'UTF-8'); ?><span><?php echo ((int)$currentMembership['expires_at'] === 0) ? 'دائمة للأبد' : 'تنتهي في ' . date('Y-m-d H:i', (int)$currentMembership['expires_at']); ?></span></div><?php } ?>
    <div class="vm_offer_grid">
        <?php $shown = 0; foreach($offers as $offer){ if((int)$offer['rank_value'] < (int)$data['user_rank']){ continue; } $shown++; $cash = ((int)$offer['payment_mode'] === 2); $msg = 'مرحباً، أرغب بشراء ' . $offer['title'] . ' - ' . vipMembershipRankName((int)$offer['rank_value']) . ' - المدة: ' . vipMembershipDurationLabel($offer['duration_days']) . ' - السعر: ' . number_format((float)$offer['cash_price'],2) . ' ' . $offer['cash_currency']; $waUrl = 'https://wa.me/' . $waNumber . '?text=' . rawurlencode($msg); ?>
            <div class="vm_offer_card <?php echo $cash ? 'vm_cash_offer' : 'vm_wallet_offer'; ?>" <?php echo $cash ? 'onclick="vipToggleContact(event,this)" tabindex="0" role="button"' : ''; ?>>
                <div class="vm_offer_rank"><img src="default_images/rank/<?php echo htmlspecialchars(rankIcon((int)$offer['rank_value']), ENT_QUOTES, 'UTF-8'); ?>" alt=""/></div>
                <div class="vm_offer_title"><?php echo htmlspecialchars($offer['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="vm_offer_rankname"><?php echo htmlspecialchars(vipMembershipRankName((int)$offer['rank_value']), ENT_QUOTES, 'UTF-8'); ?> <small>#<?php echo (int)$offer['rank_value']; ?></small></div>
                <div class="vm_offer_duration"><i class="fa fa-clock-o"></i> <?php echo vipMembershipDurationLabel($offer['duration_days']); ?></div>
                <?php if($cash){ ?><div class="vm_offer_price vm_cash_price"><?php echo number_format((float)$offer['cash_price'],2); ?> <small><?php echo htmlspecialchars($offer['cash_currency'], ENT_QUOTES, 'UTF-8'); ?></small></div><div class="vm_contact_bar"><span>لشراء هذه الرتبة نقدًا</span><?php if($cashContactEnabled){ ?><a class="vm_whatsapp_btn" href="<?php echo htmlspecialchars($waUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" onclick="event.stopPropagation()"><i class="fa fa-whatsapp"></i> تواصل للشراء عبر واتساب</a><?php } else { ?><span class="vm_whatsapp_unavailable">رقم واتساب غير مضاف من الإدارة</span><?php } ?></div><?php } else { ?><div class="vm_offer_price"><img src="<?php echo vipMembershipCurrencyIcon($offer['currency']); ?>" alt=""/> <?php echo number_format((int)$offer['price']); ?> <small><?php echo vipMembershipCurrencyName($offer['currency']); ?></small></div><?php if(useWallet()){ ?><button type="button" class="vm_buy_btn" onclick="event.stopPropagation();vipBuyMembership(<?php echo (int)$offer['offer_id']; ?>)">شراء العضوية</button><?php } else { ?><div class="vm_whatsapp_unavailable">محفظة الشات غير مفعلة</div><?php } ?><?php } ?>
            </div>
        <?php } ?>
        <?php if($shown === 0){ ?><div class="vm_empty">لا توجد عضويات متاحة لرتبتك حاليًا.</div><?php } ?>
    </div>
    <div class="vm_store_note">الرتب الأقوى تظهر أولاً. شراء المحفظة يمنح العضوية تلقائيًا بعد الخصم الناجح؛ أما العرض النقدي فيفتح محادثة واتساب مع الإدارة ولا يمنح الرتبة تلقائيًا قبل تأكيد الدفع من المالك.</div>
</div>
<script data-cfasync="false">
function vipToggleContact(event,card){if(event && event.target && $(event.target).closest('a,button').length){return;}$(card).toggleClass('vm_contact_open');}
function vipNewRequestKey(){var out='';var chars='abcdef0123456789';for(var i=0;i<64;i++){out+=chars.charAt(Math.floor(Math.random()*chars.length));}return out;}
function vipBuyMembership(id){if(!confirm('هل تريد تأكيد شراء هذه العضوية؟'))return;$.post('addons/vip/system/action.php',{token:utk,purchase:1,offer_id:id,request_key:vipNewRequestKey()},function(r){var d=r;if(typeof r==='string'){try{d=JSON.parse(r);}catch(e){d={code:0,emessage:'تعذر قراءة رد الخادم'};}}if(d.code==1){callSuccess(d.emessage||'تم شراء العضوية');$.post('addons/vip/system/template.php',{token:utk},function(html){showAddonFullPage(html,620,'large');});}else{callError(d.emessage||system.error);}},'json').fail(function(){callError(system.error);});}
</script>
