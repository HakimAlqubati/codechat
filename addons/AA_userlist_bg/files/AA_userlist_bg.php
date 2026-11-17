<?php
include(addonsLang('AA_userlist_bg'));
require_once BOOM_PATH . '/system/addon_menu_labels.php';
$aaBgStoreTitle = addonMenuLabelGet('AA_userlist_bg', 'متجر خلفية العضو في المتصلين');
?>
<style>
/* Background is painted on the existing Codychat table-row element itself.
   No display, height, width, padding, position, or child layout is changed. */
.aa_ulbg_preview{width:100%;height:86px;margin-bottom:10px;border-radius:8px;border:1px solid rgba(255,255,255,.14);background-size:cover;background-position:center;background-repeat:no-repeat}
</style>
<script data-cfasync="false" type="text/javascript">
(function($){
    window.aaListBgData = {ok:0, users:[], personal:[], active:[], assets:[], rankBg:[]};
    window.aaListBgSaved = <?php echo json_encode($lang['list_bg_saved'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    window.aaListBgRemoved = <?php echo json_encode($lang['list_bg_removed'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    window.aaListBgError = <?php echo json_encode($lang['list_bg_error'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

    var refreshRequest = null;
    var refreshQueued = false;
    var refreshTimer = null;
    var applyTimer = null;
    var lastRefresh = 0;
    var lastIdKey = '';
    var minRefresh = 4500;

    function normalizeData(){
        if(!window.aaListBgData || !Array.isArray(aaListBgData.users)){
            window.aaListBgData = {ok:0, users:[], personal:[], active:[], assets:[], rankBg:[]};
        }
        ['users','personal','active','assets','rankBg'].forEach(function(key){
            if(!Array.isArray(aaListBgData[key])){ aaListBgData[key] = []; }
        });
    }
    function lookup(list, key, value){
        for(var i=0;i<list.length;i++){
            if(String(list[i][key]) === String(value)){ return list[i]; }
        }
        return null;
    }
    function userIdFromItem(item){
        var node = $(item);
        var userId = node.attr('data-id') || node.data('id') || node.find('.get_info').attr('data') || '';
        userId = String(userId).replace(/[^0-9]/g, '');
        return userId;
    }
    function fileUrl(file){
        return 'addons/AA_userlist_bg/files/background/' + encodeURIComponent(file);
    }
    function resolveBackground(userId){
        normalizeData();
        if(!aaListBgData || aaListBgData.ok !== 1){ return ''; }
        var user = lookup(aaListBgData.users, 'user_id', userId);
        if(!user){ return ''; }

        /* Match Codychat's userlistRank() rule: a room role is displayed for
           non-staff members; staff keep their global/system rank. */
        var type = (parseInt(user.user_rank,10) < 70 && parseInt(user.user_role,10) > 0) ? 'room' : 'system';
        var value = type === 'room' ? user.user_role : user.user_rank;
        var rank = null;
        for(var i=0;i<aaListBgData.rankBg.length;i++){
            var r = aaListBgData.rankBg[i];
            if(r.rank_type === type && String(r.rank_value) === String(value)){
                rank = r; break;
            }
        }
        if(rank && rank.bg_file){ return rank.bg_file; }

        var active = lookup(aaListBgData.active, 'user_id', userId);
        if(active){
            var asset = lookup(aaListBgData.assets, 'bg_id', active.bg_id);
            if(asset && asset.bg_file){ return asset.bg_file; }
        }
        var personal = lookup(aaListBgData.personal, 'user_id', userId);
        if(personal && personal.bg_file){ return personal.bg_file; }
        return '';
    }
    window.aaListBgApplyItem = function(item){
        var node = $(item);
        var userId = userIdFromItem(item);
        if(!userId){ return; }
        var file = resolveBackground(userId);
        if(file){
            if(!node.hasClass('aa_ulbg_has_background')){
                node.data('aa-ulbg-original-style', {image:node[0].style.backgroundImage || '', size:node[0].style.backgroundSize || '', position:node[0].style.backgroundPosition || '', repeat:node[0].style.backgroundRepeat || ''});
            }
            var url = fileUrl(file).replace(/"/g, '%22');
            /* The gradient is only a background paint layer. It does not add
               an element or participate in the table layout/row dimensions. */
            node[0].style.backgroundImage = 'linear-gradient(90deg, rgba(0,0,0,.45), rgba(0,0,0,.12) 52%, rgba(0,0,0,.42)), url("' + url + '")';
            node[0].style.backgroundSize = 'cover';
            node[0].style.backgroundPosition = 'center center';
            node[0].style.backgroundRepeat = 'no-repeat';
            node.addClass('aa_ulbg_has_background');
        }
        else if(node.hasClass('aa_ulbg_has_background')){
            var originalStyle = node.data('aa-ulbg-original-style') || {};
            if(originalStyle.image){ node[0].style.backgroundImage = originalStyle.image; } else { node[0].style.removeProperty('background-image'); }
            if(originalStyle.size){ node[0].style.backgroundSize = originalStyle.size; } else { node[0].style.removeProperty('background-size'); }
            if(originalStyle.position){ node[0].style.backgroundPosition = originalStyle.position; } else { node[0].style.removeProperty('background-position'); }
            if(originalStyle.repeat){ node[0].style.backgroundRepeat = originalStyle.repeat; } else { node[0].style.removeProperty('background-repeat'); }
            node.removeData('aa-ulbg-original-style');
            node.removeClass('aa_ulbg_has_background');
        }
    };
    window.aaListBgApplyAll = function(){
        document.querySelectorAll('#chat_right_data .user_item').forEach(function(item){
            aaListBgApplyItem(item);
        });
    };
    function visibleIds(){
        var out = [];
        $('#chat_right_data .user_item[data-id]').each(function(){
            var id = userIdFromItem(this);
            if(id && out.indexOf(id) === -1){ out.push(id); }
        });
        return out;
    }
    window.aaListBgRefresh = function(force){
        var ids = visibleIds();
        if(!ids.length){ aaListBgApplyAll(); return; }
        var idKey = ids.slice().sort(function(a,b){return parseInt(a,10)-parseInt(b,10);}).join(',');
        var now = Date.now();
        if(refreshRequest){ refreshQueued = true; return; }
        if(force !== true && idKey === lastIdKey && (now - lastRefresh) < minRefresh){
            aaListBgApplyAll(); return;
        }
        lastIdKey = idKey;
        lastRefresh = now;
        refreshRequest = $.ajax({
            url:'addons/AA_userlist_bg/system/api.php',
            type:'post', cache:false, timeout:10000,
            data:{token:utk, ids:JSON.stringify(ids)}, dataType:'json'
        }).done(function(response){
            if(response && response.ok === 1){
                window.aaListBgData = response;
                aaListBgApplyAll();
            }
        }).always(function(){
            refreshRequest = null;
            if(refreshQueued){
                refreshQueued = false;
                window.aaListBgRefresh(true);
            }
        });
    };
    window.aaListBgSetPersonal = function(userId, file){
        normalizeData();
        aaListBgData.personal = aaListBgData.personal.filter(function(row){return String(row.user_id) !== String(userId);});
        if(file){ aaListBgData.personal.push({user_id:String(userId), bg_file:file}); }
    };
    function scheduleRefresh(){
        clearTimeout(applyTimer);
        applyTimer = setTimeout(function(){ aaListBgApplyAll(); }, 100);
        clearTimeout(refreshTimer);
        refreshTimer = setTimeout(function(){ aaListBgRefresh(false); }, 650);
    }

    $(document).ready(function(){
        var target = document.getElementById('chat_right_data');
        if(target && window.MutationObserver){
            var observer = new MutationObserver(function(mutations){
                var relevant = false;
                for(var i=0;i<mutations.length;i++){
                    if(mutations[i].addedNodes.length || mutations[i].removedNodes.length){ relevant = true; break; }
                }
                if(relevant){ scheduleRefresh(); }
            });
            observer.observe(target, {childList:true, subtree:true});
        }
        aaListBgRefresh(true);
        aaListBgApplyAll();
        /* Recheck occasionally so rank changes made by staff appear in already-open lists. */
        window.setInterval(function(){ aaListBgRefresh(false); }, 15000);
    });

    <?php if(boomAllow($addons['addons_access'])){ ?>
    $(document).ready(function(){
        <?php if(boomAllow($addons['custom1'])){ ?>
        if($('.avstaff .aa-ulbg-staff-menu').length === 0){
            $('.avstaff').append(
                "<div data='' onclick='showListBgBoxStaff(this)' class='avset avitem rcustom aa-ulbg-staff-menu'><span class='list_icon'><i class='fa fa-image bgrad24'></i></span> <?php echo addslashes($lang['open_list_bg']); ?></div>"
            );
        }
        <?php } ?>
        if($('.avself .aa-ulbg-self-menu').length === 0){
            $('.avself').append(
                "<div data='' onclick='showListBgBox(this)' class='avset avitem rcustom aa-ulbg-self-menu'><span class='list_icon'><i class='fa fa-image bgrad24'></i></span> <?php echo addslashes($lang['open_list_bg']); ?></div>"
            );
        }
        window.showListBgBoxStaff = function(source){
            var targetId = $(source).attr('data');
            $.post('addons/AA_userlist_bg/system/open_bg_staff.php', {target:targetId, token:utk}, function(response){
                if(!response || response == 0){ callSaved(aaListBgError, 3); }
                else { showEmptyModal(response, 390); }
            });
        };
        window.showListBgBox = function(){
            $.post('addons/AA_userlist_bg/system/open_bg.php', {token:utk}, function(response){
                if(!response || response == 0){ callSaved(aaListBgError, 3); }
                else { showEmptyModal(response, 440); }
            });
        };
    });
    <?php } ?>
})(jQuery);
</script>

<?php if(boomAllow($addons['addons_access'])){ ?>
<script data-cfasync="false" type="text/javascript">
$(function(){
    window.openAAListBgStore = function(){
        $.post('addons/AA_userlist_bg/system/open_bg.php', {token:utk}, function(response){
            if(!response || response == 0){ callSaved(aaListBgError, 3); }
            else { showAddonFullPage(response, 520, 'large'); }
        }).fail(function(){ callError(system.error); });
    };
    if(typeof addonStoreMenu === 'function'){
        addonStoreMenu('AA_userlist_bg', 50, 'addons/AA_userlist_bg/files/icon.png', <?php echo json_encode($aaBgStoreTitle, JSON_UNESCAPED_UNICODE); ?>, 'openAAListBgStore()');
    }
});
</script>
<?php } ?>
