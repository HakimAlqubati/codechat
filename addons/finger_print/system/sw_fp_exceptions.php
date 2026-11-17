<?php
$load_addons = 'finger_print';
require_once('../../../system/config_addons.php');
require_once(__DIR__ . '/runtime.php');
if (!boomAllow((int) $addons['addons_access'])) { die(); }
$list = '';
$get = $mysqli->query("SELECT * FROM boom_fp_exceptions WHERE active = 1 ORDER BY created_at DESC LIMIT 300");
if ($get && $get->num_rows > 0) {
    while ($row = $get->fetch_assoc()) {
        $name = htmlspecialchars($row['user_name'], ENT_QUOTES, 'UTF-8');
        $reason = htmlspecialchars($row['reason'], ENT_QUOTES, 'UTF-8');
        $fingerprint = htmlspecialchars($row['fingerprint'], ENT_QUOTES, 'UTF-8');
        $actor = htmlspecialchars($row['created_by_name'], ENT_QUOTES, 'UTF-8');
        $date = date('Y-m-d H:i', (int) $row['created_at']);
        $list .= '<div class="sub_list_item fp_item exception' . (int)$row['id'] . '"><div class="sub_list_cell"><div class="btable"><div class="bcell bold">' . $name . ' <span class="text_xsmall sub_text">(#' . (int)$row['user_id'] . ')</span></div><div class="bcell text_right"><span class="fp_remove" onclick="removeFingerprintException(' . (int)$row['id'] . ')"><i class="fa fa-times error"></i> إلغاء الاستثناء</span></div></div><div class="text_xsmall tpad3"><b>بواسطة:</b> ' . $actor . '　<b>التاريخ:</b> ' . $date . '</div><div class="text_xsmall tpad3"><b>السبب:</b> ' . ($reason !== '' ? $reason : 'غير مسجل') . '</div><div class="text_xsmall tpad3"><b>البصمة المستثناة لهذا الحساب:</b> <code>' . $fingerprint . '</code></div></div></div>';
    }
} else { $list = emptyZone($lang['no_data']); }
?>
<style>.fp_search{padding:8px 0}.fp_search input{width:100%;padding:9px;border-radius:8px;border:1px solid rgba(128,128,128,.3);background:transparent;color:inherit}.fp_item{padding:12px;border:1px solid rgba(128,128,128,.2);border-radius:9px;margin-bottom:8px}.fp_remove{cursor:pointer;color:#e05252}.fp_item code{word-break:break-all}</style>
<div class="fp_search"><button type="button" class="reg_button theme_btn" onclick="addFingerprintExceptionById()"><i class="fa fa-user-check"></i> إضافة استثناء لرقم عضوية</button><input type="search" oninput="fpFilterList(this.value)" placeholder="بحث بالاسم أو رقم العضوية أو البصمة أو السبب"></div><div id="container_new" class="box_height"><?php echo $list; ?></div>
