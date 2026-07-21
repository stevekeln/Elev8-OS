(function () {
    'use strict';

    var root = document.querySelector('[data-elev8-app-install]');
    if (!root || typeof Elev8OSInstall === 'undefined') { return; }

    var compact = root.querySelector('[data-elev8-install-open]');
    var panel = root.querySelector('[data-elev8-install-panel]');
    var close = root.querySelector('[data-elev8-install-close]');
    var installButton = root.querySelector('[data-elev8-install-button]');
    var message = root.querySelector('[data-elev8-install-message]');
    var steps = root.querySelector('[data-elev8-install-steps]');
    var deferredPrompt = null;
    var isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    var isIOS = /iphone|ipad|ipod/i.test(window.navigator.userAgent);
    var dismissed = window.localStorage.getItem(Elev8OSInstall.storageKey) === '1';

    function registerServiceWorker() {
        if (!('serviceWorker' in navigator)) { return; }
        navigator.serviceWorker.register(Elev8OSInstall.serviceWorkerUrl, { scope: Elev8OSInstall.serviceWorkerScope }).catch(function () {});
    }

    function setSteps(items) {
        steps.innerHTML = '';
        items.forEach(function (item) {
            var li = document.createElement('li');
            li.textContent = item;
            steps.appendChild(li);
        });
        steps.hidden = items.length === 0;
    }

    function showPanel() {
        panel.hidden = false;
        compact.hidden = true;
        root.classList.add('is-open');
    }

    function showCompact() {
        panel.hidden = true;
        compact.hidden = false;
        root.classList.remove('is-open');
    }

    function configureFallback() {
        installButton.hidden = true;
        if (isIOS) {
            message.textContent = 'Safari installs Elev8 OS from the Share menu.';
            setSteps(['Tap the Share button in Safari.', 'Choose “Add to Home Screen.”', 'Tap “Add.”']);
        } else {
            message.textContent = 'Use your browser menu to add Elev8 OS to your Home screen.';
            setSteps(['Open the browser menu.', 'Choose “Install app” or “Add to Home screen.”', 'Confirm the installation.']);
        }
    }

    registerServiceWorker();
    root.hidden = false;

    if (isStandalone) {
        window.localStorage.setItem(Elev8OSInstall.installedKey, '1');
        compact.querySelector('span:last-child').textContent = 'App Installed';
        compact.classList.add('is-installed');
        showCompact();
    } else if (dismissed) {
        showCompact();
    } else {
        showPanel();
    }

    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        deferredPrompt = event;
        installButton.hidden = false;
        message.textContent = 'Add Elev8 OS to your phone for one-tap access to your Operational Home.';
        setSteps([]);
    });

    window.addEventListener('appinstalled', function () {
        window.localStorage.setItem(Elev8OSInstall.installedKey, '1');
        compact.querySelector('span:last-child').textContent = 'App Installed';
        compact.classList.add('is-installed');
        showCompact();
    });

    compact.addEventListener('click', function () {
        showPanel();
        if (!deferredPrompt) { configureFallback(); }
    });

    close.addEventListener('click', function () {
        window.localStorage.setItem(Elev8OSInstall.storageKey, '1');
        showCompact();
    });

    installButton.addEventListener('click', function () {
        if (!deferredPrompt) {
            configureFallback();
            return;
        }
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then(function () {
            deferredPrompt = null;
            showCompact();
        });
    });
})();
