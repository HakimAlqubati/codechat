<?php
include(addonsLang('DSG_Admin_User_Manager'));
if(isset($addons['addons_access']) && boomAllow($addons['addons_access'])){
?>
<script data-cfasync="false" type="text/javascript">
(function($){
    $(function(){
        // CodyChat's renderAvMenu() fills the data attribute on every .avset
        // item with the clicked user's ID before it clones the context menu.
        var menuGroups = ['.avstaff', '.avroomstaff', '.avother'];
        $.each(menuGroups, function(_, selector){
            var $menu = $('#action_menu ' + selector);
            if($menu.length && !$menu.find('.dsg-user-manager-item').length){
                $menu.append(
                    '<div data="" onclick="openDSGUserManager(this)" class="avset bmenu avitem dsg-user-manager-item">' +
                    '<i class="fa fa-user-gear theme_color"></i> ' + <?php echo json_encode($lang['manage_user_stats'], JSON_UNESCAPED_UNICODE); ?> +
                    '</div>'
                );
            }
        });
    });

    window.openDSGUserManager = function(source){
        var target = parseInt($(source).attr('data'), 10) || 0;
        if(target <= 0){
            callError('تعذر تحديد العضو المطلوب. افتح قائمة العضو من صورته وحاول مجدداً.');
            return;
        }
        $.post('addons/DSG_Admin_User_Manager/system/edituser_simple.php', {
            target: target,
            token: utk
        }, function(response){
            if(typeof response !== 'string' || response.indexOf('dsg-manager-modal') === -1){
                callError('تعذر فتح إدارة العضو. تحقق من صلاحياتك وإعدادات الإضافة.');
                return;
            }
            showModal(response, 560);
            closeTrigger();
        }).fail(function(){
            callError('تعذر تحميل بيانات العضو.');
        });
    };
})(jQuery);
</script>
<?php } ?>
