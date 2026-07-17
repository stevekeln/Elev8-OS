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
})();
