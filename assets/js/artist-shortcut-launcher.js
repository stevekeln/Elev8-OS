(function () {
    'use strict';

    function init() {
        var dialog = document.querySelector('[data-elev8-shortcut-dialog]');
        var bar = document.querySelector('[data-elev8-shortcut-bar]');
        var sentinel = document.querySelector('.elev8-shortcut-sentinel');
        var openers = document.querySelectorAll('[data-elev8-shortcut-open]');
        var closers = document.querySelectorAll('[data-elev8-shortcut-close]');
        var lastFocused = null;

        if (!dialog || !bar || !sentinel) {
            return;
        }

        function openDialog() {
            lastFocused = document.activeElement;
            dialog.hidden = false;
            document.body.classList.add('elev8-shortcuts-open');
            var closeButton = dialog.querySelector('.elev8-shortcut-close');
            if (closeButton) {
                closeButton.focus();
            }
        }

        function closeDialog() {
            dialog.hidden = true;
            document.body.classList.remove('elev8-shortcuts-open');
            if (lastFocused && typeof lastFocused.focus === 'function') {
                lastFocused.focus();
            }
        }

        openers.forEach(function (button) {
            button.addEventListener('click', openDialog);
        });
        closers.forEach(function (button) {
            button.addEventListener('click', closeDialog);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !dialog.hidden) {
                closeDialog();
            }
        });

        var observer = new IntersectionObserver(function (entries) {
            var show = !entries[0].isIntersecting;
            bar.classList.toggle('is-visible', show);
            bar.setAttribute('aria-hidden', show ? 'false' : 'true');
            document.body.classList.toggle('elev8-shortcut-bar-visible', show);
        }, { threshold: 0 });
        observer.observe(sentinel);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
