<?php
/** Shared appearance settings for pages opened by add-ons inside the chat. */
if(!function_exists('addonPageAppearanceEnsureSchema')){
    function addonPageAppearanceEnsureSchema(){
        global $mysqli;
        if(!isset($mysqli) || !($mysqli instanceof mysqli)){ return false; }
        $sql = "CREATE TABLE IF NOT EXISTS boom_addon_page_appearance (
            appearance_id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
            bg_file VARCHAR(255) NOT NULL DEFAULT '',
            tint_color CHAR(7) NOT NULL DEFAULT '#000000',
            tint_opacity TINYINT UNSIGNED NOT NULL DEFAULT 18,
            bg_size VARCHAR(16) NOT NULL DEFAULT 'cover',
            updated_by INT UNSIGNED NOT NULL DEFAULT 0,
            updated_at INT UNSIGNED NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        return (bool)$mysqli->query($sql);
    }
}
if(!function_exists('addonPageAppearanceGet')){
    function addonPageAppearanceGet(){
        global $mysqli;
        $default = array('bg_file'=>'','tint_color'=>'#000000','tint_opacity'=>18,'bg_size'=>'cover');
        if(!isset($mysqli) || !($mysqli instanceof mysqli)){ return $default; }
        $q = @$mysqli->query("SELECT bg_file,tint_color,tint_opacity,bg_size FROM boom_addon_page_appearance WHERE appearance_id=1 LIMIT 1");
        if(!$q){ return $default; }
        $row = $q->fetch_assoc(); $q->free();
        if(!$row){ return $default; }
        $row['bg_file'] = (string)$row['bg_file'];
        $row['tint_color'] = preg_match('/^#[0-9a-fA-F]{6}$/', (string)$row['tint_color']) ? $row['tint_color'] : '#000000';
        $row['tint_opacity'] = max(0, min(90, (int)$row['tint_opacity']));
        $row['bg_size'] = in_array($row['bg_size'], array('cover','contain','100% 100%'), true) ? $row['bg_size'] : 'cover';
        return $row;
    }
}
if(!function_exists('addonPageAppearanceCss')){
    function addonPageAppearanceCss(){
        $s = addonPageAppearanceGet();
        $file = basename((string)$s['bg_file']);
        if($file === '' || !preg_match('/^apg_[a-f0-9]{32}\.(jpg|png|gif|webp)$/i', $file)){ return ''; }
        $hex = ltrim($s['tint_color'], '#');
        $r = hexdec(substr($hex, 0, 2)); $g = hexdec(substr($hex, 2, 2)); $b = hexdec(substr($hex, 4, 2));
        $a = round(((int)$s['tint_opacity']) / 100, 2);
        $url = 'upload/addon_page_appearance/' . $file;
        $size = $s['bg_size'];
        return '<style id="addon-page-appearance-css">'
            . 'body.boom-chat-viewport .addon-full-page-modal .addon-full-page-in{' 
            . 'background-image:linear-gradient(rgba(' . $r . ',' . $g . ',' . $b . ',' . $a . '),rgba(' . $r . ',' . $g . ',' . $b . ',' . $a . ')),url("' . $url . '") !important;'
            . 'background-position:center center !important;background-repeat:no-repeat !important;background-size:' . $size . ' !important;}'
            . 'body.boom-chat-viewport .addon-full-page-modal .addon-full-page-content{background:transparent !important;}'
            . '</style>';
    }
}
?>
