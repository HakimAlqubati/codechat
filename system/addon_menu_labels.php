<?php
/**
 * Shared settings for labels of add-ons that register entries in CodyChat menus.
 * This table stores presentation labels only; add-on identifiers remain unchanged.
 */
if(!function_exists('addonMenuLabelEnsureSchema')){
    function addonMenuLabelEnsureSchema(){
        global $mysqli;
        static $ready = null;
        if($ready !== null){ return $ready; }
        if(!isset($mysqli) || !($mysqli instanceof mysqli)){ $ready = false; return false; }
        $sql = "CREATE TABLE IF NOT EXISTS boom_addon_menu_labels (
            addon_name VARCHAR(64) NOT NULL,
            menu_label VARCHAR(120) NOT NULL,
            updated_by INT UNSIGNED NOT NULL DEFAULT 0,
            updated_at INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (addon_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        $ready = (bool)$mysqli->query($sql);
        return $ready;
    }
}
if(!function_exists('addonMenuLabelGet')){
    function addonMenuLabelGet($addonName, $fallback){
        global $mysqli;
        static $cache = array();
        $addonName = preg_replace('/[^A-Za-z0-9_]/', '', (string)$addonName);
        if($addonName === ''){ return (string)$fallback; }
        if(array_key_exists($addonName, $cache)){ return $cache[$addonName]; }
        if(!addonMenuLabelEnsureSchema()){ return $cache[$addonName] = (string)$fallback; }
        $stmt = $mysqli->prepare('SELECT menu_label FROM boom_addon_menu_labels WHERE addon_name = ? LIMIT 1');
        if(!$stmt){ return $cache[$addonName] = (string)$fallback; }
        $stmt->bind_param('s', $addonName);
        $stmt->execute();
        $stmt->bind_result($label);
        $found = $stmt->fetch();
        $stmt->close();
        $label = $found ? trim((string)$label) : '';
        return $cache[$addonName] = ($label !== '' ? $label : (string)$fallback);
    }
}
if(!function_exists('addonMenuLabelClientScript')){
    function addonMenuLabelClientScript(){
        static $printed = false;
        if($printed){ return ''; }
        $printed = true;
        return '<script data-cfasync="false">'
            . 'window.saveAddonMenuLabel=window.saveAddonMenuLabel||function(addon){var el=document.getElementById("addon_menu_label_"+addon);if(!el){return;}var value=(el.value||"").trim();if(!value){if(window.callError){callError("اكتب اسم الظهور أولاً");}return;}$.post("system/addon_menu_label_action.php",{token:window.utk,action:"save_menu_label",addon:addon,label:value},function(r){if(r&&r.code==1){if(window.callSuccess){callSuccess(r.message);} }else if(window.callError){callError((r&&r.message)||"تعذر الحفظ");}},"json").fail(function(){if(window.callError){callError("تعذر الاتصال بالخادم");}});};'
            . 'window.saveAddonBotName=window.saveAddonBotName||function(addon){var el=document.getElementById("addon_bot_name_"+addon);if(!el){return;}var value=(el.value||"").trim();$.post("system/addon_menu_label_action.php",{token:window.utk,action:"save_bot_name",addon:addon,name:value},function(r){if(r&&r.code==1){if(window.callSuccess){callSuccess(r.message);} }else if(window.callError){callError((r&&r.message)||"تعذر حفظ اسم البوت");}},"json").fail(function(){if(window.callError){callError("تعذر الاتصال بالخادم");}});};'
            . '</script>';
    }
}
if(!function_exists('addonMenuLabelSettingsBlock')){
    function addonMenuLabelSettingsBlock($addonName, $fallback, $description = 'يظهر هذا الاسم في القائمة الجانبية للمتجر أو الإضافة.'){
        $addonName = preg_replace('/[^A-Za-z0-9_]/', '', (string)$addonName);
        if($addonName === ''){ return ''; }
        $current = addonMenuLabelGet($addonName, $fallback);
        $safe = htmlspecialchars($current, ENT_QUOTES, 'UTF-8');
        $safeAddon = htmlspecialchars($addonName, ENT_QUOTES, 'UTF-8');
        $safeDescription = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
        return '<div class="config_section addon-menu-label-setting">'
            . '<div class="setting_element"><p class="label">اسم الظهور في القائمة الجانبية</p>'
            . '<input type="text" class="full_input" maxlength="120" id="addon_menu_label_' . $safeAddon . '" value="' . $safe . '">'
            . '<p class="sub_text">' . $safeDescription . '</p></div>'
            . '<button type="button" class="reg_button theme_btn" onclick="saveAddonMenuLabel(\'' . $safeAddon . '\')"><i class="fa fa-save"></i> حفظ اسم الظهور</button>'
            . '</div>' . addonMenuLabelClientScript();
    }
}
if(!function_exists('addonBotNameGet')){
    function addonBotNameGet($addonName, $fallback = ''){
        global $mysqli;
        $addonName = preg_replace('/[^A-Za-z0-9_]/', '', (string)$addonName);
        if($addonName === '' || !isset($mysqli)){ return (string)$fallback; }
        $stmt = $mysqli->prepare('SELECT bot_name FROM boom_addons WHERE addons = ? LIMIT 1');
        if(!$stmt){ return (string)$fallback; }
        $stmt->bind_param('s', $addonName);
        $stmt->execute();
        $stmt->bind_result($name);
        $found = $stmt->fetch();
        $stmt->close();
        return ($found && trim((string)$name) !== '') ? trim((string)$name) : (string)$fallback;
    }
}
if(!function_exists('addonBotNameSettingsBlock')){
    function addonBotNameSettingsBlock($addonName, $fallback, $title){
        $addonName = preg_replace('/[^A-Za-z0-9_]/', '', (string)$addonName);
        if($addonName === ''){ return ''; }
        $value = htmlspecialchars(addonBotNameGet($addonName, $fallback), ENT_QUOTES, 'UTF-8');
        $addon = htmlspecialchars($addonName, ENT_QUOTES, 'UTF-8');
        $heading = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        return '<div class="setting_element addon-bot-name-setting"><p class="label">' . $heading . '</p>'
            . '<input class="full_input" type="text" maxlength="32" id="addon_bot_name_' . $addon . '" value="' . $value . '">'
            . '<button type="button" class="reg_button theme_btn tmargin10" onclick="saveAddonBotName(\'' . $addon . '\')">حفظ اسم البوت</button></div>' . addonMenuLabelClientScript();
    }
}
?>
