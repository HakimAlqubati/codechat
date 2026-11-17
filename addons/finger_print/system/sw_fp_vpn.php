<?php
$load_addons = 'finger_print';
require_once('../../../system/config_addons.php');
require_once(__DIR__ . '/runtime.php');
if (!boomAllow((int) $addons['addons_access'])) { die(); }
$list = '';
$get = $mysqli->query("SELECT * FROM boom_fp_vpn_log ORDER BY last_seen DESC LIMIT 300");
if ($get && $get->num_rows > 0) {
    while ($row = $get->fetch_assoc()) {
        $name = htmlspecialchars($row['user_name'], ENT_QUOTES, 'UTF-8');
        $ip = htmlspecialchars($row['ip'], ENT_QUOTES, 'UTF-8');
        $fp = htmlspecialchars($row['fingerprint'], ENT_QUOTES, 'UTF-8');
        $date = date('Y-m-d H:i', (int)$row['last_seen']);
        $list .= '<div class="sub_list_item fp_item"><div class="sub_list_cell"><div class="btable"><div class="bcell bold">' . $name . '</div><div class="bcell text_right">' . (int)$row['hits'] . ' محاولة</div></div><div class="text_xsmall tpad3"><b>التصنيف:</b> VPN / Proxy　<b>آخر رصد:</b> ' . $date . '</div><div class="text_xsmall tpad3"><b>IP:</b> ' . $ip . '</div><div class="text_xsmall tpad3"><b>البصمة:</b> <code>' . $fp . '</code></div><div class="text_xsmall tpad3 success">تم السماح بالدخول؛ السجل للمراقبة فقط</div></div></div>';
    }
} else { $list = emptyZone($lang['no_data']); }
?>
<style>.fp_search{padding:8px 0}.fp_search input{width:100%;padding:9px;border-radius:8px;border:1px solid rgba(128,128,128,.3);background:transparent;color:inherit}.fp_item{padding:12px;border:1px solid rgba(128,128,128,.2);border-radius:9px;margin-bottom:8px}.fp_item code{word-break:break-all}</style>
<div class="fp_search"><input type="search" oninput="fpFilterList(this.value)" placeholder="بحث بالاسم أو IP أو البصمة"></div><div id="container_new" class="box_height"><?php echo $list; ?></div>
