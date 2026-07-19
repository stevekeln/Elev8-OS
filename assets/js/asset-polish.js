(function ($) {
    'use strict';
    function syncOrder() {
        var ids = [];
        $('#elev8-sortable-gallery .elev8-gallery-sort-item').each(function () {
            if (!$(this).find('input[type="checkbox"]').is(':checked')) {
                ids.push($(this).data('attachment-id'));
            }
        });
        $('#elev8-gallery-order').val(ids.join(','));
    }
    $(function () {
        var $gallery = $('#elev8-sortable-gallery');
        if (!$gallery.length) return;
        $gallery.sortable({items: '.elev8-gallery-sort-item', update: syncOrder});
        $gallery.on('change', 'input[type="checkbox"]', syncOrder);
        syncOrder();
    });
})(jQuery);
