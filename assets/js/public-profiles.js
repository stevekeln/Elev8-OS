(function($){
'use strict';
function renderPreview($control,url){
    var $preview=$control.find('[data-media-preview]');
    $preview.empty();
    if(url){
        $('<img>',{src:url,alt:''}).appendTo($preview);
        $preview.removeClass('is-empty').addClass('has-image');
        $control.find('[data-media-remove]').removeClass('is-hidden');
        $control.find('[data-media-select]').text('Replace image');
    }else{
        $('<span>',{'class':'dashicons dashicons-format-image','aria-hidden':'true'}).appendTo($preview);
        $('<em>').text('No image uploaded').appendTo($preview);
        $preview.removeClass('has-image').addClass('is-empty');
        $control.find('[data-media-remove]').addClass('is-hidden');
        $control.find('[data-media-select]').text('Upload image');
    }
}
$(document).on('click','[data-media-select]',function(e){
    e.preventDefault();
    var $control=$(this).closest('[data-elev8-media-control]');
    var frame=wp.media({
        title:$control.data('media-title')||'Choose image',
        button:{text:'Use this image'},
        library:{type:'image'},
        multiple:false
    });
    frame.on('select',function(){
        var attachment=frame.state().get('selection').first().toJSON();
        var url=(attachment.sizes&&attachment.sizes.large)?attachment.sizes.large.url:attachment.url;
        $control.find('[data-media-id]').val(attachment.id||0);
        $control.find('[data-media-legacy-url]').val('');
        renderPreview($control,url||'');
    });
    frame.open();
});
$(document).on('click','[data-media-remove]',function(e){
    e.preventDefault();
    var $control=$(this).closest('[data-elev8-media-control]');
    $control.find('[data-media-id]').val('0');
    $control.find('[data-media-legacy-url]').val('');
    renderPreview($control,'');
});
})(jQuery);
