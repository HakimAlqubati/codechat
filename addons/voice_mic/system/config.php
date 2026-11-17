<?php
$load_addons = 'voice_mic';
require_once __DIR__ . '/../../../system/config_addons.php';
if(!canManageAddons()){ die(); }
$vm = vmic_settings();
$vmicWalletEnabled = function_exists('useWallet') && useWallet();
function vmic_h($value){ return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8'); }
$roomRows=array();
$roomQuery=$mysqli->query("SELECT room_id,room_name FROM boom_rooms WHERE room_id>0 ORDER BY pinned DESC,room_name ASC");
if($roomQuery){ while($r=$roomQuery->fetch_assoc()){
    $id=(int)$r['room_id'];
    $stored=$mysqli->query("SELECT enabled FROM boom_voice_mic_rooms WHERE room_id={$id} LIMIT 1");
    $enabled=$stored && $stored->num_rows ? (int)$stored->fetch_assoc()['enabled'] : (int)$vm['default_room_enabled'];
    $roomRows[]=array('id'=>$id,'name'=>$r['room_name'],'enabled'=>$enabled);
}}
$muteRows=array();
$mq=$mysqli->query("SELECT m.room_id,m.user_id,m.reason,m.created_at,u.user_name,r.room_name FROM boom_voice_mic_mutes m LEFT JOIN boom_users u ON u.user_id=m.user_id LEFT JOIN boom_rooms r ON r.room_id=m.room_id ORDER BY m.created_at DESC LIMIT 100");
if($mq){ while($r=$mq->fetch_assoc()){ $muteRows[]=$r; }}
$mysqli->query("DELETE FROM boom_voice_mic_bans WHERE expires_at > 0 AND expires_at <= ".time());
$banRows=array();
$bq=$mysqli->query("SELECT b.room_id,b.user_id,b.expires_at,b.reason,b.created_at,u.user_name,r.room_name FROM boom_voice_mic_bans b LEFT JOIN boom_users u ON u.user_id=b.user_id LEFT JOIN boom_rooms r ON r.room_id=b.room_id ORDER BY b.created_at DESC LIMIT 100");
if($bq){ while($r=$bq->fetch_assoc()){ $banRows[]=$r; }}
$logRows=array();
$lq=$mysqli->query("SELECT l.*,a.user_name AS actor_name,t.user_name AS target_name,r.room_name FROM boom_voice_mic_logs l LEFT JOIN boom_users a ON a.user_id=l.actor_id LEFT JOIN boom_users t ON t.user_id=l.target_id LEFT JOIN boom_rooms r ON r.room_id=l.room_id ORDER BY l.log_id DESC LIMIT 50");
if($lq){ while($r=$lq->fetch_assoc()){ $logRows[]=$r; }}
$paused=(int)$vm['pause_until'] > time();
$vmicActionLabels=array('join_speaker'=>'دخول متصل','join_listener'=>'دخول مستمع','leave'=>'مغادرة','self_mute'=>'كتم ذاتي','self_unmute'=>'فتح الميكروفون','self_listener'=>'التحويل إلى مستمع','self_speaker'=>'التحويل إلى متصل','mute'=>'كتم إداري','unmute'=>'إلغاء الكتم الإداري','listener'=>'تحويل إلى مستمع','kick'=>'تنزيل من المكالمة','ban'=>'تقييد من المايك','unban'=>'إلغاء التقييد','approve'=>'قبول طلب التحدث','reject'=>'رفض طلب التحدث','service_pause'=>'إيقاف الخدمة','service_resume'=>'إعادة تشغيل الخدمة','settings_update'=>'تعديل إعدادات الإضافة','unmute_member'=>'إلغاء الكتم الإداري','approve_request'=>'قبول طلب التحدث','gold_charge'=>'خصم ذهب لدخول المايك','gold_minute_charge'=>'خصم ذهب مقابل دقائق المايك','billing_failed'=>'إخراج لعدم كفاية رصيد الدقيقة','entry_confirmed'=>'تأكيد جلسة المايك');
$vmicLiveReady=!empty($setting['live_url']) && !empty($setting['live_appid']) && !empty($setting['live_secret']);
?>
<div class="page_full vmic-admin" dir="rtl">
<style>
.vmic-admin{--va:#28b66f;--vb:#368ee8}.vmic-admin .vmic-admin-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}.vmic-admin .vmic-admin-title{font-size:18px;font-weight:900}.vmic-admin .vmic-admin-sub{font-size:12px;color:var(--sub_text,#888);margin-top:4px}.vmic-admin .vmic-admin-tabs{display:flex;gap:7px;overflow-x:auto;padding:10px 0}.vmic-admin .vmic-admin-tab{border:1px solid var(--border_color,rgba(127,127,127,.25));background:var(--secondary_bg,#f5f5f5);color:var(--text_color,#333);border-radius:10px;padding:9px 12px;white-space:nowrap;cursor:pointer;font-weight:700;font-size:12px}.vmic-admin .vmic-admin-tab.active{background:var(--theme_color,#3388cc);color:#fff}.vmic-admin .vmic-admin-pane{display:none}.vmic-admin .vmic-admin-pane.active{display:block}.vmic-admin .vmic-box{background:var(--secondary_bg,#f8f8f8);border:1px solid var(--border_color,rgba(127,127,127,.2));border-radius:12px;padding:14px;margin:10px 0}.vmic-admin .vmic-box h3{font-size:14px;font-weight:900;margin:0 0 12px}.vmic-admin .vmic-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px}.vmic-admin .vmic-field label{display:block;font-size:12px;font-weight:700;margin-bottom:6px}.vmic-admin .vmic-field input:not([type=checkbox]):not([type=color]),.vmic-admin .vmic-field select,.vmic-admin .vmic-field textarea{width:100%;min-height:38px;border:1px solid var(--border_color,rgba(127,127,127,.3));border-radius:8px;padding:7px 9px;background:var(--input_bg,transparent);color:var(--text_color,inherit)}.vmic-admin .vmic-field input[type=color]{width:100%;height:38px;border:0;background:transparent}.vmic-admin .vmic-help{font-size:11px;color:var(--sub_text,#888);margin-top:4px;line-height:1.6}.vmic-admin .vmic-switch{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:9px 0;border-bottom:1px solid var(--border_color,rgba(127,127,127,.15));font-size:12px}.vmic-admin .vmic-switch:last-child{border-bottom:0}.vmic-admin .vmic-switch input{width:18px;height:18px;accent-color:var(--theme_color,#3388cc)}.vmic-admin .vmic-room-tools{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px}.vmic-admin .vmic-room-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px;max-height:380px;overflow:auto}.vmic-admin .vmic-room{display:flex;align-items:center;gap:8px;padding:9px;border:1px solid var(--border_color,rgba(127,127,127,.2));border-radius:9px;font-size:12px}.vmic-admin .vmic-room input{accent-color:var(--theme_color,#3388cc)}.vmic-admin .vmic-small-btn{border:1px solid var(--border_color,rgba(127,127,127,.25));background:var(--secondary_bg,#eee);color:var(--text_color,inherit);padding:7px 10px;border-radius:8px;font-size:11px;cursor:pointer}.vmic-admin .vmic-save{margin:12px 0;width:100%;max-width:260px}.vmic-admin .vmic-status-pill{display:inline-block;padding:5px 9px;border-radius:20px;font-size:11px;font-weight:800}.vmic-admin .vmic-status-on{background:rgba(40,182,111,.14);color:#20945a}.vmic-admin .vmic-status-off{background:rgba(220,60,70,.13);color:#d44850}.vmic-admin .vmic-table-wrap{overflow-x:auto}.vmic-admin table{width:100%;border-collapse:collapse;font-size:11px;min-width:580px}.vmic-admin th,.vmic-admin td{padding:9px 7px;border-bottom:1px solid var(--border_color,rgba(127,127,127,.18));text-align:right}.vmic-admin th{font-weight:900}.vmic-admin .vmic-danger{color:#d44850}.vmic-admin .vmic-pause-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.vmic-admin .vmic-info{font-size:12px;line-height:1.7;color:var(--sub_text,#777)}
</style>
<div class="vmic-admin-head">
  <div><div class="vmic-admin-title">إدارة إضافة المايك الصوتي</div><div class="vmic-admin-sub">تحكم بالغرف، الصلاحيات، المظهر، المشاركين والإيقاف المؤقت من مكان واحد.</div></div>
  <span class="vmic-status-pill <?php echo ((int)$vm['enabled'] && !$paused)?'vmic-status-on':'vmic-status-off'; ?>"><?php echo ((int)$vm['enabled'] && !$paused)?'الخدمة مفعّلة':($paused?'الخدمة متوقفة مؤقتاً':'الخدمة معطّلة'); ?></span>
</div>
<div class="vmic-admin-tabs">
  <button class="vmic-admin-tab active" data-tab="general">العامة</button>
  <button class="vmic-admin-tab" data-tab="payment">الدفع والذهب</button>
  <button class="vmic-admin-tab" data-tab="rooms">الغرف</button>
  <button class="vmic-admin-tab" data-tab="permissions">الصلاحيات</button>
  <button class="vmic-admin-tab" data-tab="design">التصميم</button>
  <button class="vmic-admin-tab" data-tab="bans">التقييدات</button>
  <button class="vmic-admin-tab" data-tab="mutes">الكتم الإداري</button>
  <button class="vmic-admin-tab" data-tab="logs">السجل</button>
</div>
<div class="vmic-admin-pane active" data-pane="general">
  <div class="vmic-box"><h3>الاتصال بخادم الصوت</h3>
    <?php if($vmicLiveReady){ ?><div class="vmic-info">بيانات LiveKit مكتملة في إعدادات المكالمات. تستخدم الإضافة هذا الخادم بغرف مستقلة عن مكالمات CodyChat الأصلية.</div><?php } else { ?><div class="vmic-info vmic-danger">بيانات LiveKit غير مكتملة. افتح إعدادات الشات ← إعدادات المكالمات ← API وأدخل الرابط ومعرف التطبيق والمفتاح السري قبل اختبار الصوت.</div><?php } ?>
  </div>
  <div class="vmic-box"><h3>حالة الخدمة</h3>
    <label class="vmic-switch"><span>تفعيل إضافة المايك</span><input type="checkbox" id="vmic_enabled" <?php echo (int)$vm['enabled']?'checked':''; ?>></label>
    <label class="vmic-switch"><span>السماح بوضع الاستماع</span><input type="checkbox" id="vmic_allow_listeners" <?php echo (int)$vm['allow_listeners']?'checked':''; ?>></label>
    <label class="vmic-switch"><span>اشتراط موافقة المشرف قبل التحويل إلى متحدث</span><input type="checkbox" id="vmic_require_approval" <?php echo (int)$vm['require_speaker_approval']?'checked':''; ?>></label>
    <label class="vmic-switch"><span>السماح لمشرفي الغرف (رتب الغرفة 4 فأعلى) بالإدارة</span><input type="checkbox" id="vmic_allow_room_staff" <?php echo (int)$vm['allow_room_staff']?'checked':''; ?>></label>
    <div class="vmic-grid" style="margin-top:12px">
      <div class="vmic-field"><label>الحد الأقصى للمتصلين</label><input id="vmic_max_speakers" type="number" min="1" max="100" value="<?php echo (int)$vm['max_speakers']; ?>"></div>
      <div class="vmic-field"><label>الحد الأقصى للمستمعين</label><input id="vmic_max_listeners" type="number" min="1" max="500" value="<?php echo (int)$vm['max_listeners']; ?>"></div>
    </div>
    <button type="button" class="reg_button theme_btn vmic-save" onclick="vmicSaveSettings()">حفظ الإعدادات العامة</button>
  </div>
  <div class="vmic-box"><h3>إيقاف الخدمة مؤقتاً</h3>
    <div class="vmic-info">عند الإيقاف يتم إنهاء الجلسات الصوتية الحالية، ويظهر سبب التوقف للأعضاء في الرومات المفعّلة. اترك المدة صفراً إذا أردت بقاء التوقف حتى إعادة التفعيل يدوياً.</div>
    <div class="vmic-grid" style="margin-top:10px">
      <div class="vmic-field"><label>مدة الإيقاف بالدقائق</label><input id="vmic_pause_minutes" type="number" min="0" max="525600" value="60"><div class="vmic-help">15 دقيقة، 60 دقيقة، 1440 دقيقة = يوم. الصفر = حتى إعادة التفعيل.</div></div>
      <div class="vmic-field"><label>رسالة سبب الإيقاف</label><textarea id="vmic_pause_message" rows="2" maxlength="500" placeholder="اكتب سبب إيقاف المايك للأعضاء..."><?php echo vmic_h($vm['pause_message']); ?></textarea></div>
    </div>
    <div class="vmic-pause-actions"><button type="button" class="reg_button delete_btn" onclick="vmicPause()">إيقاف المايك الآن</button><button type="button" class="reg_button theme_btn" onclick="vmicResume()">إعادة تفعيل المايك</button></div>
    <?php if($paused){ ?><div class="vmic-info" style="margin-top:8px">متوقف حتى: <b><?php echo (int)$vm['pause_until']>=2147483647?'إعادة التفعيل يدوياً':date('Y-m-d H:i',(int)$vm['pause_until']); ?></b></div><?php } ?>
  </div>
</div>
<div class="vmic-admin-pane" data-pane="payment">
  <div class="vmic-box"><h3>الدفع حسب مدة استخدام المايك</h3>
    <?php if($vmicWalletEnabled){ ?>
      <div class="vmic-info" style="color:#20945a">محفظة الذهب مفعّلة في CodyChat. يمكن للإضافة استخدام رصيد user_gold والاحتساب ضمن الذهب المصروف user_sgold.</div>
    <?php } else { ?>
      <div class="vmic-info vmic-danger">محفظة الذهب غير مفعّلة حالياً. لتفعيل الدفع، افتح إعدادات الشات ← إعدادات المحفظة ← فعّل استخدام المحفظة، ثم احفظ.</div>
    <?php } ?>
    <div class="vmic-grid" style="margin-top:12px">
      <div class="vmic-field">
        <label>رسوم المتصلين (الاتصال الجماعي)</label>
        <select id="vmic_speaker_gold_mode">
          <option value="0" <?php echo (int)$vm['speaker_gold_mode']===0?'selected':''; ?>>مجاني</option>
          <option value="1" <?php echo (int)$vm['speaker_gold_mode']===1?'selected':''; ?> <?php echo !$vmicWalletEnabled?'disabled':''; ?>>ذهب لكل دقيقة</option>
        </select>
        <label style="margin-top:8px">سعر دقيقة الاتصال بالذهب</label>
        <input id="vmic_speaker_gold_cost" type="number" min="0" max="1000000" step="1" value="<?php echo (int)$vm['speaker_gold_cost']; ?>" inputmode="numeric">
        <div class="vmic-help">يُخصم هذا السعر من العضو الذي يدخل وضع الاتصال الجماعي ويتحدث أو يكتم ميكروفونه.</div>
      </div>
      <div class="vmic-field">
        <label>رسوم المستمعين</label>
        <select id="vmic_listener_gold_mode">
          <option value="0" <?php echo (int)$vm['listener_gold_mode']===0?'selected':''; ?>>مجاني</option>
          <option value="1" <?php echo (int)$vm['listener_gold_mode']===1?'selected':''; ?> <?php echo !$vmicWalletEnabled?'disabled':''; ?>>ذهب لكل دقيقة</option>
        </select>
        <label style="margin-top:8px">سعر دقيقة الاستماع بالذهب</label>
        <input id="vmic_listener_gold_cost" type="number" min="0" max="1000000" step="1" value="<?php echo (int)$vm['listener_gold_cost']; ?>" inputmode="numeric">
        <div class="vmic-help">يُخصم هذا السعر من العضو الذي يدخل وضع الاستماع فقط، ولا يحتاج إلى تشغيل الميكروفون.</div>
      </div>
    </div>
    <div class="vmic-info" style="margin-top:10px">تستطيع جعل الاتصال مجانياً والاستماع بذهب، أو العكس، أو تحديد سعر مختلف لكل وضع. تُخصم الدقيقة الأولى عند تأكيد الدخول، ثم تُجدد الرسوم كل 60 ثانية. عند التحويل بين المتصل والمستمع، يُطبّق سعر الوضع الجديد ابتداءً من دورة الفوترة التالية؛ وإذا انتقل العضو من وضع مجاني إلى وضع مدفوع تُخصم الدقيقة الأولى للوضع المدفوع عند التحويل. عند عدم كفاية الرصيد للدقيقة التالية يُفصل العضو تلقائياً. تغييرات الأسعار تُطبق على الجلسات الجديدة فقط، ولا تغيّر سعر جلسة قائمة.</div>
    <button type="button" class="reg_button theme_btn vmic-save" onclick="vmicSaveSettings()">حفظ إعدادات الدفع</button>
  </div>
</div>
<div class="vmic-admin-pane" data-pane="rooms">
  <div class="vmic-box"><h3>تحديد الرومات التي يظهر فيها المايك</h3>
    <label class="vmic-switch"><span>تفعيل المايك تلقائياً في الرومات الجديدة (عند تفعيله)</span><input type="checkbox" id="vmic_default_room_enabled" <?php echo (int)$vm['default_room_enabled']?'checked':''; ?>></label>
    <div class="vmic-room-tools"><button class="vmic-small-btn" type="button" onclick="vmicSelectRooms(true)">تحديد الكل</button><button class="vmic-small-btn" type="button" onclick="vmicSelectRooms(false)">إلغاء تحديد الكل</button><input class="full_input" style="max-width:230px;min-height:34px" id="vmic_room_search" placeholder="بحث عن روم..." oninput="vmicFilterRooms()"></div>
    <div class="vmic-room-list" id="vmic_room_list">
    <?php if(count($roomRows)){ foreach($roomRows as $r){ ?>
      <label class="vmic-room" data-room-name="<?php echo vmic_h(function_exists('mb_strtolower') ? mb_strtolower($r['name'],'UTF-8') : strtolower($r['name'])); ?>"><input type="checkbox" data-vmic-room="<?php echo $r['id']; ?>" <?php echo $r['enabled']?'checked':''; ?>><span><?php echo vmic_h($r['name']); ?></span></label>
    <?php }} else { ?><div class="vmic-info">لا توجد غرف في الشات حالياً.</div><?php } ?>
    </div>
    <button type="button" class="reg_button theme_btn vmic-save" onclick="vmicSaveSettings()">حفظ الغرف والإعدادات</button>
  </div>
</div>
<div class="vmic-admin-pane" data-pane="permissions">
  <div class="vmic-box"><h3>صلاحيات رتب الأعضاء</h3>
    <div class="vmic-grid">
      <div class="vmic-field"><label>أقل رتبة لدخول وضع المتصل</label><select id="vmic_min_speaker_rank"><?php echo listRank((int)$vm['min_speaker_rank']); ?></select></div>
      <div class="vmic-field"><label>أقل رتبة لدخول وضع المستمع</label><select id="vmic_min_listener_rank"><?php echo listRank((int)$vm['min_listener_rank']); ?></select></div>
      <div class="vmic-field"><label>أقل رتبة لإدارة المشاركين</label><select id="vmic_manage_rank"><?php echo listRank((int)$vm['manage_rank']); ?></select></div>
      <div class="vmic-field"><label>أقل رتبة للتقييد وإلغاء التقييد</label><select id="vmic_ban_rank"><?php echo listRank((int)$vm['ban_rank']); ?></select></div>
      <div class="vmic-field"><label>أقل رتبة للتحكم بإيقاف الخدمة</label><select id="vmic_pause_rank"><?php echo listRank((int)$vm['pause_rank']); ?></select></div>
    </div>
    <div class="vmic-help">إدارة إعدادات الإضافة نفسها محمية بصلاحية إدارة الإضافات في CodyChat. الرتب هنا تتحكم بأوامر المايك داخل الرومات.</div>
    <button type="button" class="reg_button theme_btn vmic-save" onclick="vmicSaveSettings()">حفظ صلاحيات الرتب</button>
  </div>
</div>
<div class="vmic-admin-pane" data-pane="design">
  <div class="vmic-box"><h3>تصميم الشريط الأفقي</h3>
    <div class="vmic-grid">
      <div class="vmic-field"><label>ارتفاع الشريط (بكسل)</label><input id="vmic_bar_height" type="number" min="58" max="100" value="<?php echo (int)$vm['bar_height']; ?>"></div>
      <div class="vmic-field"><label>حجم صورة العضو (بكسل)</label><input id="vmic_avatar_size" type="number" min="24" max="44" value="<?php echo (int)$vm['avatar_size']; ?>"></div>
      <div class="vmic-field"><label>لون المتصلين</label><input id="vmic_speaker_color" type="color" value="<?php echo vmic_h($vm['speaker_color']); ?>"></div>
      <div class="vmic-field"><label>لون المستمعين</label><input id="vmic_listener_color" type="color" value="<?php echo vmic_h($vm['listener_color']); ?>"></div>
      <div class="vmic-field"><label>لون الفاصل</label><input id="vmic_divider_color" type="color" value="<?php echo vmic_h($vm['divider_color']); ?>"></div>
    </div>
    <label class="vmic-switch"><span>إظهار أسماء الأعضاء</span><input type="checkbox" id="vmic_show_names" <?php echo (int)$vm['show_names']?'checked':''; ?>></label>
    <label class="vmic-switch"><span>إظهار رتب الأعضاء</span><input type="checkbox" id="vmic_show_ranks" <?php echo (int)$vm['show_ranks']?'checked':''; ?>></label>
    <div class="vmic-help">الافتراضي المقترح ارتفاع 74px وصورة 32px؛ الشريط يبقى أفقياً ولا يتمدد رأسياً مع زيادة المشاركين.</div>
    <button type="button" class="reg_button theme_btn vmic-save" onclick="vmicSaveSettings()">حفظ التصميم</button>
  </div>
</div>
<div class="vmic-admin-pane" data-pane="bans">
  <div class="vmic-box"><h3>الأعضاء المقيّدون من المايك</h3><div class="vmic-table-wrap"><table><thead><tr><th>العضو</th><th>الروم</th><th>المدة</th><th>السبب</th><th>إجراء</th></tr></thead><tbody>
  <?php if(count($banRows)){ foreach($banRows as $b){ ?><tr><td><?php echo vmic_h($b['user_name']?:('ID '.$b['user_id'])); ?></td><td><?php echo vmic_h($b['room_name']?:$b['room_id']); ?></td><td><?php echo (int)$b['expires_at']===0?'دائم':date('Y-m-d H:i',(int)$b['expires_at']); ?></td><td><?php echo vmic_h($b['reason']); ?></td><td><button class="vmic-small-btn" type="button" onclick="vmicUnban(<?php echo (int)$b['room_id']; ?>,<?php echo (int)$b['user_id']; ?>)">إلغاء التقييد</button></td></tr><?php }} else { ?><tr><td colspan="5">لا توجد تقييدات حالياً.</td></tr><?php } ?>
  </tbody></table></div></div>
</div>
<div class="vmic-admin-pane" data-pane="mutes">
  <div class="vmic-box"><h3>الأعضاء المقيّدون من التحدث (كتم إداري محفوظ)</h3><div class="vmic-info">يبقى هذا الكتم محفوظاً حتى بعد خروج العضو من المايك أو عودته، ويمكن إلغاؤه من هنا.</div><div class="vmic-table-wrap"><table><thead><tr><th>العضو</th><th>الروم</th><th>السبب</th><th>التاريخ</th><th>إجراء</th></tr></thead><tbody>
  <?php if(count($muteRows)){ foreach($muteRows as $m){ ?><tr><td><?php echo vmic_h($m['user_name']?:('ID '.$m['user_id'])); ?></td><td><?php echo vmic_h($m['room_name']?:$m['room_id']); ?></td><td><?php echo vmic_h($m['reason']); ?></td><td><?php echo date('Y-m-d H:i',(int)$m['created_at']); ?></td><td><button class="vmic-small-btn" type="button" onclick="vmicUnmuteMember(<?php echo (int)$m['room_id']; ?>,<?php echo (int)$m['user_id']; ?>)">إلغاء كتم التحدث</button></td></tr><?php }} else { ?><tr><td colspan="5">لا توجد حالات كتم إداري.</td></tr><?php } ?>
  </tbody></table></div></div>
</div>
<div class="vmic-admin-pane" data-pane="logs">
  <div class="vmic-box"><h3>آخر 50 إجراء</h3><div class="vmic-table-wrap"><table><thead><tr><th>التاريخ</th><th>الروم</th><th>المنفذ</th><th>العضو</th><th>الإجراء</th><th>التفاصيل</th></tr></thead><tbody>
  <?php if(count($logRows)){ foreach($logRows as $l){ ?><tr><td><?php echo date('Y-m-d H:i',(int)$l['created_at']); ?></td><td><?php echo vmic_h($l['room_name']?:((int)$l['room_id']?:'عام')); ?></td><td><?php echo vmic_h($l['actor_name']?:$l['actor_id']); ?></td><td><?php echo vmic_h($l['target_name']?:$l['target_id']); ?></td><td><?php echo vmic_h($vmicActionLabels[$l['action']]??$l['action']); ?></td><td><?php echo vmic_h($l['details']); ?></td></tr><?php }} else { ?><tr><td colspan="6">لا توجد عمليات مسجلة.</td></tr><?php } ?>
  </tbody></table></div></div>
</div>
<script data-cfasync="false">
(function(){
  var root=document.querySelector('.vmic-admin'); if(!root || root.dataset.ready==='1') return; root.dataset.ready='1';
  root.querySelectorAll('.vmic-admin-tab').forEach(function(tab){tab.addEventListener('click',function(){root.querySelectorAll('.vmic-admin-tab').forEach(function(x){x.classList.remove('active')});root.querySelectorAll('.vmic-admin-pane').forEach(function(x){x.classList.remove('active')});tab.classList.add('active');var pane=root.querySelector('[data-pane="'+tab.dataset.tab+'"]');if(pane)pane.classList.add('active')})});
})();
function vmicAdminPost(payload){payload=payload||{};payload.token=(typeof utk!=='undefined'?utk:'');payload.cp='admin';return $.ajax({url:'addons/voice_mic/system/admin_action.php',type:'POST',dataType:'json',data:payload});}
function vmicAdminDone(r){if(r&&r.code===1){if(typeof callSuccess==='function')callSuccess(r.message||system.saved);else alert(r.message||'تم');}else{if(typeof callError==='function')callError((r&&r.message)||system.error);else alert((r&&r.message)||'تعذر التنفيذ');}}
function vmicVal(id){var e=document.getElementById('vmic_'+id);return e?e.value:'';}
function vmicCheck(id){var e=document.getElementById('vmic_'+id);return e&&e.checked?1:0;}
function vmicSaveSettings(){
 var fields=['enabled','allow_listeners','require_approval','allow_room_staff','default_room_enabled','speaker_gold_mode','speaker_gold_cost','listener_gold_mode','listener_gold_cost','max_speakers','max_listeners','min_speaker_rank','min_listener_rank','manage_rank','ban_rank','pause_rank','bar_height','avatar_size','show_names','show_ranks','speaker_color','listener_color','divider_color'];
 var map={require_approval:'require_speaker_approval'};var s={};fields.forEach(function(k){var key=map[k]||k;if(['enabled','allow_listeners','require_approval','allow_room_staff','default_room_enabled','show_names','show_ranks'].indexOf(k)!==-1)s[key]=vmicCheck(k);else s[key]=vmicVal(k)});
 var rooms=[];document.querySelectorAll('[data-vmic-room]:checked').forEach(function(e){rooms.push(parseInt(e.getAttribute('data-vmic-room'),10))});
 vmicAdminPost({action:'save_settings',settings:JSON.stringify(s),rooms:JSON.stringify(rooms)}).done(vmicAdminDone).fail(function(){vmicAdminDone({code:0,message:'تعذر الاتصال بالخادم'})});
}
function vmicSelectRooms(on){document.querySelectorAll('[data-vmic-room]').forEach(function(e){if(e.closest('.vmic-room').style.display!=='none')e.checked=!!on})}
function vmicFilterRooms(){var q=(vmicVal('room_search')||'').toLowerCase();document.querySelectorAll('.vmic-room').forEach(function(e){e.style.display=e.textContent.toLowerCase().indexOf(q)!==-1?'flex':'none'})}
function vmicPause(){var m=parseInt(vmicVal('pause_minutes')||'0',10);var msg=vmicVal('pause_message');if(!msg){if(!confirm('لم تكتب سبب التوقف. هل تريد الإيقاف دون رسالة؟'))return;}vmicAdminPost({action:'pause',minutes:m,message:msg}).done(function(r){vmicAdminDone(r);if(r&&r.code===1)loadLob('addons/voice_mic/system/config.php')}).fail(function(){vmicAdminDone({code:0,message:'تعذر الاتصال بالخادم'})})}
function vmicResume(){vmicAdminPost({action:'resume'}).done(function(r){vmicAdminDone(r);if(r&&r.code===1)loadLob('addons/voice_mic/system/config.php')}).fail(function(){vmicAdminDone({code:0,message:'تعذر الاتصال بالخادم'})})}
function vmicUnban(room,user){if(!confirm('إلغاء تقييد هذا العضو من المايك؟'))return;vmicAdminPost({action:'unban',room_id:room,user_id:user}).done(function(r){vmicAdminDone(r);if(r&&r.code===1)loadLob('addons/voice_mic/system/config.php')}).fail(function(){vmicAdminDone({code:0,message:'تعذر الاتصال بالخادم'})})}
function vmicUnmuteMember(room,user){if(!confirm('إلغاء الكتم الإداري والسماح للعضو بطلب التحدث مجدداً؟'))return;vmicAdminPost({action:'unmute_member',room_id:room,user_id:user}).done(function(r){vmicAdminDone(r);if(r&&r.code===1)loadLob('addons/voice_mic/system/config.php')}).fail(function(){vmicAdminDone({code:0,message:'تعذر الاتصال بالخادم'})})}
</script>
</div>
