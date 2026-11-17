<?php
include(addonsLang('AA_photo_frame'));
require_once(__DIR__ . '/../system/frame_helpers.php');
require_once BOOM_PATH . '/system/addon_menu_labels.php';
$aaFrameStoreTitle = addonMenuLabelGet('AA_photo_frame', 'متجر الإطارات');
$staffAccess = aaFrameStaffAccess($addons);
?>
<script data-cfasync="false" type="text/javascript">
(function() {
    if (window.AAFrameControllerLoaded) return;
    window.AAFrameControllerLoaded = true;
    var frameCache = Object.create(null);
    var requested = Object.create(null);
    var currentScale = 116;
    var scanTimer = null;
    var apiUrl = 'addons/AA_photo_frame/system/api.php';
    var hostSelector = '.chat_avatar[data-id], .user_item[data-id] .user_item_avatar, .post_avatar[data], .reply_avatar[data], .puser_avatar[data], #proav[data-user], .profile_avatar[data-user]';

    function getUserId(host) {
        if (!host) return '';
        if (host.classList.contains('user_item_avatar')) {
            var parent = host.closest('.user_item[data-id]');
            return parent ? String(parent.getAttribute('data-id') || '') : '';
        }
        return String(host.getAttribute('data-id') || host.getAttribute('data-user') || host.getAttribute('data') || '');
    }
    function getHosts(root) {
        var scope = root && root.querySelectorAll ? root : document;
        var list = [];
        if (scope.matches && scope.matches(hostSelector)) list.push(scope);
        scope.querySelectorAll(hostSelector).forEach(function(el) { list.push(el); });
        return list;
    }
    function setFrame(host, item) {
        if (!host) return;
        var overlay = host.querySelector(':scope > .aa-photo-frame-overlay');
        if (!item || !item.url || !item.frame) {
            if (overlay) overlay.remove();
            host.classList.remove('aa-photo-frame-host');
            return;
        }
        if (!overlay) {
            overlay = document.createElement('img');
            overlay.className = 'aa-photo-frame-overlay';
            overlay.alt = '';
            overlay.setAttribute('aria-hidden', 'true');
            overlay.setAttribute('draggable', 'false');
            overlay.loading = 'eager';
            host.appendChild(overlay);
        }
        host.classList.add('aa-photo-frame-host');
        if (overlay.getAttribute('src') !== item.url) overlay.setAttribute('src', item.url);
        overlay.style.width = currentScale + '%';
        overlay.style.height = currentScale + '%';
        overlay.dataset.frame = item.frame;
        overlay.dataset.locked = item.locked ? '1' : '0';
    }
    function applyCache() {
        getHosts(document).forEach(function(host) {
            var id = getUserId(host);
            if (!/^\d+$/.test(id)) return;
            if (Object.prototype.hasOwnProperty.call(frameCache, id)) setFrame(host, frameCache[id]);
        });
    }
    function requestMissing(ids) {
        ids = ids.filter(function(id) { return !requested[id]; });
        if (!ids.length) return;
        var batches = [];
        for (var offset = 0; offset < ids.length; offset += 150) batches.push(ids.slice(offset, offset + 150));
        batches.forEach(function(batch) {
            batch.forEach(function(id) { requested[id] = true; });
            $.post(apiUrl, {ids: batch, token: utk}, function(response) {
                var data = response;
                if (typeof data === 'string') {
                    try { data = JSON.parse(data); } catch (e) { return; }
                }
                if (!data || !data.items) return;
                currentScale = Math.max(90, Math.min(180, parseInt(data.scale, 10) || 116));
                Object.keys(data.items).forEach(function(id) { frameCache[id] = data.items[id]; });
                applyCache();
            }, 'json').fail(function() {
                batch.forEach(function(id) { delete requested[id]; });
            });
        });
    }
    function injectFrameMenus() {
        if (typeof user_rank === 'undefined') return;
        var rank = parseInt(user_rank, 10) || 0;
        var selfAccess = <?php echo (int)$addons['addons_access']; ?>;
        var staffAccess = <?php echo $staffAccess; ?>;
        if (rank >= selfAccess) {
            $('.avself').each(function() {
                if ($(this).find('.aa-open-own-frames').length === 0) {
                    $(this).append('<div class="avset bmenu avitem aa-open-own-frames"><i class="fa fa-image theme_color"></i> <?php echo addslashes($lang['open_list_frame']); ?></div>');
                }
            });
        }
        if (rank >= staffAccess) {
            $('.avstaff').each(function() {
                if ($(this).find('.aa-open-staff-frames').length === 0) {
                    $(this).append('<div class="avset bmenu avitem aa-open-staff-frames"><i class="fa fa-image theme_color"></i> إدارة إطار العضو</div>');
                }
            });
        }
    }
    function scan() {
        var hosts = getHosts(document);
        var missing = Object.create(null);
        hosts.forEach(function(host) {
            var id = getUserId(host);
            if (!/^\d+$/.test(id)) return;
            if (Object.prototype.hasOwnProperty.call(frameCache, id)) setFrame(host, frameCache[id]);
            else if (!requested[id]) missing[id] = true;
        });
        requestMissing(Object.keys(missing));
    }
    function scheduleScan() {
        if (scanTimer) clearTimeout(scanTimer);
        scanTimer = setTimeout(function() { scanTimer = null; injectFrameMenus(); scan(); }, 90);
    }
    window.AAFrameRefresh = function() {
        frameCache = Object.create(null);
        requested = Object.create(null);
        scheduleScan();
    };

    $(document).ready(function() {
        if (typeof boomAddCss === 'function') boomAddCss('addons/AA_photo_frame/files/frame.css?v=5');
        <?php if(boomAllow($addons['addons_access'])){ ?>
        window.openAAFrameStore = function(){
            $.post('addons/AA_photo_frame/system/open_frames.php', {token:utk}, function(response){
                if(response && response !== '0'){ showAddonFullPage(response, 420, 'large'); }
                else { callSaved(system.error, 3); }
            }).fail(function(){ callError(system.error); });
        };
        if(typeof addonStoreMenu === 'function'){
            addonStoreMenu('AA_photo_frame', 40, 'addons/AA_photo_frame/files/icon.png', <?php echo json_encode($aaFrameStoreTitle, JSON_UNESCAPED_UNICODE); ?>, 'openAAFrameStore()');
        }
        <?php } ?>
        injectFrameMenus();
        $(document).on('click', '.aa-open-own-frames', function() {
            $.post('addons/AA_photo_frame/system/open_frames.php', {token: utk}, function(response) {
                if (response && response !== '0') showEmptyModal(response, 340);
                else callSaved(system.error, 3);
            });
        });
        $(document).on('click', '.aa-open-staff-frames', function() {
            var target = $(this).attr('data');
            $.post('addons/AA_photo_frame/system/open_frames_staff.php', {target: target, token: utk}, function(response) {
                if (response && response !== '0') showEmptyModal(response, 340);
                else callSaved(system.error, 3);
            });
        });
        scan();
        if (document.body && window.MutationObserver) {
            var observer = new MutationObserver(function(mutations) {
                var relevant = mutations.some(function(mutation) {
                    if (mutation.type === 'attributes') {
                        if (mutation.attributeName === 'data-rank') {
                            var changedId = getUserId(mutation.target);
                            if (changedId) { delete frameCache[changedId]; delete requested[changedId]; }
                        }
                        return true;
                    }
                    return Array.prototype.some.call(mutation.addedNodes || [], function(node) {
                        return node.nodeType === 1 && !node.classList.contains('aa-photo-frame-overlay');
                    });
                });
                if (relevant) scheduleScan();
            });
            observer.observe(document.body, {childList: true, subtree: true, attributes: true, attributeFilter: ['data-id', 'data-user', 'data', 'data-rank']});
        }
    });
})();
</script>
