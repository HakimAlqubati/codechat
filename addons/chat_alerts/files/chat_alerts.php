<?php
if(!defined('BOOM') || !isset($addons['addons']) || $addons['addons'] !== 'chat_alerts'){
    return;
}
require_once BOOM_PATH . '/system/addon_menu_labels.php';
$chatAlertsMenuTitle = addonMenuLabelGet('chat_alerts', 'نظام الإشعارات');
?>
<script data-cfasync="false">
(function($){
    var menuTitle = <?php echo json_encode($chatAlertsMenuTitle, JSON_UNESCAPED_UNICODE); ?>;
    if(window.chatAlertsAddonLoaded){ return; }
    window.chatAlertsAddonLoaded = true;

    function buildChatAlertMenus(){
        var desktop = $('#left_menu #left_menu_content');
        var mobile = $('#chat_left_menu #left_main_content');

        if(desktop.length && !desktop.find('.chat_alert_menu_item').length){
            var itemDesktop = $('<div class="bhover left_menu_item chat_alert_menu_item" title="' + $('<div/>').text(menuTitle).html() + '" onclick="openChatAlerts();"><div class="left_menu_icon"><i class="fa fa-bullhorn leftmenui"></i><span class="chat_alert_badge"></span></div></div>');
            var wallDesktop = desktop.children('.left_menu_item').filter(function(){ return (($(this).attr('onclick') || '').indexOf('getWall') !== -1); }).first();
            var storyDesktop = desktop.children('#rw_news_desktop_menu').first();
            var newsDesktop = desktop.children('.left_menu_item').filter(function(){ return (($(this).attr('onclick') || '').indexOf('getNews') !== -1); }).first();
            if(wallDesktop.length){ wallDesktop.after(itemDesktop); }
            else if(storyDesktop.length){ storyDesktop.after(itemDesktop); }
            else if(newsDesktop.length){ newsDesktop.after(itemDesktop); }
            else { desktop.append(itemDesktop); }
        }

        if(mobile.length && !mobile.find('.chat_alert_menu_item').length){
            var itemMobile = $('<div class="fmenu_item bhover lmenu_item chat_alert_menu_item" onclick="openChatAlerts();"><div class="fmenu_icon"><i class="fa fa-bullhorn menui"></i></div><div class="fmenu_text"></div><div class="fmenu_notify"><span class="chat_alert_badge"></span></div></div>');
            itemMobile.find('.fmenu_text').text(menuTitle);
            var wallMobile = mobile.children('#wall_menu').first();
            var storyMobile = mobile.children('#rw_news_side_menu').first();
            var newsMobile = mobile.children('#news_menu').first();
            if(wallMobile.length){ wallMobile.after(itemMobile); }
            else if(storyMobile.length){ storyMobile.after(itemMobile); }
            else if(newsMobile.length){ newsMobile.after(itemMobile); }
            else { mobile.append(itemMobile); }
        }
    }

    window.refreshChatAlertCount = function(){
        $.post('addons/chat_alerts/system/count.php', {}, function(raw){
            var count = parseInt(raw, 10) || 0;
            $('.chat_alert_badge').each(function(){
                if(count > 0){ $(this).text(count > 99 ? '99+' : count).show(); }
                else { $(this).text('').hide(); }
            });
        });
    };

    window.openChatAlerts = function(){
        $.ajax({
            url: 'addons/chat_alerts/system/panel.php',
            type: 'post',
            cache: false,
            dataType: 'json',
            data: {},
            beforeSend: function(){ prepareLeft(380); },
            success: function(response){
                if(response && response.code == 1){
                    showLeftPanel(response.content, 380, response.title || menuTitle);
                    window.refreshChatAlertCount();
                }
            },
            error: function(){ callError(system.error); }
        });
    };

    function loadAlertsIntoNews(){
        if(!$('#container_news').length || $('#chat_alerts_news_feed').length){ return; }
        $.post('addons/chat_alerts/system/news.php', {}, function(html){
            if(html && $.trim(html) !== '' && $('#container_news').length && !$('#chat_alerts_news_feed').length){
                $('#container_news').prepend('<div id="chat_alerts_news_feed">' + html + '</div>');
            }
            window.refreshChatAlertCount();
        });
    }

    $(document).on('ajaxComplete.chatAlerts', function(event, xhr, settings){
        if(settings && settings.url && settings.url.indexOf('system/panel/news.php') !== -1){
            loadAlertsIntoNews();
        }
    });

    $(function(){
        buildChatAlertMenus();
        window.refreshChatAlertCount();
        window.setInterval(window.refreshChatAlertCount, 30000);
    });
})(jQuery);
</script>
<script data-cfasync="false">
boomAddCss('addons/chat_alerts/files/chat_alerts.css');
</script>
