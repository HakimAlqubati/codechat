(function () {
    'use strict';
    var cfg = window.VoiceMicConfig;
    var root = document.getElementById('voice_mic_root');
    if (!cfg || !root || root.dataset.initialized === '1') return;
    root.dataset.initialized = '1';

    var $ = function (id) { return document.getElementById(id); };
    var roomId = Number(cfg.roomId || 0);
    var userId = Number(cfg.userId || 0);
    var liveRoom = null;
    var connected = false;
    var myRole = '';
    var myMuted = true;
    var adminMuted = false;
    var busy = false;
    var collapsed = false;
    var roomChangeBusy = false;
    var roster = { speakers: [], listeners: [], total: 0, pending_requests: [] };
    var localMutedUsers = new Set();
    var audioByIdentity = new Map();
    var heartbeatTimer = null;
    var pollTimer = null;
    var roomTimer = null;
    var toastTimer = null;
    var firstUnavailable = true;
    var lastStateRoom = roomId;
    var paymentPolicy = { speaker_mode: 0, speaker_per_minute: 0, listener_mode: 0, listener_per_minute: 0, require_speaker_approval: 0, wallet_enabled: 0 };

    function html(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }
    function toast(message, error) {
        var el = $('vmic_toast');
        if (!el) return;
        el.textContent = message || '';
        el.classList.toggle('error', !!error);
        el.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { el.classList.remove('show'); }, 3000);
    }
    function api(action, extra, useRoom) {
        return new Promise(function (resolve, reject) {
            if (!window.jQuery) return reject(new Error('مكونات الشات غير جاهزة'));
            $.ajax({
                url: cfg.endpoint,
                type: 'POST',
                dataType: 'json',
                cache: false,
                data: Object.assign({ action: action, room_id: useRoom == null ? roomId : useRoom, token: cfg.token, cp: 'chat' }, extra || {}),
                success: resolve,
                error: function () { reject(new Error('تعذر الاتصال بالخادم')); }
            });
        });
    }
    function setDockState() {
        $('vmic_entry_actions').hidden = connected;
        $('vmic_speaker_actions').hidden = !connected || myRole !== 'speaker';
        $('vmic_listener_actions').hidden = !connected || myRole !== 'listener';
        $('vmic_join_speaker').disabled = busy || !cfg.liveReady;
        $('vmic_join_listener').disabled = busy || !cfg.liveReady;
        $('vmic_self_mute').disabled = busy || adminMuted;
        $('vmic_self_mute').classList.toggle('vmic-muted', myMuted || adminMuted);
        $('vmic_self_mute').innerHTML = (myMuted || adminMuted) ? '<i class="fa fa-microphone-slash"></i>' : '<i class="fa fa-microphone"></i>';
        $('vmic_self_mute').title = adminMuted ? 'الميكروفون مكتوم من الإدارة' : (myMuted ? 'فتح الميكروفون' : 'كتم الميكروفون');
        $('vmic_promote').disabled = busy;
        var speakerFee = Number(paymentPolicy.speaker_mode) === 1 ? Number(paymentPolicy.speaker_per_minute || 0) + ' ذهب/دقيقة' : 'مجاني';
        var listenerFee = Number(paymentPolicy.listener_mode) === 1 ? Number(paymentPolicy.listener_per_minute || 0) + ' ذهب/دقيقة' : 'مجاني';
        $('vmic_join_speaker').title = 'دخول الاتصال الجماعي - ' + speakerFee;
        $('vmic_join_listener').title = 'دخول الاستماع - ' + listenerFee;
    }
    function applySettings(data) {
        var style = root.style;
        style.setProperty('--vmic-height', Math.max(58, Math.min(100, Number(data.bar_height || 74))) + 'px');
        style.setProperty('--vmic-avatar', Math.max(24, Math.min(44, Number(data.avatar_size || 32))) + 'px');
        style.setProperty('--vmic-speaker', data.speaker_color || '#28b66f');
        style.setProperty('--vmic-listener', data.listener_color || '#368ee8');
        style.setProperty('--vmic-divider', data.divider_color || '#8b929c');
        root.classList.toggle('vmic-hide-names', Number(data.show_names) === 0);
        root.classList.toggle('vmic-hide-ranks', Number(data.show_ranks) === 0);
    }
    function memberButton(member, role) {
        var muted = !!member.muted || !!member.admin_muted;
        var self = Number(member.user_id) === userId;
        var icon = role === 'speaker' ? (muted ? 'fa-microphone-slash vmic-icon-muted' : 'fa-microphone') : 'fa-headphones';
        var title = role === 'speaker' ? (muted ? 'متصل - مكتوم' : 'متصل ويتحدث') : 'مستمع';
        return '<button type="button" class="vmic-member' + (self ? ' vmic-self' : '') + '" data-vmic-user="' + Number(member.user_id) + '" data-vmic-role="' + role + '" title="' + html(title) + '">' +
            '<span class="vmic-member-avatar-wrap"><img class="vmic-member-avatar" src="' + html(member.avatar) + '" alt="" loading="lazy"><span class="vmic-member-icon ' + (muted ? 'vmic-icon-muted' : '') + '"><i class="fa ' + icon + '"></i></span></span>' +
            '<span class="vmic-member-name">' + html(member.user_name) + '</span>' +
            '<span class="vmic-member-rank">' + html(member.rank || 'عضو') + '</span></button>';
    }
    function renderGroup(targetId, members, role) {
        var node = $(targetId);
        node.innerHTML = (members || []).map(function (member) { return memberButton(member, role); }).join('');
        node.hidden = !(members && members.length);
    }
    function renderRequests(requests, canManage) {
        var button = $('vmic_requests_button');
        var list = $('vmic_requests_list');
        var count = (requests || []).length;
        button.hidden = !canManage || !count;
        $('vmic_requests_count').textContent = String(count);
        list.innerHTML = (requests || []).map(function (r) {
            return '<div class="vmic-request-item"><img src="' + html(r.avatar) + '" alt=""><span style="flex:1;min-width:0">' + html(r.user_name) + '<br><small>' + html(r.rank) + '</small></span><button data-vmic-request="approve" data-user="' + Number(r.user_id) + '">قبول</button><button class="vmic-reject" data-vmic-request="reject" data-user="' + Number(r.user_id) + '">رفض</button></div>';
        }).join('') || '<div class="vmic-request-item">لا توجد طلبات حالياً</div>';
    }
    function renderRoster(data) {
        roster = data || roster;
        var speakers = roster.speakers || [];
        var listeners = roster.listeners || [];
        renderGroup('vmic_speakers', speakers, 'speaker');
        renderGroup('vmic_listeners', listeners, 'listener');
        $('vmic_roster_divider').hidden = !(speakers.length && listeners.length);
        $('vmic_roster_empty').classList.toggle('vmic-empty-visible', !speakers.length && !listeners.length);
        $('vmic_mini_count').textContent = String(roster.total || 0);
        renderRequests(roster.pending_requests || [], !!roster.can_manage);
        if (roster.self && roster.self.user_id) {
            if (roster.self.mic_role && roster.self.mic_role !== myRole) {
                myRole = roster.self.mic_role;
                myMuted = !!roster.self.mic_muted;
                adminMuted = !!roster.self.admin_muted;
                setDockState();
            } else {
                myMuted = !!roster.self.mic_muted;
                adminMuted = !!roster.self.admin_muted;
                setDockState();
            }
        }
        if (roster.self && !roster.self.user_id && connected) {
            disconnectLocal(false).then(function () { toast('انتهت عضويتك في المايك', true); });
        }
    }
    function showUnavailable(message) {
        if (connected) disconnectLocal(false);
        $('vmic_dock').hidden = true;
        $('vmic_offline_message').hidden = false;
        $('vmic_offline_message').textContent = message || 'المايك غير متاح في هذه الغرفة';
        if (firstUnavailable && message) toast(message, true);
        firstUnavailable = false;
    }
    async function refreshState() {
        try {
            var response = await api('state');
            if (!response || response.code !== 1) return;
            if (!response.data.available) {
                if (response.data.visible === false) { root.hidden = true; return; }
                root.hidden = false;
                showUnavailable(response.data.reason || 'المايك غير متاح');
                return;
            }
            root.hidden = false;
            firstUnavailable = true;
            $('vmic_offline_message').hidden = true;
            $('vmic_dock').hidden = collapsed;
            paymentPolicy = { speaker_mode: Number(response.data.speaker_gold_mode || 0), speaker_per_minute: Number(response.data.speaker_gold_per_minute || 0), listener_mode: Number(response.data.listener_gold_mode || 0), listener_per_minute: Number(response.data.listener_gold_per_minute || 0), require_speaker_approval: Number(response.data.require_speaker_approval || 0), wallet_enabled: Number(response.data.wallet_enabled || 0) };
            applySettings(response.data);
            renderRoster(response.data);
        } catch (e) { /* retry on the next poll */ }
    }
    function attachRemoteAudio(track, publication, participant) {
        if (!track || track.kind !== 'audio' || !participant) return;
        var identity = String(participant.identity);
        if (identity === String(userId)) return;
        var audio = track.attach();
        audio.autoplay = true;
        audio.playsInline = true;
        audio.controls = false;
        audio.muted = localMutedUsers.has(identity);
        $('vmic_audio_bin').appendChild(audio);
        if (!audioByIdentity.has(identity)) audioByIdentity.set(identity, new Set());
        audioByIdentity.get(identity).add(audio);
        var play = audio.play();
        if (play && play.catch) play.catch(function () { toast('اضغط مرة داخل الشات للسماح بتشغيل الصوت'); });
    }
    function detachRemoteAudio(track, publication, participant) {
        if (track && track.detach) {
            try { track.detach().forEach(function (el) { el.remove(); }); } catch (e) {}
        }
        if (participant && participant.identity) audioByIdentity.delete(String(participant.identity));
    }
    function markActiveSpeakers(active) {
        var ids = new Set((active || []).map(function (p) { return String(p.identity); }));
        root.querySelectorAll('.vmic-member').forEach(function (el) {
            el.classList.toggle('vmic-speaking', ids.has(el.getAttribute('data-vmic-user')));
        });
    }
    async function confirmEntryWithRetry() {
        var lastError = null;
        for (var attempt = 0; attempt < 3; attempt++) {
            try {
                var response = await api('confirm_join');
                if (!response || response.code !== 1) {
                    throw new Error(response ? response.message : 'تعذر تأكيد دخول المايك');
                }
                return response;
            } catch (error) {
                lastError = error;
                // Confirmation is idempotent on the server: retrying after a lost
                // response never charges the same active session twice.
                if (attempt < 2) await new Promise(function (resolve) { setTimeout(resolve, 350 * (attempt + 1)); });
            }
        }
        throw lastError || new Error('تعذر تأكيد دخول المايك');
    }

    async function joinMode(mode) {
        if (connected || busy) return;
        if (!cfg.liveReady || !window.LivekitClient) { toast('بيانات LiveKit غير مضبوطة في إعدادات الشات', true); return; }
        var billingMode = mode;
        var waitingApproval = mode === 'speaker' && Number(paymentPolicy.require_speaker_approval) === 1 && !(roster && roster.can_manage);
        if (waitingApproval) billingMode = 'listener';
        var entryCost = billingMode === 'speaker' ? Number(paymentPolicy.speaker_per_minute || 0) : Number(paymentPolicy.listener_per_minute || 0);
        var entryPaid = billingMode === 'speaker' ? Number(paymentPolicy.speaker_mode) === 1 : Number(paymentPolicy.listener_mode) === 1;
        var confirmText = '';
        if (entryPaid) {
            if (!Number.isFinite(entryCost) || entryCost < 1) { toast('سعر الدقيقة لهذا الوضع غير مضبوط؛ أبلغ الإدارة', true); return; }
            confirmText = 'دخول ' + (billingMode === 'speaker' ? 'الاتصال الجماعي' : 'الاستماع') + ' يتطلب ' + entryCost + ' ذهب لكل دقيقة.\nستُخصم الدقيقة الأولى عند تأكيد الدخول، ثم تُخصم رسوم كل دقيقة جديدة طالما بقيت في هذا الوضع.';
        } else {
            confirmText = 'دخول ' + (billingMode === 'speaker' ? 'الاتصال الجماعي' : 'الاستماع') + ' مجاني.';
        }
        if (waitingApproval) {
            var speakerCost = Number(paymentPolicy.speaker_per_minute || 0);
            var speakerFeeText = Number(paymentPolicy.speaker_mode) === 1 ? speakerCost + ' ذهب/دقيقة' : 'مجاني';
            confirmText += '\n\nطلبك سيبدأ بوضع المستمع حتى موافقة المشرف. أثناء الانتظار تطبق رسوم المستمع، وبعد الموافقة تطبق رسوم الاتصال الجماعي (' + speakerFeeText + ').';
        }
        confirmText += '\n\nعند عدم كفاية رصيدك للدقيقة التالية سيتم إخراجك تلقائياً من المايك. هل توافق؟';
        if ((entryPaid || waitingApproval && Number(paymentPolicy.speaker_mode) === 1) && !window.confirm(confirmText)) return;
        busy = true; setDockState();
        try {
            // Request microphone access from the user's click before waiting on the server.
            if (mode === 'speaker') {
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) throw new Error('المتصفح لا يدعم الوصول إلى الميكروفون');
                var permissionStream = await navigator.mediaDevices.getUserMedia({ audio: true });
                permissionStream.getTracks().forEach(function (track) { track.stop(); });
            }
            var response = await api('join', { mode: mode });
            if (!response || response.code !== 1 || !response.data) throw new Error(response ? response.message : 'تعذر دخول المايك');
            liveRoom = new LivekitClient.Room({ adaptiveStream: true, dynacast: true, autoSubscribe: true });
            liveRoom.on(LivekitClient.RoomEvent.TrackSubscribed, attachRemoteAudio);
            liveRoom.on(LivekitClient.RoomEvent.TrackUnsubscribed, detachRemoteAudio);
            liveRoom.on(LivekitClient.RoomEvent.ActiveSpeakersChanged, markActiveSpeakers);
            liveRoom.on(LivekitClient.RoomEvent.ParticipantDisconnected, function (participant) { audioByIdentity.delete(String(participant.identity)); refreshState(); });
            liveRoom.on(LivekitClient.RoomEvent.Disconnected, function () {
                connected = false; liveRoom = null; myRole = ''; myMuted = true; adminMuted = false;
                clearInterval(heartbeatTimer); heartbeatTimer = null;
                $('vmic_audio_bin').innerHTML = ''; audioByIdentity.clear(); setDockState();
                api('leave').catch(function () {});
                refreshState();
            });
            await liveRoom.connect(response.data.url, response.data.token);
            // The token has no subscribe/publish rights until this server-side
            // confirmation succeeds. The first minute is charged atomically here; subsequent minutes are renewed by server heartbeats.
            var entryConfirmation = await confirmEntryWithRetry();
            connected = true; myRole = response.data.role; myMuted = true; adminMuted = false;
            liveRoom.remoteParticipants.forEach(function (participant) {
                participant.trackPublications.forEach(function (publication) {
                    if (publication.track && publication.track.kind === 'audio') attachRemoteAudio(publication.track, publication, participant);
                });
            });
            if (myRole === 'speaker' && !response.data.pending) {
                try {
                    await liveRoom.localParticipant.setMicrophoneEnabled(true);
                    var opened = await api('mute_self', { muted: 0 });
                    if(!opened || opened.code !== 1){ await liveRoom.localParticipant.setMicrophoneEnabled(false); throw new Error(opened ? opened.message : 'تعذر اعتماد فتح الميكروفون'); }
                    myMuted = false;
                } catch (micError) {
                    await disconnectLocal(true);
                    throw new Error(micError && micError.message ? micError.message : 'لم يتم منح المتصفح إذن الميكروفون. اسمح باستخدام الميكروفون ثم أعد المحاولة.');
                }
            }
            clearInterval(heartbeatTimer);
            heartbeatTimer = setInterval(sendHeartbeat, 10000);
            collapsed = false; $('vmic_mini_launcher').hidden = true; $('vmic_dock').hidden = false;
            setDockState(); await refreshState();
            if (entryConfirmation.data && Number(entryConfirmation.data.gold_charged || 0) > 0) { toast('تم دخول المايك وخصم ' + Number(entryConfirmation.data.gold_charged) + ' ذهب مقابل الدقيقة الأولى'); } else { toast(response.data.pending ? 'دخلت مستمعاً وأُرسل طلبك للتحدث' : (myRole === 'speaker' ? 'دخلت الاتصال الجماعي' : 'دخلت وضع الاستماع')); }
        } catch (error) {
            try { if (liveRoom) await liveRoom.disconnect(); } catch (e) {}
            try { await api('leave'); } catch (e) {}
            liveRoom = null; connected = false; myRole = ''; myMuted = true; setDockState();
            toast(error.message || 'تعذر دخول المايك', true);
        } finally { busy = false; setDockState(); }
    }
    async function sendHeartbeat() {
        if (!connected) return;
        try {
            var response = await api('heartbeat');
            if (!response || response.code !== 1) {
                await disconnectLocal(false);
                toast(response ? response.message : 'انتهت جلسة المايك', true);
                return;
            }
            if (response.data && Number(response.data.charged_gold || 0) > 0) {
                toast('تم خصم ' + Number(response.data.charged_gold) + ' ذهب مقابل ' + Number(response.data.charged_minutes || 1) + ' دقيقة إضافية من المايك');
            }
        } catch (e) { /* server-side stale cleanup prevents ghost participants */ }
    }
    async function disconnectLocal(notify) {
        clearInterval(heartbeatTimer); heartbeatTimer = null;
        if (notify) { try { await api('leave'); } catch (e) {} }
        try {
            if (liveRoom && liveRoom.localParticipant) await liveRoom.localParticipant.setMicrophoneEnabled(false);
            if (liveRoom) await liveRoom.disconnect();
        } catch (e) {}
        liveRoom = null; connected = false; myRole = ''; myMuted = true; adminMuted = false;
        audioByIdentity.clear(); $('vmic_audio_bin').innerHTML = '';
        setDockState(); await refreshState();
    }
    async function leaveMode() {
        if (!connected || busy) return;
        busy = true; setDockState();
        await disconnectLocal(true);
        busy = false; setDockState(); toast('غادرت المايك');
    }
    async function toggleSelfMute() {
        if (!connected || myRole !== 'speaker' || busy || adminMuted) return;
        busy = true; setDockState();
        var next = !myMuted;
        try {
            await liveRoom.localParticipant.setMicrophoneEnabled(!next);
            var response = await api('mute_self', { muted: next ? 1 : 0 });
            if (!response || response.code !== 1) {
                await liveRoom.localParticipant.setMicrophoneEnabled(!myMuted);
                throw new Error(response ? response.message : 'تعذر تحديث الميكروفون');
            }
            myMuted = next; toast(next ? 'تم كتم الميكروفون' : 'تم فتح الميكروفون'); await refreshState();
        } catch (e) { toast(e.message || 'تعذر تغيير الميكروفون', true); }
        finally { busy = false; setDockState(); }
    }
    async function changeRole(role) {
        if (!connected || busy) return;
        var targetModePaid = role === 'speaker' ? Number(paymentPolicy.speaker_mode) === 1 : Number(paymentPolicy.listener_mode) === 1;
        var targetRate = role === 'speaker' ? Number(paymentPolicy.speaker_per_minute || 0) : Number(paymentPolicy.listener_per_minute || 0);
        if (targetModePaid) {
            if (targetRate < 1) { toast('سعر الدقيقة للوضع المطلوب غير مضبوط', true); return; }
            var currentRate = myRole === 'speaker' ? Number(paymentPolicy.speaker_per_minute || 0) : Number(paymentPolicy.listener_per_minute || 0);
            var switchText = 'التحويل إلى ' + (role === 'speaker' ? 'الاتصال الجماعي' : 'الاستماع') + ' يكلف ' + targetRate + ' ذهب لكل دقيقة.';
            if (currentRate === 0) switchText += '\nستُخصم قيمة الدقيقة الأولى فور التحويل.';
            else switchText += '\nيستمر عداد جلستك الحالي، ويُطبّق السعر الجديد عند دورة الفوترة التالية.';
            switchText += '\nهل توافق على الرسوم؟';
            if (!window.confirm(switchText)) return;
        }
        busy = true; setDockState();
        try {
            if (role === 'speaker') {
                // Request hardware permission only after the user explicitly chooses the microphone.
                var stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                stream.getTracks().forEach(function (t) { t.stop(); });
            } else if (liveRoom && liveRoom.localParticipant) {
                await liveRoom.localParticipant.setMicrophoneEnabled(false);
            }
            var response = await api('change_role', { role: role });
            if (!response || response.code !== 1) throw new Error(response ? response.message : 'تعذر تغيير الوضع');
            if (response.data && response.data.pending) {
                myRole = 'listener'; myMuted = true; closeProfile(); toast(response.message || 'تم إرسال طلب التحدث');
            } else if (role === 'speaker') {
                myRole = 'speaker'; myMuted = true;
                await liveRoom.localParticipant.setMicrophoneEnabled(true);
                var micState = await api('mute_self', { muted: 0 });
                if (!micState || micState.code !== 1) { await liveRoom.localParticipant.setMicrophoneEnabled(false); throw new Error(micState ? micState.message : 'تعذر فتح الميكروفون'); }
                myMuted = false; closeProfile(); toast('تم تحويلك إلى متصل');
            } else {
                myRole = 'listener'; myMuted = true; closeProfile(); toast('أصبحت مستمعاً');
            }
            if (response.data && Number(response.data.charged_gold || 0) > 0) { toast('تم التحويل وخصم ' + Number(response.data.charged_gold) + ' ذهب للدقيقة الأولى'); }
            await refreshState();
        } catch (e) { toast(e.message || 'تعذر تغيير الوضع', true); }
        finally { busy = false; setDockState(); }
    }
    function findMember(id) {
        return (roster.speakers || []).concat(roster.listeners || []).find(function (m) { return Number(m.user_id) === Number(id); });
    }
    function openProfile(member) {
        if (!member) return;
        var self = Number(member.user_id) === userId;
        var canManage = !!roster.can_manage && !self;
        var canBan = !!roster.can_ban && !self;
        var status = member.admin_muted ? 'مقيّد من التحدث إدارياً' : (member.role === 'speaker' ? (member.muted ? 'متصل - الميكروفون مكتوم' : 'متصل') : 'مستمع');
        $('vmic_profile_info').innerHTML = '<div class="vmic-profile-top"><img src="' + html(member.avatar) + '" alt=""><div><div class="vmic-profile-name">' + html(member.user_name) + '</div><div class="vmic-profile-rank">' + html(member.rank || 'عضو') + '</div><div class="vmic-profile-status">' + html(status) + '</div></div></div>';
        var actions = '';
        if (self && connected) {
            if (myRole === 'speaker') {
                actions += '<button class="vmic-profile-action" data-vmic-profile-action="mute-self">' + (myMuted ? 'فتح الميكروفون' : 'كتم الميكروفون') + '</button>';
                actions += '<button class="vmic-profile-action" data-vmic-profile-action="to-listener">التحويل إلى مستمع</button>';
            } else {
                actions += '<button class="vmic-profile-action vmic-good" data-vmic-profile-action="to-speaker">التحويل إلى الاتصال الجماعي</button>';
            }
            actions += '<button class="vmic-profile-action vmic-danger" data-vmic-profile-action="leave-self">مغادرة المايك</button>';
        } else if (canManage) {
            actions += '<button class="vmic-profile-action" data-vmic-admin="' + (member.admin_muted ? 'unmute' : 'mute') + '" data-user="' + Number(member.user_id) + '">' + (member.admin_muted ? 'إلغاء كتم العضو للجميع' : 'كتم صوت العضو عند الجميع') + '</button>';
            if (member.role === 'speaker') actions += '<button class="vmic-profile-action" data-vmic-admin="listener" data-user="' + Number(member.user_id) + '">تحويل العضو إلى مستمع</button>';
            actions += '<button class="vmic-profile-action vmic-danger" data-vmic-admin="kick" data-user="' + Number(member.user_id) + '">تنزيل العضو من المكالمة</button>';
        }
        if (canBan) {
            actions += '<select class="vmic-profile-select" id="vmic_ban_duration"><option value="10">تقييد 10 دقائق</option><option value="60" selected>تقييد ساعة</option><option value="1440">تقييد 24 ساعة</option><option value="10080">تقييد أسبوع</option><option value="0">تقييد دائم</option></select>';
            actions += '<input class="vmic-profile-select" id="vmic_ban_reason" maxlength="500" placeholder="سبب التقييد (اختياري)">';
            actions += '<button class="vmic-profile-action vmic-danger" data-vmic-admin="ban" data-user="' + Number(member.user_id) + '">تقييد العضو من المايك</button>';
        }
        $('vmic_profile_actions').innerHTML = actions;
        $('vmic_profile').hidden = false;
    }
    function closeProfile() { $('vmic_profile').hidden = true; }
    async function adminAction(action, targetId) {
        if (busy) return;
        var payload = { admin_action: action, target_id: targetId };
        if (action === 'ban') {
            payload.duration = $('vmic_ban_duration') ? $('vmic_ban_duration').value : 60;
            payload.reason = $('vmic_ban_reason') ? $('vmic_ban_reason').value : '';
        }
        busy = true;
        try {
            var response = await api('admin', payload);
            if (!response || response.code !== 1) throw new Error(response ? response.message : 'تعذر تنفيذ الأمر');
            closeProfile(); toast(response.message || 'تم تنفيذ الأمر'); await refreshState();
        } catch (e) { toast(e.message || 'تعذر تنفيذ الأمر', true); }
        finally { busy = false; setDockState(); }
    }
    async function handleClick(event) {
        var memberEl = event.target.closest('[data-vmic-user]');
        if (memberEl) { openProfile(findMember(memberEl.getAttribute('data-vmic-user'))); return; }
        var profAction = event.target.closest('[data-vmic-profile-action]');
        if (profAction) {
            var a = profAction.getAttribute('data-vmic-profile-action');
            if (a === 'mute-self') { closeProfile(); await toggleSelfMute(); }
            else if (a === 'to-speaker') { await changeRole('speaker'); }
            else if (a === 'to-listener') { await changeRole('listener'); }
            else if (a === 'leave-self') { closeProfile(); await leaveMode(); }
            return;
        }
        var adminEl = event.target.closest('[data-vmic-admin]');
        if (adminEl) { await adminAction(adminEl.getAttribute('data-vmic-admin'), adminEl.getAttribute('data-user')); return; }
        var requestEl = event.target.closest('[data-vmic-request]');
        if (requestEl) { await adminAction(requestEl.getAttribute('data-vmic-request'), requestEl.getAttribute('data-user')); return; }
    }
    async function syncRoom() {
        var current = Number(window.user_room || roomId);
        if (!current || current === roomId || roomChangeBusy) return;
        roomChangeBusy = true;
        var previous = roomId;
        if (connected || liveRoom) {
            clearInterval(heartbeatTimer); heartbeatTimer = null;
            try { await api('leave', {}, previous); } catch (e) {}
            try { if (liveRoom) await liveRoom.disconnect(); } catch (e) {}
        }
        roomId = current; lastStateRoom = current;
        liveRoom = null; connected = false; myRole = ''; myMuted = true; adminMuted = false;
        roster = { speakers: [], listeners: [], total: 0, pending_requests: [] };
        $('vmic_speakers').innerHTML = ''; $('vmic_listeners').innerHTML = '';
        $('vmic_profile').hidden = true; $('vmic_requests_panel').hidden = true;
        $('vmic_audio_bin').innerHTML = ''; audioByIdentity.clear();
        setDockState(); roomChangeBusy = false; await refreshState();
    }

    $('vmic_join_speaker').addEventListener('click', function () { joinMode('speaker'); });
    $('vmic_join_listener').addEventListener('click', function () { joinMode('listener'); });
    $('vmic_self_mute').addEventListener('click', toggleSelfMute);
    $('vmic_leave_speaker').addEventListener('click', leaveMode);
    $('vmic_promote').addEventListener('click', function () { changeRole('speaker'); });
    $('vmic_leave_listener').addEventListener('click', leaveMode);
    $('vmic_collapse').addEventListener('click', function () { collapsed = true; $('vmic_dock').hidden = true; $('vmic_mini_launcher').hidden = false; });
    $('vmic_expand').addEventListener('click', function () { collapsed = false; $('vmic_mini_launcher').hidden = true; $('vmic_dock').hidden = false; });
    $('vmic_profile_close').addEventListener('click', closeProfile);
    $('vmic_requests_button').addEventListener('click', function () { $('vmic_requests_panel').hidden = !$('vmic_requests_panel').hidden; });
    $('vmic_requests_close').addEventListener('click', function () { $('vmic_requests_panel').hidden = true; });
    root.addEventListener('click', handleClick);

    setDockState();
    refreshState();
    pollTimer = setInterval(refreshState, 4000);
    heartbeatTimer = null;
    roomTimer = setInterval(syncRoom, 1000);
    window.addEventListener('beforeunload', function () {
        clearInterval(heartbeatTimer); clearInterval(pollTimer); clearInterval(roomTimer);
    });
})();
