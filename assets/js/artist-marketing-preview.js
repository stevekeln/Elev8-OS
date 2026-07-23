(function(){
'use strict';
function update(){
 var subject=document.querySelector('.elev8-marketing-compose input[name="subject"]');
 var message=document.querySelector('.elev8-marketing-compose textarea[name="message"]');
 var url=document.querySelector('.elev8-marketing-compose input[name="promoted_url"]');
 var outSubject=document.querySelector('[data-elev8-marketing-preview-subject]');
 var outMessage=document.querySelector('[data-elev8-marketing-preview-message]');
 var outLink=document.querySelector('[data-elev8-marketing-preview-link]');
 if(outSubject){outSubject.textContent=subject&&subject.value.trim()?subject.value.trim():'Your email subject';}
 if(outMessage){outMessage.textContent=message&&message.value.trim()?message.value.trim():'Your message will appear here.';}
 if(outLink){var href=url&&url.value.trim()?url.value.trim():'#';outLink.href=href;outLink.style.display=href==='#'?'none':'inline-flex';}
}
document.addEventListener('input',function(e){if(e.target.closest('.elev8-marketing-compose')){update();}});
document.addEventListener('DOMContentLoaded',update);update();
})();
