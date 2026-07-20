(function () {
    'use strict';
    function normalize(value) { return String(value || '').toLowerCase().trim(); }
    function initPicker(picker) {
        var search = picker.querySelector('[data-elev8-user-search]');
        var select = picker.querySelector('[data-elev8-user-select]');
        var status = picker.querySelector('[data-elev8-user-search-status]');
        var identity = picker.querySelector('[data-elev8-selected-identity]');
        if (!search || !select) { return; }
        var options = Array.prototype.slice.call(select.options);
        function updateIdentity() {
            var option = select.options[select.selectedIndex];
            if (!identity || !option || option.value === '0') { if (identity) identity.innerHTML = ''; return; }
            var state = option.getAttribute('data-status') || 'available';
            var label = option.getAttribute('data-status-label') || '';
            identity.className = 'elev8-selected-identity is-' + state;
            identity.textContent = label;
        }
        function filterOptions() {
            var query = normalize(search.value), visible = 0;
            options.forEach(function (option, index) {
                if (index === 0) { option.hidden = false; return; }
                var matches = !query || normalize(option.getAttribute('data-search') || option.textContent).indexOf(query) !== -1;
                option.hidden = !matches;
                if (matches) { visible += 1; }
            });
            if (status) { status.textContent = query ? (visible === 1 ? '1 matching account' : visible + ' matching accounts') : ''; }
        }
        search.addEventListener('input', filterOptions);
        search.addEventListener('keydown', function (event) { if (event.key === 'Escape') { search.value = ''; filterOptions(); search.focus(); } });
        select.addEventListener('change', updateIdentity);
        filterOptions(); updateIdentity();
    }
    document.addEventListener('DOMContentLoaded', function () { document.querySelectorAll('[data-elev8-user-picker]').forEach(initPicker); });
}());
