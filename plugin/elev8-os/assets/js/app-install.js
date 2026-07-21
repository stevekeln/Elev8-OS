(function () {
    'use strict';

    var root = document.querySelector('[data-elev8-app-install]');
    if (!root || typeof Elev8OSInstall === 'undefined') { return; }

    var compact = root.querySelector('[data-elev8-install-open]');
    var compactLabel = root.querySelector('[data-elev8-install-label]');
    var panel = root.querySelector('[data-elev8-install-panel]');
    var close = root.querySelector('[data-elev8-install-close]');
    var installButton = root.querySelector('[data-elev8-install-button]');
    var openApp = root.querySelector('[data-elev8-open-app]');
    var message = root.querySelector('[data-elev8-install-message]');
    var steps = root.querySelector('[data-elev8-install-steps]');
    var deferredPrompt = null;
    var mediaStandalone = window.matchMedia('(display-mode: standalone)');
    var isStandalone = mediaStandalone.matches || window.navigator.standalone === true;
    var isIOS = /iphone|ipad|ipod/i.test(window.navigator.userAgent);
    var dismissed = safeGet(Elev8OSInstall.storageKey) === '1';
    var installed = isStandalone || safeGet(Elev8OSInstall.installedKey) === '1';
    var keyboardOpen = false;
    var baseViewportHeight = window.visualViewport ? window.visualViewport.height : window.innerHeight;

    function safeGet(key) {
        try { return window.localStorage.getItem(key); } catch (error) { return null; }
    }

    function safeSet(key, value) {
        try { window.localStorage.setItem(key, value); } catch (error) {}
    }

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

    function setCompactState(state) {
        compact.classList.toggle('is-installed', state === 'installed');
        compact.setAttribute('data-state', state);
        compactLabel.textContent = state === 'installed'
            ? ((Elev8OSInstall.labels && Elev8OSInstall.labels.open) || 'Open App')
            : ((Elev8OSInstall.labels && Elev8OSInstall.labels.install) || 'Install App');
        compact.setAttribute('aria-label', compactLabel.textContent);
    }

    function showPanel() {
        if (isStandalone) { return; }
        panel.hidden = false;
        compact.hidden = true;
        root.classList.add('is-open');
        updatePosition();
    }

    function showCompact() {
        panel.hidden = true;
        compact.hidden = false;
        root.classList.remove('is-open');
        updatePosition();
    }

    function hideHelper() {
        root.hidden = true;
        root.setAttribute('aria-hidden', 'true');
    }

    function configureFallback() {
        installButton.hidden = true;
        openApp.hidden = false;
        if (installed) {
            message.textContent = 'Elev8 OS is already installed. Open your role-based workspace.';
            setSteps([]);
        } else if (isIOS) {
            message.textContent = 'Safari installs Elev8 OS from the Share menu.';
            setSteps(['Tap the Share button in Safari.', 'Choose “Add to Home Screen.”', 'Tap “Add.”']);
        } else {
            message.textContent = 'Use your browser menu to add Elev8 OS to your Home screen.';
            setSteps(['Open the browser menu.', 'Choose “Install app” or “Add to Home screen.”', 'Confirm the installation.']);
        }
    }

    function protectedBottomOffset() {
        var selectors = Elev8OSInstall.protectedSelectors || [];
        var viewportHeight = window.visualViewport ? window.visualViewport.height : window.innerHeight;
        var offset = 0;
        selectors.forEach(function (selector) {
            document.querySelectorAll(selector).forEach(function (element) {
                if (element === root || element.hidden) { return; }
                var style = window.getComputedStyle(element);
                if (style.display === 'none' || style.visibility === 'hidden') { return; }
                var rect = element.getBoundingClientRect();
                if (rect.width <= 0 || rect.height <= 0 || rect.top >= viewportHeight) { return; }
                if (rect.bottom >= viewportHeight - 40 && rect.top < viewportHeight) {
                    offset = Math.max(offset, viewportHeight - rect.top + 12);
                }
            });
        });
        return offset;
    }

    function updatePosition() {
        var offset = protectedBottomOffset();
        root.style.setProperty('--elev8-install-protected-offset', offset + 'px');
        root.classList.toggle('is-keyboard-open', keyboardOpen);
    }

    function updateKeyboardState() {
        if (!window.visualViewport) { return; }
        var current = window.visualViewport.height;
        keyboardOpen = baseViewportHeight - current > 150;
        if (!keyboardOpen && current > baseViewportHeight) { baseViewportHeight = current; }
        updatePosition();
    }

    function openInstalledApp() {
        window.location.assign(Elev8OSInstall.homeUrl || '/');
    }

    registerServiceWorker();

    if (isStandalone) {
        safeSet(Elev8OSInstall.installedKey, '1');
        hideHelper();
        return;
    }

    root.hidden = false;
    root.removeAttribute('aria-hidden');
    setCompactState(installed ? 'installed' : 'install');

    if (installed || dismissed) {
        showCompact();
    } else {
        showPanel();
    }

    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        deferredPrompt = event;
        installed = false;
        setCompactState('install');
        installButton.hidden = false;
        openApp.hidden = false;
        message.textContent = 'Add Elev8 OS to your phone for one-tap access to your Operational Home.';
        setSteps([]);
    });

    window.addEventListener('appinstalled', function () {
        installed = true;
        safeSet(Elev8OSInstall.installedKey, '1');
        setCompactState('installed');
        showCompact();
    });

    compact.addEventListener('click', function () {
        if (installed) {
            openInstalledApp();
            return;
        }
        showPanel();
        if (!deferredPrompt) { configureFallback(); }
    });

    close.addEventListener('click', function () {
        safeSet(Elev8OSInstall.storageKey, '1');
        showCompact();
    });

    installButton.addEventListener('click', function () {
        if (!deferredPrompt) {
            configureFallback();
            return;
        }
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then(function (choice) {
            deferredPrompt = null;
            if (choice && choice.outcome === 'accepted') {
                installed = true;
                safeSet(Elev8OSInstall.installedKey, '1');
                setCompactState('installed');
            }
            showCompact();
        });
    });

    openApp.addEventListener('click', function (event) {
        if (installed) {
            event.preventDefault();
            openInstalledApp();
        }
    });

    window.addEventListener('resize', updatePosition, { passive: true });
    window.addEventListener('orientationchange', function () { window.setTimeout(updatePosition, 150); }, { passive: true });
    document.addEventListener('focusin', function (event) {
        if (/input|textarea|select/i.test(event.target.tagName) || event.target.isContentEditable) {
            window.setTimeout(updateKeyboardState, 80);
        }
    });
    document.addEventListener('focusout', function () {
        keyboardOpen = false;
        window.setTimeout(updatePosition, 120);
    });
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', updateKeyboardState, { passive: true });
        window.visualViewport.addEventListener('scroll', updatePosition, { passive: true });
    }

    updatePosition();
})();
