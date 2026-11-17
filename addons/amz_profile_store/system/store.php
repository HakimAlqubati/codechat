<?php
$load_addons = 'amz_profile_store';
require(__DIR__ . '/../../../system/config_addons.php');
require_once __DIR__ . '/../../../system/addon_menu_labels.php';
$amzStoreTitle = addonMenuLabelGet('amz_profile_store', 'الملف الشخصي');
require_once __DIR__ . '/profile_gate.php';
amzStoreEnsureTables();
function amzStoreH($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$userRank = amzStoreRank($data);
$gold = max(0, (int)$data['user_gold']);
$services = array('background'=>'الخلفيات','frame'=>'إطارات الصور','music'=>'موسيقى الملف الشخصي');
$enabledServices = array();
foreach($services as $key=>$label){ $s=amzStoreSetting($key); if($s && (int)$s['enabled']===1){ $enabledServices[$key]=$label; } }
?>
<div class="amz-store">
 <div class="amz-store-head">
  <div><div class="amz-store-title"><i class="fa fa-store"></i> <?php echo amzStoreH($amzStoreTitle); ?></div><div class="amz-store-note">اشترِ مقتنياتك بالذهب وفعّل ما تملكه وقتما تشاء</div></div>
  <div class="amz-store-gold"><i class="fa fa-coins"></i> <span id="amz-store-gold"><?php echo number_format($gold); ?></span> ذهب</div>
 </div>
 <?php if(empty($enabledServices)){ ?><div class="amz-store-empty">المتجر غير متاح حالياً.</div><?php } else { ?>
 <div class="amz-store-tabs">
  <?php $first=true; foreach($enabledServices as $key=>$label){ ?><button type="button" class="amz-store-tab <?php echo $first?'active':''; ?>" data-tab="<?php echo amzStoreH($key); ?>" onclick="amzStoreSelectTab('<?php echo amzStoreH($key); ?>')"><?php echo amzStoreH($label); ?></button><?php $first=false; } ?>
 </div>
 <?php foreach($enabledServices as $key=>$label){ $s=amzStoreSetting($key); $canFree=($userRank >= (int)$s['free_rank']); $canBuy=($userRank >= (int)$s['paid_rank']); ?>
 <div class="amz-store-panel" id="amz-store-panel-<?php echo amzStoreH($key); ?>" style="<?php echo $key===array_key_first($enabledServices)?'':'display:none'; ?>">
  <?php if($key==='music'){
    $entitled=$canFree || amzStoreHasEntitlement('music',(int)$data['user_id']);
    if($entitled){ ?>
     <div class="amz-store-empty"><i class="fa fa-circle-check"></i><br>خدمة الموسيقى مفعّلة لحسابك.<br><span class="amz-store-note">يمكنك إدارة أغنيتك من مكانها المعتاد داخل الملف الشخصي دون تغيير واجهته.</span></div>
    <?php } elseif($canBuy){ ?>
     <div class="amz-store-card" style="max-width:360px;margin:auto"><div class="amz-store-cardbody"><div class="amz-store-name"><i class="fa fa-music"></i> فتح موسيقى الملف الشخصي</div><div class="amz-store-price">السعر: <?php echo number_format((int)$s['price_gold']); ?> ذهب — تفعيل دائم للحساب</div><button class="amz-store-btn" onclick="amzStoreServicePurchase('music')">شراء الخدمة</button></div></div>
    <?php } else { ?><div class="amz-store-empty">هذه الخدمة غير متاحة لرتبتك حالياً.</div><?php } ?>
  <?php } else {
    $stmt=$mysqli->prepare("SELECT item_id,service_key,title,file_name,price_gold,enabled FROM boom_amz_store_items WHERE service_key=? AND enabled=1 ORDER BY item_id DESC");
    $items=array(); if($stmt){$stmt->bind_param('s',$key);$stmt->execute();$stmt->bind_result($iidRow,$svcRow,$titleRow,$fileRow,$priceRow,$enabledRow);while($stmt->fetch()){$items[]=array('item_id'=>$iidRow,'service_key'=>$svcRow,'title'=>$titleRow,'file_name'=>$fileRow,'price_gold'=>$priceRow,'enabled'=>$enabledRow);}$stmt->close();}
    if(empty($items)){ ?><div class="amz-store-empty">لا توجد عناصر متاحة في هذا القسم حالياً.</div><?php }
    else { ?><div class="amz-store-grid"><?php foreach($items as $it){
      $ownStmt=$mysqli->prepare("SELECT own_id FROM boom_amz_store_owned WHERE user_id=? AND item_id=? LIMIT 1");$owned=false;if($ownStmt){$uid=(int)$data['user_id'];$iid=(int)$it['item_id'];$ownStmt->bind_param('ii',$uid,$iid);$ownStmt->execute();$ownStmt->store_result();$owned=$ownStmt->num_rows>0;$ownStmt->close();}
      $free=$canFree; $canAccess=$free || $canBuy; $activeStmt=$mysqli->prepare("SELECT item_id FROM boom_amz_store_active WHERE user_id=? AND service_key=? LIMIT 1");$active=false;if($activeStmt){$uid=(int)$data['user_id'];$activeStmt->bind_param('is',$uid,$key);$activeStmt->execute();$activeStmt->bind_result($activeItemId);$foundActive=$activeStmt->fetch();$active=$foundActive && (int)$activeItemId===(int)$it['item_id'];$activeStmt->close();}
      if($active && !$owned && !$canFree){$active=false;}
      $file='upload/amz_profile_store/' . rawurlencode($it['file_name']);
      ?>
      <div class="amz-store-card"><img class="amz-store-preview" src="<?php echo amzStoreH($file); ?>" alt="<?php echo amzStoreH($it['title']); ?>"><div class="amz-store-cardbody"><div class="amz-store-name"><?php echo amzStoreH($it['title']); ?></div><div class="amz-store-price"><?php echo $free?'مجاني لرتبتك':((int)$it['price_gold']===0?'مجاني':number_format((int)$it['price_gold']).' ذهب'); ?></div>
      <?php if($active){ ?><button class="amz-store-btn secondary" disabled>مفعّل حالياً</button><button class="amz-store-btn secondary" style="margin-top:6px" onclick="amzStoreAction('deactivate',<?php echo (int)$it['item_id']; ?>)">إيقاف</button>
      <?php } elseif($owned || $free){ ?><button class="amz-store-btn" onclick="amzStoreAction('activate',<?php echo (int)$it['item_id']; ?>)">تفعيل</button>
      <?php } elseif($canBuy){ ?><button class="amz-store-btn" onclick="amzStoreAction('buy',<?php echo (int)$it['item_id']; ?>)">شراء</button>
      <?php } else { ?><button class="amz-store-btn secondary" disabled>غير متاح لرتبتك</button><?php } ?>
      </div></div>
    <?php } ?></div><?php }
  } ?>
 </div>
 <?php } ?>
 <div class="amz-store-note">تُخصم تكلفة الشراء من رصيد الذهب الأصلي في CodyChat. ملكية الخلفيات والإطارات دائمة ولا يتكرر خصم الذهب عند تفعيل العنصر مرة أخرى. بعد التفعيل افتح الملف الشخصي مجدداً لرؤية الخلفية أو الإطار.</div>
 <?php } ?>
</div>
