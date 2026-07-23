document.addEventListener('click', function (event) {
    var button = event.target.closest('.elev8-copy-button');
    if (!button) return;
    var card = button.closest('.elev8-checkin-link-card');
    var input = card ? card.querySelector('.elev8-copy-source') : null;
    if (!input) return;
    navigator.clipboard.writeText(input.value).then(function () {
        var original = button.textContent;
        button.textContent = 'Copied';
        window.setTimeout(function () { button.textContent = original; }, 1400);
    });
});
