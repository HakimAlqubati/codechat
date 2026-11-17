<?php
$load_addons = 'finger_print';
require_once('../../../system/config_addons.php');
require_once(__DIR__ . '/runtime.php');
if (!boomAllow((int) $addons['addons_access'])) { die(); }
$list = '';
$get = $mysqli->query("SELECT j.*, (SELECT COUNT(*) FROM boom_users u WHERE u.parmakizi = j.fingerprint) AS linked_accounts FROM boom_fp_jail j ORDER BY j.active DESC, j.created_at DESC LIMIT 300");
if ($get && $get->num_rows > 0) {
    while ($jail = $get->fetch_assoc()) {
        $active = (int) $jail['active'] === 1 && ((int)$jail['expires_at'] === 0 || (int)$jail['expires_at'] > time());
        $state = $active ? '<span class="success">مسجون</span>' : '<span class="sub_text">منتهي / مفكوك</span>';
        $expires = (int)$jail['expires_at'] === 0 ? 'دائم' : date('Y-m-d H:i', (int)$jail['expires_at']);
        $created = date('Y-m-d H:i', (int)$jail['created_at']);
        $name = htmlspecialchars($jail['target_name'], ENT_QUOTES, 'UTF-8');
        $reason = htmlspecialchars($jail['reason'], ENT_QUOTES, 'UTF-8');
        $fp = htmlspecialchars($jail['fingerprint'], ENT_QUOTES, 'UTF-8');
        $createdBy = htmlspecialchars($jail['created_by_name'], ENT_QUOTES, 'UTF-8');
        $button = $active ? '<span class="fp_remove" onclick="releaseFingerprintJail(' . (int)$jail['id'] . ')"><i class="fa fa-unlock"></i> فك السجن</span>' : '';
        $list .= '<div class="sub_list_item fp_item jail' . (int)$jail['id'] . '"><div class="sub_list_cell"><div class="btable"><div class="bcell bold">' . $name . ' <span class="text_xsmall sub_text">(#' . (int)$jail['target_user'] . ')</span></div><div class="bcell text_right">' . $button . '</div></div><div class="text_xsmall tpad3">' . $state . '　<b>تاريخ السجن:</b> ' . htmlspecialchars($created, ENT_QUOTES, 'UTF-8') . '　<b>الانتهاء:</b> ' . htmlspecialchars($expires, ENT_QUOTES, 'UTF-8') . '　<b>بواسطة:</b> ' . $createdBy . '</div><div class="text_xsmall tpad3"><b>السبب:</b> ' . ($reason !== '' ? $reason : 'غير مسجل') . '</div><div class="text_xsmall tpad3"><b>الحسابات المرتبطة:</b> ' . (int)$jail['linked_accounts'] . '</div><div class="text_xsmall tpad3"><b>البصمة:</b> <code>' . $fp . '</code></div></div></div>';
    }
} else { $list = emptyZone($lang['no_data']); }
?>
<style>.fp_search{padding:8px 0}.fp_search input{width:100%;padding:9px;border-radius:8px;border:1px solid rgba(128,128,128,.3);background:transparent;color:inherit}.fp_item{padding:12px;border:1px solid rgba(128,128,128,.2);border-radius:9px;margin-bottom:8px}.fp_remove{cursor:pointer;color:#e05252}.fp_item code{word-break:break-all}</style>
<div class="fp_search"><input type="search" oninput="fpFilterList(this.value)" placeholder="بحث بالاسم أو البصمة أو السبب"></div><div id="container_new" class="box_height"><?php echo $list; ?></div>
