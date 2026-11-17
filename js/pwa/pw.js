/* PWA install UI for the website's existing account menu. */
(function () {
    'use strict';
    var deferredInstallPrompt = null;
    function isStandalone() {
        return (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || window.navigator.standalone === true;
    }
    function isIOSDevice() {
        return /iPad|iPhone|iPod/.test(window.navigator.userAgent || '') || (window.navigator.platform === 'MacIntel' && window.navigator.maxTouchPoints > 1);
    }
    function updatePwaMenu() {
        var installItem = document.getElementById('app_install');
        var reloadItem = document.getElementById('app_reload');
        var installed = isStandalone();
        if (installItem) installItem.classList.toggle('fhide', installed);
        if (reloadItem) reloadItem.classList.toggle('fhide', !installed);
    }
    window.updatePwaMenu = updatePwaMenu;
    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        deferredInstallPrompt = event;
        updatePwaMenu();
    });
    window.addEventListener('appinstalled', function () {
        deferredInstallPrompt = null;
        updatePwaMenu();
    });
    document.addEventListener('DOMContentLoaded', updatePwaMenu);
    if (document.readyState !== 'loading') updatePwaMenu();
    document.addEventListener('click', async function (event) {
        var installItem = event.target && event.target.closest ? event.target.closest('#app_install') : null;
        if (!installItem) return;
        event.preventDefault();
        if (isStandalone()) { updatePwaMenu(); return; }
        if (deferredInstallPrompt) {
            var promptEvent = deferredInstallPrompt;
            deferredInstallPrompt = null;
            try { await promptEvent.prompt(); await promptEvent.userChoice; } catch (error) {}
            updatePwaMenu();
            return;
        }
        window.alert(isIOSDevice()
            ? 'لتثبيت الشات: افتح قائمة المشاركة في Safari ثم اختر «إضافة إلى الشاشة الرئيسية».'
            : 'لتثبيت الشات: افتح قائمة المتصفح ثم اختر «تثبيت التطبيق» أو «إضافة إلى الشاشة الرئيسية». يلزم HTTPS.');
    });
})();
