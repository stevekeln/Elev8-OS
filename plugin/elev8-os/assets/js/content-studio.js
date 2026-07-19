(function(){
'use strict';
document.addEventListener('change',function(event){
    var select=event.target.closest('[data-elev8-template-select]');
    if(!select){return;}
    var option=select.options[select.selectedIndex];
    var form=select.closest('form');
    if(!form||!option){return;}
    ['subject','headline','body','cta','url'].forEach(function(field){
        var input=form.querySelector('[data-elev8-field="'+field+'"]');
        if(input&&option.dataset[field]!==undefined){input.value=option.dataset[field];}
    });
});
document.addEventListener('click',function(event){
    var selectButton=event.target.closest('[data-elev8-logo-select]');
    var removeButton=event.target.closest('[data-elev8-logo-remove]');
    if(selectButton){
        event.preventDefault();
        if(!window.wp||!wp.media){return;}
        var form=selectButton.closest('form');
        var frame=wp.media({title:'Choose Elev8 Arts Logo',button:{text:'Use this logo'},multiple:false,library:{type:'image'}});
        frame.on('select',function(){
            var attachment=frame.state().get('selection').first().toJSON();
            var input=form.querySelector('[data-elev8-logo-id]');
            var preview=form.querySelector('[data-elev8-logo-preview]');
            if(input){input.value=attachment.id||0;}
            if(preview){preview.innerHTML='<img src="'+(attachment.url||'')+'" alt="">';}
        });
        frame.open();
    }
    if(removeButton){
        event.preventDefault();
        var form=removeButton.closest('form');
        var input=form.querySelector('[data-elev8-logo-id]');
        var preview=form.querySelector('[data-elev8-logo-preview]');
        if(input){input.value='0';}
        if(preview){preview.innerHTML='<span>No logo selected</span>';}
    }
});

document.addEventListener('click',function(event){
    var button=event.target.closest('[data-elev8-copy-social]');
    if(!button){return;}
    var builder=button.closest('.elev8-campaign-builder');
    if(!builder){return;}
    var headline=builder.querySelector('[data-elev8-field="headline"]');
    var body=builder.querySelector('[data-elev8-field="body"]');
    var url=builder.querySelector('[data-elev8-field="url"]');
    var text=[headline&&headline.value.trim(),body&&body.value.trim(),url&&url.value.trim()].filter(Boolean).join('\n\n');
    var status=builder.querySelector('[data-elev8-copy-status]');
    if(!text){if(status){status.textContent='Add a headline or message first.';}return;}
    var done=function(){if(status){status.textContent='Social version copied. Add your image or video in Facebook or Instagram, then paste and post.';}};
    if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(text).then(done).catch(function(){if(status){status.textContent='Copy was blocked by the browser. Select the message and copy it manually.';}});return;}
    var area=document.createElement('textarea');area.value=text;area.setAttribute('readonly','');area.style.position='fixed';area.style.opacity='0';document.body.appendChild(area);area.select();try{document.execCommand('copy');done();}catch(e){if(status){status.textContent='Select the message and copy it manually.';}}document.body.removeChild(area);
});
})();
