(function (window, document) {
    'use strict';

    var config = window.disableF12ClipboardConfig || {};
    if (!config.enabled || Number(config.userRank) >= Number(config.minimumRank)) {
        return;
    }
    if (window.__disableF12ClipboardBound) {
        return;
    }
    window.__disableF12ClipboardBound = true;

    var message = typeof config.message === 'string' && config.message.trim() !== ''
        ? config.message
        : 'هذه العملية غير مسموحة لرتبتك.';
    var lastNotice = 0;

    function showDenied() {
        var now = Date.now();
        // Avoid flooding the notification area when a key is held down.
        if (now - lastNotice < 900) {
            return;
        }
        lastNotice = now;
        if (typeof window.callError === 'function') {
            window.callError(message);
        } else if (typeof window.callSaved === 'function') {
            window.callSaved(message, 2);
        }
    }

    function stopEvent(event) {
        if (event.cancelable) {
            event.preventDefault();
        }
        event.stopPropagation();
        if (typeof event.stopImmediatePropagation === 'function') {
            event.stopImmediatePropagation();
        }
    }

    function isFormControl(node) {
        if (!node || node.nodeType !== 1) {
            return false;
        }
        return !!node.closest('input, textarea, select, option, button, [contenteditable="true"], [contenteditable=""], [role="textbox"]');
    }

    // Apply selection restrictions to current and future AJAX-rendered content.
    var style = document.createElement('style');
    style.id = 'disable_f12_clipboard_style';
    style.textContent = [
        'html.disable-f12-clipboard, html.disable-f12-clipboard * {',
        '  -webkit-user-select: none !important;',
        '  user-select: none !important;',
        '  -webkit-touch-callout: none !important;',
        '}',
        'html.disable-f12-clipboard input,',
        'html.disable-f12-clipboard textarea,',
        'html.disable-f12-clipboard select,',
        'html.disable-f12-clipboard [contenteditable="true"],',
        'html.disable-f12-clipboard [contenteditable=""],',
        'html.disable-f12-clipboard [role="textbox"] {',
        '  -webkit-user-select: text !important;',
        '  user-select: text !important;',
        '  -webkit-touch-callout: default !important;',
        '}'
    ].join('\n');
    (document.head || document.documentElement).appendChild(style);
    document.documentElement.classList.add('disable-f12-clipboard');

    // Block copy/cut anywhere in the chat, including usernames, profile data,
    // wall posts, private messages, statuses and dynamically loaded content.
    ['copy', 'cut'].forEach(function (type) {
        document.addEventListener(type, function (event) {
            stopEvent(event);
            showDenied();
        }, true);
    });

    // Prevent pasting text/images/files from another app into chat inputs,
    // editable areas, and any other content within the chat page.
    document.addEventListener('paste', function (event) {
        stopEvent(event);
        showDenied();
    }, true);

    // Some mobile/modern browsers expose paste as beforeinput as well.
    document.addEventListener('beforeinput', function (event) {
        var inputType = event.inputType || '';
        if (inputType === 'insertFromPaste' || inputType === 'insertFromDrop') {
            stopEvent(event);
            showDenied();
        }
    }, true);

    // Block dragging selected text/images/files into the chat page.
    document.addEventListener('drop', function (event) {
        stopEvent(event);
        showDenied();
    }, true);
    document.addEventListener('dragover', function (event) {
        if (event.dataTransfer && event.dataTransfer.types && event.dataTransfer.types.length) {
            stopEvent(event);
        }
    }, true);

    // Stop keyboard shortcuts even when a browser fails to dispatch clipboard
    // events consistently (Ctrl/Cmd+C, Ctrl/Cmd+X, Ctrl/Cmd+V).
    document.addEventListener('keydown', function (event) {
        var key = (event.key || '').toLowerCase();
        var modifier = event.ctrlKey || event.metaKey;
        if (modifier && (key === 'c' || key === 'x' || key === 'v')) {
            stopEvent(event);
            showDenied();
        }
    }, true);

    // Keep ordinary form editing and cursor placement intact. Only native
    // context menus on non-form page content are suppressed to reduce long-press
    // text/image copy on mobile browsers.
    document.addEventListener('contextmenu', function (event) {
        if (!isFormControl(event.target)) {
            stopEvent(event);
            showDenied();
        }
    }, true);

    // Protect content that gets inserted later through CodyChat AJAX handlers.
    // Delegated document listeners above cover it without repeated bindings.
})(window, document);
