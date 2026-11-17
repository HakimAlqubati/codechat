<?php
$load_addons = 'finger_print';
require_once('../../../system/config_addons.php');
require_once(__DIR__ . '/runtime.php');
if (!boomAllow((int) $addons['addons_access'])) { die(); }
$list = '';
$get = $mysqli->query("SELECT * FROM boom_fp_history ORDER BY event_date DESC LIMIT 300");
$labels = array('cemetery_add'=>'إضافة إلى المقبرة','cemetery_remove'=>'فك حظر المقبرة','jail_add'=>'إدخال السجن','jail_release'=>'فك السجن','jail_expired'=>'انتهاء السجن تلقائياً','vpn_proxy_detected'=>'رصد VPN/Proxy','fingerprint_seen'=>'تحديث البصمة','cemetery_exception_add'=>'استثناء حساب من المقبرة','cemetery_exception_remove'=>'إلغاء استثناء المقبرة');
if ($get && $get->num_rows > 0) {
    while ($row = $get->fetch_assoc()) {
        $event = isset($labels[$row['event_type']]) ? $labels[$row['event_type']] : $row['event_type'];
        $target = htmlspecialchars($row['target_name'], ENT_QUOTES, 'UTF-8');
        $actor = htmlspecialchars($row['actor_name'], ENT_QUOTES, 'UTF-8');
        $details = htmlspecialchars($row['details'], ENT_QUOTES, 'UTF-8');
        $fp = htmlspecialchars($row['fingerprint'], ENT_QUOTES, 'UTF-8');
        $ip = htmlspecialchars($row['ip'], ENT_QUOTES, 'UTF-8');
        $date = date('Y-m-d H:i', (int)$row['event_date']);
        $list .= '<div class="sub_list_item fp_item"><div class="sub_list_cell"><div class="btable"><div class="bcell bold">' . htmlspecialchars($event, ENT_QUOTES, 'UTF-8') . '</div><div class="bcell text_right text_xsmall">' . $date . '</div></div><div class="text_xsmall tpad3"><b>العضو:</b> ' . $target . '　<b>المنفذ:</b> ' . $actor . '</div><div class="text_xsmall tpad3"><b>IP:</b> ' . $ip . '</div><div class="text_xsmall tpad3"><b>البصمة:</b> <code>' . $fp . '</code></div><div class="text_xsmall tpad3">' . $details . '</div></div></div>';
    }
} else { $list = emptyZone($lang['no_data']); }
?>
<style>.fp_search{padding:8px 0}.fp_search input{width:100%;padding:9px;border-radius:8px;border:1px solid rgba(128,128,128,.3);background:transparent;color:inherit}.fp_item{padding:12px;border:1px solid rgba(128,128,128,.2);border-radius:9px;margin-bottom:8px}</style>
<div class="fp_search"><input type="search" oninput="fpFilterList(this.value)" placeholder="بحث بالعضو أو المنفذ أو نوع العملية أو التفاصيل"></div><div id="container_new" class="box_height"><?php echo $list; ?></div>
