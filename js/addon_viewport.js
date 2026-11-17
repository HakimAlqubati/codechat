/* Responsive viewport for the main add-on store pages. Small dialogs are untouched. */
(function ($) {
    'use strict';

    var modalMap = {
        small: {id: '#small_modal', show: 'showModal'},
        large: {id: '#large_modal', show: 'showEmptyModal'},
        over: {id: '#over_modal', show: 'overModal'},
        over_empty: {id: '#over_emodal', show: 'overEmptyModal'},
        top: {id: '#top_modal', show: 'topModal'}
    };

    function clearAddonPageState() {
        $('.addon-full-page-modal').removeClass('addon-full-page-modal');
        $('.addon-full-page-in').removeClass('addon-full-page-in');
        $('.addon-full-page-content').removeClass('addon-full-page-content');
    }

    function wrapModalFunction(name) {
        var original = window[name];
        if (typeof original !== 'function' || original.__addonViewportWrapped) {
            return;
        }
        var wrapped = function () {
            clearAddonPageState();
            return original.apply(this, arguments);
        };
        wrapped.__addonViewportWrapped = true;
        wrapped.__addonViewportOriginal = original;
        window[name] = wrapped;
    }

    function updateViewportBounds() {
        if (!document.body || !document.getElementById('chat_head') || !document.getElementById('wrap_footer')) {
            return;
        }

        var header = document.getElementById('header_full') || document.getElementById('header2') || document.getElementById('chat_head');
        var footer = document.getElementById('wrap_footer');
        var top = 50;
        var bottom = 50;
        var viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;

        if (header) {
            var headerRect = header.getBoundingClientRect();
            if (headerRect.height > 0) {
                top = Math.max(0, Math.min(viewportHeight, headerRect.bottom));
            }
        }
        if (footer) {
            var footerRect = footer.getBoundingClientRect();
            if (footerRect.height > 0) {
                bottom = Math.max(0, Math.min(viewportHeight, viewportHeight - footerRect.top));
            }
        }

        document.body.classList.add('boom-chat-viewport');
        document.body.style.setProperty('--addon-viewport-top', top + 'px');
        document.body.style.setProperty('--addon-viewport-bottom', bottom + 'px');
    }

    window.showAddonFullPage = function (html, width, type) {
        type = type || 'large';
        var config = modalMap[type];
        if (!config || typeof window[config.show] !== 'function') {
            if (window.callError) {
                window.callError('تعذر فتح صفحة الإضافة.');
            }
            return false;
        }

        clearAddonPageState();
        window[config.show](html, width);

        var $modal = $(config.id);
        var $inner = $modal.find('.modal_in').first();
        var $content = $modal.find('.modal_content').first();
        if (!$modal.length || !$inner.length || !$content.length) {
            return false;
        }

        $modal.addClass('addon-full-page-modal');
        $inner.addClass('addon-full-page-in');
        $content.addClass('addon-full-page-content');
        updateViewportBounds();

        if (window.requestAnimationFrame) {
            window.requestAnimationFrame(updateViewportBounds);
        }
        return true;
    };

    $(function () {
        updateViewportBounds();

        ['showModal', 'showEmptyModal', 'overModal', 'overEmptyModal', 'topModal', 'hideModal', 'hideOver', 'hideTop', 'hideAllModal'].forEach(wrapModalFunction);

        window.addEventListener('resize', updateViewportBounds, {passive: true});
        window.addEventListener('orientationchange', function () {
            window.setTimeout(updateViewportBounds, 100);
        }, {passive: true});

        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', updateViewportBounds, {passive: true});
        }

        if (window.ResizeObserver) {
            var observer = new ResizeObserver(updateViewportBounds);
            var header = document.getElementById('header_full') || document.getElementById('header2') || document.getElementById('chat_head');
            var footer = document.getElementById('wrap_footer');
            if (header) observer.observe(header);
            if (footer) observer.observe(footer);
        }
    });
})(jQuery);
