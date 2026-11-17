<?php
$load_addons = 'finger_print';
require_once('../../../system/config_addons.php');
require_once(__DIR__ . '/runtime.php');
if (!boomAllow((int) $addons['addons_access'])) { die(); }
$list = '';
$get = $mysqli->query("SELECT p.*, (SELECT COUNT(*) FROM boom_users u WHERE u.parmakizi = p.parmakizleri) AS linked_accounts FROM boom_permabanned p ORDER BY p.ban_date DESC, p.id DESC LIMIT 300");
if ($get && $get->num_rows > 0) {
    while ($ban = $get->fetch_assoc()) {
        $name = htmlspecialchars($ban['user_name'], ENT_QUOTES, 'UTF-8');
        $hunter = htmlspecialchars($ban['ban_hunter'], ENT_QUOTES, 'UTF-8');
        $ip = htmlspecialchars($ban['ip'], ENT_QUOTES, 'UTF-8');
        $fp = htmlspecialchars($ban['parmakizleri'], ENT_QUOTES, 'UTF-8');
        $reason = htmlspecialchars($ban['reason'], ENT_QUOTES, 'UTF-8');
        $date = (int) $ban['ban_date'] > 0 ? date('Y-m-d H:i', (int) $ban['ban_date']) : 'سجل قديم';
        $list .= '<div class="sub_list_item fp_item finger' . (int)$ban['id'] . '"><div class="sub_list_cell"><div class="btable"><div class="bcell bold">' . $name . ' <span class="text_xsmall sub_text">(#' . (int)$ban['ban_user'] . ')</span></div><div class="bcell text_right"><span class="fp_remove" onclick="delFingerUser(' . (int)$ban['id'] . ')"><i class="fa fa-times error"></i> فك الحظر</span></div></div><div class="text_xsmall tpad3"><b>المراقب:</b> ' . $hunter . '　 <b>التاريخ:</b> ' . htmlspecialchars($date, ENT_QUOTES, 'UTF-8') . '</div><div class="text_xsmall tpad3"><b>الحسابات المرتبطة:</b> ' . (int)$ban['linked_accounts'] . '</div><div class="text_xsmall tpad3"><b>IP:</b> ' . $ip . '</div><div class="text_xsmall tpad3"><b>البصمة:</b> <code>' . $fp . '</code></div><div class="text_xsmall tpad3"><b>السبب:</b> ' . ($reason !== '' ? $reason : 'غير مسجل') . '</div></div></div>';
    }
} else { $list = emptyZone($lang['no_data']); }
?>
<style>.fp_search{padding:8px 0}.fp_search input{width:100%;padding:9px;border-radius:8px;border:1px solid rgba(128,128,128,.3);background:transparent;color:inherit}.fp_item{padding:12px;border:1px solid rgba(128,128,128,.2);border-radius:9px;margin-bottom:8px}.fp_remove{cursor:pointer;color:#e05252}.fp_item code{word-break:break-all}</style>
<div class="fp_search"><input type="search" oninput="fpFilterList(this.value)" placeholder="بحث بالاسم أو البصمة أو IP أو السبب"></div><div id="container_new" class="box_height"><?php echo $list; ?></div>
