(function () {
  'use strict';
  document.querySelectorAll('[data-elev8-recipient-picker]').forEach(function (picker) {
    var search = picker.querySelector('.elev8-recipient-search');
    var options = Array.prototype.slice.call(picker.querySelectorAll('.elev8-recipient-option'));
    var selected = picker.querySelector('.elev8-recipient-selected');

    function refreshSelected() {
      var checked = options.filter(function (option) { return option.querySelector('input').checked; });
      selected.innerHTML = '';
      if (!checked.length) {
        selected.textContent = 'No recipients selected yet.';
        return;
      }
      checked.forEach(function (option) {
        var input = option.querySelector('input');
        var name = option.querySelector('strong').textContent;
        var chip = document.createElement('button');
        chip.type = 'button';
        chip.className = 'elev8-recipient-chip';
        chip.textContent = name + ' ×';
        chip.addEventListener('click', function () { input.checked = false; refreshSelected(); });
        selected.appendChild(chip);
      });
    }

    options.forEach(function (option) {
      option.querySelector('input').addEventListener('change', refreshSelected);
    });

    picker.querySelectorAll('.elev8-select-team').forEach(function (button) {
      button.addEventListener('click', function () {
        var group = button.closest('.elev8-recipient-group');
        var visible = Array.prototype.slice.call(group.querySelectorAll('.elev8-recipient-option')).filter(function (option) {
          return option.style.display !== 'none';
        });
        var shouldSelect = visible.some(function (option) { return !option.querySelector('input').checked; });
        visible.forEach(function (option) { option.querySelector('input').checked = shouldSelect; });
        refreshSelected();
      });
    });

    if (search) {
      search.addEventListener('input', function () {
        var query = search.value.trim().toLowerCase();
        options.forEach(function (option) {
          option.style.display = !query || option.getAttribute('data-search').indexOf(query) !== -1 ? '' : 'none';
        });
        picker.querySelectorAll('.elev8-recipient-group').forEach(function (group) {
          var anyVisible = Array.prototype.some.call(group.querySelectorAll('.elev8-recipient-option'), function (option) {
            return option.style.display !== 'none';
          });
          group.style.display = anyVisible ? '' : 'none';
        });
      });
    }
    refreshSelected();
  });
})();
