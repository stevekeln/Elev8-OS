(function(){
  'use strict';
  function init(shell){
    var button=shell.querySelector('.elev8-app-shell__user-button');
    var menu=shell.querySelector('.elev8-app-shell__menu');
    if(!button||!menu){return;}
    function close(){menu.hidden=true;button.setAttribute('aria-expanded','false');}
    button.addEventListener('click',function(event){event.stopPropagation();var open=menu.hidden;menu.hidden=!open;button.setAttribute('aria-expanded',open?'true':'false');});
    document.addEventListener('click',function(event){if(!shell.contains(event.target)){close();}});
    document.addEventListener('keydown',function(event){if(event.key==='Escape'){close();button.focus();}});
  }
  document.addEventListener('DOMContentLoaded',function(){document.querySelectorAll('[data-elev8-app-shell]').forEach(init);});
})();
