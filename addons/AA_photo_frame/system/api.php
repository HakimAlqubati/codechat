<?php
$load_addons = 'AA_photo_frame';
require_once('../../../system/config_addons.php');
require_once(__DIR__ . '/frame_helpers.php');

header('Content-Type: application/json; charset=utf-8');
$ids = isset($_POST['ids']) && is_array($_POST['ids']) ? $_POST['ids'] : array();
$cleanIds = array();
foreach (array_slice($ids, 0, 200) as $id) {
    if (is_scalar($id) && ctype_digit((string)$id) && (int)$id > 0) {
        $cleanIds[(int)$id] = (int)$id;
    }
}
$items = array();
if (!empty($cleanIds)) {
    $idList = implode(',', $cleanIds);
    $query = $mysqli->query("SELECT u.user_id, u.user_rank,
        CASE WHEN r.rule_id IS NOT NULL THEN r.frame_name ELSE p.frame_name END AS frame_name,
        CASE WHEN r.rule_id IS NOT NULL THEN 1 ELSE 0 END AS frame_locked
        FROM boom_users u
        LEFT JOIN photo_frames p ON p.user_id = u.user_id
        LEFT JOIN photo_frame_rank_rules r ON r.rank_id = u.user_rank
        WHERE u.user_id IN ($idList)");
    if ($query) {
        while ($row = $query->fetch_assoc()) {
            $frame = aaSafeFrame($row['frame_name'] ?? '');
            $items[(string)$row['user_id']] = array(
                'frame' => $frame ?: '',
                'url' => $frame ? aaFrameImageUrl($frame) : '',
                'locked' => (int)$row['frame_locked'],
                'rank' => (int)$row['user_rank'],
            );
        }
    }
}
echo json_encode(array('items' => $items, 'scale' => aaFrameScale()), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
?>
