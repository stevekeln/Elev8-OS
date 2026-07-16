(function () {
    'use strict';

    var form = document.querySelector('.elev8-manage-website-form');
    var preview = document.querySelector('.elev8-live-profile-preview');
    if (!form || !preview) {
        return;
    }

    function field(name) {
        return form.querySelector('[name="' + name + '"]');
    }

    function value(name, fallback) {
        var element = field(name);
        var current = element ? element.value.trim() : '';
        return current || fallback || '';
    }

    function setText(selector, text) {
        var element = preview.querySelector(selector);
        if (element) {
            element.textContent = text;
        }
    }

    function safeImageUrl(url) {
        return /^https?:\/\//i.test(url) ? url : '';
    }

    function updateImages() {
        var avatar = preview.querySelector('[data-preview-avatar]');
        var avatarUrl = safeImageUrl(value('profile_photo'));
        var artistName = preview.getAttribute('data-artist-name') || 'Artist';
        if (avatar) {
            avatar.innerHTML = avatarUrl
                ? '<img src="' + avatarUrl.replace(/"/g, '&quot;') + '" alt="">'
                : '<span>' + artistName.charAt(0).toUpperCase() + '</span>';
        }

        var cover = preview.querySelector('[data-preview-cover]');
        var coverUrl = safeImageUrl(value('cover_image'));
        if (cover) {
            cover.style.backgroundImage = coverUrl ? 'url("' + coverUrl.replace(/"/g, '\\"') + '")' : '';
            cover.classList.toggle('has-image', Boolean(coverUrl));
        }

    function updateLinks() {
        var links = preview.querySelector('[data-preview-links]');
        if (!links) {
            return;
        }
        links.innerHTML = '';
        for (var i = 1; i <= 4; i += 1) {
            var label = value('social_' + i + '_name');
            var url = value('social_' + i + '_url');
            if (!label || !url) {
                continue;
            }
            var anchor = document.createElement('a');
            anchor.href = '#';
            anchor.textContent = label;
            anchor.addEventListener('click', function (event) { event.preventDefault(); });
            links.appendChild(anchor);
        }
        links.hidden = links.children.length === 0;
    }

    function updateBooking() {
        var button = preview.querySelector('[data-preview-book]');
        if (!button) {
            return;
        }
        button.textContent = value('booking_button_label', 'Book Now with This Artist');
        button.classList.toggle('is-disabled', !value('booking_url'));
    }

    function updatePreview() {
        setText('[data-preview-medium]', value('medium', 'Artist and Instructor'));
        setText('[data-preview-bio]', value('bio', 'Your artist story will appear here.'));
        setText('[data-preview-specialties]', value('specialties', 'Not added yet'));
        setText('[data-preview-experience]', value('experience', 'Not added yet'));
        updateImages();
        updateLinks();
        updateBooking();
    }

    form.addEventListener('input', updatePreview);
    form.addEventListener('change', updatePreview);
    updatePreview();
}());
