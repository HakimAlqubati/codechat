<?php
if(!defined('BOOM') || !boomLogged() || !isset($addons) || !boomAllow($addons['addons_access'])){ return; }
$vmic_room_id=(int)$data['user_roomid'];
$vmic_live_ready=!empty($setting['live_url']) && !empty($setting['live_appid']) && !empty($setting['live_secret']);
$vmic_version=function_exists('boomFileVersion') ? boomFileVersion() : '';
?>
<div id="voice_mic_root" class="vmic-root" hidden dir="rtl" data-room="<?php echo $vmic_room_id; ?>" data-user="<?php echo (int)$data['user_id']; ?>" data-live-ready="<?php echo $vmic_live_ready?'1':'0'; ?>">
  <div class="vmic-dock" id="vmic_dock">
    <div class="vmic-entry-actions" id="vmic_entry_actions">
      <button type="button" id="vmic_join_speaker" class="vmic-join-button vmic-join-speaker" title="دخول الاتصال الجماعي" aria-label="دخول الاتصال الجماعي"><i class="fa fa-microphone"></i></button>
      <button type="button" id="vmic_join_listener" class="vmic-join-button vmic-join-listener" title="دخول الاستماع" aria-label="دخول الاستماع"><i class="fa fa-headphones"></i></button>
    </div>
    <div class="vmic-active-actions" id="vmic_speaker_actions" hidden>
      <button type="button" id="vmic_self_mute" class="vmic-join-button vmic-self-mute" title="كتم الميكروفون" aria-label="كتم الميكروفون"><i class="fa fa-microphone"></i></button>
      <button type="button" id="vmic_leave_speaker" class="vmic-join-button vmic-leave" title="مغادرة المكالمة" aria-label="مغادرة المكالمة"><i class="fa fa-microphone-slash"></i></button>
    </div>
    <div class="vmic-active-actions" id="vmic_listener_actions" hidden>
      <button type="button" id="vmic_promote" class="vmic-join-button vmic-promote" title="التحويل إلى الاتصال" aria-label="التحويل إلى الاتصال"><i class="fa fa-microphone"></i></button>
      <button type="button" id="vmic_leave_listener" class="vmic-join-button vmic-leave" title="مغادرة الاستماع" aria-label="مغادرة الاستماع"><i class="fa fa-headphones"></i></button>
    </div>
    <div class="vmic-roster-scroll" id="vmic_roster_scroll" aria-label="المتصلون والمستمعون">
      <div class="vmic-roster-track" id="vmic_roster_track">
        <div class="vmic-roster-group vmic-speaker-group" id="vmic_speakers"></div>
        <div class="vmic-roster-divider" id="vmic_roster_divider" aria-hidden="true"></div>
        <div class="vmic-roster-group vmic-listener-group" id="vmic_listeners"></div>
        <div class="vmic-roster-empty" id="vmic_roster_empty">لا يوجد مشاركون</div>
      </div>
    </div>
    <button type="button" id="vmic_requests_button" class="vmic-requests-button" hidden title="طلبات التحدث"><i class="fa fa-hand-paper-o"></i><b id="vmic_requests_count">0</b></button>
    <button type="button" id="vmic_collapse" class="vmic-collapse" title="طي شريط المايك" aria-label="طي شريط المايك"><i class="fa fa-angle-down"></i></button>
  </div>
  <div class="vmic-mini-launcher" id="vmic_mini_launcher" hidden><button type="button" id="vmic_expand" title="فتح شريط المايك"><i class="fa fa-microphone"></i><span id="vmic_mini_count">0</span></button></div>
  <div class="vmic-offline-message" id="vmic_offline_message" hidden></div>
  <div class="vmic-profile" id="vmic_profile" hidden>
    <button type="button" class="vmic-profile-close" id="vmic_profile_close" aria-label="إغلاق">×</button>
    <div id="vmic_profile_info"></div>
    <div class="vmic-profile-actions" id="vmic_profile_actions"></div>
  </div>
  <div class="vmic-requests-panel" id="vmic_requests_panel" hidden>
    <div class="vmic-requests-head"><strong>طلبات التحدث</strong><button type="button" id="vmic_requests_close">×</button></div>
    <div id="vmic_requests_list"></div>
  </div>
  <div class="vmic-audio-bin" id="vmic_audio_bin" aria-hidden="true"></div>
  <div class="vmic-toast" id="vmic_toast" role="status" aria-live="polite"></div>
</div>
<link rel="stylesheet" type="text/css" href="addons/voice_mic/files/voice_mic.css<?php echo $vmic_version; ?>">
<script data-cfasync="false">
window.VoiceMicConfig = {
  endpoint: 'addons/voice_mic/system/api.php',
  roomId: <?php echo $vmic_room_id; ?>,
  userId: <?php echo (int)$data['user_id']; ?>,
  liveReady: <?php echo $vmic_live_ready?'true':'false'; ?>,
  token: <?php echo json_encode(setToken()); ?>
};
</script>
<?php if($vmic_live_ready){ ?><script data-cfasync="false" src="js/livekit/livekit.js<?php echo $vmic_version; ?>"></script><?php } ?>
<script data-cfasync="false" src="addons/voice_mic/files/voice_mic.js<?php echo $vmic_version; ?>"></script>
