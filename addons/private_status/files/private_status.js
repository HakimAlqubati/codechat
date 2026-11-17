(function ($) {
    'use strict';

    var cfg = window.CodyPrivateStatusConfig || {};
    var endpoint = 'addons/private_status/system/activity.php';
    var lastTarget = 0;
    var lastPulse = 0;
    var typingActive = false;
    var typingTarget = 0;
    var lastTypingInput = 0;
    var lastTypingSend = 0;
    var lastPresenceText = '';
    var pendingRequest = false;

    function isPrivateOpen() {
        return typeof currentPrivate !== 'undefined' && Number(currentPrivate) > 0 &&
            $('#private_center').length && !$('#private_center').hasClass('privhide') &&
            $('#private_center').is(':visible');
    }

    function isPageVisible() {
        return document.visibilityState === 'visible';
    }

    function post(data, callback, complete) {
        $.ajax({
            url: endpoint,
            type: 'POST',
            dataType: 'json',
            cache: false,
            timeout: 8000,
            data: $.extend({token: (typeof utk !== 'undefined' ? utk : '')}, data),
            success: function (response) {
                if (typeof callback === 'function') callback(response || {});
            },
            complete: function () {
                if (typeof complete === 'function') complete();
            }
        });
    }

    function ensureTypingMarkup() {
        var $typing = $('#ptyping');
        if (!$typing.length) return;
        $typing.html(
            '<div class="ps-typing-pill" role="status" aria-live="polite">' +
                '<span class="ps-typing-label">' + (cfg.language === 'en' ? 'Typing' : 'يكتب') + '</span>' +
                '<span class="ps-typing-dots"><i></i><i></i><i></i></span>' +
            '</div>'
        ).hide();
    }

    function ensurePresenceSlot() {
        if (!$('#private_name').length) return;
        if (!$('#private_name .ps-presence-line').length) {
            $('#private_name').append('<span class="ps-presence-line" aria-live="polite"></span>');
        }
    }

    function showPresence(presence) {
        ensurePresenceSlot();
        var $line = $('#private_name .ps-presence-line');
        if (!$line.length) return;
        if (!presence || !presence.visible || !presence.text) {
            $line.text('').hide().removeClass('ps-online ps-last-seen');
            lastPresenceText = '';
            return;
        }
        if (presence.text !== lastPresenceText) {
            $line.text(presence.text);
            lastPresenceText = presence.text;
        }
        $line.removeClass('ps-online ps-last-seen');
        $line.addClass(presence.state === 'online' ? 'ps-online' : 'ps-last-seen').show();
    }

    function showTyping(active) {
        if (!cfg.typing) return;
        if (active && isPrivateOpen()) $('#ptyping').stop(true, true).fadeIn(120);
        else $('#ptyping').stop(true, true).fadeOut(90);
    }

    function collectPrivateIds(selector) {
        var ids = [];
        $(selector).each(function () {
            var id = Number($(this).attr('data-id')) || 0;
            if (id > 0) ids.push(id);
        });
        return ids.slice(-100);
    }

    function renderReceipts(items) {
        if (!cfg.doubleCheck && !cfg.blueRead && !cfg.readLabel) return;
        var statusMap = {};
        (items || []).forEach(function (item) {
            statusMap[String(item.id)] = item;
        });

        $('#show_private .outpriv.privlog').each(function () {
            var $row = $(this);
            var id = String($row.attr('data-id') || '');
            if (!id) return;
            var state = statusMap[id];
            var $date = $row.find('.prdate').first();
            if (!$date.length) return;
            var $receipt = $date.find('.ps-private-receipt');
            if (!state) {
                if ($receipt.length) $receipt.remove();
                return;
            }
            if (!cfg.doubleCheck && !(state.view && (cfg.blueRead || cfg.readLabel))) {
                if ($receipt.length) $receipt.remove();
                return;
            }

            var read = Number(state.view) === 1;
            var delivered = Number(state.status) === 1;
            var showDouble = (delivered && cfg.doubleCheck) || (read && (cfg.blueRead || cfg.readLabel));
            var iconClass = showDouble ? 'fa-check-double' : 'fa-check';
            var stateClass = read && cfg.blueRead ? 'ps-read' : (delivered ? 'ps-delivered' : '');
            var label = read && cfg.readLabel ? (cfg.language === 'en' ? 'Read' : 'مقروءة') : '';
            var html = '<span class="ps-private-receipt ' + stateClass + '" title="' +
                (read ? (cfg.language === 'en' ? 'Read' : 'مقروءة') : (delivered ? (cfg.language === 'en' ? 'Delivered' : 'تم التسليم') : (cfg.language === 'en' ? 'Sent' : 'تم الإرسال'))) + '">' +
                '<i class="fa ' + iconClass + ' ps-tick"></i>' +
                (label ? '<span class="ps-read-label">' + label + '</span>' : '') +
            '</span>';
            if ($receipt.length) $receipt.replaceWith(html);
            else $date.append(html);
        });
    }

    function pulse() {
        if (pendingRequest) return;
        if (typeof currentPrivate === 'undefined') return;
        var target = Number(currentPrivate) || 0;
        var open = isPrivateOpen() && isPageVisible() ? 1 : 0;
        var readIds = (open && target > 0) ? collectPrivateIds('#show_private .inpriv.privlog') : [];
        var sentIds = (target > 0) ? collectPrivateIds('#show_private .outpriv.privlog') : [];
        pendingRequest = true;
        post({
            ps_action: 'pulse',
            target: target,
            read_open: open,
            read_ids: readIds.join(','),
            sent_ids: sentIds.join(',')
        }, function (response) {
            if (!response || Number(response.ok) !== 1) return;
            if (target > 0 && target === Number(currentPrivate)) {
                if (cfg.online || cfg.lastSeen) showPresence(response.presence || {});
                else showPresence({visible: 0});
                showTyping(Number(response.typing) === 1);
                renderReceipts(response.messages || []);
            } else {
                showTyping(false);
                showPresence({visible: 0});
            }
        }, function () {
            pendingRequest = false;
        });
    }

    function sendTyping(forceStop) {
        if (!cfg.typing) return;
        if (forceStop) {
            if (typingActive && typingTarget > 0) {
                post({ps_action: 'typing_stop', target: typingTarget});
            }
            typingActive = false;
            typingTarget = 0;
            return;
        }
        if (typeof currentPrivate === 'undefined' || Number(currentPrivate) < 1) return;
        var target = Number(currentPrivate);
        if (!isPrivateOpen() || !isPageVisible()) return;
        if (typingActive && typingTarget !== target) {
            post({ps_action: 'typing_stop', target: typingTarget});
            typingActive = false;
            typingTarget = 0;
        }
        if (!typingActive || Date.now() - lastTypingSend > 4000) {
            post({ps_action: 'typing', target: target});
            typingActive = true;
            typingTarget = target;
            lastTypingSend = Date.now();
        }
    }

    $(document).on('input', '#message_content', function () {
        var value = String($(this).val() || '').trim();
        if (!value) {
            sendTyping(true);
            return;
        }
        lastTypingInput = Date.now();
        sendTyping(false);
    });

    $(document).on('blur', '#message_content', function () {
        sendTyping(true);
    });


    $(window).on('beforeunload pagehide', function () {
        sendTyping(true);
    });

    $(document).on('visibilitychange', function () {
        if (!isPageVisible()) sendTyping(true);
    });

    $(document).ready(function () {
        ensureTypingMarkup();
        ensurePresenceSlot();
        // Bind on the same wrapper used by CodyChat's native private submit handler.
        $('#private_input').on('submit', function () { sendTyping(true); });

        var header = document.getElementById('private_name');
        if (header && window.MutationObserver) {
            var observer = new MutationObserver(function () {
                ensurePresenceSlot();
                if (Number(currentPrivate || 0) !== lastTarget) {
                    lastTarget = Number(currentPrivate || 0);
                    lastPresenceText = '';
                    showPresence({visible: 0});
                }
            });
            observer.observe(header, {childList: true, subtree: true, characterData: true});
        }

        setInterval(function () {
            var now = Date.now();
            var target = Number(currentPrivate || 0);
            if (target !== lastTarget) {
                sendTyping(true);
                lastTarget = target;
                lastPresenceText = '';
                ensurePresenceSlot();
                showPresence({visible: 0});
                showTyping(false);
            }
            if (typingActive && now - lastTypingInput > 4500) sendTyping(true);
            if (now - lastPulse >= 5500) {
                lastPulse = now;
                pulse();
            }
        }, 500);
    });
})(jQuery);
