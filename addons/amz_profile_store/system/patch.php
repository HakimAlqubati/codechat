<?php
if(!defined('BOOM')){ die(); }
function amzStoreApplyCorePatches(){
    $root = defined('BOOM_PATH') ? BOOM_PATH : dirname(__DIR__, 3);
    $addon = $root . '/addons/amz_profile_store';
    $backupDir = $addon . '/system/backup';
    if(!is_dir($backupDir)){ @mkdir($backupDir, 0755, true); }
    $report = array('function_all'=>false, 'profile'=>false, 'errors'=>array());

    $fa = $root . '/system/function_all.php';
    if(!is_file($fa)){ $report['errors'][] = 'لم يتم العثور على system/function_all.php'; }
    else {
        $src = file_get_contents($fa);
        $originalSrc = $src;
        if(strpos($src, 'AMZ_PROFILE_STORE_GATE_INCLUDE') === false){
            $backupOk = is_file($backupDir . '/function_all.php') || @copy($fa, $backupDir . '/function_all.php');
            if(!$backupOk){ $report['errors'][] = 'تعذر إنشاء نسخة احتياطية من system/function_all.php؛ لم يتم تعديله.'; }
            else { $src = preg_replace('/<\?php\s*/', "<?php\n/* AMZ_PROFILE_STORE_GATE_INCLUDE */\nif (defined('BOOM_PATH') && is_file(BOOM_PATH . '/addons/amz_profile_store/system/profile_gate.php')) { require_once BOOM_PATH . '/addons/amz_profile_store/system/profile_gate.php'; }\n", $src, 1); }
        }
        if(strpos($src, 'AMZ_PROFILE_STORE_MUSIC_GATE') === false){
            $pattern = '/function\s+canProfileMusic\s*\(\s*\)\s*\{.*?\n\}/s';
            $replacement = "function canProfileMusic(){\n\tglobal \$setting, \$data;\n\tif(boomAllow(\$setting['allow_pmusic']) && !featureBlock(\$data['bupload'])){\n\t\t/* AMZ_PROFILE_STORE_MUSIC_GATE */\n\t\tif(function_exists('amzStoreCanUseMusic') && !amzStoreCanUseMusic(\$data)){ return false; }\n\t\treturn true;\n\t}\n\treturn false;\n}";
            $new = preg_replace($pattern, $replacement, $src, 1, $count);
            if($count === 1){ $src = $new; }
            else { $report['errors'][] = 'تعذر ربط صلاحية الموسيقى بدالة canProfileMusic؛ قد يختلف ملف الشات عن النسخة المفحوصة.'; }
        }
        if(strpos($src, 'AMZ_PROFILE_STORE_GATE_INCLUDE') !== false && strpos($src, 'AMZ_PROFILE_STORE_MUSIC_GATE') !== false){
            if($src === $originalSrc || file_put_contents($fa, $src) !== false){ $report['function_all'] = true; }
            else { $report['errors'][] = 'لا توجد صلاحية كتابة إلى system/function_all.php'; }
        }
    }

    $profile = $root . '/system/box/profile.php';
    if(!is_file($profile)){ $report['errors'][] = 'لم يتم العثور على system/box/profile.php'; }
    else {
        $src = str_replace("\r\n", "\n", file_get_contents($profile));
        $originalSrc = $src;
        if(strpos($src, 'AMZ_PROFILE_STORE_PROFILE_ASSETS') === false){
            $backupOk = is_file($backupDir . '/profile.php') || @copy($profile, $backupDir . '/profile.php');
            if(!$backupOk){ $report['errors'][] = 'تعذر إنشاء نسخة احتياطية من system/box/profile.php؛ لم يتم تعديله.'; }
            else {
                $find = "if(empty(\$user)){\n\tdie();\n}";
                $add = $find . "\n/* AMZ_PROFILE_STORE_PROFILE_ASSETS */\n\$amz_store_bg = function_exists('amzStoreActiveAsset') ? amzStoreActiveAsset((int)\$user['user_id'], 'background') : '';\n\$amz_store_frame = function_exists('amzStoreActiveAsset') ? amzStoreActiveAsset((int)\$user['user_id'], 'frame') : '';";
                if(strpos($src, $find) !== false){ $src = str_replace($find, $add, $src); }
                else { $report['errors'][] = 'تعذر تحديد موضع تحميل بيانات الملف الشخصي لإضافة سمات المتجر.'; }
                $src = str_replace('class="modal_wrap_top pro_top profile_background <?php echo coverClass($user); ?>"', 'class="modal_wrap_top pro_top profile_background <?php echo coverClass($user); ?>" data-amz-profile-bg="<?php echo htmlspecialchars($amz_store_bg, ENT_QUOTES, \'UTF-8\'); ?>"', $src);
                $src = str_replace('id="proav" class="profile_avatar" data="<?php echo $user[\'user_tumb\']; ?>"', 'id="proav" class="profile_avatar" data="<?php echo $user[\'user_tumb\']; ?>" data-amz-profile-frame="<?php echo htmlspecialchars($amz_store_frame, ENT_QUOTES, \'UTF-8\'); ?>"', $src);
            }
        }
        if(strpos($src, 'AMZ_PROFILE_STORE_PROFILE_ASSETS') !== false && strpos($src, 'data-amz-profile-bg=') !== false && strpos($src, 'data-amz-profile-frame=') !== false){
            if($src === $originalSrc || file_put_contents($profile, $src) !== false){ $report['profile'] = true; }
            else { $report['errors'][] = 'لا توجد صلاحية كتابة إلى system/box/profile.php'; }
        }
    }
    return $report;
}
?>
